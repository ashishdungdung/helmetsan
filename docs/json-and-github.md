# JSON Data and GitHub Sync

How JSON is used in the seeder and how GitHub sync moves that data between repo and server.

**Data-flow summary:** **Ingestion** reads JSON from disk and writes to WordPress only; it does **not** write to GitHub. **Sync pull** downloads files from GitHub to local repo; then you apply (ingest) to update WordPress. **Sync push** uploads **local** JSON files to GitHub — it does not read from WordPress. To get WordPress (or edited) data into GitHub: **export** entities to JSON (Import/Export), then **sync push**. Full concept: [Data flow: JSON ↔ WordPress ↔ GitHub](data-flow.md).

---

## 1. Where JSON Lives

| Location | Purpose |
|----------|--------|
| **`data/`** (repo root) | Source-of-truth in Git. Entity folders: `brands/`, `helmets/`, `accessories/`, `motorcycles/`, `safety-standards/`, `dealers/`, `distributors/`, `marketplaces/`, `geo/`, `pricing/`, `offers/`, `comparisons/`, `recommendations/`, `catalogs/`, `helmet-types/`, `phases/`, `schemas/`. |
| **`data/helmets/master.example.json`** | Example structure for **seed generator** when using `--source-json`. Same shape as the in-file PHP array: `Brand → Model → type, price, cert, shape, weight, mat, desc, colorways[]`. |
| **`data/helmets/*.json`** (per-helmet) | Curated per-helmet JSONs (e.g. `shoei-rf-1400.json`, `agv-k6.json`). Used by **path-based ingest** for richer data and marketplace links. Not the same as the single “seed array” from the generator. |
| **`helmetsan-core/seed-data/helmets_seed.json`** | **Generated** seed file (array of helmet variants). Produced by `create_helmets_seed.php` and consumed by `wp helmetsan ingest-seed`. Can be committed or deployed to server. |

---

## 2. Two JSON Contexts for Helmets

### A. Seed generator (one big array)

- **Input:** Either the **in-file PHP array** in `scripts/create_helmets_seed.php`, or a **single JSON file** via `--source-json` (e.g. `data/helmets/master.json`).
- **Output:** One JSON array of variant records written to `--output=...` (e.g. `helmetsan-core/seed-data/helmets_seed.json`).
- **Structure of the source (PHP or JSON):**  
  `BrandName → ModelName → { type, price, cert, shape, weight, mat, desc, colorways: [ { name, family, finish, sku, price_adj, ... } ] }`.  
  See **`data/helmets/master.example.json`** for the exact shape.

```bash
# Using in-file PHP array (legacy)
php scripts/create_helmets_seed.php --output=helmetsan-core/seed-data/helmets_seed.json --stats

# Using JSON source (recommended for editing data in data/)
php scripts/create_helmets_seed.php --source-json=data/helmets/master.json --output=helmetsan-core/seed-data/helmets_seed.json --stats
```

- If you add **`data/helmets/master.json`** (or any name) with that structure, you can maintain catalog data in the repo under `data/helmets/` and stop editing the large PHP array.

### B. Path-based ingest (per-file JSON)

- **Input:** Individual JSON files under a path (e.g. `data/helmets/` or `helmets/` on the server under the data root).
- **Consumed by:** `wp helmetsan ingest --path=helmets` (or `ingest --path=data/helmets` depending on how the data root is set). Sync **pull** can also bring these files from GitHub and then apply them.
- **Use case:** Richer, per-helmet payloads (marketplace links, extra meta). Each file = one helmet entity (or variant, depending on schema). Distinct from the “one array” seed.

---

## 3. GitHub Sync and `data/`

- **Config:** **Helmetsan → GitHub** (or `helmetsan_github` option). Key settings: `owner`, `repo`, `token`, `branch`, **`remote_path`** (e.g. `data` or `data/`).
- **`remote_path`:** Subdirectory in the GitHub repo that is synced. Default is effectively **`data/`**. So the repo’s `data/brands/`, `data/helmets/`, etc. map to that path.
- **Pull:** Fetches the tree from GitHub under `remote_path`, categorizes files by folder (brands, helmets, accessories, …), downloads blob contents to local disk, and can **apply** them via ingestion (e.g. `applyBrandFiles`, `applyHelmetFiles`). So **JSON in Git under `data/`** is the source for pull. Ingestion itself only reads local files and writes to WordPress; it does not push to GitHub.
- **Push:** Uploads **local** JSON files (e.g. under `data/helmets/`) to GitHub. Push does **not** read from WordPress. To update GitHub with WordPress data: **export** first (Import/Export → Export JSON), then run sync push (or commit and push via Git).
- **Health / Go Live:** `wp helmetsan health` and **Helmetsan → Go Live** can report whether GitHub is configured and that sync is enabled.

```bash
# Validate GitHub config
wp helmetsan health --format=json

# Pull from GitHub (dry-run then apply)
wp helmetsan sync pull --profile=pull+brands --dry-run
wp helmetsan sync pull --profile=pull+brands

# Push local changes to GitHub (e.g. helmet JSONs)
wp helmetsan sync push --path=data/helmets --limit=200
```

---

## 4. Recommended Flow (JSON + GitHub)

1. **Keep catalog source in Git under `data/`:**
   - **Option A (seed):** Add `data/helmets/master.json` (same structure as `master.example.json`). Generate seed with `--source-json=data/helmets/master.json`.
   - **Option B (path ingest):** Add or edit per-helmet JSONs in `data/helmets/*.json`. Use path-based ingest or sync pull to apply them.
2. **Validate:** Use `scripts/create_helmets_seed.php --validate` for seed; use `schemas/` in `data/` for entity JSON if you have CI/CLI validation.
3. **Sync:**  
   - **Reseed (seed array):** Run `./scripts/reseed.sh` (generate → deploy → ingest-seed).  
   - **Path-based / GitHub:** Run `wp helmetsan sync pull` (and optional apply) so the server gets the latest from GitHub, then run path-based ingest or let sync apply files.
4. **Enrichment:** After ingest, run fill-missing, SEO seed, and cross-link as in the README and **`docs/ai-seeder-enrichment-roadmap.md`**.

---

## 5. Sync profiles (pull apply)

Pull **downloads** files; the **profile** controls what gets **applied** after download: `pull-only` (no apply), `pull+brands` (brands only), `pull+helmets` (helmets + accessories only), `pull+all` (brands + helmets + accessories). See **`docs/modules/sync.md`** for the full table and delete/rename behavior.

## 6. Quick Reference

| Task | Command / location |
|------|---------------------|
| Seed from JSON file | `php scripts/create_helmets_seed.php --source-json=data/helmets/master.json --output=... --stats` |
| Seed from PHP array | `php scripts/create_helmets_seed.php --output=helmetsan-core/seed-data/helmets_seed.json --stats` |
| Ingest seed array | `wp helmetsan ingest-seed` (uses `helmetsan-core/seed-data/helmets_seed.json` by default) |
| Ingest path (e.g. helmets) | `wp helmetsan ingest --path=helmets` (or path under data root) |
| GitHub pull | `wp helmetsan sync pull --profile=pull+brands` (or `pull+all`) |
| GitHub push | `wp helmetsan sync push --path=data/helmets --limit=200` |
| Example seed JSON shape | `data/helmets/master.example.json` |
| Data folder layout | `data/README.md` |
| Sync module (profiles, delete/rename) | `docs/modules/sync.md` |
