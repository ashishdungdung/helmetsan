# Helmetsan Operational Scripts & Tooling Directory
**Target:** Production Automation, Telemetry, Ingestion & Data Pipelines

This directory houses operational tools for deployment, multi-engine search indexation, revenue analytics, catalog synthesis, and background workers.

---

## 1. Search Engine Ingestion & Edge Performance

| Script | Runtime | Purpose |
|---|---|---|
| `multi_engine_ingest_controller.py` | Python 3 | Enterprise multi-engine dispatcher supporting IndexNow Universal Gateway, Bing Direct, Yandex Direct, Seznam Direct, Baidu Push API, and MariaDB ledger recording. |
| `indexnow_surge.py` | Python 3 | High-throughput batch submitter for mass catalog indexation into `api.indexnow.org`. |
| `edge_prewarm_crawler.mjs` / `.py` | Node.js / Python 3 | Concurrent crawler that sweeps high-priority catalog routes to prime Cloudflare edge POPs and Nginx FastCGI microcache (`x-fastcgi-cache: HIT`). |

---

## 2. Revenue Telemetry & Reporting

| Script | Runtime | Purpose |
|---|---|---|
| `aggregate_daily_clicks.py` | Python 3 | Rolls up raw `wp_helmetsan_clicks` records into `wp_helmetsan_clicks_daily` (configured as nightly cron: `5 0 * * *`). |
| `affiliate_telemetry_report.py` | Python 3 | Operational CLI report displaying real-time click volumes, affiliate network distributions, and top converting helmets. |
| `hybrid_affiliate_resolver.mjs` | Node.js | Cross-checks regional ASIN resolution and collision quarantine fallback routes. |

---

## 3. Translation & Swarm Orchestration

| Script | Runtime | Purpose |
|---|---|---|
| `launch_multi_swarm.py` | Python 3 | Multi-process supervisor managing continuous translation workers across locales. |
| `swarm_translation_bot.py` | Python 3 | Sharded translation engine connecting to translation APIs with lease coordination. |
| `swarm_lease_manager.py` | Python 3 | SQLite lease manager preventing worker collision during translation sweeps. |
| `translate_bridge.php` | PHP CLI | WordPress bridge connecting external translation processes to Polylang schema. |

---

## 4. Deployment, Reseeding & Infrastructure

| Script | Runtime | Purpose |
|---|---|---|
| `deploy.sh` | Bash | Production deployment builder (compiles bundles, syncs files, sets permissions, flushes caches). |
| `sync_data.sh` | Bash | Syncs catalog JSON definitions and database state between workspace and VPS. |
| `reseed.sh` | Bash | Full catalog pipeline: compiles seed JSON → syncs to VPS → executes WordPress ingestion. |
| `export-mobile-db.py` | Python 3 | Compiles master SQLite full-text search database (`HelmetsanMobile/assets/database/catalog.db`). |
| `create_helmets_seed.php` | PHP CLI | Compiles individual helmet JSON files into master ingestion seed file. |

---

## 5. Directory Substructure

- `archive/`: Historical scripts, previous version migration engines (v1–v4), and retired one-off migration tasks.
- `archive/consultations/`: One-off LLM consultation harnesses (Luna & Kimi prompt generators) preserved for provenance.
- `config/`: Configuration templates and environment maps.
