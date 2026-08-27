# OpenSpec Instructions

Repo-wide agent policy lives in `../AGENTS.md`. This file only adds conventions for files under `openspec/`.

## Workflow

1. Use short kebab-case change IDs under `openspec/changes/<change-id>/`.
2. Add `proposal.md`, optional `design.md`, spec deltas under `specs/<capability>/spec.md`, and `tasks.md`.
3. Keep `tasks.md` current as implementation proceeds.
4. Validate with `make lint-openspec`.
5. Archive the completed change only after the linked pull request has merged.

## Validation

Use this command as the OpenSpec gate:

```bash
make lint-openspec
```

Use the repository's Makefile gates for PHP validation:

```bash
make test-unit
make lint-all
```

Run integration tests only when the issue touches live API behavior and credentials are available.
