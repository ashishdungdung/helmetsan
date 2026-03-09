# AI, Seeder & Enrichment Roadmap

**Status:** All implementation phases A–E are complete (seeder consistency, fill-missing extended, SEO seed relations, cross-link, providers). Use `wp helmetsan ai fill-missing`, `wp helmetsan seo seed --use-ai`, and `wp helmetsan ai cross-link` as in README.

**Data flow:** Ingestion **reads** JSON and writes to WordPress only; it does **not** write to GitHub. Sync **pull** = download from GitHub then apply; **push** = upload local JSON to GitHub. To get WordPress (or enriched) data into GitHub: **export** then **push**. See [Data flow: JSON ↔ WordPress ↔ GitHub](data-flow.md).

This document defines the plan for:

1. **Robust seeder logic** — no duplicates, consistency, enrichment pipeline
2. **AI fill-missing** — extended meta, taxonomies, cross-linking
3. **SEO seed (meta descriptions)** — deeper logic and relations
4. **More low-cost/free AI providers** — with descriptions
5. **Unified behaviour** — seeder, standalone CLI, and AI admin all use the same rules

---

## 1. Seeder robustness (no duplicates, consistency)

### Current behaviour

- **Helmets**: Ingestion uses `_helmet_unique_id` (external `id` from seed/JSON) to find existing posts. Updates use `_source_hash` so unchanged payloads can be skipped. Upsert is create-or-update; no delete.
- **Brands / Accessories**: Similar unique-id and payload-based matching where implemented.

### Improvements

| Area | Goal | Approach |
|------|------|----------|
| **Canonical unique key** | Single source of truth for “same entity” across runs | Keep `_helmet_unique_id` for helmets; ensure brands/accessories use equivalent (e.g. `_brand_unique_id` / slug, `_accessory_unique_id`). Document in ingestion schema. |
| **Hash-based skip** | Don’t overwrite when payload unchanged | Use `_source_hash` (or equivalent) for all entity types. On ingest: compute hash; if matches stored hash, skip meta update and count as “skipped” not “updated”. |
| **Idempotency** | Re-running seeder produces same state | All writes must be deterministic from payload (no “only set if empty” unless explicitly a “fill missing” pass). Seeder + ingest: always set the same fields from the same payload. |
| **Fill-missing vs seed** | No conflict between “seed data” and “AI fill” | Seeder/ingest writes only what’s in the payload. A separate step (CLI or cron) runs “fill missing” for empty fields. Fill-missing must not overwrite non-empty values unless in “overwrite” mode. |
| **Validation before write** | Fewer inconsistent states | Validate payload (required fields, types, taxonomies exist) before upsert. Log and skip invalid rows; do not partial-write. |
| **Enrichment pipeline** | Clear order of operations | 1) Ingest seed/JSON (create/update by unique id + hash). 2) Optionally run taxonomy/term assignment from payload. 3) Run fill-missing for empty meta. 4) Run SEO seed for Yoast fields. 5) Cross-link pass (internal links). |

### Seeder-specific

- **create_helmets_seed.php** (and JSON source): Emit stable `id` (e.g. `brand_model_color`) and consistent fields so hash is stable.
- **IngestionService**: Enforce “match by unique id, update only if hash changed”; expose “skipped (unchanged)” in logs and CLI.
- **Admin / CLI**: “Reseed” = run seed generator → ingest with same rules; “Fill missing” = only fill empty fields; “SEO seed” = only Yoast meta.

---

## 2. Extended metadata AI can fill

AI (fill-missing and SEO) should be able to fill or suggest the following, in **seeder**, **standalone**, and **AI admin** modes.

### Meta fields (stored in post meta)

