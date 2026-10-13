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


## Academic Structure and Moodle Mapping

Abugida SIS uses RosarioSIS academic objects in a way that avoids duplicating Moodle courses for school sections.

### Current mapping

| Abugida / RosarioSIS | Purpose | Moodle mapping |
| --- | --- | --- |
| Grade Level | Official student enrollment grade | Student grade metadata |
| Subject, e.g. `Grade 10` | Grade-level course category | Moodle course category |
| Course, e.g. `Amharic` | One academic subject | One Moodle course |
| Course Period, e.g. `10A`, `10B` | Section / class instance | Moodle group |
| Teacher on Course Period | Section teacher assignment | Moodle teacher assignment |
| Students scheduled into Course Period | Section roster | Moodle group membership |

Example:

```text
Grade 10
├── Amharic
│   ├── 10A
│   └── 10B
├── English
│   ├── 10A
│   └── 10B
└── Mathematics
    ├── 10A
    └── 10B
```

Moodle should create only one course for each grade/subject combination, for example **Grade 10 Amharic**. Sections `10A` and `10B` must be created as Moodle groups inside that course, not as duplicate Moodle courses.

### Self-paced online delivery

The platform is designed for self-paced online learning, so RosarioSIS timetable fields are used only where the underlying data model requires them.

Recommended Course Period settings:

- one generic school period such as `Online Learning`;
- no physical room;
- no meeting-day requirement unless RosarioSIS validation requires one;
- attendance disabled unless attendance is intentionally managed in the SIS;
- course period title/short name used as the section identifier, e.g. `10A`;
- teacher assigned at the Course Period level;
- official grading scale selected, with teacher grade-scale changes disabled where Moodle is authoritative.

Course Periods should be understood as **section/class instances**, not as physical timetable periods.

## Registration and Automatic Section Assignment

The intended registration flow asks the user to select only the **grade level**.

Section assignment is planned as custom logic:

1. Student registers and is enrolled in a grade.
2. The system finds the available sections for that grade, for example `7A` and `7B`.
3. The system counts active students in each section.
4. The student is assigned to the least-filled section, subject to configured capacity.
5. The student is scheduled into every Course Period for that section across the grade's courses.
6. The Moodle integration enrolls the student into the grade's Moodle courses and adds the student to the matching Moodle group.
7. Administrators retain a manual override for section transfers.

The section assignment should be deterministic, balanced, capacity-aware, and idempotent so re-running synchronization does not create duplicate schedule records.

## Grade Synchronization and Ranking Model

Moodle is the authoritative system for assessment calculation.

### Moodle responsibilities

- quizzes, assignments, activities, and category weights;
- gradebook calculation;
- subject percentage calculation;
- teacher corrections to assessment results;
- section-level learning analytics.

### Abugida SIS responsibilities

- official student enrollment and section records;
- official marking periods;
- storage of synchronized subject percentages;
- report cards and transcripts;
- semester and annual result reporting;
- official rank output.

The planned marking-period structure is:

- Full Year (`FY`)
- Semester 1 (`S1`)
- Semester 2 (`S2`)

Numeric percentages such as `85.0`, `90.0`, or `76.5` are the primary academic result. Letter-grade bands may remain configured in RosarioSIS for compatibility or secondary interpretation, but Moodle percentages are the authoritative values.

### Ranking

The required school ranking model is percentage-based, not subject-by-subject GPA ranking.

- **Semester 1 average**: average of all applicable Semester 1 subject percentages.
- **Semester 1 rank**: rank students using the Semester 1 overall average.
- **Semester 2 average**: average of all applicable Semester 2 subject percentages.
- **Semester 2 rank**: rank students using the Semester 2 overall average.
- **Full Year average**: combine Semester 1 and Semester 2 according to the approved school formula; the current default assumption is equal weighting.
- **Final rank**: rank students using the Full Year overall average.

The rank scope must be explicitly configured as either **grade + section** or **whole grade** according to school policy.

RosarioSIS's built-in GPA/Class Rank function should not be treated as the authoritative rank when the school uses this percentage-based model. Semester and annual ranking therefore require custom integration/reporting code.

## Student Import: Setup and Fixes

### Students Import module

Production testing used RosarioSIS 12.9.4 with the Students Import add-on.

The add-on must be installed under:

```text
modules/Students_Import/
```

and should contain files such as:

```text
StudentsImport.php
Menu.php
install.sql
install_mysql.sql
classes/
includes/
js/
locale/
```

### PHP parse error fixed in StudentsImport.php

A production installation returned an HTTP 500 error when opening **Students -> Student Import**. With PHP errors enabled, the root cause was:

```text
Parse error: syntax error, unexpected ';' in modules/Students_Import/StudentsImport.php on line 219
```

The affected conditional output was missing a closing parenthesis. The corrected pattern is:

```php
AttrEscape( $alert_txt ) : htmlspecialchars( $alert_txt ) );
```

The same file also contained uses of the invalid PHP constant:

```text
ENT_QUOTE
```

which must be:

```text
ENT_QUOTES
```

After correcting the syntax and constants, the file should pass a PHP syntax check before deployment.

### Import-grade behavior

When Student Import is configured with one fixed Grade Level for an import batch, every imported student receives that grade. To preserve different grade levels, either:

- import grade-specific batches; or
- map the source Grade field to RosarioSIS Grade Level when the import workflow supports it.

Do not re-import existing students solely to fix grade placement, because that can create duplicate accounts. Update the current enrollment record instead.

## Production Image/File Upload Fix

RosarioSIS payment attachments and other uploaded files use the runtime upload tree under:

```text
assets/FileUploads/<year>/student_<id>/
```

Observed production errors included:

```text
Folder not created: assets/FileUploads/2026/student_151/
Folder not writable: assets/FileUploads/2026/student_151/
```

A folder manually set to `0777` accepted uploads, confirming a PHP process ownership/write-permission problem. `0777` is only a diagnostic and must not be used as the permanent solution.

### Required production configuration

For cPanel/WHM deployments:

1. Enable PHP-FPM for the Abugida SIS domain.
2. Run the domain's PHP-FPM pool as the cPanel account user/group.
3. Ensure PHP 8.3 session storage is writable by PHP. A session error such as the following must be resolved before testing uploads:

   ```text
   session_start(): open(/var/cpanel/php/sessions/ea-php83/sess_..., O_RDWR) failed: Permission denied (13)
   ```

4. Keep the application upload tree owned by the deployment account, for example:

   ```text
   <cpanel-user>:<cpanel-user>
   ```

5. Use normal writable permissions rather than world-writable permissions. Typical values are:
   - directories: `0755` when PHP runs as the owner, or `0775` when group write is required;
   - files: `0644` or `0664` as appropriate.

Example administrative repair:

```bash
chown -R <cpanel-user>:<cpanel-user> /path/to/abugida-sis/assets/FileUploads
find /path/to/abugida-sis/assets/FileUploads -type d -exec chmod 775 {} \;
find /path/to/abugida-sis/assets/FileUploads -type f -exec chmod 664 {} \;
```

The preferred fix is correct PHP-FPM ownership and filesystem permissions so RosarioSIS can create year and student folders automatically. Do not rely on manually creating `2026/`, `student_<id>/`, or leaving upload directories at `0777`.


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
