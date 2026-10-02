# Helmetsan Luna Implementation Blueprint

## Executive limitation

I cannot directly modify `HelmetsanWeb`, execute PHP, access `catalog.db`, call Amazon APIs, create background agents, or maintain an unattended long-running session from this chat. I can, however, provide the implementation specification, migration design, worker architecture, validation logic, test plan, and deployment sequence required for an engineering agent or developer to execute safely.

The implementation should be staged. No product should be promoted from collision quarantine, no affiliate redirect should be changed globally, and no Schema.org offer should be emitted unless its source, currency, timestamp, and availability are known.

---

# 0. Target architecture

```text
WordPress request
      │
      ├── RevenueService
      │     ├── request validation
      │     ├── variant resolution
      │     ├── identifier/status lookup
      │     ├── marketplace routing
      │     ├── collision-safe redirect selection
      │     └── telemetry
      │
      ├── DataApiController
      │     ├── canonical product document
      │     ├── JSON / Markdown negotiation
      │     ├── JSON-LD generation
      │     └── llms.txt manifests
      │
      └── CrossLinkService
            ├── catalog compatibility query
            ├── semantic fitment scoring
            ├── reciprocal recommendation generation
            └── post_meta persistence

SQLite catalog.db
      ├── helmets
      ├── helmet_variants
      ├── motorcycles
      ├── compatibility
      ├── asin_resolution_candidates
      ├── asin_resolution_runs
      └── affiliate_marketplace_config

Persistent cache
      ├── helmets_unified_master_memory_index.json
      ├── WordPress object cache
      └── generated fitment/link cache

Worker system
      ├── collision discovery worker
      ├── candidate validation worker
      ├── fitment/link regeneration worker
      ├── schema/API audit worker
      └── checkpoint and reconciliation worker
```

Recommended execution order:

1. Database migrations and read-only audit tools.
2. RevenueService in shadow mode.
3. DataApiController and Schema validation.
4. CrossLinkService regeneration.
5. Collision-resolution pipeline in dry-run mode.
6. Controlled promotions.
7. Production activation with rollback capability.

---

# 1. PILLAR 1 — WordPress affiliate routing

## 1.1 Marketplace registry

Do not hard-code marketplace behavior throughout `RevenueService.php`. Use a single immutable registry.

