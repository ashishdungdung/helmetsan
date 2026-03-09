# Helmetsan — Architecture Overview

> The global motorcycle helmet comparison and affiliate platform.

## System Diagram

```mermaid
graph TB
    subgraph External
        GH["GitHub Repository<br/>(JSON data)"]
        R2["Cloudflare R2<br/>(Media CDN)"]
        AMZ["Amazon SP-API"]
        ALG["Allegro API"]
        JUM["Jumia API"]
        FEEDS["Affiliate Feeds<br/>(RevZilla/Cycle Gear/FC-Moto)"]
    end

    subgraph "helmetsan-core (WordPress Plugin)"
        SYNC["SyncService"] --> |"pull/push"| GH
        ING["IngestionService"] --> |"upsert helmets"| WP["WordPress CPTs"]
        COM["CommerceService"] --> |"pricing/offers"| WP
        MKT["ConnectorRegistry"] --> AMZ
        MKT --> ALG
        MKT --> JUM
        MKT --> FEEDS
        GEO["GeoService"] --> ROUTER["MarketplaceRouter"]
        ROUTER --> MKT
        REV["RevenueService"] --> |"click tracking"| DB["wp_helmetsan_clicks"]
        PH["PriceHistory"] --> |"snapshots"| DB2["wp_helmetsan_price_history"]
        SCHED["SchedulerService"] --> SYNC
        SCHED --> ING
        ALERT["AlertService"] --> |"email/slack"| EXT2["Notifications"]
        MEDIA["MediaEngine"] --> R2
    end

    subgraph "helmetsan-theme (WordPress Theme)"
        PDP["Product Display Page"]
        SEARCH["Search & Filters"]
        BRAND["Brand Hub"]
    end

    WP --> PDP
    ROUTER --> PDP
```

## Module Catalogue

