# Verification

Issue: https://github.com/mesilov/rarus-echo-php-sdk/issues/57

## Local checks (2026-10-27)

- `sh -n install.sh`: passed.
- `python3 build/tests/shell-installer-check.py`: 10 offline tests passed; optional real native archive test skipped because no public standalone release exists yet. Fixtures cover all three Unix targets, pipe invocation, Unicode/quoted paths, latest and explicit prerelease tags, bash/zsh activation, repeat/update, malformed/unsafe payloads and injected commit failures. Two review findings (dot-path aliases and empty runtime-license directories) were reproduced as failing tests before the fixes.
- `python3 -m unittest discover -s build/tests -v`: 7 packaging contracts passed.
- `make lint-openspec`: passed.
- `make test-unit`: 246 tests / 626 assertions passed on PHP 8.4.27.
- `make lint-all`: OpenSpec, agent plugin reference check, PHP CS Fixer, PHPStan and Rector passed. Existing vendor Rector emitted PHP deprecation diagnostics without failing.

Docker initially refused a new Compose network because its address pools were exhausted. The checks then ran with a temporary override outside the repository:

```yaml
services:
  dev-php:
    network_mode: none
    networks: !reset []
```

`COMPOSE_FILE="$PWD/docker-compose.yaml:/private/tmp/rarus-echo-57-compose.yaml"` selected that override for both Make targets. Existing Docker networks were not changed.

## Publication boundary

The latest stable GitHub Release was checked through the connected GitHub API: tag `0.4.0`, no assets. Public anonymous installer URLs on `main` and a release with all standalone assets are prerequisites for end-user installation. This issue does not create a release or tag. Native installer CI uses offline executable fixtures; existing standalone workflow checks actual runtime packages.
