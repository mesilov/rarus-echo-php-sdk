## Why

The transcription skill defaults to Docker and does not define what to do when the CLI is absent. Its generated reference has no release target. The user requests installed-CLI-only operation, an installation offer when unavailable, and a maintained 0.5.0 call map. This follows the plugin introduced in #26 and supersedes the Docker default from #41.

## What Changes

- Require an installed `rarus-echo` executable for every Echo operation; prohibit HTTP/SDK/custom-client and automatic Docker/PHP fallbacks.
- Check executable availability and version. Offer installation or upgrade when needed, preserving existing authorization for installation.
- Document executable installation separately from interactive connection configuration.
- Identify the call map as targeting 0.5.0 with the verified 0.5.0-rc-2 baseline; verify installed help before using version-dependent options.
- Keep generated command contracts synchronized with source help and detect previously unlisted commands/options.

## Capabilities

### New Capabilities
- `installed-cli-transcription-skill`: Installed CLI prerequisite, installation guidance and versioned call map for the shared transcription skill.

### Modified Capabilities
- None. Existing unarchived changes are historical; the new policy explicitly supersedes their Docker execution default.

## Impact

Plugin skill, bundled references, reference generator, plugin metadata and CHANGELOG. No SDK runtime/API changes. No live submission or credential changes are needed for verification.
