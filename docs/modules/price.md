# Price Module

> Price formatting, multi-currency support, and price history tracking.

## Files

| File                                                                                                        | Lines | Purpose                                      |
| ----------------------------------------------------------------------------------------------------------- | ----- | -------------------------------------------- |
| [PriceService.php](file:///Users/anumac/Documents/Helmetsan/helmetsan-core/includes/Price/PriceService.php) | ~60   | Currency-aware price formatting              |
| [PriceHistory.php](file:///Users/anumac/Documents/Helmetsan/helmetsan-core/includes/Price/PriceHistory.php) | 178   | Historical price tracking in custom DB table |

## PriceService

Retrieves and formats helmet prices from post meta:

```php
$price = $priceService->getPrice($helmetId, 'USD');
// Returns: "$599.99" or null
```

Currently supports USD, EUR, GBP with plans for full multi-currency support via GeoService.

## PriceHistory

Stores price snapshots over time for trend analysis and charts.

### Database Table

`wp_helmetsan_price_history`:

```sql
CREATE TABLE wp_helmetsan_price_history (
    id bigint(20) unsigned AUTO_INCREMENT,
    helmet_id bigint(20) unsigned NOT NULL,
    marketplace varchar(50) NOT NULL,
    country_code char(2) NOT NULL,
    currency char(3) NOT NULL,
    price decimal(10,2) NOT NULL,
    captured_at datetime DEFAULT CURRENT_TIMESTAMP,
    -- Indexed: (helmet_id, marketplace, captured_at), (country_code, captured_at)
);
```

### Key Methods

| Method          | Purpose                                            |
| --------------- | -------------------------------------------------- |
| `record()`      | Store a price snapshot                             |
| `history()`     | Get price history by helmet/marketplace/date range |
| `latest()`      | Get latest price per marketplace for a helmet      |
| `ensureTable()` | Create/upgrade DB table on activation              |

### Usage

```php
// Record a price from Amazon US
$priceHistory->record($helmetId, 'amazon_us', 'US', 'USD', 599.99);

// Get 30-day price history
$data = $priceHistory->history($helmetId, 'amazon_us', '-30 days');

// Get latest price from each marketplace
$latestPrices = $priceHistory->latest($helmetId, 'US');
```

## Future: Multi-Currency Upgrade

`PriceService` will be upgraded to:

- Use `GeoService` to detect visitor's currency
- Query `PriceHistory` for latest marketplace prices
- Convert currencies when needed
- Display localized formatting
