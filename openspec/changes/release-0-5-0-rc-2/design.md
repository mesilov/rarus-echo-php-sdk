# Design

## Context

The verified media head 5400887 is merged into dev through PR #67 (e46d969). Main currently contains rc-1 and later public-installer fixes; dev additionally contains doctor and media preparation. A separate unmerged documentation branch for stable #68 remains outside this prerelease.

## Goals / Non-Goals

Publish the exact requested rc-2 from main through the existing native gates and hand off verified public binaries. Preserve source/tag identity and Latest stable metadata. Stable publication and manual user acceptance belong to #68.

## Decisions

- Use a release PR from the dev-based #69 branch into main. Tag only its verified main merge commit, avoiding a tag on an unmerged feature branch or repackaging earlier artifacts.
- Roll only actual Unreleased changes into rc-2 and update explicit RC examples. Preserve historical rc-1 notes and its exact legacy format exception.
- Archive merged doctor first, then media: the latter modifies the former diagnostic requirement. Preserve the two existing scenario titles while applying their approved bundled-toolkit semantics so OpenSpec does not silently discard coverage.
- Update the explicit prerelease documentation requirement to follow the selected RC, preserving its existing scenario identity. Runtime and CI requirements do not change; tag builds and public acceptance remain mandatory on all four targets.
- After publication/acceptance, archive this change and synchronize release documentation/evidence back into dev using a closeout PR.

## Risks / Trade-offs

- Native toolchain mirrors can fail transiently: inspect logs and retry the failed job on the same commit, preserving all acceptance gates.
- Public assets may be incomplete while publication runs: wait for the successful publish job before public acceptance and user handoff.
- A prerelease must not replace stable: use the workflow's prerelease/latest=false path and verify release metadata.

## Migration Plan

Merge the checked release source, tag the exact main SHA, wait for native publication, accept public assets, and hand off the macOS archive. On failure, repair the source through a new reviewed commit before selecting a new immutable version; never move or overwrite a published tag.
