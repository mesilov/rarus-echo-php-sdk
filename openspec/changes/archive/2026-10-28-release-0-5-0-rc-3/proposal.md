# Release 0.5.0-rc-3

## Why

Issue #83 records the maintainer's request to assemble the changelog and publish RC-3 from dev. Since RC-2, merged changes add local extraction, executable compression, reduced notices, installed-CLI skill guidance and compact documentation. The published RC-2 cannot represent this newer source.

## What Changes

- Roll actual Unreleased entries into dated RC-3 notes with a concise cumulative overview; preserve RC-1/RC-2 history and empty Unreleased.
- Pin current installation and Composer examples to RC-3. Keep README Latest links and stable availability accurate. Advance the previously verified RC-2 skill baseline to RC-3 only after public acceptance and command-map comparison with its published binary.
- Archive already-merged compression, notice cleanup, transcription skill and local extraction changes, synchronizing canonical specs. Keep #72's stable-link gate open.
- Promote the dev-based checked source to main through green reviewed PRs, tag the exact main source and use existing four-platform publication/public acceptance workflows.
- Save the public macOS package and immutable verification evidence, archive this release after merge, and synchronize closeout to dev. Stable/manual acceptance stays in #68.

## Capabilities

### New Capabilities

None.

### Modified Capabilities

`published-release-acceptance`: select RC-3 in current prerelease documentation while preserving historical identities, earlier notes and unchanged Latest stable.

`installed-cli-transcription-skill`: update the verified command-map baseline to publicly accepted RC-3, including the already-generated extract-audio contract.

## Impact

CHANGELOG, explicit release installation documentation and OpenSpec lifecycle files. No PHP runtime, dependencies, CI gates or installer behavior changes are required. Release identity must be tied to the source actually reviewed and built.
