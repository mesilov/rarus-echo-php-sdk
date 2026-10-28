## MODIFIED Requirements

### Requirement: Release-aware CLI call map
The plugin SHALL contain a generated command/option reference targeting 0.5.0, including install, doctor, extract-audio, queue, submit, status, transcript and global connection selection. It SHALL identify the publicly verified stable 0.5.0 baseline and distinguish historical prereleases from stable availability.

#### Scenario: Older CLI lacks requested capability
- **WHEN** installed help does not expose a required command or option
- **THEN** the skill offers an upgrade without fabricating the capability or implementing a workaround

#### Scenario: Help drifts
- **WHEN** a project command or option is added, removed or changed without updating the maintained map
- **THEN** `make lint-agent-plugins` fails until the generator and reference are aligned

#### Scenario: Media workflow
- **WHEN** a supported video is submitted with a media-capable installation
- **THEN** the skill passes the video directly to CLI submit, keeps the complete installed package and does not silently disable extraction or run ffmpeg itself
