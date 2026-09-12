# Helmetsan Migration Playbook: Provisioning a Clean Environment 🚀

This playbook outlines the exact step-by-step procedure to migrate the Helmetsan stack to a new server from scratch. It is designed to be fully modular, reproducible, and structured to prevent broken dependencies, orphan translation links, or configuration mismatches.

---

## 1. Migration Readiness Checklist

Our stack has been developed with high portability in mind:
*   **Database Schema Provisioning**: Done completely programmatically. The plugin auto-generates all 8 custom tables (`helmetsan_heals`, `helmetsan_product_index`, `helmetsan_reviews`, etc.) during activation via `dbDelta()`. No manual SQL dump imports are required.
*   **API Credentials & Security**: All credentials, Webhook Secrets, Cloudflare zones, R2 buckets, and GitHub integration tokens are environment-override-ready. You can pass them as PHP constants in `wp-config.php` without committing secrets.
*   **Catalog Sync & Ingestion**: De-coupled from server state. All products, specifications, brand directories, and compatibility matrixes are stored as JSON. The system can rebuild the entire catalog from scratch in minutes via WP-CLI ingestion.
*   **Theme Portability**: Built as a standard child theme of GeneratePress. Navigation locations and WooCommerce/Polylang hooks are declared programmatically.

---

## 2. Phase 1: Server OS & Software Provisioning

Ensure the target server (e.g., Ubuntu 22.04 or similar) is updated and has the following core packages installed:

### A. Web Server & PHP Stack
*   **Nginx** or **Apache** (Nginx is standard).
*   **PHP 8.1+** (PHP 8.2 or 8.3 recommended for modern engine optimization).
*   **Required PHP Extensions**:
    *   `php-fpm` (for fast CGI process management)
    *   `php-mysql` / `php-mysqli` (database connector)
    *   `php-curl` (external AI provider / Cloudflare API communications)
    *   `php-json` (parsing JSON seed catalogs and database exports)
    *   `php-mbstring` (multibyte string handling for translated contents)
    *   `php-zip` (import/export packaging utilities)
    *   `php-xml` (Yoast SEO schema generation)
    *   `php-gd` (brand logo image manipulations)
    *   `php-redis` (connecting to the object caching server)

### B. Redis Server
Install Redis to enable memory-based object caching and prevent heavy database lookups:
```bash
sudo apt update
sudo apt install redis-server php-redis -y
sudo systemctl enable redis-server
sudo systemctl start redis-server
```

### C. Developer & Operations CLI
*   **Git**: Required for GitHub sync modules.
*   **SSH & Rsync**: For deploying the codebase and copying datasets.
*   **WP-CLI**: Installed globally as `wp` at `/usr/local/bin/wp`.

---

## 3. Phase 2: WordPress Base Setup

1.  **Install WordPress**: Install a clean WordPress package at the web root (e.g. `/var/www/helmetsan.com/public`).
2.  **Permissions**: Ensure the web server user owns the directories:
    ```bash
    sudo chown -R www-data:www-data /var/www/helmetsan.com/public
    sudo find /var/www/helmetsan.com/public -type d -exec chmod 755 {} \;
    sudo find /var/www/helmetsan.com/public -type f -exec chmod 644 {} \;
    ```
3.  **Setup System-level Cron**:
    Disable default WP-Cron in `wp-config.php`:
    ```php
    define('DISABLE_WP_CRON', true);
    ```
    Add a high-performance system cron entry via `crontab -e` (running as `www-data` or `root` with the appropriate path):
    ```bash
    * * * * * wp cron event run --due-now --path=/var/www/helmetsan.com/public > /dev/null 2>&1
    ```

---

## 4. Phase 3: Configuration Variables (`wp-config.php`)

Add your environment keys and settings to the new server's `wp-config.php` file. This prevents configuration states from breaking when the plugin is activated.

