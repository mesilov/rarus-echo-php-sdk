## Purpose

Verify that users can install and run public standalone release binaries whose identity matches a version tag built from main.

## ADDED Requirements

### Requirement: Published asset identity
Release acceptance SHALL download the selected public release archive and SHA256SUMS for each supported native platform, verify checksum and embedded version/source SHA, and reject tags whose commit is not in main history. It MUST NOT substitute workflow artifacts for published release assets.

#### Scenario: Mismatched published archive
- **WHEN** checksum, version or source SHA differs from the selected tag
- **THEN** acceptance fails before installation

### Requirement: Real installer and executable acceptance
Acceptance SHALL run the public main installer with an explicit release version in isolated user directories on Linux amd64/arm64, macOS arm64 and Windows amd64. It SHALL verify repeat installation and execute the installed CLI smoke suite without PHP or production API credentials. Persistent user PATH and existing user configuration MUST remain unchanged.

#### Scenario: Install a published release candidate
- **WHEN** all four archives for 0.5.0-rc-1 are published
- **THEN** native acceptance installs each archive twice and verifies CLI identity, help, configuration and local HTTP behavior

### Requirement: Explicit prerelease documentation
Release documentation SHALL identify 0.5.0-rc-1 as a prerelease, preserve an empty Unreleased changelog section, and show explicitly pinned installer commands. Publication MUST keep the current stable Latest release unchanged.

#### Scenario: Stable release lacks standalone assets
- **WHEN** stable 0.4.0 remains Latest
- **THEN** documented RC installation selects 0.5.0-rc-1 explicitly instead of latest
