# Helmetsan ASIN & Global Identifier Manager  
## Strategic Architecture & Planning Specification — ASIN Manager V1.0

## 1. Executive Architecture

Helmetsan should not treat an ASIN as a single field attached directly to a helmet. It should treat product identity as a **hierarchical, evidence-backed identity graph**:

```text
Brand
 └── Product Family
      └── Parent Helmet
           ├── Variant: color / graphic / finish
           │    ├── size
           │    ├── region
           │    └── retailer / marketplace listings
           └── Related accessories / replacement parts
```

The core design principles are:

1. **Separate product identity from marketplace listing identity.**
2. **Store identifiers at the lowest valid level.**
3. **Never silently trust or overwrite a colliding identifier.**
4. **Preserve source, timestamp, confidence, and verification history.**
5. **Distinguish verified identifiers from placeholders and search results.**
6. **Support marketplace and regional variation.**
7. **Use fallback affiliate search links when no verified ASIN is available.**
8. **Keep SQLite as the operational source of truth while supporting in-memory resolution and live synchronization.**

The audit findings indicate that the current catalog contains a mixture of:

- Valid identifiers,
- Parent-level identifiers incorrectly copied to many products,
- Synthetic placeholders,
- Missing variant-level identifiers,
- Regionally incomplete Amazon data,
- Data-model inconsistencies.

The first release must therefore be an **identity normalization and quarantine system**, not merely an ASIN lookup screen.

---

# 2. Canonical Product Identity Model

## 2.1 Entity hierarchy

### Primary entities

```text
brand
product_family
helmet
helmet_variant
variant_size
accessory
motorcycle
marketplace_listing
identifier
identifier_observation
identifier_collision
enrichment_job
affiliate_link
```

### Recommended hierarchy

```text
helmet
 ├── product_family_id
 ├── parent_model_name
 ├── manufacturer
 ├── model_code
 └── variants[]
      ├── color_name
      ├── graphic_name
      ├── finish
      ├── variant_code
      └── sizes[]
           ├── size_label
           └── identifiers / marketplace listings
```

A “parent helmet” represents the underlying model or product family. A variant represents a material, graphic, color, finish, or production configuration. A size may be represented either as a child variant or as a variant attribute depending on the manufacturer’s identity system.

### Important distinction

These are not necessarily equivalent:

- Shoei X-Fifteen
- Shoei X-Fifteen Matte Black
- Shoei X-Fifteen Matte Black, Medium
- Shoei X-Fifteen Marquez Graphic, Large

They may have:

- One shared Amazon parent ASIN,
- Different child ASINs,
- Different MPNs,
- Different EANs,
- Different retailer SKUs,
- Or no reliable marketplace relationship at all.

The database must support all possibilities.

---

# 3. Master Identifier Schema Taxonomy

## 3.1 Canonical JSON structure

A normalized helmet record should expose a stable structure similar to:

```json
{
  "id": "helmet_000123",
  "entity_type": "helmet",
  "brand": {
    "name": "Shoei",
    "normalized": "shoei"
  },
  "product_family": {
    "id": "family_shoei_x_fifteen",
    "name": "X-Fifteen"
  },
  "model": {
    "name": "X-Fifteen",
    "normalized_name": "shoei x-fifteen",
    "mpn": "X15",
    "manufacturer_model_code": "X15"
  },
  "identifiers": {
    "amazon": {
      "us": {
        "value": "B0XXXXXXXX",
        "status": "verified",
        "confidence": 0.98,
        "source": "amazon_pa_api",
        "verified_at": "2025-01-12T12:30:00Z"
      },
      "uk": null,
      "de": null,
      "in": null,
      "jp": null
    },
    "gtin": [
      {
        "value": "07612345678905",
        "type": "gtin-13",
        "scope": "parent",
        "status": "verified",
        "source": "manufacturer_catalog"
      }
    ],
    "ean_13": [],
    "upc_a": [],
    "mpn": [
      {
        "value": "X15",
        "normalized": "X15",
        "source": "manufacturer_catalog",
        "status": "verified"
      }
    ],
    "oem_sku": [],
    "retailer_skus": {
      "revzilla": [],
      "fc_moto": [],
      "motoin": [],
      "cyclegear": []
    }
  },
  "variants": [],
  "data_quality": {
    "identity_status": "partially_verified",
    "has_collision": false,
    "has_synthetic_identifier": false,
    "last_reviewed_at": "2025-01-12T12:30:00Z"
  }
}
```

