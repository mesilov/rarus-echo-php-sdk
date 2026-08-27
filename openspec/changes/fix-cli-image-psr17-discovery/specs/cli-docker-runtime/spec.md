## ADDED Requirements

### Requirement: CLI Docker Image API Commands Discover HTTP Runtime Dependencies
The CLI Docker image SHALL include production dependencies sufficient for the SDK's default PSR-18 and PSR-17 auto-discovery path.

#### Scenario: Build validates HTTP discovery
- **WHEN** the CLI Docker image is built
- **THEN** the build SHALL verify request factory, response factory, stream factory, and PSR-18 client discovery using the production autoloader

#### Scenario: Submit command reaches Echo API
- **GIVEN** valid `RARUS_ECHO_API_KEY`, `RARUS_ECHO_USER_ID`, and optional `RARUS_ECHO_BASE_URL`
- **AND** an audio file within the documented local development upload limits is mounted into the CLI Docker image
- **WHEN** the user runs `rarus-echo submit <file> --json`
- **THEN** the command SHALL submit the file and return JSON containing submitted file identifiers

#### Scenario: Runtime supports large local audio smoke files
- **WHEN** the CLI Docker image runs
- **THEN** its PHP configuration SHALL set `memory_limit` to 4 GB and upload limits for local happy-path audio files up to 500 MB
