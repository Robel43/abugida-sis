# Excel Grade Import

## Scope

This feature imports official final percentage grades from an Excel `.xlsx` file into Abugida SIS. It is intentionally independent of Moodle.

## Location

**Grades > Utilities > Grade Import**

## Import context

The user selects:

- Grade
- Subject / Section (course period)
- Semester / marking period
- Excel file

The current SIS school and academic year are used automatically.

## Excel format

The first worksheet must contain these headers:

- `Student ID`
- `Student Name`
- `Final Score`

Accepted aliases include `ID`, `Name`, `Score`, `Final Grade`, and `Percent`.

Student IDs may be numeric RosarioSIS IDs or Abugida-style values such as `ABG123`.

## Validation

Before import, the system validates:

- required columns
- student ID format
- duplicate student IDs
- selected grade / course roster membership
- score is numeric
- score is between 0 and 100
- whether an official grade already exists

The user receives a row-by-row preview. Validation errors block the import.

Existing official grades are never overwritten silently. They are marked as `EXISTING` and require the user to explicitly enable replacement during confirmation.

## Storage

Confirmed grades are saved to the standard `student_report_card_grades` table so existing report cards, final-grade reports, GPA/class-rank logic, and other SIS functionality can use them.

Import audit information is stored in:

- `abugida_grade_import_batches`
- `abugida_grade_import_rows`

Migration: `database/migrations/007_excel_grade_import.sql`

## XLSX support

A lightweight internal XLSX reader is used for the first worksheet and does not add a Composer dependency. PHP's `ZipArchive` extension must be enabled.
