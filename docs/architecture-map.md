# Architecture map

Short map of where key subsystems live. **Read this first** before exploratory filesystem searches. Kept minimal to stay accurate; update when structure changes.

## Plugin (`helmetsan-core/`)

| Area | Path | Notes |
|------|------|--------|
| **Bootstrap** | `includes/Core/Plugin.php` | DI container, service wiring, boot. |
| **AI** | `includes/AI/` | AiService, ProviderRegistry, LMStudioProvider, ContextBuilder, FillMissingService. **Local First:** LM Studio on port `1234`. **Memory:** `@modelcontextprotocol/server-memory` stores project context across sessions. |
| **CLI** | `includes/CLI/Commands.php` | All `wp helmetsan` commands. Large file; use grep or range reads. |
| **Admin** | `includes/Admin/` | Admin.php (menus), AiAdmin.php (AI settings). |
| **Ingestion** | `includes/Ingestion/` | JSON → WP; dispatches by `entity` (brand, motorcycle, etc.). |
| **Validation** | `includes/Validation/Validator.php` | Helmet + accessory schema/logic/integrity. |
| **Sync** | `includes/Sync/SyncService.php`, `includes/Data/SyncManager.php` | GitHub pull/push, apply. |
| **Repository** | `includes/Repository/JsonRepository.php` | Data root, listJsonFiles, listSubdirs, read. |
| **Health** | `includes/Health/HealthService.php` | Single report (DB, repo, AI, validation, etc.). |
| **API** | `includes/API/` | BrandController, PriceController. |
| **CPT & meta** | `includes/CPT/Registrar.php`, `includes/CPT/MetaRegistrar.php` | Post types, meta registration. |
| **SEO** | `includes/Seo/` | SchemaService, YoastSeoSeeder, AiSeoDescriptionProvider. |
| **Search** | `includes/Search/SearchService.php` | Faceted helmet search: parseParams(), buildQueryArgs(), query(). Used by helmet archive; get via `helmetsan_core()->getSearchService()`. |
| **Support** | `includes/Support/Config.php`, `Logger.php` | Config, logging. |
| **Media** | `includes/Media/` | MediaEngine, HelmetImageEnrichmentService. |
| **Media Admin**| `includes/Admin/MediaAdmin.php` | Media Health Dashboard, Task Queue triggers. |

## Theme (`helmetsan-theme/`)

| Area | Path | Notes |
|------|------|--------|
| **Templates** | Root `.php` (e.g. `archive-helmet.php`), `template-parts/` | Template hierarchy, entity cards. |
| **Assets** | `assets/css/pages.css`, `assets/js/filters.js` | CSS styling (including glassmorphism filter sidebar) and interaction JS. |
| **Certifications** | `assets/images/certifications/` | SVG badges for ECE, DOT, Snell, SHARP, ISI, FIM. |

## Data & config

| Area | Path | Notes |
|------|------|--------|
| **JSON catalogs** | `data/` (repo) or plugin data root (Config) | helmets, brands, accessories, etc. |
| **Schemas** | `data/schemas/` | helmet.schema.json, accessory.schema.json. |
| **Scripts** | `scripts/`, `deploy.sh` (root) | Ingestion/seeding scripts under `scripts/`. Root `deploy.sh` builds plugin/theme zips from source, deploys to VPS, invalidates Nginx FastCGI microcache, flushes WordPress object cache/transients, and performs health checks. |

## Docs

| Doc | Purpose |
|-----|---------|
| `AGENTS.md` | Entry point for agents; terminology; links to workflow. |
| `.agent/workflows/ai-optimizations.md` | ATEP, guardrails, token optimizations. |
| `docs/data-flow.md` | JSON ↔ WordPress ↔ GitHub. |
| `docs/COMMANDS_REFERENCE.md` | WP-CLI command list. |
| `docs/HELMETSAN_SCOPE_AND_ROADMAP.md` | Product/tech scope and priorities. |
| `docs/media-health-pipeline.md` | Phase C: Background generation & ingestion pipeline. |
| [docs/migration-playbook.md](file:///Users/anumac/Documents/%20Projects/Helmetsan/docs/migration-playbook.md) | Step-by-step guide for provisioning a clean server, setting up WordPress, and importing data. |
