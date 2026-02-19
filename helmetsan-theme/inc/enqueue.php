<?php
/**
 * Asset loading.
 *
 * @package HelmetsanTheme
 */

if (! defined('ABSPATH')) {
    exit;
}

add_action('wp_enqueue_scripts', 'helmetsan_theme_enqueue_assets', 20);

function helmetsan_theme_enqueue_assets(): void
{
    if (wp_get_theme()->parent() && wp_get_theme()->parent()->get('TextDomain') === 'generatepress') {
        wp_enqueue_style('generate-style', get_template_directory_uri() . '/style.css', [], wp_get_theme(get_template())->get('Version'));
    }

    $baseCss = '/assets/css/base.css';
    $compCss = '/assets/css/components.css';
    $pageCss = '/assets/css/pages.css';

    wp_enqueue_style(
        'helmetsan-theme-base',
        get_stylesheet_directory_uri() . $baseCss,
        ['generate-style'],
        helmetsan_theme_asset_version($baseCss)
    );

    wp_enqueue_style(
        'helmetsan-theme-components',
        get_stylesheet_directory_uri() . $compCss,
        ['helmetsan-theme-base'],
        helmetsan_theme_asset_version($compCss)
    );

    wp_enqueue_style(
        'helmetsan-theme-pages',
        get_stylesheet_directory_uri() . $pageCss,
        ['helmetsan-theme-components'],
        helmetsan_theme_asset_version($pageCss)
    );

    wp_enqueue_style(
        'helmetsan-mega-menu',
        get_stylesheet_directory_uri() . '/assets/css/mega-menu.css',
        ['helmetsan-theme-components'],
        helmetsan_theme_asset_version('/assets/css/mega-menu.css')
    );

    wp_enqueue_script(
        'helmetsan-theme-navigation',
        get_stylesheet_directory_uri() . '/assets/js/navigation.js',
        [],
        helmetsan_theme_asset_version('/assets/js/navigation.js'),
        true
    );

    wp_enqueue_style(
        'helmetsan-comparison',
        get_stylesheet_directory_uri() . '/assets/css/comparison.css',
        ['helmetsan-theme-components'],
        helmetsan_theme_asset_version('/assets/css/comparison.css')
    );

    wp_enqueue_script(
        'helmetsan-comparison',
        get_stylesheet_directory_uri() . '/assets/js/comparison.js',
        [],
        helmetsan_theme_asset_version('/assets/js/comparison.js'),
        true
    );

    if (is_post_type_archive('helmet') || is_post_type_archive('brand') || is_tax()) {
        wp_enqueue_script(
            'helmetsan-theme-filters',
            get_stylesheet_directory_uri() . '/assets/js/filters.js',
            [],
            helmetsan_theme_asset_version('/assets/js/filters.js'),
            true
        );
        wp_localize_script('helmetsan-theme-filters', 'helmetsan_ajax', [
            'url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('helmetsan_filter_nonce'),
        ]);
    }

    if (class_exists('WooCommerce') && function_exists('is_woocommerce') && (is_woocommerce() || is_cart() || is_checkout() || is_account_page())) {
        $wooCss = '/assets/css/woocommerce.css';
        wp_enqueue_style(
            'helmetsan-theme-woocommerce',
            get_stylesheet_directory_uri() . $wooCss,
            ['helmetsan-theme-components'],
            helmetsan_theme_asset_version($wooCss)
        );

        wp_enqueue_script(
            'helmetsan-theme-woo-mobile',
            get_stylesheet_directory_uri() . '/assets/js/woo-mobile.js',
            [],
            helmetsan_theme_asset_version('/assets/js/woo-mobile.js'),
            true
        );
    }
}

function helmetsan_theme_asset_version(string $relativePath): string
{
    $absolutePath = get_stylesheet_directory() . $relativePath;

    if (file_exists($absolutePath)) {
        return (string) filemtime($absolutePath);
    }

    return wp_get_theme()->get('Version');
}
