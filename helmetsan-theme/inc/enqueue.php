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
add_filter('wp_resource_hints', 'helmetsan_theme_resource_hints', 10, 2);

/**
 * Add resource hints for performance.
 *
 * @param array<int, string> $hints
 * @param string             $relation_type
 *
 * @return array<int, string>
 */
function helmetsan_theme_resource_hints(array $hints, string $relation_type): array
{
    if ($relation_type === 'preconnect' || $relation_type === 'dns-prefetch') {
        if (function_exists('helmetsan_is_china_visitor') && helmetsan_is_china_visitor()) {
            $hints[] = 'https://fonts.loli.net';
            $hints[] = 'https://cdn.bootcdn.net';
        } else {
            $hints[] = 'https://fonts.googleapis.com';
            $hints[] = 'https://fonts.gstatic.com';
        }
        $hints[] = 'https://img.logo.dev';
        $hints[] = 'https://www.amazon.com';
        $hints[] = 'https://m.media-amazon.com';
    }

    return $hints;
}

function helmetsan_theme_enqueue_assets(): void
{
    if (wp_get_theme()->parent() && wp_get_theme()->parent()->get('TextDomain') === 'generatepress') {
        wp_enqueue_style('generate-style', get_template_directory_uri() . '/style.css', [], wp_get_theme(get_template())->get('Version'));
    }

    // High-performance unified bundle (collapses 5 HTTP requests into 1 minified bundle)
    $bundleCss = '/assets/css/helmetsan-bundle.min.css';
    $useBundle = file_exists(get_stylesheet_directory() . $bundleCss) && !(defined('SCRIPT_DEBUG') && SCRIPT_DEBUG);

    if ($useBundle) {
        wp_enqueue_style(
            'helmetsan-bundle',
            get_stylesheet_directory_uri() . $bundleCss,
            ['generate-style'],
            helmetsan_theme_asset_version($bundleCss)
        );
    } else {
        wp_enqueue_style(
            'helmetsan-design-tokens',
            get_stylesheet_directory_uri() . '/assets/css/design-tokens.css',
            [],
            helmetsan_theme_asset_version('/assets/css/design-tokens.css')
        );

        $baseCss = '/assets/css/base.css';
        $compCss = '/assets/css/components.css';
        $pageCss = '/assets/css/pages.css';

        wp_enqueue_style(
            'helmetsan-theme-base',
            get_stylesheet_directory_uri() . $baseCss,
            ['generate-style', 'helmetsan-design-tokens'],
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
    }

    $fontUrl = 'https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap';
    if (function_exists('helmetsan_is_china_visitor') && helmetsan_is_china_visitor()) {
        $fontUrl = 'https://fonts.loli.net/css2?family=Inter:wght@400;500;600;700&display=swap';
    }

    wp_enqueue_style(
        'helmetsan-fonts',
        $fontUrl,
        ['helmetsan-design-tokens'],
        null
    );

    wp_enqueue_script(
        'helmetsan-theme-toggle',
        get_stylesheet_directory_uri() . '/assets/js/theme-toggle.js',
        [],
        helmetsan_theme_asset_version('/assets/js/theme-toggle.js'),
        false
    );

    wp_enqueue_script(
        'helmetsan-theme-navigation',
        get_stylesheet_directory_uri() . '/assets/js/navigation.js',
        [],
        helmetsan_theme_asset_version('/assets/js/navigation.js'),
        ['strategy' => 'defer', 'in_footer' => true]
    );

    $features = wp_parse_args((array) get_option('helmetsan_features', []), [
        'enable_ajax_catalog_filters' => true,
        'enable_comparison_engine' => true,
        'enable_geo_pricing_fallback' => true,
        'enable_real_user_web_vitals' => true,
        'enable_adblock_beacon' => true,
        'enable_ga4_trending_badges' => true,
    ]);

    wp_enqueue_script(
        'helmetsan-currency-selector',
        get_stylesheet_directory_uri() . '/assets/js/currency-selector.js',
        [],
        helmetsan_theme_asset_version('/assets/js/currency-selector.js'),
        ['strategy' => 'defer', 'in_footer' => true]
    );

    $revConfig = [];
    if (class_exists('\Helmetsan\Core\Support\Config')) {
        $coreConfig = new \Helmetsan\Core\Support\Config();
        $revConfig = $coreConfig->revenueConfig();
    } else {
        $savedRev = get_option('helmetsan_revenue', []);
        $revConfig = is_array($savedRev) ? $savedRev : [];
    }

    $amazonTags = [
        'US' => $revConfig['amazon_tag'] ?? 'vtete-20',
        'GB' => $revConfig['amazon_tag_uk'] ?? 'vtete-21',
        'UK' => $revConfig['amazon_tag_uk'] ?? 'vtete-21',
        'IN' => $revConfig['amazon_tag_in'] ?? 'virginiatete-21',
        'JP' => $revConfig['amazon_tag_jp'] ?? 'vtete-22',
        'CA' => $revConfig['amazon_tag_ca'] ?? 'vtete-20',
        'DE' => $revConfig['amazon_tag_de'] ?? 'vtete-20',
        'FR' => $revConfig['amazon_tag_fr'] ?? 'vtete-20',
        'IT' => $revConfig['amazon_tag_it'] ?? 'vtete-20',
        'ES' => $revConfig['amazon_tag_es'] ?? 'vtete-20',
        'NL' => $revConfig['amazon_tag_nl'] ?? 'vtete-20',
        'PL' => $revConfig['amazon_tag_pl'] ?? 'vtete-20',
        'SE' => $revConfig['amazon_tag_se'] ?? 'vtete-20',
        'BE' => $revConfig['amazon_tag_be'] ?? 'vtete-20',
        'AU' => $revConfig['amazon_tag_au'] ?? 'vtete-20',
        'BR' => $revConfig['amazon_tag_br'] ?? 'vtete-20',
        'MX' => $revConfig['amazon_tag_mx'] ?? 'vtete-20',
        'AE' => $revConfig['amazon_tag_ae'] ?? 'vtete08-21',
        'SA' => $revConfig['amazon_tag_sa'] ?? 'vtete-20',
        'SG' => $revConfig['amazon_tag_sg'] ?? 'vtete-20',
        'IE' => $revConfig['amazon_tag_ie'] ?? 'vtete-21',
        'TR' => $revConfig['amazon_tag_tr'] ?? 'vtete-20',
    ];

    wp_localize_script('helmetsan-currency-selector', 'helmetsan_geo_config', [
        'enable_geo_pricing_fallback' => !empty($features['enable_geo_pricing_fallback']),
        'amazon_tags'                 => $amazonTags,
    ]);

    if (!empty($revConfig['amazon_onelink_enabled']) && !empty($revConfig['amazon_onelink_id'])) {
        wp_enqueue_script(
            'amazon-onetag',
            'https://z-na.amazon-adsystem.com/widgets/onejs?MarketPlace=US&adInstanceId=' . rawurlencode($revConfig['amazon_onelink_id']),
            [],
            null,
            ['strategy' => 'async', 'in_footer' => true]
        );
    }

    wp_enqueue_script(
        'helmetsan-animations',
        get_stylesheet_directory_uri() . '/assets/js/animations.js',
        [],
        helmetsan_theme_asset_version('/assets/js/animations.js'),
        ['strategy' => 'defer', 'in_footer' => true]
    );

    if (!empty($features['enable_comparison_engine'])) {
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
            ['strategy' => 'defer', 'in_footer' => true]
        );
    }



    if (is_post_type_archive('helmet') || is_post_type_archive('brand') || is_post_type_archive('accessory') || is_tax()) {
        wp_enqueue_script(
            'helmetsan-theme-filters',
            get_stylesheet_directory_uri() . '/assets/js/filters.js',
            [],
            helmetsan_theme_asset_version('/assets/js/filters.js'),
            ['strategy' => 'defer', 'in_footer' => true]
        );
        wp_localize_script('helmetsan-theme-filters', 'helmetsan_ajax', [
            'url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('helmetsan_filter_nonce'),
            'enable_ajax' => !empty($features['enable_ajax_catalog_filters']),
            'lang' => function_exists('pll_current_language') ? pll_current_language() : 'en',
        ]);

        wp_enqueue_script(
            'helmetsan-catalog-view-switcher',
            get_stylesheet_directory_uri() . '/assets/js/catalog-view-switcher.js',
            [],
            helmetsan_theme_asset_version('/assets/js/catalog-view-switcher.js'),
            ['strategy' => 'defer', 'in_footer' => true]
        );
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
            ['strategy' => 'defer', 'in_footer' => true]
        );
    }

    // Price history chart (single helmet pages only)
    if (is_singular('helmet')) {
        $chartJsUrl = 'https://cdn.jsdelivr.net/npm/chart.js@4/dist/chart.umd.min.js';
        $chartAdapterUrl = 'https://cdn.jsdelivr.net/npm/chartjs-adapter-date-fns@3/dist/chartjs-adapter-date-fns.bundle.min.js';
        if (function_exists('helmetsan_is_china_visitor') && helmetsan_is_china_visitor()) {
            $chartJsUrl = 'https://cdn.bootcdn.net/ajax/libs/Chart.js/4.4.1/chart.umd.min.js';
            $chartAdapterUrl = 'https://cdn.bootcdn.net/ajax/libs/chartjs-adapter-date-fns/3.0.0/chartjs-adapter-date-fns.bundle.min.js';
        }

        wp_enqueue_script(
            'chart-js',
            $chartJsUrl,
            [],
            '4.4.1',
            ['strategy' => 'defer', 'in_footer' => true]
        );
        wp_enqueue_script(
            'chartjs-adapter-date',
            $chartAdapterUrl,
            ['chart-js'],
            '3.0.0',
            ['strategy' => 'defer', 'in_footer' => true]
        );
        wp_enqueue_script(
            'helmetsan-price-history',
            get_stylesheet_directory_uri() . '/assets/js/price-history.js',
            ['chart-js', 'chartjs-adapter-date'],
            helmetsan_theme_asset_version('/assets/js/price-history.js'),
            ['strategy' => 'defer', 'in_footer' => true]
        );
        wp_localize_script('helmetsan-price-history', 'hsPrice', [
            'apiBase'  => rest_url('hs/v1'),
            'helmetId' => get_the_ID(),
        ]);
    }

    // User Reviews (single helmet and accessory pages)
    if (is_singular(['helmet', 'accessory'])) {
        $secCfg = function_exists('helmetsan_core') ? helmetsan_core()->config()->securityConfig() : [];
        wp_enqueue_script(
            'helmetsan-reviews',
            get_stylesheet_directory_uri() . '/assets/js/reviews.js',
            [],
            helmetsan_theme_asset_version('/assets/js/reviews.js'),
            ['strategy' => 'defer', 'in_footer' => true]
        );
        wp_localize_script('helmetsan-reviews', 'hsReviews', [
            'apiBase' => rest_url('hs/v1/reviews'),
            'productId' => get_the_ID(),
            'turnstileSiteKey' => $secCfg['turnstile_site_key'] ?? '',
            'nonce' => wp_create_nonce('wp_rest'),
        ]);

        wp_enqueue_script(
            'helmetsan-pdp-interactive',
            get_stylesheet_directory_uri() . '/assets/js/pdp-interactive.js',
            [],
            helmetsan_theme_asset_version('/assets/js/pdp-interactive.js'),
            ['strategy' => 'defer', 'in_footer' => true]
        );
    }

    // Store Locator Map (dealers directory page or dealers/distributors archive)
    if (is_post_type_archive(['dealer', 'distributor']) || is_page('dealers')) {
        $leafletCssUrl = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.css';
        $leafletJsUrl = 'https://unpkg.com/leaflet@1.9.4/dist/leaflet.js';
        if (function_exists('helmetsan_is_china_visitor') && helmetsan_is_china_visitor()) {
            $leafletCssUrl = 'https://cdn.bootcdn.net/ajax/libs/leaflet/1.9.4/leaflet.min.css';
            $leafletJsUrl = 'https://cdn.bootcdn.net/ajax/libs/leaflet/1.9.4/leaflet.min.js';
        }

        wp_enqueue_style('leaflet', $leafletCssUrl, [], '1.9.4');
        wp_enqueue_script('leaflet', $leafletJsUrl, [], '1.9.4', ['strategy' => 'defer', 'in_footer' => true]);
        
        $locJs = '/assets/js/locator.js';
        wp_enqueue_script(
            'helmetsan-locator',
            get_stylesheet_directory_uri() . $locJs,
            ['leaflet'],
            helmetsan_theme_asset_version($locJs),
            ['strategy' => 'defer', 'in_footer' => true]
        );

        // Fetch coordinates and logo paths for all active dealers
        $dealerData = [];
        $query = new WP_Query([
            'post_type'      => 'dealer',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
        ]);

        if ($query->have_posts()) {
            while ($query->have_posts()) {
                $query->the_post();
                $id = get_the_ID();
                $geo = json_decode((string) get_post_meta($id, 'dealer_geo_json', true), true);
                if (is_array($geo) && isset($geo['lat'], $geo['lng'])) {
                    $dealerData[] = [
                        'title'     => get_the_title(),
                        'lat'       => $geo['lat'],
                        'lng'       => $geo['lng'],
                        'address'   => get_post_meta($id, 'dealer_address', true),
                        'phone'     => get_post_meta($id, 'dealer_phone', true),
                        'link'      => get_permalink($id),
                        'logo'      => helmetsan_get_logo_url($id),
                        'brands'    => json_decode((string) get_post_meta($id, 'dealer_brands_json', true), true) ?: [],
                        'online_store'  => get_post_meta($id, 'dealer_online_store', true) === '1',
                        'offline_store' => get_post_meta($id, 'dealer_offline_store', true) === '1',
                    ];
                }
            }
            wp_reset_postdata();
        }

        wp_localize_script('helmetsan-locator', 'hsDealers', $dealerData);
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
