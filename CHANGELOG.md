# Changelog

This file tracks **released versions** of Helmetsan.  
For an in‑progress development log between releases, see `DEV_CHANGELOG.md`.

## Unreleased

- **Search (roadmap):** Helmet archive uses plugin `SearchService` for faceted search (single source of truth). `Plugin::getSearchService()` for theme; `docs/architecture-map.md` documents Search.
- **Search:** Faceted filter by **price_range** taxonomy (budget, mid-range, premium, luxury) in SearchService and helmet archive; archive filter panel adds “Price range” checkboxes alongside min–max price.
- **Search:** Faceted filters for **region** and **use_case** taxonomies in SearchService and helmet archive (filter panel + active chips).
- **Ingestion:** Main ingest path (ingestPath / ingestFiles) now dispatches by **`entity`** when payload has entity field: brand, motorcycle, safety_standard, dealer, distributor, comparison, recommendation (one file = one entity). Technology and commerce still use Import JSON only.
- **Fill-missing quality:** FillableFieldsConfig: helmet `use_case` has allowed_values (touring, racing, commuter, etc.); clearer Yoast field labels. FillMissingService: helmet context includes certification, use_case, region, feature_tag, price_range from taxonomies; allowed_values normalized (e.g. "dual sport" → "dual-sport"). ContextBuilder: brand and Yoast-specific hints in forFillField.

## 0.3.0 - 2026-03-07

- **Accessory validator:** `Validator::validateAccessorySchema()` and `validateAccessoryLogic()` for ingest and AI-generated data; CLI `wp helmetsan validate accessory --file=path`.
- **AI admin:** Generate accessories (preview) and Fill coverage report (per-field set/empty/%) under Helmetsan → AI; both wired to CLI (generate-accessories, fill-missing --report).
- **Health & Alerts:** Admin menu "Repo Health" renamed to "Health"; report includes validation/enrichment flags; AlertService Slack retry on 5xx/429; OPS_MANUAL Health & Alerts section.
- **API:** BrandController batch uses BrandService::upsertFromPayload (accepts string[] or object[] with title/id/profile); enrich validates brand ID and type; error responses use `{ error: true, message }`. PriceController validates helmet ID and uses same error shape.
- **Repository:** JsonRepository::listSubdirs() for discovering entity paths under data root.
- **Docs:** data-flow workflow 6 (AI-generated accessories), architecture blueprint (Health + AI menus), plugin-dev-guidebook see-also, COMMANDS_REFERENCE validate accessory, OPS_MANUAL Health & Alerts.
- **Agent workflow:** Token and discovery optimizations (dry run, interface-first, architecture map, stubbed TDD, CLI dry-run); `docs/architecture-map.md`; `AiServiceInterface`; gaps doc §1.2–1.4 marked implemented.

## 0.2.0 - 2026-02-23

- AI fill-missing Phase 2 refinements (stricter config, safer defaults, richer validation).
- Improved AI admin security (nonce handling) for quick actions.
- Google Analytics / GTM integration fixes (GA4 dataLayer format, Tag Assistant detection).
- Settings persistence hardening (per-tab save without wiping other options, tab restore after save).
- Single helmet page improvements (duplicate section guard, media/debug cleanup).
- Added AI governance and agent workflow docs (`AGENTS.md`, `.agent/workflows/ai-optimizations.md`).

## 0.1.0 - 2026-02-17

- Initial plugin and theme scaffolding.
- Data ingestion and sync modules.
- GitHub sync profiles and profile lock.
- Pull audit trail and scheduler integration.
- Brand command center and brand JSON workflows.
- Production readiness gate with weighted checks.
- Mac-style admin UI sprint (first pass).
