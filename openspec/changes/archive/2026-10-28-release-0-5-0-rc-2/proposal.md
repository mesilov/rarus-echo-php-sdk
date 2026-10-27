# Proposal

## Why

Issue #69 and the maintainer request authorize publishing 0.5.0-rc-2 from main. The verified media implementation and doctor need a complete native prerelease that users can install and manually accept before the separate stable release in #68.

## What Changes

- Roll the actual merged Unreleased entries into dated 0.5.0-rc-2 notes while preserving rc-1 history; update explicit Composer and standalone RC examples.
- Archive the merged doctor and media changes in dependency order and synchronize their canonical specs, retaining existing scenario identities.
- Promote the dev-based release source to main through a reviewed, green PR, then tag that exact main commit.
- Use the existing four-platform tag publication and public-release acceptance workflows; download and verify the public macOS arm64 archive for handoff.
- Fix the confirmed toolkit-free audio-only WebM regression with bounded structural container inspection; retain the approved audio/video and failure contracts.
- Synchronize release documentation back to dev. Stable 0.5.0 and the separate README restructuring in #68 retain their own lifecycle.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

`published-release-acceptance`: explicitly pinned prerelease documentation follows the selected release candidate rather than requiring RC-1 forever. Historical RC-1 notes and its frozen legacy archive identity remain unchanged. Native acceptance and media behavior are unchanged.

## Impact

CHANGELOG, explicit README version examples, OpenSpec lifecycle files and a focused internal CLI audio-only WebM classification fix with regression tests. The public SDK API, dependencies, media profile and CI behavior remain unchanged. The user has authorized main promotion and the exact 0.5.0-rc-2 tag/publication. Prerelease metadata preserves Latest stable.
