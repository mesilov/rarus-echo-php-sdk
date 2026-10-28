# published-release-acceptance Specification

## Purpose
Verify that users can install and run public standalone release binaries whose identity matches a version tag built from main.

## Requirements

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
Release documentation SHALL identify the selected release candidate as a prerelease, preserve an empty Unreleased changelog section, and show installer commands pinned to that candidate's exact version. Historical prerelease notes SHALL retain their original version and scope. Publication MUST keep the current stable Latest release unchanged.

#### Scenario: Stable release lacks standalone assets
- **WHEN** stable 0.4.0 remains Latest and the selected release candidate is 0.5.0-rc-2
- **THEN** documented RC installation selects 0.5.0-rc-2 explicitly instead of latest, while historical RC-1 notes and its frozen archive identity remain unchanged

### Requirement: Distribution archive composition
New standalone archives SHALL omit BITRIX24-CLI-LICENSE.txt and YIIPRESS-LICENSE.txt while retaining application notices, runtime/media licenses and corresponding media source. Acceptance SHALL support historical license files only for exact published RC-1 and RC-2 tag/source identities; RC-1 retains its explicit no-media exception.

#### Scenario: New platform archive
- **WHEN** each of the four platform archives is packaged from new source
- **THEN** its validated contents omit the two build-helper license files and retain all required runtime and media artifacts

#### Scenario: Published historical archive
- **WHEN** an archive has the exact published RC-1 or RC-2 identity and original composition
- **THEN** acceptance validates that historical composition without changing published assets or accepting newly introduced compression in frozen RC-2

#### Scenario: Unexpected build-helper license
- **WHEN** an archive from any other source contains either historical build-helper license file
- **THEN** release archive validation rejects the unexpected member
