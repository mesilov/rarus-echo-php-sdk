# Proposal

## Why

Issue #78 requests audio extraction without transcription. `submit` already prepares verified audio but deletes it after uploading, so users cannot retain audio alone.

## What Changes

- Add local `extract-audio <file>` with `--audio-stream`, `--media-timeout` and `--json`.
- Save validated audio beside the supplied video with its basename and the preparation policy's extension, without replacing existing paths.
- Reuse media preparation, limits, tool discovery and supported cancellation; never resolve Echo credentials or call the API.
- Document usage and require GitHub issue creation through non-interactive `gh` in the maintainer skill.

## Capabilities

### New Capabilities

- `cli-audio-extraction`: persistent local audio extraction and safe sibling-file publication.

### Modified Capabilities

None. Existing `submit` behavior and SDK API contracts remain unchanged.

## Impact

Runtime PHP additions in the console command/media layer and command registration; unit and local media tests; README / docs/cli.md, generated transcription CLI reference, CHANGELOG, maintainer skill. No dependencies, API endpoints, release publication or merge changes.
