## 1. Documentation

- [x] 1.1 Add a complete "Справочник команд и опций" reference (global options plus per-command arguments and options) to the README `## CLI` section.
- [x] 1.2 Add the CLI-alignment rule to the maintainer skill: a bullet in "Правила реализации" and an "Изменения CLI" subsection.

## 2. Verification

- [x] 2.1 Run `make lint-openspec`.
- [x] 2.2 Run `git diff --check`.
- [x] 2.3 Run `make test-unit`.
- [x] 2.4 Run `make lint-all` (includes the CLI reference drift check).
