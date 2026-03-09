This repository uses structured AI workflows for development.

Agents must follow the guidelines defined in:

- `.agent/workflows/ai-optimizations.md`

Those rules define:

- Agent Task Execution Protocol (ATEP)
- Autonomous Change Guardrails (ACG)
- Tool Usage Discipline (TUD)
- WordPress development rules
- Ingestion and Git sync safety
- Infrastructure modification safety
- Token and context optimization

Before performing modifications, agents must:

1. Confirm project root and runtime context.
2. Read `.agent/workflows/ai-optimizations.md`.
3. Follow the defined execution protocol end‑to‑end.

Project root (production server):

`/var/www/helmetsan.com/public/`

Primary code and data areas:

```text
wp-content/plugins/helmetsan-core/      # Main business logic, CPTs, AI, ingestion, sync, CLI
wp-content/themes/helmetsan-theme/      # Frontend templates, assets, WooCommerce overrides
wp-content/uploads/helmetsan-data/      # Git‑backed JSON data root (brands, helmets, accessories, etc.)
data/                                   # Source JSON catalogs in the Git repo
scripts/                                # Deployment, ingestion, enrichment, and maintenance scripts
```

Agents must **never** modify WordPress core directories:

```text
wp-admin/
wp-includes/
```

**Terminology:** **Seed** = generated JSON array of helmet variants (create_helmets_seed.php output). **ingest-seed** = CLI that ingests that array (wp helmetsan ingest-seed). **SEO seed** = Yoast title/meta/focus keyword (wp helmetsan seo seed). **Reseed** = full pipeline: generate → deploy → ingest-seed. **Sync** = GitHub pull/push of JSON under data/; **ingestion** = applying JSON to posts/meta. Full data-flow concept (JSON ↔ WordPress ↔ GitHub): **docs/data-flow.md**.

When in doubt, prefer:

- Minimal, localized changes over broad refactors.
- Editing existing services/modules instead of creating new subsystems.
- Using Helmetsan’s existing ingestion, sync, AI, and scheduler primitives rather than rolling custom infrastructure.
- Running the enrichment pipeline (fill-missing → SEO seed → cross-link) via CLI or admin rather than custom scripts; see `docs/ai-seeder-enrichment-roadmap.md` and README.