However, this JSON should be a read model. Internally, identifiers should be stored in normalized relational tables rather than embedded as a single mutable object.

---

## 3.2 Identifier categories

### A. Amazon identifiers

```json
"amazon": {
  "us": "BXXXXXXXXX",
  "uk": "BXXXXXXXXX",
  "de": "BXXXXXXXXX",
  "in": "BXXXXXXXXX",
  "jp": "BXXXXXXXXX"
}
```

Each marketplace value should actually be represented internally with metadata:

```json
{
  "marketplace": "us",
  "asin": "B0XXXXXXXX",
  "scope": "variant",
  "status": "verified",
  "confidence": 0.98,
  "source": "amazon_pa_api",
  "source_record_id": "api-response-123",
  "first_seen_at": "2025-01-01T00:00:00Z",
  "last_verified_at": "2025-01-12T00:00:00Z"
}
```

Supported marketplace codes:

```text
us
uk
de
in
jp
ca
fr
it
es
au
mx
```

The V1 interface may display US, UK, DE, IN, and JP first, but the schema should not require a migration to add additional Amazon regions.

### B. Global trade identifiers

Supported values:

- GTIN-8
- GTIN-12 / UPC-A
- GTIN-13 / EAN-13
- GTIN-14
- ISBN where relevant
- ISSN only if future non-product catalog entities require it

Canonical storage:

```json
{
  "value": "07612345678905",
  "normalized_value": "07612345678905",
  "type": "gtin-13",
  "scope": "variant",
  "status": "verified",
  "issuer": "GS1",
  "source": "manufacturer"
}
```

Rules:

- Store digits only.
- Preserve the original formatted value separately if needed.
- Validate check digits.
- Do not convert a suspected sequential placeholder into a verified GTIN.
- Permit multiple GTINs for regional packaging or production revisions.

### C. Manufacturer identifiers

```json
"mpn": [
  {
    "value": "RF-1400-MB-M",
    "normalized": "RF1400MBM",
    "scope": "variant",
    "status": "verified"
  }
]
```

Support:

- MPN,
- model number,
- manufacturer color code,
- manufacturer graphic code,
- OEM SKU,
- production revision,
- homologation code where useful.

### D. Retailer identifiers

```json
"retailer_skus": {
  "revzilla": [
    {
      "value": "123456",
      "scope": "variant",
      "status": "observed",
      "source_url": "..."
    }
  ],
  "fc_moto": [],
  "motoin": [],
  "cyclegear": []
}
```

Retailer SKUs must not be treated as globally unique. Uniqueness is scoped to the retailer and, where necessary, region or storefront.

### E. Internal identifiers

Helmetsan should create its own immutable identifiers:

```text
helmet_id
family_id
variant_id
size_id
listing_id
identifier_observation_id
```

These IDs must never be derived from ASINs or EANs because marketplace identifiers can change, collide, or be reassigned in the source data.

---

# 4. Relational Data Model

A practical SQLite schema:

## 4.1 Core product tables

