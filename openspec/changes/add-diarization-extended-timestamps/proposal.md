## Why

The RARUS Echo API supports diarization with extended timestamp ranges, but SDK and CLI users cannot request that combination without bypassing `TranscriptionOptions` and calling the low-level API client manually. Issue #22 captures a real workflow need: submit audio with speaker diarization and `[HH:MM:SS.SSS - HH:MM:SS.SSS]` timestamps from the public SDK and Docker CLI.

## What Changes

- Add a first-class `timestampsExtended` transcription option that maps to the OpenAPI `timestamps-extended` header.
- Add a `TranscriptionOptionsBuilder::withTimestampsExtended()` method.
- Add a `submit --timestamps-extended` CLI flag and wire it into submitted transcription headers.
- Document SDK, local CLI, and Docker CLI usage for diarization with extended timestamps.
- Add unit coverage for generated headers, builder behavior, CLI parsing, and CLI help.

## Capabilities

### New Capabilities
- `transcription-submission-options`: SDK and CLI transcription submissions can include optional API headers beyond the task type, including diarization-specific extended timestamps.

### Modified Capabilities
- None.

## Impact

- Affected public PHP API: `Rarus\Echo\Services\Transcription\Request\TranscriptionOptions` and `TranscriptionOptionsBuilder`.
- Affected CLI behavior: `Rarus\Echo\Infrastructure\Console\Command\SubmitCommand`.
- Affected docs: `README.md`.
- Affected tests: unit tests for transcription options and submit command behavior.
- No new runtime dependencies are required.
