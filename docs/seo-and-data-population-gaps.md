# SEO and data population – gaps and missing pieces

This document lists what is **missing or inconsistent** in the current SEO seeder, fill-missing, ingestion, and import flows—especially for **missing data and population**.

---

## 1. Ingestion (JSON → WordPress)

### 1.1 ~~Main ingest path only handles helmet + accessory~~ (extended)

- **`ingestPath` / `ingestSeedFile`** (and thus **`wp helmetsan ingest-seed`** and Data/Reseed “Ingest path”) only route to:
  - **Helmet** (when payload is not detected as accessory)
  - **Accessory** (when payload has `accessory_type`, `compatible_helmet_types`, or `type` not in helmet-type canonical)
- **Brand**: Created on the fly during helmet ingest via `findOrCreateBrand()` when helmet JSON has `brand`; no dedicated “ingest brands from `data/brands/`” in this path. Sync “apply brands” may use a different flow.
- **Safety standard, dealer, distributor, comparison, recommendation, technology, motorcycle**: Not handled by the main ingest path at all. They are only created/updated via **Import/Export → Import JSON** (ImportService), which dispatches by `entity` in each record.

**Gap:** If you drop JSON files for safety_standard, dealer, etc. under `data/` and run “Ingest path”, they are **not** processed. You must use Import JSON (single file with array of records and `entity` field).

---

### 1.2 ~~Helmet ingestion does not set region, use_case, or price_range taxonomies~~ (implemented)

- Helmet upsert in **IngestionService** sets **region** (from `region` / `regions` / `geo_pricing` keys), **use_case** (from `use_case` / `use_cases`), and **price_range** (from `price_range` or derived from price USD). Fill-missing remains for backfilling when source JSON lacks these.


---

### 1.3 ~~Comparison import does not set region taxonomy~~ (implemented)

- **ComparisonService::upsertFromPayload()** now calls `wp_set_object_terms($postId, …, 'region', false)` when `data['region']` is present (string or array).

---

### 1.4 ~~Recommendation import: region only in meta, not in taxonomy~~ (implemented)

- **RecommendationService::upsertFromPayload()** keeps meta `recommendation_region` and now also calls `wp_set_object_terms($postId, …, 'region', false)` when `data['region']` is present (string or array).

---

## 2. Fill-missing (AI)

**Coverage reporting:** Before or after running fill-missing, you can see per-field coverage (set vs empty, % complete) with no API calls: **CLI** `wp helmetsan ai fill-missing --report --post-type=helmet` (or brand/accessory/all), or **Admin** Helmetsan → AI → **Fill coverage report** (choose post type, then "Show coverage"). Use this to identify which fields need filling or to confirm coverage after a run.

### 2.1 Only helmet, brand, accessory have fill-missing

- **FillableFieldsConfig::forPostType()** and **taxonomyFillableConfig()** only define entries for **helmet**, **brand**, **accessory**.
- **safety_standard, dealer, distributor, technology, motorcycle, comparison, recommendation** have **no** fill-missing: no AI-driven population of missing meta or taxonomy terms.

**Gap:** If you want “from time to time” AI to fill missing fields (e.g. region, certifications, feature tags) on safety_standard, dealer, distributor, comparison, recommendation, technology, motorcycle, you’d need to extend FillableFieldsConfig and FillMissingService to support these CPTs (and their registered taxonomies).

---

### 2.2 Brand: no Yoast fields in fillable

- **forBrand()** does not include `yoast_title`, `yoast_metadesc`, `yoast_focuskw`. So fill-missing never suggests Yoast for brands; only the **SEO seeder** sets them.
- This is likely intentional (one place for SEO), but it means “fill missing SEO for brands” is done only by the seeder, not by the generic fill-missing pipeline.

---

## 3. SEO seeder

### 3.1 Other CPTs use a generic template only

- **buildSeoForGenericCpt()** (used for safety_standard, dealer, distributor, technology, motorcycle, comparison, recommendation) builds title/description/focuskw from **post title + CPT label** only. It does **not** use:
  - Existing **meta** (e.g. `dealer_country_code`, `recommendation_region`, `distributor_regions_json`)
  - Assigned **taxonomy terms** (region, certification, feature_tag)

