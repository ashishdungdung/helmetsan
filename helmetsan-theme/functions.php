<?php
/**
 * Theme bootstrap.
 *
 * @package HelmetsanTheme
 */

if (! defined('ABSPATH')) {
    exit;
}

$helmetsan_theme_includes = [
    '/inc/setup.php',
    '/inc/enqueue.php',
    '/inc/hooks.php',
    '/inc/template-tags.php',
    '/inc/compatibility.php',
];

foreach ($helmetsan_theme_includes as $file) {
    $path = get_template_directory() . $file;
    if (file_exists($path)) {
        require_once $path;
    }
}
