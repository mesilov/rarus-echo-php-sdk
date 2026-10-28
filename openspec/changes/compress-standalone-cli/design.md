# Design

## Context
See proposal.md and issue #74. Reference: b24cli f23ca85fe45b003cedd0b6fd0340da6ac850ba00. Echo additionally supports Linux arm64 and locates media beside the physical executable or in its immutable installed package.

## Goals / Non-Goals
Compress Echo and the acceptance probe on all four native targets. Preserve full CLI/media behavior and runtime caches. Do not compress media tools, change SDK APIs, publish a release or merge this branch.

## Decisions
- Reuse pinned UPX 5.2.1 --best and wrappe 1.0.4 / Rust 1.90.0 / Zstandard 16, rather than inventing another packer. Verify downloads and versions; patch only exact pinned source matches and record patch hashes. Linux arm64 uses its official verified UPX archive and the same ELF load-bound correction as amd64.
- UPX compresses a runtime copy before appending the unchanged PHAR. macOS signs the original combined binary, wraps it, extends launcher Mach-O bounds and signs it again. Keep originals for native acceptance, not distribution.
- Patch the pinned macOS runner to pass its own absolute executable path to the inner process. Only builds declaring the matching wrappe strategy consume that value in the PHAR bootstrap; ordinary micro uses its physical path. The runner overwrites inherited values for every invocation, including cached launches. This preserves portable/installed toolkit resolution from any cwd and copied executable name.
- Add compression-info.json and compression-licenses to archives only for builds declaring executable_compression. Validate provenance, final hash and strict size reduction. Keep historical uncompressed releases accepted; optional allowlisting never permits a partial compression package. Installers retain these artifacts with their package notices.
- Run existing native smoke including cancellation, timeouts and media against originals and final executables, and check repeat cached macOS runs. Native failure blocks artifact upload. Emit exact executable/archive size reports and include packer inputs in workflow triggers/cache keys.

## Risks / Trade-offs
- macOS extraction requires executable writable temporary storage and persistent cache space; document it and accept copied Unicode paths and cold/warm launches.
- Packer changes can alter process identity, signals or discovery; native tests cover the actual compressed release bytes.
- Archive allowlists overlap #73; preserve unrelated source attribution and coordinate contract changes during integration.
- Rust compilation increases first build time; cache tool identity and notices with checksum validation, separately from original PHP runtime validity.
