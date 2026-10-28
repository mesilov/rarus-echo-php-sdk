## ADDED Requirements

### Requirement: Short binary-first README
The README SHALL introduce the product, link to Latest Release and its four supported native packages, show only curl-based binary package installation, provide a minimal first-use example and link to detailed documentation. Download URLs MUST use releases/latest/download without pinned versions. Windows examples SHALL use curl.exe.

#### Scenario: Choose a native package
- **WHEN** a user opens the README
- **THEN** they can find Linux amd64/arm64, macOS arm64 and Windows amd64 packages and a short matching download/extraction example
- **AND** they are instructed to retain the complete package with media tools and licenses

### Requirement: Detailed guides preserve existing information
The repository SHALL provide installation details under docs/install/ and focused linked guides for connection configuration, CLI options, PHP SDK, agent plugin and development. Moving documentation MUST preserve existing instructions, fix relative links and keep the generated transcription-skill reference unchanged.

#### Scenario: Look up advanced usage
- **WHEN** a user follows the README documentation links
- **THEN** they can find checksum verification, PATH, explicit release selection, alternate installation methods, CLI defaults, media limits, SDK examples and development workflow without consulting an older README

### Requirement: Maintainer documentation follows the new locations
The maintainer workflow SHALL require synchronizing docs/cli.md and the generated transcription-skill CLI reference after CLI changes. README SHALL retain a link to the hand-written reference. Pinned Composer release examples SHALL be updated in docs/install/php.md instead of expanding README installation alternatives.

#### Scenario: Change a CLI option
- **WHEN** a maintainer adds, changes or removes a CLI option
- **THEN** they regenerate the skill reference, update docs/cli.md by hand and preserve the short README entry point

### Requirement: Latest availability is stated truthfully
Until stable native assets exist, README SHALL state the current availability limitation and link to explicit prerelease instructions in docs/install/. Documentation preparation MUST NOT authorize release publication or claim successful Latest installation. Closing issue #72 requires checking stable Latest assets after the separate release process.

#### Scenario: Latest stable has no native assets
- **WHEN** Latest stable is 0.4.0 and has no standalone archives
- **THEN** README does not claim its Latest download commands work yet and the PR records the dependency on #68
