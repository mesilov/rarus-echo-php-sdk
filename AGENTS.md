# Repository Agent Instructions

This repository uses issue-first maintenance and OpenSpec for non-trivial changes.

## Start Of Work

1. Read the linked GitHub issue before implementation.
2. Check the repository state from the root:
   ```bash
   pwd
   git status --short --branch
   openspec list
   openspec list --specs
   ```
3. Preserve unrelated local changes.
4. Use `dev` as the base branch unless the user explicitly says otherwise.

## OpenSpec Policy

Create or update an OpenSpec change for:

- public SDK API changes;
- behavior changes visible to SDK users;
- architecture or service-layer changes;
- support-process, maintainer-process, or CI workflow changes.

OpenSpec is optional for typos, dependency bumps without behavior changes, trivial one-file documentation edits, and mechanical formatting.

When OpenSpec is required:

1. Check for overlapping active changes with `openspec list`.
2. Create or continue `openspec/changes/<change-id>/`.
3. Keep `proposal.md`, optional `design.md`, spec deltas, and `tasks.md` aligned with the implementation.
4. Validate with `make lint-openspec`.
5. Archive completed changes only after the linked pull request has merged:
   ```bash
   openspec archive <change-id> --yes
   ```

## Pull Request Review Comments

After opening or updating a pull request, check inline review threads and top-level comments from agent reviewers such as Codex, Claude, or other review bots.

For each agent comment:

- verify the feedback against the current codebase before changing code;
- implement the fix when the feedback is technically correct;
- reply with a concise technical reason when the feedback is obsolete or not applicable;
- resolve the review thread only after the fix is present, the comment is obsolete, or the response explains why no change is needed.

Do not report issue work complete while required CI checks or unresolved agent review threads remain.
