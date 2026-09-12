# Helmetsan Core Integrations & Server Migration Guide

This guide documents the architecture, settings, and migration workflows for all core theme and plugin integrations inside Helmetsan.

---

## 1. Feature Toggles & Controls

To support rapid server migrations and custom configurations, all major integrations can be toggled on/off in the WordPress admin panel under **Helmetsan → Settings → Features & Toggles**.

### Toggleable Integrations

| Feature Key | Display Name | Architecture Description |
| :--- | :--- | :--- |
| `enable_ajax_catalog_filters` | AJAX Catalog Filters | Enables debounced (400ms), dynamic catalog results loading without full-page reloads. |
| `enable_comparison_engine` | Comparison Engine | Enqueues comparison tray drawer scripts/styles, premium toasts, and triggers GA4 comparison telemetry. |
| `enable_geo_pricing_fallback` | Edge Geo-Pricing Bypass | Triggers client-side JSON API fetch fallback to geolocate visitor region/currency when FastCGI static cache strips PHP headers. |
| `enable_real_user_web_vitals` | Real-User Web Vitals | Instruments `PerformanceObserver` client-side to report LCP, CLS, and FID metrics to GA4. |
| `enable_adblock_beacon` | Ad-Blocker Ingestion Beacon | Fallback ping system sending an `adblock_detected` REST API event if Google Analytics code triggers are blocked. |
| `enable_ga4_trending_badges` | GA4 Popularity Badges | Displays dynamic orange `TRENDING` badges on catalog cards and view counters on Single PDP concepts. |

---

## 2. Telemetry & Analytics Architecture

### Outbound Affiliate Click Ingestion (`generate_lead`)
When a visitor clicks on Amazon, RevZilla, or Jumia buy buttons:
1. Event is intercepted via script delegation in `tracker.js`.
2. Telemetry reports `generate_lead` directly to GA4.
3. If Google Analytics is blocked, a secure REST API beacon falls back to `/wp-json/helmetsan/v1/event` with nonce verification, logging the block locally under `adblock_detected`.

### Page Views Cache Syncing Engine
Instead of hitting Google APIs on client load:
1. A daily background cron task (`helmetsan_cron_sync_page_views`) queries GA4's reporting API.
2. The task checks and programmatically ensures custom dimensions (`href`, `text`) exist on the GA4 property.
3. Page view metrics are cached inside the postmeta key `_hs_ga_views_30d` for each helmet post, enabling high-performance frontend badges.

---

## 3. Server Migration & Integrity

When migrating Helmetsan between hosting environments or spinning up a staging mirror:

### A. Settings Backup & Import (The Configuration)
1. Go to **Helmetsan → Settings → Server Migration**.
2. Click **Export Settings JSON** to download a complete `.json` dump containing all API keys, webhook secrets, Cloudflare details, and feature toggles.
3. On the new target server, upload this `.json` backup file in the import panel.
4. Settings are automatically synchronized and restored instantly.

### B. Catalog Reseeding & Sync (The Content)
Helmetsan catalog content is stored inside the Git-based repository (`data/` directories).
1. Deploy the theme and plugin codes to the new server.
2. In the WordPress admin, navigate to **Helmetsan → Data Operations**.
3. Trigger **Sync Pull** to fetch all JSON files from the GitHub repository into `wp-content/uploads/helmetsan-data/`.
4. Trigger **Ingest Seed** to parse all JSON structures and rebuild the WordPress custom posts database.
