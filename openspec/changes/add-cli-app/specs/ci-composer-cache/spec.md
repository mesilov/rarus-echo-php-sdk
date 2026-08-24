## ADDED Requirements

### Requirement: Composer dependency cache invalidation
GitHub Actions workflows that cache the Composer `vendor` directory SHALL invalidate the cache when Composer dependency metadata changes.

#### Scenario: Composer metadata changes
- **WHEN** `composer.json` changes in a pull request
- **THEN** lint and unit-test workflows do not restore a stale `vendor` directory from a cache created for different Composer metadata

#### Scenario: Future lock file is tracked
- **WHEN** `composer.lock` is tracked in the future
- **THEN** lint and unit-test workflow Composer cache keys include the lock file metadata
