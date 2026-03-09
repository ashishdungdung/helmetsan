# Helmetsan — Product & Technical Scope and Roadmap

This document captures **future scope** and **technical scope** from a product and technical management perspective. It guides prioritization and design without committing to a fixed timeline.

---

## 1. Product scope (future)

### 1.1 Catalog and data

- **Helmets:** 100+ brands; master JSON + seed pipeline; variants, colorways, certifications; AI-generated catalog expansion.
- **Accessories:** First-class catalog with categories, compatibility (brands, helmet families), pricing; AI-generated accessories; validation against schema; ingestion and backfill aligned with helmet pipeline.
- **Brands, dealers, distributors, safety standards, technologies, motorcycles, comparisons, recommendations:** Maintain parity with fill-missing, SEO seed, and cross-link; coverage reporting in admin and CLI.

### 1.2 Enrichment and quality

- **Fill-missing:** Primary mechanism for populating crucial fields (specs, analysis, taxonomies). Goals: high coverage, clear reporting, optional refill of key fields (e.g. weight, shell, price).
- **Cross-link:** Internal links by brand, type, certification, family, category. Goals: analytic report (by reason, totals), tunable limits, relevance ordering.
- **SEO:** Yoast seed (title, meta, focus keyphrase); AI meta descriptions; schema and integrity checks.
- **Validation:** Helmet schema/logic/integrity; **accessory schema and logic** (required fields, types, price shape); reusable for ingest and AI-generated data.

### 1.3 Discovery and UX

- **Search:** Faceted search (brand, type, certification, price range); relevance and filters.
- **Frontend:** Entity cards, archives, single pages; consistent design; performance and accessibility.
- **Admin:** Dashboard, catalog UI, AI (providers, fill-missing, **accessory generator**, **fill coverage report**), sync, ingestion, health, commerce, Woo bridge, menus and navigation clarity.

### 1.4 Operations and reliability

- **Health:** Single report (DB, repository, ingestion logs, sync, GitHub, AI config, revenue/analytics tables, validation and enrichment flags); admin menu “Health” (slug: helmetsan-repo-health); go-live checklist.
- **Alerts:** Configurable channels (email, Slack); Slack retry once on 5xx/429; critical failures (ingest, sync, API) surfaced.
- **Scheduler:** Sync pull, optional enrichment steps; cron-based, no duplicate runs.

### 1.5 Commerce and revenue

- **Pricing:** Price history, marketplace routing, geo-aware; APIs for frontend and partners.
- **Revenue:** Clicks, affiliate links, reporting; import and reconciliation.

---

## 2. Technical scope

### 2.1 Data and validation

- **JsonRepository:** Single data root; list/read JSON; optional helpers (e.g. list by entity type) without duplicating logic.
- **Validator:** Helmet schema/logic/integrity; **AccessoryValidator** or **Validator::validateAccessory*** for schema + logic (required: entity, id, title, type; optional: price, identifiers, categories).
- **Ingestion:** Existing pipeline; accessory path and backfill; validation before persist.

### 2.2 AI module

- **AiService:** Single-request usage; rate limiting; optional in-request cache for identical prompts; no PHP multiprocessing (use CLI concurrency for bulk).
- **ProviderRegistry / BaseProvider:** Robust timeouts, optional single retry on 5xx; clear free vs premium; test endpoint.
- **ContextBuilder:** Structured prompts for SEO, fill-field, retry, integrity; entity-specific hints (helmet vs brand vs accessory).
- **FillableFieldsConfig:** Complete field set per post type; labels, max_length, allowed_values; taxonomy fill config.
- **FillMissingService:** getCoverageReport (per-field set/empty/pct); run() with refill options; cache and rate limit.
- **SeedGeneratorService:** 100+ brands; existing-from WP/master; output to file or stdout.
- **AccessoryGeneratorService:** Schema-aligned output (entity, id, title, type, parent_category, subcategory, price, features); **AccessoryValidator** in pipeline; categories from taxonomy; prompt and validation improvements.

### 2.3 API and services

- **BrandController:** Batch accepts `string[]` (names) or `object[]` with `title`/`name`, optional `id`, `profile`; delegates to BrandService::upsertFromPayload. Response: `{ results: [...], count }`. Enrich validates brand ID and post type; errors use `{ error: true, message }`.
- **BrandService:** Single source for brand upsert and profile; used by API and ingestion.
- **PriceController:** Errors use `{ error: true, message }`; helmet ID validated (400 for invalid, 404 for not found).
- **HealthService:** Extend report with ai_configured, optional fill_coverage and accessory_validation; no heavy queries on every load.

### 2.4 Admin and CLI

- **Admin:** Expose **accessory generator** (count, category, provider, preview/download); **fill coverage report** (post type, table of set/empty/pct); menus and labels clear and consistent.
- **WP-CLI:** generate-seed, generate-accessories, generate-all; fill-missing (--report); cross-link (--report); ingest, sync, health, SEO, etc.; seed-data logic (create_helmets_seed, ingest-seed) documented and stable.

### 2.5 Concurrency and performance

- **PHP request:** Single-threaded; use sequential calls with rate limit; optional prompt-hash cache within one request to avoid duplicate API calls.
- **Bulk jobs:** CLI `--concurrency` spawns multiple processes (offset/limit split); no shared in-process parallelism.
- **Best practice:** Prefer smaller batches, idempotent runs, and clear reporting over complex in-process multitasking.

---

## 3. Prioritization (summary)

| Area | Priority | Notes |
|------|----------|------|
| Accessory validator | High | Unblocks safe ingest and AI-generated accessories |
| Expose accessory generator + fill report in admin | High | Visibility and control without CLI only |
| AccessoryGeneratorService improvements | High | Schema-aligned output, validation, prompt |
| ContextBuilder / FillableFieldsConfig / FillMissingService | Medium | Quality and coverage of filled data |
| AiService / ProviderRegistry / BaseProvider | Medium | Robustness, timeout, optional cache/retry |
| HealthService depth | Medium | AI config, fill summary, accessory check |
| Brand/Price services and controllers | Medium | API and consistency |
| Templates, menus, frontend | Ongoing | Incremental polish and accessibility |

---

## 4. References

- **Agent workflow:** `.agent/workflows/ai-optimizations.md`
- **Data flow:** `docs/data-flow.md`
- **Enrichment roadmap:** `docs/ai-seeder-enrichment-roadmap.md`
- **Commands:** `docs/COMMANDS_REFERENCE.md`
