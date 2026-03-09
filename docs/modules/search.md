# Search Module

> Enhanced WordPress search with meta field and taxonomy support.

## Files

| File                                                                                                           | Lines | Purpose                                          |
| -------------------------------------------------------------------------------------------------------------- | ----- | ------------------------------------------------ |
| [SearchService.php](file:///Users/anumac/Documents/Helmetsan/helmetsan-core/includes/Search/SearchService.php) | 295   | Search query builder, AJAX handler, filter chips |

## What It Does

Extends WordPress core search to include post meta fields and taxonomy terms in results. Powers the helmet search & filter UI.

## Features

- **Identifier search** — When the search term `s` is set, results include any helmet or accessory whose post meta **exactly** matches that value for one of: `ean`, `gtin`, `upc`, `affiliate_asin`, `sku`, `mpn`, `fsn`. So users can find a product by pasting an ASIN, EAN, SKU, MPN, or FSN.
- **Text + identifier** — The main search matches both WordPress title/content and the identifier meta (OR), so keyword and barcode lookup work together.
- **Taxonomy filtering** — Filter by brand, helmet type, safety standard
- **AJAX endpoint** — Live search results via `wp_ajax_helmetsan_filter`
- **Filter chips** — Visual "active filter" chips rendered as HTML

## Key Methods

| Method                | Purpose                                    |
| --------------------- | ------------------------------------------ |
| `parseParams()`       | Sanitize and normalize search parameters   |
| `buildQueryArgs()`    | Convert parsed params → `WP_Query` args    |
| `query()`             | Execute the search query                   |
| `handleAjax()`        | AJAX handler for frontend search           |
| `renderActiveChips()` | Generate HTML for active filter indicators |
| `joinPostMeta()`      | SQL JOIN hook for meta field search        |
| `wherePostMeta()`     | SQL WHERE hook for meta field matching     |
| `distinct()`          | Ensures DISTINCT results when JOINing      |

## Supported Filter Parameters

| Param                     | Type   | Description                                         |
| ------------------------- | ------ | --------------------------------------------------- |
| `s`                       | string | Free-text search query                              |
| `brand`                   | string | Brand taxonomy slug                                 |
| `helmet_type`             | string | Helmet type taxonomy slug                           |
| `safety_standard`         | string | Safety standard filter                              |
| `min_price` / `max_price` | float  | Price range filter                                  |
| `sort`                    | string | Sort order (price_asc, price_desc, date, relevance) |
| `per_page`                | int    | Results per page                                    |
| `paged`                   | int    | Page number                                         |

## Hook Registration

```php
add_filter('posts_join', [$this, 'joinPostMeta']);
add_filter('posts_where', [$this, 'wherePostMeta']);
add_filter('posts_distinct', [$this, 'distinct']);
add_action('wp_ajax_helmetsan_search', [$this, 'handleAjax']);
add_action('wp_ajax_nopriv_helmetsan_search', [$this, 'handleAjax']);
```
