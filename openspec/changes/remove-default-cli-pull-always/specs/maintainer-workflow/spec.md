## ADDED Requirements

### Requirement: Fresh Dev Before Issue Worktree
The maintainer workflow SHALL refresh local `dev` from `origin/dev` with fast-forward safety before creating an issue worktree from `dev`.

#### Scenario: Issue worktree starts from dev
- **WHEN** an agent takes a GitHub issue into work and will create a worktree from `dev`
- **THEN** the agent fetches `origin/dev`
- **AND** verifies local `dev` can be fast-forwarded to `origin/dev`
- **AND** updates the local `dev` branch to `origin/dev` without discarding local commits
- **AND** creates the issue worktree only after that refresh

#### Scenario: Dev is already checked out
- **WHEN** local `dev` is checked out in an existing worktree
- **THEN** the agent updates that worktree with a fast-forward-only pull
- **AND** does not force-move the checked-out branch from another worktree
