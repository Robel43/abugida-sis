# Scheduling Terminology Update

## Purpose

Align the Scheduling -> Courses setup interface with the terminology used by Abugida SIS schools.

## User-facing terminology

| RosarioSIS internal concept | Abugida SIS label |
| --- | --- |
| Courses page/menu | Subjects |
| Subject | Grade |
| Course | Subject |
| Course Period | Section |

Examples:

- Grade 7 is shown under **Grade**.
- English is shown under **Subject**.
- 7A is shown under **Section**.

The same terminology is used for list headings, result counts, create/edit labels, chooser labels, delete prompts, and parent-section labels on the Scheduling setup page.

## Technical scope

The change is presentation-only. Database tables, request parameters, PHP function names, and RosarioSIS internal identifiers remain unchanged, including:

- `course_subjects`
- `courses`
- `course_periods`
- `subject_id`
- `course_id`
- `course_period_id`

This avoids schema changes and preserves compatibility with existing scheduling, grading, reporting, and integration logic.

## Files changed

- `rosariosis/modules/Scheduling/Menu.php`
- `rosariosis/modules/Scheduling/Courses.php`

## Database changes

None.

## Validation

Verify the Scheduling setup page shows:

1. **Subjects** in the left Setup menu and page title.
2. **Grade** above the Grade 7-12 list.
3. **Subject** above subject names such as English.
4. **Section** / **Sections** above entries such as 7A.
5. Add, edit, delete, search, and chooser flows use the same terminology.

Underlying records and IDs must remain unchanged.

## Sidebar readability

Sidebar group headings such as **Reports** and **Setup** use a high-contrast light blue/white treatment so they remain readable against the blue navigation background.
