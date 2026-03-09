# Ingestion: Unique IDs and Hash-Based Skip

This document defines the **canonical unique key** and **hash-based skip** behaviour for each entity type. Re-running the seeder or ingest with the same data should not create duplicates and should skip unchanged records.

## Helmet

| Meta / behaviour | Description |
|------------------|-------------|
| **Unique key** | `_helmet_unique_id` — set from payload `id` (e.g. `shoei_rf-1400_matte-black`). |
| **Lookup** | `findHelmetPostId($externalId)` finds existing post by `_helmet_unique_id`. |
| **Hash** | `_source_hash` = SHA-256 of JSON-encoded payload. |
| **Skip** | If existing post has same `_source_hash` **and** has at least one `helmet_type` term, the record is **skipped** (no update). Logged as `skipped`. |
| **Payload** | Seed/JSON must include stable `id`; ingestion uses it for upsert. All ingested meta (e.g. `safety_intelligence_json`, `aero_acoustic_profile_json`, `tech_integration_json`, `fitment_coordinates_json`, `spec_shell_sizes`, `model_year`, identifiers, key_specs) is included in the payload and thus in the hash. |

## Accessory

| Meta / behaviour | Description |
|------------------|-------------|
| **Unique key** | `_accessory_unique_id` — set from payload `id` (e.g. `pinlock-120`). Fallback: match by `post_name` (slug from title). |
| **Lookup** | `AccessoryService::findByExternalId($externalId)` finds by `_accessory_unique_id`. |
| **Hash** | `_source_hash` = SHA-256 of JSON-encoded payload. |
| **Skip** | If existing post has same `_source_hash`, upsert returns `action: 'skipped'` and no DB write. Ingest path counts this as **skipped**. |
| **Payload** | Include stable `id` and `title` for reliable matching. |

## Brand

| Meta / behaviour | Description |
|------------------|-------------|
| **Unique key** | `_brand_unique_id` — set from payload `id` (e.g. `shoei`, `agv`). Fallback: match by `post_name` (slug from title). |
| **Lookup** | `BrandService::findByExternalId($idRaw)` finds by `_brand_unique_id`. |
| **Hash** | `_source_hash` = SHA-256 of JSON-encoded payload. |
| **Skip** | If existing post has same `_source_hash`, upsert returns `action: 'skipped'`. Sync/ingest path counts this as **skipped**. |
| **Payload** | Include stable `id` and `title`; used by `ingestBrandsFromPath` and pull+apply. |

## Other entities (sync pull)

Motorcycle, safety_standard, dealer, distributor, comparison, recommendation, and commerce entities follow the same pattern where implemented: a unique id meta (e.g. `_motorcycle_unique_id`), `_source_hash`, and skip when hash unchanged. See each service’s `upsertFromPayload` and SyncService `apply*Files`.

## Ensuring no duplicates

1. **Seed/JSON**: Use a **stable, unique `id`** per entity (e.g. slug). Do not change `id` for the same product across runs.
2. **Ingestion**: Always match by the canonical unique key first; create only when no existing post is found.
3. **Hash**: Store `_source_hash` on every create/update. Skip full update when stored hash equals computed hash so re-runs are idempotent.

## Logs and CLI

- **IngestionService** returns `skipped` in the result; CLI and admin should show it (e.g. “Accepted: N, Skipped: M, Rejected: K”).
- Log repository: entries with status `skipped` indicate hash-unchanged records that were not updated.

## Validation before ingest

- **Accessory JSON:** Validate schema and logic before ingesting: `wp helmetsan validate accessory --file=/path/to/accessories.json`. File can be a single object or an array of accessory objects. See `Validator::validateAccessorySchema()` and `validateAccessoryLogic()`.

## Duplicate check (repository sanity)

To avoid duplicate **id**s (and duplicate **EAN**s for helmets) across JSON files before ingest or sync:

- **CLI:** `wp helmetsan data check-duplicates` scans the data root (`data/helmets`, `data/accessories`, `data/brands`, `seed-data`) and reports any key that appears more than once, with file paths and indices. Use `--type=helmet|accessory|brand` to limit scope, `--no-ean` to skip EAN checks for helmets, and `--format=table|json|count` for output.
- **What is checked:** Helmets: `id` and (unless `--no-ean`) `identifiers.ean` / `identifiers.gtin` / `identifiers.upc`; seed arrays and per-file helmet JSON; master-format (brand/model) keys. Accessories: `id` per file or array index. Brands: `id` (or profile slug / title-derived slug).
- Fix duplicates by editing the JSON (merge or remove duplicate entries, or give one of them a new unique `id`) before running ingest or pushing to the repo.
