# SECTION 2.3 — Schema.org Product and AggregateOffer Generation

## 2.3.1 Canonical Product Model

Helmetsan should maintain one canonical product entity per helmet model and one canonical variant entity per purchasable configuration.

### Canonical identifiers

| Identifier | Purpose |
|---|---|
| `helmet_id` | Internal immutable UUID or integer |
| `model_slug` | Stable editorial URL slug |
| `brand` | Manufacturer brand |
| `model_name` | Product-family name |
| `variant_id` | Internal variant identifier |
| `asin` | Amazon ASIN, nullable until verified |
| `ean13` | Validated EAN-13, nullable |
| `mpn` | Manufacturer part number |
| `sku` | Merchant/catalog SKU |
| `canonical_url` | One canonical Helmetsan URL |
| `image_urls[]` | Approved image URLs |
| `catalog_status` | `draft`, `quarantined`, `verified`, `retired` |

A model page should represent the helmet family. A variant should represent a commercially meaningful configuration such as:

- shell size,
- colorway,
- graphic,
- homologation,
- size,
- region-specific SKU,
- or manufacturer part number.

Do not create a separate `Product` entity merely for a color unless that color has an independently purchasable SKU or materially distinct specifications.

---

## 2.3.2 Product JSON-LD Structure

```json
{
  "@context": "https://schema.org",
  "@type": "Product",
  "@id": "https://helmetsan.com/helmets/shoei-x-fourteen#product",
  "url": "https://helmetsan.com/helmets/shoei-x-fourteen",
  "name": "Shoei X-Fourteen",
  "alternateName": [
    "Shoei X-14",
    "Shoei X Fourteen"
  ],
  "brand": {
    "@type": "Brand",
    "@id": "https://helmetsan.com/brands/shoei#brand",
    "name": "Shoei"
  },
  "manufacturer": {
    "@type": "Organization",
    "name": "Shoei Co., Ltd."
  },
  "model": "X-Fourteen",
  "category": "Full-face motorcycle helmet",
  "description": "Structured editorial description of the Shoei X-Fourteen.",
  "image": [
    "https://cdn.helmetsan.com/helmets/shoei-x-fourteen/front.webp",
    "https://cdn.helmetsan.com/helmets/shoei-x-fourteen/side.webp"
  ],
  "sku": "SHOEI-X14",
  "mpn": "X14",
  "gtin13": "4980760000000",
  "additionalProperty": [
    {
      "@type": "PropertyValue",
      "name": "Helmet category",
      "value": "Track/Race"
    },
    {
      "@type": "PropertyValue",
      "name": "Homologation",
      "value": "ECE 22.06"
    },
    {
      "@type": "PropertyValue",
      "name": "Shell material",
      "value": "AIM+ composite"
    },
    {
      "@type": "PropertyValue",
      "name": "Weight",
      "value": "1,550 g"
    }
  ],
  "isRelatedTo": [
    {
      "@type": "Product",
      "@id": "https://helmetsan.com/helmets/shoei-x-fourteen#variant-black-m"
    }
  ],
  "offers": {
    "@type": "AggregateOffer",
    "priceCurrency": "USD",
    "lowPrice": "649.99",
    "highPrice": "749.99",
    "offerCount": 3,
    "offers": [
      {
        "@type": "Offer",
        "@id": "https://helmetsan.com/helmets/shoei-x-fourteen#offer-us",
        "url": "https://www.amazon.com/dp/B000000000?tag=helmetsan-20",
        "priceCurrency": "USD",
        "price": "699.99",
        "availability": "https://schema.org/InStock",
        "priceValidUntil": "2026-12-31",
        "seller": {
          "@type": "Organization",
          "name": "Amazon"
        },
        "shippingDetails": {
          "@type": "OfferShippingDetails",
          "shippingDestination": {
            "@type": "DefinedRegion",
            "addressCountry": "US"
          },
          "deliveryTime": {
            "@type": "ShippingDeliveryTime",
            "handlingTime": {
              "@type": "QuantitativeValue",
              "minValue": 0,
              "maxValue": 2,
              "unitCode": "DAY"
            },
            "transitTime": {
              "@type": "QuantitativeValue",
              "minValue": 1,
              "maxValue": 7,
              "unitCode": "DAY"
            }
          }
        },
        "hasMerchantReturnPolicy": {
          "@type": "MerchantReturnPolicy",
          "applicableCountry": "US",
          "returnPolicyCategory": "https://schema.org/MerchantReturnFiniteReturnWindow",
          "merchantReturnDays": 30,
          "returnMethod": "https://schema.org/ReturnByMail",
          "returnFees": "https://schema.org/ReturnFeesCustomerResponsibility"
        }
      }
    ]
  }
}
```

