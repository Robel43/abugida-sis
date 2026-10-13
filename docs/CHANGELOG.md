# Changelog

All significant Abugida SIS changes should be recorded in this file.

## Unreleased

## Unreleased - Scheduling terminology

### Changed
- Academic terminology is now applied globally across authenticated administrator, teacher, parent, and student interfaces.
- User-facing **Subject/Subjects** is presented as **Grade/Grades**.
- User-facing **Course/Courses** is presented as **Subject/Subjects**.
- User-facing **Course Period/Course Periods** is presented as **Section/Sections**.
- Dynamic page content and user-visible accessibility/title attributes receive the same mapping.
- Internal database, URL, request, PHP, SQL, and integration identifiers remain unchanged.

## 2026-10-13 - Stable/sis validated baseline

### Added
- Validated combined public registration, Registrar/Finance workflow, permanent
  student account creation, Student Portal, Excel Grade Import, percentage ranking,
  staff class reports, billing views, and student re-registration.
- Configurable branding across staff, student, public, landing, login and SMTP sender.
- Responsive 2026 application shell, mobile navigation, accessible feedback dialogs,
  public landing page and high-contrast sidebar submenu treatment.

### Changed
- Current validated development baseline is `Stable/sis`; new feature/fix work
  should branch from it until the project owner decides to promote it to `develop`.
- Local Docker web URL documented as `http://localhost:8400`.
- SMTP sender, diagnostic subject/body, and local public URLs derive from current
  platform branding/environment rather than legacy hardcoded values.

### Fixed
- Completed-registration Student login now targets `index.php?login=1`.
- Normal status badges such as **Upcoming** no longer trigger warning dialogs.
- SMTP links no longer retain the old localhost:8090 address in local development.
- Legacy `Abugida SIS` wording removed from branded SMTP diagnostic mail.
- Legacy `unload` handler removed to avoid modern browser Permissions Policy warnings.
- Mobile landing Menu accessible name and interaction verified at 360px/480px.
- Student sidebar submenu contrast improved.

### Validation
- Full synthetic applicant-to-student lifecycle completed on 12–13 October 2026.
- Five workflow emails confirmed once in a controlled Gmail Inbox.
- SMTP failure/retry path preserved the committed Finance decision and delivered on
  retry without duplicating the workflow transition.
- Eight official grades imported/audited; S1 87.50% rank 1/8, S2 82.50% rank 2/8,
  Full Year 85.00% rank 1/8, with no GPA.
- Paid semester and zero-fee future-year re-registration completed while retaining
  the same student account and rejecting duplicates.
- Representative public/student/staff screens had no document-level horizontal
  overflow at 1440/1024/830/768/480/360.

### Known non-blocking follow-up
- Reproduce the earlier draft-validation stale-success-dialog observation.
- Improve preservation of unsaved grade/learning-approach selections after failed
  registration submission.
- Reproduce the isolated legacy Schedule save/blank-body observation before changing
  that page.
- Native PDF converter output was not part of this acceptance run.


### Added
- Initial project repository structure
- Docker-based local development environment
- Documentation framework

### Changed
- Documentation synchronized with the validated `Stable/sis` baseline.

### Fixed
- None

### Database
- None

### Integration
- None

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
