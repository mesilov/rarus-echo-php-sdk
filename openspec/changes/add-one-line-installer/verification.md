# Verification

Issue: https://github.com/mesilov/rarus-echo-php-sdk/issues/57

## Local checks (2026-10-27)

- `sh -n install.sh`: passed.
- `python3 build/tests/shell-installer-check.py`: 12 offline tests passed; optional real native archive test skipped because no archive was supplied for this local run. Fixtures cover all three Unix targets, pipe invocation, Unicode/quoted paths, latest and explicit prerelease tags, bash/zsh activation, repeat/update, malformed/unsafe payloads and injected commit failures. Review findings (dot-path aliases, empty runtime-license directories, symlink destinations and failed notices backup cleanup) were reproduced as failing tests before the fixes.
- `python3 -m unittest discover -s build/tests -v`: 7 packaging contracts passed.
- `make lint-openspec`: passed.
- `make test-unit`: 246 tests / 626 assertions passed on PHP 8.4.27.
- `make lint-all`: OpenSpec, agent plugin reference check, PHP CS Fixer, PHPStan and Rector passed. Existing vendor Rector emitted PHP deprecation diagnostics without failing.
- GitHub CI on initial PR head: installer jobs passed on Linux amd64/arm64 and macOS arm64; Unit Tests, Code Quality and Packaging contracts passed. Windows PowerShell 5.1 executed installation/update/PATH/config/identity scenarios successfully, then exposed a signed integer literal in the final locked-directory test fixture; the fixture was corrected to an explicit UInt32 value for the next run.

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