### Important implementation rules

1. `gtin13` is emitted only after the EAN-13 checksum and normalization pass.
2. `mpn` is emitted only when sourced from a manufacturer or trusted seller record.
3. Do not emit invented prices, availability, delivery times, return policies, or GTINs.
4. `priceValidUntil` must be generated from the source freshness policy, not arbitrarily set far into the future.
5. If marketplace data is stale, omit the offer or set availability to `LimitedAvailability` only when that state is verified.
6. `AggregateOffer.lowPrice` and `highPrice` must be calculated from the currently valid child offers in the same currency.
7. Cross-currency offers must not be combined into one `AggregateOffer`.

---

## 2.3.3 Variant JSON-LD

A variant page should use its own `Product` entity linked to the parent model.

```json
{
  "@context": "https://schema.org",
  "@type": "Product",
  "@id": "https://helmetsan.com/helmets/shoei-x-fourteen/black-medium#product",
  "isVariantOf": {
    "@type": "Product",
    "@id": "https://helmetsan.com/helmets/shoei-x-fourteen#product"
  },
  "name": "Shoei X-Fourteen Black Medium",
  "brand": {
    "@type": "Brand",
    "name": "Shoei"
  },
  "model": "X-Fourteen",
  "color": "Black",
  "size": "Medium",
  "sku": "SHOEI-X14-BLK-M",
  "mpn": "X14-BLK-M",
  "gtin13": "4980760123456",
  "offers": {
    "@type": "Offer",
    "url": "https://www.amazon.com/dp/B000000000?tag=helmetsan-20",
    "priceCurrency": "USD",
    "price": "699.99",
    "availability": "https://schema.org/InStock"
  }
}
```

Use `isVariantOf` for the parent model. Use `hasVariant` on the parent only when all listed variants are real, indexable, and internally resolvable.

---

## 2.3.4 Multi-Currency Offer Architecture

Supported currencies:

```php
const SUPPORTED_CURRENCIES = [
    'USD', 'EUR', 'GBP', 'INR',
    'JPY', 'AED', 'CAD', 'AUD'
];
```

A currency offer record:

```sql
CREATE TABLE product_offers (
    id INTEGER PRIMARY KEY,
    helmet_id INTEGER NOT NULL,
    variant_id INTEGER NULL,
    region_code TEXT NOT NULL,
    marketplace_code TEXT NOT NULL,
    currency_code TEXT NOT NULL,
    price_minor INTEGER NOT NULL,
    list_price_minor INTEGER NULL,
    availability TEXT NOT NULL,
    source_url TEXT NOT NULL,
    affiliate_url TEXT NOT NULL,
    source_name TEXT NOT NULL,
    observed_at TEXT NOT NULL,
    price_valid_until TEXT NOT NULL,
    shipping_json TEXT NULL,
    return_policy_json TEXT NULL,
    source_hash TEXT NOT NULL,
    FOREIGN KEY (helmet_id) REFERENCES helmets(id)
);

CREATE INDEX idx_product_offers_lookup
ON product_offers(helmet_id, region_code, currency_code, availability);
```

### Currency handling

Prices must be stored as integer minor units:

