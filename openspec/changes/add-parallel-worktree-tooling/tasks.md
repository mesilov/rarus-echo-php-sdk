## 1. Implementation

- [x] 1.1 Add `.agents/skills/rarus-echo-maintainer/scripts/worktree.sh` with `new`, `remove`, and `list` subcommands (maintainer-only tooling, not in the published `bin/`).
- [x] 1.2 Add `make worktree-new`, `worktree-remove`, and `worktree-list` targets and document them in `make help`.
- [x] 1.3 Ignore the `.worktree/` directory in `.gitignore`.
- [x] 1.4 Provision new worktrees with a symlinked `.env.local` and a clone-copied `vendor/` (composer install fallback).
- [x] 1.5 Base new worktree branches on `origin/<base>` without moving local `dev`.
- [x] 1.6 Keep the branch on worktree removal.
- [x] 1.7 Update the maintainer skill to create and clean up worktrees with the tooling.
- [x] 1.8 Document the parallel worktree workflow in `CONTRIBUTING.md`.
- [x] 1.9 Add a `CHANGELOG.md` `Unreleased` entry.
- [x] 1.10 Add `.gitattributes` `export-ignore` rules excluding development and tooling paths from the distributed package.

## 2. Validation

- [x] 2.1 `bash -n .agents/skills/rarus-echo-maintainer/scripts/worktree.sh` and a create/remove smoke test pass.
- [x] 2.2 `make lint-openspec` passes.
- [x] 2.3 `git diff --check` is clean.
- [x] 2.4 Open a pull request against `dev` and confirm CI is green.
- [x] 2.5 `git archive --worktree-attributes HEAD | tar t` confirms runtime paths are kept and development paths are excluded.
