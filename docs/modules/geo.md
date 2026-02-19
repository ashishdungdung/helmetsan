# Geo Module

> Visitor country detection and geographic routing.

## Files

| File                                                                                                  | Lines | Purpose                                         |
| ----------------------------------------------------------------------------------------------------- | ----- | ----------------------------------------------- |
| [GeoService.php](file:///Users/anumac/Documents/Helmetsan/helmetsan-core/includes/Geo/GeoService.php) | 155   | IP → country detection, region/currency mapping |

## Detection Chain

GeoService detects the visitor's country in priority order:

1. **Query parameter** — `?country=XX` (manual override for testing)
2. **Cloudflare header** — `HTTP_CF_IPCOUNTRY` (automatic when behind CF)
3. **Cookie** — `helmetsan_country` (cached from previous visit)
4. **Default** — Falls back to `US`

Once resolved, the country is cached in a `helmetsan_country` cookie for 24 hours.

## Key Methods

| Method       | Purpose                                             |
| ------------ | --------------------------------------------------- |
| `detect()`   | Resolve visitor's 2-letter country code             |
| `region()`   | Map country → region (e.g., `US` → `north_america`) |
| `currency()` | Map country → currency (e.g., `IN` → `INR`)         |
| `register()` | Hook into `init` to set country early in request    |

## Country Map (22 countries)

| Region        | Countries                  | Currency                          |
| ------------- | -------------------------- | --------------------------------- |
| North America | US, CA, MX                 | USD, CAD, MXN                     |
| Europe        | UK, DE, FR, IT, ES, PL     | GBP, EUR, PLN                     |
| Asia Pacific  | IN, JP, AU                 | INR, JPY, AUD                     |
| Africa        | NG, KE, EG, MA, GH, UG, TZ | NGN, KES, EGP, MAD, GHS, UGX, TZS |
| South America | BR                         | BRL                               |

## Integration with MarketplaceRouter

```php
$country = $geo->detect();            // e.g., "DE"
$region  = $geo->region($country);     // "europe"
$currency = $geo->currency($country);  // "EUR"

// Router uses country + region to find connectors
$connectors = $router->connectorsFor($country);
```
