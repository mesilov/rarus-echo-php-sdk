# Proposal

## Why

Issue #59 requests prerelease 0.5.0-rc-1 with standalone binaries built from main and tested after publication. PR #58 adds the installers; published-asset acceptance must now exercise the complete installation path.

## What Changes

- Roll Unreleased into a dated 0.5.0-rc-1 changelog section and document explicit RC installation.
- Archive the installer change after merged PR #58.
- Add a native four-platform workflow that downloads public release assets, checks the tag/source identity, installs through the public main installer and runs CLI smoke tests.
- Promote the prepared dev-based release branch into main, tag that main commit and let the existing tag workflow publish a prerelease without changing Latest stable.

## Capabilities

### New Capabilities

- `published-release-acceptance`: validate installed binaries from a public release against its tag and main ancestry.

### Modified Capabilities

None. Runtime PHP behavior and the standalone packaging contract are unchanged.

## Impact

CHANGELOG, README, OpenSpec archive, build acceptance helper/tests and a workflow. No PHP runtime changes or new user dependencies. Publishing 0.5.0-rc-1 is explicitly authorized by the user; no stable 0.5.0 release is planned here.
