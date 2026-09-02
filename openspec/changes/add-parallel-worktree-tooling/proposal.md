## Why

Maintainers run several issues in parallel, but preparing an isolated git worktree by hand is repetitive and error-prone. Dependencies and secrets must be placed manually, stale worktrees accumulate, and the safe way to provision `vendor/` is non-obvious: a host symlink to the primary `vendor/` breaks inside the `.:/var/www/html` Docker mount because its absolute target is invisible to the container. The repository needs first-class tooling for the create-and-remove worktree lifecycle so both humans and agents follow one repeatable path.

## What Changes

- Add `.agents/skills/rarus-echo-maintainer/scripts/worktree.sh` with `new`, `remove`, and `list` subcommands (maintainer-only tooling, kept with the maintainer skill rather than in the published `bin/`).
- Add `make worktree-new`, `make worktree-remove`, and `make worktree-list` targets and list them in `make help`.
- Store every per-issue worktree under a git-ignored `.worktree/<issue>-<slug>` directory inside the repository, named with the issue-number prefix.
- Base a new worktree branch on `origin/<base>` (default `dev`) so the local `dev` branch is never moved.
- Provision a new worktree with a symlinked `.env.local` (single shared source of secrets) and an independent clone-copy of `vendor/` (APFS clone, then reflink, then plain copy — never a hard link), falling back to `make composer-install` when the primary has no `vendor/`.
- Remove a worktree and prune metadata while keeping the branch.
- Add `.gitattributes` `export-ignore` rules so the maintainer tooling and other development-only paths are excluded from the distributed Composer package, leaving consumers only the runtime SDK.
- Update the maintainer skill to create and clean up worktrees with the tooling, and document the workflow in `CONTRIBUTING.md`.

## Capabilities

### New Capabilities

- `worktree-tooling`: Repeatable create-and-remove lifecycle for per-issue git worktrees, pre-provisioned with shared secrets and dependencies so `make` targets work immediately.

## Impact

- Affected tooling: `.agents/skills/rarus-echo-maintainer/scripts/worktree.sh`, `Makefile`, `.gitignore`, `.gitattributes`.
- Affected distribution: the Composer dist archive no longer ships development-only paths (tests, tooling, docker, OpenSpec, agent skills/plugins, CI config).
- Affected process docs: `.agents/skills/rarus-echo-maintainer/SKILL.md` (shared by Claude Code and Codex), `CONTRIBUTING.md`, `CHANGELOG.md`.
- This change does not affect runtime PHP code or the public SDK API.
