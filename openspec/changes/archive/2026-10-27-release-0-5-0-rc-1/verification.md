# Verification

Issue: https://github.com/mesilov/rarus-echo-php-sdk/issues/59

## Preparation on 2026-10-27

- PR #58 merged into dev at `7f41160e2e263f44c1c33c2596ebc0eed0147571`; all CI passed and Codex bot review completed without findings. Installer OpenSpec was archived after that merge.
- `make lint-openspec`: 21 items passed.
- `make test-unit`: 246 tests, 626 assertions passed on PHP 8.4.27.
- `make lint-all`: OpenSpec, agent plugin reference, CS Fixer, PHPStan and Rector passed; existing Rector vendor deprecation messages did not fail the gate.
- The Make targets used the temporary network-free Compose override recorded in the installer archive because the local Docker address pool is exhausted. Shared Docker networks were not changed.
- `python3 -m unittest discover -s build/tests -v`: 21 packaging/acceptance tests passed. Network-dependent offline contracts bind only a local loopback server and use synthetic data.
- Helper `--help`, Python compilation and `git diff --check` passed.
- Release plan/doc review passed. Acceptance review identified missing successful JSON-config and persistent PATH checks; those checks now execute the installed binary, assert actual loopback requests and preserve configuration/profile/registry state.
- Codex PR review found REG_EXPAND_SZ loss in the Windows installer. Tests-only commit `ecbb80edaa024442ba22169d395b9337ffdb52e0` reproduced `registry value kind changed` in [native Windows PowerShell 5.1](https://github.com/mesilov/rarus-echo-php-sdk/actions/runs/40517296985/job/122234169600). The subsequent fix reads/writes raw registry state, preserves kind/existence and broadcasts the Environment change; independent spec/code review and 22 portable PowerShell checks passed. Native green evidence is recorded in PR #62 before merge.

## Publication receipts

The immutable tag points to main commit `e359975527bd483707dc39d23599545a77c1620d`. [Build/publication run](https://github.com/mesilov/rarus-echo-php-sdk/actions/runs/40522031696) succeeded with four native archives and SHA256SUMS; the prerelease leaves Latest stable at 0.4.0. [Initial public acceptance](https://github.com/mesilov/rarus-echo-php-sdk/actions/runs/40525546547) passed both Linux targets and macOS. Windows failed before starting the binary, in PowerShell 5.1 CodeDOM under Unicode TEMP.

[Native diagnostic](https://github.com/mesilov/rarus-echo-php-sdk/actions/runs/40528017127/job/122272789088) confirms ANSI CodeDOM temp-directory creation fails while managed Unicode file creation succeeds; changing only TEMP to ASCII succeeds. [Tests-only regression RED](https://github.com/mesilov/rarus-echo-php-sdk/actions/runs/40528487241/job/122274478738) at `1cdaf07` reproduces the installer failure in a fresh Unicode TEMP/cwd process. The bootstrap fix replaces C# compilation with two fixed, in-memory Reflection.Emit P/Invoke definitions, preserving the published tag and binaries.

[Native regression GREEN](https://github.com/mesilov/rarus-echo-php-sdk/actions/runs/40528776607/job/122275509842) at `7ff03ee` passes the Unicode bootstrap and existing installation/PATH/rollback checks. Linux amd64/arm64 and macOS installer fixtures also pass. Local mandatory Make gates, 246 PHP tests / 626 assertions, 21 packaging/acceptance contracts, 12 POSIX installer checks and 23 portable PowerShell checks pass. CI additionally exercises the native Unicode bootstrap with PowerShell 7.

[PR #65](https://github.com/mesilov/rarus-echo-php-sdk/pull/65) merged into main at `045b29a5713c8cfc39b5c91427da2640edc83b22` after independent review, Codex review without findings and green CI. [All four native builds and archive verification](https://github.com/mesilov/rarus-echo-php-sdk/actions/runs/40529150330) passed. Native Windows CI passed 41 installer checks on PowerShell 5.1 and 24 bootstrap checks on PowerShell 7. The public tag and its binaries still identify `e359975527bd483707dc39d23599545a77c1620d`.

[Final public acceptance](https://github.com/mesilov/rarus-echo-php-sdk/actions/runs/40532186773) from main `045b29a5713c8cfc39b5c91427da2640edc83b22` passed on Linux amd64/arm64, macOS arm64 and Windows amd64. Each runner anonymously downloaded the public 0.5.0-rc-1 archive and SHA256SUMS, checked embedded source `e359975527bd483707dc39d23599545a77c1620d`, compared the public main installer with the checkout, installed twice in isolated Unicode paths and ran installed CLI/config/local HTTP smoke tests. Existing configuration and persistent PATH remained unchanged. No workflow-artifact fallback or production API credentials were used. Issue #59 records publication and acceptance receipts.

Dev synchronization preserves doctor from PR #63 as Unreleased. Review of PR #66 found that its newer smoke suite would require doctor from the older RC. Local native RC smoke reproduced `Command "doctor" is not defined`; source-aware historical smoke then passed, followed by a successful full public macOS RC fresh/repeat acceptance using the combined checkout. A git-history contract checks old and new source snapshots independently of the current checkout and rejects missing commits. New package builds still require doctor by default. Combined-tree gates pass 305 PHP tests / 886 assertions and 22 packaging/acceptance contracts.
