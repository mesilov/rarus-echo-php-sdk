# cli-media-toolchain Specification

## Purpose
Provide a verified, compact media toolkit with every supported CLI distribution so local video preparation needs no separate FFmpeg installation or runtime download.

## Requirements

### Requirement: Pinned compact media profile

The distributed toolkit SHALL contain FFmpeg and ffprobe built from a reviewed upstream release with verified source identity and one committed capability profile. Its advertised capabilities MUST exclude video encoding/decoding, device capture, playback and network access, while supporting the documented audio preparation matrix.

#### Scenario: Build the supported media profile

- **WHEN** a toolkit is built for a supported target
- **THEN** its reported source version, enabled capabilities and profile identity agree with the committed inputs
- **AND** both executables can probe video containers, copy supported audio packets and transcode supported audio without a video decoder

#### Scenario: Reject an unexpected source or capability

- **WHEN** the source checksum differs or an undeclared external codec, network protocol or GPL/nonfree component is enabled
- **THEN** the build fails before packaging or publication

### Requirement: Bundled media availability

The default Docker image and each standalone platform archive SHALL contain both media executables and the metadata needed to locate and verify them. Help, version, queue, status, transcript and ordinary supported audio submissions MUST NOT require running FFmpeg. CLI execution MUST NOT download a missing toolkit.

#### Scenario: Prepare video offline from the installed toolkit

- **WHEN** an extracted or installed CLI prepares a supported video without host FFmpeg or network access
- **THEN** it uses its bundled toolkit and completes local preparation

#### Scenario: Missing toolkit is diagnosed locally

- **WHEN** video preparation is requested from a package with a missing or invalid media executable
- **THEN** the command reports the local dependency error without fetching another binary or uploading the original video

### Requirement: Supported native media targets

The toolkit SHALL support Linux amd64/arm64, macOS arm64 and Windows amd64. Linux executables MUST run without a host dynamic linker or shared libraries. macOS and Windows executables MUST require only documented operating-system libraries, with no unbundled package-manager or compiler runtime dependency.

#### Scenario: Accept a platform without build-host tools

- **WHEN** both extracted media executables are run on their native supported target with a clean PATH
- **THEN** probe, stream copy and audio transcode succeed without PHP, Composer, Docker or host FFmpeg

#### Scenario: Reject an accidental host dependency

- **WHEN** linkage inspection finds a non-system shared dependency or the documented minimum OS cannot execute a binary
- **THEN** platform acceptance fails and that toolkit is not published

### Requirement: Complete media provenance and source materials

Every distributed toolkit SHALL include its version, upstream source checksum, common profile hash, target, effective configure arguments, compiler identity, binary hashes and applicable notices. Corresponding source, patches and build instructions MUST accompany the toolkit in a compressed source package; this provenance MUST describe the shipped binaries.

#### Scenario: Inspect an offline package

- **WHEN** a user inspects a downloaded archive or Docker filesystem without upstream network access
- **THEN** they can identify both binaries and access their corresponding source package, configuration, patches and licenses
- **AND** platform manifests share the reviewed source/profile identity while recording target-specific build details

### Requirement: Compact Docker delivery

The default CLI Docker image SHALL include the toolkit for linux/amd64 and linux/arm64 without installing the general-purpose distribution FFmpeg package or retaining its compiler/build tree. Both architectures MUST expose the same supported media profile and preserve the existing entrypoint and command behavior.

#### Scenario: Build a self-contained CLI image

- **WHEN** the final image is accepted for either supported architecture
- **THEN** video preparation works with a read-only input mount
- **AND** runtime contents contain the media binaries, metadata, notices and compressed source package without the build toolchain or unrelated media dependencies

### Requirement: Complete standalone media archives

Each newly built platform archive SHALL contain the CLI, both media executables, media provenance/source materials and existing runtime notices. Unix executable permissions MUST be preserved. The existing four platform archive names and selected-platform SHA256SUMS verification MUST remain usable.

