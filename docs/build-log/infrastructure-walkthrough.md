# Infrastructure & Pipeline — Walkthrough

> **Date:** February 2026

## Summary

Made the seed → deploy → ingest pipeline smooth and scriptable, cleaned up git, and pushed everything to GitHub.

## What Changed

### 1. New `wp helmetsan ingest-seed` CLI Command

[Commands.php](file:///Users/anumac/Documents/Helmetsan/helmetsan-core/includes/CLI/Commands.php) — The old `wp helmetsan ingest` only worked with per-file JSON layouts, but the seed generates one big array. The new command:

- Reads the array-format seed file directly
- Splits into temp per-helmet JSON files
- Calls `ingestFiles()` with a progress bar
- Cleans up temp files automatically

```bash
wp helmetsan ingest-seed                    # defaults to helmets_seed.json
wp helmetsan ingest-seed --dry-run          # validate only
wp helmetsan ingest-seed --file=/custom.json
```

### 2. Improved Seed Script

[create_helmets_seed.php](file:///Users/anumac/Documents/Helmetsan/create_helmets_seed.php) — Added CLI flags:

| Flag                | Purpose                                    |
| ------------------- | ------------------------------------------ |
| `--output=<file>`   | Write JSON to file instead of stdout       |
| `--validate`        | Check IDs, descriptions, type distribution |
| `--stats`           | Print summary stats to stderr              |
| `--split-dir=<dir>` | Write per-helmet JSON files                |
| `--help`            | Show usage                                 |

### 3. One-Command Pipeline

[reseed.sh](file:///Users/anumac/Documents/Helmetsan/scripts/reseed.sh) — Single script for the full lifecycle:

```bash
./scripts/reseed.sh              # generate → deploy → ingest
./scripts/reseed.sh --dry-run    # no DB writes
./scripts/reseed.sh --validate   # local validation only
```

### 4. Git Cleanup

- `.gitignore`: Added `*.expect` (credentials), `price_*`, `debug_*`, `helmets_seed.json` (root)
- Removed from tracking: credential files, junk files
- No credentials in commit — verified before push

### 5. Updated README

[README.md](file:///Users/anumac/Documents/Helmetsan/README.md) — Updated stats (1,156 helmets, 30 brands), added Data Pipeline section.

## Verification

- `php create_helmets_seed.php --validate` → ✅ 142 models, 0 duplicate IDs, all descriptions
- `git status` confirms no `*.expect` files staged
- Pushed to GitHub: commit `91d182f` (99 files, 39,205 insertions)

## Workflow for Future Use

```bash
# Edit create_helmets_seed.php → add new brands/models
# Then just:
./scripts/reseed.sh
# Done. 🎉
```
