# SEO and AI coverage: CPTs, taxonomies, and rules

Reference for what gets SEO-seeded and AI-filled across the site, and how.

## Scope

- **SEO seeder** sets Yoast SEO title, meta description, and focus keyphrase for:
  - **Posts (CPTs):** helmet, brand, accessory, safety_standard, dealer, distributor, technology, motorcycle, comparison, recommendation.
  - **Taxonomy terms:** helmet_type, region, certification, feature_tag, accessory_category, helmet_brand, use_case, price_range (so archive pages have SEO meta).
- **AI fill-missing** populates missing meta and taxonomy terms for helmets (then brands, accessories) so that:
  - Helmets get: helmet_type, region, certification, feature_tag, helmet_brand, use_case (taxonomy + meta), price_range (taxonomy + meta).
  - Brands: (as configured in FillableFieldsConfig).
  - Accessories: accessory_category, type/parent_category/subcategory/color, etc.
- **Same quality bar** applies to data used to populate all CPTs (helmets, accessories, brands, safety standards, dealers, distributors, technologies, comparisons, recommendations): consistent structure and SEO-friendly content.

## SEO rules (seeder / updater / sync)

1. **Focus keyphrase:** Always stored in **lowercase**. Improves consistency and matching.
2. **Meta description:** Should be SEO-strong:
   - Include primary keyword near the start.
   - Compelling, clear value proposition.
   - Call-to-action where appropriate (e.g. “Compare”, “Shop”, “Find”).
   - Stay within 160 characters; no generic filler.

These rules apply to both the SEO seeder and to any AI-generated or synced SEO fields used for CPTs and taxonomy terms.

## CPTs and their taxonomies (for SEO + fill-missing)

| CPT              | Taxonomies / meta to seed or fill |
|------------------|-----------------------------------|
| **Helmet**       | helmet_type, region, certification, feature_tag, helmet_brand, use_case, price_range; SEO title/desc/focuskw. |
| **Brand**        | helmet_type, region (if used); SEO. |
| **Accessory**    | helmet_type, region, feature_tag, accessory_category, use_case, price_range; SEO. |
| **Safety standard** | region, certification; SEO. |
| **Dealer**       | region; SEO. |
| **Distributor**  | region; SEO. |
| **Technology**   | feature_tag; SEO. |
| **Comparison**   | region; SEO. |
| **Recommendation** | region; SEO. |
| **Motorcycle**   | (as needed); SEO. |

## Running order

1. **AI fill-missing** (helmets first): `wp helmetsan ai fill-missing --post-type=helmet --limit=0` so helmet types, regions, certifications, feature tags, use cases, price ranges (and meta) are filled.
2. **Accessories:** fill-missing then `backfill-accessory-categories`; use `--refill-unmapped` if many have no category.
3. **SEO seed** for all CPTs and terms: `wp helmetsan seo seed --post-type=all --scope=all` (or `--scope=posts` for CPTs only, `--scope=terms` for term archives only). Single type: `--post-type=helmet`, `--post-type=safety_standard`, etc.

## From time to time

- Scheduler can run SEO seed (and optionally fill-missing) for configured CPTs so SEO and key data stay updated.
- **Terms:** Enable `enrichment_seo_terms_enabled` in scheduler config to seed SEO for taxonomy term archives (helmet_type, region, certification, etc.) each run.
- **Other CPTs:** Enable `enrichment_seo_other_cpts_enabled` and set `enrichment_seo_other_cpts_limit` to seed safety_standard, dealer, distributor, technology, motorcycle, comparison, recommendation.
- Configure under Helmetsan → Settings (scheduler / enrichment) which post types and how often.