```sql
CREATE TABLE product_families (
  id TEXT PRIMARY KEY,
  brand TEXT NOT NULL,
  normalized_brand TEXT NOT NULL,
  name TEXT NOT NULL,
  normalized_name TEXT NOT NULL,
  manufacturer_model_code TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE TABLE helmets (
  id TEXT PRIMARY KEY,
  family_id TEXT NOT NULL,
  canonical_name TEXT NOT NULL,
  product_type TEXT NOT NULL DEFAULT 'helmet',
  source_record_id TEXT,
  identity_status TEXT NOT NULL DEFAULT 'unverified',
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  FOREIGN KEY (family_id) REFERENCES product_families(id)
);

CREATE TABLE helmet_variants (
  id TEXT PRIMARY KEY,
  helmet_id TEXT NOT NULL,
  color_name TEXT,
  graphic_name TEXT,
  finish TEXT,
  variant_code TEXT,
  normalized_label TEXT,
  source_record_id TEXT,
  identity_status TEXT NOT NULL DEFAULT 'unverified',
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  FOREIGN KEY (helmet_id) REFERENCES helmets(id)
);

CREATE TABLE variant_sizes (
  id TEXT PRIMARY KEY,
  variant_id TEXT NOT NULL,
  size_label TEXT NOT NULL,
  normalized_size TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL,
  FOREIGN KEY (variant_id) REFERENCES helmet_variants(id)
);
```

## 4.2 Identifier tables

```sql
CREATE TABLE identifiers (
  id TEXT PRIMARY KEY,
  identifier_type TEXT NOT NULL,
  normalized_value TEXT NOT NULL,
  raw_value TEXT,
  marketplace TEXT,
  scope TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'observed',
  is_synthetic INTEGER NOT NULL DEFAULT 0,
  is_quarantined INTEGER NOT NULL DEFAULT 0,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);

CREATE TABLE entity_identifiers (
  id TEXT PRIMARY KEY,
  entity_type TEXT NOT NULL,
  entity_id TEXT NOT NULL,
  identifier_id TEXT NOT NULL,
  relationship TEXT NOT NULL DEFAULT 'assigned',
  confidence REAL NOT NULL DEFAULT 0,
  source TEXT NOT NULL,
  source_url TEXT,
  source_record_id TEXT,
  first_seen_at TEXT NOT NULL,
  last_seen_at TEXT NOT NULL,
  verified_at TEXT,
  FOREIGN KEY (identifier_id) REFERENCES identifiers(id)
);
```

`entity_type` may be:

```text
helmet
variant
variant_size
accessory
motorcycle
marketplace_listing
```

`scope` may be:

```text
parent
variant
size
regional
listing
unknown
```

## 4.3 Amazon marketplace listings

```sql
CREATE TABLE marketplace_listings (
  id TEXT PRIMARY KEY,
  entity_type TEXT NOT NULL,
  entity_id TEXT NOT NULL,
  marketplace TEXT NOT NULL,
  asin TEXT NOT NULL,
  title TEXT,
  brand TEXT,
  model TEXT,
  color TEXT,
  size TEXT,
  availability TEXT,
  canonical_url TEXT,
  source TEXT NOT NULL,
  status TEXT NOT NULL DEFAULT 'observed',
  confidence REAL NOT NULL DEFAULT 0,
  last_verified_at TEXT,
  created_at TEXT NOT NULL,
  updated_at TEXT NOT NULL
);
```

Recommended uniqueness is not a hard unique constraint on `asin` alone. An ASIN may legitimately appear in multiple internal records if the source catalog is wrong or if it is a parent listing. Instead, collision analysis should explicitly detect many-to-one assignments.

---

# 5. Collision and Synthetic Identifier Quarantine

## 5.1 Collision classifications

Every duplicate identifier should be classified into one of the following categories:

### Legitimate shared parent identifier

A parent ASIN may legitimately represent multiple child variations.

```text
Same parent ASIN
 ├── black / M
 ├── black / L
 └── white / M
```

This is acceptable only when Amazon metadata confirms the relationship.

### Incorrect copied identifier

A single ASIN was copied onto unrelated helmets or variants.

### Regional reuse or source ambiguity

The source does not clearly distinguish marketplace or country.

### Synthetic placeholder

The identifier is generated, sequential, repeated, or otherwise not traceable to a real product record.

### Unresolved collision

The system has insufficient evidence and sends it to manual review.

---

## 5.2 Collision detection rules

### ASIN collision score

Flag an ASIN when:

```text
count(distinct assigned entity_id) > 1
```

Escalate severity based on differences in:

- brand,
- product family,
- model,
- product type,
- color,
- size,
- source record,
- country,
- title similarity.

