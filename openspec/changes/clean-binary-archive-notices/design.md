# Design

## Context

See proposal.md for scope and issue #73. The archive allowlist, installer required lists and fixture producers all currently require build-helper licenses. Application staging includes bin/rarus-echo, src, LICENSE, composer.json and locked production dependencies; package-phar.php and linkage-check.py are build-only. Direct comparison with pinned upstream source confirms that the PHAR stub, Windows UTF-8 patch and macOS payload-bounds patch derive from b24cli. The YiiPress PHAR template contributes the original bootstrap approach. Runtime and PHP micro licenses already accompany those components.

## Goals / Non-Goals

Remove build-helper licenses from new binaries while preserving provenance in source and old release installation. Keep runtime/media licensing, checksums, source packages and archive safety checks. Do not change PHP behavior or republish old assets.

## Decisions

- Document build-source attribution in build/PACKAGING-NOTICES.md with existing upstream license files retained in source. build/THIRD-PARTY-NOTICES.md will describe shipped components and consolidate both original BSD notice texts for the derived runtime support. Installer source attribution links to the packaging notice.
- Remove two files from REQUIRED and package.py's copy loop. Keep strict validators for new archives. Exact published RC-1 and RC-2 identities select their old required license files; no missing-file heuristic selects a historical schema.
- Installers accept the two known historical license names as optional regular files, copy them when present, and do not require them for new packages. The frozen RC-1 no-media contract continues requiring its original licenses. Allowing these two inert files for installation avoids coupling installers to every prior version, while build validation stays strict.
- Preserve atomic package replacement: upgrading from old to new must remove obsolete license files from installer-owned notices, while retaining old immutable packages and user files.

Alternatives: deleting source provenance would misrepresent build origins; retaining mandatory archive licenses would leave the issue unresolved. Rewriting unrelated packaging logic would add risk without changing shipped code.

## Risks / Trade-offs

- Old release checks could accidentally accept new extras → tag/source pins and tests reject unknown identities with historical extras.
- Installer copy loops could fail on absent optional files or leave stale files → exercise old-to-new updates and full notices directory replacement.
- Native Windows cannot run on macOS → portable archive tests locally and required Windows installer/native build CI.

## Migration Plan

Ship installers and new binary archives through the normal dev PR. Old archives remain untouched. Keep OpenSpec active until the PR merges; roll back the commit if required.
