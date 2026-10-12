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


## Phase 2 browser-test fixes

The Phase 2 browser matrix found two confirmed presentation defects and one intermittent resize artifact.

Corrections applied:

- RosarioSIS `.rt` ListOutput tables now use normal semantic table layout below 1024px and rely on the Abugida horizontal scroll wrapper. This removes the legacy fixed-height/block-cell strategy that clipped labels such as Actions, Reference, First Name and Payment Status.
- the empty Re-Registration Payments result is now an inline informational state rather than a success modal;
- legacy `fixedmenu` spacer nodes are suppressed at all widths because the Abugida shell already owns sidebar spacing. This prevents the spacer from reappearing after mobile-to-desktop live resize.

Targeted retest:

1. Application Payments at 830, 768, 480 and 360: all headers must be fully visible;
2. narrow ListOutput tables must scroll inside their local wrapper with no document-level horizontal overflow;
3. Re-Registration Payments with no matching rows must show an inline informational empty state with no modal;
4. resize a staff page 360 -> 1440 and 1440 -> 360 repeatedly without reload; desktop content should return directly beside the 282px sidebar with no black spacer.


## Phase 3 - configurable branding and compact login

The System Configuration area now includes a Branding tab for full administrators.

Configurable items:

- Platform Name;
- Login Tagline;
- Primary Color;
- Secondary Color;
- Platform Logo;
- Browser Favicon;
- Login Background Image.

Branding is stored globally in the existing `config` table with `SCHOOL_ID=0`, so the same identity is available before login and on public registration pages. No database migration is required.

Uploaded branding files are stored in:

```text
assets/branding/
```

Supported logo/background formats: PNG, JPG, WebP.
Favicon additionally supports ICO.
Maximum upload size: 5 MB.

The Platform Name is also synchronized to the legacy RosarioSIS `NAME` and `TITLE` settings so older screens that still read those settings display the configured platform name.

Applied surfaces:

- browser page title;
- favicon;
- login screen;
- application sidebar logo;
- global primary/secondary palette;
- public registration page;
- public registration payment page.

### Login redesign

The legacy oversized login surface has been reduced to a focused authentication card with a maximum width of approximately 470px. The configured logo, platform name and tagline are displayed above the credentials, and an optional configured background image fills the login viewport.

### Phase 3 browser test focus

1. Open Configuration > Branding as administrator.
2. Change Platform Name and verify login, browser title, sidebar and public registration.
3. Change Primary and Secondary colors and verify buttons/accent surfaces/sidebar.
4. Upload logo, favicon and login background; save and hard-refresh.
5. Log out and verify the compact login card at 1440, 768, 480 and 360.
6. Verify favicon after a hard refresh/new tab.
7. Restore/remove each custom asset using the Branding controls.
8. Confirm public registration and payment retain responsive behavior after branding changes.


## Phase 3 browser-test fixes

The first branding/login browser pass found four presentation issues plus oversized action controls.

Corrections applied:

- branding asset URLs used inside CSS are now converted to application-root-aware web paths before being emitted, so login background images resolve correctly even when Abugida is deployed in a subdirectory;
- the compact login card now uses viewport-aware grid centering and switches to top-aligned scrolling on short-height screens;
- public Registration now displays the configured platform logo instead of a hardcoded letter mark;
- public Registration hero, mark/progress accents, and Payment actions/status surfaces now derive from configured primary/secondary brand colors instead of hardcoded blue/slate values;
- public Registration Payment now includes the configured platform logo/name;
- generic staff action buttons are normalized to compact dimensions, including Configuration Save controls, while retaining a touch-friendly minimum height.

Targeted retest:

1. Configure a temporary login background and verify the computed URL points to the actual `assets/branding` asset;
2. verify login card vertical centering at 1440, 768, 480 and 360 widths, including a short-height viewport;
3. verify Registration and Registration Payment both display the configured logo;
4. verify Registration hero and major public accents change with configured primary/secondary colors;
5. verify Configuration Save and representative staff action buttons are compact rather than oversized;
6. restore branding through the UI after the test.


## Student-facing branding propagation

Branding is now applied consistently to student-facing surfaces as well as staff/admin pages.

Updated behavior:

- student portal hero gradients use the configured primary and secondary colors;
- student portal primary action buttons use the configured primary color;
- Student Dashboard displays the configured platform logo and platform name;
- public Registration displays the configured platform logo;
- public Registration hero/progress accents use configured colors;
- public Registration Payment displays the configured platform logo/name and configured action colors;
- public CSS/JS assets now include file modification query strings so branding changes are not masked by a stale browser stylesheet;
- compact staff buttons now use border-box sizing so rendered height matches the intended control height.

Targeted retest:

1. temporarily configure a non-default logo and primary/secondary colors;
2. verify Student Dashboard branding and student portal hero/action colors;
3. verify Registration logo, hero gradient and progress bar colors;
4. verify Registration Payment logo/name and primary action color;
5. verify Configuration Save height is compact and no longer inflated by content-box padding;
6. restore branding through the UI.


## Public student landing page

A new public entry page is available at:

```text
landing.php
```

Unauthenticated visits to `index.php` now redirect to the landing page unless the request is an explicit login, authentication POST, password-reset/login reason, account creation flow, or protected redirect target.

Primary actions:

- Register -> `registration.php`
- Login -> `index.php?login=1`

The landing page follows the same broad information architecture as the referenced Select Academy site—top utility bar, sticky navigation, hero, statistics, service cards, process section, CTA and footer—while using original Abugida/SIS copy and the platform's configurable branding.

Branding applied to landing:

