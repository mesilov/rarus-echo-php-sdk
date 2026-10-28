## Why

Issue #72 asks for a short README modeled on bitrix24-cli, a single curl-based binary installation path, and detailed documentation under docs/. The current 828-line README mixes installation alternatives, API examples, CLI reference and maintainer workflows. Latest stable 0.4.0 has no standalone assets, so the documentation must explain that publication dependency without replacing Latest with an RC.

## What Changes

- Keep a short README with four Latest asset links, curl download/extraction instructions, first use and documentation navigation.
- Move installation details to docs/install/ and move connection, CLI, SDK, agent plugin and development material to focused guides without dropping existing instructions.
- Update maintainer and contributor documentation to keep the hand-written CLI reference in docs/cli.md aligned with the generated skill reference.
- Retain an explicit stable-asset release gate shared with #68; this PR targets dev and does not authorize a tag or release.

## Capabilities

### New Capabilities
- `user-documentation`: Short README, detailed guides, navigation and maintenance contract. This supersedes the README-location requirements in the completed but unarchived document-cli-option-reference change (#46), without changing that historical change or its generator contract.

### Modified Capabilities
- `cli-media-preparation`: Relocate detailed preparation documentation from README to docs/cli.md, linked from README.

## Impact

README, docs/, CONTRIBUTING, maintainer skill, CHANGELOG and OpenSpec only. No runtime PHP, dependencies, installers, CLI commands or CI workflows change. Existing generated CLI reference stays byte-identical. Release issue #68 retains manual acceptance and stable publication responsibilities; #72 replaces its overlapping README layout requirements.
