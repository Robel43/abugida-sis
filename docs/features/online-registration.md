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

Current workflow statuses include:

```text
DRAFT
SUBMITTED
DECLINED
APPROVED_FOR_PAYMENT
PAYMENT_SUBMITTED
PAYMENT_DECLINED
PAYMENT_VERIFIED
ACTIVE
```

A DRAFT application is editable. A DECLINED application can be corrected and resubmitted. After approval, payment becomes available. Finance can verify or reject submitted payment proof. Final Registrar confirmation creates the permanent student record and moves the application to ACTIVE.

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

## Implemented Follow-on Workflow

The Registrar review, decline/correction, approval-for-payment, payment-proof submission, Finance verification, and final student creation workflow is implemented and documented in:

`docs/features/registration-approval-payment-workflow.md`

Additional implemented extensions include Fayda ID upload, study-approach selection, secure payment-access tokens, grade-based registration fees, and SMTP/email diagnostics.

## Future Work

- Replace or strengthen phone-only draft-resume authentication before public production use.
- Multiple applications per family phone number if required.
- Direct payment-gateway integration if required.
- SMS notification/account delivery if required.
- Automatic section assignment and Moodle provisioning after activation.
