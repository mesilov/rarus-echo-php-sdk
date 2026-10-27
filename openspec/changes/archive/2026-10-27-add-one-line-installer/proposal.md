# Proposal

## Why

Issue #57: standalone CLI archives from #53 need an installation path that selects the user's platform and verifies the download without PHP, Composer or Docker.

## What Changes

- Add POSIX and PowerShell installers for published standalone archives, adapted from bitrix24-cli f23ca85fe45b003cedd0b6fd0340da6ac850ba00.
- Support stable latest, explicit release tags, user installation directories and optional PATH activation.
- Verify checksums, archive contents, notices and candidate identity before replacing an installation.
- Document publication prerequisites and add offline installer CI on all four supported platforms.

## Capabilities

### New Capabilities

- `cli-installation`: install verified public standalone CLI releases for the current platform.

### Modified Capabilities

None. Existing standalone packaging and connection configuration contracts remain applicable.

## Impact

Root install.sh/install.ps1, build/tests, installer CI, README, CHANGELOG and packaging attribution. No runtime PHP code or SDK API changes. Public installation requires scripts on main and a Release containing standalone assets; this change does not publish a release.
