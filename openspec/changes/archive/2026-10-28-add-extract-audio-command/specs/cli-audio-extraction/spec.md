# CLI Audio Extraction

## Purpose

Let users retain verified audio beside a local video without submitting a transcription request or configuring an Echo connection.

## ADDED Requirements

### Requirement: Local extraction command

The CLI SHALL expose `extract-audio <file>` for one local video. It MUST operate without resolving connection settings or calling Echo. It SHALL support `--audio-stream`, `--media-timeout` (default 600) and `--json`, using the existing preparation format policy, deterministic stream selection, timeline checks and resource limits.

#### Scenario: Extract without configured credentials

- **WHEN** a supported video is extracted without credentials and with an unusable connection config
- **THEN** the command succeeds locally, preserves the original video and makes no API call

#### Scenario: Explicit stream and timeout

- **WHEN** the user supplies an absolute audio stream index and positive media timeout
- **THEN** the selected stream is prepared under that deadline using the same rules as submit

### Requirement: Sibling audio naming

The command SHALL save audio in the supplied input path's directory with the same basename and the extension selected by the preparation policy. It MUST preserve spaces, Unicode and literal shell characters in paths. Text stdout SHALL contain the absolute output path; JSON stdout SHALL contain an `output` field with that path. Progress and errors MUST go to stderr.

#### Scenario: Codec determines extension

- **WHEN** `meeting.mp4` contains copy-compatible AAC audio
- **THEN** the result is `meeting.m4a` beside that video
- **AND** an input requiring the existing lossless fallback produces a sibling `.flac` file

#### Scenario: Input symlink has a different name

- **WHEN** the supplied video path is a symlink named `alias.mp4`
- **THEN** the output is beside the symlink and uses basename `alias`

### Requirement: Safe publication and cleanup

The command MUST never replace an existing output path, including a symlink, directory or the input itself, even if the path appears during extraction. Only a complete validated output SHALL become visible at the destination. Failure and supported cancellation MUST clean owned staging and prepared files. Unsupported atomic publication MUST fail without truncating another file.

#### Scenario: Existing sibling audio

- **WHEN** the destination already exists or appears before publication
- **THEN** extraction fails without replacing its contents

#### Scenario: Preparation or persistence fails

- **WHEN** conversion, output copying, publication or supported cancellation fails
- **THEN** the command returns nonzero and leaves no partial destination or owned temporary audio

### Requirement: Invalid input diagnostics

The command MUST reject missing, unreadable, empty, corrupt, audio-only and no-audio inputs, invalid stream overrides, invalid timeout values and unavailable media tools with a concise diagnostic and a nonzero exit status.

#### Scenario: Audio-only input

- **WHEN** the input has no real video, including audio with attached artwork
- **THEN** the command fails without copying the input or creating a sibling output
