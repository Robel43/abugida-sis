# Stage 2: review, manual payment and SMTP

## Confirmed implementation

Registrar and Finance actions use existing RosarioSIS Can Use/Can Edit program
permissions (admin-based staff profiles). Assign the relevant programs under
Users > User Profiles or User Permissions; no permission grants are added by this
feature. Registrar and Finance authority remain separate.

| Workflow | Menu | Local URL |
| --- | --- | --- |
| Applicant registration/correction | Public | http://localhost:8090/registration.php |
| Registrar queue | Students > Online Applications | http://localhost:8090/Modules.php?modname=Custom/ApplicationReview.php |
| Grade-based fees | Student Billing > Registration Fees | http://localhost:8090/Modules.php?modname=Custom/RegistrationFees.php |
| Applicant manual payment | Approval email/payment link | http://localhost:8090/registration-payment.php?token=APPROVAL_TOKEN |
| Finance queue | Student Billing > Application Payments | http://localhost:8090/Modules.php?modname=Custom/FinanceApplications.php |
| SMTP test | Users > Email Test | http://localhost:8090/Modules.php?modname=Custom/EmailTest.php |

Approval requires a configured fee for the selected school/year/grade. It records
that fee, payment instructions and a random payment token. Rejection requires a
reason. Applicants correct DECLINED applications using the same phone number and
resubmit the same record. Finance reviews only PAYMENT_SUBMITTED receipts;
rejection returns them to PAYMENT_DECLINED for another upload. Verification moves
them to PAYMENT_VERIFIED. No payment gateway is used. Final Registrar confirmation
is an existing, separate explicit POST action that creates permanent records;
it is protected against GET/CSRF and duplicate confirmation. The initial Stage 2
suite leaves permanent student records untouched. The separate `enrollment-http.php`
regression now verifies final confirmation, the credentials email and standard
student portal login using one synthetic permanent student in an isolated database.

Transactions lock the applicant and commit each decision with its audit event and
notification. Receipt submission and application resubmission also preserve audit
history and reject stale/duplicate actions. Old upload files are retained. Direct
upload-directory access is denied; application-file.php checks the relevant
Registrar/Finance permission, confines files to upload storage, and sends no-store,
nosniff and sandbox headers.

## Configure SMTP locally

No external delivery has been verified. Automated tests use a synthetic SMTP
server on the isolated Docker network, with certificate verification and fake
authentication credentials. Fixture credentials in database/tests have no real
account or delivery privileges.

1. Copy `.env.smtp.example` to `.env.smtp` in the repository root. `.env.smtp` is
   ignored by Git. Restrict access to this file on your computer. Do not paste any
   credentials into chat, add them to tracked files, or print `docker compose
   config` without `--quiet` after configuring secrets.
2. Set ABUGIDA_SMTP_HOST, PORT, ENCRYPTION, AUTH, USERNAME, PASSWORD, FROM and
   optional FROM_NAME. Set ABUGIDA_BASE_URL to the URL applicants can actually
   reach, including a subdirectory if applicable. Set ABUGIDA_PAYMENT_INSTRUCTIONS
   to your demonstration bank/account and receipt instructions. Environment values
   take precedence over legacy AbugidaMail* variables in untracked config.inc.php.
