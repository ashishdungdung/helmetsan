<?php
/**
 * Theme hooks.
 *
 * @package HelmetsanTheme
 */

if (! defined('ABSPATH')) {
    exit;
}

add_filter('body_class', 'helmetsan_theme_body_class');

/**
 * @param array<int, string> $classes
 *
 * @return array<int, string>
 */
function helmetsan_theme_body_class(array $classes): array
{
    if (is_singular('helmet')) {
        $classes[] = 'is-helmet-single';
    }

    if (is_post_type_archive('helmet')) {
        $classes[] = 'is-helmet-archive';
    }

    return $classes;
}
