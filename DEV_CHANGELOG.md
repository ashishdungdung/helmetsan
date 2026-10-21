# Development Changelog

This file tracks **day-to-day development changes** and internal notes between tagged releases.  
High-level, user-facing release notes live in `CHANGELOG.md`.

## 2026-10-22 — Cloudflare Edge Worker & Infrastructure Overhaul

### Cloudflare Workers (edge-assembly-worker) — PRIMARY WORKER
- **Added `EdgeAnalytics` class**: Structured event emission to Cloudflare Analytics Engine (dashboard-gated binding `HELMETSAN_EDGE_ANALYTICS`) with console fallback. Events: `cache_hit`, `cache_miss`, `cache_bypass`, `purge`, `esi_render`, `error`, `security_block`. Each event carries `colo`, `route`, `country`, `lang`, `event_type`, optional `value`.
- **Added `X-Edge-Worker: helmetsan-edge-assembly-worker/2.2.0` header** on all responses (HIT, MISS, BYPASS, purge auth, probe).
- **Added `WORKER_VERSION = '2.2.0'` constant** and `EDGE_WORKER_HEADER` template.
- **Cache-key versioning**: `__edge_ver={CACHE_VERSION}` added to ESI cache keys (mega menu, stats, exchange rates) so version bumps atomically bust all edge cache entries.
- **Edge-cache API responses**: `scripts/test_all_workers.cjs` — `isDataApiFormatRequest()` detects `?format=json|md` and caches those API responses at edge instead of bypassing. Added `isAjaxFilter` to cache `wp-admin/admin-ajax.php?action=helmetsan_ajax_filter` GET responses.
- **Edge JSON-LD injection**: New `JsonLdHandler` class injects Organization `application/ld+json` schema into `<head>` of every cached page.
- **Cache-tag invalidation**: `storeCacheTags()` writes tag→URL mappings into KV namespace `EDGE_TAG_INDEX` on every `cache.put()`. Purge endpoint accepts `body.tag` / `body.tags` to purge all cached URLs under a tag.
- **Secret hygiene**: Removed hardcoded `'helmetsan-edge-purge-secret-2026'` fallback; `expectedSecret` now reads `env?.PURGE_SECRET` only (set via `wrangler secret put`).
- **`wrangler.toml`**: Pinned `account_id`, `compatibility_date = "2025-01-01"`, `preview_urls = false`, added `[[kv_namespaces]]` binding for `EDGE_TAG_INDEX` (ID: `d651333514b44722831d957b582507ec`).
- **Single-flight cache MISS protection**: New `inFlightFetch` Map deduplicates concurrent origin fetches for the same cache key, preventing thundering herd on popular pages.
- **Deployed version**: `340c45c0-4bb6-4f73-81a4-a089b26c4217` on route `*helmetsan.com/*`.

### Cloudflare Workers (edge-cache-worker) — DELETED
- Entire worker directory removed (`wrangler.toml`, `src/index.js`, `package.json`). Was idle (no route), duplicated assembly worker caching, added operational debt.

### Cloudflare Workers (image-resizing-worker)
- Added `account_id`, `compatibility_date = "2025-01-01"`, `R2_PUBLIC_DOMAIN = "assets.helmetsan.com"` var.
- Deployed version `742e9bf8-479a-4101-8cef-c4d8067c1557` as standalone worker.
- SSRF shield verified (403 on internal metadata hostnames).
- PHP `CloudflareR2Service` reads `HELMETSAN_R2_IMAGE_RESIZER_URL` env var.

### Cloudflare Workers (ingestion-worker)
- Added `account_id`, `compatibility_date = "2025-01-01"`, `[[queues.producers]]` binding `INGEST_QUEUE` for `helmetsan-ingest-queue`, `R2_PUBLIC_URL`, `WORDPRESS_REST_URL` vars.
- Deployed version `59d73139-efd8-46ed-a9e5-ad962111a4f8` as producer + consumer for `helmetsan-ingest-queue`.
- Queue `helmetsan-ingest-queue` created via `wrangler queues create`.
- R2 bucket `helmetsan` bound as `ASSETS_BUCKET`.

