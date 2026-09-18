# Abugida SIS Documentation

This directory is the official technical and functional documentation area for Abugida SIS.

Every significant feature or change must be documented here as part of the same pull request that introduces the change.

## Documentation Structure

Recommended structure:

```text
docs/
├── README.md
├── CHANGELOG.md
├── FEATURE_TEMPLATE.md
├── architecture/
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

- Code is implemented
- Local testing is complete
- Required database migration is included
- Documentation is updated
- Pull request is reviewed
- Changes are merged into `develop`
