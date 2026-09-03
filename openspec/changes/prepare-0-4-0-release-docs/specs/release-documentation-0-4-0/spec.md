## ADDED Requirements

### Requirement: Changelog Marks 0.4.0 Release
The changelog SHALL mark version 0.4.0 as released with its release date and SHALL keep an empty `[Unreleased]` section above the released version.

#### Scenario: Maintainer prepares the 0.4.0 release
- **WHEN** the 0.4.0 release PR is reviewed
- **THEN** `CHANGELOG.md` SHALL contain a `## [0.4.0] - 2026-09-03` section holding the entries that were under `[Unreleased]`
- **AND** `CHANGELOG.md` SHALL retain an empty `## [Unreleased]` heading above the 0.4.0 section

### Requirement: README Installation Example Targets the 0.4 Release Line
The README installation example SHALL pin the current release line so new consumers install a 0.4-compatible version.

#### Scenario: User installs the SDK
- **WHEN** a user follows the README installation section
- **THEN** the install command SHALL be `composer require mesilov/rarus-echo-php-sdk:^0.4`
