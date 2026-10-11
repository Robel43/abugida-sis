# Registration Approval and Payment Workflow

This customization implements the Abugida SIS demo registration workflow around the public online registration page and the internal Registrar/Finance work queues.

The workflow is based on the agreed demo sequence: applicant self-registration, Registrar review, payment availability, receipt submission, Finance verification, Registrar final confirmation, and full student account creation.

## Implemented Roles and Access

### Applicant / Student

Public pages:

```text
/registration.php
/registration-payment.php
```

The applicant can:

- start or resume an application using the same phone number;
- complete personal information;
- select Grade 7-12;
- choose Online or Distance Learning;
- upload a supporting document;
- upload Fayda ID;
- save a draft;
- submit the application;
- see the Registrar decision;
- correct and resubmit a rejected application;
- see payment amount/instructions after approval;
- upload payment proof;
- see payment verification status;
- see the generated student username after final activation.

### Registrar Officer / Registrar Manager

Internal page:

```text
Students -> Online Applications
```

The page supports:

- viewing all online applications;
- opening an application record;
- inspecting applicant information;
- downloading the supporting document;
- downloading Fayda ID;
- approving an application for payment;
- entering the amount to be paid;
- entering payment instructions;
- rejecting an application with a mandatory reason;
- seeing payment progress;
- final confirmation after Finance verification;
- creating the permanent student record and active enrollment;
- seeing the generated Student ID and username.

### Finance Manager / Finance Officer

Internal page:

```text
Student Billing -> Application Payments
```

The page supports:

- viewing applicants that have reached the payment stage;
- viewing payment amount and status;
- opening the payment record;
- downloading submitted payment proof;
- verifying payment;
- rejecting payment with a mandatory reason.

## Permission Model

Both internal pages use the existing RosarioSIS profile permission system.

For each custom profile under:

```text
Users -> User Profiles
```

configure:

### Registrar Officer

```text
Students -> Online Applications
Can Use: Yes
Can Edit: Yes
```

### Registrar Manager

```text
Students -> Online Applications
Can Use: Yes
Can Edit: Yes
```

### Finance Manager

```text
Student Billing -> Application Payments
Can Use: Yes
Can Edit: Yes
```

### Finance Officer

If Finance Officers are allowed to verify payments:

```text
Student Billing -> Application Payments
Can Use: Yes
Can Edit: Yes
```

If they should only inspect records:

```text
Can Use: Yes
Can Edit: No
```

The same pattern can be used for any read-only oversight role. **Can Use** provides page visibility. **Can Edit** controls approval/rejection actions.

## Status Flow

```text
DRAFT
  -> SUBMITTED
  -> APPROVED_FOR_PAYMENT
  -> PAYMENT_SUBMITTED
  -> PAYMENT_VERIFIED
  -> ACTIVE
```

Correction paths:

```text
SUBMITTED
  -> DECLINED
  -> SUBMITTED

PAYMENT_SUBMITTED
  -> PAYMENT_DECLINED
  -> PAYMENT_SUBMITTED
```

## Email Notifications

Where outgoing email is configured, the workflow sends applicant email notifications for:

- application rejection;
- application approval for payment;
- payment rejection;
- payment verification;
- final account creation.

## Permanent Student Creation

Final Registrar confirmation is available only after Finance sets the payment to VERIFIED.

The action:

1. maps the selected Grade 7-12 to the configured school grade level;
2. creates a permanent student record;
3. generates a unique student username;
4. creates an active enrollment for the current school year;
5. stores the permanent Student ID against the applicant;
6. changes the application status to ACTIVE;
7. emails temporary login credentials when email delivery is configured.

The applicant record is retained after activation for workflow history.

## Audit History

Workflow decisions are logged in:

```text
abugida_application_history
```

The log records:

- applicant;
- previous status;
- new status;
- action;
- actor type;
- actor ID;
- actor name;
- reason;
- timestamp.

## Database Migration

Apply:

```text
database/migrations/004_registration_approval_payment_workflow.sql
```

after migrations 001-003.

Local PowerShell:

```powershell
Get-Content .\database\migrations\004_registration_approval_payment_workflow.sql -Raw | docker compose exec -T db mariadb -uabugida_user -pabugida_dev_password abugida_sis
```

## Required Setup Before Testing

1. Enable the **Custom** module in School -> Configuration -> Modules if it is not already enabled.
2. Ensure Grade 7 through Grade 12 exist under School -> Grade Levels.
3. Ensure exactly one default Add enrollment code exists.
4. Ensure a default school calendar exists.
5. Configure role permissions under Users -> User Profiles.
6. Configure outgoing email if email notifications should be tested.

## Demo Test Sequence

1. Applicant submits a registration.
2. Registrar opens **Students -> Online Applications**.
3. Registrar reviews files and approves for payment.
4. Applicant returns with phone number and opens payment instructions.
5. Applicant uploads payment proof.
6. Finance opens **Student Billing -> Application Payments**.
7. Finance verifies payment.
8. Registrar returns to the application and selects **Final Confirm & Create Student Account**.
9. Applicant sees ACTIVE status and generated username.
10. Student signs in through the normal SIS login.


## Stage 2 verification and SMTP setup

See [Stage 2 tests, permissions, SMTP configuration, and safe email retries](stage2-testing-smtp.md) for the verified workflow and local configuration steps. Migration 007 adds the notification outbox; migrations 001–006 remain unchanged.
