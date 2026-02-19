# Analytics Module

> Frontend tracking (GA4, GTM, Clarity, Hotjar) and custom event logging.

## Files

| File                                                                                                              | Lines | Purpose                                     |
| ----------------------------------------------------------------------------------------------------------------- | ----- | ------------------------------------------- |
| [Tracker.php](file:///Users/anumac/Documents/Helmetsan/helmetsan-core/includes/Analytics/Tracker.php)             | 105   | Script injection for GA4/GTM/Clarity/Hotjar |
| [EventService.php](file:///Users/anumac/Documents/Helmetsan/helmetsan-core/includes/Analytics/EventService.php)   | ~150  | REST API endpoint for custom events         |
| [EventRenderer.php](file:///Users/anumac/Documents/Helmetsan/helmetsan-core/includes/Analytics/EventRenderer.php) | ~80   | Event display in WP Admin                   |

## Tracker

Injects third-party analytics scripts into the frontend:

| Platform           | Hook        | Requires                                        |
| ------------------ | ----------- | ----------------------------------------------- |
| Google Tag Manager | `wp_head`   | `gtm_container_id`                              |
| Google Analytics 4 | `wp_head`   | `ga4_measurement_id`                            |
| Microsoft Clarity  | `wp_footer` | `clarity_project_id` + `enable_heatmap_clarity` |
| Hotjar             | `wp_footer` | `hotjar_site_id` + `enable_heatmap_hotjar`      |

### Smart Deduplication

If GTM container ID is set, GA4 snippet is skipped (GTM handles it). Also respects MonsterInsights — if that plugin is active, Tracker backs off.

## Custom Event Tracking

Frontend JS auto-tracks:

- **Outbound clicks** — captures href + link text
- **Internal search** — captures search queries

Events are sent via `navigator.sendBeacon()` (with `fetch` fallback) to `/wp-json/helmetsan/v1/event`.

## Database Table

`wp_helmetsan_analytics_events` — stores custom events with page URL, referrer, source, and JSON metadata.

## Configuration

Via `Config::analyticsConfig()`:

| Key                                 | Default | Description                     |
| ----------------------------------- | ------- | ------------------------------- |
| `enable_analytics`                  | `false` | Master toggle                   |
| `gtm_container_id`                  | `''`    | GTM container (e.g., GTM-XXXXX) |
| `ga4_measurement_id`                | `''`    | GA4 measurement ID              |
| `enable_heatmap_clarity`            | `false` | Toggle Clarity                  |
| `clarity_project_id`                | `''`    | Clarity project ID              |
| `enable_heatmap_hotjar`             | `false` | Toggle Hotjar                   |
| `hotjar_site_id`                    | `''`    | Hotjar site ID                  |
| `enable_enhanced_event_tracking`    | `false` | Track outbound clicks           |
| `enable_internal_search_tracking`   | `false` | Track search queries            |
| `analytics_respect_monsterinsights` | `true`  | Defer to MonsterInsights        |
