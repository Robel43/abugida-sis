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

Docker is used only to provide a consistent local development environment. The application itself does not depend on Docker and can be deployed normally on a standard PHP/MySQL hosting environment such as cPanel.

Current local development stack:

- PHP 8.1
- Apache
- MariaDB 10.11
- Docker Compose
- Local URL: `http://localhost:8090`

## Repository Structure

A typical development checkout contains:

```text
abugida-sis/
├── rosariosis/            Application source
├── docs/                  Project and feature documentation
├── database/              Database migrations and seed data
├── Dockerfile             Local development PHP/Apache image
├── docker-compose.yml     Local development services
├── php.ini                Local PHP settings
├── .gitignore
└── README.md
```

The source directory name may be renamed later as part of project cleanup. The application name used by this project is **Abugida SIS**.

## Local Installation with Docker

### Prerequisites

Install:

- Git
- Docker Desktop
- Docker Compose

Docker Desktop must be running before starting the environment.

### 1. Clone the repository

```powershell
git clone https://github.com/Robel43/abugida-sis.git
cd abugida-sis
```

### 2. Create the local application configuration

Copy the sample configuration file to:

```text
config.inc.php
```

Configure the development database connection to use the Docker database service:

```php
$DatabaseType = 'mysql';
$DatabaseServer = 'db';
$DatabaseUsername = 'abugida_user';
$DatabasePassword = 'abugida_dev_password';
$DatabaseName = 'abugida_sis';
```

Do not commit `config.inc.php`.

### 3. Build the development image

```powershell
docker compose build
```

### 4. Start the environment

```powershell
docker compose up -d
```

### 5. Verify the containers

```powershell
docker compose ps
```

The web and database containers should both be running.

### 6. Open Abugida SIS

Open:

```text
http://localhost:8090
```

For a fresh database, run the application's database installer before using the system.

### 7. Stop the environment

```powershell
docker compose down
```

Do not use `docker compose down -v` unless you intentionally want to delete the local database volume.

## Development Workflow

The permanent branches are:

- `main` - stable, production-ready code
- `develop` - latest integrated development build

Development is done in temporary feature branches created from `develop`.

Example:

```powershell
git checkout develop
git pull
git checkout -b feature/payment-verification
```

After implementation and local testing:

```powershell
git add .
git commit -m "Add payment verification workflow"
git push origin feature/payment-verification
```

Create a pull request from the feature branch into `develop`.

After integration testing and UAT, `develop` is merged into `main`.

## Documentation Policy

Every feature, workflow change, integration change, database migration, and significant configuration decision must be documented in the `docs/` directory.

At minimum, each feature should document:

- Purpose
- Scope
- Business rules
- Roles involved
- Database changes
- Files/modules changed
- Configuration changes
- Testing performed
- Known limitations
- Deployment or migration steps

See `docs/README.md` and `docs/FEATURE_TEMPLATE.md`.

## Production Deployment

Docker is not required for production.

A production release from `main` can be deployed to a standard cPanel PHP/MySQL or MariaDB environment.

Typical deployment flow:

1. Create a tested release from `main`.
2. Back up the production files and database.
3. Upload the application files to the cPanel application directory.
4. Configure the production `config.inc.php`.
5. Run any required database migrations.
6. Verify permissions and PHP extensions.
7. Perform smoke testing.
8. Confirm registration, finance, academic, and integration workflows.

Production credentials must never be committed to GitHub.

## Project Status

The project is under active development. Planned work includes registrar workflow customization, finance workflow, role-based access control, Moodle integration, grade synchronization, grade-review workflow, reporting, and audit logging.
