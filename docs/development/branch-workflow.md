# Git Branch Workflow

## Purpose

This document defines the standard Git workflow for Abugida SIS.

The workflow is intentionally simple:

- developers work independently on feature branches;
- developers test their own work locally;
- completed feature branches are merged into `develop`;
- `main` is off limits for normal developer work;
- only the project owner/maintainer promotes tested integrated code from `develop` to `main`.

## Branch Model

```text
feature/*, fix/*, docs/*
          |
          v
       develop
          |
          v
         main
```

`main` is controlled by the project owner/maintainer.

## 1. main

`main` is the stable release branch.

Developers must not:

- work directly on `main`;
- commit normal development work to `main`;
- push directly to `main`;
- merge feature branches directly into `main`.

Only the project owner/maintainer should merge `develop` into `main` for a release.

## 2. develop

`develop` is the shared integration branch.

Developers use it as the starting point for new work and as the destination for completed work.

Developers should not build features directly on `develop`.

Instead:

```text
develop -> feature branch -> local test -> push feature branch -> PR -> develop
```

There is no mandatory code reviewer. The developer who owns a feature may merge their own Pull Request into `develop` after completing local testing and documentation.

## 3. Branch naming

Recommended prefixes:

| Prefix | Purpose |
| --- | --- |
| `feature/` | New functionality |
| `fix/` | Bug fix |
| `docs/` | Documentation-only change |
| `refactor/` | Internal restructuring |
| `hotfix/` | Urgent production fix coordinated by the project owner |

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

## 4. Start a new feature

Always update `develop` first:

```powershell
git checkout develop
git pull origin develop
```

Then create your branch:

```powershell
git checkout -b feature/payment-verification
```

Confirm the active branch:

```powershell
git branch
```

The current feature branch should have `*` beside it.

## 5. Develop and test locally

Work only on the feature branch.

Run the application locally, test the feature, and confirm that the intended workflow works before merging.

Check changed files:

```powershell
git status
```

Stage the intended changes:

```powershell
git add .
```

Review again:

```powershell
git status
```

Commit:

```powershell
git commit -m "Add payment verification workflow"
```

The same branch should also contain the related documentation under `docs/`.

## 6. Push the feature branch

Push the feature branch to GitHub:

```powershell
git push -u origin feature/payment-verification
```

After the first push, later updates can use:

```powershell
git push
```

Developers must not push normal feature work directly to:

```text
origin/main
origin/develop
```

## 7. Open a Pull Request to develop

Create a Pull Request on GitHub:

```text
base: develop
compare: feature/payment-verification
```

The PR is the controlled merge step and creates a clear history of the feature entering `develop`.

No separate code-review approval is required.

Before merging their own PR, the developer must confirm:

- the feature works locally;
- relevant failure/error cases were checked;
- documentation is included or updated;
- required database migration/schema changes are included;
- no passwords, secrets, database dumps, uploaded documents, receipts, or student photos are committed;
- the branch has no unresolved merge conflicts.

Then the developer may merge the PR into `develop`.

## 8. If develop changed during feature development

Update the local feature branch before merging:

```powershell
git checkout develop
git pull origin develop
git checkout feature/payment-verification
git merge develop
```

Resolve conflicts locally.

Then test again:

```powershell
git add .
git commit
git push
```

The existing Pull Request updates automatically after the push.

## 9. After merging into develop

Update local `develop`:

```powershell
git checkout develop
git pull origin develop
```

Delete the completed local branch:

```powershell
git branch -d feature/payment-verification
```

The remote feature branch can also be deleted from GitHub after merge.

## 10. Start the next feature

Always start the next task from the latest `develop`.

Do not branch from a completed feature branch.

```powershell
git checkout develop
git pull origin develop
git checkout -b feature/next-feature
```

## 11. Release process

The normal path is:

```text
feature/* -> develop -> main
```

Developers are responsible for:

```text
feature branch -> develop
```

The project owner/maintainer is responsible for:

```text
develop -> main
```

When the integrated version on `develop` has been tested and accepted, the project owner/maintainer promotes it to `main` and can tag the release.

Developers must not independently merge into `main`.

## 12. Hotfix process

A production hotfix starts from `main` only with project-owner coordination.

Typical flow:

```text
main -> hotfix/* -> main
                 -> develop
```

The fix must also return to `develop` so future releases retain it.

## 13. Pull rules

| Situation | Pull from |
| --- | --- |
| Starting a normal feature | `origin/develop` |
| Continuing feature work | own feature branch |
| Updating feature with team changes | `origin/develop` |
| Checking latest integrated version | `origin/develop` |
| Checking stable release | `origin/main` |

## 14. Push and merge rules

| Work | Push directly to | Merge destination |
| --- | --- | --- |
| New feature | own `feature/*` branch | `develop` |
| Bug fix | own `fix/*` branch | `develop` |
| Documentation task | own `docs/*` branch | `develop` |
| Refactor | own `refactor/*` branch | `develop` |
| Production release | not a normal developer action | project owner merges `develop` -> `main` |

## 15. Developer checklist before merging to develop

1. Confirm the active branch with `git branch`.
2. Pull current `develop` and merge it into the feature branch if necessary.
3. Run `git status`.
4. Test the feature locally.
5. Test important error/negative paths.
6. Confirm `config.inc.php` is not committed.
7. Confirm no secrets or user data are committed.
8. Include required database changes.
9. Update the relevant documentation under `docs/`.
10. Push the feature branch.
11. Open PR to `develop`.
12. Merge the PR only after the above checks pass.

## Example complete workflow

```powershell
# Update integration branch
git checkout develop
git pull origin develop

# Create feature branch
git checkout -b feature/student-application

# Develop and test locally

# Stage and commit
git status
git add .
git status
git commit -m "Add student application workflow"

# Push feature branch
git push -u origin feature/student-application
```

Then on GitHub:

```text
Pull Request:
feature/student-application -> develop
```

After local testing and documentation are complete, the developer may merge the PR.

Then:

```powershell
git checkout develop
git pull origin develop
git branch -d feature/student-application
```

The developer is now ready to start the next task from the updated `develop`.
