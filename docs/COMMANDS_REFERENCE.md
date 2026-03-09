# Helmetsan — Commands Reference

A structured guide to all commands: **where they run**, **what they do**, and **how they fit into each process**. Use this when you need to deploy, ingest, enrich, or maintain the catalog.

---

## Table of contents

1. [Where commands run (locations)](#1-where-commands-run-locations)
2. [Key processes (overview)](#2-key-processes-overview)
3. [Scripts (run from your machine / repo)](#3-scripts-run-from-your-machine--repo)
4. [WP-CLI commands (run on the server)](#4-wp-cli-commands-run-on-the-server)
5. [Quick reference by task](#5-quick-reference-by-task)

---

## 1. Where commands run (locations)

### Your machine (local / repo)

- **Repo root**  
  The project directory on your computer (e.g. `~/Documents/Helmetsan`).  
  All **scripts** (reseed, deploy, seed generator, pull-data, etc.) are intended to be run from here so that paths like `scripts/config`, `helmetsan-core/seed-data/`, and `data/` resolve correctly.

### Server (production)

These paths are defined in **`scripts/config`** and used by deploy and SSH.

| Purpose | Path on server |
|--------|-----------------|
| **WordPress root** (web root, where `wp` runs) | `/var/www/helmetsan.com/public` |
| **Theme** | `/var/www/helmetsan.com/public/wp-content/themes/helmetsan-theme` |
| **Plugin** | `/var/www/helmetsan.com/public/wp-content/plugins/helmetsan-core` |
| **Plugin data** (JSON catalogs on server) | `/var/www/helmetsan.com/public/wp-content/plugins/helmetsan-core/data` |
| **Seed file** (after deploy) | `/var/www/helmetsan.com/public/wp-content/plugins/helmetsan-core/seed-data/helmets_seed.json` |

**How to run WP-CLI on the server**

- **SSH from your machine** (recommended for one-off commands):
  ```bash
  ssh root@helmetsan.com "wp --path=/var/www/helmetsan.com/public <command> --allow-root"
  ```
- **Logged in on the server** (e.g. over SSH):
  ```bash
  cd /var/www/helmetsan.com/public
  wp <command> --allow-root
  ```

In both cases, `<command>` is a `wp helmetsan ...` command. The `--path` (or working directory) must be the **WordPress root** above so that the plugin and data paths are correct.

---

## 2. Key processes (overview)

| Process | What it does | Where it runs |
|--------|---------------|----------------|
| **Reseed (full pipeline)** | Generate helmet seed JSON → deploy theme/plugin (and seed file) to server → run ingest on server. | Scripts from **repo root**; ingest runs **on server** (via script over SSH). |
| **Deploy only** | Sync theme and plugin (and optionally ads.txt) to production. | **Repo root** (`./scripts/deploy.sh`). |
| **Ingest** | Read seed JSON or per-file JSON and create/update helmet, brand, or accessory posts in WordPress. | **On server** (WP-CLI in WordPress root). |
| **Enrichment** | After ingest: fill missing meta (AI), seed SEO (Yoast), then cross-link. Optional scheduler. | **On server** (WP-CLI). |
| **Sync (GitHub)** | Pull JSON from GitHub into plugin data, or push local JSON to GitHub. | **On server** (WP-CLI). |
| **Data pull** | Copy plugin data from server back to your local repo. | **Repo root** (`./scripts/pull-data.sh`). |

---

## 3. Scripts (run from your machine / repo)

**Location:** Run every command in this section from the **repo root** (e.g. `~/Documents/Helmetsan`).  
Scripts read **`scripts/config`** for server host and paths; do not put secrets in `config` (use environment variables; see `scripts/README.md`).

---

### 3.1 Reseed pipeline (`reseed.sh`)

**Purpose:** One command to regenerate the helmet catalog and get it onto the server and into the database.

**Process:**

1. Generate seed JSON from the built-in data (or `--source-json`).
2. Validate the seed (IDs, structure).
3. Optionally deploy theme and plugin to the server (skipped with `--skip-deploy`).
4. Run ingestion on the server (create/update helmet posts from the seed).

| Command | When to use it |
|--------|-----------------|
| `./scripts/reseed.sh` | Full run: generate → deploy → ingest. |
| `./scripts/reseed.sh --skip-deploy` | Seed is already on server; only generate locally + run ingest on server. |
| `./scripts/reseed.sh --dry-run` | Generate + deploy; on server, run ingest in **dry-run** (no DB writes). |
| `./scripts/reseed.sh --validate` | Only validate seed locally; no deploy, no ingest. |
| `./scripts/reseed.sh --help` | Show all options. |

---

### 3.2 Deploy (`deploy.sh`)

**Purpose:** Push the theme and plugin (and seed file) to production. Does not run ingestion.

**Location:** Repo root. Requires `DEPLOY_PASSWORD` and the expect-based rsync scripts (see `scripts/README.md`).

| Command | Description |
|--------|--------------|
| `bash scripts/deploy.sh` | Sync `helmetsan-theme` and `helmetsan-core` to server; optionally copy `ads.txt` to WordPress root. |

**Server result:** Theme and plugin (and seed file) updated under `/var/www/helmetsan.com/public/wp-content/`.

---

### 3.3 Seed generator (`create_helmets_seed.php`)

**Purpose:** Build or validate the helmet seed JSON used by the ingest pipeline. Can use a PHP array (default) or a JSON master file.

**Location:** Repo root. Output is written under the repo (e.g. `helmetsan-core/seed-data/helmets_seed.json`); that file is deployed with the plugin so the server can run `wp helmetsan ingest-seed`.

| Command | Description |
|--------|--------------|
| `php scripts/create_helmets_seed.php --output=helmetsan-core/seed-data/helmets_seed.json --stats` | Generate seed to default path and print stats. |
| `php scripts/create_helmets_seed.php --source-json=data/helmets/master.json --output=... --stats` | Generate from a JSON master instead of the PHP array. |
| `php scripts/create_helmets_seed.php --validate` | Validate only; no output file. |
| `php scripts/create_helmets_seed.php --export-master=data/helmets/master.json` | Export current master to JSON; then exit. |
| `php scripts/create_helmets_seed.php --split-dir=helmets/` | Generate seed and also write per-helmet JSON files. |
| `php scripts/create_helmets_seed.php --help` | List all options. |

---

### 3.4 Data pull (`pull-data.sh`)

**Purpose:** Copy plugin data (brands, helmets, accessories, etc.) from the server into your local repo.

**Location:** Repo root. Requires `DEPLOY_PASSWORD` and the expect script (see `scripts/README.md`).

| Command | Description |
|--------|--------------|
| `./scripts/pull-data.sh` | Rsync from server **`/var/www/helmetsan.com/public/wp-content/plugins/helmetsan-core/data`** to local **`helmetsan-core/data/`**. |

---

### 3.5 Other scripts (repo root)

| Command | Description |
|--------|--------------|
| `php scripts/update_dev_changelog.php` | Append recent git commits to `DEV_CHANGELOG.md`. |
| `./scripts/log-work.sh [summary] [IDE]` | Append a work log entry to `docs/WORK_LOG.md`. |

More scripts (e.g. `fetch_logos.php`, `update_term_descriptions.php`) are documented in **`scripts/README.md`** with run context (repo vs server and WP context).

---

## 4. WP-CLI commands (run on the server)

**Location:** Every command in this section must run with **WordPress root** as the working directory or with `--path` set to that directory:

- **WordPress root on server:** `/var/www/helmetsan.com/public`

**Examples:**

- From your machine:
  ```bash
  ssh root@helmetsan.com "wp --path=/var/www/helmetsan.com/public helmetsan ingest-seed --allow-root"
  ```
- On the server:
  ```bash
  cd /var/www/helmetsan.com/public
  wp helmetsan ingest-seed --allow-root
  ```

---

### 4.1 Ingestion and seed

**Purpose:** Create or update WordPress posts (helmets, brands, accessories) from JSON. The main flow after a deploy is **ingest-seed** (single seed file). **ingest** and **ingest-brands** use per-file or directory-based JSON under the plugin data path on the server.

**Server paths involved:**

- Seed file (default): `wp-content/plugins/helmetsan-core/seed-data/helmets_seed.json` (i.e. under WordPress root).
- Data root for `ingest` / `ingest-brands`: `wp-content/plugins/helmetsan-core/data` (e.g. `data/helmets`, `data/accessories`, `data/brands`).

| Command | Description |
|--------|-------------|
| `wp helmetsan ingest-seed` | Ingest the helmet seed array from the default seed file. Main command after deploy. |
| `wp helmetsan ingest-seed --file=/path/to/seed.json` | Use a specific seed file (path on server). |
| `wp helmetsan ingest-seed --dry-run` | Validate seed without writing to the database. |
| `wp helmetsan ingest-seed --batch-size=25` | Set batch size (default 25); use a smaller value if you hit timeouts. |
| `wp helmetsan ingest --path=data/helmets` | Ingest per-file helmet JSON from the plugin data path. |
| `wp helmetsan ingest --path=data/accessories` | Ingest accessory JSONs. |
| `wp helmetsan ingest-brands` | Ingest brand JSONs from the data root. |
| `wp helmetsan unlock-ingestion` | Clear a stale ingestion lock if a previous run crashed. |
| `wp helmetsan seed [--set=start-pack-v1] [--force]` | Legacy seed set; use ingest-seed for the normal pipeline. |
| `wp helmetsan data check-duplicates` | Scan JSON under the data root for duplicate **id** (and **EAN** for helmets). Reports type, key, count, and file locations. Use to keep the repository sane before ingest or sync. |
| `wp helmetsan data check-duplicates --type=helmet,accessory` | Check only helmets and accessories. |
| `wp helmetsan data check-duplicates --no-ean --format=json` | Skip EAN duplicate check for helmets; output full report as JSON. |
| `wp helmetsan data check-duplicates --format=count` | Output only the number of duplicate keys (for scripts). |
| `wp helmetsan data fix-duplicates --dry-run` | Report duplicates and suggest fix; no file writes. |
| `wp helmetsan data fix-duplicates --strategy=export-deduped --output=helmets/deduped_seed.json` | Write a single JSON array with first occurrence of each helmet id (deduped). Replace or merge with your seed file manually. |

---

### 4.2 SEO

**Purpose:** Set or update Yoast SEO fields (title, meta description, focus keyword). Can be template-only or use AI-generated descriptions (requires API keys in Helmetsan → AI or environment).

**Location:** Server, WordPress root (`/var/www/helmetsan.com/public`).

| Command | Description |
|--------|-------------|
| `wp helmetsan seo seed` | Seed Yoast fields with templates only (no AI), all types. |
| `wp helmetsan seo seed --use-ai` | Same, with AI-generated meta descriptions. |
| `wp helmetsan seo seed --use-ai --limit=5` | Test run (e.g. 5 items). |
| `wp helmetsan seo seed --post-type=helmet \| brand \| accessory \| all` | Restrict to one post type. |
| `wp helmetsan seo seed --dry-run` | Show what would be done; no writes. |
| `wp helmetsan seo check` | Check existing SEO meta (missing/invalid Yoast title, metadesc, focus keyphrase). Use `--scope=posts|terms|all`, `--post-type=<type>`, `--format=table|count`. |
| `wp helmetsan seo update` | Fix Yoast meta (lowercase focus keyphrase, truncate title/metadesc). Use `--scope=posts|terms|all`, `--post-type=<type>`, `--dry-run` to report without saving. |
| `wp helmetsan seo schema-check --limit=200 --format=table` | Audit schema-required fields on helmet posts. |

---

### 4.3 AI enrichment and catalog generation

**Purpose:** (1) **fill-missing** and **cross-link** enrich existing posts using providers under Helmetsan → AI. (2) **generate-seed** and **generate-accessories** create new catalog data (helmets or accessories) with the same AI credentials; **generate-all** runs both in one command.

**Location:** Server, WordPress root (`/var/www/helmetsan.com/public`).

| Command | Description |
|--------|-------------|
| `wp helmetsan ai generate-seed --count=10 --brand=HJC` | Generate 10 helmet models for HJC. **Duplicate check is on by default** (excludes existing parent helmets in WordPress). Output to stdout unless `--output` is set. |
| `wp helmetsan ai generate-seed --count=5 --brands=HJC,Arai,Bell --output=helmets/generated.json` | Generate 5 models across brands; write to data root `helmets/generated.json`. |
| `wp helmetsan ai generate-seed --count=10 --existing-from=helmets/master.json` | Exclude brand/model pairs already in that master file (use instead of WP when generating from JSON). |
| `wp helmetsan ai generate-seed --count=5 --existing-from-wp --output=helmets/generated.json` | Explicitly use WordPress as existing list (default); write to file. |
| `wp helmetsan ai generate-seed --count=5 --no-duplicate-check` | Disable duplicate check; allow generating models that may already exist (e.g. testing). |
| `wp helmetsan ai generate-seed --count=3 --provider=groq --dry-run` | Test run: call AI, show count and JSON; do not write file. |
| `wp helmetsan ai generate-accessories --count=10 --category="Bluetooth Headsets"` | Generate 10 accessories in a given category; output to stdout or `--output=accessories/generated.json`. |
| `wp helmetsan ai generate-accessories --count=5 --categories=Pinlock Inserts,Visors --output=accessories/generated.json` | Generate 5 accessories across categories; write to file. |
| `wp helmetsan ai generate-all --helmets-count=10 --accessories-count=10 --output-dir=.` | Generate both helmets and accessories in one run; write to `helmets/generated.json` and `accessories/generated.json` under data root. Use `--helmets-count=0` or `--accessories-count=0` to skip one. |
| `wp helmetsan ai fill-missing --post-type=all --limit=0` | Fill blank fields for all types (helmets, brands, accessories). |
| `wp helmetsan ai fill-missing --report --post-type=helmet` | Coverage report only: per-field set/empty and % complete; no API calls or writes. |
| `wp helmetsan ai fill-missing --post-type=helmet --limit=200` | Fill only helmets, limit 200. |
| `wp helmetsan ai fill-missing --only-incomplete` | Only items with missing or incomplete data. |
| `wp helmetsan ai fill-missing --dry-run --verbose` | Show what would be filled; no writes. |
| `wp helmetsan ai cross-link --post-type=all --limit=0` | Overwrite internal link suggestions for all. |
| `wp helmetsan ai cross-link --post-type=helmet --limit=50 --report` | Cross-link helmets and print analytic report (links by reason, total links, avg per post). Use `--dry-run --report` for report-only. |

**After generate-seed / generate-accessories:** Merge generated JSON into `data/helmets/master.json` or `data/accessories/` as needed, then run the ingest pipeline. **fill-missing --report** and **cross-link --report** help you see coverage and link distribution before or after enrichment.

---

### 4.4 Accessory categories

**Purpose:** Ensure accessory_category terms exist and are assigned so category archives and filters work.

**Location:** Server, WordPress root (`/var/www/helmetsan.com/public`).

| Command | Description |
|--------|-------------|
| `wp helmetsan seed-accessory-categories` | Create or update accessory_category terms. |
| `wp helmetsan backfill-accessory-categories` | Assign category from type/parent_category/subcategory to each accessory. |
| `wp helmetsan backfill-accessory-categories --force` | Re-run backfill even if already set. |

---

### 4.5 Sync and GitHub

**Purpose:** Pull JSON from GitHub into the plugin data directory on the server, or push local JSON to GitHub. Paths are relative to the plugin data root on the server.

**Location:** Server, WordPress root (`/var/www/helmetsan.com/public`).

| Command | Description |
|--------|-------------|
| `wp helmetsan sync pull --profile=pull+brands --dry-run` | Dry-run pull (e.g. brands only). Profiles: `pull+brands`, `pull+helmets`, `pull+all`. |
| `wp helmetsan sync pull --profile=pull+all` | Pull all data from GitHub. |
| `wp helmetsan sync push --path=data/helmets --limit=200` | Push local JSON (e.g. helmets) to GitHub. |
| `wp helmetsan sync-logs --tail --limit=20 --format=table` | Show recent sync log entries. |
| `wp helmetsan sync-logs-cleanup --days=30` | Clean old sync logs. |

---

### 4.6 Health, go-live, analytics

**Purpose:** Check system state, go-live readiness, and analytics. No data changes.

**Location:** Server, WordPress root (`/var/www/helmetsan.com/public`).

| Command | Description |
|--------|-------------|
| `wp helmetsan health --format=json` | Health check (config, ingestion state, etc.). |
| `wp helmetsan go-live checklist` | Go-live gate: score, blockers, diagnostics. |
| `wp helmetsan analytics smoke-test` | Quick analytics smoke test. |
| `wp helmetsan analytics report --days=7 --format=table` | Analytics report. |
| `wp helmetsan api-check` | Check external API connectivity. |

---

### 4.7 Scheduler and alerts

**Purpose:** Inspect or run scheduled tasks and test the alerts pipeline.

**Location:** Server, WordPress root (`/var/www/helmetsan.com/public`).

| Command | Description |
|--------|-------------|
| `wp helmetsan scheduler status` | Show scheduler status. |
| `wp helmetsan scheduler run --task=sync_pull` | Run a scheduled task (e.g. sync_pull). |
| `wp helmetsan alerts test --title="Ping" --message="Test"` | Test alerts. |

---

### 4.8 Other WP-CLI commands

**Location:** Server, WordPress root (`/var/www/helmetsan.com/public`).

| Command | Description |
|--------|-------------|
| `wp helmetsan ingest-logs-cleanup --days=30 --status=failed` | Clean old ingestion logs. |
| `wp helmetsan retry-failed --limit=100 --batch-size=50` | Retry failed ingestion items. |
| `wp helmetsan revenue report --days=30 --format=table` | Revenue report. |
| `wp helmetsan revenue import-links` | Import revenue/affiliate links. |
| `wp helmetsan brand cascade --brand-id=12` | Cascade brand updates (one brand). |
| `wp helmetsan brand cascade --all` | Cascade all brands. |
| `wp helmetsan media backfill-brand-logos` | Backfill brand logos. |
| `wp helmetsan helmet-images` | Helmet image operations. |
| `wp helmetsan woo-bridge sync --helmet-id=123` | Sync one helmet to WooCommerce. |
| `wp helmetsan woo-bridge sync --limit=100 --dry-run` | Bulk WooCommerce sync (dry-run). |
| `wp helmetsan list-unmapped-accessory-meta` | List accessory meta not mapped to categories. |
| `wp helmetsan price seed-history` | Seed price history. |
| `wp helmetsan docs build-index` | Build docs index. |
| `wp helmetsan validate schema \| logic \| integrity \| accessory` | Validate data/schema. Use `validate accessory --file=/path/to.json` for accessory JSON (single object or array). |
| `wp helmetsan import --file=/path/data.zip --mode=merge` | Import data from file. |
| `wp helmetsan export --entity=helmet --id=shoei-nxr2 --format=json` | Export entity to JSON. |

---

## 5. Quick reference by task

| Task | Where | Command |
|------|--------|--------|
| **Full reseed (generate → deploy → ingest)** | Repo root | `./scripts/reseed.sh` |
| **Ingest only (seed already on server)** | Repo root | `./scripts/reseed.sh --skip-deploy` |
| **Deploy theme/plugin only** | Repo root | `bash scripts/deploy.sh` |
| **Generate seed JSON** | Repo root | `php scripts/create_helmets_seed.php --output=helmetsan-core/seed-data/helmets_seed.json --stats` |
| **Pull data from server to local** | Repo root | `./scripts/pull-data.sh` |
| **Run ingest on server** | Server (WP root) or SSH | `wp --path=/var/www/helmetsan.com/public helmetsan ingest-seed --allow-root` |
| **Generate catalog via AI (helmets)** | Server (WP root) or SSH | `wp --path=/var/www/helmetsan.com/public helmetsan ai generate-seed --count=10 --brand=HJC [--output=helmets/generated.json] --allow-root` |
| **Generate catalog via AI (accessories or both)** | Server (WP root) or SSH | `wp helmetsan ai generate-accessories --count=10` or `wp helmetsan ai generate-all --helmets-count=5 --accessories-count=5 --output-dir=.` |
| **SEO / AI / Sync / Health** | Server (WP root) or SSH | `wp --path=/var/www/helmetsan.com/public helmetsan <subcommand> ... --allow-root` |

**Config:** Server host and paths are in **`scripts/config`** (`REMOTE_WP_PATH`, `REMOTE_HOST`, etc.). Always run deploy and reseed scripts from **repo root** so that `config` is found. For more script details and environment variables, see **`scripts/README.md`**.