Modules are grouped by domain. Each has a detailed doc in [`docs/modules/`](file:///Users/anumac/Documents/Helmetsan/docs/modules/).

### 🔄 Data Pipeline

| Module                                                                            | Service            | Purpose                                                                 |
| --------------------------------------------------------------------------------- | ------------------ | ----------------------------------------------------------------------- |
| [Data flow](data-flow.md)                                                          | —                  | **Concept:** JSON ↔ WordPress ↔ GitHub; ingestion, export, sync pull/push |
| [Sync](modules/sync.md)                                                            | `SyncService`      | **Pull** = download from GitHub then apply; **Push** = upload local JSON to GitHub |
| [Ingestion](modules/ingestion.md)                                                 | `IngestionService` | **Read** JSON from disk → WordPress only (does not write to GitHub)    |
| [Repository](modules/data-layer.md)                                               | `JsonRepository`   | Local JSON file management            |
| [Validation](modules/data-layer.md)                                                | `Validator`        | Schema validation & integrity checks  |

### 🛒 Commerce & Marketplace

| Module                                                                              | Service                         | Purpose                             |
| ----------------------------------------------------------------------------------- | ------------------------------- | ----------------------------------- |
| [Commerce](file:///Users/anumac/Documents/Helmetsan/docs/modules/commerce.md)       | `CommerceService`               | Pricing, offers, marketplace data   |
| [Marketplace](file:///Users/anumac/Documents/Helmetsan/docs/modules/marketplace.md) | `ConnectorRegistry`             | Multi-marketplace price engine      |
| [Price](file:///Users/anumac/Documents/Helmetsan/docs/modules/price.md)             | `PriceService` + `PriceHistory` | Currency formatting, price tracking |
| [Revenue](file:///Users/anumac/Documents/Helmetsan/docs/modules/revenue.md)         | `RevenueService`                | Affiliate link tracking & reporting |

### 🌍 Geo & Routing

| Module                                                              | Service             | Purpose                       |
| ------------------------------------------------------------------- | ------------------- | ----------------------------- |
| [Geo](file:///Users/anumac/Documents/Helmetsan/docs/modules/geo.md) | `GeoService`        | Visitor country detection     |
| Marketplace Router                                                  | `MarketplaceRouter` | Country → marketplace routing |

### 🖼️ Media

| Module                                                                  | Service                        | Purpose                           |
| ----------------------------------------------------------------------- | ------------------------------ | --------------------------------- |
| [Media](file:///Users/anumac/Documents/Helmetsan/docs/modules/media.md) | `MediaEngine` + `MediaService` | Logo management, galleries, video |

### 📊 Analytics & Monitoring

| Module                                                                          | Service                    | Purpose                                |
| ------------------------------------------------------------------------------- | -------------------------- | -------------------------------------- |
| [Analytics](file:///Users/anumac/Documents/Helmetsan/docs/modules/analytics.md) | `Tracker` + `EventService` | GA4/GTM/Clarity/Hotjar + custom events |
| [Health](file:///Users/anumac/Documents/Helmetsan/docs/modules/health.md)       | `HealthService`            | System health dashboard                |
| [Alerts](file:///Users/anumac/Documents/Helmetsan/docs/modules/alerts.md)       | `AlertService`             | Email + Slack notifications            |

### ⚙️ Infrastructure

| Module                                                                          | Service            | Purpose                              |
| ------------------------------------------------------------------------------- | ------------------ | ------------------------------------ |
| [Scheduler](file:///Users/anumac/Documents/Helmetsan/docs/modules/scheduler.md) | `SchedulerService` | WP-Cron job orchestration            |
| [Config](file:///Users/anumac/Documents/Helmetsan/docs/modules/config.md)       | `Config`           | Centralized settings + env overrides |
| [CLI](file:///Users/anumac/Documents/Helmetsan/docs/modules/cli.md)             | `Commands`         | WP-CLI command registration          |
| [Admin](file:///Users/anumac/Documents/Helmetsan/docs/modules/admin.md)         | `Admin`            | WP Admin dashboard pages             |

### 📦 Content Types

| Module                                                                                     | Service                       | Purpose                   |
| ------------------------------------------------------------------------------------------ | ----------------------------- | ------------------------- |
| [CPT](file:///Users/anumac/Documents/Helmetsan/docs/modules/cpt.md)                        | `Registrar` + `MetaRegistrar` | Custom post types & meta  |
| [Brands](file:///Users/anumac/Documents/Helmetsan/docs/modules/content-types.md)           | `BrandService`                | Brand CPT management      |
| [Helmets](file:///Users/anumac/Documents/Helmetsan/docs/modules/content-types.md)          | `HelmetService`               | Helmet CPT management     |
| [Accessories](file:///Users/anumac/Documents/Helmetsan/docs/modules/content-types.md)      | `AccessoryService`            | Accessory CPT management  |
| [Motorcycles](file:///Users/anumac/Documents/Helmetsan/docs/modules/content-types.md)      | `MotorcycleService`           | Motorcycle CPT management |
| [Safety Standards](file:///Users/anumac/Documents/Helmetsan/docs/modules/content-types.md) | `SafetyStandardService`       | ECE/DOT/Snell standards   |
| [Dealers](file:///Users/anumac/Documents/Helmetsan/docs/modules/content-types.md)          | `DealerService`               | Dealer directory          |
| [Distributors](file:///Users/anumac/Documents/Helmetsan/docs/modules/content-types.md)     | `DistributorService`          | Distributor directory     |

### 🔗 Integrations

| Module                                                                          | Service            | Purpose                              |
| ------------------------------------------------------------------------------- | ------------------ | ------------------------------------ |
| [WooBridge](file:///Users/anumac/Documents/Helmetsan/docs/modules/woobridge.md) | `WooBridgeService` | Helmet → WooCommerce product sync    |
| [Search](file:///Users/anumac/Documents/Helmetsan/docs/modules/search.md)       | `SearchService`    | Enhanced search with meta + taxonomy |

## Data Flow

- **Full concept and structure:** [Data flow: JSON ↔ WordPress ↔ GitHub](data-flow.md) — ingestion (read-only from JSON), export (WP → JSON), sync pull (GitHub → local then apply), sync push (local JSON → GitHub).

```mermaid
sequenceDiagram
    participant Cron as SchedulerService
    participant Sync as SyncService
    participant GH as GitHub
    participant Ing as IngestionService
    participant WP as WordPress CPTs
    participant Mkt as ConnectorRegistry
    participant APIs as External APIs
    participant PDP as Product Page

    Cron->>Sync: runSyncPull()
    Sync->>GH: Fetch JSON files
    GH-->>Sync: JSON data
    Sync->>Ing: apply (ingest downloaded files)
    Ing->>WP: upsert helmets/brands

    Note over PDP: Visitor arrives
    PDP->>Mkt: bestPriceForCountry(helmetRef, "US")
    Mkt->>APIs: fanOut queries
    APIs-->>Mkt: PriceResult[]
    Mkt-->>PDP: Best price + offers
```

## Plugin Bootstrap

All services are initialized in [`Plugin.php`](file:///Users/anumac/Documents/Helmetsan/helmetsan-core/includes/Core/Plugin.php):

1. **Constructor** — Creates all service instances with dependency injection
2. **`boot()`** — Registers WordPress hooks for each service
3. **`activate()`** — Creates custom DB tables, schedules cron jobs
4. **`deactivate()`** — Clears cron schedules

## Key Design Principles

- **JSON-first data** — Helmet data lives as JSON (in repo or exported from WP). Ingestion reads JSON → WordPress only; sync pull brings GitHub → local, sync push uploads local JSON → GitHub. See [Data flow](data-flow.md).
- **Pluggable connectors** — Marketplace integrations via `MarketplaceConnectorInterface`
- **Geo-aware pricing** — Visitors see prices from their country's marketplaces
- **Error isolation** — One marketplace failure doesn't break the whole price display
- **Config-driven** — All features toggleable via `wp_options` with env-var overrides
