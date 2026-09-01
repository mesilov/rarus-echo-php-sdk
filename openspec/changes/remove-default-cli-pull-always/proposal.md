## Why

Issue #27 asks to remove `--pull=always` from default README Docker examples so quick-start commands do not unexpectedly refresh mutable image tags on every run. The issue work also updates the repository maintainer skill so future issue work refreshes `dev` before creating a worktree from it.

## What Changes

- Remove `--pull=always` from the default Docker CLI commands in `README.md`.
- Keep `--pull=always` documented as an explicit opt-in for users who need to refresh the published CLI image.
- Require the maintainer workflow to fetch `origin/dev` and update local `dev` with fast-forward safety before creating an issue worktree from `dev`.
- Add an `Unreleased` changelog entry for both documentation and workflow changes.

## Capabilities

### New Capabilities

- None.

### Modified Capabilities

- `cli-docker-image`: README Docker CLI examples default to ordinary `docker run` usage and make forced pulls opt-in.
- `maintainer-workflow`: Issue worktree creation starts from a freshly updated local `dev` branch without discarding local commits.

## Impact

- Affected docs: `README.md`, `CHANGELOG.md`.
- Affected maintainer process: `.agents/skills/rarus-echo-maintainer/SKILL.md`, consumed through existing Claude Code and Codex symlinks.
- Affected runtime code: none.
- Affected tests: none; verification uses OpenSpec, diff, grep, unit, and lint gates.
