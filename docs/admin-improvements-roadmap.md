# Admin & Plugin Improvements Roadmap

This document tracks the planned improvements across Helmetsan admin tabs, ingestion, sync, media, scheduler, catalog, pricing/offers, and Discover. Work is phased for minimal, safe changes.

---

## Phase 1 — Delivered / In progress

### Brands tab — AI enrichment (in progress)
- **Fillable fields:** total models, helmet types, cert coverage, support URL, warranty, origin (add to `FillableFieldsConfig::forBrand()`; some already exist).
- **UI:** On Brands page, add "AI enrichment" panel with actions: "Fill all missing / outdated" (up to 100 brands), "Fill key fields only" (total models, helmet types, cert coverage, support URL, warranty, origin). Reuse catalog AI pattern: `admin_post_helmetsan_brand_ai_*` handlers, transient result, message on redirect.
- **Backend:** Reuse `FillMissingService::run('brand', ...)` with appropriate `onlyFields`; add any missing brand meta to fillable config and optional URL validation for `brand_support_url`.

---

## Phase 2 — Ingestion & Ingestion tab

- **Mechanism:** Improve error reporting (per-file/per-entity), retry logic for failed items, optional dry-run summary before apply. Consider chunked processing for large JSON.
- **Tab:** Clearer steps (1. Select source 2. Validate 3. Ingest). Show last run summary (processed, created, updated, failed). Link to ingestion logs; add "Ingest path: helmets/brands/accessories" with path display and dry-run checkbox. Optional: progress indicator for long runs (e.g. heartbeat or redirect to log tail).

---

## Phase 3 — Sync tab

- **UI:** Distinguish "Pull" (GitHub → local JSON) vs "Push" (local → GitHub). Show last sync time and status; link to Sync Logs. One-click "Pull now" / "Push now" with confirmation. Display current branch and sync profile (from config).
- **Safety:** Keep existing guardrails (no force push, profile lock). Optional: diff preview before push (which files changed).

---

## Phase 4 — Repo Health tab

- **UI:** Replace raw JSON with a readable dashboard: sections for Repository, Database (CPT counts), Sync logs, Scheduler, AI, Alerts. Use cards or tables with status pills (healthy/warning/error). Link to relevant tabs (Sync, Scheduler, Catalog, etc.).
- **Content:** Expose same `HealthService::report()` data in a structured, scannable layout.

---

## Phase 5 — Import/Export tab

- **Clarity:** Separate "Export" and "Import" sections. Export: entity type (helmet/brand/…), post ID or bulk (e.g. "Export all helmets"), output path/upload. Import: file picker or path, dry-run option, summary (created/updated/skipped).
- **UX:** Post-run message with counts and link to Catalog/Brands. Optional: "Export changed since last sync" to reduce push size.

---

## Phase 6 — Technical analysis tool

- **Frontend (theme):** Richer single-helmet technical analysis block: animations (e.g. fade-in, stagger), optional charts (e.g. weight vs price, certs breakdown), metrics cards (weight, shell material, price, certs count). Keep content from `technical_analysis` meta and structured specs.
- **Admin:** If there is a dedicated "Technical analysis" tool in plugin, add preview and optional metrics (word count, readability, missing fields). Use existing design tokens and component styles.

---

## Phase 7 — Media engine & Media tab

- **Engine:** Improve logging and error messages for sideload/EAN/RevZilla; optional retry for failed image fetch. Document allowed sources (EAN-DB, eandata, RevZilla, AI) in Settings → Media.
- **Tab:** Summary of featured image coverage (helmets with/without thumb), last "Helmet images" run result, link to Helmet images page. Optional: bulk "Re-run enrichment for missing only".

---

## Phase 8 — Scheduler & Scheduler tab

- **Service:** Ensure task keys and cron hooks are consistent; clear error message when enrichment runs but no AI provider is configured.
- **Tab (Settings → Scheduler):** List next run times per task; "Run now" per task with result message. Enable/disable per task type. Show last run outcome (success/failure, message) from scheduler status.

---

## Phase 9 — Catalog

- **UI:** Keep existing catalog table and filters. Add optional columns (e.g. featured image yes/no, last modified). AI enrichment panel already improved (fill all / certs / specs). Optional: bulk export selected rows; "Select all on page" for actions.

---

## Phase 10 — Pricing & offers

- **Thinking:** Clarify data flow: catalog (helmet/brand) → pricing engines → offers. Ensure PriceService and marketplace connectors are documented; consider "Pricing health" on Repo Health (e.g. offers count, last feed run). No schema change in this phase; improve visibility and docs.

---

## Phase 11 — Discover tab

- **Enrich:** Keep current dashboard metrics and links. Add short "What to do next" (e.g. run sync, run ingestion, fill missing images, run AI enrichment). Optional: recent activity (last ingest, last sync, last AI run) from transients or logs. Improve cards layout and copy for first-time users.

---

## Implementation notes

- **Minimal change:** Prefer extending existing services (`FillMissingService`, `IngestionService`, `SyncService`, `HealthService`, `SchedulerService`) over new subsystems.
- **i18n:** All new UI strings wrapped in `__()`, `esc_html__()`, etc., with text domain `helmetsan-core`.
- **Security:** Nonces and `manage_options` for all admin actions; no direct file write from request without validation.
- **Docs:** Update `README.md` and `docs/data-flow.md` when flows change (ingestion, sync, export).
