# Design

## Context

See proposal.md for motivation. Echo already exposes a Symfony Console entrypoint and locks production dependencies. The reference is mesilov/bitrix24-cli at f23ca85fe45b003cedd0b6fd0340da6ac850ba00. Its PHP micro patches address Windows fake CLI configuration/UTF-8 and signed macOS payload boundaries. Echo additionally needs fileinfo and Linux arm64.

## Goals / Non-Goals

Goals: run one executable from an extracted release archive, preserve existing credentials/current-directory behavior, and verify each target before publication. Build identity must describe committed source and the exact production lock file.

Non-goals: release a new SDK version for this issue, change API services, replace the Docker quick start, add an installer/updater, compress executables with third-party packers, or obtain signing certificates.

## Decisions

1. Embed a production PHAR in statically built PHP micro. A plain PHAR still requires PHP; bundling a PHP installation creates a multi-file runtime. PHP micro reuses the existing CLI with a single executable.
2. Pin PHP 8.5.11, static-php-cli, micro source, and reviewed patches to the reference versions. Compile curl, fileinfo, mbstring, phar, openssl, and supporting extensions; add pcntl on Unix. Record pins, patch hashes and composer.lock SHA256. Pin LF checkout for these provenance inputs so native Windows and Unix metadata agree. Runtime libraries are identified by SPC's exported source lock. Cache only the checkout-local compiler/runtime, keyed by config and patches.
3. Allowlist application files, create fresh staging, and install locked production packages without plugins/scripts. Explicit Nyholm factories in the SDK remain usable without Composer discovery plugins. Reject dirty source or a supplied source SHA different from HEAD.
4. On Windows copy the embedded PHAR into a private temporary directory and remove it at shutdown, following the reference workaround for micro stream handling. Use its UTF-8 patch for Cyrillic and spaced file paths.
5. On macOS include the PHAR inside Mach-O signature bounds and create an ad-hoc signature using the reference helper. This satisfies local loader checks but provides no Developer ID/notarization.
6. Build on native GitHub runners for all four targets. Accept archives in a temporary directory with no checkout dependencies and a clean PATH. Test version/SHA, all command help, expected credential errors, compiled extension/protocol discovery and local HTTP upload with Cyrillic paths.
7. A tag push builds all platforms, verifies the final archive/checksum set, then creates/updates that tag's GitHub Release. PRs build the same artifacts without publication. Workflow dispatch produces development artifacts. Prerelease suffixes create prereleases without changing Latest. The existing tag workflow is independent of Docker publication.

## Risks / Trade-offs

- Native runtime compilation is expensive → cache by pinned inputs and set bounded job timeouts; all four successful outputs remain required.
- Windows temp space is required → use an unpredictable private directory, propagate failures, and verify cleanup. Recheck the physical file after Phar unlink and use PHP filesystem deletion when it remains, including Unicode temporary paths.
- Unnotarized macOS/unsigned Windows files may trigger OS trust prompts → document this honestly.
- Toolchain source libraries can drift even with pinned SPC → include the exact source lock and notices with every artifact; no claim of bit-for-bit reproducibility.
- SDK dependency extensions change → platform checks and embedded runtime probe fail before publishing.
- Linux runtime must remain portable → force musl and check native imports and test the executable in a PHP-free container in addition to native acceptance.

## Migration Plan

Merge the PR into dev after required checks and agent reviews. Keep this change active until merge. A later version tag emits the four archives and SHA256SUMS; this issue does not create a release tag. Removing the new packaging workflow reverts distribution without changing Docker/Composer usage.
