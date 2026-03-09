# Schemas and Validation

How JSON schemas in the repo relate to plugin ingestion validation.

## Authority

| Layer | Purpose | Used at ingest? |
|-------|---------|-----------------|
| **`data/schemas/*.json`** | Authoritative reference for entity shape (helmet, brand, accessory, etc.). Use in **CI** and **AI agents** to validate or generate JSON before commit or sync. | **No** — plugin does not load these files. |
| **Plugin `Validator`** | PHP-only checks during ingestion: required `id`, `specs.weight_g` type, `legal_status` structure, weight range warnings. | **Yes** — runs for every file in ingest-seed and path/sync apply. |

So: **schemas = source of truth for structure; Validator = minimal runtime checks at ingest.** Keeping payloads valid against `data/schemas/` (e.g. via CI) avoids drift; the plugin does not enforce schema files itself.

## Schema files (data/schemas/)

Canonical list for entities and related types (exact filenames may vary):

- `helmet.schema.json`
- `brand.schema.json`
- `accessory.schema.json`
- `marketplace.schema.json`
- `pricing.schema.json`
- `offer.schema.json`
- … plus others under `data/schemas/`

Use these for:

- Pre-commit or CI validation of JSON in `data/`.
- AI/agent prompts that generate or validate entity JSON.

## Plugin validation (Validator)

- **validateSchema($data):** Requires `id` (string); optionally validates `specs.weight_g` as integer.
- **validateLogic($data):** Weight range warnings (800–3000 g); `legal_status` per-region structure.
- **validateIntegrity():** Placeholder; no cross-reference checks implemented yet.

Ingestion uses **validateSchema** and **validateLogic**; rejections are logged. To enforce full schema compliance at ingest, the plugin would need to load and apply `data/schemas/*.json` (e.g. via a JSON Schema library); that is not implemented today.

## Seed generator shape

The **seed generator** (`scripts/create_helmets_seed.php`) input shape (Brand → Model → colorways) is documented by **`data/helmets/master.example.json`**. It is not validated against a JSON Schema in code; keep it in sync with the generator and ingestion expectations manually or via CI.
