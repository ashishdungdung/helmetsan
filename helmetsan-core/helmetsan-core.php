<?php
/**
 * Plugin Name: Helmetsan Core
 * Plugin URI: https://helmetsan.com
 * Description: ERP-style control plane for data, ingestion, analytics, and publishing workflows.
 * Version: 0.3.0
 * Requires at least: 6.4
 * Requires PHP: 8.3
 * Author: Helmetsan
 * License: GPLv3 or later
 * License URI: http://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain: helmetsan-core
 */

declare(strict_types=1);

if (! defined('ABSPATH')) {
    exit;
}

define('HELMETSAN_CORE_VERSION', '0.3.0');
define('HELMETSAN_CORE_FILE', __FILE__);
define('HELMETSAN_CORE_DIR', plugin_dir_path(__FILE__));
define('HELMETSAN_CORE_URL', plugin_dir_url(__FILE__));

require_once HELMETSAN_CORE_DIR . 'stubs/helmetsan-constants.php';
if (file_exists(HELMETSAN_CORE_DIR . 'includes/CompatibilityEngine.php')) {
    require_once HELMETSAN_CORE_DIR . 'includes/CompatibilityEngine.php';
}

// Load Composer autoloader if it exists
if (file_exists(HELMETSAN_CORE_DIR . '../vendor/autoload.php')) {
    require_once HELMETSAN_CORE_DIR . '../vendor/autoload.php';
} elseif (file_exists(HELMETSAN_CORE_DIR . '../../vendor/autoload.php')) {
    require_once HELMETSAN_CORE_DIR . '../../vendor/autoload.php';
} elseif (defined('ABSPATH') && file_exists(ABSPATH . 'vendor/autoload.php')) {
    require_once ABSPATH . 'vendor/autoload.php';
}


spl_autoload_register(static function (string $class): void {
    $prefix = 'Helmetsan\\Core\\';

    if (strpos($class, $prefix) !== 0) {
        return;
    }

    $relative = substr($class, strlen($prefix));
    $path     = HELMETSAN_CORE_DIR . 'includes/' . str_replace('\\', '/', $relative) . '.php';

    if (file_exists($path)) {
        require_once $path;
    }
});

function helmetsan_core(): Helmetsan\Core\Core\Plugin
{
    static $instance = null;

    if ($instance === null) {
        $instance = new Helmetsan\Core\Core\Plugin();
    }

    return $instance;
}

function helmetsan_is_china_visitor(): bool
{
    // 1. Check Cloudflare physical IP location header (real-time)
    $cfHeader = $_SERVER['HTTP_CF_IPCOUNTRY'] ?? '';
    if (strcasecmp(trim((string) $cfHeader), 'CN') === 0) {
        return true;
    }

    // 2. Check the raw geo cookie
    $cookieVal = $_COOKIE['helmetsan_geo'] ?? '';
    if (strcasecmp(trim((string) $cookieVal), 'CN') === 0) {
        return true;
    }

    return false;
}

add_action('plugins_loaded', static function (): void {
    helmetsan_core()->boot();
});

register_activation_hook(__FILE__, static function (): void {
    helmetsan_core()->activate();
});

register_deactivation_hook(__FILE__, static function (): void {
    helmetsan_core()->deactivate();
});
