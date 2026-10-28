# Release verification

Date: 2026-10-28 (Asia/Bishkek). Issue: #69. Target: exact prerelease 0.5.0-rc-2.

## Prepared source

PR #67 merged verified head 54008876e3e2028ca60fdefa8289d1fcf6b91479 into dev as e46d96962709a36de1c072be7bacb67dc20ebc72. All six feature workflows were green and no unresolved agent threads remained. The release worktree is based on that dev merge; the separate stable documentation branch for #68 was preserved.

CHANGELOG rolls only actual Unreleased changes into rc-2, leaving an empty Unreleased and preserving rc-1. README uses exact rc-2 Composer/installer commands and doctor availability; the historical rc-1 media exception remains exact. Merged doctor/media OpenSpec changes are archived in dependency order with synchronized canonical specs. Diagnostic scenario titles retain their previous identities with the approved new toolkit semantics. Independent review found the former hardcoded rc-1 documentation requirement; the release delta now selects the current RC while preserving historical notes/legacy identity. The initial preparation commit changed no runtime or CI code. PR #70 subsequently reproduced the existing audio-only WebM contract violation described below; the focused CLI fix implements that approved behavior, with no dependency, media-profile or CI change.

## Local gates

- make lint-openspec: 25 items passed; no missing diagnostic canonical dependency remains.
- make test-unit after the WebM fix: 392 tests, 1088 assertions; three opt-in real-corpus tests skipped by the standard suite and executed separately. Product media/native evidence is retained with archived #60.
- make lint-all: generated CLI reference, OpenSpec, PHP CS Fixer, PHPStan and Rector passed; dependency deprecation notices are nonfatal.
- python3 -m unittest discover -s build/tests -v: 54 passing contracts.
- Explicit real compact-tool media and diagnostics suites: 133 tests, 409 assertions, no skips; the earlier accepted Linux arm64 toolkit was mounted read-only.
- git diff --check: passed.

Local Docker checks reuse the existing project dev image and external network with a temporary Compose override because the host's default address pools are exhausted. Credentials were not read or printed.

## PR review follow-up

PR #70 bot finding PRRT_kwDOQieu5c6w1QhY / comment 4392735956 was verified with the real fileinfo result: audio-only.webm and video-opus.webm both report video/webm, while audio-only.m4a reports audio/x-m4a. The audio-only fixture reproduced ToolkitUnavailableException without overrides. Bounded structural WebM inspection proves audio-only tracks before toolkit resolution, preserving the original path/bytes and stream-selection rejection while video/uncertain containers retain verified preparation. Focused Media suite: 75 tests / 134 assertions (two existing opt-in corpus cases skipped there and executed in the explicit real-tool run). Independent re-review approved the frozen fix. Metadata reads are limited to 1 MiB and 4096 headers; binary payloads are skipped by finite element boundaries, and only root Segment unknown size is bounded by EOF. Tests cover the actual audio/video fixtures, original byte identity, manual selection, unknown-root variants, duplicate Tracks, video/unknown entries, malformed/truncated tails, concatenated documents and resource limits.

The first Windows PR build failed during FFmpeg HTTPS source acquisition with an SSL handshake timeout before compilation. A cache miss preceded that fresh source fetch; the final source must still pass its own native build. No TLS or checksum checks are bypassed.

## Publication evidence

- Release PR [#70](https://github.com/mesilov/rarus-echo-php-sdk/pull/70) merged after all six workflows passed, including [native package run 40564546109](https://github.com/mesilov/rarus-echo-php-sdk/actions/runs/40564546109). The exact main merge/tag SHA is `ba667107bb7611cbc0f3f47859cb63e970a635b9`; its tree equals reviewed source `63a0ca8d70927a40c9b465de8fe49d5ebd160da7`. Lightweight tag `0.5.0-rc-2` points to that commit. All five main push workflows passed, including Docker publication.
- [Tag build/publication 40568365590](https://github.com/mesilov/rarus-echo-php-sdk/actions/runs/40568365590): all four native packages, aggregate identities/checksums and release publication passed. [0.5.0-rc-2](https://github.com/mesilov/rarus-echo-php-sdk/releases/tag/0.5.0-rc-2) was published at 2026-10-27 19:45:21 UTC with four archives plus SHA256SUMS, prerelease=true and draft=false. Latest stable remains 0.4.0.
- [Public acceptance 40572112333](https://github.com/mesilov/rarus-echo-php-sdk/actions/runs/40572112333) passed its contracts and all four native platform jobs, using the exact rc-2 tag/source, anonymous public downloads and public main installers. Fresh/repeat installation, installed identity/configuration/PATH preservation, doctor and controlled audio-only upload passed without production Echo credentials or workflow-artifact substitution.
- [Packagist metadata](https://repo.packagist.org/p2/mesilov/rarus-echo-php-sdk.json) lists 0.5.0-rc-2 with source/dist reference `ba667107bb7611cbc0f3f47859cb63e970a635b9`.

Published SHA-256 values (release asset digests; each native archive also verified by public acceptance against SHA256SUMS):

| Asset | SHA-256 |
| --- | --- |
| rarus-echo-linux-amd64.tar.gz | c46926519d7f44e2d4f61ca1576eeb5b050e6ae8cce4d4954995196b0f35f3f2 |
| rarus-echo-linux-arm64.tar.gz | 3d82f548baab0a03664c14671b56880ed826b413751a6e0fb7447580fbd77820 |
| rarus-echo-macos-arm64.tar.gz | aae149c2dae65d9ebfdb224866b012ed44bdc2849e7d6e72a27978e4abf7a637 |
| rarus-echo-windows-amd64.zip | ad94720644813171d5cf154f5a5ec6256f0b8cd3e82786e44fefdba28f6aef45 |
| SHA256SUMS | 870a04f300ce196394f4fa1b4757a213a3a95ddf5ec96f98ce70503dbba1444a |

## Local public handoff and additional regression proof

The public macOS arm64 archive, SHA256SUMS and current public install.sh are saved under the primary workspace's `downloads/0.5.0-rc-2/`, with an extracted complete package, README and verification.json. The shared four-record manifest, archive identity/source/media payload and installer bytes were verified before extraction/execution. Installer SHA-256 is `060fa1beadc960bc3fb094aae512f5ed54e9f298904ca8e7efe38c3e8e8736a0`. The actual executable reports `RARUS Echo CLI 0.5.0-rc-2 (source ba667107bb7611cbc0f3f47859cb63e970a635b9)`.

A separate isolated check copied only the native executable (no adjacent toolkit), cleared PATH/real credentials and used a controlled loopback server: actual audio-only WebM uploaded its unchanged original bytes exactly once; actual video WebM failed before another request; source hashes and temporary cleanup remained correct. This passed both on the Linux arm64 CI binary from reviewed 63a0ca8 and the downloaded public macOS rc-2 binary. The local user's connection configuration and persistent PATH were not changed.

All tracked release tasks are complete. Closeout PR #71 carries this archive and synchronizes the reviewed main release source back into dev after its own green CI/review. Human installation/connection/workflow acceptance for stable 0.5.0 remains a separate step in #68; this release work does not claim that acceptance or publish stable.

## Closeout gates

After archive synchronization, strict OpenSpec passes 24 items. Final make test-unit passes 392 tests/1088 assertions with three default opt-in skips already covered by the explicit real-tool run; make lint-all, all 54 Python contracts and git diff --check pass. Independent closeout review verified the actual public release/source/hash/acceptance evidence and found no actionable issues. PR #71 still requires its final CI and review transition before merge into dev.
