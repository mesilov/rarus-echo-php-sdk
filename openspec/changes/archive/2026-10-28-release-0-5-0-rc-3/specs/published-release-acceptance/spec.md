## MODIFIED Requirements

### Requirement: Explicit prerelease documentation
Release documentation SHALL identify the selected release candidate as a prerelease, preserve an empty Unreleased changelog section, and show installer commands pinned to that candidate's exact version. Historical prerelease notes SHALL retain their original version and scope. Publication MUST keep the current stable Latest release unchanged.

#### Scenario: Stable release lacks standalone assets
- **WHEN** stable 0.4.0 remains Latest and the selected release candidate is 0.5.0-rc-3
- **THEN** current installation documentation selects 0.5.0-rc-3 explicitly, README retains its permanent Latest links and accurate availability notice, and historical RC-1/RC-2 notes and frozen archive identities remain unchanged
