## ADDED Requirements

### Requirement: Release Issue Workflow
The maintainer workflow SHALL document the release-specific steps required before a release pull request is opened.

#### Scenario: Release issue defines a target version
- **WHEN** an agent takes a release issue into work
- **THEN** it identifies the target SemVer version from the issue body or milestone
- **AND** it stops for clarification if the issue and milestone disagree about the target version

#### Scenario: Release changelog is prepared
- **WHEN** a release pull request is prepared
- **THEN** `CHANGELOG.md` contains a dated `## [<version>] - YYYY-MM-DD` section for the release
- **AND** the previous `Unreleased` entries are moved into that release section
- **AND** a new empty `## [Unreleased]` section remains above the release section

#### Scenario: README installation example is aligned
- **WHEN** a release pull request is prepared
- **THEN** `README.md` shows a Composer installation example that matches the current release line
