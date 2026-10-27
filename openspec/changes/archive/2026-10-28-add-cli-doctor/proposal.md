# Proposal

## Why

Issue #61 needs a single read-only command to explain whether CLI connection settings, Echo access and video audio extraction prerequisites are ready.

## What Changes

- Add argument-free `doctor` with independent config, connection and ffmpeg diagnostics, text and JSON reports.
- Reuse existing configuration validation and selection; perform an authenticated queue read with a ten-second total deadline.
- Find ffmpeg in PATH and run its version probe with a five-second deadline.
- Add deterministic tests and update CLI documentation and generated references.

## Capabilities

### New Capabilities

- `cli-diagnostics`: Read-only readiness checks and stable diagnostic reports.

### Modified Capabilities

None; connection precedence and validation remain unchanged.

## Impact

Runtime PHP console code, internal configuration metadata, tests, README, CLI reference generator and CHANGELOG. Add Symfony Process as a production dependency for portable executable discovery and bounded process execution. No transcription requests or new SDK service API.
