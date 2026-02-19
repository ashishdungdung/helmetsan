# Deterministic Variant Data — Implementation Plan

> **Status:** Complete  
> **Date:** February 2026

## Overview

Transform the helmet catalog from randomly-generated variants to a **deterministic, e-commerce-ready data engine** where the GitHub JSON is the single source of truth.

> [!IMPORTANT]
> This converts 242 models × ~5 colorways each = **~1,200 hand-defined variant entries** in `create_helmets_seed.php`. The top brands (Shoei, Arai, AGV, Bell, HJC, Schuberth) get real-world colorway names.

> [!WARNING]
> **Breaking change:** Variant IDs change from `shoei_rf_1400_red` to `shoei_rf-1400_matte-black-iridium`. Existing WordPress posts with old IDs become orphans. A full reset + re-ingest is required.

## New Variant Schema

```php
'RF-1400' => [
    'type' => 'Full Face', 'price' => 579.99,
    'cert' => ['DOT', 'Snell M2020'], 'shape' => 'Intermediate Oval',
    'colorways' => [
        [
            'name'         => 'Matte Black',
            'color_family' => 'Black',
            'sku'          => 'SHO-RF14-MBK',
            'price_adj'    => 0,
            'finish'       => 'matte',
            'is_graphic'   => false,
            'availability' => 'in_stock',
        ],
        [
            'name'         => 'Nocturne TC-1',
            'color_family' => 'Red',
            'sku'          => 'SHO-RF14-NOC',
            'price_adj'    => 50,       // Graphics cost more
            'finish'       => 'gloss',
            'is_graphic'   => true,
            'availability' => 'in_stock',
        ],
    ],
],
```

### Color Family Values

Standardized set for faceted filtering across the catalog:

| Color Family    | Example Colorways                        |
| --------------- | ---------------------------------------- |
| Black           | Matte Black, Gloss Black, Black Metallic |
| White           | Gloss White, Pearl White                 |
| Red             | Nocturne TC-1, Salvatore Red, Racing Red |
| Blue            | Blue Metallic, Ascend TC-7, Navy         |
| Yellow / Hi-Viz | Hi-Viz Yellow, Neon Yellow               |
| Grey / Silver   | Basalt Grey, Anthracite, Silver Metallic |
| Green           | Military Green, Kawasaki Green           |
| Orange          | KTM Orange, Fluo Orange                  |
| Multi / Graphic | Custom graphics with 2+ colors           |
| Carbon          | Exposed carbon weave finish              |

### Generated JSON Output

```json
{
  "id": "shoei_rf-1400_nocturne-tc-1",
  "title": "Shoei RF-1400 Nocturne TC-1",
  "type": "variant",
  "parent_id": "shoei_rf-1400",
  "color": "Nocturne TC-1",
  "color_family": "Red",
  "sku": "SHO-RF14-NOC",
  "finish": "gloss",
  "is_graphic": true,
  "availability": "in_stock",
  "price": { "usd": 629.99, "eur": 579.59, "gbp": 497.59 },
  "sizes": ["XS", "S", "M", "L", "XL", "2XL"]
}
```

## Proposed Changes

### Data Layer

#### [MODIFY] create_helmets_seed.php

1. Add `colorways` array to each model definition
2. Remove random variant generation loop
3. Build variants deterministically from `colorways`
4. Generate deterministic variant IDs: `{brand}_{model}_{colorway-slug}`
5. Generate deterministic SKUs: `{BRAND_PREFIX}-{MODEL_CODE}-{COLOR_CODE}`

### Ingestion Layer

#### [MODIFY] IngestionService.php

Add handling for new variant meta fields:

- `color_family` → `_color_family` meta
- `sku` → `_sku` meta
- `finish` → `_finish` meta
- `is_graphic` → `_is_graphic` meta
- `availability` → `_availability` meta

## Verification Plan

```bash
# 1. Validate seed — zero duplicates
php create_helmets_seed.php --validate

# 2. Regenerate and check
php create_helmets_seed.php --output=helmetsan-core/seed-data/helmets_seed.json --stats

# 3. Deploy + ingest
./scripts/reseed.sh
```

### Data Integrity Checks

- Every model has ≥ 2 colorways
- Every colorway has a unique SKU
- `color_family` values from standardized set
- No duplicate variant IDs across entire catalog
