# Helmetsan Unified Architecture Specification

This specification consolidates the Amazon identifier engine, marketplace routing, Translation Studio, worker orchestration, Metal inference, SSH/WP bridge reliability, and Mission Control UI into one scalable architecture.

The design principle is:

> **Keep every external integration replaceable, every long-running operation asynchronous, and every generated identifier verifiable before publication.**

---

# 1. Target Architecture

```text
                         ┌────────────────────────────┐
                         │ Mission Control UI          │
                         │ Express.js :3005            │
                         └──────────────┬─────────────┘
                                        │ REST + WebSocket/SSE
                         ┌──────────────▼─────────────┐
                         │ Helmetsan Orchestrator      │
                         │ - job queue                  │
                         │ - routing                    │
                         │ - validation                 │
                         │ - metrics                    │
                         └───────┬───────────┬─────────┘
                                 │           │
                ┌────────────────▼───┐   ┌──▼────────────────┐
                │ Identifier Engine   │   │ Translation Engine │
                │ ASIN/API/Search     │   │ TM/cache/glossary │
                └───────┬─────────────┘   └─────┬────────────┘
                        │                       │
        ┌───────────────▼──────────────┐  ┌─────▼────────────────┐
        │ Amazon Creator/PA-API Client │  │ LM Studio Metal       │
        │ Disabled-safe adapter         │  │ OpenAI-compatible API │
        └───────────────┬──────────────┘  └──────────────────────┘
                        │
        ┌───────────────▼──────────────┐
        │ Geo-targeted search fallback │
        │ Search API / approved query  │
        │ URL generation               │
        └──────────────────────────────┘

                         │
                 ┌───────▼────────┐
                 │ SSH/WP Bridge  │
                 │ pooled/control  │
                 │ idempotent jobs │
                 └───────┬────────┘
                         │
                 ┌───────▼────────┐
                 │ WordPress      │
                 │ Production     │
                 └────────────────┘
```

## Core services

Initially these can remain in the existing Express/Python deployment rather than being split into separate servers.

1. **Mission Control API**
2. **Job Orchestrator**
3. **Marketplace Identifier Engine**
4. **Translation Worker Pool**
5. **Translation Memory and Cache**
6. **Amazon Integration Adapter**
7. **Search Fallback Adapter**
8. **Remote WordPress Bridge Adapter**
9. **Metrics and Health Service**

The architecture should support a later move to Redis, PostgreSQL, or a separate worker host without changing the public API.

---

# 2. Part 1 — ASIN and Identifier Hybrid Engine

## 2.1 Routing modes

Use a single configuration variable:

```env
AFFILIATE_MODE=hybrid
```

Allowed values:

```text
hybrid
api_only
fallback_only
```

### `hybrid`

Recommended production mode.

Routing order:

1. Existing verified ASIN
2. Verified marketplace-specific ASIN cache
3. Amazon Creator API / PA-API adapter
4. Approved automated search-query fallback
5. Unresolved/quarantine state

### `api_only`

Use only:

1. Existing verified ASIN
2. Creator API / PA-API

No search fallback is performed.

### `fallback_only`

Use only:

1. Existing verified ASIN if explicitly trusted
2. Geo-targeted search-query generation

No API request is made.

---

## 2.2 Provider abstraction

The Creator API should be developed now even if credentials or enablement are unavailable.

```js
class AmazonProductProvider {
  async lookupByAsin({ marketplace, asin }) {}
  async searchByIdentifiers({ marketplace, identifiers }) {}
  async searchByKeywords({ marketplace, query }) {}
  async getOffers({ marketplace, asin }) {}
}
```

Implementations:

```text
CreatorApiProvider
SearchFallbackProvider
CachedProductProvider
```

The orchestrator must never directly call Amazon-specific code:

```js
const provider = providerRegistry.resolve(mode);

const result = await provider.resolveProduct({
  marketplace,
  identifiers,
  query
});
```

This permits the Creator API to be enabled later by changing configuration rather than rewriting the system.

---

## 2.3 Marketplace configuration

Use a normalized marketplace table rather than scattering domains and tags through application code.

```json
{
  "US": {
    "amazonHost": "www.amazon.com",
    "tld": ".com",
    "locale": "en-US",
    "currency": "USD",
    "affiliateTag": "vtete-20",
    "enabled": true
  },
  "UK": {
    "amazonHost": "www.amazon.co.uk",
    "tld": ".co.uk",
    "locale": "en-GB",
    "currency": "GBP",
    "affiliateTag": null,
    "enabled": true
  },
  "DE": {
    "amazonHost": "www.amazon.de",
    "tld": ".de",
    "locale": "de-DE",
    "currency": "EUR",
    "affiliateTag": null,
    "enabled": true
  },
  "IN": {
    "amazonHost": "www.amazon.in",
    "tld": ".in",
    "locale": "en-IN",
    "currency": "INR",
    "affiliateTag": "virginiatete-21",
    "enabled": true
  },
  "JP": {
    "amazonHost": "www.amazon.co.jp",
    "tld": ".co.jp",
    "locale": "ja-JP",
    "currency": "JPY",
    "affiliateTag": null,
    "enabled": true
  }
}
```

