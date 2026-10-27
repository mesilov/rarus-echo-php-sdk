# Spec Delta

## Purpose

Distribute verifiable RARUS Echo CLI executables for workstations that do not have PHP, Composer, or Docker installed.

## ADDED Requirements

### Requirement: Supported standalone executables

The distribution SHALL provide Linux amd64/arm64, macOS arm64, and Windows amd64 executables that run without PHP, Composer, Docker, or a repository checkout.

#### Scenario: Start an extracted executable

- **WHEN** a user extracts the archive for their supported platform and runs help or version with a clean PATH
- **THEN** the CLI exits successfully without external runtime dependencies or credentials

### Requirement: Embedded build identity

The executable SHALL report its version and full source commit SHA through --version and ship matching build-info.json with runtime pins and the production dependency lock checksum.

#### Scenario: Release identity

- **WHEN** a version tag is packaged from its committed source
- **THEN** --version contains that version and source SHA and agrees with the archive metadata

#### Scenario: Source mismatch

- **WHEN** the checkout is dirty or the supplied source SHA differs from HEAD
- **THEN** packaging fails before distributing an executable

### Requirement: Production CLI behavior

The executable SHALL retain queue, submit, status, transcript, current-directory credentials loading, HTTP factory discovery, uploads from Unicode paths, and compiled required extensions.

#### Scenario: Local upload acceptance

- **WHEN** the extracted CLI submits a file with a Cyrillic and spaced pathname to a controlled local API
- **THEN** the API receives the upload and the CLI returns the expected file identifier

### Requirement: Verifiable release archives

Each platform archive SHALL contain its executable, build metadata, runtime source metadata and applicable licenses. SHA256SUMS SHALL cover exactly the four final platform archives, using ZIP for Windows and tar.gz for Unix.

#### Scenario: Verify downloaded archives

- **WHEN** all platform outputs have passed acceptance
- **THEN** their final archives have matching SHA256 checksums and consistent source/version identities

### Requirement: Publication gate

Release publication SHALL require successful builds and isolated acceptance for all four platforms. Pull requests and manually dispatched development builds SHALL provide artifacts without publishing a release.

#### Scenario: Platform failure

- **WHEN** any platform build, acceptance, archive or checksum verification fails
- **THEN** no release assets are published by that run

### Requirement: Existing installation paths

Documentation SHALL retain Docker as the primary quick start and explain standalone download, checksum verification, execution, and unsigned platform limitations.

#### Scenario: Choose a standalone download

- **WHEN** a user follows the standalone installation instructions
- **THEN** they can select their platform archive, verify it, and run the CLI using the existing credentials
