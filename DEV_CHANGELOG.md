# Development Changelog

This file tracks **day-to-day development changes** and internal notes between tagged releases.  
High-level, user-facing release notes live in `CHANGELOG.md`.

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