```php
// --- Helmetsan Cloudflare Edge Settings ---
define('HELMETSAN_CLOUDFLARE_ZONE_ID', 'your_zone_id_here');
define('HELMETSAN_CLOUDFLARE_API_TOKEN', 'your_cloudflare_api_token');
define('HELMETSAN_CLOUDFLARE_ACCOUNT_ID', 'your_cloudflare_account_id');
define('HELMETSAN_WEBHOOK_SECRET', 'your_shared_webhook_secret_signature');
define('HELMETSAN_CF_INGEST_QUEUE', 'helmetsan-ingest-queue');
define('HELMETSAN_R2_BUCKET', 'helmetsan-cdn');
define('HELMETSAN_R2_PUBLIC_URL', 'https://cdn.helmetsan.com');

// --- AI Engines (Local & Cloud) ---
define('HELMETSAN_LMSTUDIO_BASE_URL', 'https://ai.helmetsan.com/v1'); // If using Cloudflare Tunnel to Mac
// define('HELMETSAN_LMSTUDIO_BASE_URL', 'http://127.0.0.1:1234/v1'); // If local server LLM
define('HELMETSAN_OPENAI_API_KEY', 'sk-proj-...'); // If premium models are toggled

// --- Media & Brand Assets APIs ---
define('HELMETSAN_BRANDFETCH_TOKEN', 'brandfetch_api_token');
define('HELMETSAN_LOGODEV_PUBLISHABLE_KEY', 'logodev_token');
define('HELMETSAN_LOGODEV_SECRET_KEY', 'logodev_token');

// --- Github Sync Setup ---
define('HELMETSAN_GITHUB_OWNER', 'ashishdungdung');
define('HELMETSAN_GITHUB_REPO', 'helmetsan');
define('HELMETSAN_GITHUB_TOKEN', 'github_personal_access_token');
define('HELMETSAN_GITHUB_BRANCH', 'main');

// --- Security & Bot Safeguards ---
define('HELMETSAN_TURNSTILE_SITE_KEY', 'cloudflare_turnstile_site_key');
define('HELMETSAN_TURNSTILE_SECRET_KEY', 'cloudflare_turnstile_secret_key');

// --- Redis Cache Salt ---
define('WP_CACHE_KEY_SALT', 'helmetsan_prod_');
```

---

## 5. Phase 4: Core Plugin Dependencies

Log in to WP Admin or use WP-CLI to install and activate our dependencies **before** activating the custom themes/plugins:

```bash
# Install GeneratePress (Parent Theme)
wp theme install generatepress --allow-root

# Install required external plugins
wp plugin install woocommerce --activate --allow-root
wp plugin install polylang --activate --allow-root
wp plugin install wordpress-seo --activate --allow-root # Yoast SEO
wp plugin install redis-cache --activate --allow-root
```
*Note: Make sure to configure the Redis Cache plugin by running `wp redis enable --allow-root` once installed.*

---

## 6. Phase 5: Language Initialization (Polylang Setup)

> [!WARNING]
> You **MUST** define languages in Polylang before importing any catalog data. Ingesting content before defining the target translation languages can cause post metadata or translation hooks to be orphan-linked or fail completely.

1.  Navigate to **Languages** in WP Admin.
2.  Add the target languages in order:
    *   **English** (Code: `en`, Default Language)
    *   **German** (Code: `de`)
    *   **Simplified Chinese** (Code: `zh`)
3.  Configure URL routing to use directory names in page URLs (e.g. `https://helmetsan.com/de/`).

---

## 7. Phase 6: Codebase & Database Provisioning

1.  **Deploy code**: Use the local repository deploy helper:
    ```bash
    ./scripts/deploy.sh
    ```
    This syncs the theme (`helmetsan-theme`), plugin (`helmetsan-core`), scripts, and data catalogs to the server web directory.
2.  **Activate Helmetsan Core**:
    ```bash
    wp plugin activate helmetsan-core --allow-root
    ```
    *This runs the bootstrap tables setup automatically. Check that tables `wp_helmetsan_*` are registered in the MySQL database.*
3.  **Activate Theme**:
    ```bash
    wp theme activate helmetsan-theme --allow-root
    ```

---

## 8. Phase 7: Catalog Ingestion & Seeding

1.  **Create upload data path**:
    Ensure the path `/wp-content/uploads/helmetsan-data` is created and writable by the web server:
    ```bash
    mkdir -p /var/www/helmetsan.com/public/wp-content/uploads/helmetsan-data
    chown -R www-data:www-data /var/www/helmetsan.com/public/wp-content/uploads/helmetsan-data
    ```
2.  **Rsync datasets**: Run `scripts/sync_data.sh` to populate the `/uploads/helmetsan-data` directory with the latest JSON files and trigger the ingestion pipeline.
    ```bash
    # Run locally from repo root:
    ./scripts/sync_data.sh
    ```
    This script will automatically trigger path-by-path ingestion:
    *   `wp helmetsan ingest-brands`
    *   Ingests `helmets`, `accessories`, and `motorcycles` CPTs.
