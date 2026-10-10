# Online registration database setup (MariaDB 10.11)

The complete registration workflow requires migrations 001–007. Docker starting a database does
not install these tables, especially when a database volume already exists. Core
RosarioSIS must be installed separately through its established installation flow.
Never reset a volume or drop tables to resolve a registration schema error.

For fresh Windows/Docker installations, baseline setup, permissions, SMTP and the
registration-to-enrollment checklist, see
[the collaborator guide](../docs/deployment/windows-registration-setup.md).

The maintenance service is explicit and disabled during ordinary `compose up`.
It uses the same `rosariosis/config.inc.php` as the website. Inspect status first:

```powershell
docker compose run --rm registration-migrations
```

Status is read-only. Missing or incomplete schema makes the public registration
and payment pages return HTTP 503 without attempting applicant queries.

Before applying, make a complete backup (choose a new timestamp for every run):

```powershell
New-Item -ItemType Directory -Force database/backups | Out-Null
docker compose exec -T db sh -c 'MYSQL_PWD=$MARIADB_ROOT_PASSWORD mariadb-dump -uroot --single-transaction --routines --events --triggers --hex-blob --databases $MARIADB_DATABASE > /tmp/registration-before-YYYYMMDD-HHMMSS.sql'
# Stop if the dump command failed. Copy the dump only after a successful exit.
docker compose cp db:/tmp/registration-before-YYYYMMDD-HHMMSS.sql database/backups/registration-before-YYYYMMDD-HHMMSS.sql
Get-FileHash database/backups/registration-before-YYYYMMDD-HHMMSS.sql -Algorithm SHA256
```

Coordinate a maintenance window and avoid concurrent schema changes during backup
and migration. `--single-transaction` gives a consistent InnoDB snapshot; legacy
nontransactional tables require writes to be paused for a consistent full backup.
Keep backups private, outside version control, and verify restore procedures.
Back up applicant upload files separately before any upload maintenance.

Ask the database owner for approval, naming this backup and the target database.
Only after approval:

```powershell
docker compose run --rm registration-migrations --apply --approved --backup=/opt/abugida/database/backups/registration-before-YYYYMMDD-HHMMSS.sql
docker compose run --rm registration-migrations
```

Fresh databases receive the registration tables in order. Existing databases with
untracked or partly applied migrations retain compatible objects and rows.
The runner uses MariaDB's `ADD ... IF NOT EXISTS` guards without changing the
collaborator's SQL files. Existing incompatible column types, nullability or named
indexes stop preflight for manual review rather than converting data. Postchecks
verify the migration objects before recording filename, SHA-256 checksum, and
completion time in `abugida_schema_migrations`. A named database lock serializes
runners. Changed checksums stop execution. Repeated successful runs do no work.
MariaDB DDL commits implicitly: interrupted migrations may leave some additions,
so fix the reported cause and rerun with a valid backup and renewed approval.
Never assume a transaction can roll back schema changes.

A fresh registration schema does not create students, users, grade fees or school
data. Preserve the established RosarioSIS installation and registrar workflows.

After approval, the targeted HTTP test creates six clearly named test applications
(one per Grade 7–12), using both learning approaches. It checks draft save, a new
session resume, missing uploads, invalid MIME, CSRF, saved-file preservation after
failed replacement, successful PDF/PNG submission and read-only submitted state.
It retains test applications and uploads for inspection, and touches no core SIS
records. Run it only against the approved local test environment:

```powershell
docker compose run --rm --no-deps --volume "${PWD}/rosariosis:/opt/abugida/rosariosis:ro" --entrypoint php registration-migrations /opt/abugida/database/tests/registration-http.php --approved
```

The isolated migration harness is `database/tests/migrations.php`. It requires a
separate MariaDB container named `abugida-registration-test` on the Compose
network, with no published ports, root password `registration-test-only`, and
empty databases `reg_fresh`, `reg_partial`, `reg_complete`, `reg_incompatible`.
Before running, back up those four empty databases with `mariadb-dump --databases`
to `database/backups/registration-isolated-empty.sql`. The test creates sentinel
applicant rows and checks fresh setup, partial migrations, adoption, repeat runs,
checksum rejection and incompatible-schema rejection. Use new empty test
databases for each suite execution; never reset the SIS database to rerun tests.
For the core-data comparison, restore the pre-migration backup into a separate
`abugida_verify` database in that container, changing only the quoted database
identifier. Create the original `abugida_user`@`%` view-definer account and grant
it SELECT on the restored database before restoring RosarioSIS views. Never
restore a test backup over the running SIS database.

```powershell
docker compose run --rm --no-deps --entrypoint php registration-migrations /opt/abugida/database/tests/migrations.php --approved
```

The migration runner supports `ABUGIDA_MIGRATION_CONFIG` for an explicit CLI
configuration path. The test harness uses a restricted fixture configuration;
ordinary maintenance uses the website's existing configuration by default.
