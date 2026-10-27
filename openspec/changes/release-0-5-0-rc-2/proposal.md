# Proposal

## Why

Issue #69 and the maintainer request authorize publishing 0.5.0-rc-2 from main. The verified media implementation and doctor need a complete native prerelease that users can install and manually accept before the separate stable release in #68.

## What Changes

- Roll the actual merged Unreleased entries into dated 0.5.0-rc-2 notes while preserving rc-1 history; update explicit Composer and standalone RC examples.
- Archive the merged doctor and media changes in dependency order and synchronize their canonical specs, retaining existing scenario identities.
- Promote the dev-based release source to main through a reviewed, green PR, then tag that exact main commit.
- Use the existing four-platform tag publication and public-release acceptance workflows; download and verify the public macOS arm64 archive for handoff.
- Synchronize release documentation back to dev. Stable 0.5.0 and the separate README restructuring in #68 retain their own lifecycle.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

`published-release-acceptance`: explicitly pinned prerelease documentation follows the selected release candidate rather than requiring RC-1 forever. Historical RC-1 notes and its frozen legacy archive identity remain unchanged. Native acceptance and media behavior are unchanged.

## Impact

CHANGELOG, explicit README version examples and OpenSpec lifecycle files. No PHP runtime, SDK API, dependencies or CI behavior changes. The user has authorized main promotion and the exact 0.5.0-rc-2 tag/publication. Prerelease metadata preserves Latest stable.
