## ADDED Requirements

### Requirement: Submit Wait Polling
The CLI SHALL allow `submit` users to wait for completed transcript results after a successful submission.

#### Scenario: JSON wait output for one file
- **WHEN** a user runs `rarus-echo submit audio.ogg --wait --json`
- **THEN** the command submits the file, polls its transcript until a terminal result, and writes one JSON payload to stdout containing `file_ids` and `results`
- **AND** each result includes `file_id`, `status`, `task_type`, and `result`
- **AND** polling progress is written to stderr, not stdout

#### Scenario: JSON wait output for multiple files
- **WHEN** a user runs `rarus-echo submit audio-1.ogg audio-2.ogg --wait --json`
- **THEN** stdout contains all submitted `file_ids` and one terminal result object for each file
- **AND** progress for each file is written to stderr

#### Scenario: Raw transcript output
- **WHEN** a user runs `rarus-echo submit audio.ogg --wait --raw-result`
- **THEN** stdout contains only the transcript result text for the submitted file
- **AND** Symfony Console markup-like transcript text is emitted as raw text

#### Scenario: Output file for transcript
- **WHEN** a user runs `rarus-echo submit audio.ogg --wait --output=transcript.txt`
- **THEN** the command writes only the transcript result text to `transcript.txt`
- **AND** stdout does not contain the transcript body

#### Scenario: Human-readable wait output
- **WHEN** a user runs `rarus-echo submit audio.ogg --wait`
- **THEN** stdout includes the submitted file id, final status, optional task type, and transcript result when present

### Requirement: Submit Wait Validation
The CLI SHALL reject invalid `submit --wait` option combinations before creating the SDK client or submitting files.

#### Scenario: Raw result requires wait
- **WHEN** a user runs `rarus-echo submit audio.ogg --raw-result`
- **THEN** stderr explains that `--raw-result` requires `--wait` and the command exits with a non-zero status

#### Scenario: Output path requires wait
- **WHEN** a user runs `rarus-echo submit audio.ogg --output=transcript.txt`
- **THEN** stderr explains that `--output` requires `--wait` and the command exits with a non-zero status

#### Scenario: Single-file raw result
- **WHEN** a user runs `rarus-echo submit audio-1.ogg audio-2.ogg --wait --raw-result`
- **THEN** stderr explains that `--raw-result` supports only one submitted file and the command exits with a non-zero status

#### Scenario: Single-file output path
- **WHEN** a user runs `rarus-echo submit audio-1.ogg audio-2.ogg --wait --output=transcript.txt`
- **THEN** stderr explains that `--output` supports only one submitted file and the command exits with a non-zero status

#### Scenario: Positive polling numbers
- **WHEN** a user passes a non-positive `--poll-interval` or `--timeout`
- **THEN** stderr explains the invalid option and the command exits with a non-zero status

### Requirement: Submit Wait Failure Handling
The CLI SHALL use non-zero exit codes and stderr diagnostics for wait failures while preserving stdout for successful final results only.

#### Scenario: Polling times out
- **WHEN** every transcript response remains `waiting` or `processing` until `--timeout` expires
- **THEN** the command exits with a non-zero status
- **AND** stderr includes the timeout and the last known status for each submitted file
- **AND** stdout does not contain a final result payload

#### Scenario: Terminal failed transcript
- **WHEN** a transcript reaches terminal status `failure`
- **THEN** the command exits with a non-zero status
- **AND** stderr identifies the failed file id and status

#### Scenario: API error during wait
- **WHEN** the SDK operation throws while submitting or polling
- **THEN** stderr contains `Error: <message>`, stdout does not contain a partial result, and the command exits with a non-zero status

#### Scenario: Interrupt signal during wait
- **WHEN** a user interrupts `rarus-echo submit audio.ogg --wait` with `SIGINT`
- **THEN** stderr explains that the command is shutting down because of `SIGINT`
- **AND** stdout does not contain a final result payload
- **AND** the command exits with a signal-aware non-zero status

#### Scenario: Terminate signal during wait
- **WHEN** `rarus-echo submit audio.ogg --wait` receives `SIGTERM`
- **THEN** stderr explains that the command is shutting down because of `SIGTERM`
- **AND** stdout does not contain a final result payload
- **AND** the command exits with a signal-aware non-zero status

### Requirement: Submit Wait Help
The CLI SHALL document the wait options in command help and README examples.

#### Scenario: Help lists wait options
- **WHEN** a user runs `rarus-echo submit --help`
- **THEN** help output lists `--wait`, `--poll-interval`, `--timeout`, `--raw-result`, and `--output`
