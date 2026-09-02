## Why

Issue #41 asks to make the published Docker CLI the unconditional default in the `rarus-echo-transcription` transcribe skill. The current `Command Selection` guidance says "Prefer the published Docker CLI" but then adds a conditional local-binary branch, which pushes agents to probe the environment (host `php`, `vendor/`, `bin/rarus-echo`) before running any command. On a typical machine without host PHP the answer is always Docker, so the probing is a wasted round trip.

## What Changes

- Rewrite the `Command Selection` section in `.agent-plugins/rarus-echo-transcription/skills/transcribe/SKILL.md` so the published Docker CLI is the unconditional default: the agent runs `docker run ... :cli <command>` directly without probing for host PHP or a local binary.
- Keep the local binary (`vendor/bin/rarus-echo`, or `php bin/rarus-echo` without a Composer bin proxy) documented, but only as an explicit opt-in — when the user asks for local execution or Docker is unavailable.
- Preserve existing `--pull=always` and credential-safety guidance.
- Add an `Unreleased` changelog entry.

## Capabilities

### New Capabilities

- None.

### Modified Capabilities

- `agent-transcription-skill`: The transcribe skill makes the Docker CLI the default execution path and treats local binary execution as an explicit opt-in instead of an auto-probed fallback.

## Impact

- Affected skill guidance: `.agent-plugins/rarus-echo-transcription/skills/transcribe/SKILL.md` (`Command Selection`).
- Affected docs: `CHANGELOG.md`.
- Affected runtime code: none.
- Affected CLI reference: none; `references/cli.md` records command contracts only and is unchanged.
- Verification uses OpenSpec, agent-plugin, diff, and lint gates.
