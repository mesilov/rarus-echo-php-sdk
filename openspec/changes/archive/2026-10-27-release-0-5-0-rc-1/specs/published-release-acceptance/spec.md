## Purpose

Verify that users can install and run public standalone release binaries whose identity matches a version tag built from main.

## ADDED Requirements

### Requirement: Published asset identity
Release acceptance SHALL download the selected public release archive and SHA256SUMS for each supported native platform, verify checksum and embedded version/source SHA, and reject tags whose commit is not in main history. It MUST NOT substitute workflow artifacts for published release assets.

#### Scenario: Mismatched published archive
- **WHEN** checksum, version or source SHA differs from the selected tag
- **THEN** acceptance fails before installation

### Requirement: Real installer and executable acceptance
Acceptance SHALL run public main installers with explicit versions on Linux amd64/arm64, macOS arm64 and Windows amd64 in isolated user directories. It SHALL verify repeat installation and installed CLI smoke tests without PHP or production credentials, preserving configuration and persistent PATH. Smoke commands SHALL follow verified tagged source; doctor remains mandatory for new builds and releases containing DoctorCommand.php.

#### Scenario: Install a published release candidate
- **WHEN** all four archives for 0.5.0-rc-1 are published
- **THEN** native acceptance installs each archive twice and verifies CLI identity, help, configuration and local HTTP behavior

#### Scenario: Historical release predates doctor
- **WHEN** the current checkout includes doctor but the verified release source does not
- **THEN** published acceptance runs the release's earlier command set, while new build smoke tests still require doctor

### Requirement: Explicit prerelease documentation
Release documentation SHALL identify 0.5.0-rc-1 as a prerelease, preserve an empty Unreleased changelog section, and show explicitly pinned installer commands. Publication MUST keep the current stable Latest release unchanged.

#### Scenario: Stable release lacks standalone assets
- **WHEN** stable 0.4.0 remains Latest
- **THEN** documented RC installation selects 0.5.0-rc-1 explicitly instead of latest
