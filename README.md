# Helmetsan

Helmetsan is a WordPress-based, AI-assisted helmet data platform with:

- `helmetsan-core` plugin (ERP-style operations, ingestion, GitHub sync, analytics, go-live gates)
- `helmetsan-theme` GeneratePress child theme (frontend presentation layer)
- `docs/` architecture and technical documentation

## Repository Layout

- `helmetsan-core/` WordPress plugin
- `helmetsan-theme/` WordPress theme
- `data/` GitHub source datasets (brands, helmets, accessories, motorcycles, safety standards, dealers, distributors, pricing, offers, marketplaces, currencies, recommendations, comparisons, geo, schemas)
- `scripts/` deployment, seeding, and enrichment scripts
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

## Data Schema (v2 - Deterministic)

The catalog now uses a deterministic data processing pipeline. Variants are no longer randomly generated at ingestion time but are explicitly defined in the seed generator.

### 1. Variant Structure

- **Colorways**: Defined per-model in `create_helmets_seed.php`.
- **SKU**: Generated as `{BRAND_PREFIX}-{MODEL_PREFIX}-{COLOR_CODE}` (e.g., `SHO-RF14-MBK`).
- **IDs**: Slugs are now deterministic based on color name (e.g., `shoei_rf_1400_matte-black`).
- **Pricing**: Base price defined at model level; variants have specific `price_adj` deltas (e.g., +$50 for Graphics).

### 2. Standardized Taxonomies

- **Color Family**: 10 primary buckets for checking/filtering (Black, White, Red, Blue, Green, Yellow/Hi-Viz, Orange, Grey/Silver, Multi/Graphic, Carbon).
- **Finish**: `matte` or `gloss`.
- **Type**: Full Face, Modular, Open Face, etc.

## Usage

### Ingesting Data

The pipeline is automated via `scripts/reseed.sh`.

```bash
./scripts/reseed.sh             # Full cycle: Generate -> Deploy -> Ingest
./scripts/reseed.sh --skip-deploy # Just generate and ingest locally/remotely
```

### AI module (Phase 1: SEO; Phases 2–4 planned)

Configure providers (Groq, Gemini, Mistral, OpenRouter, Hugging Face, OpenAI, Perplexity) under **Helmetsan → AI**. Use free/low-cost first; premium providers have dedicated controls. See `docs/ai-module.md`.

### SEO seed (Yoast title, meta description, focus keyword)

Seed SEO fields for helmets, brands, and accessories. Optional AI-generated descriptions (Groq + Gemini, free tier). See `docs/seo-seed-plan.md` for details.

**On server** (from WordPress root `/var/www/helmetsan.com/public`):
```bash
wp helmetsan seo seed                    # Template-only, all types
wp helmetsan seo seed --use-ai          # With AI descriptions (set API keys in wp-config or env)
wp helmetsan seo seed --use-ai --limit=5 # Test run
```

### Resetting Data

To completely wipe the database (required for schema changes):

```bash
ssh root@helmetsan.com "wp --path=/var/www/helmetsan.com/public eval-file ../reset_helmets.php --allow-root"
```

## Data Pipeline

The helmet catalog is managed through a seed → deploy → ingest pipeline.

### One-command pipeline

```bash
./scripts/reseed.sh              # Generate → Deploy → Ingest
./scripts/reseed.sh --dry-run    # Validate + deploy, no DB writes
./scripts/reseed.sh --validate   # Just validate locally
```

### Individual steps

```bash
# 1. Generate seed JSON
php create_helmets_seed.php --output=helmetsan-core/seed-data/helmets_seed.json --stats

# 2. Validate (checks IDs, descriptions, type distribution)
php create_helmets_seed.php --validate

# 3. Deploy to server
bash scripts/deploy.sh

# 4. Ingest on server
ssh root@helmetsan.com "wp --path=/var/www/helmetsan.com/public helmetsan ingest-seed --allow-root"

# Optional: also ingest curated per-helmet JSONs from data/helmets (richer data, marketplace links)
ssh root@helmetsan.com "wp --path=/var/www/helmetsan.com/public helmetsan ingest --path=data/helmets --allow-root"
```

### WP-CLI Commands

| Command                              | Description                                                   |
| ------------------------------------ | ------------------------------------------------------------- |
| `wp helmetsan ingest-seed`           | Ingest a seed JSON array file (main workflow)                 |
| `wp helmetsan ingest --path=<dir>`   | Ingest per-file JSONs from data root (helmets, accessories)   |
| `wp helmetsan ingest-brands`        | Ingest brand JSONs from data root / brands                    |
| `wp helmetsan ingest-seed --dry-run` | Validate without writing to DB                                |
| `wp helmetsan unlock-ingestion`      | Force-remove stale ingestion lock                             |
| `wp helmetsan seed-accessory-categories` | Create accessory_category terms (fixes /accessory-category/bluetooth-headsets/ etc.) |

**Dashboard:** Use **Helmetsan > Data / Reseed** to run helmet seed, path-based helmets, accessories, and brands from the admin UI (with optional dry run).

### Consistency and safety

- **Ingestion is upsert-only:** Items are matched by `_helmet_unique_id` (or brand/accessory equivalent). Re-running seed or path ingest only **creates** new items and **updates** existing ones; it **never deletes** posts. Your existing 1400+ helmets and 70+ brands stay intact; new or changed seed data is merged in.
- **Yoast SEO:** During bulk ingestion, Yoast indexable creation is temporarily suppressed to avoid MySQL lock timeouts. After a full reseed, run Yoast’s indexation from **SEO > Tools** if you want fresh indexables for imported content.

## GitHub Sync Notes

The plugin supports pull/push sync with a GitHub data repository path (default `data/`).
Set credentials/config in plugin settings and validate with:

- `wp helmetsan health --format=json`
- `wp helmetsan sync pull --profile=pull+brands --dry-run`

## Production Readiness

Use the Go Live gate from WordPress Admin (`Helmetsan > Go Live`) or CLI:

- `wp helmetsan go-live checklist`

It provides objective score, critical blockers, and per-check diagnostics.

## Project Status

<!-- STATS_START -->

## 📊 Project Dashboard (Status: Live)

| Metric                | Value       | Status      |
| :-------------------- | :---------- | :---------- |
| **Brands in Catalog** | 73          | ✅          |
| **Helmets Indexed**   | 1,412       | ✅ Live     |
| **Parent Models**     | 297         | ✅          |
| **Logo Coverage**     | 67% (49/73) | 🎨 Enriched |
| **Last Sync**         | 2026-02-23  | 📡 Active   |

> _Stats generated automatically by `scripts/update_stats.php`_

<!-- STATS_END -->

## License

This repository is licensed under the MIT License. See `LICENSE`.
