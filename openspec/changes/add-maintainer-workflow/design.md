## Context

The repository already has a `dev` branch, GitHub Actions for linting and unit tests, and contributor documentation. It does not yet have a repo-local maintainer workflow for issue-driven agent work or an OpenSpec policy for deciding when a change must be specified before implementation.

The new workflow should be useful to both humans and agents. It should not introduce runtime dependencies or change SDK behavior.

## Goals / Non-Goals

**Goals:**

- Provide a maintainer skill that agents can invoke when working from GitHub issues.
- Make the skill available in both Claude Code and Codex repo-local skill locations.
- Initialize OpenSpec and document how it is used in this repository.
- Document the issue-to-PR process in `README.md`.
- Keep the workflow lightweight enough for a small open-source PHP SDK.

**Non-Goals:**

- Do not change the public PHP API or intended SDK behavior.
- Do not refactor runtime PHP code except for a minimal validation cleanup required to keep existing checks green.
- Do not add Bitrix24-specific generator, OpenAPI refresh, or live API discovery rules.
- Do not require OpenSpec for trivial edits where it does not clarify requirements.
- Do not automate merging.

## Decisions

- Store matching maintainer skill entrypoints under `.claude/skills/rarus-echo-maintainer/` and `.codex/skills/rarus-echo-maintainer/`.
  - Rationale: Claude Code and Codex discover skills from different local conventions. Keeping both entrypoints in the repository makes the workflow portable.
  - Alternative considered: store only one canonical skill and link to it. This reduces duplication but makes one tool depend on reading a non-native path before the skill can operate.

- Use OpenSpec as a change contract, not as the whole delivery workflow.
  - Rationale: OpenSpec is strong at recording why/what/tasks/spec deltas, but it does not replace repository-specific rules for branch naming, Makefile gates, PR target branch, and CI checks.
  - Alternative considered: make OpenSpec mandatory for every issue. This would slow down typo fixes and dependency bumps.

- Keep `dev` as the PR target branch for issue work.
  - Rationale: the repository already has `dev` and CI runs on pull requests targeting `dev`.

- Document the human-facing workflow in `README.md`.
  - Rationale: maintainer rules should not be hidden only in agent skills.

## Risks / Trade-offs

- Duplicate skill files can drift over time.
  - Mitigation: keep both files intentionally short and identical, and update them together.
- OpenSpec initialization can create tool-specific files.
  - Mitigation: commit only repo-local artifacts and avoid relying on global user directories.
- Requiring OpenSpec too often can slow maintenance.
  - Mitigation: explicitly document where OpenSpec is optional.
- A validation-only runtime cleanup could look unrelated to maintainer workflow.
  - Mitigation: keep the cleanup minimal, cover it with existing failing unit tests, and mention it in the PR validation notes.

## Migration Plan

1. Add OpenSpec project files and the `add-maintainer-workflow` change.
2. Add Claude Code and Codex maintainer skill entrypoints.
3. Update `README.md` with the maintainer development workflow.
4. Fix any pre-existing local gate blocker only when it prevents proving the workflow change.
5. Validate OpenSpec and repository checks locally.
6. Open a pull request against `dev`.
7. Archive the OpenSpec change after the PR is merged.

## Open Questions

- None for this change.
