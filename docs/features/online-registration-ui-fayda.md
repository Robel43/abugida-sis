# Online Registration - UI and Fayda ID Update

## Change Summary

The public online registration form has been redesigned with a more modern, mobile-responsive interface and now includes a second required upload field named **Fayda ID**.

## Applicant Fields

The current public form collects:

- First Name
- Last Name
- Phone Number
- Email Address
- Applying Grade (Grade 7 through Grade 12)
- Supporting Document
- Fayda ID

Both uploaded files accept:

- PDF
- PNG

Maximum size:

- 5 MB per file

## Resume Behavior

The applicant can save an incomplete registration and later return using the same phone number. Saved personal information, grade selection, Supporting Document metadata, and Fayda ID metadata are restored automatically.

## Submission Rule

Final submission requires both:

1. Supporting Document
2. Fayda ID

After submission, the public application becomes read-only pending Registrar review.

## Database Update

Existing development databases must apply:

```text
database/migrations/002_add_fayda_id_upload.sql
```

PowerShell:

```powershell
Get-Content .\database\migrations\002_add_fayda_id_upload.sql -Raw | docker compose exec -T db mariadb -uabugida_user -pabugida_dev_password abugida_sis
```

The original `001_online_registration.sql` remains the baseline migration for the applicant table. Migration 002 extends an already-created applicant table.

## Local URL

```text
http://localhost:8090/registration.php
```
