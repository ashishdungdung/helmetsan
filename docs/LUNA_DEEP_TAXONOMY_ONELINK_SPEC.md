# Helmetsan Global Product, Marketplace, and WordPress Commerce Architecture

## 0. Critical Corrections and Design Decisions

### Marketplace count

The supplied list contains **22 country/market targets**, not 21:

1. US  
2. CA  
3. MX  
4. BR  
5. UK  
6. DE  
7. FR  
8. IT  
9. ES  
10. NL  
11. PL  
12. SE  
13. BE  
14. TR  
15. IN  
16. JP  
17. AU  
18. SG  
19. AE  
20. SA  
21. EG  
22. ZA  

The system should therefore use a generic **marketplace registry**, rather than hard-coding “21 marketplaces.” If one target is later removed, the registry changes without a schema migration.

### Important Amazon distinction

Amazon OneLink is not a universal replacement for direct marketplace URLs.

It is an Amazon Associates linking and localization mechanism whose behavior depends on:

- Whether OneLink is available for the source and destination country.
- Whether the Associate has completed destination-country enrollment.
- Whether the destination marketplace supports the relevant linking behavior.
- Whether the product exists or is purchasable in the destination marketplace.
- Whether the correct destination Associate ID/tag is configured.
- Whether the browser permits the OneLink client-side behavior.
- Whether the URL contains an ASIN that can be resolved across marketplaces.

Therefore:

> **Direct localized marketplace URLs must remain first-class records. OneLink should be treated as an optional resolution and attribution layer, not as the canonical product URL.**

The architecture should support both:

```text
Canonical product
    ├── Direct regional offer URLs
    ├── OneLink-compatible canonical URL
    ├── Affiliate tag / Associate ID mapping
    └── Fallback retailer URLs
```

---

# 1. High-Level System Architecture

```text
                 ┌─────────────────────────────┐
                 │ Git Versioned Product JSON   │
                 │ Canonical editorial source   │
                 └──────────────┬──────────────┘
                                │
                    Validate / Normalize / Enrich
                                │
                 ┌──────────────▼──────────────┐
                 │ Ingestion and Collision      │
                 │ Detection Pipeline           │
                 └──────────────┬──────────────┘
                                │
                 ┌──────────────▼──────────────┐
                 │ SQLite Projection            │
                 │ catalog.db, WAL, FTS5        │
                 └───────┬─────────────┬────────┘
                         │             │
             ┌───────────▼────┐ ┌────▼────────────────┐
             │ mmap Search     │ │ WordPress Sync       │
             │ Unified Index   │ │ CPT + postmeta       │
             └───────────┬────┘ └────┬────────────────┘
                         │           │
       ┌─────────────────▼───┐   ┌───▼─────────────────────┐
       │ API / Redirector     │   │ helmetsan-core/theme    │
       │ Marketplace Resolver │   │ Templates and JS        │
       └────────────┬─────────┘   └────────────┬───────────┘
                    │                          │
       ┌────────────▼─────────────┐   ┌────────▼────────────┐
       │ Amazon PA-API / Creator  │   │ Analytics / Tracking  │
       │ API adapters             │   │ Consent-aware events  │
       └──────────────────────────┘   └─────────────────────┘
```

## Architectural principles

1. **Git JSON is the human-reviewable source of truth.**
2. **SQLite is the operational read model.**
3. **The memory-mapped index is disposable and rebuildable.**
4. **WordPress is a presentation and editorial integration layer, not the primary catalog database.**
5. **Every marketplace URL is explicit, typed, validated, and auditable.**
6. **No product record is silently overwritten.**
7. **All ambiguous identifiers enter quarantine.**
8. **Affiliate routing is policy-driven and configuration-driven.**
9. **Amazon API data is enrichment, not an uncontrolled overwrite of curated catalog data.**
10. **All external APIs are optional at runtime; the site remains functional during API failure.**

---

# 2. Canonical Domain Model

The catalog should separate the commercial hierarchy from technical identifiers and marketplace offers.

## 2.1 Entity hierarchy

