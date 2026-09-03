## 1. Documentation

- [x] 1.1 Add a complete "Справочник команд и опций" reference (global options plus per-command arguments and options) to the README `## CLI` section.
- [x] 1.2 Add the CLI-alignment rule to the maintainer skill: a bullet in "Правила реализации" and an "Изменения CLI" subsection.

## 2. Review refinements

- [x] 2.1 Scope `--json` to the Echo commands and add `--silent` to the README global-options table.
- [x] 2.2 Qualify `--silent` with its minimum Symfony Console version (7.2) given the `composer.json` constraint.

## 3. Generator hardening

- [x] 3.1 Single-source the command list in `update-cli-reference.sh`: pass `PROJECT_COMMANDS` into the Node generator and remove the duplicate hardcoded array.
- [x] 3.2 Fail with a clear message when a command has no `optionAllowlist` entry instead of crashing.
- [x] 3.3 Simplify the maintainer "Изменения CLI" note to the two remaining lists (`PROJECT_COMMANDS` + `optionAllowlist`).

## 4. Verification

- [x] 2.1 Run `make lint-openspec`.
- [x] 2.2 Run `git diff --check`.
- [x] 2.3 Run `make test-unit`.
- [x] 2.4 Run `make lint-all` (includes the CLI reference drift check).
