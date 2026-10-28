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
- **WHEN** stable 0.4.0 remains Latest and the selected release candidate is 0.5.0-rc-3
- **THEN** current installation documentation selects 0.5.0-rc-3 explicitly, README retains its permanent Latest links and accurate availability notice, and historical RC-1/RC-2 notes and frozen archive identities remain unchanged

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

### Requirement: Stable publication and Latest acceptance
A stable release SHALL consolidate its candidate changes into a dated changelog section while preserving historical candidate notes and empty Unreleased. It SHALL publish the exact checked main source with four verified native archives and SHA256SUMS as a non-prerelease Latest release. Stable availability notices MUST be updated only after public assets and Latest routes are verified. Public acceptance MUST exercise all four native installers and preserve tag/source identity.

#### Scenario: Publish stable 0.5.0
- **WHEN** the maintainer authorizes final 0.5.0 from checked dev and all tag builds pass
- **THEN** the exact main tag is published as stable Latest, public installation passes on all supported platforms and Composer metadata points to that source

#### Scenario: Verify Latest downloads
- **WHEN** 0.5.0 becomes Latest with four standalone archives and SHA256SUMS
- **THEN** all four permanent README download routes resolve to its verified assets and final documentation reports stable availability

#### Scenario: Preserve candidate history
- **WHEN** stable release notes aggregate RC-1, RC-2 and RC-3
- **THEN** earlier dated notes and immutable RC tags/assets remain unchanged
