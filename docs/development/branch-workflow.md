# Git Branch Workflow

## Purpose

This document defines how Abugida SIS developers create branches, pull changes, push work, open pull requests, and move tested code toward release.

The purpose is to keep `main` stable while allowing several developers to work independently without overwriting each other's changes.

## Branch Model

```text
main
  ^
  |
develop
  ^
  |
feature/*, fix/*, docs/*
```

For urgent production fixes:

```text
main -> hotfix/* -> main
                  -> develop
```

## 1. main

`main` is the stable release branch.

It should contain only code that has passed integration testing and is approved for release.

Developers must not:

- implement features directly on `main`;
- commit normal development work directly to `main`;
- push directly to `main`.

A release is produced by merging reviewed `develop` into `main`.

## 2. develop

`develop` is the integration branch.

It contains completed features that have passed their feature-level review and are ready for combined testing.

Developers normally pull from `develop` before beginning a task.

Developers must not use `develop` as their personal working branch.

## 3. Feature branches

Every development task should have its own branch.

Recommended prefixes:

| Prefix | Purpose |
| --- | --- |
| `feature/` | New functionality |
| `fix/` | Non-production bug fix |
| `docs/` | Documentation-only change |
| `refactor/` | Internal code restructuring |
| `hotfix/` | Urgent fix based on production/main |

Examples:

```text
feature/registration-workflow
feature/payment-verification
feature/moodle-user-sync
feature/grade-submission
fix/section-transfer-history
docs/installation-guide
```

Use short lowercase names separated by hyphens.

## 4. Starting new work

Always update `develop` first:

```powershell
git checkout develop
git pull origin develop
```

Then create a branch:

```powershell
git checkout -b feature/payment-verification
```

Confirm:

```powershell
git branch
```

The active branch should have `*` beside the feature branch.

## 5. Working and committing

Develop and test locally.

Review changed files:

```powershell
git status
```

Stage the intended changes:

```powershell
git add .
```

Review again before committing:

```powershell
git status
```

Commit with a clear message:

```powershell
git commit -m "Add payment verification workflow"
```

A feature should include its related documentation in `docs/` before the pull request is considered complete.

## 6. Where developers push

Developers push their own feature branch:

```powershell
git push -u origin feature/payment-verification
```

After the first push, later updates can use:

```powershell
git push
```

Do not push normal feature work directly to:

```text
origin/main
origin/develop
```

## 7. Pull request destination

Normal feature pull requests always target:

```text
feature/* -> develop
```

Example:

```text
feature/payment-verification -> develop
```

The pull request should explain:

- what changed;
- why the change is needed;
- business rules implemented;
- database changes;
- configuration changes;
- how it was tested;
- documentation added or updated.

## 8. Updating a feature with new develop changes

If other work has already been merged into `develop`, update the feature before final review:

```powershell
git checkout develop
git pull origin develop
git checkout feature/payment-verification
git merge develop
```

Resolve any conflicts locally.

Then:

```powershell
git add .
git commit
git push
```

Run the relevant tests again after resolving conflicts.

## 9. After a pull request is merged

Update local `develop`:

```powershell
git checkout develop
git pull origin develop
```

Delete the completed local feature branch:

```powershell
git branch -d feature/payment-verification
```

The merged remote feature branch can also be deleted.

## 10. Starting the next task

Do not branch from an old feature branch.

Always start again from current `develop`:

```powershell
git checkout develop
git pull origin develop
git checkout -b feature/next-feature
```

## 11. Release process

When a set of features is ready and has passed integration testing/UAT, a maintainer creates a reviewed release merge:

```text
develop -> main
```

After merging, tag the release as appropriate.

Developers should not independently merge `develop` into `main` unless they are responsible for the release.

## 12. Hotfix process

A genuine urgent production fix starts from `main`:

```powershell
git checkout main
git pull origin main
git checkout -b hotfix/short-description
```

After testing, push the branch and open a pull request to `main`.

The completed hotfix must also be merged into `develop` so the fix remains part of future releases.

## 13. Pulling rules summary

| Situation | Pull from |
| --- | --- |
| Starting a normal feature | `origin/develop` |
| Continuing feature work | your feature branch, then update from `develop` as needed |
| Checking latest integrated code | `origin/develop` |
| Checking production/release baseline | `origin/main` |
| Starting an urgent production hotfix | `origin/main` |

## 14. Push rules summary

| Work | Push to |
| --- | --- |
| New feature | own `feature/*` branch |
| Bug fix | own `fix/*` branch |
| Documentation-only task | own `docs/*` branch |
| Hotfix | own `hotfix/*` branch |
| Integrated development | merge by PR into `develop` |
| Production release | reviewed merge from `develop` into `main` |

## 15. Important repository rules

Before every push:

1. Confirm the active branch with `git branch`.
2. Run `git status`.
3. Do not commit `config.inc.php`.
4. Do not commit passwords, secrets, production credentials, database dumps, uploaded documents, student photos, or receipts.
5. Include database migration files when schema changes are required.
6. Update documentation for the feature.
7. Test the feature locally.
8. Push only the feature/fix/docs/hotfix branch.

## Example complete developer workflow

```powershell
# Get current integration code
git checkout develop
git pull origin develop

# Create task branch
git checkout -b feature/student-application

# Work and test locally
git status
git add .
git status
git commit -m "Add student application workflow"

# Push task branch
git push -u origin feature/student-application
```

Then create:

```text
Pull Request:
feature/student-application -> develop
```

After review and merge:

```powershell
git checkout develop
git pull origin develop
git branch -d feature/student-application
```
