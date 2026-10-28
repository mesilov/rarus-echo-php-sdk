# installed-cli-transcription-skill Specification

## Purpose
Guide agents through transcription using the installed RARUS Echo CLI, with explicit installation and connection setup, secret-safe execution and a release-aware command reference.

## Requirements

### Requirement: Installed CLI execution
The transcribe skill SHALL perform all RARUS Echo operations through the installed `rarus-echo` executable and SHALL NOT use direct HTTP, SDK code, custom clients, or automatic Docker/PHP fallbacks. This policy supersedes the Docker default in `default-transcribe-skill-to-docker`.

#### Scenario: Working CLI is installed
- **WHEN** a user requests an Echo workflow and the executable is available
- **THEN** the skill checks its version and executes the requested CLI command using local file paths and machine-readable output where supported

#### Scenario: CLI is missing or cannot start
- **WHEN** no working executable is available
- **THEN** the skill offers the appropriate installation instructions and waits for installation authorization unless already given
- **AND** it does not switch execution paths or submit through another client

### Requirement: Installation and connection setup
The skill SHALL distinguish installing the executable from configuring the connection with `rarus-echo install`, preserve secret safety, and retain existing installation authorization.

#### Scenario: Fresh installation without credentials
- **WHEN** the executable is installed but connection configuration is missing
- **THEN** the skill directs the user to the interactive `rarus-echo install` command without asking for secrets in chat
- **AND** it explains that `doctor --json` checks authenticated access

### Requirement: Release-aware CLI call map
The plugin SHALL contain a generated command/option reference targeting 0.5.0, including install, doctor, queue, submit, status, transcript and global connection selection. It SHALL distinguish the verified 0.5.0-rc-2 baseline from stable release availability.

#### Scenario: Older CLI lacks requested capability
- **WHEN** installed help does not expose a required command or option
- **THEN** the skill offers an upgrade without fabricating the capability or implementing a workaround

#### Scenario: Help drifts
- **WHEN** a project command or option is added, removed or changed without updating the maintained map
- **THEN** `make lint-agent-plugins` fails until the generator and reference are aligned

#### Scenario: Media workflow
- **WHEN** a supported video is submitted with a media-capable installation
- **THEN** the skill passes the video directly to CLI submit, keeps the complete installed package and does not silently disable extraction or run ffmpeg itself
