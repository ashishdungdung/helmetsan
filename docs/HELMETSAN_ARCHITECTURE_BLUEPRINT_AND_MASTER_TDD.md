# Helmetsan Platform

## Architectural Blueprint + Master Technical Design Document (TDD)

Version: 1.1  
Date: 2026-02-18  
Scope: WordPress-first ERP plugin with GitHub-backed JSON data, deep ingestion, analytics/SEO/performance, and GeneratePress child theme.

---

## 1. Executive Architecture

### 1.1 Goal

Build `Helmetsan Core` as a WordPress plugin that acts as:

- Data ERP for product + content + geo/legal/supply-chain JSON in GitHub.
- Runtime engine for catalog rendering, analytics, SEO, and monetization.
- Control plane for AI-assisted workflows (Cursor/Codex/CLI/API).

### 1.2 Core Design Principles

- WordPress = control plane + fast read model.
- GitHub JSON = canonical long-term data asset.
- CLI-first for automation (`wp helmetsan ...`).
- Strong validation gates before publish/sync.
- Modular architecture with independently deployable modules.

### 1.3 High-Level Topology

1. AI/manual ingestion creates/updates JSON.
2. GitHub stores/version-controls JSON/content files.
3. `Helmetsan Core` sync job imports to WordPress read model (CPT + index tables).
4. GeneratePress child theme renders optimized pages.
5. Analytics + revenue + health checks run continuously.

---

## 2. Plugin Modules (Helmetsan Core)

### 2.1 Required Modules

1. Data Repository Manager
2. Curated Dataset Seeder + Manager
3. Deep Ingestion Engine
4. Repo Health + Validation Engine
5. Content Forge (AI content + fill pages)
6. SEO & Performance Suite
7. Analytics + Heatmap + Smoke Tests
8. Revenue Intelligence
9. Contribution Manager (PR workflow)
10. Import/Export Engine
11. Go-Live Checklist
12. Documentation Center
13. Brand Command Center
14. Geo-Legal-Supply Chain Engine
15. WooBridge (WooCommerce Sync)
16. Media Engine (Logo/Image Sideloading)
17. Scheduler (Async Task Manager)
18. Commerce Engine (Marketplaces, Offers, Pricing)
19. Comparison Engine
20. Recommendation Engine

### 2.2 Admin IA (Mac App Store style)

- `Helmetsan > Dashboard`
- `Helmetsan > Catalog`
- `Helmetsan > Brands`
- `Helmetsan > Ingestion`
- `Helmetsan > Repo Health`
- `Helmetsan > Analytics`
- `Helmetsan > Revenue`
- `Helmetsan > Contributions`
- `Helmetsan > Import/Export`
- `Helmetsan > Go Live`
- `Helmetsan > Docs`
- `Helmetsan > Settings`
- `Helmetsan > Safety Intelligence` (New)

---

## 3. Data Model & JSON Strategy

### 3.1 Repository Layout (Modular JSON)

```text
helmetsan-data/
  products/{brand}/{chassis}/
    global_core.json
    global_media.json
    geo/{REGION}/identity.json
    geo/{REGION}/parts.json
    geo/{REGION}/variants.json
    geo/{REGION}/media.json
    supply_chain/distributors.json
    supply_chain/warehouse_map.json
  entities/
    brands/{brand}.json
    distributors/{id}.json
    dealers/{id}.json
    standards/{id}.json
  content/
    products/{brand}/{model}/review.md
    products/{brand}/{model}/seo.json
    pages/about.md
    pages/contact.md
    brands/{brand}/history.md
  analytics/
    price_history/{product_id}.json
  schemas/
    helmet.schema.json
    brand.schema.json
    brand.schema.json
    dealer.schema.json
  safety_intelligence/
    sharp_ratings.json
  acoustics/
    wind_tunnel_profiles.json
```

### 3.2 WordPress CPT + Taxonomy

- CPT: `helmet`, `brand`, `accessory`, `motorcycle`, `safety_standard`, `dealer`, `distributor`, `technology`, `comparison`, `recommendation`.
- Taxonomies: `helmet_type`, `region`, `certification`, `segment`, `feature_tag`.

### 3.3 Deep Relationships

