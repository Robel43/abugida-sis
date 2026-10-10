# Existing Student Re-Registration

## Purpose

The re-registration workflow lets an authenticated existing student continue into a new Semester or a future academic year without creating a new student account.

This is separate from the public new-applicant registration workflow.

New applicants use:

```text
registration.php
```

Existing students use:

```text
Student Portal > Re-Registration
```

## Student identity and security

The re-registration page uses the authenticated student session:

```text
UserStudentID()
```

It does not accept a Student ID from GET or POST.

A student can therefore create and manage only their own re-registration request.

## Request types

### Next Semester

The student remains in the current academic year and current grade.

The request stores the selected target Semester.

On final Registrar confirmation:

- the request is marked completed;
- the existing annual enrollment is retained;
- the existing student account is retained;
- course scheduling remains a separate Registrar / Scheduling workflow.

The student cannot select the currently active Semester or an already-ended Semester.

### New Academic Year

The student selects:

- a configured future academic year;
- a target grade.

The Registrar reviews and confirms the requested progression.

On final confirmation the system creates the student's enrollment for the target academic year if one does not already exist.

If a future enrollment row already exists, the workflow updates only its grade and preserves its existing dates and other enrollment details.

The existing student record, username, password, and Student ID are retained.

## Request status flow

Standard paid flow:

```text
SUBMITTED
  -> APPROVED_FOR_PAYMENT
  -> PAYMENT_SUBMITTED
  -> PAYMENT_VERIFIED
  -> COMPLETED
```

If Finance rejects a receipt:

```text
PAYMENT_SUBMITTED
  -> PAYMENT_DECLINED
  -> PAYMENT_SUBMITTED
```

The student can upload a corrected receipt.

If the Registrar returns a request:

```text
SUBMITTED
  -> DECLINED
```

The student sees the reason and can create a corrected new request.

If the configured registration fee is zero:

```text
SUBMITTED
  -> READY_FOR_FINAL
  -> COMPLETED
```

Finance verification is skipped.

## Registration fees

Re-registration reuses the existing grade-based registration fee configuration:

```text
abugida_registration_fees
```

The fee is looked up by:

- school;
- target academic year;
- target grade.

Registrar approval is blocked if no fee has been configured for the requested target year / grade.

A configured fee of `0.00` means no payment is required.

Fees continue to be managed under:

```text
Student Billing > Registration Fees
```

For a future-year request, the target school year and its Full Year marking period must already be configured.

## Student payment

After Registrar approval for payment, the Student Portal shows:

- amount due;
- payment instructions;
- receipt upload.

Allowed receipt formats:

```text
PDF
PNG
JPG/JPEG
```

Maximum size:

```text
5 MB
```

Receipts are stored under:

```text
assets/FileUploads/ReRegistrationReceipts/
```

Direct browser access is denied through an automatically created `.htaccess` file.

## Registrar workflow

Location:

```text
Students > Re-Registration Requests
```

Registrar staff can:

- view all re-registration requests;
- see the existing student identity and current grade;
- see the requested Semester / academic year / target grade;
- approve the request;
- return the request with a required reason;
- perform final confirmation after payment verification;
- complete no-fee requests directly after approval.

For a New Academic Year request, final confirmation creates or updates `student_enrollment` for the target year.

For a Semester request, final confirmation records that the student has completed Semester re-registration but does not duplicate the annual enrollment record.

## Finance workflow

Location:

```text
Student Billing > Re-Registration Payments
```

Authorized Finance staff can:

- see re-registration payments;
- download the submitted receipt;
- verify the payment;
- reject the payment with a required reason.

A rejected payment can be resubmitted by the student from the Student Portal.

## Duplicate protection

A student can have only one active re-registration request at a time.

After a target period has been completed, the system prevents the student from submitting the same completed target again.

## Database tables

Requests:

```text
abugida_reregistration_requests
```

Audit history:

```text
abugida_reregistration_history
```

Migrations:

```text
database/migrations/012_student_reregistration.sql
database/migrations/013_student_reregistration_permissions.sql
```

## Permissions

Migration 013 grants:

Student profile:

```text
Students/ReRegistration.php
Can Use: Yes
Can Edit: No
```

Administrator-type profiles:

```text
Custom/ReRegistrationReview.php
Can Use: Yes
Can Edit: Yes

Custom/ReRegistrationPayments.php
Can Use: Yes
Can Edit: Yes
```

Custom Registrar and Finance profiles can be restricted through **Users > User Profiles** using the normal RosarioSIS permission model.

## Recommended browser test

### Semester flow

1. Sign in as an existing student.
2. Open **Student Portal > Re-Registration**.
3. Select **Next Semester**.
4. Select an available future Semester.
5. Submit.
6. Registrar approves.
7. Student uploads payment receipt if the configured fee is above zero.
8. Finance verifies.
9. Registrar final-confirms.
10. Confirm the student keeps the same account and the request becomes Completed.

### New academic year / grade flow

1. Configure the future academic year and Full Year marking period.
2. Configure the registration fee for the target year and grade.
3. Student selects **New Academic Year**.
4. Select target academic year and target grade.
5. Submit.
6. Registrar approves.
7. Complete payment / Finance verification if required.
8. Registrar final-confirms.
9. Verify `student_enrollment` contains the future-year enrollment with the selected grade.
10. Sign in again with the same student account.


## Browser test fixes

The following issues found during the first browser test were corrected:

### Semester eligibility is independent of the sidebar

A Semester request is now valid only when the Semester start date is later than the current date.

The currently active Semester is determined from the configured Semester date range, not from `UserMP()` or the Semester selected in the sidebar.

Changing the sidebar Semester therefore cannot make the currently active Semester eligible for re-registration.

### Finance decisions

Finance verification no longer depends on the `modfunc` query value or a clicked submit-button name.

Verify and Reject use separate POST forms with an explicit hidden `finance_action` value.

### Secure receipt download

Re-registration receipts are served through:

```text
reregistration-receipt.php
```

The endpoint requires an authenticated admin-type staff account with permission to use the Re-Registration Payments program, restricts the request to the current school, validates the stored MIME type, and returns the file directly with the correct download headers.

This avoids binary receipt content being rendered inside the normal RosarioSIS Modules page.