| Field / area | Description | Used in |
|--------------|-------------|--------|
| Helmet types | Taxonomy `helmet_type` (term assignment) | Fill-missing, SEO context |
| Regions | Taxonomy or meta for target regions/markets | Fill-missing |
| Certifications | Taxonomy `certification` (term assignment) | Fill-missing, SEO |
| Feature tags | Taxonomy `feature_tag` (term assignment) | Fill-missing |
| Brand | `rel_brand` (post ID) or brand slug | Fill-missing, SEO, cross-link |
| Use cases | Meta or taxonomy (e.g. touring, racing, commuter) | Fill-missing |
| Price range | Meta or derived (e.g. budget, mid, premium) | Fill-missing, SEO |
| Date | Published/updated or “model year” style meta | Fill-missing |
| SEO score / Readability | Optional Yoast-style scores (store as meta if needed) | SEO seed, AI review |
| SEO Title | `_yoast_wpseo_title` | SEO seed, fill-missing |
| Meta description | `_yoast_wpseo_metadesc` | SEO seed, fill-missing |
| Keyphrase | `_yoast_wpseo_focuskw` | SEO seed, fill-missing |
| Outgoing internal links | Stored links (e.g. JSON meta) to other helmets/brands/pages | Cross-link pass |
| Received internal links | Can be derived from outgoing links of others | Cross-link pass |

### Taxonomies (term assignment)

- **helmet_type**, **certification**, **feature_tag**, **accessory_category**, **helmet_brand** (if used): AI suggests term slugs/names; code resolves to term IDs and assigns via `wp_set_object_terms`. Validation: only assign existing terms; create none by default (or optional “create if missing” in config).

### Cross-linking

- **When updating**: After filling meta/terms, a “cross-link” step can suggest or write “related” links (e.g. same brand, same type, same certification) into a meta field (e.g. `outgoing_internal_links_json`) or blocks.
- **Bidirectional**: “Received” links can be computed by scanning other entities’ outgoing links and updating a “received” meta or not storing at all and deriving on display.

---

## 3. AI fill-missing: deeper logic and relations

- **Context**: Pass brand name, existing terms (helmet_type, certification), price range, and family into the fill-missing context so AI outputs are consistent with the rest of the catalog.
- **Validation**: After AI returns a value, validate (allowed_values, max_length, existing term slugs). If invalid, retry with feedback or skip and log.
- **Relations**: When AI suggests “helmet_type” or “certification”, resolve to taxonomy term IDs and assign; when it suggests “brand”, resolve to post ID and set `rel_brand`.
- **Fill order**: Fill meta first, then taxonomies that depend on meta (e.g. “use case” from description). Then run SEO seed so meta description can use the enriched data.
- **Modes**: “Fill only empty” (default), “Overwrite” (optional flag for admin), “Dry-run” (suggest only, no write).

---

## 4. SEO seed (meta descriptions): improved logic and relations

- **Relations**: Use brand, helmet_type, certification, feature_tag, and price in the prompt and in template fallbacks so generated title/meta/keyphrase are consistent with the entity.
- **Readability / length**: Enforce max length (e.g. 60 title, 160 meta); optionally ask AI for “short, scannable” meta and run a simple readability check (store score in meta if desired).
- **Templates**: Improve non-AI fallbacks to include type, certs, and price so even without AI the page has good SEO.
- **Keyphrase**: Derive from brand + model + type; allow AI to refine; validate length.
- **Internal links**: SEO seed does not create links; a separate “cross-link” step (or fill-missing extension) writes suggested internal links into meta.

---

## 5. More low-cost / free AI providers

Integrate and document these so the plugin can use them for SEO and fill-missing (with descriptions and details in admin).

| Provider | Tier | Notes |
|----------|------|--------|
| **Groq** | Free / low-cost | Fast, free tier; already integrated. Use for meta desc and fill-missing. |
| **Google Gemini** | Free tier | Good for longer copy; already integrated. |
| **Mistral** | Free / low-cost | Good balance; already integrated. |
| **Hugging Face** | Free (rate limits) | Use Inference API; already integrated. |
| **OpenRouter** | Pay-per-use, many models | Access to Llama, Mistral, etc. with one key; already integrated. |
| **OpenAI** | Paid | Already integrated; use when quality justifies cost. |
| **Perplexity** | Paid | Already integrated for research-style queries. |
| **Together.ai** | Free tier / low-cost | Add as new provider; low-cost inference. |
| **Fireworks.ai** | Free tier / low-cost | Add as new provider; fast, many OSS models. |
| **Cohere** | Free tier | Add as new provider; good for classification/short text. |
| **Anthropic Claude** | Paid / free tier | Add if not present; good for nuanced copy. |

Admin: list each provider with “Tier”, “Best for” (e.g. “Meta descriptions”, “Fill-missing”, “Long copy”), and link to pricing/signup.