| Currency | Minor-unit handling |
|---|---|
| USD | cents |
| EUR | cents |
| GBP | pence |
| INR | paise, subject to source precision |
| JPY | zero decimal |
| AED | fils |
| CAD | cents |
| AUD | cents |

Do not calculate displayed prices from foreign exchange rates unless the page explicitly identifies the result as an estimate. Schema.org offers should use observed marketplace prices whenever possible.

### Currency-specific AggregateOffer

```json
{
  "@type": "AggregateOffer",
  "priceCurrency": "EUR",
  "lowPrice": "549.00",
  "highPrice": "699.00",
  "offerCount": 2,
  "offers": [
    {
      "@type": "Offer",
      "priceCurrency": "EUR",
      "price": "549.00",
      "availability": "https://schema.org/InStock",
      "url": "https://www.amazon.de/dp/B000000000?tag=helmetsan-21"
    },
    {
      "@type": "Offer",
      "priceCurrency": "EUR",
      "price": "699.00",
      "availability": "https://schema.org/LimitedAvailability",
      "url": "https://www.amazon.fr/dp/B000000000?tag=helmetsan-22"
    }
  ]
}
```

A product can expose a `offers` array containing multiple currency-specific `AggregateOffer` objects, but each individual aggregate must have one currency.

---

# SECTION 2.4 — Shipping and Merchant Return Markup

## 2.4.1 Shipping Policy Data Contract

```json
{
  "country": "US",
  "currency": "USD",
  "shippingRate": {
    "value": 0,
    "currency": "USD"
  },
  "handlingDays": {
    "min": 0,
    "max": 2
  },
  "transitDays": {
    "min": 1,
    "max": 7
  },
  "service": "Standard"
}
```

PHP generator:

```php
function shippingDetails(array $shipping): array
{
    $result = [
        '@type' => 'OfferShippingDetails',
        'shippingDestination' => [
            '@type' => 'DefinedRegion',
            'addressCountry' => $shipping['country'],
        ],
        'deliveryTime' => [
            '@type' => 'ShippingDeliveryTime',
            'handlingTime' => [
                '@type' => 'QuantitativeValue',
                'minValue' => $shipping['handlingDays']['min'],
                'maxValue' => $shipping['handlingDays']['max'],
                'unitCode' => 'DAY',
            ],
            'transitTime' => [
                '@type' => 'QuantitativeValue',
                'minValue' => $shipping['transitDays']['min'],
                'maxValue' => $shipping['transitDays']['max'],
                'unitCode' => 'DAY',
            ],
        ],
    ];

    if (isset($shipping['shippingRate'])) {
        $result['shippingRate'] = [
            '@type' => 'MonetaryAmount',
            'value' => $shipping['shippingRate']['value'],
            'currency' => $shipping['shippingRate']['currency'],
        ];
    }

    return $result;
}
```

## 2.4.2 Return Policy Contract

```json
{
  "applicableCountry": ["US"],
  "returnPolicyCategory": "MerchantReturnFiniteReturnWindow",
  "merchantReturnDays": 30,
  "returnMethod": "ReturnByMail",
  "returnFees": "ReturnFeesCustomerResponsibility"
}
```

```php
function merchantReturnPolicy(array $policy): array
{
    return [
        '@type' => 'MerchantReturnPolicy',
        'applicableCountry' => $policy['applicableCountry'],
        'returnPolicyCategory' =>
            'https://schema.org/' . $policy['returnPolicyCategory'],
        'merchantReturnDays' => $policy['merchantReturnDays'],
        'returnMethod' =>
            'https://schema.org/' . $policy['returnMethod'],
        'returnFees' =>
            'https://schema.org/' . $policy['returnFees'],
    ];
}
```

The policy must be marketplace-specific. An Amazon US return policy must not be copied onto an Amazon UK or direct-retailer offer unless the source explicitly confirms it.

---

# SECTION 2.5 — DataApiController

## 2.5.1 Supported Endpoints

