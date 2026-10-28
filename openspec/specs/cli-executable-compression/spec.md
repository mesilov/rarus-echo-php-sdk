# cli-executable-compression Specification

## Purpose
Reduce standalone executable storage using verified packers while preserving the CLI and bundled media behavior on every supported native platform.

## Requirements

### Requirement: Verified executable compression
Normal standalone builds SHALL compress the CLI on all four supported native targets using pinned, verified tools. Failure, unexpected inputs or lack of executable size reduction MUST block distribution. Cached original runtimes MUST remain unchanged.

#### Scenario: Packer fails or produces larger output
- **WHEN** a packer fails, has an unexpected digest/version or does not reduce size
- **THEN** archive publication fails and the cached original runtime remains usable

### Requirement: Compression provenance and notices
Compressed distributions SHALL include tool provenance, original/final executable sizes and SHA256 values and required packer notices. Validators MUST reject incomplete provenance, altered executable bytes or missing notices. Historical uncompressed releases SHALL remain installable.

#### Scenario: Inspect or install compressed archive
- **WHEN** an archive declares executable compression
- **THEN** its compression report, executable hash and notices are validated and retained with the installed package

#### Scenario: Report is missing or modified
- **WHEN** compression artifacts disagree with the declared build identity or executable
- **THEN** archive acceptance fails before activation

### Requirement: Native behavioral equivalence
Compressed CLI builds SHALL preserve command output, exit codes, signal handling, Unicode paths, identity and offline media discovery. Native acceptance MUST compare original and compressed binaries on all supported targets before upload.

#### Scenario: macOS extraction and cached launch
- **WHEN** the macOS executable launches for the first time and again from its extraction cache, outside its package cwd
- **THEN** commands retain clean JSON/stdout and resolve their matching portable or installed media toolkit beside the original launcher
