## Context

Issue #46 asks for two things: a complete CLI key reference in the README, and a maintainer rule that keeps CLI documentation aligned when the CLI changes.

Two documentation surfaces describe the CLI:

- `.agent-plugins/rarus-echo-transcription/skills/transcribe/references/cli.md` — generated from structured CLI metadata by `update-cli-reference.sh` and drift-checked by `validate-agent-plugin.sh` (run through `make lint-agent-plugins`, part of `make lint-all` / `make ci`). It intentionally records only RARUS Echo command contracts and excludes framework-provided Symfony options.
- `README.md` `## CLI` — hand-written, consumer-facing, not covered by any drift check.

The command definitions in `src/Infrastructure/Console/Command/` are the source of truth.

## Decision

- Document the full option set in the README as a structured "Справочник команд и опций" subsection: a global-options table (`--json` plus the common Symfony Console globals) and per-command argument/option tables for `queue`, `submit`, `status`, `transcript`, mirroring the option set enforced in `cli.md`.
- Record the alignment obligation in the maintainer skill (`SKILL.md`): a bullet in "Правила реализации" and a dedicated "Изменения CLI" subsection describing the two surfaces, how to regenerate the skill reference, and that the README must be updated by hand. The `.claude/` and `.codex/` skill copies are symlinks to the `.agents/` file, so a single edit updates all three.
- Do not regenerate `cli.md`; it already matches the current command definitions, and no CLI behavior changes here.

## Validation

- `make lint-openspec`
- `git diff --check`
- `make test-unit`
- `make lint-all` (includes `lint-agent-plugins`, which fails on CLI reference drift)
