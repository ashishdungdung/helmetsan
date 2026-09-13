# Archived Scripts

This directory contains historical migration, initial seeding, and batch enrichment scripts used during earlier development phases of Helmetsan.

> **WARNING — HISTORICAL / ARCHIVED FILES**
> Do not execute these scripts directly against the active canonical dataset (`data/helmets/*.json`) without review.
>
> Many scripts in this directory assume:
> 1. Legacy price schemas (e.g. `price['current']` or flat `price_usd`) which have been migrated to the structured 6-currency schema (`price.usd`, `price.inr`, `price.eur`, `price.gbp`, `price.aed`, `price.jpy`).
> 2. Direct database / memory dumps prior to atomic-write and JSON validation standards.
> 3. Older slug patterns prior to canonical `hs_slug()` normalization.
>
> For current catalog maintenance, refer to active scripts in `scripts/`:
> - `scripts/seed_helmets.php`
> - `scripts/continuous-sweep.php`
> - `scripts/local_llm_fix_and_enrich.php`
> - `scripts/deduplicate_catalog.php`
