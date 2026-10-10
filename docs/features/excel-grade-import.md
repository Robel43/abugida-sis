# Excel Grade Import

## Purpose

The Excel Grade Import feature lets authorized staff import official high-school semester grades into Abugida SIS from an `.xlsx` workbook.

This feature is independent of Moodle.

## Abugida grading model

Abugida uses a semester-only academic grading structure:

```text
Academic Year
├── Semester 1
└── Semester 2
```

Final grades are stored and presented as percentages from 0 to 100.

Quarter and Progress Period grading are not part of the Abugida user-facing workflow.

## Location

**Grades > Utilities > Grade Import**

## Import workflow

1. Select the Grade.
2. The Subject / Section list loads automatically.
3. Select the Subject / Section.
4. Select the Semester.
5. Download the official Excel template if needed.
6. Upload the completed `.xlsx` file.
7. Select **Validate & Preview**.
8. Review every row and correct any validation errors.
9. Confirm the import.
10. The grades are saved as official semester grades.

## Import context

The page uses:

- current school;
- current academic year;
- selected grade;
- selected course period / section;
- selected graded semester.

The Subject / Section selector is populated from the configured course hierarchy:

```text
Grade course subject
→ Course
→ Course Period / Section
```

## Excel template

A downloadable workbook is available directly from the Grade Import page.

Filename:

```text
Abugida_Grade_Import_Template.xlsx
```

Required columns:

| Column | Use |
| --- | --- |
| Student ID | Primary student match |
| Student Name | Human-readable validation |
| Final Score | Official semester percentage from 0 to 100 |

The Student ID is authoritative. If the Excel name differs from the SIS name, the system warns the user but continues to match by Student ID.

Accepted Student ID formats include the numeric SIS ID and Abugida-style IDs such as `ABG123`.

## Validation

Before any grade is saved, the system checks:

- required Excel columns;
- valid Student ID format;
- duplicate Student IDs in the workbook;
- student membership in the selected grade and course roster;
- numeric score;
- score between 0 and 100;
- whether an official grade already exists.

Rows with validation errors block confirmation.

Existing official grades are marked as `EXISTING` and are never replaced silently. Replacement requires explicit confirmation.

## Official grade storage

Confirmed imports are written to the standard `student_report_card_grades` table using:

- school year;
- school;
- student;
- course period;
- semester marking period;
- percentage grade;
- course title.

The percentage is the authoritative grade value for Abugida.

## Import audit trail

Each import batch is recorded in:

- `abugida_grade_import_batches`
- `abugida_grade_import_rows`

The audit data records the source workbook, importer, import time, row number, student, previous percentage, new percentage, action, and result message.

Migration:

```text
database/migrations/007_excel_grade_import.sql
```

## XLSX processing

A lightweight internal XLSX reader processes the first worksheet and does not add a Composer dependency.

Required PHP capability:

```text
ZipArchive
```

## Related semester behavior

Course periods should be assigned to Semester 1 or Semester 2.

In Group Schedule, the Semester is taken automatically from the selected course period. Staff do not separately choose a grading period when scheduling students.

The schedule start date must fall inside the selected course period's Semester dates.

## Current limitations

- `.xlsx` only;
- first worksheet only;
- one final percentage per student, subject / section, and semester;
- no Moodle grade synchronization in this feature;
- no student-facing dashboard in this feature.

## Planned next phase

The next student-facing phase will expose the official SIS information already stored in Abugida through a student dashboard. Planned student views include:

- current enrolled courses;
- Semester 1 and Semester 2 percentage grades;
- payment status and balances;
- student profile / enrollment information;
- relevant registration and academic status;
- important school notices or account information where appropriate.

The student-facing dashboard will be read-only for official academic and finance information unless a specific workflow explicitly allows student action.
