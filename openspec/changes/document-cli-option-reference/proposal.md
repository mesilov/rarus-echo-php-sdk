## Why

The README `## CLI` section describes usage but does not list every command key in a structured reference, so consumers cannot see the full option set (values, defaults, per-command availability) without reading the source. Separately, the canonical CLI reference (`cli.md`) lives in the transcription skill and is drift-validated, while the README CLI section is edited by hand — nothing in the maintainer workflow requires keeping both aligned when the CLI changes, so they can silently diverge.

## What Changes

- Add a complete "Справочник команд и опций" reference to the README `## CLI` section: global options plus per-command arguments and options (`queue`, `submit`, `status`, `transcript`) with value requirements and defaults.
- Add a maintainer-workflow rule: when CLI commands or options change, align documentation in both places — the transcription skill CLI reference (regenerated via `update-cli-reference.sh`, drift-checked by `make lint-agent-plugins`) and the README `## CLI` section.
- Verify documentation and standard project checks, including the CLI reference drift check.

## Capabilities

### New Capabilities
- `cli-documentation`: Complete CLI option reference in the README and a maintainer rule to keep CLI docs aligned across the skill reference and README.

### Modified Capabilities

## Impact

- Affected files: `README.md`, `.agents/skills/rarus-echo-maintainer/SKILL.md` (mirrored via symlinks to `.claude/` and `.codex/`).
- Runtime SDK impact: none (documentation and maintainer process only).
- No CLI behavior changes; the generated `cli.md` already matches the current command definitions and is left untouched.
