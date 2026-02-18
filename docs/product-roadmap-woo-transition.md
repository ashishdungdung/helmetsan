# Helmetsan Data-Bank to WooCommerce Transition Roadmap

## Goal
Build a data-first helmet intelligence platform now, then transition into direct commerce with minimal model churn.

## Core Data Model (Current)
- `helmet` (CPT) remains canonical product knowledge object.
- Rich JSON-backed meta:
  - `variants_json`
  - `product_details_json`
  - `part_numbers_json`
  - `sizing_fit_json`
  - `related_videos_json`
  - `geo_pricing_json`
  - `pricing_records_json`
  - `offers_json`
  - `best_offer_json`

## Relationship Model
- `helmet` -> `brand` (meta `rel_brand`)
- `helmet` -> `accessory` (compatibility)
- `helmet` -> `safety_standard` (taxonomy `certification`)
- `helmet` -> `comparison` (through `comparison` CPT meta)
- `helmet` -> `recommendation` (through `recommendation` CPT meta)
- `dealer` and `distributor` represent physical/commercial channels.

## Variant Strategy
Each helmet contains multiple sellable variants with geo-sensitive availability and pricing.
Variant keys:
- `sku`
- `mfr_part_number`
- `style` / `graphics` / `color`
- `size`, `size_cm`, `size_in`
- per-variant price/currency/geo pricing

## WooCommerce Mapping (Future)
When commerce is enabled:
- Create Woo Product per Helmet Family (or per core helmet model).
- Create Woo variations from `variants_json`.
- Map fields:
  - `color` -> `pa_color`
  - `size` -> `pa_size`
  - `style/graphics` -> `pa_style`
  - `mfr_part_number` -> variation meta
  - geo pricing -> price sync rules by region/country
- Keep `helmet` CPT as editorial/spec authority.
- Link Woo product IDs in `helmet` meta (`wc_product_id`, `wc_variation_map_json`).

## Product Page UX Pattern
Adopt RevZilla-style depth with data-native sections:
1. Product details (style, MFR product number, sizing & fit summary)
2. Variants table (color/size/part no/price/availability)
3. Sizing & fit table (cm + inch translation)
4. Certification/legal/geo pricing
5. Related videos
6. Compatible accessories

## Phased Delivery
1. Data quality and schema depth
2. Variant coverage by top families/brands
3. Geo pricing and offer confidence scoring
4. Woo bridge service for product + variation upsert
5. Checkout/logistics enablement

## Guardrails
- Batch imports only; avoid one-shot mega ingestion.
- Schema-first validation before sync/apply.
- Keep SEO pages stable; avoid URL churn during Woo onboarding.
- Preserve meta parity between data-bank CPT and Woo entities.
