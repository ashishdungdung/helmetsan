# Helmetsan Core Plugin: Development Guidebook & Optimization Reference

This guidebook documents the architectural decisions, code patterns, and performance optimizations used in the **Helmetsan Core** plugin. Use this as the blueprint for developing high-performance WordPress plugins.

---

## 1. Architectural Philosophy

**"The Plugin is the Application"**
We treat WordPress as a framework, not just a CMS. The Core Plugin holds all business logic, while the Theme is purely for presentation.

### Directory Structure & Namespaces

We use a **PSR-4** structure where file paths match namespaces.

```text
/helmetsan-core
  helmetsan-core.php         # Entry point (Singleton access)
  /includes
    /Core                    # Bootstrapping (Plugin.php)
    /Price                   # Domain: Pricing Logic
    /Ingestion               # Domain: Data Import
    /CPT                     # Domain: Post Types
```

**Namespace Pattern**: `Helmetsan\Core\{Domain}\{Class}`

---

## 2. The "Plugin" Class Pattern (Dependency Injection)

Avoid global state. We use a central `Plugin` class as a simple Dependency Injection (DI) container. This allows services to share dependencies (like `GeoService`) without global variables.

**Boilerplate (`includes/Core/Plugin.php`):**

```php
class Plugin {
    // 1. Declare Services
    private PriceService $price;
    private GeoService $geo;

    public function __construct() {
        // 2. Instantiate & Wire Dependencies
        $this->geo = new GeoService();
        $this->price = new PriceService($this->geo); // Inject Geo into Price
    }

    public function boot() {
        // 3. Register Hooks (The "Main" method)
        add_action('init', [$this->price, 'register']);
    }

    // 4. Expose Services (Public Accessors)
    public function price(): PriceService {
        return $this->price;
    }
}
```

**Accessing Services:**
Instead of `global $price_service`, we use:

```php
helmetsan_core()->price()->getBestPrice($id);
```

---

## 3. Performance Optimizations

### A. Transient Caching (The "1-Hour Rule")

For expensive operations (like API calls or complex queries), we use WP Transients.

**Pattern (from `PriceService.php`):**

```php
$cacheKey = 'hs_price_' . $postId . '_' . $countryCode;
$cached = get_transient($cacheKey);

if ($cached !== false) {
    return $cached; // Return Early
}

// ... Perform Expensive API Call ...

set_transient($cacheKey, $result, HOUR_IN_SECONDS);
return $result;
```

### B. Batch Ingestion & Transactions

When importing thousands of items, standard `wp_insert_post` is too slow.

**Optimization 1: Database Transactions**
Wrap batch operations in a transaction to reduce disk I/O commit overhead.

```php
global $wpdb;
$wpdb->query('START TRANSACTION');
try {
    // ... insert 50 posts ...
    $wpdb->query('COMMIT');
} catch (Throwable $e) {
    $wpdb->query('ROLLBACK');
}
```

**Optimization 2: Disable Deferral**
During ingestion, pause deferred term counting to speed up inserts.

```php
wp_defer_term_counting(true);
// ... insert terms ...
wp_defer_term_counting(false);
```

### C. Locking (Race Condition Prevention)

Prevent multiple ingestion processes from running simultaneously.

```php
if (get_transient('hs_ingestion_lock')) {
    return; // Already running
}
set_transient('hs_ingestion_lock', true, 60 * 10); // 10 min lock
// ... process ...
delete_transient('hs_ingestion_lock');
```

---

## 4. Server & Environment Config

### A. SSH & Server Access Template

_Keep this handy for your new server provisioning._

| Service      | Setting             | Recommended Value                  |
| :----------- | :------------------ | :--------------------------------- |
| **SSH User** | `root` / `deploy`   | Create a dedicated deploy user     |
| **SSH Key**  | `~/.ssh/id_rsa.pub` | Add to `~/.ssh/authorized_keys`    |
| **Firewall** | UFW                 | Allow ports `22`, `80`, `443` ONLY |
| **DB Host**  | `localhost`         | Restrict access to `127.0.0.1`     |

### B. System Cron (Crucial)

Default WP Cron checks for updates on _every page load_. Kill it.

1.  **Disable in `wp-config.php`**:
    ```php
    define('DISABLE_WP_CRON', true);
    ```
2.  **Add System Cron (`crontab -e`):**
    ```bash
    * * * * * wp cron event run --due-now --path=/var/www/html > /dev/null 2>&1
    ```

### C. Redis Object Cache

Install Redis to cache weird WordPress queries (Options, Meta) in memory.

1.  **Install**: `apt install redis-server php-redis`
2.  **WP Plugin**: Install `Redis Object Cache`.
3.  **Config**: Add key salt if multi-tenant.
    ```php
    define('WP_CACHE_KEY_SALT', 'my_project_');
    ```

---

## 5. Development Checklist

1.  [ ] **Strict Types**: Start every file with `declare(strict_types=1);`.
2.  [ ] **WP-CLI**: Build commands (`includes/Commands.php`) for maintenance tasks, not admin pages.
3.  [ ] **Logging**: Don't use `error_log()`. Use a `Logger` service that writes to a specific daily file.
4.  [ ] **Idempotency**: Ensure your ingestion scripts can run 100 times without creating duplicate data (use unique meta keys as IDs).

---

**Reference Files:**

- **Architecture**: `includes/Core/Plugin.php`
- **Caching**: `includes/Price/PriceService.php`
- **Ingestion**: `includes/Ingestion/IngestionService.php`