| Endpoint | Function |
|---|---|
| `/data/helmet/{slug}` | Product data |
| `/data/helmet/{slug}?format=json` | Machine-readable JSON |
| `/data/helmet/{slug}?format=md` | Markdown representation |
| `/data/helmet/{slug}?format=jsonld` | JSON-LD only |
| `/llms.txt` | Concise site and catalog map |
| `/llms-full.txt` | Expanded machine-readable catalog documentation |
| `/api/motorcycles/{slug}` | Motorcycle fitment and linked helmets |
| `/api/helmets/{slug}/fitment` | Helmet-to-motorcycle compatibility |

Canonical identifiers returned by every object:

```json
{
  "id": "helmet:shoei:x-fourteen",
  "type": "Helmet",
  "canonicalUrl": "https://helmetsan.com/helmets/shoei-x-fourteen",
  "slug": "shoei-x-fourteen",
  "brand": "Shoei",
  "model": "X-Fourteen",
  "asin": "B000000000",
  "ean13": "4980760000000",
  "mpn": "X14",
  "catalogStatus": "verified",
  "lastVerifiedAt": "2026-02-21T12:00:00Z"
}
```

## 2.5.2 Content Negotiation

Precedence:

1. Explicit `?format=`.
2. File endpoint such as `/llms.txt`.
3. `Accept` header.
4. Default HTML or JSON according to endpoint.

Supported formats:

```php
final class ResponseFormat
{
    public const JSON = 'json';
    public const MARKDOWN = 'md';
    public const JSONLD = 'jsonld';
    public const TEXT = 'text';
}
```

Controller skeleton:

```php
final class DataApiController
{
    public function __construct(
        private HelmetRepository $helmets,
        private CrossLinkService $crossLinks,
        private JsonLdBuilder $jsonLd,
        private AffiliateUrlBuilder $affiliates
    ) {}

    public function helmet(ServerRequestInterface $request, string $slug): ResponseInterface
    {
        $format = $this->resolveFormat($request);
        $helmet = $this->helmets->findVerifiedBySlug($slug);

        if (!$helmet) {
            return $this->error(404, 'Helmet not found');
        }

        $payload = $this->buildPayload($helmet);

        return match ($format) {
            ResponseFormat::JSON =>
                $this->json($payload),
            ResponseFormat::JSONLD =>
                $this->json($this->jsonLd->forHelmet($helmet)),
            ResponseFormat::MARKDOWN =>
                $this->markdown($this->toMarkdown($payload)),
            default =>
                $this->json($payload),
        };
    }

    public function llms(ServerRequestInterface $request, bool $full = false): ResponseInterface
    {
        $content = $full
            ? $this->helmets->buildLlmsFullDocument()
            : $this->helmets->buildLlmsDocument();

        return new Response(
            200,
            [
                'Content-Type' => 'text/plain; charset=utf-8',
                'Cache-Control' => 'public, max-age=3600',
            ],
            $content
        );
    }

    private function resolveFormat(ServerRequestInterface $request): string
    {
        $query = $request->getQueryParams();

        if (isset($query['format'])) {
            return match (strtolower($query['format'])) {
                'json' => ResponseFormat::JSON,
                'md', 'markdown' => ResponseFormat::MARKDOWN,
                'jsonld' => ResponseFormat::JSONLD,
                default => ResponseFormat::JSON,
            };
        }

        $accept = strtolower($request->getHeaderLine('Accept'));

        if (str_contains($accept, 'text/markdown')) {
            return ResponseFormat::MARKDOWN;
        }

        if (str_contains($accept, 'application/ld+json')) {
            return ResponseFormat::JSONLD;
        }

        return ResponseFormat::JSON;
    }

    private function buildPayload(array $helmet): array
    {
        return [
            'id' => $helmet['canonical_id'],
            'type' => 'Helmet',
            'canonicalUrl' => $helmet['canonical_url'],
            'product' => $helmet,
            'offers' => $this->affili