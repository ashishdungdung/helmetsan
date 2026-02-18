# Helmetsan Core (Scaffold)

WordPress ERP plugin scaffold for Helmetsan.

## Install (local)
1. Copy `helmetsan-core` into `wp-content/plugins/`.
2. Activate `Helmetsan Core`.
3. Ensure `wp-content/uploads/helmetsan-data` exists for repository operations.

## CLI examples
```bash
wp helmetsan health --format=json
wp helmetsan seed --set=start-pack-v1
wp helmetsan ingest --path=products --batch-size=100 --dry-run
wp helmetsan ingest --path=products --batch-size=200 --limit=500
wp helmetsan sync pull
wp helmetsan validate schema --file=/absolute/path/to/file.json
wp helmetsan analytics smoke-test
wp helmetsan go-live checklist
wp helmetsan docs build-index
wp helmetsan retry-failed --limit=100 --batch-size=50
wp helmetsan retry-failed --limit=50 --batch-size=25 --dry-run
wp helmetsan import --file=/absolute/path/helmets.json --batch-size=100
wp helmetsan export --post-id=123 --out=/absolute/path/helmet-123.json
wp helmetsan export --entity=brand --post-id=55
wp helmetsan unlock-ingestion
wp helmetsan sync pull --limit=200 --dry-run
wp helmetsan sync pull --limit=200 --apply-brands
wp helmetsan sync pull --limit=200 --apply-helmets
wp helmetsan sync pull --limit=200 --apply-brands --apply-helmets
wp helmetsan sync pull --profile=pull+all --limit=200
wp helmetsan sync push --limit=200 --path=data/helmets
wp helmetsan sync push --mode=pr --pr-title=\"Helmetsan data sync\" --limit=100
wp helmetsan sync push --mode=pr --auto-merge --limit=50
wp helmetsan sync-logs --tail --limit=20 --format=table
wp helmetsan sync-logs --status=error --action=push --limit=50
wp helmetsan sync-logs --id=42
wp helmetsan sync-logs-cleanup --days=30
wp helmetsan ingest-logs-cleanup --days=30 --status=failed
wp helmetsan seo schema-check --limit=200 --format=table
wp helmetsan revenue report --days=30 --format=table
wp helmetsan analytics report --days=7 --format=table
wp helmetsan scheduler status
wp helmetsan scheduler run --task=sync_pull
wp helmetsan alerts test --title="Ping" --message="Alerts pipeline check"
wp helmetsan brand cascade --brand-id=12
wp helmetsan brand cascade --all
wp helmetsan woo-bridge sync --helmet-id=123
wp helmetsan woo-bridge sync --limit=100
wp helmetsan woo-bridge sync --limit=100 --dry-run
```

## GitHub Sync Setup
- Configure in `Helmetsan > Settings > GitHub Sync`:
  - `enabled`
  - `owner`
  - `repo`
  - `token`
  - `branch`
  - `remote_path`
  - `sync_json_only`
  - `sync_run_profile` (`pull-only`, `pull+brands`, `pull+all`)
  - `sync_profile_lock` (forces saved profile for scheduler/CLI/admin runs)
  - `push_mode` (`commit` or `pr`)
  - `pr_branch_prefix`
  - `pr_reuse_open`
  - `pr_auto_merge`
- Or set constants in `wp-config.php`:
  - `HELMETSAN_GITHUB_OWNER`
  - `HELMETSAN_GITHUB_REPO`
  - `HELMETSAN_GITHUB_TOKEN`
  - `HELMETSAN_GITHUB_BRANCH`
  - `HELMETSAN_GITHUB_REMOTE_PATH`

## Credit-Safe Build Rules
- Prefer `dry-run` before full ingestion.
- Process data in batches; avoid whole-repo passes for small edits.
- Use `--limit` for test runs and iterative rollout.
- Keep modules independent; avoid regenerating unchanged code paths.
- Default AI-heavy operations to manual approval and explicit execution.

## Ingestion Reliability
- Upsert uses `_helmet_unique_id` and `_source_hash` to skip unchanged records.
- Each entity upsert is wrapped in DB transaction boundaries.
- Ingestion outcomes are stored in `wp_helmetsan_ingest_logs`.
- `wp helmetsan health --format=json` includes ingestion log stats.
- Admin logs view is available in `Helmetsan > Ingestion` with status filter/search/pagination.
- Bulk retry is available in `Helmetsan > Ingestion` for selected `failed`/`rejected` rows.
- Concurrency lock prevents overlapping ingestion runs across Admin and CLI.

