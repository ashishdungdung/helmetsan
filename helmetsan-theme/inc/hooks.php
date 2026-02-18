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
add_action('wp_head', 'helmetsan_theme_dynamic_layout_css', 30);

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

    $layout = (string) get_theme_mod('helmetsan_layout_alignment', 'balanced');
    $classes[] = 'hs-layout-' . sanitize_html_class($layout);

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

function helmetsan_theme_dynamic_layout_css(): void
{
    $width = absint((string) get_theme_mod('helmetsan_content_max_width', 1200));
    if ($width < 980) {
        $width = 980;
    }
    if ($width > 1600) {
        $width = 1600;
    }
    echo '<style id=\"hs-layout-width\">body{--hs-content-max:' . esc_attr((string) $width) . 'px;}</style>';
}
