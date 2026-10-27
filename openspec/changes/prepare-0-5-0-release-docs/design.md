# Design

## Context

The source branch contains doctor; the latest published binary tag is rc-1 and omits it. PR #67 is independently changing media handling. See proposal.md for scope and issue #68 for release acceptance.

## Decisions

- Reuse the existing detailed standalone installation section, moving it before the SDK quick start. Remove the Docker walkthrough instead of maintaining two CLI onboarding paths.
- Keep the published rc-1 tag in executable installer examples until rc-2 exists. Mark doctor as available only in a newer build. A nonexistent rc-2 command would make the quick start fail today.
- Build a cumulative 0.5.0 summary under Unreleased and retain rc-1 verbatim. Stable dates and the Composer `^0.5` constraint are follow-up after manual acceptance.
- Do not copy unmerged media documentation from PR #67. Reconcile it with the new README layout after that PR merges.

## Risks / Trade-offs

- README overlaps PR #67 → keep this PR separate and rebase/reconcile its media sections before merge.
- rc-2 is unavailable → issue #68 tracks public build completion, checksum/source validation and local download; publication and user acceptance remain pending.
- Docker development checks need a network → use an existing Compose project network without deleting or pruning other resources.
