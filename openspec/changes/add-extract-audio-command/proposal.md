# Proposal

## Why

Issue #78 requests audio extraction without transcription. `submit` already prepares verified audio but deletes it after uploading, so users cannot retain audio alone.

## What Changes

- Add local `extract-audio <file>` with `--audio-stream`, `--media-timeout` and `--json`.
- Save validated audio beside the supplied video with its basename and the preparation policy's extension, without replacing existing paths.
- Reuse media preparation, limits, tool discovery and supported cancellation; never resolve Echo credentials or call the API.
- Document usage and require GitHub issue creation through non-interactive `gh` in the maintainer skill.
- Unblock the required Windows native gate by retrying atomic executable activation only for short-lived sharing/lock violations, with a strict attempt limit and existing rollback protection.

## Capabilities

### New Capabilities

- `cli-audio-extraction`: persistent local audio extraction and safe sibling-file publication.

### Modified Capabilities

- `cli-installation`: bounded retries for Windows executable activation sharing locks exposed by repeat-install acceptance. Existing `submit` behavior and SDK API contracts remain unchanged.

## Impact

Runtime PHP additions in the console command/media layer and command registration; unit and local media tests; README / docs/cli.md, generated transcription CLI reference, CHANGELOG, maintainer skill, Windows installer and its contract tests. No dependencies, API endpoints, release publication or merge changes.