```php
final class AmazonMarketplaceRegistry
{
    public const MARKETS = [
        'US' => [
            'domain' => 'amazon.com',
            'locale' => 'en-US',
            'currency' => 'USD',
            'tag_key' => 'amazon_us_tag',
            'onelink_enabled' => false,
        ],
        'CA' => [
            'domain' => 'amazon.ca',
            'locale' => 'en-CA',
            'currency' => 'CAD',
            'tag_key' => 'amazon_ca_tag',
            'onelink_enabled' => true,
        ],
        'MX' => [
            'domain' => 'amazon.com.mx',
            'locale' => 'es-MX',
            'currency' => 'MXN',
            'tag_key' => 'amazon_mx_tag',
            'onelink_enabled' => false,
        ],
        'BR' => [
            'domain' => 'amazon.com.br',
            'locale' => 'pt-BR',
            'currency' => 'BRL',
            'tag_key' => 'amazon_br_tag',
            'onelink_enabled' => false,
        ],
        'UK' => [
            'domain' => 'amazon.co.uk',
            'locale' => 'en-GB',
            'currency' => 'GBP',
            'tag_key' => 'amazon_uk_tag',
            'onelink_enabled' => true,
        ],
        'DE' => [
            'domain' => 'amazon.de',
            'locale' => 'de-DE',
            'currency' => 'EUR',
            'tag_key' => 'amazon_de_tag',
            'onelink_enabled' => true,
        ],
        'FR' => [
            'domain' => 'amazon.fr',
            'locale' => 'fr-FR',
            'currency' => 'EUR',
            'tag_key' => 'amazon_fr_tag',
            'onelink_enabled' => true,
        ],
        'IT' => [
            'domain' => 'amazon.it',
            'locale' => 'it-IT',
            'currency' => 'EUR',
            'tag_key' => 'amazon_it_tag',
            'onelink_enabled' => true,
        ],
        'ES' => [
            'domain' => 'amazon.es',
            'locale' => 'es-ES',
            'currency' => 'EUR',
            'tag_key' => 'amazon_es_tag',
            'onelink_enabled' => true,
        ],
        'NL' => [
            'domain' => 'amazon.nl',
            'locale' => 'nl-NL',
            'currency' => 'EUR',
            'tag_key' => 'amazon_nl_tag',
            'onelink_enabled' => false,
        ],
        'PL' => [
            'domain' => 'amazon.pl',
            'locale' => 'pl-PL',
            'currency' => 'PLN',
            'tag_key' => 'amazon_pl_tag',
            'onelink_enabled' => false,
        ],
        'SE' => [
            'domain' => 'amazon.se',
            'locale' => 'sv-SE',
            'currency' => 'SEK',
            'tag_key' => 'amazon_se_tag',
            'onelink_enabled' => false,
        ],
        'BE' => [
            'domain' => 'amazon.com.be',
            'locale' => 'nl-BE',
            'currency' => 'EUR',
            'tag_key' => 'amazon_be_tag',
            'onelink_enabled' => false,
        ],
        'TR' => [
            'domain' => 'amazon.com.tr',
            'locale' => 'tr-TR',
            'currency' => 'TRY',
            'tag_key' => 'amazon_tr_tag',
            'onelink_enabled' => false,
        ],
        'IN' => [
            'domain' => 'amazon.in',
            'locale' => 'en-IN',
            'currency' => 'INR',
            'tag_key' => 'amazon_in_tag',
            'onelink_enabled' => false,
        ],
        'JP' => [
            'domain' => 'amazon.co.jp',
            'locale' => 'ja-JP',
            'currency' => 'JPY',
            'tag_key' => 'amazon_jp_tag',
            'onelink_enabled' => false,
        ],
        'AU' => [
            'domain' => 'amazon.com.au',
            'locale' => 'en-AU',
            'currency' => 'AUD',
            'tag_key' => 'amazon_au_tag',
            'onelink_enabled' => false,
        ],
        'SG' => [
            'domain' => 'amazon.sg',
            'locale' => 'en-SG',
            'currency' => 'SGD',
            'tag_key' => 'amazon_sg_tag',
            'onelink_enabled' => false,
        ],
        'AE' => [
            'domain' => 'amazon.ae',
            'locale' => 'en-AE',
            'currency' => 'AED',
            'tag_key' => 'amazon_ae_tag',
            'onelink_enabled' => false,
        ],
        'SA' => [
            'domain' => 'amazon.sa',
            'locale' => 'ar-SA',
            'currency' => 'SAR',
            'tag_key' => 'amazon_sa_tag',
            'onelink_enabled' => false,
        ],
        'EG' => [
            'domain' => 'amazon.eg',
            'locale' => 'en-EG',
            'currency' => 'EGP',
            'tag_key' => 'amazon_eg_tag',
            'onelink_enabled' => false,
        ],
        'ZA' => [
            'domain' => 'amazon.co.za',
            'locale' => 'en-ZA',
            'currency' => 'ZAR',
            'tag_key' => 'amazon_za_tag',
            'onelink_enabled' => false,
        ],
    ];
}
```

The exact affiliate tag must come from configuration, not source code. The `vtete-20` tag should only be used if it is genuinely enrolled and authorized for the relevant OneLink configuration.

```php
final class AffiliateTagProvider
{
    public function getAmazonTag(string $marketplace): ?string
    {
        $tag = get_option('helmetsan_amazon_tag_' . strtolower($marketplace));

        if (!$tag || !preg_match('/^[A-Za-z0-9_-]{3,64}$/', $tag)) {
            return null;
        }

        return $tag;
    }
}
```

