## Context

Issue #34 asks to ship the 0.4.0 release. Both the issue title and the `0.4.0` milestone target the same SemVer version, so the release version is unambiguous. The 0.4.0 work is already merged into `dev` and captured under the changelog `[Unreleased]` section; what remains is the documentation rollover that marks the version as released.

## Decision

Follow the maintainer release-rollout process:

- Move the current `[Unreleased]` entries into a `## [0.4.0] - 2026-09-03` section, preserving the existing `Added` / `Fixed` / `Changed` grouping and wording verbatim.
- Keep an empty `## [Unreleased]` heading above the 0.4.0 section so future changes have a landing spot.
- Update the README installation example to pin the release line with `composer require mesilov/rarus-echo-php-sdk:^0.4`, matching the shipped version.

Tag, GitHub release, and Packagist publication are handled after the release PR merges and only if the issue requires them; this change scopes the documentation rollover only.

## Validation

- `make lint-openspec`
- `git diff --check`
- `make test-unit`
- `make lint-all`
