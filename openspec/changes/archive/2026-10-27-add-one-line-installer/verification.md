# Verification

Issue: https://github.com/mesilov/rarus-echo-php-sdk/issues/57

## Local checks (2026-10-27)

- `sh -n install.sh`: passed.
- `python3 build/tests/shell-installer-check.py`: 12 offline tests passed; optional real native archive test skipped because no archive was supplied for this local run. Fixtures cover all three Unix targets, pipe invocation, Unicode/quoted paths, latest and explicit prerelease tags, bash/zsh activation, repeat/update, malformed/unsafe payloads and injected commit failures. Review findings (dot-path aliases, empty runtime-license directories, symlink destinations and failed notices backup cleanup) were reproduced as failing tests before the fixes.
- `python3 -m unittest discover -s build/tests -v`: 7 packaging contracts passed.
- `make lint-openspec`: passed.
- `make test-unit`: 246 tests / 626 assertions passed on PHP 8.4.27.
- `make lint-all`: OpenSpec, agent plugin reference check, PHP CS Fixer, PHPStan and Rector passed. Existing vendor Rector emitted PHP deprecation diagnostics without failing.
- `docker run --rm --network none --platform linux/amd64 --mount type=bind,src="$PWD",dst=/work,readonly --workdir /work mcr.microsoft.com/powershell:7.4-ubuntu-22.04 pwsh -NoLogo -NoProfile -File build/tests/windows-installer-check.ps1`: 22 portable PowerShell checks passed. Review reproduced a malformed ZIP directory-length field hiding an empty required license; extraction now checks actual written length and the regression passes.
- GitHub installer CI [run 40497067753](https://github.com/mesilov/rarus-echo-php-sdk/actions/runs/40497067753) on code commit `f19799212045ea14c783c7ad77e870e599d4e9af`: all four target jobs passed, including native Windows PowerShell 5.1 architecture detection, executable/PATH/update and locked-file/directory rollback scenarios. The initial run exposed a signed integer literal in the directory-lock test fixture; it was corrected to an explicit UInt32 value before this successful run.
- Spec compliance and separate code quality reviews passed for both installers after the verified fixes. Integration review corrected the upstream attribution to BSD-3-Clause, matching the retained license.

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

## Merge acceptance

Final head adc762073248db01e8948178f58a8c4f871e4598 passed all CI: https://github.com/mesilov/rarus-echo-php-sdk/actions/runs/40497674370 and https://github.com/mesilov/rarus-echo-php-sdk/actions/runs/40497674396. Codex bot completed review without findings; no unresolved review threads. PR #58 merged into dev at 7f41160e2e263f44c1c33c2596ebc0eed0147571 on 2026-10-27. Public release validation continues in issue #59.
