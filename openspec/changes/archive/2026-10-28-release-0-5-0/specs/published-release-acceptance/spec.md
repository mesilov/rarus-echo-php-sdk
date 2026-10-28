## ADDED Requirements

### Requirement: Stable publication and Latest acceptance
A stable release SHALL consolidate its candidate changes into a dated changelog section while preserving historical candidate notes and empty Unreleased. It SHALL publish the exact checked main source with four verified native archives and SHA256SUMS as a non-prerelease Latest release. Stable availability notices MUST be updated only after public assets and Latest routes are verified. Public acceptance MUST exercise all four native installers and preserve tag/source identity.

#### Scenario: Publish stable 0.5.0
- **WHEN** the maintainer authorizes final 0.5.0 from checked dev and all tag builds pass
- **THEN** the exact main tag is published as stable Latest, public installation passes on all supported platforms and Composer metadata points to that source

#### Scenario: Verify Latest downloads
- **WHEN** 0.5.0 becomes Latest with four standalone archives and SHA256SUMS
- **THEN** all four permanent README download routes resolve to its verified assets and final documentation reports stable availability

#### Scenario: Preserve candidate history
- **WHEN** stable release notes aggregate RC-1, RC-2 and RC-3
- **THEN** earlier dated notes and immutable RC tags/assets remain unchanged
