# Abugida SIS

Abugida SIS is a student information and school administration platform designed for secondary-school operations. It is being developed to support student registration, finance, academic records, staff roles, reporting, and integration with Moodle 4.5 for teaching, assessment, enrolment, and grade synchronization.

## Project Goals

Abugida SIS is intended to provide a centralized administrative system for schools while allowing Moodle to remain the learning and assessment platform.

The planned solution supports:

- Student application and registration
- Document review and registrar approval
- Finance verification and installment tracking
- Grade levels, sections, subjects, and semesters
- Staff roles and permission control
- Student academic records
- Student finance balance and payment history
- Moodle user and enrolment synchronization
- Moodle Gradebook synchronization
- Teacher grade submission
- Department Head review, locking, unlocking, and publication
- Student grade-review requests
- Audit trails and operational reporting

## System Architecture

### Abugida SIS

Abugida SIS is the administrative system of record for:

- Registration
- Student records
- Grade and section placement
- Finance
- Payment history
- Staff administration
- Official grades
- Reporting

### Moodle 4.5

Moodle is used for:

- Course delivery
- Quizzes
- Assignments
- Learning activities
- Gradebook calculations
- Course and section-group enrolment

Approved grades are synchronized from Moodle to Abugida SIS.

## Development Environment

Docker is used to provide a consistent local development environment. Docker is not required for the final production deployment.

Current development stack:

- PHP 8.1
- Apache
- MariaDB 10.11
- Docker Compose
- Web URL: `http://localhost:8090`
- MariaDB host port: `3307`

## Repository Structure

```text
abugida-sis/
├── rosariosis/             Application source
├── docs/                   Project and feature documentation
├── Dockerfile              PHP/Apache development image
├── docker-compose.yaml     Local web and database services
├── php.ini                 Local PHP settings
├── .gitattributes          Repository line-ending rules
├── .gitignore              Local/secrets/runtime exclusions
└── README.md
```

The application name used by this project is **Abugida SIS**.

# Local Installation

## Prerequisites

Install:

- Git
- Docker Desktop
- Docker Compose

Docker Desktop must be running.

## 1. Clone the repository

Developers should clone the repository and work from `develop`:

```powershell
git clone https://github.com/Robel43/abugida-sis.git
cd abugida-sis
git checkout develop
git pull origin develop
```

The `main` branch is the stable release branch and is off limits for normal developer work.

## 2. Create the local application configuration

The real configuration file is intentionally excluded from Git.

Copy:

```text
rosariosis/config.inc.sample.php
```

to:

```text
rosariosis/config.inc.php
```

For the Docker development environment, configure:

```php
$DatabaseType = 'mysql';
$DatabaseServer = 'db';
$DatabaseUsername = 'abugida_user';
$DatabasePassword = 'abugida_dev_password';
$DatabaseName = 'abugida_sis';
```

If a database port variable is used, use the container port `3306`, not the Windows host port `3307`.

Never commit `config.inc.php`, passwords, production credentials, database exports, student photos, uploaded receipts, or other user-generated files.

## 3. Build the Docker image

From the repository root:

```powershell
docker compose build
```

The initial build can take several minutes because PHP extensions and system packages must be installed.

## 4. Start the environment

```powershell
docker compose up -d
```

## 5. Verify the containers

```powershell
docker compose ps
```

Both containers should be running:

```text
abugida-web
abugida-db
```

## 6. Install the database on a fresh environment

For a new database, open:

```text
http://localhost:8090/InstallDatabase.php
```

Complete the database installation once.

Then open:

```text
http://localhost:8090/
```

Do not run the database installer again against an already initialized database unless you intentionally intend to rebuild it.

## 7. Normal daily startup

After the first successful installation, developers normally only need:

```powershell
docker compose up -d
```

Application source changes under `rosariosis/` are mounted into the web container and normally do not require rebuilding the Docker image.

Rebuild only when changing items such as:

- `Dockerfile`
- PHP extensions
- system packages
- relevant container configuration

## 8. Stop the environment

```powershell
docker compose down
```

The named MariaDB volume is preserved.

Do **not** run:

```powershell
docker compose down -v
```

unless you intentionally want to remove the local database volume and start again.