```text
Brand
  └── Product Family
        └── Master Model
              └── Product Variant
                    └── Size / Certification SKU
                          └── Marketplace Offer
                                └── Regional URL / Affiliate Route
```

The supplied data volumes fit naturally into this hierarchy:

- Product variants: approximately **1,642**
- Motorcycle fitments: approximately **3,247**
- Current source records: **5,493+**
- Target scale: **25,000+ records**

## 2.2 Recommended relational entities

### Core entities

```text
brand
product_family
master_model
product_variant
variant_size
certification
global_identifier
marketplace
marketplace_offer
retailer
fitment_motorcycle
fitment
media_asset
attribute_definition
attribute_value
product_attribute
```

### Operational entities

```text
ingestion_batch
ingestion_record
source_document
collision_case
quarantine_record
redirect_event
api_cache
api_request_log
price_snapshot
availability_snapshot
sync_job
sync_error
```

---

# 3. Database Schema

SQLite is sufficient for 25,000 products and considerably beyond, provided the schema is normalized and indexes are deliberate.

## 3.1 Product tables

```sql
CREATE TABLE brand (
    brand_id INTEGER PRIMARY KEY,
    slug TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    normalized_name TEXT NOT NULL,
    website_url TEXT,
    country_code TEXT,
    status TEXT NOT NULL DEFAULT 'active'
        CHECK (status IN ('active', 'inactive', 'quarantined')),
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);

CREATE TABLE product_family (
    family_id INTEGER PRIMARY KEY,
    brand_id INTEGER NOT NULL REFERENCES brand(brand_id),
    slug TEXT NOT NULL,
    name TEXT NOT NULL,
    normalized_name TEXT NOT NULL,
    description TEXT,
    UNIQUE (brand_id, slug)
);

CREATE TABLE master_model (
    master_model_id INTEGER PRIMARY KEY,
    family_id INTEGER NOT NULL REFERENCES product_family(family_id),
    model_code TEXT,
    name TEXT NOT NULL,
    normalized_name TEXT NOT NULL,
    model_year_from INTEGER,
    model_year_to INTEGER,
    UNIQUE (family_id, model_code)
);

CREATE TABLE product_variant (
    variant_id INTEGER PRIMARY KEY,
    master_model_id INTEGER NOT NULL REFERENCES master_model(master_model_id),
    slug TEXT NOT NULL UNIQUE,
    display_name TEXT NOT NULL,
    normalized_name TEXT NOT NULL,
    gender TEXT,
    construction_type TEXT,
    lifecycle_status TEXT NOT NULL DEFAULT 'active',
    source_confidence REAL NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
);
```

## 3.2 Size and certification

Do not place certification directly into a free-form product description. Certification is region- and SKU-sensitive.

```sql
CREATE TABLE variant_size (
    variant_size_id INTEGER PRIMARY KEY,
    variant_id INTEGER NOT NULL REFERENCES product_variant(variant_id),
    size_label TEXT NOT NULL,
    size_system TEXT,
    head_circumference_min_mm INTEGER,
    head_circumference_max_mm INTEGER,
    shell_size TEXT,
    UNIQUE (variant_id, size_label, size_system)
);

CREATE TABLE certification (
    certification_id INTEGER PRIMARY KEY,
    code TEXT NOT NULL UNIQUE,
    authority TEXT,
    region_code TEXT,
    standard_name TEXT NOT NULL,
    version TEXT,
    valid_from TEXT,
    valid_to TEXT
);

CREATE TABLE variant_certification (
    variant_size_id INTEGER NOT NULL REFERENCES variant_size(variant_size_id),
    certification_id INTEGER NOT NULL REFERENCES certification(certification_id),
    certification_status TEXT NOT NULL,
    evidence_url TEXT,
    evidence_hash TEXT,
    PRIMARY KEY (variant_size_id, certification_id)
);
```

This avoids incorrectly representing “the model is ECE-certified” when only a particular regional SKU or production revision carries that certification.

## 3.3 Global identifiers

Identifiers must be typed and scoped.