Example severity:

```text
LOW:
same family, same brand, variant differences only

MEDIUM:
same brand, different family but similar title

HIGH:
different brands or unrelated product types

CRITICAL:
same ASIN assigned to dozens of unrelated records
```

The three identified collisions of 137, 119, and 116 records should immediately be marked:

```text
status = "quarantined"
severity = "critical"
requires_manual_review = true
```

### EAN / GTIN collision rules

An EAN is suspicious when:

- It maps to multiple unrelated products.
- It is sequentially generated across catalog rows.
- It lacks a valid check digit.
- It appears on products from unrelated manufacturers.
- It has an implausibly high reuse rate.

The example `8421567890123` shared by 80 helmets should not remain usable for automatic matching.

---

## 5.3 Synthetic detection

Use multiple signals rather than a single hardcoded pattern.

### Synthetic indicators

```text
- Sequential assignment across import order
- High collision count
- Same prefix/suffix across unrelated products
- Invalid or repeated check digits
- Values generated at regular intervals
- No matching manufacturer or retailer source
- Same identifier created on the same import timestamp
- Identifier appears on more products than the source marketplace could plausibly contain
```

### Quarantine behavior

Quarantined identifiers:

- Remain stored for auditability.
- Are excluded from automatic matching.
- Are excluded from affiliate-link generation.
- Are excluded from “verified identifier” counts.
- Are visible in the cockpit as data-quality issues.
- Can be restored only by a user with appropriate permissions or by trusted source evidence.

```json
{
  "identifier": "8421567890123",
  "status": "quarantined",
  "reason_codes": [
    "high_collision_rate",
    "synthetic_sequence_pattern",
    "cross_product_family_collision"
  ],
  "automatic_matching_allowed": false
}
```

No destructive deletion should occur during V1.

---

# 6. Confidence and Evidence Framework

## 6.1 Confidence classes

Required classes:

```text
verified_exact_asin
title_matched_asin
search_fallback_query
unverified_placeholder
```

Recommended additional statuses:

```text
observed_unverified
verified_from_manufacturer
verified_from_retailer
conflicted
quarantined
expired
```

## 6.2 Suggested confidence model

| Evidence | Base score |
|---|---:|
| Exact verified GTIN/EAN match from trusted source | 0.98 |
| Exact MPN + brand match | 0.94 |
| Amazon listing title + brand + model + color + size match | 0.88 |
| Title + brand + model match, variant uncertain | 0.76 |
| Retailer SKU cross-reference | 0.80 |
| Search result with partial title match | 0.55 |
| Generic fallback query | 0.25 |
| Synthetic or copied placeholder | 0.00 |

Scores should be modified by penalties:

```text
-0.30 if identifier is colliding
-0.40 if source is synthetic
-0.20 if color is missing
-0.15 if size is missing
-0.25 if brand conflicts
-0.15 if marketplace differs from requested region
```

Final score should be capped by the evidence class.

Example:

```json
{
  "asin": "B0XXXXXXXX",
  "confidence": 0.96,
  "confidence_class": "verified_exact_asin",
  "evidence": [
    {
      "type": "exact_gtin_match",
      "weight": 0.60
    },
    {
      "type": "brand_model_match",
      "weight": 0.25
    },
    {
      "type": "marketplace_listing_active",
      "weight": 0.11
    }
  ]
}
```

The system must preserve the evidence components, not only the final number.

---

# 7. ASIN Enrichment and Resolution Pipeline

## 7.1 Pipeline stages

```text
1. Normalize source catalog
2. Validate identifiers
3. Detect collisions and synthetic values
4. Build candidate product identity
5. Attempt exact identifier matching
6. Query approved Amazon data source
7. Match listing metadata
8. Resolve variant and size
9. Assign confidence
10. Store evidence and history
11. Generate fallback link if needed
12. Publish updated read model
```

## 7.2 Continuous in-memory matching engine

The matching engine should load compact indexes into memory:

```text
asinIndex[marketplace][asin] -> listing/entity candidates
gtinIndex[normalized_gtin] -> entity candidates