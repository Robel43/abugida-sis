# System-wide Academic Terminology

## Purpose

Abugida SIS presents the RosarioSIS academic hierarchy using terminology that matches the school's operating model.

The terminology is applied consistently across the authenticated application for all role types, including administrators, teachers, parents, and students.

## User-facing terminology

| RosarioSIS internal concept | Abugida SIS label |
| --- | --- |
| Subject / Subjects | Grade / Grades |
| Course / Courses | Subject / Subjects |
| Course Period / Course Periods | Section / Sections |

Examples:

- RosarioSIS **Subject: Grade 7** is displayed as **Grade: Grade 7**.
- RosarioSIS **Course: English** is displayed as **Subject: English**.
- RosarioSIS **Course Period: 7A** is displayed as **Section: 7A**.
- A page or menu originally titled **Courses** is displayed as **Subjects**.

The mapping also applies inside longer user-visible phrases. For example, **Course List** becomes **Subject List**, **Choose a Course** becomes **Choose a Subject**, and **Parent Course Period** becomes **Parent Section**.

## Implementation strategy

The terminology is implemented in the global Abugida authenticated UI layer:

`rosariosis/assets/js/abugida-2026.js`

That script is loaded by the RosarioSIS application shell for authenticated users. It updates rendered text nodes and user-visible attributes such as titles, ARIA labels, placeholders, alternative text, and button values.

A MutationObserver applies the same mapping to content loaded or inserted dynamically after the initial page render.

This approach provides one terminology policy across modules and roles instead of maintaining separate wording changes in individual RosarioSIS modules.

## Safety / compatibility

This is strictly a presentation-layer change.

The following internal identifiers are intentionally unchanged:

- `course_subjects`
- `courses`
- `course_periods`
- `subject_id`
- `course_id`
- `course_period_id`
- PHP function and class names
- SQL queries
- URLs and request parameter names
- database schema
- Moodle/integration identifiers

The terminology layer does not rewrite `href`, `name`, `id`, `data-*`, form field keys, JavaScript code, CSS, `code`, `pre`, or `textarea` content.

This separation is deliberate so the UI terminology can change without changing the RosarioSIS data model or breaking business logic and integrations.

## Role consistency

The same mapping is applied regardless of authenticated role:

- Administrator
- Teacher
- Parent
- Student

Role permissions and menu visibility remain unchanged. Only the wording presented to the user changes.

## Sidebar readability

Sidebar group headings such as **Reports** and **Setup** use a high-contrast light treatment so they remain readable against the blue navigation background.

## Database changes

None.

## Validation checklist

For each available role, verify representative screens under Scheduling, Grades, Students, reports, and portal pages.

Confirm:

1. **Courses** is presented as **Subjects**.
2. **Course** is presented as **Subject**.
3. **Subjects** is presented as **Grades**.
4. **Subject** is presented as **Grade**.
5. **Course Periods** is presented as **Sections**.
6. **Course Period** is presented as **Section**.
7. Longer phrases use the same terminology.
8. Dynamically loaded content receives the same mapping.
9. Links, forms, saved records, IDs, filters, and actions still work normally.
10. No database migration is required.
11. Admin, teacher, parent, and student users see the same terminology for the same academic objects.

## Print/PDF note

The global terminology layer covers the authenticated browser interface. Server-generated PDF content that bypasses the browser JavaScript should be validated separately before relying on the renamed terminology in exported documents.
