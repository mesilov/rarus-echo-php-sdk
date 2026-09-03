## Why

Agents that operate the RARUS Echo CLI currently rediscover the same transcription workflow in every session: command names, options, credential handling, Docker volume mapping, polling, transcript retrieval, and safe reporting. A distributable agent skill turns that workflow into maintained repository content for both Claude Code and Codex.

## What Changes

- Add a repository-local RARUS Echo transcription plugin with shared skill instructions and host-specific Claude Code and Codex manifests.
- Add maintained references for CLI commands, distribution/install paths, safe credential handling, and Docker/local execution.
- Add marketplace entries for local or repo-scoped plugin testing in Claude Code and Codex-compatible hosts.
- Add validation that checks plugin/skill structure and fails when the documented CLI reference drifts from the current CLI help.
- Document installation and usage from the README.
- This change does not affect runtime PHP SDK API or service behavior.

## Capabilities

### New Capabilities
- `agent-transcription-skill`: Cross-agent RARUS Echo transcription skill packaging, documentation, and validation.

### Modified Capabilities
- None.

## Impact

- Affected agent/plugin files: `.agent-plugins/rarus-echo-transcription/**`, `.claude-plugin/marketplace.json`, `.agents/plugins/marketplace.json`.
- Affected docs: `README.md`, `CHANGELOG.md`, OpenSpec artifacts.
- Affected validation: `Makefile` gains an agent-plugin validation target; no live API test is required by default.
- Affected runtime PHP code: none.
