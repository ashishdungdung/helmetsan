# Scripts

Run **from repo root** unless noted. Paths and remote host are in **`config`** (source it from script dir). Do not put secrets in `config`; use environment variables.

## Environment variables

| Variable | Used by | Description |
|----------|--------|-------------|
| `DEPLOY_PASSWORD` | `deploy.sh`, `pull-data.sh` | SSH/rsync password when using expect-based rsync. Optional: set in `scripts/.env.deploy` (gitignored); `deploy.sh` sources it if present. If unset and SSH key works, deploy uses key auth. |
| `DRY_RUN` | `purge_duplicate_pages.php` | Set to `1` to list pages that would be deleted without deleting. |
| `HELMETSAN_PARALLEL_SECRET` | `parallel_seed.php` | Secret for REST API parallel seeding. |
| `HELMETSAN_API_URL` | `parallel_seed.php` | Optional; base URL for brands API. |

## Bash scripts

| Script | Run from | Description |
|--------|----------|-------------|
| `reseed.sh` | Repo root | Full pipeline: generate seed → deploy → ingest on server. Options: `--skip-deploy`, `--dry-run`, `--validate`, `--help`. |
| `deploy.sh` | Repo root | Deploy theme and plugin to production. Uses SSH key if available; else set `DEPLOY_PASSWORD` (e.g. in `scripts/.env.deploy`) and have `deploy-rsync.expect` for password auth. |
| `vps-recover-themes-plugins.sh` | **On the VPS** | Clean theme/plugin set: only GeneratePress + helmetsan-theme and Helmetsan Core + WooCommerce + Yoast. Removes all others, then configures theme and plugins together. See `docs/OPS_MANUAL.md`. |
| `vps-fix-duplicate-helmetsan-core.sh` | **On the VPS** | Fix duplicate “Helmetsan Core” entries and “Could not fully remove” on the Plugins page. Removes stray file, dedupes `active_plugins`, reactivates once. |
| `vps-fix-wp-content-permissions.sh` | **On the VPS** (root/sudo) | Fix “Could not create directory” when installing themes/plugins. Sets `wp-content` ownership to web user (www-data/nginx). |
| `pull-data.sh` | Repo root | Sync data from server to local `helmetsan-core/data` (requires `DEPLOY_PASSWORD` and `deploy-rsync.expect`). |
| `log-work.sh` | Repo root | Append a work log entry to `docs/WORK_LOG.md`. |

Expect scripts (`*.expect`) are **gitignored** (credentials). Create them locally for rsync-over-SSH if you use deploy/pull-data.

## PHP scripts

### Repo root (no WordPress)

| Script | Command | Description |
|--------|---------|-------------|
| `create_helmets_seed.php` | `php scripts/create_helmets_seed.php [--output=... \| --validate \| --stats \| --help]` | Generate or validate helmet seed JSON. |
| `fetch_logos.php` | `php scripts/fetch_logos.php` | Download brand logos from Clearbit to `helmetsan-core/data/logos`. |
| `update_dev_changelog.php` | `php scripts/update_dev_changelog.php` | Append recent git commits to `DEV_CHANGELOG.md`. |

### Server / WordPress context

Run from **WordPress public root** (`wp-load.php` in CWD) or via **`wp eval-file scripts/...`** on the server:

| Script | Description |
|--------|-------------|
| `seed_brands.php` | Seed brands and sideload logos (WP required). |
| `seed_accessory_categories.php` | Create/update accessory_category terms and fix page config. |
| `update_term_descriptions.php` | Update helmet_type term descriptions. |
| `update_navigation_links.php` | Point menu items to post type archives. |
| `enrich_global_metadata.php` | Update taxonomy and post excerpts. |
| `enrich_safety_standards.php` | Enrich safety_standard posts with links and text. |
| `setup_hub_pages.php` | Create or update hub pages and meta. |
| `cleanup_logos.php` | **Destructive.** Remove brand featured images (for re-seeding). |
| `purge_duplicate_pages.php` | **Destructive.** Delete listed pages by slug. Use `--dry-run` or `DRY_RUN=1` to preview. |

`enrich_brands_data.php` and `update_stats.php` are **eval-file only** (require `ABSPATH`).

## Python scripts

| Script | Run from | Description |
|--------|----------|-------------|
| `add_affiliate_links.py` | Repo root | Add/update `marketplace_links` in helmet JSONs under `data/`. Optional arg: data directory. |
| `generate_ai_helmets.py` | Repo root | Generate high-detail helmet JSON entries. |

## Config

**`config`** defines (do not put secrets here):

- `REMOTE_WP_PATH` — WordPress public path on server  
- `REMOTE_HOST` — SSH target (e.g. `root@helmetsan.com`)  
- `HOST`, `USER` — Used by deploy/pull  
- `REMOTE_PLUGIN_DATA_PATH` — Plugin data path on server  

Full command reference: **`docs/COMMANDS_REFERENCE.md`**.