- Helmet ↔ Brand (1:n)
- Helmet ↔ Accessory (m:n bi-directional)
- Helmet ↔ Motorcycle (m:n)
- Helmet ↔ Safety Standard (m:n)
- Brand ↔ Distributor (m:n by region)
- Distributor ↔ Warehouse (1:n)
- Dealer ↔ Brand (m:n)

---

## 4. CLI-First Command Surface

All key features must be available via WP-CLI for Cursor/Codex/VS Code automation.

```bash
wp helmetsan health [--format=json]
wp helmetsan seed --set=start-pack-v1 [--force]
wp helmetsan ingest --source=repo --path=products/shoei/chassis-nxr2
wp helmetsan ingest --path=products --batch-size=100 --limit=500 --dry-run
wp helmetsan sync push|pull [--entity=helmet --id=shoei-nxr2]
wp helmetsan validate schema|logic|integrity [--id=shoei-nxr2]
wp helmetsan analytics smoke-test [--verbose]
wp helmetsan import --file=/path/data.zip --mode=merge
wp helmetsan export --entity=helmet --id=shoei-nxr2 --format=json
wp helmetsan go-live checklist
wp helmetsan docs build-index
```

---

## 4.1 Credit-Safe Execution Policy

- Batch all ingestion and sync operations by default.
- Run `dry-run` before production write operations.
- Avoid full-repository scans for single-entity updates.
- Deduplicate inputs and skip unchanged records.
- Keep AI generation opt-in and explicitly triggered.

## 5. Cross-Validation Framework (Deep)

### 5.1 Layer A: Schema Validation

- Validate JSON against schema files.
- Block sync on invalid structure/types.

### 5.2 Layer B: Logical Validation

- Weight bounds, price anomalies, required cert dependencies.
- Region legality consistency checks.

### 5.3 Layer C: Integrity Validation

- WordPress vs GitHub hash drift detection.
- Orphaned relationships/meta detection.
- Missing media / broken links scan.

### 5.4 Repo Health Output

`wp helmetsan health --format=json` returns:

- schema version/status
- CPT/table rows
- missing fields counts
- broken relationships
- sync lag/drift
- analytics readiness state

---

## 6. Deep Ingestion Engine

### 6.1 Pipeline

1. Intake (JSON/CSV/Markdown/API)
2. Normalize
3. Validate (A/B/C layers)
4. Resolve relationships (create-on-miss optional)
5. Transactional write to read model
6. Commit/PR to GitHub
7. Cache purge/warm

### 6.2 Transaction Rules

- SQL transaction per entity batch.
- rollback on hard errors.
- partial success only with explicit `--allow-partial` mode.

---

## 7. Analytics, Heatmaps, and Smoke Checks

### 7.1 Settings Keys

- `enable_analytics`
- `analytics_respect_monsterinsights`
- `ga4_measurement_id`
- `gtm_container_id`
- `enable_enhanced_event_tracking`
- `enable_internal_search_tracking`
- `enable_heatmap_clarity`
- `clarity_project_id`
- `enable_heatmap_hotjar`
- `hotjar_site_id`
- `hotjar_version`

### 7.2 Runtime Smoke Test

`wp helmetsan analytics smoke-test` validates:

- GA4/GTM config integrity
- event instrumentation registration
- MonsterInsights compatibility (no double tagging)
- Clarity/Hotjar script readiness
- consent mode and script gating

---

## 8. SEO & Performance Standards

### 8.1 SEO

- Programmatic meta title/description templates.
- JSON-LD: Product, Review, Breadcrumb, FAQ, Organization.
- Internal linking engine (glossary + entity links).
- Geo/legal-aware content blocks.

### 8.2 Performance

- Selective asset loading by route/post type.
- critical CSS support.
- image optimization + responsive media fields.
- cache tagging + granular purges.
- object cache compatibility.

---

## 9. Importer/Exporter (Deep)

### 9.1 Import

- JSON folders, zipped datasets, CSV mappings.
- dry-run mode with detailed report.
- merge/upsert/replace strategies.

### 9.2 Export

- entity-level or full snapshot export.
- include relationships and schema metadata.
- export for GitHub PR pipelines.

---

## 10. Contribution Manager

