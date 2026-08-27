## ADDED Requirements

### Requirement: README Contains Inline Release Examples
The README SHALL contain release-ready examples directly in the document instead of requiring a separate examples directory.

#### Scenario: User follows quick start
- **WHEN** a user reads the quick-start section
- **THEN** Docker CLI usage SHALL be shown before PHP SDK usage
- **AND** PHP SDK examples SHALL use current namespaces and method names

### Requirement: Contributor Documentation Matches Current Release
Contributor documentation SHALL match the current PHP version requirements, branch workflow, and example-documentation policy.

#### Scenario: Contributor checks requirements
- **WHEN** a contributor reads `CONTRIBUTING.md`
- **THEN** it SHALL state PHP 8.4 or 8.5 compatibility and avoid references to non-existent example directories

### Requirement: Changelog Marks 0.3.0 Release
The changelog SHALL mark version 0.3.0 as released with the release date and summarize CLI, Docker image, OpenSpec workflow, and integration-test documentation changes.

#### Scenario: Maintainer prepares release
- **WHEN** the 0.3.0 release PR is reviewed
- **THEN** `CHANGELOG.md` SHALL no longer label 0.3.0 as unreleased
