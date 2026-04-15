# Changelog

This file tracks **released versions** of Helmetsan.  
For an in‑progress development log between releases, see `DEV_CHANGELOG.md`.

## Unreleased (0.4.0)
- **Bulk Data Ingestion**: Deployed and executed ingestion of 2,100+ enriched helmets with 11,500+ record updates (parents/variants).
- **Core Improvements**: Implemented `--force` flag in `ingest` and `ingest-seed` commands to bypass hash-skipping.
- **WP-CLI Concurrency**: Fixed a major regression in parallel ingestion by propagating `--path` and `--allow-root` to sub-processes.
- **Enrichment**: Full catalog-wide enrichment of technical specifications (Warranty, Strap Type, Visor/Liner Features).

- **AI Token Optimization:** Created `.geminiignore` to exclude `vendor/` and `data/` from indexing (~70% context reduction). Slimmed `AGENTS.md` and streamlined governance workflows. Reduced system prompt overhead.
- **Local AI First:** Deep integration with **LM Studio** for local inference (M4 Pro). Added `HELMETSAN_LMSTUDIO_BASE_URL` constant override for secure remote access.
- **Networking:** Added **Cloudflare Tunnel** guide for exposing local AI to production servers without port forwarding.
- **Performance:** Disabled unused IDE plugins (StitchMCP, etc.) and optimized VS Code filters.
- **Static Analysis:** Installed **PHPStan** (level 3) with WordPress stubs and baseline for local budget-friendly code quality checks.
- **Governance:** Added **Memory MCP** for persistent project context across agent sessions.

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
