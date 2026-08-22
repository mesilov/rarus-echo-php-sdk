---
name: rarus-echo-maintainer
description: Use when working with GitHub issues, maintainer workflow, OpenSpec changes, branches, pull requests, or CI for mesilov/rarus-echo-php-sdk.
user-invocable: true
---

# RARUS Echo Maintainer

Repository: `mesilov/rarus-echo-php-sdk`

Use this skill for issue-driven maintenance work in this repository: reading issues, planning implementation, deciding whether OpenSpec is required, creating branches, running validation, opening pull requests, and checking CI.

## Start Of Work

1. Load the GitHub issue before implementation. Read title, body, labels, milestone, assignees, comments, and linked pull requests.
2. From the repository root, check current state:
   ```bash
   pwd
   git status --short --branch
   openspec list
   openspec list --specs
   ```
3. Preserve unrelated local changes. Do not reset, delete, stage, or reformat files outside the issue scope.
4. Use `dev` as the base branch for issue work unless the user explicitly says otherwise.
5. Create a branch named:
   ```text
   feature/<issue-number>-<short-slug>
   bugfix/<issue-number>-<short-slug>
   docs/<issue-number>-<short-slug>
   ```

## OpenSpec Policy

Create or update an OpenSpec change for:

- public SDK API changes;
- behavior visible to SDK users;
- architecture or service-layer changes;
- CI, support-process, or maintainer-process changes.

OpenSpec may be skipped for typos, dependency bumps, mechanical formatting, and trivial one-file documentation edits.

When OpenSpec is required:

1. Check for overlapping active changes with `openspec list`.
2. Create or continue `openspec/changes/<change-id>/`.
3. Keep `proposal.md`, `design.md` when useful, `tasks.md`, and spec deltas aligned with the implementation.
4. Validate with:
   ```bash
   openspec validate --all --strict --no-interactive
   ```
5. Archive completed changes only after the corresponding pull request is merged:
   ```bash
   openspec archive <change-id> --yes
   ```

## Implementation Rules

- Follow existing SDK structure: `Services`, `Core`, `Infrastructure`, `Contracts`, immutable result/configuration objects, strict types, and PSR-compatible dependencies.
- Keep changes scoped to the issue.
- Add or update tests when PHP runtime behavior changes.
- Update `README.md`, `CONTRIBUTING.md`, or OpenSpec artifacts when workflow or public usage changes.
- Do not introduce Bitrix24-specific rules, generated result-item contracts, OpenAPI refresh steps, or v1/v3 branch selection. Those belong to other SDKs, not this repository.

## Validation

For OpenSpec or workflow-only changes, run:

```bash
openspec validate --all --strict --no-interactive
git diff --check
make test-unit
make lint-all
```

For PHP behavior changes, also run the focused unit tests that cover the changed code. Run integration tests only when the issue touches live API behavior and credentials are available:

```bash
make test-integration
```

If a required check cannot run because of missing infrastructure or credentials, report that exact blocker and do not describe the issue as complete.

## Pull Request

Open the pull request only after local validation is green or after an explicitly documented external blocker.

Rules:

- Push the issue branch to `origin`.
- Open the pull request against `dev`.
- Include `Closes #<issue-number>` in the PR body as plain text.
- Mention the validation commands that passed.
- Check PR CI after opening or updating the PR.
- Do not report the issue complete until required CI checks are green.
