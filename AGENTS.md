This repository uses structured AI workflows for development.

## Token Discipline (MANDATORY)

**Local First & Memory Enabled**:

- **Local AI**: Use LM Studio (`http://192.168.2.74:1234`) for routine WP-CLI tasks to save cloud tokens.
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

## Deploy Workflow (CRITICAL — read before every deploy)

**The deploy script always builds fresh zips from source. Never pre-build manually.**

```bash
# Full deploy (theme + plugin) — takes ~30s
bash deploy.sh

# Theme-only deploy (faster, use after CSS/PHP/template changes)
bash deploy.sh --theme-only

# Plugin-only deploy (use after helmetsan-core PHP changes)
bash deploy.sh --plugin-only
```

The script auto-handles: build zips → upload → extract → set permissions → clear all caches → health check.

**Cache clear (if you ever need it manually):**
```bash
ssh root@31.70.136.154 "rm -rf /var/cache/nginx/microcache/* && nginx -s reload"
cd /var/www/helmetsan.com/public && wp cache flush --allow-root && wp transient delete --all --allow-root
```

| Gotcha | Root Cause | Fix |
|--------|-----------|-----|
| Theme/CSS reverts after deploy | `dist/` zips are stale (months old). Old script used pre-built artifacts. | `bash deploy.sh` now ALWAYS builds fresh from source first. Never run old `scp dist/*.zip` pattern. |
| Active filter chips not showing | `$active_chips` typo — correct variable is `$activeChips` in `archive-helmet.php` | Fixed June 2026. Variable is now `$activeChips` everywhere. |
| Homepage shows German/Chinese | Polylang browser-language detection was enabled, Nginx cached the 302 redirect | Fixed: `browser => 0` in Polylang options. Never re-enable. |
| Child theme assets 404 | `get_template_directory_uri()` returns parent theme path | Always use `get_stylesheet_directory_uri()` for child theme assets. |
| OPcache serves old PHP after deploy | Nginx reload doesn't flush PHP-FPM OPcache | `deploy.sh` now runs `wp cache flush` which triggers OPcache reset via WP. |
| Homepage returns 404 | `show_on_front` was set to `page` pointing to a deleted/trashed `page_on_front` post ID | Set `show_on_front => posts` so WordPress renders `front-page.php`. |
| GSC API 403 on Sitemaps/Inspection | Service account was given "Full" instead of "Owner" permission in GSC | Set Service Account permission to "Owner" in Google Search Console Settings. |
| AdSense "Low Value Content" penalty | Indexing thousands of thin child SKU variants (`post_parent > 0`) or stub CPTs | Keep `AutoSeoObserver.php` active: child SKUs must be `noindex, follow` + canonical to parent. |

## SEO & AdSense Architecture

- **Master Strategy**: `docs/adsense-approval-strategy.md`
- **Standard Operating Playbook**: `docs/WEBSITE_INDEXATION_AND_ADSENSE_PLAYBOOK.md`
- **GSC Automation Tool**: `scripts/gsc_manager.py` (uses Google Cloud service account JSON in root)
- **Deep Quality Audit Tool**: `scripts/deep_audit.py`

