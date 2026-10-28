## ADDED Requirements

### Requirement: Distribution archive composition
New standalone archives SHALL omit BITRIX24-CLI-LICENSE.txt and YIIPRESS-LICENSE.txt while retaining application notices, runtime/media licenses and corresponding media source. Acceptance SHALL support historical license files only for exact published RC-1 and RC-2 tag/source identities; RC-1 retains its explicit no-media exception.

#### Scenario: New platform archive
- **WHEN** each of the four platform archives is packaged from new source
- **THEN** its validated contents omit the two build-helper license files and retain all required runtime and media artifacts

#### Scenario: Published historical archive
- **WHEN** an archive has the exact published RC-1 or RC-2 identity and original composition
- **THEN** acceptance validates that historical composition without changing published assets

#### Scenario: Unexpected build-helper license
- **WHEN** an archive from any other source contains either historical build-helper license file
- **THEN** release archive validation rejects the unexpected member
