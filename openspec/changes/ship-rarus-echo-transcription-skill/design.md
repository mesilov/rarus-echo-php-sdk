## Overview

The repository will ship one shared plugin tree under `.agent-plugins/rarus-echo-transcription/`. That tree contains the source skill at `skills/transcribe/SKILL.md`, references under `skills/transcribe/references/`, and host-specific plugin manifests under `.claude-plugin/` and `.codex-plugin/`.

The skill is instruction-only: it does not introduce a wrapper around the SDK or a new runtime command. It teaches agents how to use the existing `rarus-echo` CLI safely and repeatably.

## Plugin Layout

```text
.agent-plugins/rarus-echo-transcription/
  .claude-plugin/plugin.json
  .codex-plugin/plugin.json
  skills/transcribe/SKILL.md
  skills/transcribe/references/cli.md
  skills/transcribe/references/distribution.md
  scripts/update-cli-reference.sh
  scripts/validate-agent-plugin.sh
.claude-plugin/marketplace.json
.agents/plugins/marketplace.json
```

The plugin name is `rarus-echo-transcription`; the skill name is `transcribe`. Claude Code can expose it as `/rarus-echo-transcription:transcribe ...`. Codex-compatible hosts can install the plugin and invoke the `transcribe` skill through their normal skill syntax.

## Skill Behavior

The skill instructions will cover five workflows: queue inspection, submission of one or more local audio files, status lookup by file id, transcript lookup by file id, and submit-and-wait when the CLI supports it.

For execution, the skill will prefer the published Docker CLI:

```bash
docker run --rm ghcr.io/mesilov/rarus-echo-php-sdk:cli <command> [args]
```

It will document `--pull=always` as an explicit refresh or validation mode, not the routine default, matching the current README Docker image policy. It will also document the faster local path using `vendor/bin/rarus-echo` when dependencies are already installed. The Docker path must mount local audio paths read-only and pass credentials through environment variables or env files without printing secret values.

## CLI Reference Maintenance

The CLI reference will be generated from structured `rarus-echo list --format=json` and command-specific `rarus-echo help <command> --format=json` metadata. The update script records only the project-owned command contracts for `queue`, `submit`, `status`, and `transcript`, and filters out framework-provided Symfony options. The update script will run the local Composer binary when `vendor/bin/rarus-echo` exists, or fall back to `php bin/rarus-echo` when dependencies are installed but the Composer bin proxy is absent. The validation script will regenerate the reference into a temporary file and compare it to the checked-in `references/cli.md`.

Local PHP fallbacks require a host PHP version compatible with the SDK. If host PHP is older than the Composer platform requirement, the update script uses Docker Compose instead.

This keeps the skill honest when CLI options such as `--timestamps-extended`, `--wait`, `--raw-result`, or `--output` change.

## Validation

`make lint-agent-plugins` will run the plugin validation script. The script will check:

- required plugin manifests exist and are valid JSON;
- required marketplace files exist and are valid JSON;
- the skill has YAML frontmatter with the expected name;
- required reference files exist;
- the CLI reference matches freshly generated structured command metadata.

`make lint-all` will include this target so pull requests validate the skill alongside OpenSpec and PHP checks.

## Non-Goals

- Do not add a new PHP SDK API.
- Do not add a new CLI command.
- Do not perform a live API smoke test by default.
- Do not print or validate real credential values.
