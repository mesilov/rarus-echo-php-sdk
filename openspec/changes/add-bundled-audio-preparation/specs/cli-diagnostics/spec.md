# CLI Diagnostics

## MODIFIED Requirements

### Requirement: Executable ffmpeg check
Doctor SHALL resolve and verify the same FFmpeg/ffprobe toolkit as submit within five seconds, using the complete package or explicit absolute override pair. It SHALL report safe errors and retain the JSON code family and path/version details.

#### Scenario: Available bundled toolkit with empty PATH
- **WHEN** the matching complete toolkit is present and PATH is empty
- **THEN** doctor reports successful media readiness and the resolved ffmpeg path/version

#### Scenario: Incomplete override or damaged bundle
- **WHEN** one override path is absent or a package identity/hash/capability check fails
- **THEN** doctor reports an actionable media error independently of configuration and connection checks

#### Scenario: Bounded unavailable toolkit
- **WHEN** toolkit resolution or probing is missing, unsuccessful or exceeds the combined five-second deadline
- **THEN** doctor returns a categorized safe error without installing dependencies or falling back to an unrelated PATH executable
