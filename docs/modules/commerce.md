# Commerce Module

> Manages marketplace definitions, pricing records, and offer data.

## Files

| File                                                                                                                 | Lines | Purpose                       |
| -------------------------------------------------------------------------------------------------------------------- | ----- | ----------------------------- |
| [CommerceService.php](file:///Users/anumac/Documents/Helmetsan/helmetsan-core/includes/Commerce/CommerceService.php) | 342   | Core commerce data management |

## What It Does

Handles the ingestion and management of commerce-specific data entities:

- **Marketplaces** — Store definitions (Amazon US, RevZilla, FC-Moto, etc.)
- **Pricing** — Per-country, per-marketplace price records
- **Offers** — Time-bound deals with discount tracking
- **Currencies** — Exchange rate and currency definitions

## Key Methods

| Method                  | Purpose                                                                      |
| ----------------------- | ---------------------------------------------------------------------------- |
| `upsertFromPayload()`   | Route data to the correct handler based on `entity` field                    |
| `upsertMarketplace()`   | Create/update marketplace definitions in `helmetsan_marketplaces` option     |
| `upsertPricing()`       | Store pricing data in post meta (`pricing_records_json`, `geo_pricing_json`) |
| `upsertOffer()`         | Store time-bound offer data per helmet                                       |
| `upsertCurrency()`      | Manage currency definitions                                                  |
| `computeBestOffer()`    | Find the cheapest offer from a list                                          |
| `resolveHelmetPostId()` | Map helmet slug → WordPress post ID                                          |

## Data Storage

| Data         | Storage                               | Format                                |
| ------------ | ------------------------------------- | ------------------------------------- |
| Marketplaces | `wp_options → helmetsan_marketplaces` | JSON object indexed by marketplace ID |
| Pricing      | Post meta → `pricing_records_json`    | JSON array of per-country records     |
| Geo Pricing  | Post meta → `geo_pricing_json`        | JSON object indexed by country code   |
| Offers       | Post meta → `offer_records_json`      | JSON array of offer records           |
| Currencies   | `wp_options → helmetsan_currencies`   | JSON object indexed by currency code  |

## Deduplication

All upsert methods use SHA-256 hashing to prevent redundant writes:

```php
$newHash = hash('sha256', wp_json_encode($record));
if (hash_equals($oldHash, $newHash)) {
    return ['ok' => true, 'action' => 'skipped'];
}
```

## JSON Schemas

| Schema      | Location                                                                                                                |
| ----------- | ----------------------------------------------------------------------------------------------------------------------- |
| Marketplace | [`data/schemas/marketplace.schema.json`](file:///Users/anumac/Documents/Helmetsan/data/schemas/marketplace.schema.json) |
| Pricing     | [`data/schemas/pricing.schema.json`](file:///Users/anumac/Documents/Helmetsan/data/schemas/pricing.schema.json)         |
| Offer       | [`data/schemas/offer.schema.json`](file:///Users/anumac/Documents/Helmetsan/data/schemas/offer.schema.json)             |
