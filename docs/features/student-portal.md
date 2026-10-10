# Student Portal

## Purpose

The Abugida Student Portal gives each authenticated student a simple, read-only view of their own academic, enrollment, and payment information.

It intentionally replaces the legacy RosarioSIS student-facing Grades and Student Billing menus with one consolidated student experience.

## Navigation

For an authenticated student the **Student Portal** menu contains:

- Dashboard
- My Courses
- My Grades
- Payments
- My Profile
- Re-Registration

The normal RosarioSIS portal landing page redirects students to the Student Dashboard after login.

Parents retain their existing parent-facing menus.

## Security model

All Student Portal pages require:

```text
User('PROFILE') === 'student'
```

The student identity always comes from the authenticated session:

```text
UserStudentID()
```

Student Portal pages do not accept a Student ID from GET or POST.

This prevents URL or request manipulation from being used to view another student's:

- courses;
- grades;
- class rank;
- finance information;
- profile / enrollment data.

The portal also resolves the student's current school from their current-year enrollment when the session school has not yet been initialized.

## Dashboard

Location:

```text
Student Portal > Dashboard
```

The dashboard summarizes:

- current grade level;
- current Semester;
- active course count;
- payment status;
- current Semester average;
- current Semester class rank;
- outstanding balance;
- academic year.

Quick links open the dedicated student pages.

## My Courses

Location:

```text
Student Portal > My Courses
```

The page reads the student's official SIS schedule for the current academic year and shows:

- subject;
- section / course period;
- teacher(s);
- Semester;
- status.

Status is derived from the schedule start / end dates:

- Upcoming
- Active
- Completed

## My Grades

Location:

```text
Student Portal > My Grades
```

The page reads official Semester grades from:

```text
student_report_card_grades
```

It displays a subject table with:

- Semester 1 percentage;
- Semester 2 percentage.

If a subject grade is not yet available, the page shows **Pending** rather than zero.

The page also displays calculated results from:

```text
abugida_student_academic_rank
```

including:

- Semester 1 Average;
- Semester 1 Class Rank;
- Semester 2 Average;
- Semester 2 Class Rank;
- Full Year Cumulative Average;
- Full Year Class Rank.

No GPA is displayed or calculated.

## Payments

Location:

```text
Student Portal > Payments
```

The page summarizes current-year billing information:

- Total Charges;
- Total Paid;
- Outstanding Balance;
- Payment Status.

Possible status values:

- No Charges
- Paid
- Partially Paid
- Unpaid

It also lists the student's ten most recent recorded payments with date, amount, and comment.

The view is read-only.

## My Profile

Location:

```text
Student Portal > My Profile
```

The first version shows:

- full name;
- Student ID;
- username;
- school;
- grade level;
- academic year;
- enrollment status.

The page is read-only and directs students to the Registrar for official corrections.

## Permissions

Migration:

```text
database/migrations/011_student_portal_permissions.sql
```

This grants existing student profiles read-only access to:

```text
Students/StudentDashboard.php
Students/MyCourses.php
Students/MyGrades.php
Students/MyPayments.php
Students/MyProfile.php
```

## Test recommendations

Use an existing demo student who has:

- a current enrollment;
- Semester 1 / Semester 2 course schedules;
- official grades;
- calculated rank records;
- optional billing records.

Verify:

1. login lands on Student Dashboard;
2. only Student Portal navigation is shown for the student;
3. My Courses shows only that student's schedule;
4. My Grades shows only that student's official percentages / ranks;
5. Payments shows only that student's billing records;
6. My Profile shows only that student's enrollment;
7. attempts to add `student_id` to any URL do not change the displayed student.


## UI design

The student portal uses a dedicated modern presentation layer while keeping RosarioSIS data and permission logic intact.

The visual system includes:

- responsive summary cards;
- modern page hero headers;
- consistent section cards;
- status and payment badges;
- responsive table containers;
- mobile-friendly two-column / one-column breakpoints;
- read-only profile information cards;
- clearer empty and pending states.

The styling is centralized in:

```text
ProgramFunctions/AbugidaStudentPortal.fnc.php
```

This keeps the student-facing look consistent across Dashboard, My Courses, My Grades, Payments, and My Profile.


## Re-Registration

Existing students can continue into a future Semester or academic year through:

```text
Student Portal > Re-Registration
```

This workflow retains the student's existing account and Student ID. See:

```text
docs/features/student-reregistration.md
```
