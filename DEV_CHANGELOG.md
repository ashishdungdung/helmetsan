# Development Changelog

This file tracks **day-to-day development changes** and internal notes between tagged releases.  
High-level, user-facing release notes live in `CHANGELOG.md`.

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

