# Abugida SIS 2026 UI Refresh

## Goal

Modernize the visual system and responsive behavior of Abugida SIS without changing the validated registration, grading, finance, reporting, student portal, or re-registration business logic.

The refresh is implemented as a global presentation layer so existing modules inherit the same design system.

## Phase 1 implemented

### Global design system

New file:

```text
rosariosis/assets/css/abugida-2026.css
```

Includes:

- application color, spacing, radius and shadow tokens;
- modern typography and focus states;
- responsive forms and inputs;
- primary and secondary button treatment;
- modern tables and content surfaces;
- desktop, tablet and phone breakpoints;
- reduced-motion support.

### Responsive application shell

The legacy RosarioSIS shell is restyled into:

- fixed desktop sidebar;
- compact top utility bar;
- off-canvas mobile navigation drawer;
- mobile backdrop;
- responsive body spacing;
- scroll-safe navigation;
- larger touch targets.

The existing RosarioSIS menu and permission structure remain authoritative.

### Feedback dialogs

New file:

```text
rosariosis/assets/js/abugida-2026.js
```

Existing RosarioSIS feedback is enhanced at runtime:

- `.error` becomes an accessible error modal;
- `.warning` becomes an accessible warning modal;
- `.note` becomes an accessible success modal.

Dialogs include:

- focus management;
- Escape key close;
- focus trapping;
- click-outside close;
- ARIA dialog semantics;
- queued display when multiple messages are generated.

No workflow or database logic is changed.

### Responsive tables

Existing tables inside the main application body are wrapped automatically in horizontal scroll containers where needed. This prevents page-level overflow on narrow screens while preserving the original table markup.

### Standalone/public screens

Public and non-module screens receive a centered responsive content surface with modern spacing, form controls and mobile behavior.

## Global loading

`Warehouse.php` loads the Abugida 2026 stylesheet and JavaScript globally with file modification hashes for cache invalidation.

PDF generation bypasses the normal Warehouse shell and is therefore unaffected by the UI layer.

## Regression requirements

Before promotion, browser-test at minimum:

- administrator login and navigation;
- Registrar application review;
- Finance application payment review;
- Grade Import and Class Rank;
- Class Grade Reports;
- student Dashboard / Courses / Grades / Payments / Profile / Re-Registration;
- public registration and registration payment pages;
- menu operation at desktop, tablet and phone widths;
- AJAX navigation and AJAX-generated success/error feedback;
- keyboard Escape and focus behavior for dialogs.

Recommended viewport checks:

```text
1440px
1024px
768px
480px
360px
```

The validated `feature/online-registration-integration` branch remains the functional baseline.