3.  **Seeding Accessory Categories**:
    ```bash
    wp --path=/var/www/helmetsan.com/public helmetsan seed-accessory-categories --allow-root
    wp --path=/var/www/helmetsan.com/public helmetsan backfill-accessory-categories --allow-root
    ```
4.  **Provision Hub Pages**:
    Run the template hub setup script:
    ```bash
    wp --path=/var/www/helmetsan.com/public eval-file scripts/setup_hub_pages.php --allow-root
    ```

---

## 9. Phase 8: Content Translation

With the English catalogs fully loaded, translate taxonomy terms and posts:

1.  **Translate Taxonomy Terms**:
    Run `translate_terms.php` to generate German and Chinese taxonomies:
    ```bash
    wp --path=/var/www/helmetsan.com/public eval-file scripts/translate_terms.php helmet_type de --allow-root
    wp --path=/var/www/helmetsan.com/public eval-file scripts/translate_terms.php helmet_type zh --allow-root
    # Repeat for: feature_tag, certification, accessory_category
    ```
2.  **Translate Posts**:
    Use the native CLI translation command:
    ```bash
    wp --path=/var/www/helmetsan.com/public helmetsan translate --lang=de --post-type=helmet --limit=500 --allow-root
    wp --path=/var/www/helmetsan.com/public helmetsan translate --lang=zh --post-type=helmet --limit=500 --allow-root
    # Repeat for brand, accessory, and motorcycle post types as needed
    ```
3.  **Map Translation Menus**:
    Create your target translation menus in the WordPress Admin dashboard (e.g. `helmetsan-primary-de`, `helmetsan-primary-zh`).
    Run the sync script to map locations and set up fallbacks:
    ```bash
    wp --path=/var/www/helmetsan.com/public eval-file scripts/sync-polylang-menus.php --allow-root
    ```

---

## 10. Phase 9: Verification & Handover

Perform final diagnostics to confirm health and connectivity:
```bash
# 1. Run Helmetsan Platform Diagnosis
wp --path=/var/www/helmetsan.com/public helmetsan health --allow-root

# 2. Run Go-Live Gates
wp --path=/var/www/helmetsan.com/public helmetsan go-live checklist --allow-root

# 3. Test Email/Slack Notification Routing
wp --path=/var/www/helmetsan.com/public helmetsan alerts test --title="Server Migration Status" --message="Migration completed successfully." --allow-root
```
*Your new server is now fully configured, seeded, and open for business!*

---

## 11. Language and Localization Context

Helmetsan is configured as a multi-language platform with Polylang acting as the translation registry. Below is the documentation of the implemented languages and how their translation states are linked:

### A. Implemented Languages

| Language Name | Locale Code | URL Slug | Description / Primary Role |
| :--- | :--- | :--- | :--- |
| **English** | `en_US` (`en`) | `/` (Root) | **Master Catalog Source**. All seed data, JSON imports, and base post objects originate in English. |
| **German** | `de_DE` (`de`) | `/de/` | European localized catalog. Terms and descriptions are translated dynamically via AI translators. |
| **Simplified Chinese** | `zh_CN` (`zh`) | `/zh/` | Asian localized catalog. Terms and descriptions are translated dynamically via AI translators. |

### B. Translation Linkage & Fail-safe Fallbacks
To prevent broken layouts or missing data if a translator has not completed a translation:
1.  **Post Hierarchy Alignment**: Every localized post (e.g. in German or Chinese) is associated with its English parent post as the "master translation" using `pll_get_post($id, 'en')`.
2.  **Pricing Fallback**: If a helmet's pricing, reviews, or specifications are requested on a translated page (`/de/` or `/zh/`) but do not exist, the plugin resolves the English parent post ID first to retrieve the active price metadata. This ensures that guest users always see complete specifications and prices.
3.  **Taxonomy Term Mapping**: Taxonomies (`helmet_type`, `feature_tag`, `certification`, `accessory_category`) are linked across languages via Polylang's term translations. This links term counts, faceted sidebar counts, and navigation items.
4.  **Menu Fallbacks**: The sync script automatically maps primary and secondary headers to their localized menus (e.g. `helmetsan-primary-de`), falling back to default English menus if no translation exists.

