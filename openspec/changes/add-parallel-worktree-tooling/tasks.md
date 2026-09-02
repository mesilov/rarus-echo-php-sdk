## 1. Implementation

- [x] 1.1 Add `bin/worktree.sh` with `new`, `remove`, and `list` subcommands.
- [x] 1.2 Add `make worktree-new`, `worktree-remove`, and `worktree-list` targets and document them in `make help`.
- [x] 1.3 Ignore the `.worktree/` directory in `.gitignore`.
- [x] 1.4 Provision new worktrees with a symlinked `.env.local` and a clone-copied `vendor/` (composer install fallback).
- [x] 1.5 Base new worktree branches on `origin/<base>` without moving local `dev`.
- [x] 1.6 Keep the branch on worktree removal.
- [x] 1.7 Update the maintainer skill to create and clean up worktrees with the tooling.
- [x] 1.8 Document the parallel worktree workflow in `CONTRIBUTING.md`.
- [x] 1.9 Add a `CHANGELOG.md` `Unreleased` entry.

## 2. Validation

- [x] 2.1 `bash -n bin/worktree.sh` and a create/remove smoke test pass.
- [x] 2.2 `make lint-openspec` passes.
- [x] 2.3 `git diff --check` is clean.
- [x] 2.4 Open a pull request against `dev` and confirm CI is green.
