# Verification — issue #72

## Scope and content

README reduced from 828 to 111 lines. Detailed material is retained in nine docs/ guides, including docs/install/README.md, docker.md and php.md. A comparison against the original README confirmed every fenced example is preserved (CLI examples may replace vendor/bin/rarus-echo with rarus-echo, with the invocation distinction explained) and all original option/argument table rows remain present. Checked 67 relative links and anchors across README and the nine guides; README sh blocks parse with sh -n. Generated transcription reference is unchanged.

Independent read-only review of the uncommitted diff found no actionable issues in the commands, relocation, links or maintenance rules. No runtime PHP, installer, dependency or workflow files changed.

## Local gates

- make lint-openspec: 25 items passed, zero failures.
- git diff --check: passed.
- COMPOSE_PROJECT_NAME=rarus-echo-php-sdk make test-unit: 392 tests, 1088 assertions, three skips, no failures (PHP 8.4.25). The existing skips depend on optional real media tools.
- COMPOSE_PROJECT_NAME=rarus-echo-php-sdk COMPOSE_IGNORE_ORPHANS=1 make lint-all: passed, including OpenSpec, agent plugin/reference, CS Fixer, PHPStan and Rector. Rector emitted existing vendor deprecations but returned success.

The new Compose project could not allocate a network because Docker address pools are exhausted. Reusing the existing rarus-echo-php-sdk project network allowed the normal Make gates to run with this worktree mounted. No existing networks or containers were deleted.

The generic skill quick validator rejects the pre-existing user-invocable frontmatter field; that supported project field is preserved. YAML parsing, existing metadata and new documentation destinations were checked separately; the repository agent-plugin gate passes.

## Native smoke and release limit

Executed the exact README macOS extraction/checksum/version block in an isolated temporary directory with curl transport substituted to copy the already downloaded public RC archive and manifest. Verified complete package contents, --version/build-info identity, --help and install/doctor/submit help, including all three media options. Download-failure transport exits before extraction or execution. No Echo API calls or user configuration/PATH modifications.

Verified package: 0.5.0-rc-2, source ba667107bb7611cbc0f3f47859cb63e970a635b9, macos-arm64. This verifies package layout and the documented native commands; it does not verify unavailable Latest URLs or Windows/Linux execution.

GitHub Latest metadata checked on 2026-10-28: stable 0.4.0, no assets. README includes this limitation and links to the explicit RC instructions under docs/install/. Stable publication and four-platform Latest download/install verification remain with #68 and the pending release task in #72. This PR authorizes no tag or release.


## Stable Latest acceptance — 2026-10-28

Release #68 published stable 0.5.0 as Latest from main tag source 43a76b3e8ae837e85f31e1104ca4259137e988a1. All four permanent Latest archive routes and SHA256SUMS were actually downloaded and passed API digest/size, manifest and strict archive/source/metadata verification. Public fresh/repeat native installation and CLI/media acceptance passed on all four targets in https://github.com/mesilov/rarus-echo-php-sdk/actions/runs/40722532306. A separate macOS installation without a version override selected stable 0.5.0 in isolated user directories, preserving personal credentials and persistent PATH. The public macOS package and verification record are saved under downloads/0.5.0/.

Original documentation PRs #75 and #80 are merged with all native/quality/unit/archive checks green and no review threads. The pending #72 stable-link gate is now fulfilled. README keeps permanent Latest URLs and curl-only installation; availability and pinned stable guides now report observed public state. Local README/docs file targets were checked across ten Markdown files. This closes the separate documentation dependency after release #68 publication rather than publishing a release from the original documentation PR.