### Cloudflare Workers (reviews-worker)
- Added `account_id`, `compatibility_date = "2025-01-01"`, `[[d1_databases]]` binding `DB` for `helmetsan_reviews`.
- Deployed version `9e791c1f-bc4b-4b53-b6b7-5859a9847cff` as standalone worker.
- D1 database `helmetsan_reviews` created (region APAC), schema applied (reviews + review_votes tables).
- `WORDPRESS_WEBHOOK_SECRET` and `TURNSTILE_SECRET_KEY` uploaded as wrangler secrets.

### WordPress Plugin (helmetsan-core)
- **`CloudflareCacheService.php`**: Added `computePostTags(int $postId): array` — computes edge cache tags (`helmetsan`, `public`, `helmet`, `helmet-N`, `brand-X`, `catalog`, `helmets`, `home`) mirroring `EdgeCacheService`. Added `purgeTags(array $tags, bool $blocking)` — POSTs `['tags' => [...]]` to worker purge endpoint for tag-based `caches.default` invalidation. `purgeUrls()` now also calls `purgeTags()`.
- **`CacheWarmingService.php`**: `onProductUpdated()` now calls `CloudflareCacheService::computePostTags()` + `purgeTags()` in addition to URL purge + pre-warming.
- **`ObjectCacheService.php`**: `onPriceUpdated(int $postId)` now accepts post ID, purges edge cache for affected product URL. Triggered by `helmetsan_price_updated` action.
- **`PriceHistory.php`**: `record()` now calls `do_action('helmetsan_price_updated', $postId)` after successful insert.
- **`CloudflareR2Service.php`**: Added `deleteFile(string $r2Key): bool|WP_Error` — deletes object from R2 bucket. Added `HELMETSAN_R2_IMAGE_RESIZER_URL` constant support for resizer URL.
- **`Config.php`**: `dataRoot()` now respects `HELMETSAN_DATA_ROOT` constant. `r2_image_resizing_enabled` default changed `false` → `true`.
- **`Plugin.php`**: Added `TurnstileFormProtection` instantiation + `register()` call. Added `registerR2ImageCleanup()` + `deleteR2ImagesForPost(int $postId)` hooked to `wp_trash_post` and `before_delete_post` — deletes R2 objects (`_asset_r2_url` meta) when posts are trashed/deleted.

### WordPress Plugin — NEW FILE: `TurnstileFormProtection.php`
- Adds Cloudflare Turnstile widget to `wp-login.php` login form and WordPress comment form.
- Verifies Turnstile tokens via `authenticate` filter (login) and `preprocess_comment` filter (comments).
- Fail-open when not configured or secret missing — never locks out users.
- Logged-in users bypass comment Turnstile.
- Loads `challenges.cloudflare.com/turnstile/v0/api.js?render=explicit` on login and comment-form pages.

### WordPress Theme (helmetsan-theme)
- `single-motorcycle.php`: Added `HELMETSAN_DATA_ROOT` constant support as first candidate path for motorcycle JSON files, unifying theme and plugin data roots.

### Tests
- `scripts/test_all_workers.cjs`: Renamed from `.js` (CommonJS breakage under parent `type: module`). Removed 5 deleted edge-cache-worker test cases. 19/19 tests passing.

### Server / Infrastructure
- **PHP-FPM Opcache**: Created `/etc/php/8.5/fpm/conf.d/opcache.ini` — `memory_consumption=256`, `max_accelerated_files=20000`, `interned_strings_buffer=32`, `revalidate_freq=60`, `compatibility_date`-aligned. PHP-FPM restarted. Verified live: Cache hits 150,751, misses 4,732 (~97% hit rate), 4,731 cached scripts.
- **Swap**: Expanded from 2 GB to 4 GB (`/swapfile2`, added to `/etc/fstab`). Was 95% full (91 MB free), now ~47% full (2.1 GB free).
- **PHP-FPM `memory_limit`**: All 5 pools (`helmetsan_com`, `ashishdungdung_com`, `androidemulator_org`, `dazestack_com`, `bootstrap`) changed from `2048M` → `512M`. `ashishdungdung_com` `max_children` reduced 25 → 12.
- **MySQL**: `innodb_buffer_pool_size` reduced 7,965 MB → 3,072 MB (actual usage only 2.2 GB / 28.6% pool utilization). MariaDB restarted.
- **Live site**: All URLs return HTTP 200. `cf-edge-cache: HIT`, `x-edge-worker` header present.

