# Proposal

Issue: https://github.com/mesilov/rarus-echo-php-sdk/issues/68

## Why

Version 0.5.0 needs a cumulative changelog and a binary-first CLI quick start. The published rc-1 predates doctor, and rc-2 must be downloaded and manually accepted before stable publication.

## What Changes

- Summarize the merged 0.5.0 scope under Unreleased while preserving the dated rc-1 history.
- Promote the existing standalone installers and local command examples in README; remove the Docker walkthrough and image history.
- Keep installation examples tied to the actually published rc-1 until rc-2 exists, and explain which commands require a newer build.
- Track rc-2 download/manual acceptance and final documentation rollover in issue #68. Do not publish or date stable 0.5.0 in this documentation PR.

## Capabilities

### New Capabilities

None. This is documentation for existing functionality; `skip_specs: true` records that no runtime or installer contract changes.

### Modified Capabilities

None. Existing cli-installation and published-release-acceptance requirements remain unchanged.

## Impact

- Files: README.md, CHANGELOG.md and this OpenSpec change.
- Runtime PHP, public SDK API, dependencies and CI: unchanged.
- PR #67 / issue #60 is still pending; its media behavior must be reconciled after merge, before fixing the rc-2/stable scope.
