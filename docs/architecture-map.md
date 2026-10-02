# Helmetsan Architecture Map

A deterministic structural map of Helmetsan's **47 plugin subsystems**, **theme architecture**, and **catalog engines**. Read this first before exploratory filesystem searches.

---

## 1. Plugin Clusters (`helmetsan-core/includes/`)

### Cluster 1: Catalog & Entity Architecture
| Subsystem | Path | Function |
|:---|:---|:---|
| **CPT & Meta** | `CPT/Registrar.php`, `MetaRegistrar.php` | Post types (`helmet`, `accessory`, `motorcycle`, `brand`, etc.) and meta registrations. |
| **Entities** | `Helmet/`, `Accessory/`, `Brands/`, `Motorcycle/`, `SafetyStandard/`, `Dealer/`, `Distributor/` | Specific entity business logic, model repositories, and attributes. |
| **Ingestion Pipeline** | `Ingestion/`, `Seed/` | Ingestion pipeline converting raw JSON files into WordPress posts and taxonomies. |
| **Repository** | `Repository/JsonRepository.php` | Low-level filesystem catalog reader and parser. |
| **Validation** | `Validation/Validator.php` | Multi-tier schema and semantic physics validator (ECE 22.06 weights, negative prices). |

### Cluster 2: Monetization & Regional Commerce
| Subsystem | Path | Function |
|:---|:---|:---|
| **Revenue Service** | `Revenue/RevenueService.php` | Outbound `/go/` affiliate redirect engine with 404 search query fallback. |
| **Geo & Currency** | `Geo/GeoService.php` | 35-country registry, cookie parsing, exchange rates, and road legality mapper. |
| **Marketplace** | `Marketplace/Connectors/AmazonCreatorConnector.php` | Amazon Creator API (v3.1) integration with 30-min automated circuit breaker. |
| **Price Engine** | `Price/PriceService.php` | Historical price tracking, retailer offer aggregations, and transient caching. |
| **Commerce & Bridge** | `Commerce/`, `WooBridge/` | WooCommerce catalog synchronization and bridge interfaces. |

### Cluster 3: Intelligence & AI Engines
| Subsystem | Path | Function |
|:---|:---|:---|
| **Core AI Service** | `AI/AiService.php`, `ProviderRegistry.php` | Multi-provider AI interface, LMStudioProvider (`:1234`), FillMissingService. |
| **Heal & Self-Correction** | `AI/HealService.php`, `HealRepository.php` | Applies staged corrections from `data/corrections/` to master files with audit logging. |
| **Discovery & Search** | `Discovery/DiscoveryService.php`, `Search/SearchService.php` | Semantic facet filtering, catalog discovery indices. |
| **Cross-Linking & Scoring** | `CrossLink/CrossLinkService.php`, `Score/ScoreService.php` | Automated contextual internal linking, helmet safety scoring algorithms. |
| **Fitment & Compatibility** | `CompatibilityEngine.php`, `Comparison/` | Helmet ↔ Motorcycle and Helmet ↔ Accessory compatibility matrix resolution. |
| **Reviews & Ratings** | `Reviews/ReviewsService.php` | Editorial evaluation aggregation and SHARP/ECE benchmark reviews. |

### Cluster 4: SEO, Performance & Edge
| Subsystem | Path | Function |
|:---|:---|:---|
| **SEO Engines** | `Seo/SchemaService.php`, `IndexNowService.php` | Rich JSON-LD motorcycle schemas, instant Bing/Yandex IndexNow pings. |
| **Cloudflare & Edge** | `Cloudflare/CloudflareService.php` | Automated edge cache purge and CDN coordination. |
| **Cache & Health** | `Cache/`, `Health/HealthService.php` | Redis/transient caching wrappers, complete system health dashboard. |
| **Media Pipeline** | `Media/MediaEngine.php`, `HelmetImageEnrichmentService.php` | Vision AI asset analysis and automated image scraping. |

### Cluster 5: Admin & Operations
| Subsystem | Path | Function |
|:---|:---|:---|
| **Admin Panels** | `Admin/Admin.php`, `RevenueAdmin.php`, `AiAdmin.php` | WordPress admin menus, settings, and 21-marketplace StoreID tables. |
| **WP-CLI Engine** | `CLI/Commands.php` | All `wp helmetsan *` CLI operations. |
| **Scheduler & Sync** | `Scheduler/`, `Sync/SyncService.php` | Background WP-Cron tasks, GitHub JSON sync engine. |
| **Support & Logging** | `Support/Config.php`, `Logger.php` | Global configuration options, fallback tags, and structured logging. |

---

## 2. Theme Subsystem (`helmetsan-theme/`)

| Area | Path | Notes |
|:---|:---|:---|
| **Templates** | Root `.php` (`archive-helmet.php`, `single-helmet.php`, `single-accessory.php`, `page-comparison.php`) | GeneratePress-hybrid templates. |
| **Modular Parts** | `template-parts/helmet/` (`where-to-buy.php`, `quick-verdict.php`, `hero-header.php`) | Focused components; inspect these instead of full templates. |
| **Components** | `template-parts/components/` (`helmet-card.php`, `comparison-bar.php`, `country-selector-modal.php`) | Reusable UI widgets. |
| **Source Styles** | `assets/css/` (`components.css`, `pages.css`, `base.css`, `design-tokens.css`) | Vanilla CSS with design tokens. |
| **CSS Production Bundle** | `assets/css/helmetsan-bundle.min.css` | Compiled via `python3 scripts/bundle_and_minify_css.py`. Never edit directly. |
| **Client JavaScript** | `assets/js/` (`currency-selector.js`, `price-history.js`, `filters.js`) | Vanilla JS islands reading `window.helmetsan_geo_config`. |

---

## 3. Catalog & In-Memory RAM Tools (`data/` & `scripts/`)

| Tool | Command | Execution Time & Purpose |
|:---|:---|:---|
| **RAM Similarity Matcher** | `php scripts/in_memory_search_engine.php [id]` | **<66ms**: Vector similarity and alternative recommendations. |
| **RAM Analytics Matrix** | `php scripts/in_memory_analytics_engine.php` | **<300ms**: 4-pass RAM statistical analysis across 2,218 helmets. |
| **Recompile RAM Master Index** | `python3 scripts/build_in_memory_catalog_index.py` | **<1s**: Compiles `data/helmets_unified_master_memory_index.json` (3.5MB). |
| **Catalog Data Linter** | `python3 scripts/lint_helmet_data.py` | Fast catalog consistency and schema validation. |
| **Validation Bridge** | `php scripts/id-ai-validate.php [file]` | Validates single JSON files against `Validator.php`. |
| **LM Studio Health Check** | `bash scripts/check-lm-studio.sh` | Verifies local LM Studio endpoint (`http://127.0.0.1:1234/v1`). |