### Credentials (NOT committed)
- Cloudflare API token extracted from production `wp-config.php` to temp file (chmod 600), destroyed after each use.
- `HELMETSAN_EDGE_PURGE_SECRET`, `HELMETSAN_CLOUDFLARE_API_TOKEN`, `HELMETSAN_CLOUDFLARE_ACCOUNT_ID`, `HELMETSAN_CLOUDFLARE_ZONE_ID` all live in production `wp-config.php` only — never in git.
- `HELMETSAN_WEBHOOK_SECRET` added to production `wp-config.php`.
- `PURGE_SECRET` uploaded to Cloudflare as worker secret (not in git).

## 2026-08-24

- **Google AdSense "Low Value Content" Remediation & Strategy:**
  - Diagnosed root cause of AdSense rejection: mass programmatic indexing of >5,000 thin child variant SKUs (`post_parent > 0`), thin CPTs (`dealer`, `distributor`, `comparison`), and empty taxonomy archives.
  - Implemented automated Yoast and Core XML sitemap pruning and dynamic `X-Robots-Tag: noindex, follow` header injection in `helmetsan-core/includes/Seo/AutoSeoObserver.php`.
  - Added dynamic canonical consolidation routing all child SKU URLs back to primary parent helmet models (`post_parent === 0`).
  - Authored and seeded **15 authoritative, long-form editorial guides** (800–2,000 words each) into WordPress under `/blog/` (`post-sitemap.xml`).
  - Created structured theme templates: `helmetsan-theme/single.php` (for guides with author/trust boxes) and `helmetsan-theme/home.php` (for the `/blog/` grid).
- **Essential Trust & Legal Pages Polish:**
  - Polished `page-about.php` with complete 4-pillar data methodology and Editorial Board credentials.
  - Polished `page-contact.php` with direct departmental routing emails (`contact@`, `editorial@`, `corrections@`), operating entity (*Ash Digital Services*), and 24–48h response SLA.
  - Seeded compliant content for Privacy Policy (GDPR/CCPA + AdSense DART cookies), FTC Affiliate Disclosure, Terms of Use, and Safety Notice.
- **Google Search Console API Direct Integration:**
  - Created automated GSC management tool `scripts/gsc_manager.py` using Google Cloud service accounts.
  - Verified programmatic authentication (`sc-domain:helmetsan.com`), submitted fresh sitemaps with 0 errors, and performed real-time URL inspections via API.
- **Deep Quality Audit & Critical Fixes:**
  - Built `scripts/deep_audit.py` for automated multi-point crawling (HTTP status, robots headers, canonical tags, word counts, schema).
  - Fixed critical bug: homepage returning 404 because `page_on_front` was pointing to a deleted stub; updated `show_on_front` to `posts` to restore `front-page.php` to HTTP 200 OK.
- **Cross-Project Playbook Distribution:**
  - Documented `WEBSITE_INDEXATION_AND_ADSENSE_PLAYBOOK.md` and distributed it along with `gsc_manager.py` across `Helmetsan`, `the-modern-theme`, `Dazestack`, and `AndroidEmulator`.

## 2026-03-28


- **Token Discipline & Context Optimization:**
  - Created `.geminiignore` to skip `vendor/` and `data/` (~70% context reduction).
  - Slimmed `AGENTS.md` and streamlined governance workflows.
  - Disabled `StitchMCP` in `mcp_config.json` to reduce tool definition overhead.
  - Added **Memory MCP** for persistent agent knowledge across sessions.
- **Local AI (LM Studio):**
  - Configured `LMStudioProvider` as the primary "Local & Free" provider.
  - Added `HELMETSAN_LMSTUDIO_BASE_URL` constant override in `ProviderRegistry` for secure tunneling.
  - Created Cloudflare Tunnel walkthrough for exposing local models to production.
