# Adversarial audit

## Executive conclusion

The blueprint has several sound architectural principles—canonical SI values, non-localized stored prices, explicit locale keys, and bounded caches—but it is **not ready for production sign-off**.

The main blockers are:

1. The structured-data implementation is not included, so exact Schema.org validity cannot be established.
2. The aerodynamic formula is absent, making mathematical correctness impossible to verify.
3. Country-to-currency handling is underspecified and is unsafe if used directly with CDN-cached pages.
4. The gettext cache is only partially locale-safe. It can serve stale translations when Polylang changes locale or translation state without changing the two-letter language code.
5. Monetary arithmetic must be explicitly decimal/integer-based; the blueprint’s phrase “minor-unit-safe decimal strings” is an invariant, not an implementation.

---

# 1. Schema.org and Google Rich Results audit

## 1.1 The relevant structured-data code is missing

The supplied blueprint describes a “Structured-data rendering” layer but does not show its JSON-LD. Therefore, it is impossible to confirm:

- whether `Product` is the root type;
- whether `offers` is an `Offer` or `AggregateOffer`;
- whether fields are emitted with the correct JSON types;
- whether localized values are accidentally placed into machine-readable fields;
- whether variants, availability, reviews, and shipping data are represented correctly.

This is a blocking omission for sign-off.

## 1.2 Minimum valid Product/Offer shape

For an individual product, the implementation should be structurally similar to:

```json
{
  "@context": "https://schema.org",
  "@type": "Product",
  "@id": "https://example.com/helmet/model-x#product",
  "name": "Model X Helmet",
  "image": [
    "https://example.com/images/model-x.jpg"
  ],
  "description": "Product description",
  "sku": "MODEL-X-BLK-M",
  "brand": {
    "@type": "Brand",
    "name": "Example Brand"
  },
  "offers": {
    "@type": "Offer",
    "url": "https://example.com/helmet/model-x",
    "priceCurrency": "EUR",
    "price": "249.95",
    "availability": "https://schema.org/InStock",
    "itemCondition": "https://schema.org/NewCondition"
  }
}
```

For an `Offer`, the implementation must ensure:

- `price` is a JSON number or a numeric string, not `"€249,95"`;
- `priceCurrency` is a valid three-letter ISO 4217 code;
- `availability` is a Schema.org URL, not `"In stock"` or a localized phrase;
- `url` is an absolute canonical product URL;
- `price` and `priceCurrency` refer to the same market and currency;
- no negative, `NaN`, infinite, or malformed price is emitted.

For `AggregateOffer`, do not emit an ordinary single-offer field set. Use appropriate fields such as:

```json
{
  "@type": "AggregateOffer",
  "lowPrice": "199.95",
  "highPrice": "299.95",
  "priceCurrency": "EUR",
  "offerCount": 4
}
```

`lowPrice` and `highPrice` must be numeric and must satisfy:

```text
0 <= lowPrice <= highPrice
offerCount >= 1
```

Do not use `AggregateOffer` merely because a product has size or color variants unless the aggregation genuinely represents multiple purchasable offers.

## 1.3 `PropertyValue` risks

Technical specifications should be represented approximately as:

```json
{
  "@type": "PropertyValue",
  "name": "Circumference",
  "value": 58,
  "unitCode": "CMT"
}
```

or:

```json
{
  "@type": "PropertyValue",
  "name": "Noise rating",
  "value": 98,
  "unitText": "dB(A)"
}
```

Potential violations and failure modes:

- `value` contains a localized string such as `"58 cm"` rather than a numeric value;
- `unitCode` contains an unsupported or arbitrary value;
- both `unitCode` and an inconsistent `unitText` are emitted;
- numeric fields contain `"N/A"`, an empty string, or `null`;
- a property is typed as `PropertyValue` but has no `name` or usable `value`;
- values are converted differently between visible HTML and JSON-LD;
- `angle` or `speed` is serialized with a localized decimal comma.

For non-standard units such as `dB(A)`, using `unitText` is safer than inventing a non-standard `unitCode`. The code should be validated against the Schema.org/UN/CEFACT unit vocabulary before emission.

## 1.4 Review and rating hazards

If reviews are emitted, these rules are critical:

- Do not emit `aggregateRating` without genuine, product-specific review data.
- `ratingValue` must be numeric and within the declared scale.
- `reviewCount` and `ratingCount` must be non-negative integers.
- Do not fabricate ratings from internal compatibility scores.
- Do not expose ratings that are visible only to crawlers.
- Ratings must match the product page and not the entire catalog.

Google may ignore or penalize misleading review markup even if the JSON-LD is syntactically valid.

## 1.5 Localization and structured data

Structured-data labels such as `name` and `description` may be localized, but machine values must not be localized:

Bad:

```json
"price": "249,95 €",
"availability": "En stock",
"unitCode": "cm"
```

Better:

```json
"price": "249.95",
"priceCurrency": "EUR",
"availability": "https://schema.org/InStock",
"unitCode": "CMT"
```

The page’s visible language, HTML `lang`, canonical URL, and JSON-LD language should also be consistent.

### Schema verdict

**Status: BLOCKED pending the actual JSON-LD implementation and validation.**

Required test set:

- one in-stock product;
- one out-of-stock product;
- one product with variants;
- one product without a price;
- one product with no reviews;
- one non-EUR currency;
- one product with missing technical measurements;
- one localized page.

Validate with Google’s Rich Results Test, Schema Markup Validator, and custom JSON-schema-like application tests.

---

# 2. Aerodynamic recommendation formula

## 2.1 The formula is not supplied

The blueprint refers to `S_posture` and `S_windshield`, but provides no definitions, domains, weights, normalization functions, or final recommendation equation.

Consequently, the following cannot be verified:

- monotonicity;
- score bounds;
- denominator safety;
- behavior for missing measurements;
- whether a larger or smaller value is desirable;
- whether posture and windshield effects are independent;
- whether the result is stable around thresholds.

This is another blocking omission.

## 2.2 Required mathematical invariants

Every score-producing function should enforce:

```text
all inputs are finite;
all required denominators are non-zero;
all normalized values are in [0, 1];
all weights are finite and non-negative;
sum(weights) > 0;
final score is in [0, 100] or [0, 1], as explicitly specified.
```

A safe normalization primitive is:

```text
normalize(x, min, max):
    reject if x, min, or max is non-finite
    reject if max <= min
   