#### Scenario: Verify a complete archive

- **WHEN** the release archive/checksum set is validated
- **THEN** all four archives contain their target's complete toolkit and consistent common media identity
- **AND** missing, duplicate, unsafe or unexpected members, wrong hashes and missing executable permissions fail validation

### Requirement: Install and update a coherent package

Installers SHALL verify, stage and activate the CLI and its toolkit as one installer-owned package, preserving existing user-scoped install and PATH options. Package resolution MUST work outside the installation directory and for paths with spaces or Unicode. Updates MUST preserve connection configuration and unrelated files.

#### Scenario: Upgrade a legacy executable installation

- **WHEN** a user upgrades an existing single-executable installation
- **THEN** the same command on PATH launches the new complete package and locates its bundled tools from another working directory
- **AND** the connection configuration remains byte-for-byte unchanged

#### Scenario: Install without system-wide changes

- **WHEN** a user selects a custom directory or disables PATH modification
- **THEN** the installer retains those choices and installs the toolkit without administrator privileges or a separate FFmpeg install

### Requirement: Failure-safe package activation

An installer MUST validate the staged complete toolkit before activating it. Failure during staging or activation MUST restore the previous complete working installation. An update MUST NOT mix tools from different package versions or overwrite media executables in use by an older running command.

#### Scenario: Interrupted activation or invalid media binary

- **WHEN** toolkit validation or package activation fails
- **THEN** the previous CLI, media toolkit, notices and activation entrypoint remain usable together
- **AND** temporary staging is removed while any files needed to recover an incomplete rollback are retained and identified

#### Scenario: Windows cannot replace an in-use CLI executable

- **WHEN** Windows prevents replacement of the installed CLI executable during an update
- **THEN** the installer reports that the running command must finish and preserves the previous complete installation
- **AND** it does not overwrite an in-use media executable or activate a mixed package

### Requirement: Media release acceptance

Publication SHALL require native acceptance for all four standalone targets and both Docker architectures, including extracted-package and installer checks. Toolkit input changes MUST trigger the affected build gates. Package size and compressed download deltas MUST be recorded against a named baseline, including source/notices overhead.

#### Scenario: A supported platform fails

- **WHEN** any required toolkit build, linkage, fixture, package or installer check fails
- **THEN** the corresponding distribution is not published by that run
- **AND** local success on a different platform is not reported as acceptance of the failed target

#### Scenario: Media profile changes

- **WHEN** pinned media source, profile, build helpers or toolkit fixtures change
- **THEN** Docker and standalone workflows validate the new inputs and do not reuse a cache entry with a different input identity

### Requirement: Composer and SDK dependency boundary

Installing the PHP library through Composer SHALL NOT install FFmpeg, require an FFmpeg PHP extension or change SDK transcription-service signatures. The Composer CLI MUST explain how to supply a compatible local toolkit when video preparation is requested; existing audio-only library and CLI usage MUST continue without that toolkit.

#### Scenario: SDK or audio-only consumer has no FFmpeg

- **WHEN** a Composer consumer uses the SDK or submits an existing supported audio file
- **THEN** no FFmpeg dependency is resolved or required and current SDK behavior remains available

### Requirement: Frozen published legacy contract

Installers MAY accept only the frozen published pre-media version/source pair in the design. It MUST retain checksum, exact legacy shape and native identity checks. Unknown identities or any partial media payload MUST fail.

#### Scenario: Install the existing published release candidate
- **WHEN** the user selects `0.5.0-rc-1` with source `e359975527bd483707dc39d23599545a77c1620d` and its verified complete legacy archive
- **THEN** installation and repeat installation preserve the original non-media CLI contract
- **AND** public acceptance retains the public main installers and checks either their authentic old layout or the matching immutable package layout

#### Scenario: Unknown or partial media archive
- **WHEN** an unknown version/source lacks media or any media members appear in the frozen legacy shape
- **THEN** validation fails before activation without selecting a legacy fallback