The UK, DE, and JP tags must be populated only with confirmed affiliate-account values. Do not reuse a tag across regions unless Amazon explicitly permits it.

### Link construction

```js
function buildAmazonUrl({ marketplace, asin, query }) {
  const config = marketplaces[marketplace];

  const params = new URLSearchParams();

  if (config.affiliateTag) {
    params.set("tag", config.affiliateTag);
  }

  if (asin) {
    return `https://${config.amazonHost}/dp/${encodeURIComponent(asin)}?${params}`;
  }

  return `https://${config.amazonHost}/s?k=${encodeURIComponent(query)}&${params}`;
}
```

All generated links should pass through:

- Marketplace validation
- Affiliate tag validation
- URL encoding
- ASIN format validation
- Collision/quarantine checks
- Link health monitoring

---

## 2.4 Identifier schema

The system must distinguish globally identifying values from retailer-specific values.

### Product-level identity

```sql
products
---------
id
brand
model_name
model_family
product_type
manufacturer
oem_code
canonical_slug
created_at
updated_at
```

### Variant-level identity

```sql
product_variants
----------------
id
product_id
variant_name
color
size
gender
finish
year_range
country_of_origin
is_active
```

### Identifier table

```sql
product_identifiers
-------------------
id
product_id
variant_id
identifier_type
identifier_value
normalized_value
country_code
marketplace
source
confidence
is_verified
is_primary
status
first_seen_at
last_verified_at
metadata_json
```

`identifier_type`:

```text
gtin
gtin12
gtin13
gtin14
ean
upc
isbn
mpn
sku
oem_code
asin
retailer_sku
```

### Retailer identifier table

```sql
retailer_identifiers
--------------------
id
product_id
variant_id
retailer
retailer_sku
retailer_url
country_code
source
confidence
is_verified
last_checked_at
status
```

Examples:

```text
retailer = revzilla
retailer = fc_moto
retailer = chromeburner
retailer = louis
retailer = motoin
```

### Marketplace ASIN table

```sql
marketplace_products
--------------------
id
product_id
variant_id
marketplace
asin
asin_status
source
confidence
last_verified_at
title_snapshot
brand_snapshot
image_snapshot
detail_page_url
offer_status
quarantine_reason
```

This is important because the same physical model may have:

- Different ASINs in different countries
- Different ASINs for colors or sizes
- No ASIN in one marketplace
- A parent ASIN and child variation ASIN
- A retailer SKU that is not an MPN
- A manufacturer MPN shared by multiple regional listings

---

## 2.5 Identifier normalization

### GTIN/EAN rules

- EAN-13 is a GTIN-13.
- UPC-A is a GTIN-12.
- Do not silently convert a value into another identifier type.
- Preserve leading zeroes.
- Strip spaces, dashes, and non-numeric formatting only during normalization.
- Validate check digits.
- Store original and normalized values.

Example:

```js
function normalizeNumericIdentifier(value) {
  return String(value).replace(/[^\d]/g, "");
}
```

Do not classify every 12- or 13-digit number as valid solely by length. Validate:

1. Character set
2. Expected length
3. Check digit
4. Source reliability
5. Brand/product consistency

### MPN rules

MPNs are manufacturer-defined and may contain:

- Letters
- Numbers
- Hyphens
- Slashes
- Spaces
- Region suffixes

Store:

```text
original_value
normalized_value
manufacturer
source
```

Normalization must not remove meaningful suffixes.

### SKU rules

Internal SKU and retailer SKU must never be treated as GTIN, EAN, MPN, or ASIN.

Recommended internal SKU format:

```text
HSN-{product-family}-{variant}-{region}-{sequence}
```

Example:

```text
HSN-RPHA12-BLK-L-GB-0001
```

The internal SKU should remain stable even when marketplace listings change.

---

## 2.6 ASIN verification pipeline

Every ASIN must have a status.

```text
candidate
unverified
verified
stale
mismatch
quarantined
retired
```

### Verification checks

A candidate ASIN is considered verified only after:

1. Correct marketplace domain
2. Valid ASIN format
3. Product title similarity
4. Brand similarity
5. MPN/EAN/GTIN match where available
6. Variant match where applicable
7. No synthetic collision signal
8. Source and timestamp recorded

### Confidence scoring

Example scoring model:

```text
+35 exact EAN/GTIN match
+25 exact MPN match
+15 exact brand match
+15 strong title similarity
+10 variant/size/color match
-40 conflicting brand
-35 conflicting model
-30 known duplicate collision
-20 stale source
```

Suggested outcomes:

```text
90–100: verified
70–89: review or conditionally usable
40–69: candidate only
0–39: reject/quarantine
```

The score should be explainable and stored in the database.

---

## 2.7 Collision quarantine

Synthetic or incorrect ASIN records must not be deleted immediately. They should be isolated for auditability.

### Collision signals

- Same ASIN assigned to unrelated products
- Same ASIN appearing across incompatible brands
- ASIN generated without a traceable source
- ASIN found in an import file but not independently verified
- ASIN title mismatch
- ASIN marketplace mismatch
- Repeated use of a known suspicious ASIN such as `B07QKZV8YJ`
- ASIN assigned to multiple incompatible variants
- Detail URL redirects to a different product

### Quarantine table

```sql
identifier_quarantine
---------------------
id
identifier_type
identifier_value
product_id
marketplace
reason_code
evidence_json
detected_at
review_status
reviewed_by
resolution
```

Reason codes:

```text
SYNTHETIC_DUPLICATE
MARKETPLACE_MISMATCH
PRODUCT_TITLE_MISMATCH
BRAND_MISMATCH
VARIANT_MISMATCH
UNVERIFIED_IMPORT
STALE_LOOKUP
CONFLICTING_SOURCE
```

### Behaviour after quarantine

Do not generate:

```text
/dp/B07QKZV8YJ
```

Instead generate a clean search URL using the strongest available identifiers:

```text
Brand + model + MPN
Brand + model + EAN
Brand + model + variant
```

Example:

```text
https://www.amazon.com/s?k=Shoei+NXR2+helmet+MPN
```

The system should label this link as:

```text
resolution = search_fallback
asin = null
```

---

## 2.8 Hybrid resolution algorithm

```js
async function resolveMarketplaceProduct(input) {
  const {
    productId,
    marketplace,
    identifiers,
    keywords
  } = input;

  const mode = config.AFFILIATE_MODE;

  const existing = await getVerifiedAsin({
    productId,
    marketplace
  });

  if (existing && !isQuarantined(existing)) {
    return buildVerifiedResult(existing);
  }

  if (mode !== "fallback_only") {
    const apiResult = await creatorApi.lookupByIdentifiers({
      marketplace,
      identifiers,
      keywords
    });

    if (apiResult && passesValidation(apiResult)) {
      await persistVerifiedResult(apiResult);
      return apiResult;
    }
  }

  if (mode !== "api_only") {
    const query = buildGeoTargetedQuery({
      marketplace,
      identifiers,
      keywords
    });

    const fallback = await searchFallback.resolve({
      marketplace,
      query
    });

    return persistFallbackResult(fallback);
  }

  return {
    status: "unresolved",
    marketplace,
    reason: "NO_VERIFIED_RESULT"
  };
}
```

---

## 2.9 Search fallback rules

The fallback must use an approved search provider or Amazon-compatible search URL mechanism. It should not rely on uncontrolled scraping of Amazon pages.

Search query priority:

1. Exact EAN/GTIN
2. Exact MPN
3. Brand + model
4. Brand + model + variant
5. Product title plus motorcycle fitment where relevant

Example query generation:

```js
function buildGeoTargetedQuery({ identifiers, keywords }) {
  const exact = [
    identifiers.ean,
    identifiers.gtin,
    identifiers.mpn
  ].filter(Boolean);

  if (exact.length) {
    return exact.join(" ");
  }

  return keywords
    .filter(Boolean)
    .join(" ")
    .trim();
}
```

Queries should be locale-aware:

```text
US: English terminology
DE: German product terms where available
JP: Japanese product terms where available
IN: English plus regional spelling variants
```

Store the original query, normalized query, marketplace, timestamp, and result source.

---

## 2.10 Product coverage model

The initial dataset:

```text
2,219 helmets
1,642 variants
27 accessories
3,247 motorcycles
```

Relationships should be many-to-many where required:

```sql
motorcycle_fitments
-------------------
id
product_id
motorcycle_id
year_from
year_to
engine_cc
fitment_source
confidence
```

Do not embed motorcycle fitment only in free text. Preserve both:

- Structured fitment data
- Human-readable compatibility text

This supports localized search and future marketplace enrichment.

---

# 3. Part 2 — Translation Studio

## 3.1 Translation processing model

Translation must become asynchronous.

The browser should never wait for the full translation operation.

```text
POST /api/translation/jobs
        │
        ▼
job created immediately
        │
        ▼
worker processes batches
        │
        ├── progress events
        ├── candidate events
        ├── error events
        └── completion event
```

Client access:

```text
GET /api/translation/jobs/:id
GET /api/translation/jobs/:id/items
GET /api/translation/jobs/:id/errors
GET /api/translation/jobs/:id/metrics
```

Live updates:

```text
/ws/translation/:jobId
```

or Server-Sent Events:

```text
GET /api/translation/jobs/:id/events
```

---

## 3.2 Recommended timeout strategy

Increasing HTTP timeouts alone will not solve the problem. Long operations must be moved into jobs with progress tracking.

### Timeout matrix

| Component | Current | Recommended initial | Hard maximum | Behaviour |
|---|---:|---:|---:|---|
| Mission Control HTTP request | variable | 15–30 sec | 60 sec | Create job only |
| Local LM