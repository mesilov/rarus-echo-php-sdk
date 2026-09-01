## 1. OpenSpec

- [x] 1.1 Create proposal, task list, and spec deltas for issue #27.
- [x] 1.2 Validate OpenSpec with `make lint-openspec`.

## 2. Maintainer workflow

- [x] 2.1 Add a maintainer skill rule to update local `dev` from `origin/dev` before creating an issue worktree.
- [x] 2.2 Verify the skill rule is discoverable through the shared skill content.

## 3. README

- [x] 3.1 Remove `--pull=always` from default Docker CLI examples.
- [x] 3.2 Keep forced image refresh documented as an explicit opt-in.
- [x] 3.3 Verify README command examples no longer contain `--pull=always`.

## 4. Release notes

- [x] 4.1 Add `CHANGELOG.md` `Unreleased` entries for README and maintainer workflow changes.

## 5. Verification

- [x] 5.1 Run `git diff --check`.
- [x] 5.2 Run `make test-unit`.
- [x] 5.3 Run `make lint-all`.
