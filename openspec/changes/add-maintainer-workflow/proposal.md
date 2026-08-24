## Why

Maintainers need a repeatable issue-to-PR workflow that both humans and AI agents can follow without relying on ad hoc chat context. The repository already has CI and contribution basics, but it lacks a repo-specific maintainer playbook and an OpenSpec policy for non-trivial changes.

## What Changes

- Add a repository-local maintainer skill for issue-driven SDK maintenance.
- Make the maintainer skill available to both Claude Code and Codex by storing matching skill entrypoints under `.claude/skills/` and `.codex/skills/`.
- Initialize OpenSpec and document how this repository uses OpenSpec changes, validation, and archive timing.
- Document the maintainer development workflow in `README.md`.
- Define a lightweight policy where OpenSpec is required for non-trivial public API, behavior, architecture, and support-process changes, but optional for trivial edits.

## Capabilities

### New Capabilities

- `maintainer-workflow`: Issue-first maintainer process from GitHub issue link to pull request against `dev` with local validation and CI status checks.

### Modified Capabilities

- None.

## Impact

- Affected docs: `README.md`, OpenSpec project files.
- Affected agent instructions: `.claude/skills/rarus-echo-maintainer/SKILL.md`, `.codex/skills/rarus-echo-maintainer/SKILL.md`.
- Affected workflow: maintainers and agents gain a documented path for issue intake, OpenSpec decision-making, local validation, PR creation, and CI verification.
- A minimal SDK runtime validation cleanup may be included only when needed to keep the existing local/CI gate green without changing the public PHP API.
