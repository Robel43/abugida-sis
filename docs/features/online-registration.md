# Feature: Public Online Registration

## Purpose

Provide a standalone applicant-facing registration page for Abugida SIS without creating a permanent student or user account during the application stage.

## URL

```text
/registration.php
```

For local Docker development:

```text
http://localhost:8090/registration.php
```

## Current Scope

The applicant can:

- enter a phone number to start or resume an application;
- enter first name;
- enter last name;
- enter email;
- select Grade 7 through Grade 12;
- upload one PDF or PNG supporting document;
- save an incomplete application as a draft;
- leave the page and later continue using the same phone number;
- see previously saved values preloaded;
- submit a completed application.

The feature does **not** create a permanent RosarioSIS student record or login account.

## Current Access Model

For this first implementation, the phone number alone is used to locate and resume an unfinished application.

There is currently:

- no OTP;
- no SMS;
- no password;
- no email verification.

This is intentionally a prototype-stage access model and should be strengthened before public production deployment because anyone who knows an applicant's phone number could potentially resume that applicant's draft.

## Applicant Status

Current statuses:

```text
DRAFT
SUBMITTED
```

A DRAFT application is editable.

A SUBMITTED application is read-only on the public page.

Registrar review states such as APPROVED_FOR_PAYMENT and DECLINED will be added in the Registrar workflow feature.

## Data Storage

Applicant records are stored in:

```text
abugida_applicants
```

This table is separate from permanent student records.

The phone number is currently unique, meaning one active applicant record per phone number in this first version.

## File Upload

Allowed formats:

```text
PDF
PNG
```

Maximum file size:

```text
5 MB
```

Files are stored under:

```text
rosariosis/assets/FileUploads/ApplicantDocuments/
```

The directory is already covered by the repository's user-upload ignore rule. The registration page also creates an Apache `.htaccess` denial file in the upload directory to prevent direct browser access.

## Database Installation

Apply:

```text
database/migrations/001_online_registration.sql
```

Local PowerShell example:

```powershell
Get-Content .\database\migrations\001_online_registration.sql -Raw | docker compose exec -T db mariadb -uabugida_user -pabugida_dev_password abugida_sis
```

## Local Test

1. Apply the migration.
2. Start Docker with `docker compose up -d`.
3. Open `http://localhost:8090/registration.php`.
4. Enter a new phone number.
5. Fill only part of the form and select **Save and continue later**.
6. Select **Use another phone number**.
7. Enter the original phone again.
8. Confirm all saved fields are preloaded.
9. Upload a PDF or PNG.
10. Submit the application.
11. Confirm the status becomes SUBMITTED and the public form becomes read-only.

## Security Notes

- CSRF protection is enabled for form posts.
- Uploaded MIME type is checked server-side.
- Uploaded filenames are randomized.
- PDF and PNG are the only permitted types.
- File size is limited to 5 MB.
- Applicant output is HTML-escaped.
- Direct access to uploaded documents is denied on Apache.

The phone-only resume mechanism is not considered strong authentication and is expected to be replaced or supplemented before production.

## Future Work

- Registrar review queue.
- Decline/reapply workflow.
- Multiple applications per family phone number if required.
- Online payment integration.
- Permanent student creation after verified payment and final Registrar approval.
- SMS account notification after the permanent account is created.