- Submit corrections/reviews via frontend.
- moderation queue in WP admin.
- GitHub PR creation/merge/close from WordPress.
- anti-spam + optional AI moderation assist.

---

## 11. Brand Command Center (Detailed)

Per-brand profile includes:

- corporate identity
- legal/warranty data
- region-specific certification mapping
- official distributors/dealers
- size-chart data
- service/support links

Cascade update action:

- update brand root data once
- regenerate dependent helmet overlays
- create batched GitHub commit/PR

---

## 12. Geo / Legal / Supply Chain

- Region overlays for identity/specs/media/parts.
- legality map (`legal`, `restricted`, `banned`, `import_only`).
- distributor + warehouse + dealer index.
- localized availability + compliance warnings on frontend.

---

## 13. Go-Live Checklist (In Plugin)

Status board must verify before “Launch Ready”:

- minimum dataset completeness threshold
- analytics smoke check passed
- schema validation clean
- no critical broken relationships
- SEO baseline complete
- performance baseline complete
- legal/compliance pages present
- backups and rollback paths verified

---

## 14. Documentation Tab

Built-in docs tab should include:

- architecture map
- CLI reference
- schema reference
- ingestion playbooks
- analytics troubleshooting
- release checklist

Markdown docs can be read from plugin `docs/` and rendered in admin.

---

## 15. New Modules (v0.2+)

### 15.1 WooBridge (WooCommerce Integration)

- Syncs `helmet` CPTs to WooCommerce `product` (Variable).
- Maps `variants_json` to Woo Variations with attributes (Color, Size, Style).
- Handles SKU generation, stock status mapping, and price updates.
- Supports dry-run and batch sync modes via CLI/Admin.
- Triggers on `save_post` or via Scheduler.

### 15.2 Scheduler Service

- Manages background cron tasks.
- **Sync Pull**: Periodic pull from GitHub based on configured profile.
- **Retry Failed**: Automatically retries failed ingestion logs.
- **Log Cleanup**: Prunes old sync/ingestion logs.
- **Health Snapshot**: Daily health check recording.

### 15.3 Media Engine

- Specialized tool for finding and sideloading brand logos.
- integrations: `SimpleIcons`, `Logo.dev`, `Brandfetch`, `Wikimedia`.
- UI in Admin (`Helmetsan > Media Engine`) and Media Library.
- Auto-converts/optimizes images (AVG/PNG) and attaches to posts.
- Deduplicates attachments based on source URL.

### 15.4 Commerce Engine

- Manages `Currency`, `Marketplace`, `Pricing`, and `Offer` entities.
- **Smart Pricing**: Updates `geo_pricing_json` with country-specific pricing/availability.
- **Best Offer**: Computes best offer from multiple marketplaces.
- **Entities**: JSON-backed storage for marketplaces and offers.

### 15.5 Comparison & Recommendation

- **Comparison CPT**: Stores `rel_helmets` and parameters/scores for side-by-side comparison.
- **Recommendation CPT**: Stores use-case based recommendations (`use_case`, `filters`, `items`).
- Both support deep ingestion from JSON payloads.

---

## 15. GeneratePress Child Theme Compatibility

Theme goals:

- fast rendering, minimal JS, mobile-first.
- pages: home index, helmet single, archive/filter, comparison, brand, dealer locator.
- plugin-driven data blocks for specs, legal notices, availability, CTA, history chart.
- sticky mobile CTA + structured data-aware templates.

---

## 16. Delivery Phases

### Phase 1 (Foundation)

- CPTs + schemas + sync + health + validation + docs + go-live tab.

### Phase 2 (Scale)

- deep ingestion + import/export + analytics smoke checks + SEO/perf suite.

### Phase 3 (Growth)

- content forge + contribution manager + advanced revenue + geo/legal/dealer index.

### Phase 4 (Optimization)

- brand cascade automation + advanced supply-chain intelligence + AI-assisted QA loops.

---

## 17. Definition of Done (v1)

- Plugin installs cleanly and registers all core CPT/taxonomies.
- CLI commands functional for health/seed/ingest/sync/validate.
- JSON schema validation gates live.
- Repo health dashboard operational.
- Analytics smoke test operational and MonsterInsights-safe mode works.
- Docs tab + go-live checklist available.
- GeneratePress child theme baseline templates integrated.