---

## 1.2 Request contract

Accepted route:

```text
/go/{slug}
```

Optional query parameters:

```text
/go/{slug}?variant={variant-id}&marketplace={marketplace-id}
```

Validation requirements:

- `slug`: resolve through WordPress permalink/post lookup.
- `variant`: treat as an opaque internal identifier; never interpolate directly into SQL.
- `marketplace`: uppercase and validate against registry.
- Never trust a user-supplied ASIN.
- Never use a query parameter to override a verified product-to-ASIN relationship.
- Unsupported marketplace falls back to geo-detected marketplace or US.

```php
final class RedirectRequest
{
    public function __construct(
        public readonly string $slug,
        public readonly ?string $variantId,
        public readonly string $marketplace
    ) {}
}
```

---

## 1.3 Four-stage redirect engine

### Stage 1 — Verified direct ASIN

Conditions:

```text
asin_status = verified
asin belongs to requested helmet or variant
ASIN passes global uniqueness constraints
marketplace route is enabled
```

Destination:

```text
https://www.amazon.{tld}/dp/{asin}?tag={regional_tag}
```

Build URLs with `http_build_query()`.

```php
private function directAsinUrl(
    string $domain,
    string $asin,
    string $tag
): string {
    return 'https://' . $domain . '/dp/' . rawurlencode($asin)
        . '?' . http_build_query(['tag' => $tag]);
}
```

Do not include `price`, `currency`, or availability in a redirect URL unless the affiliate program explicitly supports those parameters.

### Stage 2 — OneLink forwarding

Eligible markets:

```text
UK, CA, DE, FR, IT, ES
```

Only use this route when:

- the US ASIN is verified;
- OneLink enrollment is confirmed;
- the OneLink tag is configured;
- regional direct-ASIN routing is not preferred or unavailable;
- the current request is not already being recursively redirected.

Destination:

```text
https://www.amazon.com/dp/{asin}?tag=vtete-20
```

Do not call OneLink for an item known to be unavailable in the US marketplace.

### Stage 3 — Collision or missing-ASIN localized search

Destination:

```text
https://www.amazon.{tld}/s?k={encoded query}&tag={regional tag}
```

Query composition should be deterministic:

```text
{brand} {family} {model or graphic} {finish if materially identifying}
```

Do not include arbitrary descriptions, marketing copy, or safety claims.

Example:

```php
$query = implode(' ', array_filter([
    $helmet->brand,
    $helmet->family,
    $helmet->model,
    $variant?->finish,
]));

$url = 'https://' . $market['domain'] . '/s/?' . http_build_query([
    'k'   => $query,
    'tag' => $tag,
]);
```

### Stage 4 — Brand storefront fallback

Use only where a validated brand storefront URL exists.

Priority:

1. Configured brand storefront for marketplace.
2. Marketplace search for brand.
3. Helmetsan product page with a clear user-facing error.

Do not redirect to an unverified external URL stored in arbitrary post meta.

---

## 1.4 Geo-detection

Geo-detection should be a preference, not a correctness dependency.

```text
explicit marketplace query parameter
    > cookie/user preference
    > trusted reverse-proxy country header
    > WordPress locale
    > US
```

Never trust `X-Forwarded-For` or country headers unless the proxy is explicitly configured.

Cache geo results briefly:

```text
key: helmetsan_geo:{ip_hash}
TTL: 6 hours
```

Hash or truncate IP data. Do not store raw IPs in redirect logs unless legally required.

---

## 1.5 Variant-level resolution

Recommended SQLite schema:

