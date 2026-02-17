# Helmetsan Data Repository

This directory is the GitHub source-of-truth for structured datasets consumed by the Helmetsan plugin/theme.

## Entity Folders

- `brands/`: brand profile entities (`entity: brand`)
- `helmets/`: helmet model entities (`entity: helmet`) with geo pricing, legality, and certification docs
- `accessories/`: accessory entities (`entity: accessory`)
- `motorcycles/`: motorcycle entities (`entity: motorcycle`)
- `safety-standards/`: safety standard entities (`entity: safety_standard`)
- `helmet-types/`: helmet taxonomy entities (`entity: helmet_type`)
- `geo/countries/`: country reference entities (`entity: country`)
- `geo/regions/`: region reference entities (`entity: region`)

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