So even when a dealer/comparison/recommendation has region or other data, the SEO meta description and focus phrase are generic, not data-driven.

**Gap:** Optionally enhance **buildSeoForGenericCpt()** (or add CPT-specific builders) to use post meta and taxonomy terms so meta descriptions and focus keyphrases are richer and more SEO-relevant (e.g. “Dealer X – North America | Dealers | Helmetsan”, “Recommendation Y – India | Recommendations | Helmetsan”).

---

### 3.2 No dedicated “SEO checker” or “SEO updater” CLI

- You have **seo seed** (and scheduler) to set/overwrite Yoast title, meta description, focus keyphrase.
- There is **no** CLI or job that:
  - **Checks** existing Yoast meta (e.g. missing focus keyphrase, meta description too short/long, focus keyphrase not lowercase).
  - **Updates only missing or invalid** fields (leaving valid ones untouched).

**Gap:** If you want a “checker and updater” (as in “seeder; checker and updater”), you’d add e.g. `wp helmetsan seo check` and/or `wp helmetsan seo update` that validate and optionally fix existing SEO meta (e.g. enforce lowercase focus keyphrase, suggest/apply meta description when empty).

---

## 4. Scheduler

- Scheduler already supports:
  - Fill-missing + SEO seed for helmet, brand, accessory (with per-type limits).
  - Optional **term SEO** (`enrichment_seo_terms_enabled`) and **other CPTs SEO** (`enrichment_seo_other_cpts_enabled` + limit).
- No additional scheduler gaps identified for “from time to time” SEO; the main gaps are **data population** (ingestion/import) and **fill-missing coverage** for other CPTs.

---

## 5. Summary table

| Area | Status |
|------|--------|
| **Ingestion** | **Extended.** Ingest path dispatches by `entity` when payload has entity field (brand, motorcycle, safety_standard, dealer, distributor, comparison, recommendation); one file = one entity. Technology/commerce still via Import JSON. |
| **Helmet ingest** | **Done.** Sets region, use_case, price_range from JSON (IngestionService). |
| **Comparison import** | **Done.** Sets region taxonomy when payload has region. |
| **Recommendation import** | **Done.** Sets region taxonomy and keeps recommendation_region meta. |
| **Fill-missing** | **Done.** FillableFieldsConfig + taxonomy fill for all supported CPTs; `ai fill-missing --post-type=all`. |
| **SEO generic CPTs** | **Done.** getGenericCptSeoContext + buildSeoForGenericCpt use meta and taxonomies per CPT. |
| **SEO tooling** | **Done.** `wp helmetsan seo check` and `wp helmetsan seo update` validate/fix Yoast meta. |


---

## 6. Suggested order of fixes (for missing data and population)

1. **Recommendation:** In RecommendationService upsert, when `data['region']` is present, call `wp_set_object_terms($postId, … , 'region', false)` (and optionally normalize to existing region terms). **DONE.**
2. **Comparison:** If comparison payload has a region field, add `wp_set_object_terms(…, 'region', false)` in ComparisonService upsert. **DONE.**
3. **Helmet ingestion:** In IngestionService::upsertHelmet(), if JSON has region / use_case / price_range (or derivable from price/geo), set `region`, `use_case`, `price_range` taxonomies. **DONE.**
4. **SEO checker/updater:** Add CLI commands (e.g. `seo check`, `seo update`) to enforce lowercase focus keyphrase, validate length, and optionally fill empty meta descriptions. **DONE.** (`wp helmetsan seo check`, `wp helmetsan seo update`)
5. **Optional:** Richer SEO for other CPTs using meta + taxonomies in buildSeoForGenericCpt or CPT-specific builders. **DONE.** (getGenericCptSeoContext + buildSeoForGenericCpt)
6. **Optional:** Extend fill-missing to safety_standard, dealer, distributor, comparison, recommendation (and optionally technology, motorcycle) with appropriate meta and taxonomy config in FillableFieldsConfig. **DONE.** (forPostType + taxonomyFillableConfig extended; CLI `ai fill-missing --post-type=all` includes all CPTs)

This document can be updated as further gaps are closed.
