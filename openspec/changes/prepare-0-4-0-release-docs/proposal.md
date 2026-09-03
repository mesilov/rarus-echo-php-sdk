## Why

The 0.4.0 milestone is ready to ship. The changelog still keeps every 0.4.0 change under `[Unreleased]`, and the README installation example does not pin the current release line. Release documentation should mark 0.4.0 as released and point new consumers at the `^0.4` constraint.

## What Changes

- Roll the `[Unreleased]` changelog entries into a dated `## [0.4.0] - 2026-09-03` section and keep an empty `## [Unreleased]` heading above it.
- Update the README installation example to pin the current release line with `composer require mesilov/rarus-echo-php-sdk:^0.4`.
- Verify documentation and standard project checks.

## Capabilities

### New Capabilities
- `release-documentation-0-4-0`: Release documentation and installation guidance for shipping version 0.4.0.

### Modified Capabilities

## Impact

- Affected files: `CHANGELOG.md`, `README.md`.
- Runtime SDK impact: none.
- Release impact: issue #34 can close after documentation checks and PR validation pass.