3. Gmail: use smtp.gmail.com, 587/tls (STARTTLS) or 465/ssl (implicit TLS), AUTH=true,
   your full email address and an App Password rather than the normal account
   password. App Passwords require an eligible account with 2-Step Verification:
   [Google App Password instructions](https://support.google.com/accounts/answer/185833)
   and [Google SMTP setup](https://support.google.com/a/answer/176600).
4. Institutional SMTP: use the host, port, approved sender, encryption and
   credentials provided by your administrator. An authorized SMTP relay can use
   AUTH=false; authenticated plaintext SMTP is blocked. TLS certificate validation
   is always enabled; private institutional CAs must be installed in the container
   trust store rather than disabling verification.
5. Load the local environment by running `docker compose up -d web`. This recreates
   the web service as needed and retains the database volume.
6. Log in with Email Test permission and use Users > Email Test. It reports safe
   host/port/TLS/configuration diagnostics and categorized errors, without showing
   usernames, passwords, SMTP replies, tokens or message bodies. Send to a mailbox
   you control and confirm receipt there. A successful send means SMTP acceptance,
   not confirmed mailbox delivery. Authentication/TLS/connectivity failures appear
   as credential-free categories in the web-container error log.

Without SMTP settings, review decisions still commit and notifications become
FAILED. Payment instructions have a safe demonstration fallback that directs
applicants to the school for actual account details.

## Outbox and retries

Migration 007 adds only abugida_email_outbox. It was applied after a full database
backup and explicit approval. Existing migrations 001–006 are unchanged.

Each audit event can have only one notification. Applicant detail pages show
notification state, attempts and a credential-free failure reason. Registrar can
retry Registrar messages and Finance can retry Finance messages using **Retry
notification only**. PENDING/FAILED retries never repeat approval/rejection or
change a payment token. SENT messages cannot be resent. Obsolete pending/failed
messages are cancelled when the application has progressed beyond their status.

A locked claim prevents simultaneous retry attempts. A stable Message-ID further
identifies each notification. SMTP and the database cannot provide an atomic
mailbox delivery guarantee: disconnect after DATA may leave delivery uncertain.
UNKNOWN and interrupted SENDING messages are blocked from automatic retry; have
the mail administrator confirm the server outcome before any manual reconciliation.
This conservatively avoids duplicate messages after an uncertain acceptance.

## Targeted test results (2026-10-10)

- Stage 2 HTTP suite: 46 workflow, authorization, CSRF, upload, content, notification
  and duplicate-action checks passed, using actual RosarioSIS logins/permissions.
- SMTP transport suite: 10 checks passed (initialization, DNS/TCP, STARTTLS,
  authentication failure, authenticated plaintext refusal, missing configuration,
  connection failure, recipient rejection, implicit TLS and URL validation).
- Transaction/permission suite: 12 checks passed, including complete rollback,
  fee validation, obsolete notification cancellation, and read-only/denied/Finance
  restrictions. An additional untrusted-certificate test passed.
- Concurrent approval and concurrent retry tests passed: one decision/audit/outbox
  and one SMTP acceptance respectively.
- Existing Stage 1 regression passed for all six grades and both approaches,
  including create/save/new-session resume, required uploads, invalid MIME/CSRF,
  submission, and read-only submitted applications.
- All 95 main core SIS table checksums match the pre-Stage-2 backup.
- Targeted PHP lint and Compose validation passed. No external SMTP acceptance or
  mailbox delivery has been tested. An early HTTP harness CSRF-token assumption
  and a database readiness race were fixed; the complete fresh-container suite passed on rerun.

Tests live in database/tests/stage2-*.php and docker-compose.stage2.yaml. They
require fresh disposable containers and a backup restore into the isolated DB;
do not rerun fixtures in an existing SIS database. Restore the pre-Stage-2 backup
only into the isolated stage2-db, apply 007 there through the approved runner,
then snapshot it before fixtures. Generate a short-lived certificate with DNS SAN
abugida-stage2-smtp in ignored tmp/stage2 and configure the isolated test trust
store through stage2-php.ini. All test endpoints and credentials are confined to
those containers. Tests never contact Gmail or institutional SMTP.

Reproduce the complete isolated suite after approval with the existing backup:

```powershell
./database/tests/run-stage2.ps1 -BackupPath database/backups/abugida-before-stage2-20261010.sql -Approved
```

The runner refuses existing test containers or a previous SMTP capture log. Archive
ignored tmp/stage2 under another name before a new run. It retains test containers
for inspection, with synthetic uploads isolated under tmp/stage2/uploads. After
inspection, remove only the test containers:

```powershell
docker compose -f database/tests/docker-compose.stage2.yaml -p abugida-stage2 down
```

## Changed files

Final handoff verification additionally passed fresh baseline installation and all
001–007 migrations, 46 Stage 2 HTTP checks, 17 enrollment/login checks, the Finance
form regression, six Grade 7–12 registration scenarios, ten direct SMTP checks,
untrusted TLS rejection, and targeted PHP lint. SMTP delivery was accepted only by
the local TLS/authenticated fixture, not an external mailbox. The default student
portal remains unchanged. See [the Windows setup guide](../deployment/windows-registration-setup.md).

Stage 2 code: ProgramFunctions/AbugidaEmail.fnc.php,
ProgramFunctions/AbugidaWorkflow.fnc.php, modules/Custom/ApplicationReview.php,
modules/Custom/FinanceApplications.php, modules/Custom/EmailTest.php,
modules/Custom/RegistrationFees.php, application-file.php, registration.php,
and registration-payment.php (all under rosariosis).

Setup: .gitignore, .env.smtp.example, docker-compose.yaml,
database/migrate-registration.php, database/migrations/007_stage2_email_outbox.sql.

Tests: database/tests/docker-compose.stage2.yaml, run-stage2.ps1,
stage2-config.inc.php, stage2-php.ini, stage2-fixtures.php, stage2-http.php,
stage2-mail.php, stage2-smtp.php, stage2-transactions.php, stage2-untrusted-tls.php,
stage2-concurrency.php, stage2-concurrency-action.php,
stage2-core-preservation.php. Existing registration-http.php gained isolated test
URL/root options; migrations.php now checks the dynamic migration count.

Documentation: this file and registration-approval-payment-workflow.md.

The local commit also includes the already-completed, previously uncommitted
Stage 1 dependencies: database/README-registration.md, migration runner and its
migration-config.php/migrations.php tests, registration-schema.inc.php and
online-registration.md. No Stage 1 schema migration files were changed.
