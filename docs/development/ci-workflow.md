# Continuous Integration: PHP Syntax and Project Validation

## Purpose

Abugida SIS uses a lightweight GitHub Actions workflow to catch basic project problems.
The repository's current automated trigger remains centered on `develop`, while
`Stable/sis` is temporarily the validated working baseline.

The workflow is intentionally limited to syntax and structural validation at this stage. It does not deploy the application and it does not replace local testing.

## Workflow file

```text
.github/workflows/php-ci.yml
```

## When it runs

The workflow currently runs automatically when:

- a Pull Request targets `develop`;
- code is pushed to `develop`;
- a maintainer starts it manually from GitHub Actions.

Because new work is currently based on `Stable/sis`, use the manual GitHub Actions
run (or local syntax/Compose validation) for a PR targeting `Stable/sis` until the
workflow trigger is deliberately expanded. Do not document a Stable/sis PR as having
automatic CI unless a run actually occurred.

## Current checks

The workflow performs:

1. PHP 8.1 environment setup.
2. Required project-file validation.
3. `composer.json` validation.
4. PHP syntax validation for all `*.php` files under `rosariosis/`.
5. Docker Compose configuration validation.

## Required files checked

The CI currently expects these files to exist:

```text
Dockerfile
docker-compose.yaml
php.ini
rosariosis/index.php
rosariosis/config.inc.sample.php
rosariosis/composer.json
```

If one is missing, the workflow fails.

## Developer workflow

A developer still develops and tests locally first:

```text
Stable/sis
  -> feature branch
  -> local development
  -> local testing
  -> push feature branch
  -> Pull Request to Stable/sis
  -> run current CI manually (or validate locally)
  -> merge to Stable/sis when checks pass
```

If CI fails, the developer should fix the issue on the same feature branch, commit, and push again. The existing Pull Request will automatically run the checks again.

## Important limitation

Passing CI means the repository passed basic automated validation. It does not prove that the feature works correctly from a business or user perspective.

Developers remain responsible for local functional testing before merging into the current target branch.

## Future extensions

Additional checks can be added later, such as:

- automated application tests;
- database migration checks;
- API/integration tests;
- security/static-analysis checks;
- Docker build tests;
- staging deployment checks.
