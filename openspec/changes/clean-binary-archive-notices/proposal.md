# Proposal

## Why

Issue: https://github.com/mesilov/rarus-echo-php-sdk/issues/73

Standalone archives carry licenses for build helpers derived from b24cli and YiiPress, even though neither project is an application dependency. Separate build-source attribution from notices for shipped components and remove these extra archive files without breaking published releases.

## What Changes

- Omit BITRIX24-CLI-LICENSE.txt and YIIPRESS-LICENSE.txt from all new platform archives.
- Keep packaging attribution and original license files in source; consolidate applicable upstream terms for derived launcher/runtime support in the existing distributed notice.
- Update archive validation and both installers for the reduced composition, preserving old release installation and frozen legacy acceptance.
- Cover the new archive shape, published RC-2 shape, installer upgrades and retained runtime/media licenses with regression tests.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

- `cli-installation`: require runtime notices and licenses while supporting archives with and without historical build-helper license files.
- `published-release-acceptance`: validate the reduced new archive shape and the exact previously published tag/source shapes.

## Impact

Build packaging, archive validation, POSIX/PowerShell installers, build tests, build documentation and changelog. No SDK API, application dependencies or runtime PHP code changes. No historical assets are changed.
