This repository uses structured AI workflows for development.

## Token Discipline (MANDATORY)

**Local First & Memory Enabled**:

- **Local AI**: Use LM Studio (`http://192.168.2.240:1234`) for routine WP-CLI tasks to save cloud tokens.
- **Memory MCP**: Use the `memory` server to store/recall project context across sessions (avoid re-reading).
- **Minimal Context**: For small tasks (single file, typo), do NOT load full workflows.

**For complex, exploratory, or multi-subsystem tasks**:
- Read `.agent/workflows/ai-optimizations.md` for full governance.
- Use `docs/architecture-map.md` to locate subsystems before broad searches.

## Quick Reference

**Project root (production):** `/var/www/helmetsan.com/public/`

**Key paths:**

| Area | Path |
|------|------|
| Plugin (business logic) | `helmetsan-core/includes/` |
| AI modules | `helmetsan-core/includes/AI/` |
| CLI commands | `helmetsan-core/includes/CLI/Commands.php` |
| Ingestion | `helmetsan-core/includes/Ingestion/` |
| Sync | `helmetsan-core/includes/Sync/` |
| Theme | `helmetsan-theme/` |
| JSON data | `data/` |
| Scripts | `scripts/` |

**Never modify:** `wp-admin/`, `wp-includes/`

**Terminology:** Seed = JSON array from `create_helmets_seed.php`. Ingest = `wp helmetsan ingest-seed`. SEO seed = `wp helmetsan seo seed`. Sync = GitHub pull/push of JSON. Full data-flow: `docs/data-flow.md`.

**Rules:**
- Minimal, localized changes over broad refactors (≤3 files, ≤200 lines).
- Edit existing services instead of creating new subsystems.
- No hardcoded API keys — use `getenv()` or `get_option()`.
- Use existing primitives (ingestion, sync, AI, scheduler) rather than custom infra.
- Full governance: `.agent/workflows/ai-optimizations.md`
