# Proposal

## Why
Issue #74 requests the executable compression already used by b24cli. Echo currently compresses only its PHAR and download archives, leaving the embedded PHP runtime uncompressed.

## What Changes
- Port pinned UPX and wrappe packaging from b24cli f23ca85fe45b003cedd0b6fd0340da6ac850ba00; add Linux arm64.
- Preserve media discovery across macOS launcher extraction, command behavior and original runtime caches.
- Ship compression provenance/notices; accept compressed and original binaries natively before publication.
- Extend installer allowlists compatibly with existing releases and document macOS cache requirements.

## Capabilities
### New Capabilities
- `cli-executable-compression`: verified packers, distribution provenance and native behavior acceptance.
### Modified Capabilities
None. Existing installation and media requirements continue to apply.

## Impact
Packaging Python, PHAR bootstrap, standalone workflow, archive validators, installers, tests and documentation. Public SDK API and FFmpeg binaries remain unchanged. Coordinate overlapping archive work with #73.
