## Implementation

- [x] Inspect current skill, source CLI and published release state; evaluate baseline scenarios.
- [x] Replace Docker-first instructions with installed CLI detection, installation offer and version checks.
- [x] Document executable installation, connection setup and the 0.5.0 call map.
- [x] Align plugin metadata and generated reference; detect omitted commands/options.
- [x] Verify help against installed 0.5.0-rc-2 and run independent skill scenarios.
- [x] Run plugin/OpenSpec validation, unit tests, lint-all and diff checks.
- [x] Update the installed skill and its references after verification.

## Verification evidence

- `make lint-all`: passed with `COMPOSE_PROJECT_NAME=rarus-echo-php-sdk COMPOSE_IGNORE_ORPHANS=True`, reusing the existing project network after Docker exhausted new-network address pools. Includes 27 OpenSpec items, plugin/help drift validation, CS Fixer, PHPStan and Rector.
- `make test-unit` with the same Compose environment: 392 tests, 1088 assertions, 3 skipped.
- Skill frontmatter validation and `bash -n`: passed.
- Installed 0.5.0-rc-2 help matches generated reference; fixture mutations verify omitted commands/options fail and changed defaults produce drift without replacing output on errors.
- Independent scenarios: absent CLI, old CLI, working RC, missing connection and wait timeout; clarified stderr submission IDs and empty stdout with `--output`. No live audio/API submission or credential changes.

- PR #79: merged current dev documentation and compression work without reverting it; plugin guidance follows the move from README to `docs/agent-plugin.md`. Revalidated after conflict resolution.
