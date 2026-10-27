# Design

## Context

Issue: https://github.com/mesilov/rarus-echo-php-sdk/issues/59. PR #58 is merged into dev after all CI jobs and bot review passed. Existing version-tag workflow builds four native archives and SHA256SUMS; tags containing a hyphen publish as prereleases with latest=false. Stable 0.4.0 has no standalone assets.

## Decisions

Use exact version/tag 0.5.0-rc-1. Prepare the release branch from dev and promote it through a reviewed PR into main. Only tag a verified main commit; do not release a feature-branch commit or rename an existing tag.

Keep runtime compilation and publication in the existing standalone workflow. Add an explicitly dispatched acceptance workflow on main with the release tag as input. Require the tag commit to be an ancestor of main. All four native runners download anonymous public release assets; no workflow-artifact fallback or production credentials are used.

Reuse build/release_assets.py archive inspection and build/tests/smoke.py. A small acceptance helper validates checksum and embedded version/source SHA, compares the downloaded public main installer to the checkout, installs into isolated Unicode/spaced directories without modifying persistent PATH, repeats installation and runs smoke against the installed executable. Existing POSIX tests may additionally consume the actual downloaded native archive through their --assets interface.

The acceptance helper receives version and source SHA as process arguments, not interpolated shell source. Windows invokes PowerShell 5.1; all configuration/environment directories are isolated. Tests use the local HTTP stub and generated WAV, with no remote transcription charge.

## Risks / Trade-offs

- First prerelease does not replace stable Latest, whose archives are missing → README uses an explicit RC version and retains stable guidance.
- Main can advance after tagging → ancestry plus exact embedded SHA proves origin; public installer comparison proves the currently served installer matches the tested checkout.
- Native compilation takes time → retain existing four-platform gates; wait for successful publication before acceptance.

## Execution

Validate locally and merge the release PR after green CI and handled agent review. Create the tag on main, verify all four archives and SHA256SUMS are published, then dispatch public-release acceptance on main. Record run URLs and source identity in the release issue. Archive this change only after its PR is merged and release acceptance is complete.
