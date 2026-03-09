# Duplicate check and fix: design

## Goals

- **Repository sanity**: No duplicate `id` (or EAN for helmets) across JSON so ingest and sync behave predictably.
- **Single place to check**: CLI, plugin admin, and dashboard all expose duplicate status.
- **Fix when safe**: Offer optional fix strategies for repo JSON; never auto-delete or auto-merge without user confirmation.

## Check vs fix

| Layer | Check | Fix |
|-------|--------|-----|
| **Repo JSON** (data/helmets, accessories, brands) | `wp helmetsan data check-duplicates`; Health report; Data/Duplicates admin page. Scans by `id` and (helmets) EAN. | Optional: `wp helmetsan data fix-duplicates --strategy=...` and admin "Fix" with dry-run. Strategies: **rename-suffix** (duplicate ids get `_dup2`, `_dup3` so user can merge/delete), **export-deduped** (output single deduped file keeping first occurrence). No auto-delete. |
| **WordPress DB** | Ingestion uses unique id + hash; no duplicate posts for same id. Duplicate *content* (e.g. same EAN on two posts) is not auto-detected today. | Manual: merge or trash duplicate posts in admin. Future: "Find duplicates by EAN" report + choose which to keep. |
| **AI generate-seed** | Default: exclude existing brand/model (and optionally EAN) from WP so we don't generate duplicates. | N/A (prevention only). |
| **fill-missing** | Per-post; no duplicate "records". Optional: skip filling EAN if another post already has that EAN (uniqueness hint). | N/A. |
| **cross-link** | Before save: dedupe suggested links by URL so we don't store the same link twice per post. | In-code dedupe (no user-facing fix). |
| **sync pull (apply)** | Before or after apply: run repo duplicate check on pulled paths; warn (or abort) if duplicates exist so user can fix JSON before re-applying. | User fixes JSON then re-runs. |
| **sync push** | Before push: run repo duplicate check; warn if duplicates so user doesn't push bad state. | User fixes JSON then re-pushes. |

## Where duplicate logic is applied

| Command / flow | Duplicate logic |
|----------------|------------------|
| **ai generate-seed** | Uses existing brand/model (and optional EAN) from WP or `--existing-from` file; filters generated list; default = check on. |
| **fill-missing** | No duplicate records; operates per post. Optional future: "unique EAN" skip. |
| **cross-link** | Dedupe links by URL before saving `outgoing_internal_links_json`. |
| **sync pull** | After download, before apply: run duplicate check on repo; if duplicates > 0, add warning to result and optionally set flag. |
| **sync push** | Before push: run duplicate check; if duplicates > 0, add warning to CLI/admin output. |
| **ingest / ingest-seed** | Already uses unique id lookup and hash skip; no duplicate posts for same id. Duplicate ids in JSON = last write wins; check-duplicates helps avoid that. |

## UI and CLI

- **Dashboard**: Show "Repository duplicates: N" with link to Data → Duplicates (or Health). Use cached count from health report.
- **Health page**: Show duplicate count; link to Data → Duplicates for full table and fix.
- **Data → Duplicates**: New submenu. Run check (table of type, key, count, locations). Buttons: "Re-check", optional "Fix (dry-run)" then "Apply fix" with strategy choice.
- **CLI**: `wp helmetsan data check-duplicates` (existing). Add `wp helmetsan data fix-duplicates --dry-run --strategy=rename-suffix|export-deduped [--type=helmet|accessory|brand]`.

## Fix strategies (repo JSON only)

- **rename-suffix**: For each duplicate id (2nd, 3rd, … occurrence), write updated JSON so that id becomes `id_dup2`, `id_dup3`. User can then manually merge or delete. Requires writing back to files or new files.
- **export-deduped**: For seed-style arrays, output one combined array keeping first occurrence of each id; user replaces file. Does not modify files in place.

Implement rename-suffix only where we can write back (e.g. per-file entities); for seed array, export-deduped is safer.
