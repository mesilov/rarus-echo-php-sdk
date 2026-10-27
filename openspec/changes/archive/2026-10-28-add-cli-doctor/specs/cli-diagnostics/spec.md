# CLI Diagnostics

## Purpose

Allow CLI users to identify configuration, Echo access and ffmpeg problems with one read-only readiness report.

## ADDED Requirements

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
Doctor SHALL locate ffmpeg in the CLI runtime PATH, probe its version within five seconds and report its path and version on success. Missing, unlaunchable, unsuccessful or invalid-version executables SHALL produce an error with guidance about audio extraction from video.

#### Scenario: Available ffmpeg
- **WHEN** ffmpeg runs and returns a recognizable version
- **THEN** doctor reports a successful check, executable path and version

#### Scenario: Unavailable ffmpeg
- **WHEN** ffmpeg is missing, fails or times out
- **THEN** doctor reports a failure and recommends installing or fixing ffmpeg in the CLI environment

### Requirement: Stable text and JSON reports
Doctor SHALL show each check and an overall result, support --json with schema_version 1, status and config/connection/ffmpeg checks containing status, code, message and details. Statuses SHALL be ok, warning, error or skipped. Exit code SHALL be 1 for any error/skipped and 0 otherwise; warning SHALL be the overall status when only warnings remain.

#### Scenario: Complete readiness
- **WHEN** all checks succeed
- **THEN** overall status is ok and exit code is 0

#### Scenario: Mixed results
- **WHEN** any check fails or is skipped
- **THEN** JSON remains parseable, contains all checks and overall error, and exit code is 1
