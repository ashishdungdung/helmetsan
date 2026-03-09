# Changelog

This file tracks **released versions** of Helmetsan.  
For an in‑progress development log between releases, see `DEV_CHANGELOG.md`.

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
