# Helmetsan

Helmetsan is a WordPress-based, AI-assisted helmet data platform with:

- `helmetsan-core` plugin (ERP-style operations, ingestion, GitHub sync, analytics, go-live gates)
- `helmetsan-theme` GeneratePress child theme (frontend presentation layer)
- `docs/` architecture and technical documentation

## Repository Layout

- `helmetsan-core/` WordPress plugin
- `helmetsan-theme/` WordPress theme
- `data/` GitHub source datasets (brands, helmets, accessories, motorcycles, safety standards, dealers, distributors, pricing, offers, marketplaces, currencies, recommendations, comparisons, geo, schemas)
- `docs/` architecture and TDD docs

## Quick Start (Local)

1. Copy plugin to WordPress plugins:
   - `wp-content/plugins/helmetsan-core`
2. Copy theme to WordPress themes:
   - `wp-content/themes/helmetsan-theme`
3. Activate plugin:
   - `wp plugin activate helmetsan-core`
4. (Optional) Activate theme:
   - `wp theme activate helmetsan-theme`

## GitHub Sync Notes

The plugin supports pull/push sync with a GitHub data repository path (default `data/`).
Set credentials/config in plugin settings and validate with:

- `wp helmetsan health --format=json`
- `wp helmetsan sync pull --profile=pull+brands --dry-run`

## Production Readiness

Use the Go Live gate from WordPress Admin (`Helmetsan > Go Live`) or CLI:

- `wp helmetsan go-live checklist`

It provides objective score, critical blockers, and per-check diagnostics.

## License

This repository is licensed under the MIT License. See `LICENSE`.
