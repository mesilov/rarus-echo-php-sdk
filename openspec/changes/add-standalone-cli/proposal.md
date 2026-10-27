# Proposal

## Why

Issue #53 requests a CLI that works on workstations without PHP, Composer, or Docker, especially Windows. The standalone packaging in mesilov/bitrix24-cli is the reference for embedding PHP micro and distributing verifiable platform archives.

## What Changes

- Build standalone CLI executables for Linux amd64/arm64, macOS arm64, and Windows amd64 from the locked production dependencies and a pinned PHP micro toolchain.
- Embed release version and source SHA, expose them through `--version`, and ship matching `build-info.json`, runtime source metadata, licenses, and SHA256SUMS.
- Add native platform acceptance and release publication workflows; publish only after all four platform archives pass acceptance.
- Document installation, build prerequisites, release operation, and unsigned platform limitations. Keep Docker as the primary quick start.

## Capabilities

### New Capabilities

- `standalone-cli`: Dependency-free executable distribution, embedded build identity, archive verification, and release publication.

### Modified Capabilities

None. Existing active CLI changes were inspected; none defines standalone distribution.

## Impact

CLI application version reporting changes; SDK service behavior and HTTP contracts are unchanged. New build scripts and GitHub Actions compile PHP micro, stage production Composer packages, smoke-test isolated archives, and upload release assets. README, CONTRIBUTING, CHANGELOG, export exclusions, and packaging attribution are updated.
