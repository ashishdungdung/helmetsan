# Luna Red-Team Verdict

The Phase 3 plan is directionally sound, but it is **not yet enterprise-grade or airtight**. The largest weaknesses are not in the individual services; they are at the boundaries between:

- geo-routing, caching, and attribution;
- product identity, variants, and regional commerce data;
- WordPress rendering and large-scale fitment relationships;
- background workers, API quotas, and resumable state;
- multilingual content and structured data.

There is also a scope inconsistency: the brief refers to **four pillars**, but the requested audit contains **five**. Continuous-agent/session architecture must be treated as a first-class pillar because it is the reliability layer for Pillar 4 and the data pipelines supporting all other pillars.

## Executive risk register

| Area | Current risk | Severity | Required decision |
|---|---|---:|---|
| Affiliate routing | Geo, language, currency, and marketplace selection may conflict | Critical | Introduce deterministic routing policy and redirect contract |
| Redirect security | Tampered external parameters could create open redirects or loops | Critical | Allowlist destinations and prohibit arbitrary URL passthrough |
| Caching | Full-page caching can serve one user’s attribution/routing result to another | Critical | Separate cacheable content from request-time routing |
| Schema offers | Mixed currencies and markets on one URL can generate invalid or misleading markup | High | Use market-specific offer sets and regional URL strategy |
| Variants | Parent/child helmet identity may be conflated | High | Establish canonical product/variant identity model |
| Fitment | Runtime querying of 38,196 links is unacceptable | Critical | Precompute/materialize fitment projections |
| Internal links | Fixed link counts alone do not solve relevance or dilution | Medium | Use relevance, diversity, and crawl-budget rules |
| ASIN resolution | Cross-region ASIN reuse can pollute product identity | Critical | Key all marketplace data by marketplace and region |
| PA-API | Quota and token failure could stall workers | Critical | Implement distributed rate limiting, budgets, retries, and quarantine |
| Continuous agent | Cursor-only progress tracking is insufficient | Critical | Add durable jobs, leases, checkpoints, idempotency, and dead-letter states |
| Polylang | Translation and commerce synchronization are underspecified | High | Define master/translation/versioning rules |
| Data API | Public product APIs can become an unbounded scraping surface | High | Add authentication, rate limits, field filtering, and versioning |

---

# 1. WordPress Core Affiliate Routing Alignment

## 1.1 OneLink cannot rely on a single signal

Browser language, browser currency, IP geolocation, account locale, and referral source can conflict.

Examples:

- A French-speaking user in Germany.
- A VPN user in the United States with a GBP browser locale.
- A UK user whose browser sends `en-US`.
- A traveler using a German IP but shopping through an Amazon US account.
- A cached page generated for one country but viewed by another.

### Required routing precedence

Use an explicit, documented precedence model:

1. **Explicit user marketplace selection**, if present and valid.
2. **Previously stored user preference**, preferably first-party and consent-compliant.
3. **Known account/session preference**, if available.
4. **Geo-IP country**, as the default marketplace signal.
5. **Browser language**, as a language preference only.
6. **Browser currency**, as a display preference only.
7. **Site default**.

Language must not silently determine affiliate marketplace. Currency must not silently determine the destination country. They are signals, not authoritative identity.

The user must be able to override the result, for example:

```text
/marketplace/us/
/marketplace/gb/
/marketplace/de/
```

The selection should persist using a first-party cookie or user profile, subject to consent requirements.

## 1.2 OneLink fallback behavior is underspecified

The implementation must define:

- What happens when the local Amazon marketplace does not carry the product?
- What happens when Amazon has no valid affiliate link?
- Whether a non-Amazon retailer is shown as the primary CTA.
- Whether the site displays one retailer or multiple retailers.
- Whether a user is automatically redirected or shown a choice page.

Recommended policy:

```text
preferred local retailer
    -> local Amazon marketplace
    -> approved regional retailer
    -> global fallback retailer
    -> product page without commerce CTA
```

