<?php
/**
 * Plugin Name: Helmetsan Translate
 * Plugin URI:  https://helmetsan.com/
 * Description: Enterprise continuous automated translation infrastructure for Helmetsan. Orchestrates a 10-tier Multi-AI fleet (Mercury-3, Gemini Flash Lite, GPT-6-Luna, Cerebras, Groq, Mistral), Redis L1 translation memory, Cloudflare D1 edge lookups, atomic Polylang clustering, Yoast SEO sync, and zero-downtime background queues.
 * Version:     1.0.0
 * Author:      Helmetsan Engineering
 * Author URI:  https://helmetsan.com/
 * Text Domain: helmetsan-translate
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 8.1
 */

declare(strict_types=1);

defined('ABSPATH') || exit;

define('HELMETSAN_TRANSLATE_VERSION', '1.0.0');
define('HELMETSAN_TRANSLATE_DIR', plugin_dir_path(__FILE__));
define('HELMETSAN_TRANSLATE_URL', plugin_dir_url(__FILE__));
define('HELMETSAN_TRANSLATE_QUEUE_TABLE', 'helmetsan_translate_queue');
define('HELMETSAN_TRANSLATE_LOG_TABLE', 'helmetsan_translate_log');

// Include components
require_once HELMETSAN_TRANSLATE_DIR . 'includes/class-encryption.php';
require_once HELMETSAN_TRANSLATE_DIR . 'includes/class-queue-manager.php';
require_once HELMETSAN_TRANSLATE_DIR . 'includes/class-settings.php';
require_once HELMETSAN_TRANSLATE_DIR . 'includes/class-taxonomy-mapper.php';
require_once HELMETSAN_TRANSLATE_DIR . 'includes/class-taxonomy-repair.php';
require_once HELMETSAN_TRANSLATE_DIR . 'includes/class-spec-body-localizer.php';
require_once HELMETSAN_TRANSLATE_DIR . 'includes/class-content-normalizer.php';
require_once HELMETSAN_TRANSLATE_DIR . 'includes/class-seo-integrator.php';
require_once HELMETSAN_TRANSLATE_DIR . 'includes/class-cache-purger.php';
require_once HELMETSAN_TRANSLATE_DIR . 'includes/class-save-post-hook.php';

if (defined('WP_CLI') && WP_CLI) {
    require_once HELMETSAN_TRANSLATE_DIR . 'includes/class-cli-commands.php';
}

/**
 * Database installer on plugin activation
 */
register_activation_hook(__FILE__, function(): void {
    global $wpdb;
    require_once ABSPATH . 'wp-admin/includes/upgrade.php';
    $charset_collate = $wpdb->get_charset_collate();

    // 1. Queue Table with per-language completion tracking
    $queue_table = $wpdb->prefix . HELMETSAN_TRANSLATE_QUEUE_TABLE;
    $sql_queue = "CREATE TABLE {$queue_table} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        post_id BIGINT(20) UNSIGNED NOT NULL,
        post_type VARCHAR(50) NOT NULL,
        content_hash VARCHAR(32) NOT NULL,
        status VARCHAR(20) NOT NULL DEFAULT 'pending',
        attempts INT(5) NOT NULL DEFAULT 0,
        languages_state LONGTEXT NOT NULL,
        last_error TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        UNIQUE KEY post_hash (post_id, content_hash),
        KEY status (status),
        KEY post_type (post_type)
    ) {$charset_collate};";
    dbDelta($sql_queue);

    // 2. Rolling Log Table with 7-day retention
    $log_table = $wpdb->prefix . HELMETSAN_TRANSLATE_LOG_TABLE;
    $sql_log = "CREATE TABLE {$log_table} (
        id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        queue_id BIGINT(20) UNSIGNED NULL,
        post_id BIGINT(20) UNSIGNED NOT NULL,
        target_lang VARCHAR(10) NOT NULL,
        provider VARCHAR(50) NOT NULL,
        latency_ms INT(10) NOT NULL DEFAULT 0,
        status VARCHAR(20) NOT NULL,
        message TEXT NULL,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY post_lang (post_id, target_lang),
        KEY created_at (created_at)
    ) {$charset_collate};";
    dbDelta($sql_log);

    // Initialize default settings if not existing
    HelmetsanTranslate\Settings::init_defaults();
});

/**
 * Bootstrap the plugin services
 */
add_action('plugins_loaded', function(): void {
    HelmetsanTranslate\Settings::instance();
    HelmetsanTranslate\SavePostHook::instance();
    HelmetsanTranslate\QueueManager::instance();
});
