# Enrichment process: updating blank or existing fields

How to fill **blank** fields and refresh **existing** SEO/cross-link data for helmets, accessories, and brands.

**Data-flow context:** Enrichment runs **after** ingestion. Ingestion reads JSON from disk and writes to WordPress only (it does not write to GitHub). To get enriched data back into the repo: **export** entities to JSON (Import/Export), then **sync push**. See [Data flow: JSON ↔ WordPress ↔ GitHub](data-flow.md).

---

## Order of operations

Run in this order so later steps can use the data from earlier ones:

1. **Fill-missing** (AI) — fills **only blank** meta and taxonomy terms.
2. **SEO seed** — writes/overwrites Yoast title, meta description, focus keyword (template or AI).
3. **Cross-link** — writes/overwrites suggested internal links (`outgoing_internal_links_json`).

---

## 1. Fill-missing (blank fields only)

**Behavior:** For each post, only **empty** fillable meta and (optionally) **empty** taxonomy terms are filled. Existing values are **never overwritten**. No “overwrite all” mode.

**Entity types:** `helmet`, `brand`, `accessory`, or `all`.

**Helmets:** Meta (e.g. `spec_shell_material`, `helmet_family`, `head_shape`, `technical_analysis`, `use_case`, `price_range`, `model_year`, `spec_shell_sizes`, `yoast_title`, `yoast_metadesc`, `yoast_focuskw`, `outgoing_internal_links_json`; structured JSON such as `safety_intelligence_json`, `aero_acoustic_profile_json`, `tech_integration_json`, `fitment_coordinates_json` when configured as fillable) and taxonomies: `helmet_type`, `certification`, `feature_tag`, `helmet_brand`.

**Brands:** Meta only (e.g. `brand_story`, `brand_motto`, `brand_manufacturing_ethos`, `brand_origin_country`, `brand_founded_year`, `brand_warranty_terms`, `brand_certification_coverage`). No taxonomies.

**Accessories:** Meta (e.g. `accessory_type`, `accessory_parent_category`, `accessory_subcategory`, `accessory_color`) and taxonomy: `accessory_category`.

**CLI:**

```bash
# All entity types, default limit 50 per type
wp helmetsan ai fill-missing

# Helmets only, up to 200, with taxonomy fill
wp helmetsan ai fill-missing --post-type=helmet --limit=200

# Brands and accessories only
wp helmetsan ai fill-missing --post-type=brand --limit=0
wp helmetsan ai fill-missing --post-type=accessory --limit=0

# Only posts that have at least one empty fillable field (faster)
wp helmetsan ai fill-missing --only-incomplete

# Specific fields only
wp helmetsan ai fill-missing --post-type=helmet --fields=use_case,helmet_family,head_shape

# Preview without saving
wp helmetsan ai fill-missing --dry-run --verbose

# Skip taxonomy fill (meta only)
wp helmetsan ai fill-missing --no-taxonomies
```

**Requirements:** At least one AI provider configured under **Helmetsan → AI**.

### Using a local LLM (LM Studio / Zed)

You can run enrichment using a model loaded in **LM Studio** (or any OpenAI-compatible local server) so the plugin uses your local LLM instead of a cloud API:

1. **LM Studio:** Start LM Studio, load a model, and enable the **Local Server** (e.g. `http://localhost:1234`). Leave it running.
2. **Plugin:** In **Helmetsan → AI**, under free providers enable **LM Studio (local)**. Set **Base URL** to `http://localhost:1234/v1` (or your server URL + `/v1`). Set **Model** to the name shown in LM Studio (e.g. `local` or the model id). No API key needed.
3. **CLI:** Run fill-missing or SEO seed as usual; the plugin will call your local endpoint.

**Zed IDE:** If you use Zed with an LM Studio–backed LLM, the same LM Studio server can be used by the plugin. Run LM Studio’s local server; point Zed’s LLM at it for editing, and point Helmetsan’s AI settings at it for batch enrichment (fill-missing, SEO seed). For one-off edits you can enrich content in Zed; for bulk blanks use `wp helmetsan ai fill-missing` with the LM Studio provider selected as default free.

---

## 2. SEO seed (overwrites Yoast fields)

**Behavior:** For each post, **overwrites** Yoast SEO title, meta description, and focus keyword (from templates or AI). Not blank-only; every processed post is updated.

**Entity types:** `helmet`, `brand`, `accessory`, or `all` (via `--post-type`).

**CLI:**

```bash
# Template-only (no AI), all types
wp helmetsan seo seed

# With AI-generated meta descriptions (uses configured providers)
wp helmetsan seo seed --use-ai

# Helmets only, limit 100
wp helmetsan seo seed --use-ai --post-type=helmet --limit=100

# Preview
wp helmetsan seo seed --dry-run
```

Use **`--use-ai`** to generate meta descriptions with AI; otherwise templates are used. Titles and focus keywords are always built from templates/logic.

---

## 3. Cross-link (overwrites suggested links)

**Behavior:** For each post, **overwrites** `outgoing_internal_links_json` with suggested related links (same brand, type, certification, etc.). Not blank-only.

**Entity types:** `helmet`, `brand`, `accessory`, or `all`.

**CLI:**

```bash
# All types
wp helmetsan ai cross-link --post-type=all

# Helmets only, limit 50
wp helmetsan ai cross-link --post-type=helmet --limit=50

# Preview
wp helmetsan ai cross-link --dry-run
```

No AI required; suggestions are rule-based from taxonomies and relations.

---

## “Update or enrich all” in practice

| Goal | What to run |
|------|-------------|
| **Fill only blank** meta/taxonomies (helmets, brands, accessories) | `wp helmetsan ai fill-missing --post-type=all --limit=0` (or per type with desired limit). |
| **Refresh SEO** (title, meta desc, focus kw) for all helmets/brands/accessories | `wp helmetsan seo seed --use-ai --post-type=all` (or omit `--post-type` for all). |
| **Refresh internal link suggestions** for all | `wp helmetsan ai cross-link --post-type=all --limit=0`. |
| **Full enrichment** (fill blanks, then SEO, then cross-link) | Run the three commands in order (fill-missing → seo seed → cross-link). |

**Overwriting existing meta (e.g. re-fill with AI):** Fill-missing has **no overwrite mode**; it only fills empty fields. To change existing meta you must clear it first (e.g. manually or via a custom script), then run fill-missing. SEO seed and cross-link **always overwrite** their targets (Yoast meta and `outgoing_internal_links_json`) for every post they process.

---

## Scheduled enrichment

Under **Helmetsan → Scheduler → AI Enrichment** you can enable periodic **fill-missing + SEO seed** for **helmets only**, with configurable limits and interval. Cross-link is not included in the scheduler; run it manually or add a cron job if needed.

---

## Admin quick actions

**Helmetsan → AI** provides “Dry run (last 10 helmets)” and “Fill last 10 helmets” for a quick test. These use the same FillMissingService with a small limit.
