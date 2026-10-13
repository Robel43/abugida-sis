# Changelog

All significant Abugida SIS changes should be recorded in this file.

## Unreleased

### Added
- Public online registration with save-and-resume by phone number
- Supporting-document and Fayda ID uploads
- Online/Distance Learning study-approach selection
- Registrar online-application review queue
- Application rejection, correction, and resubmission workflow
- Grade-based registration fee configuration
- Secure tokenized payment access
- Applicant payment-proof upload
- Finance application-payment verification queue
- Final Registrar confirmation and permanent student-account creation
- Application status/history audit trail
- Applicant email notifications and Abugida SMTP helper
- SMTP diagnostic logging and internal email test utility

### Changed
- Registrar approval now uses the configured fee for the applicant's grade
- Grade-fee lookup supports ordinal grade names
- Registrar decision handling and success feedback were made server-verifiable and more reliable
- Application document access now uses a secured viewer instead of direct public upload URLs
- Finance notifications use the configured Abugida SMTP path

### Fixed
- Registrar decision POST handling
- Null trim deprecation in application review
- Grade registration fee lookup for ordinal grade labels
- Supporting-document viewing/access behavior
- Registration decision success feedback

### Database
- `001_online_registration.sql` - applicant/application base table
- `002_add_fayda_id_upload.sql` - Fayda ID upload fields
- `003_add_study_approach.sql` - Online/Distance Learning selection
- `004_registration_approval_payment_workflow.sql` - approval, payment, activation, and history workflow
- `005_payment_access_token.sql` - secure payment access token
- `006_grade_registration_fees.sql` - grade-based registration fees

### Integration
- Configurable SMTP/email helper for applicant and finance workflow notifications
- Secure payment links can be sent from approval notifications

## Release Format

Use the following format for future releases:

```markdown
## [Version] - YYYY-MM-DD

### Added
- New features

### Changed
- Modified behavior

### Fixed
- Bug fixes

### Database
- Schema or migration changes

### Integration
- Moodle or external integration changes
```