```sql
CREATE TABLE global_identifier (
    identifier_id INTEGER PRIMARY KEY,
    variant_size_id INTEGER REFERENCES variant_size(variant_size_id),
    variant_id INTEGER REFERENCES product_variant(variant_id),
    identifier_type TEXT NOT NULL
        CHECK (identifier_type IN (
            'GTIN', 'EAN', 'UPC', 'ISBN', 'MPN',
            'OEM', 'HSN_SKU', 'ASIN', 'SKU', 'MODEL_NUMBER'
        )),
    identifier_value TEXT NOT NULL,
    normalized_value TEXT NOT NULL,
    issuing_region TEXT,
    issuing_party TEXT,
    is_primary INTEGER NOT NULL DEFAULT 0,
    confidence REAL NOT NULL DEFAULT 0,
    source_id TEXT,
    UNIQUE (identifier_type, normalized_value, issuing_region)
);
```

Important distinctions:

- **ASIN** is marketplace-specific, not globally universal.
- **SKU** may belong to Amazon, a manufacturer, or a retailer.
- **EAN/GTIN** may identify packaging or a particular size/color variant.
- **MPN** may identify a model rather than a saleable SKU.
- **OEM numbers** may be reused across regional catalogs.
- **HSN SKU** should be represented as a source-specific identifier, not assumed to be a universal identifier.

---

# 4. Global Marketplace Registry

The marketplace registry must be data-driven.

## 4.1 Marketplace configuration

```json
{
  "marketplace_code": "US",
  "amazon_domain": "amazon.com",
  "country_code": "US",
  "currency": "USD",
  "locale": "en-US",
  "url_pattern": "https://www.amazon.com/dp/{asin}",
  "supports_direct_link": true,
  "onelink_status": "configured",
  "associate_tag_key": "amazon_us",
  "requires_separate_associate_account": false,
  "availability_policy": "verify_at_runtime"
}
```

A complete registry should include all supplied targets, but should distinguish:

- **Retail marketplace exists**
- **Affiliate program exists**
- **OneLink is configured**
- **Direct links are supported**
- **Product ASIN is known**
- **Product is currently purchasable**

These are not equivalent states.

## 4.2 Configuration examples

The user-provided IDs should be represented as deployment configuration, not embedded in PHP or JavaScript:

```json
{
  "amazon_us": {
    "marketplace": "US",
    "associate_tag": "vtete-20",
    "onelink_enabled": true
  },
  "amazon_uk": {
    "marketplace": "UK",
    "associate_tag": "vtete-21",
    "onelink_enabled": true
  },
  "amazon_in": {
    "marketplace": "IN",
    "associate_tag": "virginiatete-21",
    "onelink_enabled": false,
    "requires_separate_resolution": true
  },
  "amazon_jp": {
    "marketplace": "JP",
    "associate_tag": "vtete-22",
    "onelink_enabled": false,
    "requires_separate_resolution": true
  },
  "amazon_ae": {
    "marketplace": "AE",
    "associate_tag": "vtete0c-21",
    "onelink_enabled": false,
    "requires_separate_resolution": true
  }
}
```

These values must be verified against the current Associates account configuration. They should not be treated as assumed valid merely because they appear in a source file.

## 4.3 Marketplace offer schema

```sql
CREATE TABLE marketplace_offer (
    offer_id INTEGER PRIMARY KEY,
    variant_size_id INTEGER REFERENCES variant_size(variant_size_id),
    marketplace_id INTEGER NOT NULL REFERENCES marketplace(marketplace_id),
    retailer_id INTEGER NOT NULL REFERENCES retailer(retailer_id),
    asin TEXT,
    seller_sku TEXT,
    product_url TEXT,
    canonical_url TEXT,
    affiliate_url TEXT,
    price_amount INTEGER,
    price_currency TEXT,
    availability_status TEXT,
    offer_status TEXT NOT NULL DEFAULT 'active',
    source TEXT,
    source_confidence REAL NOT NULL DEFAULT 0,
    last_verified_at TEXT,
    url_hash TEXT,
    UNIQUE (variant_size_id, marketplace_id, retailer_id, asin)
);
```

