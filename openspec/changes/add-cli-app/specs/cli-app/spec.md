## ADDED Requirements

### Requirement: CLI entrypoint
The package SHALL provide a Composer-installed `rarus-echo` command line executable.

#### Scenario: Installed executable starts
- **WHEN** a user runs `vendor/bin/rarus-echo --help`
- **THEN** the command displays top-level help without requiring API credentials

#### Scenario: Composer bin is declared
- **WHEN** the package is installed through Composer
- **THEN** Composer exposes the CLI executable from the package `bin` configuration

### Requirement: CLI runtime dependency
The package SHALL depend on Symfony Console versions that support the CLI entrypoint and remain compatible with the repository lint tooling.

#### Scenario: Composer installs without a lock file
- **WHEN** Composer resolves package dependencies without a tracked `composer.lock`
- **THEN** the resolved Symfony Console version can be analyzed by the repository PHPStan lint configuration

### Requirement: Credential loading
Service commands SHALL load RARUS Echo credentials from `RARUS_ECHO_API_KEY`, `RARUS_ECHO_USER_ID`, and optional `RARUS_ECHO_BASE_URL` environment variables, including values loaded from local `.env` files when present.

#### Scenario: Credentials are present
- **WHEN** a service command runs with valid credential environment variables
- **THEN** the command creates an SDK client using those credentials

#### Scenario: Credentials are missing
- **WHEN** a service command runs without required credential environment variables
- **THEN** the command writes a concise credential error to stderr and exits with a non-zero status

#### Scenario: Help does not require credentials
- **WHEN** a user runs top-level help or command help
- **THEN** the command displays help even if credential environment variables are missing

### Requirement: Queue command
The CLI SHALL provide a `queue` command that returns aggregated transcription queue information.

#### Scenario: Human-readable queue output
- **WHEN** a user runs `rarus-echo queue`
- **THEN** stdout includes file count, total file size, and total duration in a human-readable format

#### Scenario: JSON queue output
- **WHEN** a user runs `rarus-echo queue --json`
- **THEN** stdout contains a JSON object with `files_count`, `files_size`, and `files_duration`

### Requirement: Status command
The CLI SHALL provide a `status <file-id>` command that returns the transcription status for one file.

#### Scenario: Human-readable status output
- **WHEN** a user runs `rarus-echo status <file-id>` with a valid UUID
- **THEN** stdout includes the file id, status, file size, file duration, and arrival timestamp

#### Scenario: JSON status output
- **WHEN** a user runs `rarus-echo status <file-id> --json`
- **THEN** stdout contains a JSON object with `file_id`, `status`, `file_size`, `file_duration`, and `timestamp_arrival`

#### Scenario: Invalid status file id
- **WHEN** a user runs `rarus-echo status not-a-uuid`
- **THEN** stderr explains that the file id must be a UUID and the command exits with a non-zero status

### Requirement: Transcript command
The CLI SHALL provide a `transcript <file-id>` command that returns the transcription result for one file.

#### Scenario: Human-readable transcript output
- **WHEN** a user runs `rarus-echo transcript <file-id>` with a valid UUID
- **THEN** stdout includes the file id, status, task type when available, and transcript text when available

#### Scenario: JSON transcript output
- **WHEN** a user runs `rarus-echo transcript <file-id> --json`
- **THEN** stdout contains a JSON object with `file_id`, `status`, `task_type`, and `result`

#### Scenario: Invalid transcript file id
- **WHEN** a user runs `rarus-echo transcript not-a-uuid`
- **THEN** stderr explains that the file id must be a UUID and the command exits with a non-zero status

### Requirement: Submit command
The CLI SHALL provide a `submit <file>...` command that submits one or more files for transcription using SDK transcription options.

#### Scenario: Submit default options
- **WHEN** a user runs `rarus-echo submit audio.ogg`
- **THEN** the command submits the file with default transcription options and stdout includes the returned file id

#### Scenario: Submit explicit transcription options
- **WHEN** a user runs `rarus-echo submit audio.ogg --task-type=diarization --language=ru --censor --speakers-correction --low-priority --request-source=cli`
- **THEN** the command passes the selected task type, language, boolean flags, and request source to the SDK transcription request

#### Scenario: Submit without storing file
- **WHEN** a user runs `rarus-echo submit audio.ogg --no-store-file`
- **THEN** the command submits the file with `store-file` disabled in the SDK transcription request

#### Scenario: JSON submit output
- **WHEN** a user runs `rarus-echo submit audio.ogg --json`
- **THEN** stdout contains a JSON object with `file_ids`

#### Scenario: Invalid submit option
- **WHEN** a user passes an unsupported task type or language code
- **THEN** stderr explains the invalid option and the command exits with a non-zero status

### Requirement: CLI output and failures
The CLI SHALL write primary command results to stdout, write errors to stderr, return zero on success, and return non-zero on failure.

#### Scenario: Service command succeeds
- **WHEN** a service command completes successfully
- **THEN** command output is written to stdout and the exit code is zero

#### Scenario: Service command fails
- **WHEN** the SDK operation throws an exception
- **THEN** stderr contains a concise error message, stdout does not contain a partial result, and the exit code is non-zero
