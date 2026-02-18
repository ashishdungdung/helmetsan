# Helmetsan Data Repository

This directory is the GitHub source-of-truth for structured datasets consumed by the Helmetsan plugin/theme.

## Entity Folders

- `brands/`: brand profile entities (`entity: brand`)
- `helmets/`: helmet model entities (`entity: helmet`) with geo pricing, legality, and certification docs
- `accessories/`: accessory entities (`entity: accessory`)
- `motorcycles/`: motorcycle entities (`entity: motorcycle`)
- `safety-standards/`: safety standard entities (`entity: safety_standard`)
- `dealers/`: store/shop entities (`entity: dealer`) for local/offline/online presence
- `distributors/`: authorized distributor entities (`entity: distributor`) with warehouse and contact data
- `currencies/`: currency reference entities (`entity: currency`)
- `marketplaces/`: country/region marketplace entities (`entity: marketplace`)
- `pricing/`: helmet-country-marketplace price records (`entity: pricing`)
- `offers/`: offer discovery records (`entity: offer`) used to compute best-offer snapshots
- `comparisons/`: multi-helmet comparison entities (`entity: comparison`)
- `recommendations/`: use-case recommendation entities (`entity: recommendation`)
- `catalogs/`: controlled vocabulary/master lists (brands, helmet types, colors, head shapes, certifications, features, helmet families)
- `helmet-types/`: helmet taxonomy entities (`entity: helmet_type`)
- `geo/countries/`: country reference entities (`entity: country`)
- `geo/regions/`: region reference entities (`entity: region`)
- `phases/`: phased enrichment plans and quality gates

## Schemas

JSON Schemas are in `schemas/` and should be used by AI agents and CI validation before commits.

## Brand Enrichment Fields

Brand `profile` supports:

- `origin_country`
- `warranty_terms`
- `support_url`
- `support_email`
- `manufacturing_ethos`
- `distributor_regions`
- `size_chart_json`
- `total_models`
- `helmet_types`
- `certification_coverage`

## Recommended Workflow

1. Add/update JSON in this directory.
2. Validate schema with CI/CLI.
3. Run plugin sync pull profile `pull+brands` or `pull+all`.
4. Review admin screens (`Catalog`, `Brands`, `Go Live`).

## Helmet Family Model

`Helmet Family` represents model/product-line grouping across multiple variants:

- Colorways
- Graphics/replicas
- Carbon / MIPS / DLX style editions
- Year refreshes

Examples:

- `Shoei RF1400`
- `AGV K6 S`
- `Icon Airflite`

This enables catalog filtering independent of brand/type/certification.

## Phased Enrichment Policy

Global brand coverage is managed in phased batches via `phases/brand-enrichment-phases.json` to keep data quality high and prevent noisy bulk ingestion.