- platform name;
- logo;
- favicon;
- primary color;
- secondary color;
- tagline.

The login page now includes a Back to Home link to `landing.php`.

### Landing page test focus

- unauthenticated `index.php` redirects to `landing.php`;
- Register opens `registration.php`;
- Login opens `index.php?login=1`;
- configured logo/name/colors/favicon propagate to landing;
- desktop, tablet and 360px mobile layouts have no document-level horizontal overflow;
- existing login POST and password-reset flows remain functional.


## Expanded Select Academy-inspired landing catalogue

The landing page now includes additional visual blocks based on the source homepage structure and assets supplied by the user:

- Grades 9–12 visual cards;
- Full Package catalogue;
- Semester 2 package catalogue;
- Short Notes and Summary catalogue;
- ESSLCE Natural and Social preparation feature cards;
- Grade 9–12 Natural/Social summary cards;
- source-provided education imagery and package artwork.

The visual structure follows the reference site's catalogue-heavy browsing pattern while keeping Abugida branding, colors, logo, favicon and SIS actions configurable. Register and Login continue to route into Abugida workflows rather than the source site's commerce/course URLs.


## Phase 3 cleanup

Final cleanup work completed on the UI branch:

- application, payment, delivery and re-registration states now use human-readable labels instead of raw database codes;
- workflow states use consistent success / warning / danger / information badge tones across staff, student and public pages;
- empty re-registration request/payment lists remain inline informational states rather than false success dialogs;
- student-facing re-registration payment statuses are humanized;
- notification delivery statuses are humanized;
- account-creation and workflow notification branding uses the configured platform name;
- public landing copy no longer exposes reference-site wording;
- legacy image-button selectors are excluded from generic mobile action sizing;
- public registration and payment status chips use the same workflow color semantics.

## Final source validation

Source-level regression checks completed on `feature/ui-2026-refresh`:

- unauthenticated `index.php` routes to `landing.php`;
- Login Register action routes to `registration.php`;
- Registration Back to Home routes to `landing.php`;
- registration/payment pages use human-readable workflow labels;
- landing, login, public registration, public payment and student portal read configurable branding;
- branding helper exposes logo, favicon and login background;
- warning markup receives `abg-feedback-warning`;
- JS classifies warnings before generic errors;
- mobile menu handlers remain idempotent;
- legacy `img.button` icons have explicit compact dimensions;
- student re-registration payment labels are human-readable;
- no public-facing `Select Academy SIS` literal remains in the primary entry pages;
- PR contains no database migration files.

The PR remains draft until the final Docker/browser matrix below is completed.

## Final Docker/browser regression matrix

Run on the current branch at 1440, 1024, 830, 768, 480 and 360 where applicable.

### Public entry and branding
- landing page loads from unauthenticated root;
- Login and Register routes work;
- configured platform name/logo/favicon/primary/secondary colors propagate;
- login background loads correctly;
- public Registration and Registration Payment show configured branding;
- Registration Back to Home works.

### Login
- desktop right panel remains slim, full-height and flush right;
- mobile panel is centered and scroll-safe;
- Forgot Password, Register and Back to Home work;
- invalid login error appears in modal;
- no About section;
- no document-level horizontal overflow.

### Responsive shell
- desktop sidebar visible at 1024/1440;
- drawer opens with one click/Enter at <=874;
- drawer is above backdrop and links navigate;
- backdrop and Escape close;
- repeated 360 -> 1440 -> 360 resize leaves no spacer/gap.

### Staff workflows
- Online Applications list/detail;
- Application Payments list/detail;
- Registration Fees;
- Grade Import;
- Class Rank;
- Class Grade Reports;
- Re-Registration Requests;
- Re-Registration Payments;
- readable status labels and consistent badge colors;
- no oversized legacy icons.

### Feedback
- error modal;
- success modal;
- real warning modal;
- keyboard focus trapped in modal;
- Escape dismisses and focus restores.

### Student portal
- Dashboard, Courses, Grades, Payments, Profile and Re-Registration;
- configured branding visible;
- URL student-id tampering does not change student identity;
- grades/ranks/payments unchanged from validated integration baseline.

### Functional smoke
- one invalid Grade Import validation;
- one Class Rank recalculation;
- one read-only staff report;
- one registration validation error;
- one public payment validation error;
- no approval, payment verification, enrollment or grade mutation is required solely for UI validation.


## Final regression fixes after 2026-10-12 browser pass

Three confirmed issues from the final Docker/browser regression were corrected:

1. Landing branding
   - the configured platform logo is now rendered in the landing header;
   - configured platform name is rendered beside it;
   - configured tagline is rendered when present.

2. Landing mobile navigation
   - a dedicated Menu control appears at mobile widths;
   - Grades 9–12, Packages, ESSLCE, Student Services and About are available from the expanded menu;
   - selecting a menu link closes the mobile menu;
   - the control exposes `aria-controls` and `aria-expanded`.

3. Informational feedback semantics
   - `ErrorMessage()` now supports an explicit `info` type;
   - informational messages remain inline and use a blue information treatment;
   - the student password-reset availability notice now uses `info`;
   - the Rollover greyed-item explanation now uses `info`;
   - real success notes remain `note` and continue to use the success modal.

Targeted retest required before PR readiness:
- temporary branding change must show logo, platform name and tagline on `landing.php`;
- 360px and 480px landing menu must expose all hidden navigation items;
- Forgot Password must show the student-reset-disabled message inline with no Success modal;
- Rollover warning must remain a warning modal, and the greyed-item explanation must remain inline with no queued Success modal.
