# Verification

Issue: #78. Delivery: PR #81 targets `dev`; no merge or release requested.

## Local checks

- `make lint-openspec`: all 26 changes/specs pass strict validation after rebasing onto the documentation move in #75.
- `git diff --check`: passes.
- `make test-unit`: 405 tests, 1170 assertions, four opt-in media tests skipped in the ordinary suite.
- `make lint-all`: OpenSpec, agent-plugin/generated CLI reference, CS Fixer, PHPStan and Rector pass. Rector's existing dependency emits PHP 8.4 deprecation notices without failing the check.
- `python3 -m unittest discover -s build/tests -v`: 54 tests pass.
- Separate `AudioExtractorCorpusTest` run with bundled Linux arm64 FFmpeg/ffprobe, PHP 8.4.27 and Docker `--network none`: 66 assertions pass for AAC/Opus stream copy, AC3/delayed-timeline FLAC, explicit track selection, source preservation, existing-output preservation and invalid inputs. No Echo client is created.
- Real SIGINT process tests cover both `submit` and `extract-audio`: child stops and owned partial audio/directory are removed.

Docker Makefile checks use `COMPOSE_PROJECT_NAME=rarus-echo-php-sdk COMPOSE_IGNORE_ORPHANS=True` to reuse an existing network because the host's pool for new Docker subnets is exhausted. No unrelated networks or containers were removed.

## Review and delivery

Independent local review found staging creation just outside its cleanup scope and non-portable expected path separators in tests. Both are corrected; the staging regression test was observed failing before the fix, then passed. Re-review found no remaining actionable findings.

PR native packaging smoke exercises advertised local extraction with AAC and FLAC, a missing config, no-overwrite publication and cleanup. The harness remains compatible with older published binaries without this command. Current native CI and hosted agent review results are tracked on PR #81; local acceptance alone is not native acceptance for the other platforms.

OpenSpec remains unarchived until the PR merges.

## Updated dev integration

The initial PR head `6f658c9` passed all 15 non-publication checks, including four native packages and archive validation. During that run dev advanced to `405a7c8` with #77 (standalone compression and the broader gh-only maintenance rule), causing documentation conflicts.

Merged that dev into the PR branch without rewriting published history. Preserved both Unreleased entries and the general gh-only policy together with the explicit non-interactive issue creation example. Extraction PHP code is unchanged. Independent integration review found no actionable issues with compressed-launcher media discovery or canonical native-smoke paths. Full local lint and all 405 unit tests pass again; updated packaging contracts also pass. The merged head requires fresh CI on PR #81 before delivery.