Do not silently send a user to an unrelated country merely because an ASIN exists there.

## 1.3 Redirect loops and open redirects

Any external query parameter such as:

```text
?redirect=
?url=
?marketplace=
?return_to=
```

must be treated as untrusted.

### Required controls

- Never redirect to an arbitrary URL supplied by the request.
- Store approved destinations as internal retailer IDs, not raw URLs.
- Resolve the retailer ID server-side.
- Validate the final host against a strict allowlist.
- Validate scheme: permit only `https`.
- Reject credentials, encoded alternate hosts, protocol-relative URLs, and malformed Unicode domains.
- Remove or normalize routing parameters before issuing the redirect.
- Add a loop counter or signed routing token.
- Never route a destination back through the same internal router unless explicitly marked as terminal.
- Use `302` or `307` during testing and controlled routing. Do not permanently cache `301` decisions based on volatile geo logic.

Example safe model:

```text
/go/helmet-123?retailer=amazon-de
```

The server resolves `amazon-de` from configuration. It does not accept:

```text
/go?url=https://somewhere.example
```

## 1.4 Affiliate attribution versus edge and FastCGI caching

This is one of the most dangerous omissions.

If attribution is written during a page request and the page is cached, the following can occur:

- User A’s affiliate query is embedded in a cached response served to User B.
- A redirect response is cached for the wrong country.
- `hs_utm_source` or `first_referrer` is set inconsistently.
- Cloudflare caches a response before the cookie is set.
- Nginx FastCGI microcache returns a response generated under another user’s cookie state.
- Purge timing creates inconsistent behavior between Cloudflare and origin.

### Required architecture

Separate the system into:

#### Cache-safe page render

The product page should be cacheable and must not contain user-specific affiliate attribution.

#### Request-time routing endpoint

Use a non-cacheable endpoint such as:

```text
/go/{product-slug}?retailer=amazon-us
```

This endpoint:

1. Reads explicit user selection and request signals.
2. Resolves the retailer server-side.
3. Sets attribution cookies where lawful.
4. Generates the affiliate URL.
5. Issues the final redirect.
6. Sends:

```http
Cache-Control: private, no-store
Vary: Cookie
```

Cloudflare must bypass cache for this endpoint. Nginx must also exclude it from FastCGI microcaching.

### Cookie rules

Define:

- host-only versus `.example.com` scope;
- `Secure`;
- `HttpOnly` where JavaScript does not need access;
- `SameSite=Lax` unless a specific cross-site workflow requires another policy;
- explicit expiration;
- consent requirements;
- overwrite policy;
- first-touch versus last-touch attribution policy.

Do not put raw referrer URLs into cookies without length limits, normalization, and privacy review. Store a normalized source classification where possible.

## 1.5 Multiple affiliate networks

A helmet may have:

- Amazon US;
- RevZilla CJ;
- FC-Moto;
- Flipkart;
- Moto-In;
- direct manufacturer or local dealer links.

The plan needs a **retailer-offer model**, not one `affiliate_url` field.

Recommended entity:

```text
ProductOffer
- product_id
- variant_id nullable
- retailer_id
- marketplace
- locale
- currency
- destination_url
- affiliate_program
- availability_status
- price
- stock_status
- last_verified_at
- confidence
- priority
- disclosure_label
```

Routing policy should consider:

1. User marketplace and country.
2. Product availability.
3. Variant availability, especially size.
4. Retailer trust and compliance.
5. Commercial priority.
6. Freshness.
7. Price only if the price is verified and comparable.

Do not always prefer Amazon. A RevZilla offer may be better for a US user if it has the correct size and a valid local delivery path.

A user-facing “Where to buy” component is safer than an invisible retailer substitution. Affiliate disclosures must appear near the commercial component and comply with applicable advertising rules.

---

# 2. Schema.org, Data API, and Product Identity

## 2.1 Mixed-currency AggregateOffer

