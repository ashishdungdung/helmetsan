# Rich schema (JSON-LD) for search

Helmetsan outputs JSON-LD structured data so **helmets, accessories, and motorcycles** can be eligible for Google Search **product rich results** (product snippets, stars, price, breadcrumbs). The plugin also outputs **BreadcrumbList**, **WebSite**, and **Organization** where relevant.

## What is output

| Type | Where | Purpose |
|------|--------|---------|
| **Product** | Single helmet, accessory, motorcycle | Product rich results: name, image, brand, offers (with **priceValidUntil**), optional aggregateRating and review |
| **BreadcrumbList** | Singular and archive pages | Breadcrumb rich results in SERP |
| **WebSite** | Front page only | Sitelinks search box; site identity |
| **Organization** | Front page only | Knowledge panel / site identity |
| **ItemList** | Helmet / accessory / motorcycle archive pages | List/carousel rich results (first 20 items) |

## Product schema (helmets, accessories, motorcycles)

- **Helmet:** Name, url, description, image, brand (from `rel_brand`), **offers** (price from `best_offer_json` or `price_retail_usd`, **priceValidUntil** from offer `valid_until` or default 30 days), **additionalProperty** (weight, SHARP safety rating). Optional **aggregateRating** and **review** from meta (see below).
- **Accessory:** Same Product shape; price from `price_json` or omitted; optional rating/review.
- **Motorcycle:** Same Product shape; brand from make/model; optional rating/review.

All product types now get **priceValidUntil** in the Offer so Google can show “valid until” and treat the offer correctly.

## Optional: star ratings and reviews (aggregateRating, review)

For **rich snippets with stars** and review count, add **consumer** rating/review data. The plugin does **not** use SHARP or other safety scores as `aggregateRating` (that would be misleading).

### Meta keys (helmet and accessory)

- **`aggregate_rating_json`** — One object for the product:
  ```json
  {
    "ratingValue": 4.5,
    "reviewCount": 24,
    "bestRating": 5
  }
  ```
  Only output when `reviewCount` > 0.

- **`reviews_json`** — Array of individual reviews:
  ```json
  [
    {
      "author": { "name": "Jane R." },
      "datePublished": "2025-01-15",
      "reviewBody": "Great fit and finish.",
      "reviewRating": { "ratingValue": 5, "bestRating": 5 }
    }
  ]
  ```

When these are set and valid, the plugin adds `aggregateRating` and `review` to the Product JSON-LD. You can populate them via import, a review CPT, or an external review provider.

## Other schema you can add

- **FAQPage** — If you add FAQ blocks on product or category pages, output FAQ schema for FAQ rich results.
- **HowTo** — For “how to choose a helmet” or sizing guides.
- **Article** / **BlogPosting** — For blog or editorial content.
- **LocalBusiness** — For dealer or store pages (address, opening hours).
- **ItemList** — Already output on helmet/accessory/motorcycle archive pages (first 20 items). Filter: `helmetsan_schema_item_list`.

Use the filters below to inject or extend schema without editing the plugin.

## Filters

- **`helmetsan_schema_product`** — `apply_filters('helmetsan_schema_product', $schema, $postId, $postType)`. Modify Product schema for helmet, accessory, or motorcycle.
- **`helmetsan_schema_website`** — Modify WebSite schema (front page).
- **`helmetsan_schema_organization`** — Modify Organization schema (front page).

## CLI

- **`wp helmetsan seo schema-check --limit=200 --format=table`** — Audit helmet posts for missing required fields (title, permalink, price_retail_usd, rel_brand, featured_image). Does not validate accessory/motorcycle; use Search Console or a validator for full coverage.

## Data flow

Schema is built from **WordPress** post and meta. To get new or updated product data into the site, use ingestion (JSON → WP) or edit in the admin. To persist edits to the repo: export then sync push. See [Data flow](data-flow.md).