---

## 6. Where it applies: seeder, standalone, AI admin

| Flow | Seeder/ingest | Standalone CLI | AI admin (quick actions) |
|------|----------------|----------------|--------------------------|
| **Unique id + hash** | Yes | N/A (operates on existing posts) | N/A |
| **Fill empty meta** | Optional post-step | `wp helmetsan ai fill-missing` | “Fill missing” button |
| **Assign taxonomies** | From payload if present | Fill-missing can suggest/assign | Same |
| **SEO seed** | Optional post-step | `wp helmetsan seo seed` | “Generate SEO” per entity |
| **Cross-link** | Optional post-step | `wp helmetsan ai cross-link` (new) | Optional “Suggest links” |
| **Provider choice** | Same as plugin settings | Same | Same |

So: **one set of rules** (unique id, hash, fill-only-empty, validation, taxonomy resolution, cross-link) used everywhere; only the trigger differs (batch ingest, CLI, or single-post admin).

---

## 7. Implementation phases (suggested)

- **Phase A — Seeder consistency**: Harden ingestion to skip unchanged by hash; document unique ids for brands/accessories; add validation before upsert.
  - **Done:** Helmet and accessory ingest paths now count **skipped** when hash unchanged (accessory branch in `IngestionService` fixed to treat `action === 'skipped'` as skipped). Brands and accessories already use `_brand_unique_id` / `_accessory_unique_id` and `_source_hash`. See **`docs/ingestion-unique-ids-and-hash.md`** for canonical unique keys and hash-based skip for all entity types.
- **Phase B — Fill-missing extended**: `FillableFieldsConfig` already includes extended meta (use_case, price_range, model_year, yoast_title, yoast_metadesc, yoast_focuskw, outgoing_internal_links_json) and `taxonomyFillableConfig()` / `yoastMetaMapping()`. **(Done)** (1) Yoast meta is synced when filling yoast_* keys. (2) FillMissingService now fills missing taxonomy terms (helmet_type, certification, feature_tag, helmet_brand, accessory_category): AI suggests from existing term names only; code resolves to term and calls `wp_set_object_terms`. Use `--no-taxonomies` to skip taxonomy fill. (3) Fill order: meta first, then taxonomies for the same post.
- **Phase C — SEO seed relations**: **(Done)** (1) **Helmet SEO**: YoastSeoSeeder passes `helmet_family`, `feature_tags`, `use_case` into AI context; fallback template includes family when set; focus keyword includes helmet type when it fits in 60 chars. AiSeoDescriptionProvider and ContextBuilder::forSeoDescription use the same extended context (family, features, use_case) in prompts. (2) **Brand SEO**: Brand meta `brand_motto` and `brand_story` (trimmed snippet) are passed to AI for richer meta descriptions; fallback unchanged. (3) **Accessory SEO**: Focus keyword now includes category when not generic (e.g. "Pinlock 120 Visor") and is truncated to 60 chars.
- **Phase D — Cross-link**: **(Done)** (1) **CrossLinkService** (`includes/CrossLink/CrossLinkService.php`): `suggestForPost($postId)` returns related links by entity type — helmets: same brand, same helmet_type, same certification, same helmet_family (up to 10 links); brands: links to that brand’s helmets; accessories: same accessory_category. Stored format: `outgoing_internal_links_json` = JSON array of `{post_id, url, reason}`. (2) **CLI** `wp helmetsan ai cross-link [--post-type=helmet|brand|accessory|all] [--limit=N] [--offset=N] [--dry-run]` runs the service and optionally writes meta. “Received” links can be computed later by scanning other posts’ outgoing links.
- **Phase E — Providers**: **(Done)** Added **Together AI**, **Fireworks AI**, **Cohere**, and **Anthropic (Claude)**. Together and Fireworks use OpenAI-compatible chat endpoints; Cohere uses v1 chat; Anthropic uses Messages API. All four are in Config and ProviderRegistry (Together, Fireworks, Cohere = free; Anthropic = premium). Admin AI page shows a **Best for** column for every provider.

This roadmap should be used both when running the **seeder**, when running **standalone** CLI commands, and when using **AI mode** in the admin for updating metadata.
