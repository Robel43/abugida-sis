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


## Phase 1 browser-test fixes

Browser testing identified four regressions and the following corrections were applied:

- aligned the Abugida mobile shell breakpoint with FlatSIS at 874px;
- explicitly restores mobile drawer height and geometry that legacy `zresponsive.css` collapsed to zero;
- coordinates the Abugida drawer state with the legacy `.slide` class;
- suppresses the legacy fixedmenu spacer on mobile and clears stale inline geometry during live resize;
- forces student hero headings to remain white against the dark gradient;
- Class Rank recalculation now emits semantic success feedback through `ErrorMessage(..., 'note')`, allowing the global success modal to display.

Retest widths:

```text
1440
1024
830
768
480
360
```

Also retest live resize from 1440 directly to 360 without reloading.


## Phase 1 retest fixes

A second browser retest found two remaining issues:

- repeated `refresh()` calls were rebinding the mobile Menu click handler, causing one click to toggle the drawer multiple times and finish closed;
- RosarioSIS `ErrorMessage(..., 'note')` renders the legacy `.updated` class, while the UI enhancer originally looked for `.note`.

Corrections:

- Menu and sidebar click handlers are now bound idempotently using per-element data flags;
- success markup now recognizes legacy `.updated`;
- `ErrorMessage()` now adds non-breaking semantic classes:
  - `abg-feedback-success`
  - `abg-feedback-warning`
  - `abg-feedback-error`
- legacy `.updated` and `.error` classes remain intact for backward compatibility;
- warning messages can now be distinguished from ordinary errors even though RosarioSIS historically renders both with the `.error` class.

Targeted retest:

1. fresh reload at 360, 480, 768 and 830;
2. one Menu click must open exactly once;
3. Enter on the Menu button must open exactly once;
4. backdrop and Escape must close the drawer;
5. Class Rank Recalculate must display a success modal;
6. an `ErrorMessage(..., 'warning')` path should display a warning modal rather than an error modal.


## Mobile drawer stacking fix

The targeted UI retest confirmed that drawer state and dimensions were correct, but FlatSIS retained `#menu { z-index: 1 !important; }`, placing the opened drawer underneath the Abugida backdrop.

The Abugida UI layer now sets the application drawer to `z-index: 1040 !important` globally and again in the mobile shell rule. The backdrop remains at 1035, so the intended stack is:

```text
top utility bar / drawer controls
drawer: 1040
backdrop: 1035
page content: below backdrop
```

Targeted verification should confirm:
- drawer content is sharp and interactive;
- `elementFromPoint()` inside the drawer resolves to menu content, not the backdrop;
- a Student Portal link such as My Courses navigates normally;
- backdrop click outside the drawer closes it;
- Escape closes it.


## Phase 2 - module and workflow modernization

Phase 2 extends the design layer beyond the shell.

Implemented:

- modern RosarioSIS page headers and secondary headings;
- consistent legacy ListOutput / widefat / postbox table surfaces;
- modern search and filter containers;
- modern tab treatment;
- unified Abugida cards, review panels, status chips, action buttons, and workflow tables;
- improved mobile stacking for action groups and custom forms;
- shared public registration/payment design layer in `assets/css/abugida-public-2026.css`;
- shared public success/error dialogs in `assets/js/abugida-public-2026.js`;
- public Registration and Registration Payment now load the shared UI layer.

Business logic and database behavior remain unchanged.

### Phase 2 browser test focus

Staff:
- Online Applications list and applicant detail;
- Application Payments list and Finance detail;
- Registration Fees;
- Grade Import;
- Class Rank;
- Class Grade Reports;
- Re-Registration Requests;
- Re-Registration Payments.

Public:
- registration start form;
- saved/submitted success feedback;
- validation error feedback;
- returned/declined application state;
- registration payment;
- receipt validation error;
- receipt submitted success feedback.

Responsive widths:

```text
1440
1024
830
768
480
360
```

Verify no page-level horizontal overflow, buttons remain reachable, tables scroll inside their own containers, and modal focus/Escape behavior remains intact.
