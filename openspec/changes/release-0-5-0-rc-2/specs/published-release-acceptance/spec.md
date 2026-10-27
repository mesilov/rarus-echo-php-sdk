## MODIFIED Requirements

### Requirement: Explicit prerelease documentation
Release documentation SHALL identify the selected release candidate as a prerelease, preserve an empty Unreleased changelog section, and show installer commands pinned to that candidate's exact version. Historical prerelease notes SHALL retain their original version and scope. Publication MUST keep the current stable Latest release unchanged.

#### Scenario: Stable release lacks standalone assets
- **WHEN** stable 0.4.0 remains Latest and the selected release candidate is 0.5.0-rc-2
- **THEN** documented RC installation selects 0.5.0-rc-2 explicitly instead of latest, while historical RC-1 notes and its frozen archive identity remain unchanged