Store currency in minor units:

```text
USD 129.99 → 12999
JPY 12980   → 12980
```

Never store monetary values as floating point.

---

# 5. Amazon OneLink and Direct Marketplace Resolution

## 5.1 Recommended resolution strategy

Use a three-stage strategy:

```text
Stage 1: Resolve an explicit direct regional offer
Stage 2: If unavailable, resolve a verified OneLink-capable route
Stage 3: If neither is available, use a safe canonical marketplace or retailer fallback
```

The redirector should not blindly redirect every user to OneLink.

### Decision table

| Condition | Preferred route |
|---|---|
| User region has a verified direct ASIN and URL | Direct regional Amazon URL |
| User region has a verified direct URL but no ASIN | Direct regional URL |
| User region is OneLink-enabled and source link is compatible | OneLink route |
| User region has a separate Associate ID but no verified product URL | Region-specific lookup or retailer fallback |
| Destination marketplace is unsupported | Source marketplace or specialty retailer |
| Browser/API cannot determine location | User-selected region or default configured marketplace |
| Product unavailable in destination region | Alternate retailer or global source marketplace |

## 5.2 Why direct links should generally win

Direct regional links offer:

- Predictable marketplace routing.
- Better control over product and variant matching.
- Fewer client-side dependencies.
- Better server-side logging.
- Clearer debugging.
- More reliable affiliate attribution where the regional tag is known.
- Less risk of OneLink resolving to the wrong variant.

OneLink remains valuable when:

- A single link needs to serve many countries.
- The product mapping is reliable.
- The user is browsing from an unconfigured destination.
- A direct regional URL has not yet been verified.
- A unified campaign URL is strategically preferred.

## 5.3 OneLink-aware fallback

The system should maintain a capability matrix:

```text
marketplace
  ├── direct_url_supported
  ├── onelink_supported
  ├── onelink_configured
  ├── separate_associate_id_required
  ├── product_mapping_supported
  ├── last_verified_at
  └── failure_reason
```

Example routing algorithm:

```pseudo
function resolve_amazon_route(product, request_context):
    country = detect_country(request_context)
    marketplace = marketplace_registry.resolve(country)

    offer = product.find_verified_amazon_offer(marketplace)

    if offer exists and offer.product_url is valid:
        return route(
            type = "direct",
            url = attach_verified_tag(offer.product_url, marketplace),
            marketplace = marketplace
        )

    if marketplace.onelink_enabled
       and product.onelink_source_url exists
       and product.onelink_mapping_confidence >= threshold:
        return route(
            type = "onelink",
            url = product.onelink_source_url,
            marketplace = marketplace
        )

    if product.has_verified_global_amazon_source:
        return route(
            type = "source_marketplace",
            url = product.global_amazon_source_url,
            marketplace = product.global_amazon_marketplace
        )

    return retailer_fallback(product, country)
```

## 5.4 URL construction rules

Do not construct arbitrary Amazon URLs from user-submitted strings.

The URL builder must:

1. Parse the URL with a strict URL parser.
2. Allow only approved Amazon hostnames.
3. Extract and validate ASIN format.
4. Remove untrusted query parameters.
5. Add only the configured tag for the target marketplace.
6. Preserve permitted campaign parameters.
7. Reject redirects to non-Amazon domains unless the retailer is registered.
8. Record the final normalized URL and hash.

Example:

```pseudo
function build_amazon_url(asin, marketplace, tag):
    assert is_valid_asin(asin)
    assert marketplace.domain in approved_amazon_domains

    base = "https://" + marketplace.domain + "/dp/" + asin
    query = {
        "tag": tag,
        "linkCode": "ll1",
        "language": marketplace.default_language
    }

    return append_query(base, query)
```

Amazon’s current linking and disclosure policies must be verified before enabling any particular parameter set.

## 5.5 OneLink implementation model

OneLink should be handled in two modes:

### Server-side mode

Used for deterministic redirects and analytics:

```text