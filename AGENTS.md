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

Before performing modifications:

1. Confirm project root and runtime context.
2. **Token discipline:** For **small, scoped tasks** (single file, obvious fix, one parameter, typo), do **not** load the full governance doc or architecture map—proceed with minimal context. For **complex, exploratory, or multi-subsystem tasks**, read `.agent/workflows/ai-optimizations.md` and use **`docs/architecture-map.md`** to locate subsystems before broad searches. Prefer the **minimum** file reads and tool calls that suffice.
3. Follow the execution protocol in ai-optimizations.md when you have loaded it; otherwise apply minimal-change and safety principles (no core dirs, no hardcoded secrets, prefer existing services).

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

