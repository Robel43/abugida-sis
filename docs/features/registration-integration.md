# Online registration integration

`feature/online-registration-integration` combines the grading/portal/re-registration
branch with the transactional registration and SMTP/outbox branch. The merge retains
the semester percentage model, XLSX import, class rank and reports, student dashboard,
courses/grades/payments/profile, re-registration, and secure receipt endpoint.

The SMTP environment settings and optional PDF converter configuration coexist.
The upstream `rosariosis_mysql.sql` remains a baseline installer, not a snapshot of
the complete Abugida schema; never install it over an existing database.

## Migration compatibility

The outbox migration is now `014_stage2_email_outbox.sql`, after the existing feature
migrations 007–013. Its contents are unchanged. `migrate-registration.php` explicitly
runs 001–006 and 014, preserving the boundary between registration DDL and academic/
portal permission migrations. It adopts the old outbox tracking filename by checksum
and schema verification without deleting tracking history or replaying its DDL.
See [database setup](../../database/README-registration.md) for fresh versus existing installs.

## Verification

Executed during integration:

- PHP 8.3 lint of 464 PHP files: no syntax errors.
- `database/tests/integration-core.php`: 32 assertions covering migration selection,
  legacy tracking/checksum protection, SMTP configuration and URL safety, CSRF,
  arithmetic semester averages, competition ranks, pending full-year results,
  85.00% full-year averages, grade replacement recalculation, and date/school-scoped
  re-registration eligibility. Academic SQL runs against an isolated in-memory SQLite
  fixture. It never reads or changes the school's database.
- Grading, student portal, re-registration and secure re-registration receipt source
  files are unchanged from the combined input branch.

The integration execution account could not access Docker or WSL. MariaDB migration,
transaction/concurrency, actual SMTP delivery and full browser workflow tests have
therefore **not been rerun on the merged branch**. Prior branch-specific results do not
establish that the entire merged system passes. The HTTP enrollment suite now checks
the Abugida dashboard and student identity isolation across all five self-service pages.

Run the offline checks with PHP + PDO SQLite:

```sh
php -d extension=pdo_sqlite database/tests/integration-core.php
```

Then run the isolated Docker suite described in
[Stage 2 testing](stage2-testing-smtp.md), including enrollment and Finance regression
checks. Use a full private backup and disposable test containers. Complete the
browser applicant → Registrar → Finance → new student → scheduling → XLSX grades →
reports → student portal → billing → re-registration test before production rollout.
No running database migrations or live configuration were changed by this integration.
