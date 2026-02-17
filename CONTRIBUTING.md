# Contributing

## Workflow

1. Create a branch from `main`.
2. Keep changes modular and scoped.
3. Validate PHP syntax locally:
   - `find helmetsan-core helmetsan-theme -name '*.php' -print0 | xargs -0 -n1 php -l`
4. Open a pull request with:
   - clear summary
   - test/validation notes
   - rollback notes (if relevant)

## Standards

- Follow WordPress coding standards.
- Avoid unnecessary abstractions.
- Do not commit secrets or tokens.