## Sync Efficiency
- GitHub push is diff-aware: unchanged files are skipped by comparing local Git blob SHA with remote tree SHA.
- Sync actions are audited in `wp_helmetsan_sync_logs` (pull/push mode, branch, counts, status, payload).
- Admin sync audit view is available at `Helmetsan > Sync Logs` with filters, pagination, and payload detail view.

## SEO Schema
- Product JSON-LD is injected automatically on single `helmet` pages.
- Schema audit command: `wp helmetsan seo schema-check` to identify missing required fields.

## Revenue Tracking
- Cloaked outbound links use `/go/{helmet-slug}` and are logged to `wp_helmetsan_clicks`.
- Revenue report command: `wp helmetsan revenue report`.

## Import/Export Admin
- Use `Helmetsan > Import/Export` for:
  - JSON import (file upload or absolute path, with dry-run and batch size)
    - auto-detects `helmet` vs `brand` records (via `entity` or `profile` payload)
  - JSON export by post ID (`helmet` or `brand`, optional output path)

## Brand JSON Sync
- Brand export defaults to repository path: `wp-content/uploads/helmetsan-data/brands/<id>.json`
- This is GitHub-sync ready with existing `wp helmetsan sync push` flow.

## Extended Entity Auto-Apply
- `pull+all` now auto-applies JSON datasets for:
  - `helmet`, `brand`, `accessory`, `motorcycle`, `safety_standard`
  - `dealer`, `distributor`, `comparison`, `recommendation`
  - commerce entities: `currency`, `marketplace`, `pricing`, `offer`
- Commerce records update helmet pricing/offer metas (`pricing_records_json`, `offers_json`, `best_offer_json`, `geo_pricing_json`).

## WooCommerce Bridge
- Admin page: `Helmetsan > Woo Bridge`
- Sync options:
  - single helmet by id
  - batch sync for helmets with `variants_json`
- Linkage/meta mapping:
  - helmet: `wc_product_id`, `wc_variation_map_json`
  - product: `_helmet_post_id`, `_helmet_unique_id`, `_product_details_json`, `_part_numbers_json`, `_sizing_fit_json`, `_related_videos_json`
  - variation: `_helmet_variant_id`, `_mfr_part_number`, `_geo_pricing_json`

## Analytics Instrumentation
- Frontend event tracking (when enabled):
  - `outbound_click`
  - `internal_search`
- Events are sent to `helmetsan/v1/event` and stored in `wp_helmetsan_analytics_events`.
- CLI report: `wp helmetsan analytics report`.

## Scheduler
- Configure at `Helmetsan > Settings > Scheduler`.
- Scheduled tasks:
  - sync pull
  - retry failed ingestion
  - log cleanup
  - health snapshot
- CLI control:
  - `wp helmetsan scheduler status`
  - `wp helmetsan scheduler run --task=<task>`
- Brand auto-apply on scheduled pull:
  - Controlled by GitHub `sync_run_profile` (shared across scheduler, CLI, and admin pull).

## Pull Audit Trail
- Every pull sync log payload includes:
  - `profile_saved`
  - `profile_requested`
  - `profile_source` (`saved`, `override`, `locked_saved`)
  - `profile_locked`
  - `audit` (`source`, and admin user identity when triggered from wp-admin)

## Production Readiness Gate
- Go Live tab now computes an objective launch gate:
  - weighted score (0-100)
  - pass/fail threshold (80)
  - critical blockers list
  - per-check status/details
- CLI:
  - `wp helmetsan go-live checklist` returns full gate report JSON.

## Alerts
- Configure at `Helmetsan > Settings > Alerts`.
- Channels:
  - Email (`to_email`, `subject_prefix`)
  - Slack webhook (`slack_webhook_url`)
- Trigger toggles:
  - `alert_on_sync_error`
  - `alert_on_ingest_error`
  - `alert_on_health_warning`
- CLI smoke test:
  - `wp helmetsan alerts test`

## Brand Command Center
- Deep brand profile fields are managed on each `brand` edit screen:
  - origin country
  - warranty terms
  - support URL/email
  - manufacturing ethos
  - distributor regions
  - size chart JSON
- Cascade brand data to all linked helmets:
  - Admin: `Helmetsan > Brands` (`Cascade` / `Cascade All Brands`)
  - CLI: `wp helmetsan brand cascade --brand-id=<id>` or `--all`
