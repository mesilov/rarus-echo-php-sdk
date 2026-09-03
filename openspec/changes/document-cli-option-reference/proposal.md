## Why

The README `## CLI` section describes usage but does not list every command key in a structured reference, so consumers cannot see the full option set (values, defaults, per-command availability) without reading the source. Separately, the canonical CLI reference (`cli.md`) lives in the transcription skill and is drift-validated, while the README CLI section is edited by hand — nothing in the maintainer workflow requires keeping both aligned when the CLI changes, so they can silently diverge.

## What Changes

- Add a complete "Справочник команд и опций" reference to the README `## CLI` section: global options plus per-command arguments and options (`queue`, `submit`, `status`, `transcript`) with value requirements and defaults.
- Add a maintainer-workflow rule: when CLI commands or options change, align documentation in both places — the transcription skill CLI reference (regenerated via `update-cli-reference.sh`, drift-checked by `make lint-agent-plugins`) and the README `## CLI` section.
- Qualify the README global-options table by scope and version: `--json` is an Echo-command option (not available on Symfony's built-in `list`/`help`), and `--silent` requires Symfony Console ≥ 7.2 while `composer.json` still allows older Console versions.
- Harden `update-cli-reference.sh` so the maintainer process is not fragile: single-source the command list from the shell `PROJECT_COMMANDS` array (the Node generator receives it as arguments instead of duplicating it), and fail with a clear message when a command has no `optionAllowlist` entry instead of crashing.
- Verify documentation and standard project checks, including the CLI reference drift check (regenerated `cli.md` must stay byte-identical).

## Capabilities

### New Capabilities
- `cli-documentation`: Complete CLI option reference in the README and a maintainer rule to keep CLI docs aligned across the skill reference and README.

### Modified Capabilities

## Impact

- Affected files: `README.md`, `.agents/skills/rarus-echo-maintainer/SKILL.md` (mirrored via symlinks to `.claude/` and `.codex/`), `.agent-plugins/rarus-echo-transcription/scripts/update-cli-reference.sh`.
- Runtime SDK impact: none (documentation, maintainer process, and reference-generation tooling only).
- No CLI behavior changes; the generated `cli.md` stays byte-identical to the current command definitions.
