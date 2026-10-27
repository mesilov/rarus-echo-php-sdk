# cli-installation Specification

## Purpose
Let users install and update the standalone RARUS Echo CLI using a verified public release for their platform without a PHP installation.

## Requirements

### Requirement: Native one-line installation
The installers SHALL support Linux amd64/arm64 and macOS arm64 through POSIX sh, and Windows amd64 through PowerShell 5.1+, without PHP, Composer, Docker or elevated privileges. Unsupported platforms MUST fail before download.

#### Scenario: Select supported archive
- **WHEN** the user runs the installer on a supported platform
- **THEN** it selects exactly the matching standalone release archive

#### Scenario: Unsupported architecture
- **WHEN** the platform has no standalone archive
- **THEN** the installer reports supported platforms and performs no download

### Requirement: Consistent public release selection
The installers SHALL resolve latest stable once or accept an explicit safe release tag, including prereleases. All downloads MUST use HTTPS and the selected public release. Missing releases or assets MUST fail clearly without CI artifact or source-build fallback.

#### Scenario: Latest stable changes during download
- **WHEN** latest resolves to a tag and a newer release is published during installation
- **THEN** the archive and checksums still come from the initially selected tag

#### Scenario: Stable release has no archive
- **WHEN** the selected release does not contain the platform archive
- **THEN** installation fails and preserves an existing installation

### Requirement: Verify before installing
The installers MUST verify a single well-formed SHA-256 record for the archive before unpacking or executing it. They SHALL reject unsafe, duplicate, linked or unexpected archive members and require distribution notices and runtime licenses. Candidate version output MUST match the selected tag and contain a full source SHA.

#### Scenario: Invalid release payload
- **WHEN** a download has a checksum mismatch, unsafe archive, missing license or wrong candidate identity
- **THEN** installation fails before replacing the existing executable or notices

### Requirement: User-scoped updates
The installers SHALL support an absolute custom installation directory and paths containing spaces and Unicode. Repeated installs SHALL update only the installer-owned executable, complete notices package directory and activation files. Connection configuration and user files outside those destinations MUST remain unchanged. Staging failures MUST preserve the existing executable and notices.

#### Scenario: Repeat installation
- **WHEN** the user installs twice or selects a newer version
- **THEN** the executable and notices are updated and connection configuration is byte-for-byte unchanged

### Requirement: Idempotent PATH activation
The installers SHALL configure user PATH idempotently, support opting out, and print activation instructions for the current shell. Unix startup changes SHALL respect ZDOTDIR and bash's existing login profile. Windows SHALL update only user PATH and preserve raw variable references, value type and original existence during update or rollback.

#### Scenario: PATH modification disabled
- **WHEN** --no-modify-path or -NoModifyPath is passed
- **THEN** shell profiles and persistent PATH remain unchanged and manual activation instructions are printed

#### Scenario: Expandable Windows PATH
- **WHEN** existing user PATH contains expandable variable references
- **THEN** installation retains those references and their registry value type, and rollback restores the original raw value, type and existence

### Requirement: Unicode Windows bootstrap
The Windows installer SHALL initialize native architecture detection and environment notification in PowerShell 5.1 or newer when temporary and working directories contain Unicode characters, without changing temporary-directory environment variables or weakening directory permissions.

#### Scenario: Unicode temporary directory
- **WHEN** a fresh PowerShell process starts the installer with Unicode TEMP and working directories
- **THEN** native architecture detection and bounded environment notification succeed, repeated initialization succeeds, and no compiler files remain
