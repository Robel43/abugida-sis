# Student Dashboard - Planned Scope

## Purpose

The Student Dashboard will provide each authenticated student with a clear, read-only view of the most important information held about them in Abugida SIS.

This is the next planned feature after the Excel Grade Import and semester grading work.

## Core dashboard areas

### Academic summary

Show:

- current grade level;
- current academic year;
- current Semester;
- enrollment status;
- student ID.

### My Courses

Show the student's current scheduled courses / course periods, including:

- subject / course;
- section;
- teacher;
- Semester;
- enrollment status.

The source of truth is the SIS schedule and course-period data.

### My Grades

Show official grades from `student_report_card_grades`.

The first version should show:

| Subject | Semester 1 | Semester 2 |
| --- | ---: | ---: |
| Amharic | 82% | 88% |
| Mathematics | 76% | 81% |

Below the subject table, show summary results such as:

```text
Semester 1 Average: 79.00%
Semester 1 Class Rank: 4 / 32

Semester 2 Average: 84.50%
Semester 2 Class Rank: 2 / 32

Full Year Cumulative Average: 81.75%
Full Year Class Rank: 3 / 32
```

Rules:

- subject grades are percentage values;
- no GPA;
- show Semester Average across all published subject percentages;
- show Semester Class Rank based on that Semester Average;
- show Full Year Cumulative Average after both Semester averages are available;
- show Full Year Class Rank based on the cumulative average;
- do not show a separate rank for each subject;
- no unofficial gradebook values unless explicitly added later;
- if a Semester grade is not yet available, show a clear pending / not published state rather than zero.

### Payment Status

Show the student's current finance position, based on SIS billing records:

- total charges;
- total paid;
- outstanding balance;
- payment status;
- recent verified payments where useful.

The student view is informational. Finance staff remain responsible for payment verification and correction.

### Profile and enrollment information

Show key student data such as:

- full name;
- student ID;
- grade level;
- school;
- current enrollment status;
- contact information that the student is allowed to see.

### Important information

A later dashboard iteration may also include:

- announcements;
- registration status;
- upcoming school dates;
- downloadable official documents;
- account / login information;
- attendance summary.

These should only be added where the underlying SIS workflow is defined.

## Access and security

The student must only see records belonging to their own authenticated student account.

The dashboard must not allow URL manipulation to expose another student's:

- grades;
- course enrollment;
- finance information;
- profile information.

Every student-facing query must be constrained by the authenticated student ID.

## Design direction

The dashboard should be substantially simpler than the staff interface.

Recommended top-level student navigation:

```text
Dashboard
My Courses
My Grades
Payments
My Profile
```

The dashboard home should summarize rather than duplicate every detail from the dedicated pages.

## First implementation recommendation

Build the student side in this order:

1. authenticated student dashboard shell;
2. My Courses;
3. My Grades;
4. Payment Status;
5. Profile / Enrollment summary;
6. dashboard summary cards;
7. additional student services.

This sequence validates the read-only data-access model before adding more functionality.
