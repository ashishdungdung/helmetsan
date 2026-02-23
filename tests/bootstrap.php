<?php

declare(strict_types=1);

// Minimal bootstrap for unit tests (no WordPress). Define plugin path, stubs, and autoload.
$coreDir = dirname(__DIR__) . '/helmetsan-core/';
if (! defined('HELMETSAN_CORE_DIR')) {
    define('HELMETSAN_CORE_DIR', $coreDir);
}
require_once __DIR__ . '/stubs.php';

spl_autoload_register(static function (string $class): void {
    $prefix = 'Helmetsan\\Core\\';
    if (strpos($class, $prefix) !== 0) {
        return;
    }
    $relative = substr($class, strlen($prefix));
    $path = HELMETSAN_CORE_DIR . 'includes/' . str_replace('\\', '/', $relative) . '.php';
    if (file_exists($path)) {
        require_once $path;
    }
});
