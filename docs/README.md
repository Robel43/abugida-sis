# Abugida SIS Documentation

This directory is the official technical and functional documentation area for Abugida SIS.

Every significant feature or change must be documented on the same feature branch that implements it.

## Documentation Structure

```text
docs/
├── README.md
├── CHANGELOG.md
├── FEATURE_TEMPLATE.md
├── architecture/
├── development/
│   ├── branch-workflow.md
│   └── ci-workflow.md
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

## Development Workflow

The team follows this model:

```text
develop -> feature branch -> local test -> push feature branch -> PR -> CI -> develop
```

There is no mandatory code-review approval step.

The developer responsible for a feature may merge their own Pull Request into `develop` after confirming that:

- the feature works locally;
- required documentation is complete;
- database changes are included and documented;
- no secrets or user-generated data are committed;
- the branch merges cleanly;
- required GitHub Actions checks pass.

`main` is off limits for normal developer work.

Only the project owner/maintainer promotes accepted integrated work from:

```text
develop -> main
```

Detailed development guides:

```text
docs/development/branch-workflow.md
docs/development/ci-workflow.md
```

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
- required database changes are included;
- documentation is updated on the same feature branch;
- the feature branch is pushed to GitHub;
- a Pull Request is opened to `develop`;
- required automated checks pass;
- the developer confirms the feature is ready;
- the Pull Request is merged into `develop`.

Normal developers must not push or merge feature work into `main`.
