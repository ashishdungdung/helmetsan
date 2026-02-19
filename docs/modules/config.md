# Config Module

> Centralized settings management with environment variable overrides.

## Files

| File                                                                                              | Lines | Purpose                  |
| ------------------------------------------------------------------------------------------------- | ----- | ------------------------ |
| [Config.php](file:///Users/anumac/Documents/Helmetsan/helmetsan-core/includes/Support/Config.php) | ~240  | All plugin configuration |

## How It Works

Every configuration domain follows the same pattern:

```php
public function exampleConfig(): array {
    $defaults = self::exampleDefaults();
    $saved = get_option('helmetsan_example', []);
    $merged = wp_parse_args($saved, $defaults);

    // Override sensitive keys from env vars
    if (defined('HELMETSAN_EXAMPLE_TOKEN')) {
        $merged['token'] = HELMETSAN_EXAMPLE_TOKEN;
    }

    return $merged;
}
```

This allows:

1. **Defaults** — Sensible fallbacks baked into the code
2. **WP Options** — Admin-configurable via the dashboard
3. **Environment/Constants** — Secure overrides for sensitive keys

## Configuration Domains

| Method                | WP Option Key           | Scope                          |
| --------------------- | ----------------------- | ------------------------------ |
| `analyticsConfig()`   | `helmetsan_analytics`   | GA4, GTM, Clarity, Hotjar      |
| `revenueConfig()`     | `helmetsan_revenue`     | Affiliate tracking, Amazon tag |
| `alertsConfig()`      | `helmetsan_alerts`      | Email + Slack notifications    |
| `githubConfig()`      | `helmetsan_github`      | GitHub sync credentials        |
| `mediaConfig()`       | `helmetsan_media`       | Brandfetch, Logo.dev tokens    |
| `schedulerConfig()`   | `helmetsan_scheduler`   | Cron intervals                 |
| `wooBridgeConfig()`   | `helmetsan_woobridge`   | WooCommerce sync               |
| `marketplaceConfig()` | `helmetsan_marketplace` | API credentials per connector  |

## Environment Variable Overrides

| Constant                         | Config Domain |
| -------------------------------- | ------------- |
| `HELMETSAN_ALERTS_TO_EMAIL`      | Alerts        |
| `HELMETSAN_ALERTS_SLACK_WEBHOOK` | Alerts        |
| `HELMETSAN_GITHUB_OWNER`         | GitHub        |
| `HELMETSAN_GITHUB_REPO`          | GitHub        |
| `HELMETSAN_GITHUB_TOKEN`         | GitHub        |
| `HELMETSAN_GITHUB_BRANCH`        | GitHub        |
| `HELMETSAN_BRANDFETCH_TOKEN`     | Media         |
| `HELMETSAN_LOGODEV_TOKEN`        | Media         |
| `HELMETSAN_AMZ_CLIENT_ID`        | Marketplace   |
| `HELMETSAN_AMZ_CLIENT_SECRET`    | Marketplace   |
| `HELMETSAN_ALLEGRO_CLIENT_ID`    | Marketplace   |
| `HELMETSAN_JUMIA_API_KEY`        | Marketplace   |

## Usage in Other Modules

Every service receives `Config` via constructor injection:

```php
final class RevenueService {
    public function __construct(private readonly Config $config) {}

    public function handleRedirect(): void {
        $settings = $this->config->revenueConfig();
        // ...
    }
}
```
