## Why

Issue #35 asks to make release maintenance less dependent on ad hoc chat context. The maintainer skill already covers issue-first work, but it does not spell out the release-specific changelog rollover and README installation-example update. New issues also start from a blank editor instead of structured bug and release templates.

## What Changes

- Add GitHub issue templates for reproducible bug reports and compact release rollout requests.
- Document the release workflow in the maintainer skill: set the release version in `CHANGELOG.md`, roll `Unreleased` forward, and update the README Composer installation example for the current release line.
- Keep the release work issue-first, OpenSpec-backed when it changes maintainer process, and verified through the existing local and CI gates.
- Add an `Unreleased` changelog entry for the maintainer workflow and issue-template change.

## Capabilities

### New Capabilities

- `issue-templates`: Repository issue creation offers a structured bug-report form and a compact release-rollout form.

### Modified Capabilities

- `maintainer-workflow`: Release issues include explicit changelog rollover and README installation-example steps.

## Impact

- Affected maintainer process: `.agents/skills/rarus-echo-maintainer/SKILL.md`.
- Affected GitHub configuration: `.github/ISSUE_TEMPLATE/`.
- Affected release notes: `CHANGELOG.md`.
- Runtime SDK impact: none.
- Affected tests: none; verification uses OpenSpec, diff, unit, and lint gates.
