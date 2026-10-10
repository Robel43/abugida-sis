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

Abugida does not use GPA or class-rank presentation as part of the high-school grading workflow.

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

## Student-facing use

The planned student dashboard will read these official semester percentages and show them alongside enrolled courses and other student information.

The dashboard must not calculate a separate GPA or ranking layer.