The plan must not assume that all regional offers can be combined into one global `AggregateOffer`.

For Google eligibility and semantic correctness:

- `priceCurrency` must be explicit.
- `lowPrice` and `highPrice` should represent comparable offers in the same currency.
- A single product URL should not present a misleading aggregate across USD, GBP, EUR, and INR.
- Google Merchant Center data is fundamentally country/market-specific.
- Merchant listings, shipping, tax, availability, and price should align with the target country.

### Recommended policy

Use one of these models:

#### Model A: Regional URLs

```text
/product/helmet-x
/product/helmet-x?market=us
/product/helmet-x?market=gb
/product/helmet-x?market=de
```

Prefer indexable regional paths or subdomains if market-specific content is materially different.

Each indexable regional page exposes only the appropriate currency and offers.

#### Model B: One canonical editorial URL, market-specific commerce widgets

The editorial page has product identity and non-market-specific facts. A dynamic commerce widget shows local offers, but the page’s indexable Product/Offer markup remains limited to one declared market.

#### Model C: Separate country storefronts

Use separate country URLs with correct `hreflang`, canonical, prices, shipping, and structured data.

Do not publish a mixed-currency `AggregateOffer` merely because it is technically possible to serialize it.

## 2.2 Parent helmets versus variant helmets

The identity model must distinguish:

- product family;
- purchasable variant;
- size;
- color;
- certification configuration;
- regional catalog listing;
- retailer offer;
- ASIN.

### Recommended Schema.org structure

For a family page:

```text
Product
  name: Model family
  brand
  model
  image
  hasVariant: Product[]
```

Each variant should have:

- a stable variant identifier;
- variant-specific name;
- color;
- size;
- SKU/MPN where applicable;
- variant-specific offers;
- variant-specific images where available.

For a variant page:

- Use its own `Product` markup if it has a distinct, indexable URL and distinct purchasable identity.
- Link it to the parent using `isVariantOf`.
- The parent may reference it with `hasVariant`.
- Do not create multiple pages with identical Product markup and different URLs unless the distinction is meaningful.
- Do not place a price for a child variant on the parent page if that price applies only to one size/color.

A parent ASIN is usually a catalog container and may not be purchasable. It should not automatically be represented as the purchasable SKU.

## 2.3 Schema and visible content consistency

Every structured-data claim must be supported by visible page content or a clearly visible commerce component:

- price;
- availability;
- rating;
- review count;
- brand;
- model;
- variant;
- currency;
- retailer.

Do not inject stale or hidden offers solely for search engines. Add automated validation for:

- missing `priceCurrency`;
- negative or zero prices;
- stale `priceValidUntil`;
- invalid availability values;
- mismatch between visible and JSON-LD price;
- review count greater than stored review count;
- unsupported or duplicated identifiers;
- multiple conflicting canonical entities.

## 2.4 DataApiController risks

A public API needs more than a controller class.

Required controls:

- versioned endpoints: `/api/v1/...`;
- authentication for write operations;
- read rate limits by IP, token, and account;
- pagination with hard maximums;
- maximum query complexity;
- field selection;
- response caching;
- ETags and conditional requests;
- schema validation;
- audit logging;
- CORS policy;
- denial of arbitrary SQL-like filtering;
- protection from product-data scraping at excessive volume;
- PII exclusion;
- explicit freshness metadata.

The API should return a canonical product identity and regional offers, not raw internal tables.

---

# 3. Polylang, Canonical URLs, and hreflang

The English record cannot simply be duplicated into five language records and assumed to remain synchronized.

## 3.1 Required content model

Define a language-neutral master entity:

```text
HelmetMaster
- stable product identity
- brand
- model
- technical attributes
- fitment relationships
- identifiers
```

Then define localized projections:

```text
HelmetTranslation
- master_id
- language
- title
- slug
- description
- translated attributes
- translation_status
- source_version
- reviewed_at
```

