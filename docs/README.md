# Abugida SIS Documentation

This directory is the official technical and functional documentation area for Abugida SIS.

Every significant feature or change must be documented as part of the same feature branch and pull request that introduces the change.

## Documentation Structure

```text
docs/
├── README.md
├── CHANGELOG.md
├── FEATURE_TEMPLATE.md
├── architecture/
├── development/
│   └── branch-workflow.md
├── features/
├── workflows/
├── database/
├── integration/
├── testing/
└── deployment/
```

Create subdirectories when the first document for that category is added.

## Required Documentation for Features

Each implemented feature should document:

1. Feature name
2. Purpose
3. Business requirements
4. Roles and permissions
5. User workflow
6. Technical implementation
7. Database/schema changes
8. Configuration changes
9. API or integration changes
10. Test cases
11. Known limitations
12. Deployment/migration notes

Use `FEATURE_TEMPLATE.md` as the starting point.

## Development Documentation

Development process documentation belongs under `docs/development/`.

The branch and pull-request workflow is defined in:

```text
docs/development/branch-workflow.md
```

All developers are expected to follow that workflow.

## Naming Convention

Use lowercase descriptive filenames with hyphens.

Examples:

```text
features/student-registration-workflow.md
features/payment-verification.md
integration/moodle-user-provisioning.md
integration/grade-sync.md
workflows/grade-review.md
database/001-registration-tables.md
```

## Change Documentation Rule

A feature is not considered complete until:

- code is implemented;
- local testing is complete;
- required database migration is included;
- documentation is updated on the same feature branch;
- the feature branch is pushed to GitHub;
- a pull request to `develop` is reviewed;
- required corrections are completed;
- the pull request is merged into `develop`.

Normal feature development must not be pushed directly to `main` or `develop`.
