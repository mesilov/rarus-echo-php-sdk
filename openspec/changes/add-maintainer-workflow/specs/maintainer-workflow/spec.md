## ADDED Requirements

### Requirement: Maintainer skill availability
The repository SHALL provide a single Russian-language maintainer skill for issue-driven maintenance work that is available to both Claude Code and Codex from repo-local paths.

#### Scenario: Claude Code maintainer skill exists
- **WHEN** a maintainer opens the repository in Claude Code
- **THEN** the maintainer workflow is available from `.claude/skills/rarus-echo-maintainer/SKILL.md`

#### Scenario: Codex maintainer skill exists
- **WHEN** a maintainer opens the repository in Codex
- **THEN** the maintainer workflow is available from `.codex/skills/rarus-echo-maintainer/SKILL.md`

#### Scenario: Maintainer skill has one source
- **WHEN** the Claude Code and Codex maintainer skill entrypoints are resolved
- **THEN** both paths point to the same shared skill content

### Requirement: Issue-first maintainer workflow
The maintainer workflow SHALL start from a GitHub issue and guide work through branch creation, OpenSpec decision-making, local validation, pull request creation, and CI verification.

#### Scenario: Issue link is provided
- **WHEN** a maintainer gives an agent a GitHub issue link
- **THEN** the agent reads the issue before implementation and uses its title, body, labels, milestone, and comments as scope

#### Scenario: Pull request targets dev
- **WHEN** issue work is ready for review
- **THEN** the agent opens the pull request against `dev`

#### Scenario: Completion requires CI status
- **WHEN** the pull request is opened or updated
- **THEN** the agent checks CI status and does not report the issue complete until required checks are green

#### Scenario: Agent review comments are resolved
- **WHEN** an agent reviewer posts pull request comments
- **THEN** the agent evaluates each comment, implements or responds to it, and resolves the thread before reporting the issue complete

### Requirement: OpenSpec policy
The repository SHALL document when OpenSpec is required and how OpenSpec changes are validated and archived.

#### Scenario: Non-trivial change
- **WHEN** an issue changes public SDK API, user-visible behavior, architecture, CI, or maintainer process
- **THEN** the change includes OpenSpec artifacts under `openspec/changes/<change-id>/`

#### Scenario: Trivial change
- **WHEN** an issue is limited to a typo, dependency bump, formatting, or trivial documentation edit
- **THEN** OpenSpec may be skipped if a formal change would not clarify requirements

#### Scenario: OpenSpec validation
- **WHEN** OpenSpec artifacts exist in the repository
- **THEN** `make lint-openspec` is the standard validation command

#### Scenario: OpenSpec CI lint
- **WHEN** a pull request targets `dev`
- **THEN** GitHub Actions runs OpenSpec validation as part of the code quality workflow

#### Scenario: Repository-wide OpenSpec policy
- **WHEN** an agent changes files outside `openspec/`
- **THEN** repository-wide OpenSpec policy is discoverable from the root `AGENTS.md`

#### Scenario: OpenSpec CLI provisioning
- **WHEN** a contributor prepares a clean checkout for OpenSpec workflow
- **THEN** the README documents the OpenSpec CLI package, version, and update command for generated instructions

### Requirement: README workflow documentation
The repository SHALL document the maintainer development workflow in `README.md`.

#### Scenario: Contributor reads README
- **WHEN** a contributor or maintainer reads the development section
- **THEN** they can identify the issue-first workflow, OpenSpec policy, local validation commands, PR target branch, and green CI expectation
