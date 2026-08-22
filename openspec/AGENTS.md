# OpenSpec Instructions

This repository uses OpenSpec for spec-driven maintenance of non-trivial changes.

## When To Use OpenSpec

Create or update an OpenSpec change for:

- public SDK API changes;
- behavior changes visible to SDK users;
- architecture or service-layer changes;
- support-process, maintainer-process, or CI workflow changes.

OpenSpec is optional for:

- typo fixes;
- dependency bumps without behavior changes;
- trivial one-file documentation edits;
- mechanical formatting changes.

When in doubt, create a small OpenSpec change. The overhead is acceptable when the issue changes behavior or process.

## Workflow

1. Read the linked GitHub issue.
2. Run `openspec list` and `openspec list --specs` from the repository root.
3. If an active change already covers the issue, continue that change instead of creating a parallel one.
4. Create a short kebab-case change id under `openspec/changes/<change-id>/`.
5. Add `proposal.md`, optional `design.md`, spec deltas under `specs/<capability>/spec.md`, and `tasks.md`.
6. Validate with `openspec validate --all --strict --no-interactive`.
7. Implement the issue and keep `tasks.md` current.
8. Open the pull request against `dev`.
9. After the PR merges, archive the completed change with `openspec archive <change-id> --yes`.

## Validation

Use this command as the OpenSpec gate:

```bash
openspec validate --all --strict --no-interactive
```

Use the repository's Makefile gates for PHP validation:

```bash
make test-unit
make lint-all
```

Run integration tests only when the issue touches live API behavior and credentials are available.
