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

## Publication receipts

The release tag, exact main commit, native build run and public-asset acceptance results are recorded in issue #59 after publication. Real acceptance uses anonymous public release assets, not PR workflow artifacts. No release success is implied by the local offline checks above.
