# cli-diagnostics Specification

## Purpose
Allow CLI users to identify configuration, Echo access and ffmpeg problems with one read-only readiness report.

## Requirements

### Requirement: Argument-free independent diagnostics
The CLI SHALL provide doctor without required arguments or interactive input, run independent checks despite failures and avoid configuration writes, dependency installation and transcription submission.

#### Scenario: Missing configuration
- **WHEN** doctor runs without usable settings
- **THEN** it reports the config error, skips connection with a reason and still checks ffmpeg

### Requirement: Configuration readiness
Doctor SHALL report the chosen config path, file validity and effective source using existing schema, protection and precedence rules. Credentials and file contents MUST NOT appear in output.

#### Scenario: Explicit configuration
- **WHEN** doctor receives --config PATH
- **THEN** it validates only that file for connection selection and ignores environment credentials

#### Scenario: Environment configuration
- **WHEN** valid env or .env credentials override an absent or invalid default file
- **THEN** doctor reports environment as source, warns about the file and checks the server

#### Scenario: Invalid active configuration
- **WHEN** settings are missing, unreadable, malformed, unsafe or have invalid version, fields or values
- **THEN** doctor reports an error without credentials and skips the connection probe

### Requirement: Authenticated bounded connectivity check
Doctor SHALL perform a read-only authenticated Echo request, limit the full request to ten seconds, follow no redirects and distinguish authentication, network/timeout, server and unexpected response failures without raw remote content.

#### Scenario: Reachable server
- **WHEN** the configured Echo server returns a valid authenticated queue response
- **THEN** the connection check succeeds without creating a transcription job

#### Scenario: Failed server request
- **WHEN** the request fails or times out
- **THEN** doctor reports a categorized error with a recommendation and continues independent checks

### Requirement: Executable ffmpeg check
Doctor SHALL resolve and verify the same FFmpeg/ffprobe toolkit as submit within five seconds, using the complete package or explicit absolute override pair. It SHALL report safe errors and retain the JSON code family and path/version details.

#### Scenario: Available ffmpeg
- **WHEN** the matching complete toolkit is present and PATH is empty
- **THEN** doctor reports successful media readiness and the resolved ffmpeg path/version

#### Scenario: Unavailable ffmpeg
- **WHEN** one override path is absent or a package identity/hash/capability check fails
- **THEN** doctor reports an actionable media error independently of configuration and connection checks

#### Scenario: Bounded unavailable toolkit
- **WHEN** toolkit resolution or probing is missing, unsuccessful or exceeds the combined five-second deadline
- **THEN** doctor returns a categorized safe error without installing dependencies or falling back to an unrelated PATH executable

### Requirement: Stable text and JSON reports
Doctor SHALL show each check and an overall result, support --json with schema_version 1, status and config/connection/ffmpeg checks containing status, code, message and details. Statuses SHALL be ok, warning, error or skipped. Exit code SHALL be 1 for any error/skipped and 0 otherwise; warning SHALL be the overall status when only warnings remain.

#### Scenario: Complete readiness
- **WHEN** all checks succeed
- **THEN** overall status is ok and exit code is 0

#### Scenario: Mixed results
- **WHEN** any check fails or is skipped
- **THEN** JSON remains parseable, contains all checks and overall error, and exit code is 1
