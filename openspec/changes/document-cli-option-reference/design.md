## Context

Issue #46 asks for two things: a complete CLI key reference in the README, and a maintainer rule that keeps CLI documentation aligned when the CLI changes.

Two documentation surfaces describe the CLI:

- `.agent-plugins/rarus-echo-transcription/skills/transcribe/references/cli.md` — generated from structured CLI metadata by `update-cli-reference.sh` and drift-checked by `validate-agent-plugin.sh` (run through `make lint-agent-plugins`, part of `make lint-all` / `make ci`). It intentionally records only RARUS Echo command contracts and excludes framework-provided Symfony options.
- `README.md` `## CLI` — hand-written, consumer-facing, not covered by any drift check.

The command definitions in `src/Infrastructure/Console/Command/` are the source of truth.

## Decision

- Document the full option set in the README as a structured "Справочник команд и опций" subsection: a global-options table (`--json` plus the common Symfony Console globals) and per-command argument/option tables for `queue`, `submit`, `status`, `transcript`, mirroring the option set enforced in `cli.md`.
- Record the alignment obligation in the maintainer skill (`SKILL.md`): a bullet in "Правила реализации" and a dedicated "Изменения CLI" subsection describing the two surfaces, how to regenerate the skill reference, and that the README must be updated by hand. The `.claude/` and `.codex/` skill copies are symlinks to the `.agents/` file, so a single edit updates all three.
- Do not change `cli.md` output; it already matches the current command definitions and stays byte-identical.
- Qualify the README global options by scope and version: `--json` is added by `AbstractEchoCommand` and is only present on the four Echo commands (not Symfony's built-in `list`/`help`); `--silent` was introduced in Symfony Console 7.2, but `composer.json` allows `symfony/console: ^6.4 || ^7.0 || 8.0.*`, so library consumers on older Console will not have it.
- Harden `update-cli-reference.sh` so the reference-generation process is not fragile:
  - Pass the shell `PROJECT_COMMANDS` array into the Node generator as arguments and derive `projectCommands` from them, removing the duplicate hardcoded list so the two cannot drift apart.
  - When a command in `PROJECT_COMMANDS` has no `optionAllowlist` entry, fail with a clear, actionable message instead of aborting on `undefined is not iterable`.
  This keeps the generator's output identical while making the manual steps captured in the maintainer skill smaller and safer.

## Validation

- `make lint-openspec`
- `git diff --check`
- `make test-unit`
- `make lint-all` (includes `lint-agent-plugins`, which fails on CLI reference drift)
