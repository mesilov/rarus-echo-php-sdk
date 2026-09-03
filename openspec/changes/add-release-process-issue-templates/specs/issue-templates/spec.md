## ADDED Requirements

### Requirement: Bug Report Issue Template
The repository SHALL provide a GitHub issue template for reproducible bug reports.

#### Scenario: User reports a bug
- **WHEN** a user opens a bug report issue
- **THEN** the template prompts for summary, affected area, reproduction steps, expected behavior, actual behavior, version, environment, and relevant logs
- **AND** it reminds the user not to include credentials or other secrets

### Requirement: Release Rollout Issue Template
The repository SHALL provide a GitHub issue template for release rollout requests.

#### Scenario: Maintainer opens a release issue
- **WHEN** a maintainer starts a release issue
- **THEN** the template prompts only for the SemVer release version and a brief description
