# Class Grade Reports

## Purpose

The **Class Grade Reports** feature provides a printable, staff-facing view of official high-school grades.

It is designed for teachers, Registrar staff, school managers, and other authorized staff who need to review a class and produce individual student grade reports.

## Location

**Grades > Reports > Class Grade Reports**

## Access

The page is available to authorized administrator-type and teacher-type profiles.

Migration:

```text
database/migrations/010_class_grade_report_permissions.sql
```

The migration grants existing administrator and teacher profiles read-only access to:

```text
Grades/ClassGradeReport.php
```

Custom roles can be adjusted under **Users > Profiles**.

## Class report workflow

Staff select:

1. Grade — the Class / Subject list reloads automatically when the Grade changes;
2. Class / Subject;
3. Semester.

The report lists the students scheduled in the selected course period for the selected Semester.

The full class report can be opened as a printable / downloadable PDF using **Download / Print Class Report**.

For each student it shows:

- Student ID;
- Student name;
- official subject percentage;
- Semester Average;
- Semester Class Rank.

Teachers are restricted to course periods where they are the primary or secondary teacher.

Administrator-type roles with permission can access all configured classes.

## Student printable report

From the class report, staff can select one or more students and choose:

**Download / Print Selected Student Reports**

Each selected student is rendered on a separate PDF page.

The system generates a PDF with one student per page.

Each student report contains:

- school name;
- student name;
- student ID;
- grade level;
- Semester;
- all official subject percentages for that Semester;
- Semester Average;
- Semester Class Rank;
- Full Year Cumulative Average and Full Year Class Rank when available.

The report is intended to be printable and downloadable.

## Data sources

Official subject grades come from:

```text
student_report_card_grades
```

Semester / Full Year averages and class ranks come from:

```text
abugida_student_academic_rank
```

Course membership comes from:

```text
schedule
course_periods
courses
student_enrollment
```

## Security

Teachers may only view course periods where they are assigned as the primary or secondary teacher.

Other staff access is controlled by RosarioSIS profile permissions.

The report is read-only and does not modify grades.

## Grading model

The report follows the Abugida high-school grading model:

- subject grades are percentages;
- Semester Average is the arithmetic mean of all official subject percentages for that Semester;
- Semester Class Rank is based on the Semester Average;
- Full Year Cumulative Average is the average of Semester 1 Average and Semester 2 Average;
- Full Year Class Rank is based on the cumulative average;
- no GPA is used.


## PDF behavior

When `wkhtmltopdf` is configured in RosarioSIS, report actions use download mode and return PDF files directly.

If `wkhtmltopdf` is not configured, RosarioSIS cannot create a true PDF. In that environment the feature intentionally opens a print-ready HTML version and displays **Print / Save as PDF**, allowing the browser's print dialog to save the report as PDF.

This fallback avoids labeling an HTML response as a PDF download.

## Teacher authorization

Teacher access is validated again inside both report-generation handlers, not only when displaying the report page.

For selected-student reports the request must include:

- Grade;
- Course Period;
- Semester;
- selected Student IDs.

The server verifies that:

- the teacher is assigned as primary or secondary teacher to the Course Period;
- every generated student report belongs to the selected Grade / Course Period / Semester roster.

Student IDs outside the authorized roster are discarded before report generation. This prevents a teacher from posting another student's ID directly to the report handler.