Commerce data should be separated from prose translation. Price, stock, ASIN, and retailer offers are market data, not translations.

## 3.2 Canonical and hreflang rules

For each language page:

- self-canonicalize to its own language URL;
- include reciprocal `hreflang` links;
- include `x-default`;
- ensure all alternates are reachable and return `200`;
- do not point French, German, Spanish, and Italian pages to the English canonical unless they are intentionally non-indexable duplicates;
- keep language URL mapping stable;
- redirect old translated slugs with `301` when changed;
- use the same product identity in JSON-LD while localizing `name` and `description`.

Example:

```html
<link rel="canonical" href="https://example.com/fr/casque-x/">
<link rel="alternate" hreflang="en" href="https://example.com/en/helmet-x/">
<link rel="alternate" hreflang="fr" href="https://example.com/fr/casque-x/">
<link rel="alternate" hreflang="de" href="https://example.com/de/helm-x/">
<link rel="alternate" hreflang="x-default" href="https://example.com/helmet-x/">
```

Do not use automatic IP redirects on indexable pages. They interfere with crawlers, caching, and user choice.

---

# 4. Fitment, Semantic Matching, and Cross-Linking

## 4.1 Runtime querying of 38,196 links is not acceptable

If `CrossLinkService` queries tens of thousands of links during page rendering, expected consequences include:

- elevated TTFB;
- database CPU spikes;
- lock contention;
- cache misses;
- PHP worker exhaustion;
- timeout risk;
- poor crawl performance;
- cache fragmentation if results vary by user or locale.

The rule should be:

> CrossLinkService must not perform an unbounded relational query during a normal frontend page render.

## 4.2 Precompiled fitment architecture

Use a normalized source model and a denormalized serving model.

### Source tables

```text
fitment_source
- bike_id
- helmet_id
- score
- rationale
- evidence
- source_version
- status
```

### Serving projection

```text
bike_related_helmets
- bike_id
- rank
- helmet_id
- score
- reason_code
- generated_at
- projection_version
```

```text
helmet_related_bikes
- helmet_id
- rank
- bike_id
- score
- reason_code
- generated_at
- projection_version
```

Precompute using a queue or batch job, then publish atomically.

Serving options:

- WordPress post meta for small, bounded lists;
- custom indexed tables;
- Redis/object cache;
- serialized JSON blobs keyed by entity and version;
- static JSON generated during deployment or rebuild.

The page should perform one bounded lookup or read a prebuilt fragment. “0ms runtime overhead” is not literally achievable, but **near-zero additional database work** is achievable.

## 4.3 Matching quality and explainability

A score without a reason is not enterprise-grade.

Store reason codes such as:

- shell shape compatible;
- riding posture compatible;
- size range overlap;
- certification match;
- climate suitability;
- touring/noise preference;
- manufacturer/model family evidence;
- editorial validation.

Do not imply that a helmet physically fits a motorcycle. Most helmet-to-bike relationships are recommendation relationships, not mechanical fitment. Use precise language such as “recommended for riders of” rather than “compatible with” where appropriate.

## 4.4 Reciprocal consistency

A reciprocal relationship should not be assumed.

If Bike A recommends Helmet B, Helmet B may not rank Bike A highly because the reverse relationship has a different candidate universe.

Use two concepts:

1. **Relationship existence**: A ↔ B is valid.
2. **Directional rank**: B’s rank among A’s recommendations and A’s rank among B’s recommendations.

Store both directions explicitly. Add consistency rules:

- if relationship is editorially reciprocal, both projections must contain it;
- if directional, mark it as directional;
- if scores tie, use deterministic tie-breaking:
  1. higher evidence confidence;
  2. higher editorial quality;
  3. stronger data freshness;
  4. stable entity ID.

Never allow rank instability from nondeterministic SQL ordering.

## 4.5 Internal-link budgeting

There is no universal Google rule that says every post must have a maximum of five or eight internal links. “Penguin” is not a simple internal-link-count