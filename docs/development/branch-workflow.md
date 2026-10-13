# Git Branch Workflow

## Purpose

This document defines the standard Git workflow for Abugida SIS.

The workflow is intentionally simple:

- developers work independently on feature branches;
- developers test their own work locally;
- `Stable/sis` is the current validated working baseline;
- feature/fix branches start from `Stable/sis` and return there only after testing;
- `develop` and `main` are not changed until the project owner explicitly promotes the stable line.

## Branch Model

```text
Stable/sis
   |
   +--> feature/*, fix/*, docs/*
             |
             +--> tested merge back to Stable/sis

Later, by project-owner decision:
Stable/sis -> develop -> main
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

## 2. Stable/sis

`Stable/sis` is the current validated baseline. It is the starting point for new work
and the destination for a feature/fix only after focused regression passes.

Do not develop directly on `Stable/sis`. Instead:

```text
Stable/sis -> feature branch -> local test -> push -> PR -> Stable/sis
```

## 3. develop

`develop` remains the long-term integration branch. It is intentionally left
unchanged for now. The project owner will decide when to promote `Stable/sis` into
`develop`.

## 4. Branch naming

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

## 5. Start a new feature

Always update `Stable/sis` first:

```powershell
git checkout Stable/sis
git pull origin Stable/sis
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

## 6. Develop and test locally

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

## 7. Push the feature branch

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
origin/Stable/sis
```

## 8. Open a Pull Request to Stable/sis

Create a Pull Request on GitHub:

```text
base: Stable/sis
compare: feature/payment-verification
```

The PR is the controlled merge step and creates a clear history of the feature entering the current validated baseline.

No separate code-review approval is required.

Before merging their own PR, the developer must confirm:

- the feature works locally;
- relevant failure/error cases were checked;
- documentation is included or updated;
- required database migration/schema changes are included;
- no passwords, secrets, database dumps, uploaded documents, receipts, or student photos are committed;
- the branch has no unresolved merge conflicts.

Then the developer may merge the PR into `Stable/sis`.

## 9. If Stable/sis changed during feature development

Update the local feature branch before merging:

```powershell
git checkout Stable/sis
git pull origin Stable/sis
git checkout feature/payment-verification
git merge Stable/sis
```

Resolve conflicts locally.

Then test again:

```powershell
git add .
git commit
git push
```

The existing Pull Request updates automatically after the push.

## 10. After merging into Stable/sis

Update local `Stable/sis`:

```powershell
git checkout Stable/sis
git pull origin Stable/sis
```

Delete the completed local branch:

```powershell
git branch -d feature/payment-verification
```

The remote feature branch can also be deleted from GitHub after merge.

## 11. Start the next feature

Always start the next task from the latest `Stable/sis`.

Do not branch from a completed feature branch.

```powershell
git checkout Stable/sis
git pull origin Stable/sis
git checkout -b feature/next-feature
```

## 12. Release process

The normal path is:

```text
Stable/sis -> feature/* -> Stable/sis; later Stable/sis -> develop -> main
```

Developers are responsible for:

```text
feature branch -> Stable/sis
```

The project owner/maintainer is responsible for deciding when to promote:

```text
Stable/sis -> develop -> main
```

Until that decision, `develop` and `main` remain unchanged.

Developers must not independently merge into `main`.

## 13. Hotfix process

A production hotfix starts from `main` only with project-owner coordination.

Typical flow:

```text
main -> hotfix/* -> main
                 -> develop
```

The fix must also return to `develop` so future releases retain it.

## 14. Pull rules

| Situation | Pull from |
| --- | --- |
| Starting a normal feature | `origin/Stable/sis` |
| Continuing feature work | own feature branch |
| Updating feature with accepted changes | `origin/Stable/sis` |
| Checking current validated baseline | `origin/Stable/sis` |
| Checking stable release | `origin/main` |

## 15. Push and merge rules

| Work | Push directly to | Merge destination |
| --- | --- | --- |
| New feature | own `feature/*` branch | `develop` |
| Bug fix | own `fix/*` branch | `develop` |
| Documentation task | own `docs/*` branch | `develop` |
| Refactor | own `refactor/*` branch | `develop` |
| Production release | not a normal developer action | project owner later promotes `Stable/sis` -> `develop` -> `main` |

## 16. Developer checklist before merging to Stable/sis

1. Confirm the active branch with `git branch`.
2. Pull current `Stable/sis` and merge it into the feature branch if necessary.
3. Run `git status`.
4. Test the feature locally.
5. Test important error/negative paths.
6. Confirm `config.inc.php` is not committed.
7. Confirm no secrets or user data are committed.
8. Include required database changes.
9. Update the relevant documentation under `docs/`.
10. Push the feature branch.
11. Open PR to `Stable/sis`.
12. Merge the PR only after the above checks pass.

## 17. Example complete workflow

```powershell
# Update integration branch
git checkout Stable/sis
git pull origin Stable/sis

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
feature/student-application -> Stable/sis
```

After local testing and documentation are complete, the developer may merge the PR.

Then:

```powershell
git checkout Stable/sis
git pull origin Stable/sis
git branch -d feature/student-application
```

The developer is now ready to start the next task from the updated `Stable/sis`.
