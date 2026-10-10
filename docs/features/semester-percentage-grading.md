# Abugida High-School Grading Model

## Purpose

This document defines the grading model used by Abugida SIS for secondary-school implementation.

## Academic periods

Abugida uses two graded semesters per academic year.

```text
Academic Year
├── Semester 1
└── Semester 2
```

Quarter and Progress Period grading are not part of the Abugida operational model.

Legacy RosarioSIS compatibility code may still recognize older marking-period types internally so historical data does not break, but new Abugida workflows use Semesters.

## Grade representation

Official grades are percentage values:

```text
0 - 100
```

The percentage is the primary grade shown to staff and students.

Abugida does not use GPA. Class rank is required, but it is calculated at the Semester and Full Year level rather than separately for each subject.

## Course setup

Every course period used for grading should be attached to the appropriate Semester.

Example:

```text
Grade 8
└── Amharic
    └── S1 - 8A - Teacher
        └── Semester 1
```

## Student scheduling

When staff add a course to students through Group Schedule:

- the Semester comes from the course period;
- staff do not separately choose a grading period;
- the enrollment start date must fall inside the Semester dates;
- schedule records are stored against the course period's Semester.

## Grade entry paths

Official semester percentages can enter the SIS through:

1. Excel Grade Import.
2. Existing staff final-grade workflows that have been adapted to Semester context.

Moodle synchronization is outside the current Excel Grade Import scope.

## Official storage

Official semester grades are stored in `student_report_card_grades`.

The important Abugida dimensions are:

- academic year;
- school;
- student;
- course period;
- Semester;
- percentage.

## Semester average and class rank

For each student and Semester:

1. take all official subject percentages recorded for that Semester;
2. calculate the arithmetic mean;
3. compare that Semester average with the averages of students in the same grade level;
4. assign the student's Semester class rank.

Example:

```text
Amharic        80%
Mathematics    90%
English        70%
Biology        85%

Semester Average = (80 + 90 + 70 + 85) / 4 = 81.25%
```

Class rank is based on the Semester Average, not on any individual subject.

Students with equal averages share the same competition rank.

## Full Year cumulative average and rank

The Full Year cumulative average is calculated only when both Semester averages are available.

```text
Semester 1 Average = 81.25%
Semester 2 Average = 84.75%

Full Year Cumulative Average = (81.25 + 84.75) / 2 = 83.00%
```

The Full Year class rank is then calculated among students in the same grade level using the Full Year Cumulative Average.

There is no GPA conversion.

## Rank storage

Calculated Semester and Full Year averages / ranks are stored in:

```text
abugida_student_academic_rank
```

Migration:

```text
database/migrations/008_semester_class_rank.sql
```

The table stores the student, grade level, period, average percentage, rank position, cohort size, and calculation time.

## Student-facing use

The planned student dashboard will show:

- subject percentages for Semester 1 and Semester 2;
- Semester Average;
- Semester Class Rank;
- Full Year Cumulative Average;
- Full Year Class Rank.

The dashboard must not calculate GPA.
