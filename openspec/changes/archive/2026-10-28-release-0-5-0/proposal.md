# Stable 0.5.0

## Why

The maintainer explicitly requested consolidating every RC change into 0.5.0, delivering it to dev, promoting dev to main and publishing final stable. Issue #68 targets 0.5.0, matching its milestone. The current user instruction authorizes stable publication; it does not invent a report that the maintainer ran particular manual tests.

## What Changes

- Add a cumulative stable changelog covering RC-1/RC-2/RC-3, preserving their historical entries and an empty Unreleased.
- Set Composer examples to ^0.5 and prepare pinned stable installation documentation without prematurely claiming public assets or Latest availability.
- Deliver the checked release preparation into dev first, then promote dev into main through a separate green reviewed PR, as explicitly requested.
- Tag the exact main commit as 0.5.0, build all four packages, publish a non-prerelease Latest release, verify public installation on all platforms and Packagist identity.
- After public verification, update availability notices and the verified skill baseline, fulfill #72's Latest gate, archive completed release/documentation changes and synchronize final documentation in dev and main.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

`published-release-acceptance`: stable releases consolidate candidate history, publish verified native assets as Latest and update installation availability after public verification; prerelease history remains unchanged.

`installed-cli-transcription-skill`: advance the verified command-map baseline to publicly accepted stable 0.5.0 without changing execution or authorization policy.

## Impact

Changelog, release/installation documentation, verified skill metadata and OpenSpec lifecycle. PHP runtime, installer implementation and CI behavior remain unchanged.
