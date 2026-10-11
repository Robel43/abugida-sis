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
- The initial merge preserved grading, student portal, re-registration and receipt
  behavior. Subsequent browser testing found and fixed XLSX namespace parsing and the
  manual Class Rank AJAX submit action.

At merge time the integration execution account could not access Docker or WSL.
Browser tests on the user's updated Docker app and read-only MariaDB checks were
subsequently completed on 2026-10-11 as described below. The isolated transaction/
concurrency and HTTP suites remain separate and have not been rerun.

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

## Docker browser regression — 2026-10-11

Synthetic applicant ABG-APP-2026-000007 became student 8 (ABG8), Grade 8, after
Registrar rejection/correction/resubmission/approval, receipt upload, Finance
rejection/corrected receipt/verification, and Registrar final account creation.
Uploaded supporting and Fayda documents persisted. SMTP acceptance was displayed;
recipient mailbox delivery was not checked. The missing outbox table was resolved
by applying migration 014 in the user's Docker database.

Verified on the merged app:

- Existing John and new ABG8: all six portal pages load and ignore another student's
  URL student_id; new login opens the dashboard with the correct enrollment.
- Official scheduling: ABG8 enrolled in four Grade 8 subjects for both semesters.
- XLSX template download and automatic Grade → section loading; unknown IDs,
  duplicate IDs, scores over 100, nonnumeric scores, and out-of-roster IDs blocked.
- Valid 87% import and explicit 87 → 91 replacement; database audit shows old/new
  values and IMPORTED/REPLACED actions. Valid prefixed XML workbooks now import.
  database/tests/xlsx-reader.php covers default and prefixed namespaces and malformed XML.
- Final synthetic grades: S1 Math 90, Amharic 80, English 85, Science 95 → 87.50%,
  rank 1/7; S2 Math 85, Amharic 75, English 80, Science 90 → 82.50%, rank 2/7;
  Full Year → 85.00%, tied rank 1/7. The next full-year rank is 3.
- Pending Semester 2 did not become zero and Full Year remained unavailable until
  both graded semesters existed. Student grades, dashboard, staff and print reports agree.
- Manual Recalculate succeeds for S1 and Full Year after the AJAX action fix.
- Reports: full class, one student, two students and select-all (seven pages) HTML
  output; teacher assigned-class access, unassigned-class restriction, forged
  out-of-roster rejection, and filtering of mixed authorized/unauthorized IDs.
- Billing: 10,000 charge, 4,000 partial payment → 6,000 balance/Partially Paid;
  6,000 final payment → zero/Paid; both recent payments appear for ABG8.
- Paid future-semester request RR-2026-CCF6732BA8: approval, receipt download,
  Finance rejection/reason/resubmission/verification, Registrar final confirmation.
  Student receipt endpoint denies access; semester completion retains one annual enrollment.
- Zero-fee 2027 Grade 9 request RR-2026-F04A464F1B: approval → Ready for Final
  Confirmation → Completed, skipping Finance. Same student ID, username and login;
  database has exactly one 2026 Grade 8 and one 2027 Grade 9 enrollment.
- Completed semester and completed year duplicate submissions rejected; an active
  request hides the new-request form. Future semester eligibility is independent
  of the sidebar semester. The future-semester date renders as text.

Limitations: browser Save as PDF output itself, concurrent duplicate submissions,
production PDF conversion, SMTP mailbox delivery and isolated Docker automated
transaction/HTTP suites have not been verified. Synthetic records are intentionally
retained in the local test database; these tests did not change real students' grades.