- **Local Analysis:**
  - Installed **PHPStan** (level 3) with WordPress stubs and baseline.
  - Established `phpstan.neon` and `phpstan-baseline.neon` to suppress existing 309 errors and only flag new issues.
- **Cleanup:**
  - Removed redundant scripts: `update_dev_changelog.php`, `generate_ai_helmets.py`.
  - Merged `enrichment-process.md` into `ai-module.md` to simplify documentation.

## 2026-03-07

- **Agent / token optimizations:** Extended `.agent/workflows/ai-optimizations.md` with §8 (dry run before exploration, interface-first context, architecture map first, stubbed TDD, CLI dry-run for new data-mutation scripts). Added `docs/architecture-map.md` and referenced it from AGENTS.md. Introduced `AiServiceInterface`; `AiService` implements it so callers can type-hint the interface.
- **Gaps doc:** Marked ingestion items §1.2 (helmet region/use_case/price_range), §1.3 (comparison region), §1.4 (recommendation region) as implemented; code already set these taxonomies at upsert.
- **0.3.0 release:** Bumped plugin version to 0.3.0; cut CHANGELOG 0.3.0 section. OPS_MANUAL: added "Release / tagging" (version, changelog, smoke test, tag/push, deploy).
- **Roadmap – Search:** Wired helmet archive to `SearchService`: archive calls `parseParams($_GET)` and `buildQueryArgs($parsed)`, override posts_per_page 40. Added `Plugin::getSearchService()` for theme; fallback when plugin unavailable. Architecture map: Search row.
- **Search – price_range:** SearchService parseParams/buildQueryArgs and helmet archive now support `price_range[]` taxonomy filter (budget, mid-range, premium, luxury). Archive filter panel: “Price range” checkboxes; “Price (min–max)” kept for numeric range.
- **Search – region & use_case:** SearchService and helmet archive support `region[]` and `use_case[]` taxonomy filters; archive filter panel adds Region and Use case sections with checkboxes and chips.
- **Ingestion – entity dispatch:** IngestionService detects `entity` (or `profile` for brand) and dispatches to BrandService, MotorcycleService, SafetyStandardService, DealerService, DistributorService, ComparisonService, RecommendationService when running ingest path; one JSON file = one entity. Plugin wires these services into IngestionService. Gaps doc §1.1 and summary table updated.
- **Fill-missing quality & coverage:** FillableFieldsConfig: helmet use_case with allowed_values (touring, racing, commuter, adventure, track, daily, off-road, sport, cruising, dual-sport, motocross, street); Yoast labels clarified. FillMissingService: gatherExistingData for helmet now includes certification, use_case, region, feature_tag, price_range from taxonomies; sanitizeAndValidate normalizes slug-like values (spaces → hyphens) for allowed_values. ContextBuilder: forFillField hints for brand (factual) and Yoast (SEO, product name).

## 2026-02-23

- Refined AI fill-missing workflows and configuration (Phase 2):
  - Safer defaults and stricter field config to avoid over-writing curated data.
  - Better validation and context building for helmet/brand/accessory enrichment.
- Hardened AI admin quick actions:
  - Fixed nonce handling so security checks pass reliably.
  - Reduced chances of accidental replays or unauthorized calls.
- Analytics and tracking:
  - Cleaned up GA4/GTM integration, including dataLayer event structure.
  - Added defensive checks so tracking only runs when IDs and settings are valid.
- Settings UX:
  - Ensured saving one settings tab does not reset other tabs.
  - Preserved the active tab after save/redirect for a smoother admin experience.
- Frontend:
  - Prevented duplicate main content blocks on certain `helmet` single pages.
  - Removed leftover debug logging around helmet galleries.
- Governance / agents:
  - Introduced `AGENTS.md` and `.agent/workflows/ai-optimizations.md` as the primary AI and agent governance docs for this repo.

## 2026-02-17

- Established initial Helmetsan plugin/theme scaffold and ingestion/sync pipeline.
- Implemented core CLI commands for health, seed, ingest, sync, SEO schema checks, revenue, analytics, scheduler, and Woo bridge.
- Wired up ingestion logs, sync logs, and go-live checklist services.
- Added initial Mac-style admin UI and dashboards.

