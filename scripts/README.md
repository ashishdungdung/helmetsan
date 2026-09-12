# Helmetsan Operational Scripts

Core operational scripts for deployment, synchronization, data generation, and local AI audits. Run from the repository root unless specified.

## Core Deployment & Sync Tools

| Script | Type | Description |
| :--- | :--- | :--- |
| `deploy.sh` | Bash | Production deploy (builds fresh zips from source, uploads, sets permissions, clears caches). |
| `sync_data.sh` | Bash | Data synchronization engine between local workspace and production VPS. |
| `reseed.sh` | Bash | Full ingestion pipeline: generate seed JSON → deploy → ingest into WordPress. |
| `restore_to_server.sh` | Bash | Disaster recovery & database restore utility for the production server. |
| `pull-data.sh` | Bash | Pulls latest data files from server to local workspace. |
| `check-lm-studio.sh` | Bash | Verifies local LM Studio endpoint (`192.168.2.74:1234`) for zero-token AI tasks. |

## Core Data & AI Engines

| Script | Command | Description |
| :--- | :--- | :--- |
| `create_helmets_seed.php` | `php scripts/create_helmets_seed.php` | Compiles single/multi-variant helmet schemas into master ingestion seed. |
| `unified_deep_audit_and_enrich.php` | `php scripts/unified_deep_audit_and_enrich.php` | Unified master audit and multi-field enrichment engine. |
| `local_llm_deep_audit.php` | `php scripts/local_llm_deep_audit.php` | Local LLM-powered helmet data audit via LM Studio. |
| `local_llm_fix_and_enrich.php` | `php scripts/local_llm_fix_and_enrich.php` | Automated repair and enrichment using local AI consensus. |
| `media_draw_things_api.php` | `php scripts/media_draw_things_api.php` | Local DrawThings image generation runner. |
| `media_pollinations_batch.php` | `php scripts/media_pollinations_batch.php` | Fallback batch image generator via Pollinations. |

## VPS Recovery Utilities (`scripts/vps-*.sh`)

Run directly on the VPS host:
* `vps-recover-themes-plugins.sh`: Restores baseline clean WordPress theme and plugin stack.
* `vps-fix-duplicate-helmetsan-core.sh`: De-duplicates active plugin entries.
* `vps-fix-wp-content-permissions.sh`: Fixes ownership and permissions for `wp-content`.

## Archived Scripts (`scripts/archive/`)

Historical, one-off database migrations, and legacy version-specific enrichment scripts (v1–v4) are safely preserved in [`scripts/archive/`](./archive/) to avoid search pollution and token context bloat.
