# Documentation Changelog

## 1.2 - 2026-02-23

**Source**: Documentation pass (data flow, ingestion, sync, admin, enrichment)

### New

- **docs/data-flow.md** — Data flow: JSON ↔ WordPress ↔ GitHub. Concept, structure diagram, where each action lives (Ingestion, Data/Reseed, Sync Logs, Import/Export), typical workflows, terminology. Copied to helmetsan-core/docs/data-flow.md for in-app Docs list.

### Updated

- **Admin (Admin.php)** — Page headers and descriptions: Ingestion (apply JSON to WP, read from data root; does not write to GitHub), Sync Logs (pull = download then apply; push = upload local JSON), Data/Reseed (run ingestion from JSON on disk), Import/Export (export = WP→JSON for push; import = JSON→WP). Dashboard panel and Docs page reference data flow.
- **docs/enrichment-process.md** — Data-flow context; fill-missing helmet meta now includes spec_shell_sizes and optional structured JSON (safety_intelligence, aero_acoustic, tech_integration, fitment).
- **docs/ai-module.md** — Data-flow note: AI runs on WordPress; export then push to get enriched data into GitHub.
- **docs/ai-seeder-enrichment-roadmap.md** — Data-flow note: ingestion does not write to GitHub; sync pull/push and export workflow.
- **docs/architecture.md** — Data Pipeline table: Data flow doc link, Sync = pull/push (not bidirectional in old sense), Ingestion = read JSON → WP only. Data Flow section links to data-flow.md.
- **docs/ingestion-unique-ids-and-hash.md** — Payload and hash include all ingested meta (e.g. safety_intelligence_json, aero_acoustic_profile_json, tech_integration_json, fitment_coordinates_json, spec_shell_sizes, model_year).
- **docs/json-and-github.md** — Aligned with data-flow: ingestion does not write to GitHub; push uploads local files; to get WP changes to GitHub use export then push.
- **docs/seo-seed-plan.md** — Data-flow and enrichment order; model_year and helmet_family in data used; links to enrichment-process and data-flow.
- **docs/HELMETSAN_ARCHITECTURE_BLUEPRINT_AND_MASTER_TDD.md** — Data flow reference; ingestion vs sync clarified; Admin IA includes Data/Reseed, Sync Logs.
- **AGENTS.md** — Terminology line references docs/data-flow.md for full data-flow concept.
- **docs/WORK_LOG.md** — Entry for 2026-02-23 documentation pass.

## 1.1 - 2026-02-18

**Source**: Multi-IDE (Cursor, ChatGPT Codex, Manual)

### HELMETSAN_ARCHITECTURE_BLUEPRINT_AND_MASTER_TDD.md

- **Added**: Section 15 "New Modules (v0.2+)" covering WooBridge, Scheduler, Media Engine, Commerce Engine, Comparison, and Recommendation.
- **Enriched**: `helmet.schema.json` with 4 new dimensions:
  - **Safety**: `safety_intelligence` (SHARP/homologation)
  - **Acoustics**: `aero_acoustic_profile` (Noise/Drag)
  - **Tech**: `tech_integration` (Comms/HUD)
  - **Fit**: `fitment_coordinates` (3D shape)
- **Updated**: Section 2.1 "Required Modules" to include new engines.
- **Updated**: Section 3.2 "WordPress CPT + Taxonomy" to include `comparison` and `recommendation` CPTs.

## 1.0 - 2026-02-17

- Initial version of the Architecture Blueprint and Master TDD.