# Git Branching and Development Workflow

Abugida SIS uses a simple feature-branch workflow.

## Permanent branches

### `main`

`main` contains stable code intended for release or production deployment.

For normal development, `main` is **off limits** to developers.

Developers must not:

- develop directly on `main`;
- commit feature work directly to `main`;
- push directly to `main`;
- merge feature branches directly into `main`.

Only the project owner/maintainer controls releases from `develop` into `main`.

### `develop`

`develop` is the shared integration branch.

Developers should:

- pull the latest `develop` before starting a task;
- create their own feature/fix branch from `develop`;
- build and test locally on that branch;
- document the change under `docs/`;
- push their branch to GitHub;
- merge the completed branch into `develop` through a Pull Request.

There is no mandatory code-review step. The developer responsible for the feature may merge their own Pull Request into `develop` after confirming that the feature works locally and its documentation is complete.

## Feature branches

Create one branch per feature, fix, or clearly scoped task.

Examples:

```text
feature/registration-workflow
feature/payment-verification
feature/moodle-user-sync
feature/grade-submission
fix/student-section-transfer
docs/branching-guide
```

Start a feature:

```powershell
git checkout develop
git pull origin develop
git checkout -b feature/payment-verification
```

Develop and test locally.

Then:

```powershell
git add .
git status
git commit -m "Add payment verification workflow"
git push -u origin feature/payment-verification
```

Developers push **their own branch**, not `main` or `develop`.

Then create a Pull Request:

```text
feature/payment-verification -> develop
```

Before merging their own PR into `develop`, the developer must confirm:

- the feature works locally;
- relevant error paths have been tested;
- no secrets or user-generated data are committed;
- any database changes are included and documented;
- the relevant documentation under `docs/` is updated;
- the branch merges cleanly with the current `develop`.

## Keeping a feature branch current

If `develop` changes while a feature is being developed:

```powershell
git checkout develop
git pull origin develop
git checkout feature/payment-verification
git merge develop
```

Resolve conflicts locally, test again, commit if required, and push the updated feature branch.

## After a feature is merged

Once the developer merges the Pull Request into `develop`:

```powershell
git checkout develop
git pull origin develop
git branch -d feature/payment-verification
```

The remote feature branch can also be deleted after merge.

For the next task, always start again from the latest `develop`.

## Release flow

The normal flow is:

```text
feature/* -> develop -> main
```

Developers control their feature work up to `develop`.

The project owner/maintainer controls the move from `develop` to `main`.

A release should only move to `main` after the integrated system has been tested and accepted.

## Hotfixes

Production hotfixes are exceptional and must be coordinated by the project owner/maintainer because they start from `main`.

For the complete branch policy, see `docs/development/branch-workflow.md`.

# Documentation Policy

Every feature, workflow change, integration change, database migration, and significant configuration decision must be documented in `docs/`.

Feature documentation must be committed on the **same feature branch** as the implementation.

At minimum, document:

- Purpose
- Scope
- Business rules
- Roles and permissions
- User workflow
- Technical implementation
- Files/modules changed
- Database changes
- Configuration changes
- Integration/API changes
- Testing performed
- Known limitations
- Deployment or migration steps

See:

- `docs/README.md`
- `docs/FEATURE_TEMPLATE.md`
- `docs/development/branch-workflow.md`

# Production Deployment

Docker is not required for production.

A tested release from `main` can be deployed to a standard PHP/MySQL or MariaDB hosting environment such as cPanel.

Typical deployment sequence:

1. Test and accept the integrated version on `develop`.
2. Project owner/maintainer merges `develop` into `main`.
3. Tag the release.
4. Back up the production files and database.
5. Upload/deploy the application source.
6. Create the production `config.inc.php` outside Git history.
7. Run required database migrations.
8. Verify PHP extensions and filesystem permissions.
9. Perform smoke testing.
10. Confirm registration, finance, academic, reporting, and Moodle integration workflows.

Production credentials must never be committed to GitHub.

## Project Status

Abugida SIS is under active development. Planned work includes registrar workflow customization, finance workflow, role-based access control, Moodle integration, grade synchronization, grade-review workflow, reporting, and audit logging.
