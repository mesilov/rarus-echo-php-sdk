# Windows Installation Delta

## ADDED Requirements

### Requirement: Bounded executable sharing retries

The Windows installer SHALL retry atomic executable replacement only for transient sharing or lock violations, for at most twenty attempts separated by 250 ms. Access-denied and unrelated failures MUST propagate without retries. Exhausted retries MUST fail through the existing rollback path and preserve the previous installation or its documented recovery files.

#### Scenario: Brief executable sharing lock

- **WHEN** executable activation encounters a sharing or lock violation that clears within the retry budget
- **THEN** installation completes without weakening archive validation or replacing atomic activation with a partial copy

#### Scenario: Persistent lock or unrelated failure

- **WHEN** the executable remains locked for the entire budget or activation fails for another reason
- **THEN** installation fails with bounded work and existing rollback protections remain active
