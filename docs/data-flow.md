# Data flow: JSON ↔ WordPress ↔ GitHub

This document describes how catalog data (helmets, accessories, brands) moves between **JSON files**, **WordPress** (posts and meta), and **GitHub**.

## Concept

- **Source of truth for content** can be either:
  - **JSON in the repo** (e.g. `data/helmets/*.json`) — edited in Git, then applied to WordPress via **ingestion** or after a **sync pull**.
  - **WordPress** — edited in the admin, then written back to JSON via **export**, and optionally pushed to GitHub via **sync push**.

- **Ingestion** only **reads** JSON and **writes** to WordPress. It does **not** write to GitHub or to JSON files.

- **Sync** has two directions:
  - **Pull:** GitHub → download JSON into the local repo → optionally **apply** (run ingestion/brands etc.) so WordPress is updated.
  - **Push:** Local JSON files on disk → upload to GitHub as-is. Push does **not** read from WordPress; it uploads whatever files exist in the repo (e.g. under `data/`).

- To get **WordPress changes into GitHub**: export entities (helmet/brand) to JSON (which writes/overwrites files on disk), then run **sync push**.

## Structure (high level)

```
                    ┌─────────────┐
                    │   GitHub    │
                    │  (remote)   │
                    └──────┬──────┘
                           │
            Pull (download) │  Push (upload)
                            │
                    ┌───────▼───────┐
                    │  Local repo  │
                    │ data/helmets │
                    │ data/brands  │
                    │ data/...     │
                    └───────┬───────┘
                            │
    ┌───────────────────────┼───────────────────────┐
    │                       │                       │
    │  Ingestion (read)     │    Export (write)      │
    │  Data / Reseed        │    Import/Export       │
    │  Sync "apply" step    │    (WP → JSON file)   │
    │                       │                       │
    └───────────────────────┼───────────────────────┘
                            │
                    ┌───────▼───────┐
                    │  WordPress    │
                    │  (helmet,     │
                    │   brand,      │
                    │   accessory   │
                    │   CPT + meta) │
                    └───────────────┘
```

## Where each action lives in the admin

| Concept | Admin menu | What it does |
|--------|-------------|---------------|
| **Ingestion** | **Ingestion** | Logs of past runs (created/updated/skipped/failed). Ingestion itself **reads** JSON from disk and writes to WordPress; it does not update GitHub. |
| **Run ingestion** | **Data / Reseed** | Run ingestion now: from seed file, or from path (`helmets`, `accessories`, `brands`). Uses JSON already on disk (e.g. after a pull or manual copy). |
| **Sync Pull** | **Sync Logs** (top section) | Download JSON from GitHub into local repo; optionally apply brands/helmets/accessories so WordPress is updated. |
| **Sync Push** | Settings → **GitHub Sync** (or CLI) | Upload **local** JSON files to GitHub. To update GitHub with WordPress data, **export** first (Import/Export), then push. |
| **Export** | **Import/Export** → Export JSON | Build JSON from a helmet or brand post and write it to a file (e.g. under data root). Exported file includes all meta (identifiers, safety_intelligence, etc.) and is ready to push. |
| **Import** | **Import/Export** → Import JSON | Read a JSON file (upload or path) and run ingestion on it → WordPress is updated. |

## Typical workflows

1. **GitHub → WordPress (use repo as source of truth)**  
   Run **Sync** → Pull (download), with profile e.g. "Pull + Helmets". Then ingestion runs on the downloaded files and WordPress is updated.

2. **WordPress → GitHub (use WP as source of truth)**  
   Use **Import/Export** → Export one or more helmets/brands to JSON (files land in data root or a path you choose). Commit and push to GitHub (or use sync push if configured to push from that path).

3. **Edit JSON locally, then update WordPress**  
   Edit files under `data/helmets/` etc. Run **Data / Reseed** → Ingest path (helmets/accessories/brands). No GitHub step needed unless you also want to push the edits.

## Terminology

- **Ingestion** = Applying JSON to WordPress (create/update posts and meta). One-way: JSON → WordPress.
- **Export** = Building JSON from WordPress (one entity at a time) and writing to a file. One-way: WordPress → file.
- **Sync pull** = Download files from GitHub to local repo; optional “apply” step runs ingestion/brands on the downloaded files.
- **Sync push** = Upload local repo files to GitHub. Does not read WordPress.
- **Seed** = Generated helmet (variant) JSON array; **ingest-seed** = ingest that array. **Reseed** = full pipeline (generate → deploy → ingest-seed). See AGENTS.md.

## See also

- [Sync module](modules/sync.md) — Pull/push behavior, profiles, configuration.
- [Ingestion module](modules/ingestion.md) — Meta fields written, identifiers, round-trip.
- [Ingestion unique IDs and hash](ingestion-unique-ids-and-hash.md) — How upserts and skips work.
