# Registration feature: Windows Docker installation and testing

Use branch `feature/online-registration-updated`. This delivers Stage 1 registration,
Stage 2 Registrar/Finance review and notifications, and the existing final Registrar
confirmation/student account flow. The standard RosarioSIS student portal is retained;
there is no custom dashboard, Stage 3 implementation, or online payment gateway.

GitHub contains source and migrations, not the developer's MariaDB database, student
records, uploads, payment tokens, generated passwords or backups. Initialize your own
database and use synthetic applicants. The bundled `rosariosis/rosariosis_mysql.sql`
is the clean English RosarioSIS v12.9.4 installer schema, verified against
[upstream release source](https://github.com/francoisjacquet/rosariosis/blob/v12.9.4/rosariosis_mysql.sql).
Its default school, template student and template staff are upstream demonstration data.

## Clone and start

Install Git and Docker Desktop with Linux containers enabled. Run PowerShell from a
writable directory. Docker supplies PHP 8.1/Apache and MariaDB 10.11; host PHP is optional.
Use a current Docker Compose v2 supporting optional `env_file` entries (2.24.0 or newer).
Ports 8090 and 3307 must be free.

```powershell
git clone --branch feature/online-registration-updated https://github.com/Robel43/abugida-sis.git
Set-Location abugida-sis
git checkout feature/online-registration-updated
git branch --show-current
Copy-Item rosariosis/config.inc.sample.php rosariosis/config.inc.php
```

If `config.inc.php` already exists, preserve it instead of copying over it. Edit the
untracked `rosariosis/config.inc.php` locally to use these supplied Docker development
settings (the Compose database service defines these defaults):

```php
$DatabaseType = 'mysql';
$DatabaseServer = 'db';
$DatabasePort = 3306;
$DatabaseUsername = 'abugida_user';
$DatabasePassword = 'abugida_dev_password';
$DatabaseName = 'abugida_sis';
$DefaultSyear = '2026';
```

Use `db:3306` inside Docker. A host database client uses `localhost:3307`. These are
public development defaults, not production credentials. Keep configuration local.

```powershell
docker compose config --quiet
docker compose up -d --build
docker compose ps
docker compose exec -T db sh -c 'MYSQL_PWD=$MARIADB_PASSWORD mariadb -u"$MARIADB_USER" "$MARIADB_DATABASE" -e "SELECT 1"'
```

Wait for `SELECT 1` to succeed. On a **new, empty** database, open
[InstallDatabase.php](http://localhost:8090/InstallDatabase.php). It installs the baseline
English database. The optional language-translation SQL is not included in this
feature package; retain English. Do not run installation over an existing database.
Open [index.php](http://localhost:8090/index.php), log in with upstream template
`admin` / `admin`, and change that password immediately under **Users → My Preferences**.
Preserve existing databases; never use `docker compose down -v` or delete volumes.

## Apply migrations 001–007

The explicit maintenance service runs `database/migrate-registration.php` with the
same local connection configuration as the website. It does not run on normal startup.

```powershell
docker compose run --rm registration-migrations
```

This reads status only. It should list seven migrations as pending on a new baseline.
Before applying to either fresh or existing databases, create a new full backup:

```powershell
New-Item -ItemType Directory -Force database/backups | Out-Null
$registrationBackup = 'registration-before-' + (Get-Date -Format 'yyyyMMdd-HHmmss') + '.sql'
docker compose exec -T db sh -c 'MYSQL_PWD=$MARIADB_ROOT_PASSWORD mariadb-dump -uroot --single-transaction --routines --events --triggers --hex-blob --databases "$MARIADB_DATABASE" > /tmp/registration-before.sql'
if ($LASTEXITCODE -ne 0) { throw 'Backup failed; do not migrate.' }
docker compose cp db:/tmp/registration-before.sql "database/backups/$registrationBackup"
if ($LASTEXITCODE -ne 0) { throw 'Backup copy failed; do not migrate.' }
Get-FileHash "database/backups/$registrationBackup" -Algorithm SHA256
```

Pause application writes for a consistent backup and coordinate approval with the
database owner, identifying the database and backup. After approval:

```powershell
docker compose run --rm registration-migrations --apply --approved "--backup=/opt/abugida/database/backups/$registrationBackup"
if ($LASTEXITCODE -ne 0) { throw 'Migration failed; inspect the error before proceeding.' }
docker compose run --rm registration-migrations
```

The runner applies 001–007 in filename order and records checksums in
`abugida_schema_migrations`. It can adopt compatible pre-existing/partially applied
registration objects without deleting records. It stops on incompatible schemas or
changed checksums. Do not edit applied migrations or manually bypass the runner.
For backup and recovery details, see [database setup](../../database/README-registration.md).

## School, grades, roles and fees

As administrator, choose the correct school and school year (the baseline is 2026).
Under **School → Configuration → Modules**, enable **Students**, **Users**,
**Student Billing** and **Custom**. The Custom module contributes the registration menus.
Under **School → Grade Levels**, ensure one unambiguous grade for each number 7–12.
Titles such as `Grade 7` or `7th Grade` and short names `7`–`12` are recognized.
Configure the current-year school calendar under **School → Calendars** and an
Add enrollment code under **Students → Enrollment Codes**; final enrollment
uses the default calendar and default Add code when available.

Under **Users → User Profiles**, create Registrar/Finance profiles based on the
Administrator profile type and assign staff to them. Custom actions require staff
with RosarioSIS admin-type profiles, not student/parent/teacher profiles. Configure
**Can Use / Can Edit** for each program; individual **Users → User Permissions**
exceptions can further control access. Log out/in after changing role permissions.

| Role | Program | Can Use | Can Edit |
| --- | --- | --- | --- |
| Registrar | Students → Online Applications (`Custom/ApplicationReview.php`) | Yes | Yes |
| Finance | Student Billing → Application Payments (`Custom/FinanceApplications.php`) | Yes | Yes |
| Read-only reviewer | Appropriate review program | Yes | No |
| Administrator | Student Billing → Registration Fees (`Custom/RegistrationFees.php`) | Yes | Yes |
| Email administrator | Users → Email Test (`Custom/EmailTest.php`) | Yes | Yes |

Restrict Registrar and Finance programs separately. Configure fees for all six grades
under **Student Billing → Registration Fees** in the selected school/year. Approval
automatically snapshots the configured grade fee and payment instructions. Missing
fees block approval. Manual verification does not create tuition transactions or
initiate a bank transfer.

## Configure SMTP privately

```powershell
Copy-Item .env.smtp.example .env.smtp
```

Preserve an existing `.env.smtp`. Edit it locally; do not paste credentials into chat,
commit the file, or print `docker compose config` without `--quiet`. Set:

```dotenv
ABUGIDA_SMTP_HOST=smtp.gmail.com
ABUGIDA_SMTP_PORT=587
ABUGIDA_SMTP_ENCRYPTION=tls
ABUGIDA_SMTP_AUTH=true
ABUGIDA_SMTP_USERNAME=your-school-account@example.com
ABUGIDA_SMTP_PASSWORD=REPLACE_LOCALLY_WITH_APP_PASSWORD
ABUGIDA_SMTP_FROM=your-school-account@example.com
ABUGIDA_SMTP_FROM_NAME=Abugida SIS
ABUGIDA_BASE_URL=http://localhost:8090
ABUGIDA_PAYMENT_INSTRUCTIONS="Use your demonstration bank details and include the application reference. Upload the receipt afterward."
```

For Gmail, use your full Gmail/Workspace address and a Google App Password, not the
normal sign-in password. Enable 2-Step Verification and create the App Password in
[Google account settings](https://support.google.com/accounts/answer/185833). App
Passwords may be unavailable for accounts restricted by organization policy or
Advanced Protection. Remove display spaces from the generated App Password locally.
For institutional SMTP, use your institution's host, sender/authentication policy,
and port/encryption: `587` with `tls` (STARTTLS), or `465` with `ssl` (implicit TLS).
Authenticated SMTP requires encryption; certificate checks remain enabled.

Set `ABUGIDA_BASE_URL` to the address applicants can reach. `localhost` works only
on the same computer; email recipients on another computer need a reachable host URL.
Environment settings override the legacy local `$AbugidaMail*` settings.

```powershell
docker compose up -d --force-recreate web
```

This recreates the web container to load its environment and preserves the database
volume and bind-mounted source/uploads. Use **Users → Email Test** to send a test to
your own mailbox and read its sanitized diagnostics. SMTP server acceptance is not
proof that mail reached the inbox; check spam and server logs. Real Gmail/institutional
delivery must be verified in your environment.

Registrar/Finance decisions commit before delivery. Their outbox panel provides
**Retry notification only** for PENDING/FAILED messages. Delivered notifications
cannot be resent through that action. UNKNOWN/SENDING results require delivery
confirmation from the mail administrator before a resend is considered.
Final enrollment currently sends its credentials email directly through the same
SMTP transport; it has no outbox retry. Verify SMTP before final confirmation. If
that email fails, the account remains created: do not enroll the applicant twice;
use the standard administrative password-reset procedure.

## Registration-to-student-login checklist

Use synthetic names, test phone numbers, an email inbox you control, and non-sensitive
PDF/PNG/JPEG documents. Record the generated application reference locally.

- [ ] Open [online registration](http://localhost:8090/registration.php).
- [ ] Start an application; select Grade 7–12 and Online or Distance Learning; save a draft.
- [ ] Close the applicant session, resume with the same phone number, and verify saved fields.
- [ ] Upload the supporting document and Fayda ID (synthetic), then submit.
- [ ] Log in as Registrar/administrator; open **Students → Online Applications**.
- [ ] Approve and confirm the grade fee, or reject with a reason; correct and resubmit the same record.
- [ ] Confirm the personalized decision email reaches your inbox.
- [ ] Open the approval email's payment link; check amount and payment instructions.
- [ ] Upload a synthetic payment receipt; confirm PAYMENT_SUBMITTED / SUBMITTED.
- [ ] Open **Student Billing → Application Payments**, then **View Payment**; inspect the receipt.
- [ ] Verify the receipt (PAYMENT_VERIFIED / VERIFIED), or reject with a mandatory reason and upload a corrected receipt.
- [ ] Confirm the Finance email; refresh/repeat the action and verify no repeated decision or email.
- [ ] As Registrar, open the verified application and click **Final Confirm & Create Student Account**.
- [ ] Confirm ACTIVE, the permanent Student ID, username and current-school/year enrollment.
- [ ] Confirm the account-created email containing the generated username and temporary password.
- [ ] Log in at [index.php](http://localhost:8090/index.php) using those credentials; change the password.
- [ ] Verify the default RosarioSIS student portal/menu and student record access. No custom dashboard is expected.

Staff URLs after login:

```text
http://localhost:8090/Modules.php?modname=Custom/ApplicationReview.php
http://localhost:8090/Modules.php?modname=Custom/FinanceApplications.php
http://localhost:8090/Modules.php?modname=Custom/RegistrationFees.php
http://localhost:8090/Modules.php?modname=Custom/EmailTest.php
```

The applicant payment URL is `http://localhost:8090/registration-payment.php?token=...`;
obtain the actual token only from that synthetic application's approval link.

## Focused automated tests

Source: `database/tests/`. See [Stage 2 test instructions](../features/stage2-testing-smtp.md)
for the separate disposable MariaDB/SMTP suite and [database setup](../../database/README-registration.md)
for migration/registration tests. Tests require explicit approval and synthetic fixtures.
Never restore a test backup over your working SIS database. The full suite expects
fresh `abugida-stage2-*` containers and a fresh ignored `tmp/stage2` runtime; preserve
previous logs/results before choosing a new runtime for another run.

```powershell
# Use your own private, successful backup, not a developer's database dump.
powershell -ExecutionPolicy Bypass -File database/tests/run-stage2.ps1 -Approved -BackupPath "database/backups/$registrationBackup"
# Reset only the synthetic SMTP fixture's one-time failure behavior between suites.
docker restart abugida-stage2-smtp
docker exec abugida-stage2-web php /test/finance-decisions-http.php
docker restart abugida-stage2-smtp
docker exec abugida-stage2-web php /test/enrollment-http.php
```

The suite uses fake SMTP credentials and a local TLS SMTP fixture. It never contacts
Gmail/institutional servers. Tests leave synthetic records/results for inspection.
`enrollment-http.php` runs the Stage 2 HTTP sequence and then creates exactly one
synthetic permanent student in the isolated database, checks the credentials email,
duplicate confirmation protection and the standard student portal login. Run these
tests with the baseline first-login/password settings; if first-login agreements or
forced password changes are enabled, complete that additional flow manually.

## Troubleshooting and demonstration limitations

| Symptom | Check |
| --- | --- |
| `config.inc.php` missing / wrong database | Create the untracked configuration; use `db`, port 3306, and the Compose development settings. |
| Core table missing | On an empty database, run `InstallDatabase.php` before registration migrations. A partial installation needs investigation, not a reset. |
| Installer SQL missing | Confirm `rosariosis/rosariosis_mysql.sql` is present on this branch; do not substitute a local database dump. |
| `abugida_applicants` missing / registration HTTP 503 | Run migration status; back up and obtain approval, then apply all 001–007 with the runner. |
| Migration checksum/schema conflict | Stop and inspect status and the exact conflict. Preserve data and files; do not drop tables or change tracking rows to suppress the error. |
| Menus hidden / access denied | Enable Custom, Students, Users and Student Billing; check admin-type profile and program Can Use/Can Edit permissions. |
| Grade fee missing / final grade not found | Configure Grades 7–12 in the selected school; set each fee for the selected school/year. |
| Verify Payment reports an invalid decision | Confirm the latest feature commit is checked out, then reload the detail page; both decision forms use hidden decision fields. |
| Upload denied | Confirm the bind-mounted `assets/FileUploads/ApplicantDocuments` directory is writable by Apache (`www-data`) and files satisfy the page's type/size validation. |
| SMTP authentication/TLS/connectivity failure | Check private environment values, Gmail account eligibility, network/firewall access and Email Test diagnostics. Keep TLS checks enabled. Recreate web after environment changes. |
| Email link points to the wrong host | Fix ABUGIDA_BASE_URL locally and recreate web; already queued messages retain their original content. |
| Student login fails | Confirm ACTIVE and enrollment in the current school/year, exact generated credentials and delivered email; use standard password reset if necessary. |

This is a local demonstration system. Phone-only draft continuation is not strong
identity verification: someone knowing the phone number may resume/access the
application. Payment links are bearer tokens and must remain private. Temporary
passwords are sent by email; email is not an end-to-end secure credential channel.
Enable **School → Configuration → Force Password Change on First Login** when
available and have students change credentials immediately. Use synthetic data and
controlled inboxes. Docker exposes development ports and public default credentials;
production deployment, secure identity recovery, HTTPS and SMTP deliverability are
outside this feature's tested scope.