```sql
CREATE TABLE IF NOT EXISTS helmet_variants (
    variant_id TEXT PRIMARY KEY,
    helmet_id INTEGER NOT NULL,
    asin_us TEXT,
    sku TEXT NOT NULL,
    finish TEXT,
    search_query TEXT,
    availability TEXT,
    price_usd REAL,
    price_inr REAL,
    asin_status TEXT NOT NULL DEFAULT 'missing',
    asin_confidence REAL,
    asin_source TEXT,
    asin_verified_at TEXT,
    FOREIGN KEY (helmet_id) REFERENCES helmets(id)
);

CREATE INDEX IF NOT EXISTS idx_variants_helmet
ON helmet_variants(helmet_id);

CREATE INDEX IF NOT EXISTS idx_variants_asin
ON helmet_variants(asin_us);
```

Resolution precedence:

```text
requested variant ASIN
    > helmet's verified ASIN
    > helmet-level localized search
```

A variant ASIN must never silently inherit a parent ASIN if the variant is known to differ by product identity rather than merely color.

---

## 1.6 In-memory and post-meta alignment

Use one canonical normalized document:

```php
[
    'helmet_id' => 1234,
    'canonical_sku' => 'HSN-HLM-01234',
    'asin_us' => 'B012345678',
    'asin_status' => 'verified',
    'variants' => [
        [
            'variant_id' => 'v-1234-black-m',
            'sku' => 'HSN-HLM-01234-BLK-M',
            'asin_us' => 'B012345678',
            'asin_status' => 'verified',
        ],
    ],
    'affiliate_links' => [
        'US' => [
            'type' => 'direct_asin',
            'url' => 'https://amazon.com/dp/B012345678?...',
        ],
    ],
    'index_version' => 3,
    'generated_at' => '2025-...',
]
```

Post meta:

```text
_affiliate_links_json
_affiliate_links_version
_affiliate_links_generated_at
_affiliate_links_hash
```

The existing `affiliate_links_json` should either be migrated to this structure or wrapped in a versioned envelope:

```json
{
  "schema_version": 2,
  "helmet_id": 1234,
  "links": {},
  "generated_at": "2025-01-01T00:00:00Z"
}
```

Use a SHA-256 hash to detect stale WordPress metadata.

---

## 1.7 Redirect safety and observability

Use:

```php
wp_safe_redirect($url, 302);
exit;
```

A `302` or `307` is preferable during rollout. Do not use a permanent `301` until route correctness has been demonstrated.

Log structured events:

```json
{
  "event": "affiliate_redirect",
  "helmet_id": 1234,
  "variant_id": "v-1234-black",
  "marketplace": "UK",
  "stage": 1,
  "asin_status": "verified",
  "destination_domain": "amazon.co.uk",
  "request_id": "uuid",
  "timestamp": "..."
}
```

Do not log full query strings containing personal identifiers.

---

# 2. PILLAR 2 — Schema.org and LLM API

## 2.1 Canonical product document

Create a service independent of the controller:

```php
final class ProductDocumentService
{
    public function build(int $helmetId, ?string $variantId = null): array
    {
        // Load catalog entities.
        // Normalize identifiers.
        // Attach verified offers only.
        // Attach fitment and affiliate metadata.
    }
}
```

The controller should not construct product data directly from scattered post meta fields.

---

## 2.2 Canonical identifiers

SKU format:

```text
HSN-HLM-{zero-padded helmet ID}
```

Variant SKU:

```text
HSN-HLM-{helmet ID}-{normalized colorway}-{normalized size}
```

Example:

```text
HSN-HLM-01234
HSN-HLM-01234-MATTE-BLACK-M
```

Identifier rules:

- `sku`: canonical Helmetsan SKU.
- `mpn`: manufacturer part number only when sourced from manufacturer/catalog data.
- `gtin13`: only if validated as a 13-digit EAN/GTIN with checksum.
- `asin`: place in an additional identifier block or `sameAs`-style internal data, not as `gtin13`.
- Never convert an ASIN into a GTIN.

EAN-13 checksum validation:

```text
sum = d1 + 3