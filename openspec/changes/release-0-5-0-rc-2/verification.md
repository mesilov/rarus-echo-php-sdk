# Release verification

Date: 2026-10-28 (Asia/Bishkek). Issue: #69. Target: exact prerelease 0.5.0-rc-2.

## Prepared source

PR #67 merged verified head 54008876e3e2028ca60fdefa8289d1fcf6b91479 into dev as e46d96962709a36de1c072be7bacb67dc20ebc72. All six feature workflows were green and no unresolved agent threads remained. The release worktree is based on that dev merge; the separate stable documentation branch for #68 was preserved.

CHANGELOG rolls only actual Unreleased changes into rc-2, leaving an empty Unreleased and preserving rc-1. README uses exact rc-2 Composer/installer commands and doctor availability; the historical rc-1 media exception remains exact. Merged doctor/media OpenSpec changes are archived in dependency order with synchronized canonical specs. Diagnostic scenario titles retain their previous identities with the approved new toolkit semantics. Independent review found the former hardcoded rc-1 documentation requirement; the release delta now selects the current RC while preserving historical notes/legacy identity. No runtime or CI code changed during release preparation.

## Local gates

- make lint-openspec: 25 items passed; no missing diagnostic canonical dependency remains.
- make test-unit: 353 tests, 1040 assertions; three opt-in real-corpus tests skipped by the standard suite. Product media/native evidence is retained with archived #60.
- make lint-all: generated CLI reference, OpenSpec, PHP CS Fixer, PHPStan and Rector passed; dependency deprecation notices are nonfatal.
- python3 -m unittest discover -s build/tests -v: 54 passing contracts.
- git diff --check: passed.

Local Docker checks reuse the existing project dev image and external network with a temporary Compose override because the host's default address pools are exhausted. Credentials were not read or printed.

## Publication evidence

The exact main/tag SHA, release PR, native publication, public-asset acceptance and macOS checksum/handoff are recorded at closeout after those steps succeed. The active tasks deliberately keep these gates open before publication.
