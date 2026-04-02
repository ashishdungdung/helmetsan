---
description: Full AI governance — ATEP, guardrails, token optimization. Load only for complex/exploratory tasks.
---

# Helmetsan AI Governance

> **Token budget rule**: Do NOT load this file for trivial tasks (typo, single file, one parameter). Use `.ai-context.md` or `AGENTS.md` quick reference instead.

## Runtime Context

- `PROJECT_ROOT  = /var/www/helmetsan.com/public/`
- `PLUGIN_ROOT   = PROJECT_ROOT/wp-content/plugins/helmetsan-core/`
- `THEME_ROOT    = PROJECT_ROOT/wp-content/themes/helmetsan-theme/`
- `DATA_ROOT     = wp-content/uploads/helmetsan-data/` (via `Config`)

## Agent Task Execution Protocol (ATEP)

1. **Understand** — Parse objective, identify affected subsystem (AI / Ingestion / Sync / Theme / CLI), assess risk (low/med/high).
2. **Establish context** — Confirm root path. Locate modules via `docs/architecture-map.md`.
3. **Discover existing code** — Search before adding. Extend existing services (FillMissingService, IngestionService, SyncService, etc.).
4. **Validate paths** — Confirm files exist. Use `plugin_dir_path()`, `Config` helpers, `get_template_directory()`.
5. **Plan minimal change** — Smallest modification possible. Single service/template scope.
6. **Execute** — Maintain formatting, use existing abstractions (Config, Logger, CLI patterns).
7. **Verify** — Re-read modified files, check syntax.
8. **Prevent duplicates** — Track completed actions, don't repeat operations.
9. **Validate impact** — For ingestion/sync: consider log tables, prefer `--dry-run`/`--limit`.
10. **Confirm** — Summarize what changed, where, and any follow-up steps.

## Autonomous Change Guardrails (ACG)

| Rule | Constraint |
|------|-----------|
| Minimal change | Targeted patches over broad refactors |
| Scope containment | Stay within the task's subsystem |
| File limits | ≤ 3 files, ≤ 200 lines unless instructed otherwise |
| Protected files | `wp-config.php`, `.env`, `docker/*`, `deploy*`, lock files — need explicit approval |
| Infra safety | Backup → validate config → reload only with user approval |
| DB protection | No `DROP`/`ALTER`/unscoped `DELETE` without approval. Use WP core functions. |
| AI/API safety | No hardcoded keys. Long tasks → async (Cron/Scheduler/CLI). Handle 429s with backoff. Log token usage. |
| Dependencies | No new packages unless explicitly requested |
| Failure handling | 3 retries max, then reassess |

## Directory Roles

**Plugin (`helmetsan-core/includes/`):**

| Dir | Purpose |
|-----|---------|
| `Core/` | Plugin bootstrap, DI |
| `CPT/` | Post types, meta registration |
| `AI/` | LLM integrations, providers, prompts, fill-missing |
| `Ingestion/` | JSON → WordPress pipelines |
| `Sync/` + `Data/` | GitHub sync |
| `Scheduler/` | Background tasks |
| `Seo/` | Schema, AI SEO descriptions |
| `CLI/Commands.php` | All WP-CLI commands |
| `Support/` | Config, Logger, helpers |
| `API/` | REST controllers |

**Theme (`helmetsan-theme/`):** Templates (root PHP, `template-parts/`), `assets/` (CSS/JS), `inc/` (hooks).

**Data:** `data/` (JSON catalogs), `scripts/` (deployment/enrichment).

## WordPress Rules

- Use WP APIs: `add_action`, `add_filter`, `WP_Query`, `wp_remote_post`, `wp_insert_post`
- Sanitize: `sanitize_text_field()`, `absint()`, `sanitize_key()`
- Escape: `esc_html()`, `esc_attr()`, `esc_url()`, `wp_kses_post()`
- Forms: `wp_nonce_field()` + `check_admin_referer()`
- Never modify `wp-admin/` or `wp-includes/`

## Token Optimization

- **Search narrow**: File-scoped grep when you know the subsystem.
- **Read partial**: Line ranges, not full files.
- **Outline-first**: Read interface/first ~80 lines before full implementation.
- **Architecture map**: `docs/architecture-map.md` — read before broad `find`/`grep` on exploratory tasks.
- **New data-mutating CLI**: Must support `--dry-run`. New scripts default to dry-run.

## Capability Matrix

| Role | Code | Theme/Plugin | AI | Ingestion/Sync | Infra | Deps |
|------|------|-------------|-----|----------------|-------|------|
| Builder | ✅ | ✅ | ✅ | ✅ (guarded) | ❌ | Limited |
| Refactor | ✅ | ✅ | ✅ | ✅ (guarded) | ❌ | ❌ |
| Reviewer | Read | Read | Read | Read | Read | Read |
