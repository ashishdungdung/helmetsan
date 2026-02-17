<?php
/**
 * Asset loading.
 *
 * @package HelmetsanTheme
 */

if (! defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', 'helmetsan_theme_enqueue_assets');

function helmetsan_theme_enqueue_assets(): void
{
    $baseCss = '/assets/css/base.css';
    $compCss = '/assets/css/components.css';
    $pageCss = '/assets/css/pages.css';

    wp_enqueue_style(
        'helmetsan-theme-base',
        get_template_directory_uri() . $baseCss,
        [],
        helmetsan_theme_asset_version($baseCss)
    );

    wp_enqueue_style(
        'helmetsan-theme-components',
        get_template_directory_uri() . $compCss,
        ['helmetsan-theme-base'],
        helmetsan_theme_asset_version($compCss)
    );

    wp_enqueue_style(
        'helmetsan-theme-pages',
        get_template_directory_uri() . $pageCss,
        ['helmetsan-theme-components'],
        helmetsan_theme_asset_version($pageCss)
    );

    wp_enqueue_script(
        'helmetsan-theme-navigation',
        get_template_directory_uri() . '/assets/js/navigation.js',
        [],
        helmetsan_theme_asset_version('/assets/js/navigation.js'),
        true
    );

    if (is_post_type_archive('helmet') || is_tax()) {
        wp_enqueue_script(
            'helmetsan-theme-filters',
            get_template_directory_uri() . '/assets/js/filters.js',
            [],
            helmetsan_theme_asset_version('/assets/js/filters.js'),
            true
        );
    }
}

function helmetsan_theme_asset_version(string $relativePath): string
{
    $absolutePath = get_template_directory() . $relativePath;

    if (file_exists($absolutePath)) {
        return (string) filemtime($absolutePath);
    }

    return wp_get_theme()->get('Version');
}
