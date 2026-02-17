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
add_filter('the_content', 'helmetsan_theme_append_about_attribution');

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

function helmetsan_theme_append_about_attribution(string $content): string
{
    if (! is_page('about') || ! in_the_loop() || ! is_main_query()) {
        return $content;
    }

    $section = '<section class="hs-panel" aria-label="Attribution"><h2>Attribution</h2><p><a href="https://logo.dev">Logos provided by Logo.dev</a></p></section>';

    return $content . $section;
}
