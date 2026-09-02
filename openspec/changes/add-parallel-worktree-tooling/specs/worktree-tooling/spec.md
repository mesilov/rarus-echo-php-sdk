## ADDED Requirements

### Requirement: Per-issue worktree location
The worktree tooling SHALL place every per-issue worktree under a git-ignored `.worktree/<issue>-<slug>` directory inside the repository, named with the issue-number prefix.

#### Scenario: Worktree created under .worktree
- **WHEN** a maintainer creates a worktree for issue `29` with slug `parallel-worktree-tooling`
- **THEN** the worktree is created at `.worktree/29-parallel-worktree-tooling`

#### Scenario: Worktree directory is ignored
- **WHEN** the repository status is checked from the primary checkout
- **THEN** the `.worktree/` directory is ignored by git

### Requirement: Worktree creation command
The tooling SHALL provide a `make worktree-new` target that branches a new worktree off `origin/<base>` and does not move the local `dev` branch.

#### Scenario: Branch is based on origin base
- **WHEN** a maintainer runs `make worktree-new ISSUE=29 SLUG=parallel-worktree-tooling`
- **THEN** the tooling fetches `origin/dev` and creates the worktree branch from `origin/dev`

#### Scenario: Local dev is not moved
- **WHEN** a worktree is created while the primary checkout has `dev` checked out
- **THEN** the local `dev` branch reference is left unchanged

#### Scenario: Branch naming follows issue type
- **WHEN** a maintainer passes `TYPE` as `feature`, `bugfix`, or `docs` (default `feature`) and `BASE` (default `dev`)
- **THEN** the created branch is named `<type>/<issue>-<slug>`

### Requirement: Worktree provisioning
A newly created worktree SHALL be provisioned so `make` targets work immediately, using a symlinked `.env.local` for secrets and a clone-copied `vendor/` for dependencies rather than a host symlink to `vendor/`.

#### Scenario: Secrets are symlinked
- **WHEN** the primary checkout has a `.env.local` file
- **THEN** the new worktree receives a `.env.local` symlink pointing at the primary `.env.local`

#### Scenario: Dependencies are clone-copied
- **WHEN** the primary checkout has a `vendor/` directory
- **THEN** the new worktree receives a clone-copy of `vendor/` (not a host symlink) so it resolves inside the Docker mount

#### Scenario: Dependency fallback
- **WHEN** the primary checkout has no `vendor/` directory
- **THEN** the tooling runs `make composer-install` in the new worktree

### Requirement: Worktree removal command
The tooling SHALL provide a `make worktree-remove` target that removes a per-issue worktree and prunes its metadata while keeping the branch.

#### Scenario: Remove by issue number
- **WHEN** a maintainer runs `make worktree-remove ISSUE=29` with a single matching worktree
- **THEN** the `.worktree/29-*` worktree is removed and worktree metadata is pruned

#### Scenario: Remove by explicit name
- **WHEN** a maintainer runs `make worktree-remove NAME=29-parallel-worktree-tooling`
- **THEN** the named worktree is removed

#### Scenario: Branch is kept after removal
- **WHEN** a worktree is removed
- **THEN** the worktree branch continues to exist for the open pull request

### Requirement: Worktree listing command
The tooling SHALL provide a `make worktree-list` target that lists the active per-issue worktrees under `.worktree/`.

#### Scenario: List active worktrees
- **WHEN** a maintainer runs `make worktree-list`
- **THEN** the active worktrees under `.worktree/` are listed

### Requirement: Maintainer workflow uses the worktree tooling
The maintainer process documentation SHALL instruct maintainers to create and clean up per-issue worktrees with the tooling.

#### Scenario: Skill creates and removes worktrees
- **WHEN** the maintainer skill is followed for an issue
- **THEN** it uses `make worktree-new` to start work and `make worktree-remove` to clean up after the pull request is merged

#### Scenario: Contributor documentation describes the workflow
- **WHEN** a contributor reads `CONTRIBUTING.md`
- **THEN** the parallel worktree create, list, and remove workflow is documented

### Requirement: Maintainer tooling excluded from the published package
The repository SHALL exclude the maintainer tooling and other development-only paths from the distributed Composer package via `.gitattributes` `export-ignore`, so consumers receive only the runtime SDK.

#### Scenario: Worktree tooling is not distributed
- **WHEN** the Composer dist archive is produced with `git archive`
- **THEN** the maintainer worktree tooling under `.agents/` and other development paths (such as `tests/`, `docker/`, `openspec/`, and `Makefile`) are excluded

#### Scenario: Runtime files remain distributed
- **WHEN** the Composer dist archive is produced with `git archive`
- **THEN** the runtime SDK files (`src/`, `composer.json`, `LICENSE`, `README.md`, and the published `bin/` CLI) are included
