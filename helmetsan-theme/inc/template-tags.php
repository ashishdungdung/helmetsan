<?php
/**
 * Template tags.
 *
 * @package HelmetsanTheme
 */

if (! defined('ABSPATH')) {
    exit;
}

function helmetsan_theme_posted_on(): void
{
    echo '<span class="posted-on">' . esc_html(get_the_date()) . '</span>';
}

/**
 * Human-readable marketplace label for Where to Buy (e.g. amazon-in → Amazon (India)).
 *
 * @param string $marketplaceId e.g. amazon-in, amazon-us, static
 * @return string
 */
function helmetsan_marketplace_label(string $marketplaceId): string
{
    $labels = [
        'amazon-us' => 'Amazon (US)',
        'amazon-in' => 'Amazon (India)',
        'amazon-uk' => 'Amazon (UK)',
        'amazon-de' => 'Amazon (Germany)',
        'amazon-fr' => 'Amazon (France)',
        'amazon-ca' => 'Amazon (Canada)',
        'amazon-it' => 'Amazon (Italy)',
        'amazon-es' => 'Amazon (Spain)',
        'amazon-jp' => 'Amazon (Japan)',
        'amazon-au' => 'Amazon (Australia)',
        'amazon-nl' => 'Amazon (Netherlands)',
        'amazon-pl' => 'Amazon (Poland)',
        'amazon-se' => 'Amazon (Sweden)',
        'amazon-be' => 'Amazon (Belgium)',
        'amazon-cz' => 'Amazon',
        'amazon'    => 'Amazon',
        'flipkart-in' => 'Flipkart',
        'static' => 'Amazon',
    ];
    $key = strtolower($marketplaceId);
    return $labels[$key] ?? ucwords(str_replace('-', ' ', $marketplaceId));
}

function helmetsan_get_brand_id(int $helmetId): int
{
    if (function_exists('pll_default_language') && function_exists('pll_get_post')) {
        $defaultLang = pll_default_language();
        $masterId = (int) pll_get_post($helmetId, $defaultLang);
        if ($masterId && $masterId > 0) {
            $helmetId = $masterId;
        }
    }
    return (int) get_post_meta($helmetId, 'rel_brand', true);
}

function helmetsan_get_brand_name(int $helmetId): string
{
    $brandId = helmetsan_get_brand_id($helmetId);
    if ($brandId <= 0) {
        return '';
    }

    $brand = get_post($brandId);
    return $brand instanceof WP_Post ? (string) $brand->post_title : '';
}

function helmetsan_get_related_helmets_by_brand(int $helmetId, int $limit = 6): array
{
    $brandId = helmetsan_get_brand_id($helmetId);
    if ($brandId <= 0) {
        return [];
    }

    $q = new WP_Query([
        'post_type' => 'helmet',
        'post_status' => 'publish',
        'post_parent' => 0,
        'posts_per_page' => max(1, $limit),
        'post__not_in' => [$helmetId],
        'meta_query' => [
            [
                'key' => 'rel_brand',
                'value' => $brandId,
            ],
        ],
    ]);

    if (! $q->have_posts()) {
        return [];
    }

    return $q->posts;
}

function helmetsan_get_helmet_price($helmetId): string
{
    if (function_exists('pll_default_language') && function_exists('pll_get_post')) {
        $defaultLang = pll_default_language();
        $masterId = (int) pll_get_post((int) $helmetId, $defaultLang);
        if ($masterId && $masterId > 0) {
            $helmetId = $masterId;
        }
    }

    if (function_exists('helmetsan_core')) {
        $priceService = helmetsan_core()->price();
        $perfConfig = helmetsan_core()->config()->performanceConfig();
        if (!empty($perfConfig['enable_geoip_pricing'])) {
            return $priceService->getGeoPrice((int) $helmetId);
        }
        return $priceService->getPrice($helmetId);
    }

    $price = get_post_meta($helmetId, 'price_retail_usd', true);
    if (! is_numeric((string) $price)) {
        return 'N/A';
    }

    return '$' . number_format((float) $price, 2);
}

/**
 * Render dynamic price HTML wrapper for Geo-IP Edge/Client parsing.
 */
function helmetsan_render_price_element(int $helmetId, string $class = 'hs-price', string $extraAttrs = ''): string
{
    if (function_exists('pll_default_language') && function_exists('pll_get_post')) {
        $defaultLang = pll_default_language();
        $masterId = (int) pll_get_post($helmetId, $defaultLang);
        if ($masterId && $masterId > 0) {
            $helmetId = $masterId;
        }
    }

    $basePrice = get_post_meta($helmetId, 'price_retail_usd', true);
    if (! is_numeric($basePrice)) {
        $basePrice = get_post_meta($helmetId, 'price_usd', true);
    }
    $basePriceVal = is_numeric($basePrice) ? (float) $basePrice : 0.0;
    $manualPricingJson = (string) get_post_meta($helmetId, 'geo_pricing_json', true);
    
    $formatted = helmetsan_get_helmet_price($helmetId);
    
    $attrsStr = $extraAttrs !== '' ? ' ' . $extraAttrs : '';
    $finalClass = str_contains($class, 'hs-price') ? $class : $class . ' hs-price';
    
    if ($formatted === 'Check Retailer' || $formatted === 'Check Deals' || $formatted === 'N/A' || $basePriceVal <= 0.0) {
        $fallbackClass = ($class === 'helmet-card__price') ? 'helmet-card__fallback-price' : $finalClass . ' is-fallback';
        return '<span class="' . esc_attr($fallbackClass) . '"' . $attrsStr . '>' . esc_html__('Check Deals', 'helmetsan-theme') . ' &rarr;</span>';
    }
    
    $taxLabel = '';
    if (function_exists('helmetsan_core')) {
        $cc = helmetsan_core()->geo()->getCountry();
        $vatCountries = [
            'DE', 'FR', 'IT', 'ES', 'GB', 'UK', 'PL', 'AT', 'BE', 'BG', 'CY', 'CZ', 'DK', 'EE', 'FI',
            'GR', 'HR', 'HU', 'IE', 'LT', 'LU', 'LV', 'MT', 'NL', 'PT', 'RO', 'SE', 'SI', 'SK'
        ];
        if (in_array($cc, $vatCountries, true)) {
            $taxLabel = ' <small class="hs-tax-label">incl. VAT</small>';
        }
    }

    return sprintf(
        '<span class="%s" data-base-price="%s" data-base-currency="USD" data-manual-pricing="%s"%s>%s%s</span>',
        esc_attr($finalClass),
        esc_attr((string) $basePriceVal),
        esc_attr($manualPricingJson),
        $attrsStr,
        esc_html($formatted),
        $taxLabel
    );
}

function helmetsan_get_brand_helmet_count(int $brandId): int
{
    $cacheKey = 'helmetsan_brand_count_' . $brandId;
    $cached = wp_cache_get($cacheKey);
    if (false !== $cached) {
        return (int) $cached;
    }

    $q = new WP_Query([
        'post_type' => 'helmet',
        'post_status' => 'publish',
        'post_parent' => 0,
        'posts_per_page' => 1,
        'fields' => 'ids',
        'meta_query' => [
            [
                'key' => 'rel_brand',
                'value' => $brandId,
            ],
        ],
    ]);

    $count = (int) $q->found_posts;
    wp_cache_set($cacheKey, $count, '', 3600);
    return $count;
}

/**
 * Get formatted price for any currency.
 */
function helmetsan_get_price($post, string $currency = 'USD'): string
{
    if (function_exists('helmetsan_core')) {
        return helmetsan_core()->price()->getPrice($post, $currency);
    }
    
    return 'N/A';
}

function helmetsan_get_certifications(int $helmetId): string
{
    $terms = [];
    if (function_exists('helmetsan_core')) {
        $terms = helmetsan_core()->helmets()->getInheritedTerms($helmetId, 'certification');
    } else {
        $terms = get_the_terms($helmetId, 'certification');
    }

    if (! is_array($terms) || $terms === []) {
        return 'N/A';
    }

    $names = array_map(static fn ($term): string => (string) $term->name, $terms);
    return implode(', ', $names);
}

/**
 * Get the helmet description (AI marketing description falling back to post content).
 * Supports inheritance for variants.
 */
function helmetsan_get_description(int $helmetId): string
{
    $desc = '';
    if (function_exists('helmetsan_core')) {
        $desc = (string) helmetsan_core()->helmets()->getInheritedMeta($helmetId, 'marketing_description');
    } else {
        $desc = (string) get_post_meta($helmetId, 'marketing_description', true);
    }

    if ($desc === '') {
        $post = get_post($helmetId);
        $desc = $post ? $post->post_content : '';
        
        // Fallback to parent content if child content is empty
        if ($desc === '' && $post && $post->post_parent > 0) {
            $parent = get_post($post->post_parent);
            $desc = $parent ? $parent->post_content : '';
        }
    }

    return $desc;
}

/**
 * Key features as HTML for comparison table (list or fallback text).
 *
 * @return string Safe HTML (ul/li or span)
 */
function helmetsan_get_helmet_key_features_html(int $helmetId): string
{
    $json = (string) get_post_meta($helmetId, 'features_json', true);
    $features = is_string($json) && $json !== '' ? json_decode($json, true) : null;
    if (is_array($features) && $features !== []) {
        $out = '<ul class="hs-comp-feature-list">';
        foreach (array_slice($features, 0, 12) as $item) {
            $out .= '<li>' . esc_html((string) $item) . '</li>';
        }
        if (count($features) > 12) {
            $out .= '<li class="hs-comp-feature-more">+' . (count($features) - 12) . ' more</li>';
        }
        $out .= '</ul>';
        return $out;
    }
    $terms = get_the_terms($helmetId, 'feature_tag');
    if (is_array($terms) && $terms !== []) {
        $names = wp_list_pluck($terms, 'name');
        return '<span class="hs-comp-feature-tags">' . esc_html(implode(', ', $names)) . '</span>';
    }
    return '<span class="hs-comp-feature-empty">—</span>';
}

function helmetsan_get_logo_url(int $postId): string
{
    $attachmentId = (int) get_post_meta($postId, '_helmetsan_logo_attachment_id', true);
    if ($attachmentId > 0) {
        $attachmentUrl = (string) wp_get_attachment_url($attachmentId);
        if ($attachmentUrl !== '') {
            return $attachmentUrl;
        }
    }

    $url = (string) get_post_meta($postId, '_helmetsan_logo_url', true);
    if ($url !== '') {
        return $url;
    }

    $thumbUrl = (string) get_the_post_thumbnail_url($postId, 'full');
    if ($thumbUrl !== '') {
        return $thumbUrl;
    }

    $postType = get_post_type($postId);
    if ($postType === 'brand') {
        $supportUrl = (string) get_post_meta($postId, 'brand_support_url', true);
        $domain = (string) wp_parse_url($supportUrl, PHP_URL_HOST);
        $domain = strtolower(trim($domain));
        $domain = preg_replace('#^www\.#', '', $domain) ?? $domain;
        if ($domain !== '') {
            $mediaCfg = wp_parse_args((array) get_option('helmetsan_media', []), [
                'logodev_enabled' => false,
                'logodev_publishable_key' => '',
                'logodev_token' => '',
            ]);
            if (! empty($mediaCfg['logodev_enabled'])) {
                $token = (string) ($mediaCfg['logodev_publishable_key'] ?? '');
                if ($token === '') {
                    $token = (string) ($mediaCfg['logodev_token'] ?? '');
                }
                $fallback = 'https://img.logo.dev/' . rawurlencode($domain);
                if ($token !== '') {
                    $fallback = add_query_arg(['token' => $token], $fallback);
                }
                return (string) $fallback;
            }
        }
    }

    if ($postType === 'helmet') {
        $brandId = helmetsan_get_brand_id($postId);
        if ($brandId > 0) {
            $brandLogo = (string) get_post_meta($brandId, '_helmetsan_logo_url', true);
            if ($brandLogo !== '') {
                return $brandLogo;
            }
        }
    }

    // Site-wide default placeholder when nothing is set (Settings → Default Images).
    if (function_exists('helmetsan_core')) {
        $svc = helmetsan_core()->defaultImages();
        $type = $postType === 'brand' ? 'brand' : ($postType === 'accessory' ? 'accessory' : 'helmet');
        $default = $svc->getDefaultImageUrl($type);
        if ($default !== '') {
            return $default;
        }
    }

    return '';
}

/**
 * Get technical analysis for a helmet, inheriting from parent if needed.
 */
function helmetsan_get_technical_analysis($helmetId): string
{
    if (function_exists('helmetsan_core')) {
        return (string) helmetsan_core()->helmets()->getInheritedMeta($helmetId, 'technical_analysis');
    }
    return (string) get_post_meta($helmetId, 'technical_analysis', true);
}

/**
 * Get key specs for a helmet.
 */
function helmetsan_get_key_specs($helmetId): array
{
    if (function_exists('helmetsan_core')) {
        $json = helmetsan_core()->helmets()->getInheritedMeta($helmetId, 'key_specs_json');
    } else {
        $json = get_post_meta($helmetId, 'key_specs_json', true);
    }

    if (is_string($json) && $json !== '') {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
    return [];
}

/**
 * Get compatible accessory IDs for a helmet.
 */
function helmetsan_get_compatible_accessories($helmetId): array
{
    if (function_exists('helmetsan_core')) {
        $json = helmetsan_core()->helmets()->getInheritedMeta($helmetId, 'compatible_accessories_json');
    } else {
        $json = get_post_meta($helmetId, 'compatible_accessories_json', true);
    }

    if (is_array($json)) {
        return $json;
    }
    if (is_string($json) && $json !== '') {
        $decoded = json_decode($json, true);
        return is_array($decoded) ? $decoded : [];
    }
    return [];
}

/**
 * Get shell material for a helmet.
 */
function helmetsan_get_shell_material($helmetId): string
{
    if (function_exists('helmetsan_core')) {
        return (string) helmetsan_core()->helmets()->getInheritedMeta($helmetId, 'spec_shell_material');
    }
    return (string) get_post_meta($helmetId, 'spec_shell_material', true);
}

/**
 * Get weight for a helmet (grams).
 */
function helmetsan_get_weight($helmetId): int
{
    if (function_exists('helmetsan_core')) {
        return (int) helmetsan_core()->helmets()->getInheritedMeta($helmetId, 'spec_weight_g');
    }
    return (int) get_post_meta($helmetId, 'spec_weight_g', true);
}

/**
 * Get head shape for a helmet.
 */
function helmetsan_get_head_shape($helmetId): string
{
    if (function_exists('helmetsan_core')) {
        return (string) helmetsan_core()->helmets()->getInheritedMeta($helmetId, 'head_shape');
    }
    return (string) get_post_meta($helmetId, 'head_shape', true);
}

/**
 * Get warranty years for a helmet (meta, e.g. from fill-missing).
 */
function helmetsan_get_warranty_years($helmetId): string
{
    if (function_exists('helmetsan_core')) {
        return (string) helmetsan_core()->helmets()->getInheritedMeta($helmetId, 'warranty_years');
    }
    return (string) get_post_meta($helmetId, 'warranty_years', true);
}

/**
 * Get use case for a helmet (e.g. touring, racing, commuter).
 */
function helmetsan_get_use_case($helmetId): string
{
    if (function_exists('helmetsan_core')) {
        return (string) helmetsan_core()->helmets()->getInheritedMeta($helmetId, 'use_case');
    }
    return (string) get_post_meta($helmetId, 'use_case', true);
}

/**
 * Get price range slug for a helmet.
 */
function helmetsan_get_price_range($helmetId): string
{
    $terms = get_the_terms($helmetId, 'price_range');
    if (! is_array($terms) || $terms === []) {
        return '';
    }
    return (string) $terms[0]->slug;
}

/**
 * Unified Source of Truth for Helmet Technical Specifications.
 * Fetches all high-value fields, handling inheritance and legacy JSON fallbacks.
 * 
 * @param int $helmetId
 * @return array<string,mixed>
 */
function helmetsan_get_technical_profile(int $helmetId): array
{
    $helmet = get_post($helmetId);
    if (!$helmet instanceof WP_Post) return [];

    $svc = function_exists('helmetsan_core') ? helmetsan_core()->helmets() : null;

    $getMeta = function($key) use ($helmetId, $svc) {
        if ($svc) return $svc->getInheritedMeta($helmetId, $key);
        return get_post_meta($helmetId, $key, true);
    };

    // 1. Safety Intelligence
    $homologation = (string) $getMeta('homologation_standard');
    $sharp = $getMeta('sharp_rating');
    $rotational = (string) $getMeta('rotational_tech');
    $emergencyRelease = $getMeta('emergency_release_system');
    $multiDensityEps = $getMeta('multi_density_eps');

    // Fallback to legacy safety_intelligence_json if new keys are empty
    if ($homologation === '' || $rotational === '' || empty($sharp) || $emergencyRelease === '' || $multiDensityEps === '') {
        $json = (string) $getMeta('safety_intelligence_json');
        $data = json_decode($json, true);
        if (is_array($data)) {
            if ($homologation === '') $homologation = (string) ($data['homologation_standard'] ?? '');
            if (empty($sharp)) $sharp = $data['sharp_rating'] ?? 0;
            if ($rotational === '') $rotational = (string) ($data['rotational_mitigation'] ?? '');
            if ($emergencyRelease === '') $emergencyRelease = !empty($data['emergency_release_system']) ? '1' : '0';
            if ($multiDensityEps === '') $multiDensityEps = !empty($data['multi_density_eps']) ? '1' : '0';
        }
    }

    // 2. Aero & Acoustic
    $noise = (string) $getMeta('noise_db_at_100kph');
    $ventilation = $getMeta('ventilation_score');
    $windTunnel = $getMeta('wind_tunnel_tested');

    // Fallback to legacy aero_acoustic_profile_json
    if ($noise === '' || empty($ventilation) || $windTunnel === '') {
        $json = (string) $getMeta('aero_acoustic_profile_json');
        $data = json_decode($json, true);
        if (is_array($data)) {
            if ($noise === '') $noise = (string) ($data['noise_db_at_100kph'] ?? '');
            if (empty($ventilation)) $ventilation = $data['ventilation_efficiency_score'] ?? 0;
            if ($windTunnel === '') $windTunnel = !empty($data['wind_tunnel_tested']) ? '1' : '0';
        }
    }

    // 3. Tech Integration
    $comms = (string) $getMeta('comms_ready');
    if ($comms === '') {
        $json = (string) $getMeta('tech_integration_json');
        $data = json_decode($json, true);
        if (is_array($data)) {
            $comms = (string) ($data['dedicated_intercom_integration'] ?? $data['comms_cutout_type'] ?? '');
        }
    }

    // 4. Features (Array)
    $visorJson = (string) $getMeta('visor_features_json');
    $visor = json_decode($visorJson, true) ?: [];
    
    $linerJson = (string) $getMeta('liner_features_json');
    $liner = json_decode($linerJson, true) ?: [];

    // 5. Fitment & Sizing Fit
    $fitmentJson = (string) $getMeta('fitment_coordinates_json');
    $fitment = json_decode($fitmentJson, true) ?: [];

    $glassesGrooves = $getMeta('glasses_grooves');
    $removableInterior = $getMeta('removable_interior');

    if ($glassesGrooves === '' || $removableInterior === '') {
        $sizingJson = (string) $getMeta('sizing_fit_json');
        $sizingData = json_decode($sizingJson, true);
        if (is_array($sizingData)) {
            if ($glassesGrooves === '') $glassesGrooves = !empty($sizingData['glasses_grooves']) ? '1' : '0';
            if ($removableInterior === '') $removableInterior = !empty($sizingData['removable_washable_interior']) ? '1' : '0';
        }
    }

    // 6. Specs Fallbacks
    $sunVisor = $getMeta('integrated_sun_visor');
    $pinlockIncluded = $getMeta('pinlock_included');
    $pinlockType = (string) $getMeta('pinlock_type');
    $breathDeflector = $getMeta('breath_deflector');

    if ($sunVisor === '' || $pinlockIncluded === '' || $pinlockType === '' || $breathDeflector === '') {
        $specsJson = (string) $getMeta('specs_json');
        $specsData = json_decode($specsJson, true);
        if (is_array($specsData)) {
            if ($sunVisor === '') $sunVisor = !empty($specsData['integrated_sun_visor']) ? '1' : '0';
            if ($pinlockIncluded === '') $pinlockIncluded = !empty($specsData['pinlock_included']) ? '1' : '0';
            if ($pinlockType === '') $pinlockType = (string) ($specsData['pinlock_type'] ?? '');
            if ($breathDeflector === '') $breathDeflector = !empty($specsData['breath_deflector_curtain_included']) ? '1' : '0';
        }
    }

    return [
        'homologation' => $homologation ?: 'N/A',
        'sharp_rating' => (int) $sharp,
        'rotational_tech' => $rotational ?: 'N/A',
        'noise_db' => $noise ? $noise . ' dB' : 'N/A',
        'ventilation_score' => $ventilation ? $ventilation . '/10' : 'N/A',
        'warranty' => helmetsan_get_warranty_years($helmetId) ?: 'N/A',
        'strap_type' => (string) $getMeta('strap_type') ?: 'N/A',
        'visor_features' => $visor,
        'liner_features' => $liner,
        'comms_ready' => $comms ?: 'N/A',
        'weight' => helmetsan_get_weight($helmetId),
        'shell' => helmetsan_get_shell_material($helmetId) ?: 'N/A',
        'head_shape' => helmetsan_get_head_shape($helmetId) ?: 'N/A',
        'fitment' => $fitment,
        // New features mapped clearly for PHP templates
        'emergency_release' => !empty($emergencyRelease) && $emergencyRelease !== '0',
        'multi_density_eps' => !empty($multiDensityEps) && $multiDensityEps !== '0',
        'glasses_grooves' => !empty($glassesGrooves) && $glassesGrooves !== '0',
        'removable_interior' => !empty($removableInterior) && $removableInterior !== '0',
        'integrated_sun_visor' => !empty($sunVisor) && $sunVisor !== '0',
        'pinlock_included' => !empty($pinlockIncluded) && $pinlockIncluded !== '0',
        'pinlock_type' => $pinlockType ?: 'N/A',
        'breath_deflector' => !empty($breathDeflector) && $breathDeflector !== '0',
        'wind_tunnel_tested' => !empty($windTunnel) && $windTunnel !== '0',
    ];
}

/**
 * @param string $type
 * @return array<string,mixed>
 */
function helmetsan_get_mega_menu_data(string $type = 'helmet'): array
{
    $filename = $type . '-mega-menu.json';
    $paths = [
        WP_CONTENT_DIR . '/uploads/helmetsan-data/catalogs/' . $filename,
        get_stylesheet_directory() . '/data/' . $filename,
        ABSPATH . '../data/catalogs/' . $filename,
    ];

    foreach ($paths as $path) {
        if (! is_string($path) || ! file_exists($path)) {
            continue;
        }
        $raw = file_get_contents($path);
        if (! is_string($raw) || $raw === '') {
            continue;
        }
        $decoded = json_decode($raw, true);
        if (is_array($decoded)) {
            return $decoded;
        }
    }

    return [];
}

/**
 * Backward compatibility wrapper.
 */
function helmetsan_get_helmet_mega_menu_data(): array
{
    return helmetsan_get_mega_menu_data('helmet');
}

function helmetsan_find_term_slug_by_label(string $taxonomy, string $label): string
{
    static $cache = [];
    $key = $taxonomy . '::' . $label;
    if (isset($cache[$key])) {
        return $cache[$key];
    }

    $normalizedLabel = helmetsan_normalize_filter_label($taxonomy, $label);
    $slug = sanitize_title($normalizedLabel);
    $term = get_term_by('slug', $slug, $taxonomy);
    if ($term instanceof WP_Term) {
        $cache[$key] = (string) $term->slug;
        return $cache[$key];
    }

    $term = get_term_by('name', $normalizedLabel, $taxonomy);
    if ($term instanceof WP_Term) {
        $cache[$key] = (string) $term->slug;
        return $cache[$key];
    }

    $cache[$key] = $slug;
    return $cache[$key];
}

function helmetsan_normalize_filter_label(string $taxonomy, string $label): string
{
    $value = trim((string) $label);
    if ($value === '') {
        return '';
    }
    if ($taxonomy !== 'helmet_type') {
        return $value;
    }

    $lower = strtolower($value);
    $lower = str_replace(['&', '/'], [' and ', ' '], $lower);
    $lower = preg_replace('/\s+/', ' ', $lower) ?? $lower;
    $lower = str_replace('helmets', '', $lower);
    $lower = trim($lower);

    return match ($lower) {
        'full face', 'full-face' => 'Full Face',
        'modular' => 'Modular',
        'open face', 'open-face' => 'Open Face',
        'half', 'half helmet', 'half-helmet' => 'Half',
        'dirt', 'mx', 'dirt mx', 'dirt motocross', 'off road', 'off-road', 'motocross' => 'Dirt/MX',
        'adventure', 'dual sport', 'adventure dual sport', 'adventure and dual sport' => 'Adventure/Dual Sport',
        'touring' => 'Touring',
        'track', 'race', 'track race' => 'Track/Race',
        'youth' => 'Youth',
        'snow', 'snowmobile' => 'Snow',
        'carbon fiber', 'carbon-fiber' => 'Carbon Fiber',
        'graphics', 'graphic' => 'Graphics',
        'sale', 'closeout' => 'Sale',
        default => $value,
    };
}

function helmetsan_is_certification_label(string $label): bool
{
    $needle = strtolower(trim($label));
    if ($needle === '') {
        return false;
    }

    $certHints = ['ece', 'snell', 'fim', 'dot'];
    foreach ($certHints as $hint) {
        if (str_contains($needle, $hint)) {
            return true;
        }
    }
    return false;
}

function helmetsan_is_type_label(string $label): bool
{
    $needle = strtolower(trim($label));
    $typeHints = ['full face', 'modular', 'open face', 'half', 'dirt', 'adventure', 'touring', 'track', 'youth', 'snow'];
    foreach ($typeHints as $hint) {
        if (str_contains($needle, $hint)) {
            return true;
        }
    }
    return false;
}

function helmetsan_mega_menu_item_url(string $label, string $heading, string $type = 'helmet'): string
{
    $label = trim($label);
    $heading = trim($heading);

    // --- BRANDS MENU ---
    if ($type === 'brands') {
        $slug = sanitize_title($label);
        return (string) home_url('/brands/' . $slug . '/');
    }

    // --- ACCESSORIES MENU ---
    if ($type === 'accessories') {
        $slug = sanitize_title($label);
        return (string) home_url('/accessory-category/' . $slug . '/');
    }

    // --- MOTORCYCLES MENU ---
    if ($type === 'motorcycles') {
        $slug = sanitize_title($label);
        return (string) home_url('/motorcycles/?s=' . rawurlencode($label));
    }

    // --- HELMETS MENU (default) ---
    $helmetsArchive = (string) get_post_type_archive_link('helmet');
    if ($helmetsArchive === '') {
        $helmetsArchive = (string) home_url('/helmets/');
    }
    if ($label === '') {
        return $helmetsArchive;
    }

    // "Shop by Brand" column → /brands/slug/
    if (stripos($heading, 'Brand') !== false) {
        $slug = sanitize_title($label);
        return (string) home_url('/brands/' . $slug . '/');
    }

    // "Shop by Type" column → /helmet-type/slug/
    if (stripos($heading, 'Type') !== false || helmetsan_is_type_label($label)) {
        $slug = helmetsan_find_term_slug_by_label('helmet_type', $label);
        return (string) home_url('/helmet-type/' . $slug . '/');
    }

    // "Riding Style & Safety" column → certifications or features
    if (stripos($heading, 'Safety') !== false || stripos($heading, 'Riding') !== false) {
        if (helmetsan_is_certification_label($label)) {
            $slug = helmetsan_find_term_slug_by_label('certification', $label);
            return (string) home_url('/certification/' . $slug . '/');
        }
        // Riding styles → feature tags
        $slug = helmetsan_find_term_slug_by_label('feature_tag', $label);
        return (string) home_url('/feature/' . $slug . '/');
    }

    // "Features & Style" column → /feature/slug/
    if (stripos($heading, 'Style') !== false || stripos($heading, 'Feature') !== false) {
        $slug = helmetsan_find_term_slug_by_label('feature_tag', $label);
        return (string) home_url('/feature/' . $slug . '/');
    }

    // "Model Family" column → query param
    if (stripos($heading, 'Model') !== false) {
        return (string) add_query_arg(['helmet_family' => $label], $helmetsArchive);
    }

    // Fallback: feature tag filter
    $slug = helmetsan_find_term_slug_by_label('feature_tag', $label);
    return (string) home_url('/feature/' . $slug . '/');
}

/**
 * Return the "View All" archive URL for a mega menu type (no double-s or wrong path).
 *
 * @param string $type One of: helmet, helmets, brands, accessories, motorcycles
 * @return string Full URL for the archive
 */
function helmetsan_mega_menu_footer_url(string $type): string
{
    $slug = [
        'helmet'      => 'helmets',
        'helmets'     => 'helmets',
        'brands'      => 'brands',
        'accessories' => 'accessories',
        'motorcycles' => 'motorcycles',
    ];
    $path = $slug[$type] ?? $type;
    return (string) home_url('/' . $path . '/');
}

function helmetsan_render_mega_menu(string $type = 'helmet'): void
{
    $lang = function_exists('pll_current_language') ? pll_current_language() : 'en';
    $cacheKey = 'hs_mega_menu_' . $type . '_' . $lang;

    // Check transient cache
    $cached = get_transient($cacheKey);
    if ($cached !== false) {
        echo $cached;
        return;
    }

    ob_start();

    // 1. Try to render from WP Nav Menu (The "Control" Way)
    $location = 'mega_' . $type; // e.g., 'mega_brands'
    $locations = get_nav_menu_locations();

    if (isset($locations[$location])) {
        $menuId = $locations[$location];
        $items = wp_get_nav_menu_items($menuId);

        if ($items) {
            // Build Tree
            $menuTree = [];
            foreach ($items as $item) {
                if (empty($item->menu_item_parent)) {
                    $menuTree[$item->ID] = [
                        'heading' => $item->title,
                        'url' => $item->url,
                        'children' => []
                    ];
                }
            }
            foreach ($items as $item) {
                if (! empty($item->menu_item_parent) && isset($menuTree[$item->menu_item_parent])) {
                    $menuTree[$item->menu_item_parent]['children'][] = [
                        'label' => $item->title,
                        'url' => $item->url,
                    ];
                }
            }

            // Get JSON just for the highlight blocks / footer / title fallback
            $jsonData = helmetsan_get_mega_menu_data($type);
            $title = $jsonData['title'] ?? ucfirst($type) . ' Menu';
            $footerLabel = $jsonData['footer'] ?? 'View All ' . ucfirst($type);
            $footerUrl = helmetsan_mega_menu_footer_url($type);
            
            ?>
            <div class="hs-mega-menu">
                <div class="hs-mega-menu__inner">
                    <div class="hs-mega-menu__grid">
                        <?php foreach ($menuTree as $column): ?>
                            <div class="hs-mega-menu__col">
                                <h3>
                                    <?php if ($column['url'] && $column['url'] !== '#'): ?>
                                        <a href="<?php echo esc_url($column['url']); ?>"><?php echo esc_html($column['heading']); ?></a>
                                    <?php else: ?>
                                        <?php echo esc_html($column['heading']); ?>
                                    <?php endif; ?>
                                </h3>

                                <?php if (! empty($column['children'])): ?>
                                    <ul>
                                        <?php foreach ($column['children'] as $link): ?>
                                            <li>
                                                <a href="<?php echo esc_url($link['url']); ?>">
                                                    <?php echo esc_html($link['label']); ?>
                                                </a>
                                            </li>
                                        <?php endforeach; ?>
                                    </ul>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>

                    <?php if (! empty($jsonData['highlight_blocks'])): ?>
                        <div class="hs-mega-menu__bottom">
                            <?php foreach ($jsonData['highlight_blocks'] as $block): ?>
                                <div class="hs-mega-menu__highlight">
                                    <h3><?php echo esc_html($block['heading']); ?></h3>
                                    <?php if (! empty($block['items'])): ?>
                                        <ul>
                                            <?php foreach ($block['items'] as $item): 
                                                $label = is_string($item) ? $item : $item['label'];
                                                $url = '#'; 
                                            ?>
                                                <li><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a></li>
                                            <?php endforeach; ?>
                                        </ul>
                                    <?php endif; ?>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>

                    <div class="hs-mega-menu__footer">
                        <a href="<?php echo esc_url($footerUrl); ?>">
                            <?php echo esc_html($footerLabel); ?> &rarr;
                        </a>
                    </div>
                </div>
                
                <!-- Mobile Fallback (Nav Menu) -->
                <div class="hs-mega-menu-mobile">
                     <div class="hs-mega-menu-mobile__title"><?php echo esc_html($title); ?></div>
                     <?php foreach ($menuTree as $column): ?>
                        <details class="hs-mobile-nav-group">
                            <summary><?php echo esc_html($column['heading']); ?></summary>
                            <ul>
                                <?php foreach ($column['children'] as $link): ?>
                                    <li><a href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($link['label']); ?></a></li>
                                <?php endforeach; ?>
                            </ul>
                        </details>
                     <?php endforeach; ?>
                </div>

            </div>
            <?php
            $html = ob_get_clean();
            if (trim($html) !== '') {
                set_transient($cacheKey, $html, DAY_IN_SECONDS);
            }
            echo $html;
            return;
        }
    }

    // 2. Fallback to JSON (Original Logic)
    $menu = helmetsan_get_mega_menu_data($type);
    if ($menu === []) {
        ob_end_clean();
        return;
    }

    $columns = isset($menu['columns']) && is_array($menu['columns']) ? $menu['columns'] : [];
    $highlights = isset($menu['highlight_blocks']) && is_array($menu['highlight_blocks']) ? $menu['highlight_blocks'] : [];
    $families = isset($menu['family_examples']) && is_array($menu['family_examples']) ? $menu['family_examples'] : [];
    if ($columns === []) {
        ob_end_clean();
        return;
    }

    $footerUrl  = helmetsan_mega_menu_footer_url($type);
    $footerLabel = $menu['footer'] ?? 'View All ' . ucfirst($type);
    ?>
    <section class="hs-mega-menu" aria-label="<?php echo esc_attr(ucfirst($type)); ?> mega menu">
        <div class="hs-mega-menu__inner">
            <div class="hs-mega-menu__grid">
                <?php foreach ($columns as $column): 
                    $heading = isset($column['heading']) ? (string) $column['heading'] : '';
                    $items = isset($column['items']) && is_array($column['items']) ? $column['items'] : [];
                    if ($heading === '') continue;
                ?>
                    <article class="hs-mega-menu__col">
                        <h3><?php echo esc_html($heading); ?></h3>
                        <ul>
                        <?php foreach ($items as $item): 
                             $label = is_string($item) ? $item : $item['label'];
                             $url = helmetsan_mega_menu_item_url($label, $heading, $type);
                        ?>
                            <li><a href="<?php echo esc_url($url); ?>"><?php echo esc_html($label); ?></a></li>
                        <?php endforeach; ?>
                        </ul>
                    </article>
                <?php endforeach; ?>
            </div>
            <div class="hs-mega-menu__footer">
                <a href="<?php echo esc_url($footerUrl); ?>"><?php echo esc_html($footerLabel); ?> &rarr;</a>
            </div>
        </div>
    </section>
    <?php
    $html = ob_get_clean();
    if (trim($html) !== '') {
        set_transient($cacheKey, $html, DAY_IN_SECONDS);
    }
    echo $html;
}
/**
 * Backward compatibility wrapper.
 */
function helmetsan_render_helmet_mega_menu(): void
{
    helmetsan_render_mega_menu('helmet');
}

/**
 * Render sticky comparison bar.
 */
function helmetsan_render_comparison_bar(): void
{
    get_template_part('template-parts/sticky-comparison-bar');
}
add_action('wp_footer', 'helmetsan_render_comparison_bar');

/**
 * Render a semantic breadcrumb trail for Helmets and Brands.
 * Returns both HTML for display and a data array for Schema.org injection.
 *
 * @param bool $echo Whether to echo or return the HTML.
 * @return string|array If $echo is false, returns HTML. Use globally to get schema data.
 */
function helmetsan_breadcrumb(bool $echo = true)
{
    $items = [
        [
            'name' => __('Home', 'helmetsan-theme'),
            'url'  => home_url('/'),
        ]
    ];

    if (is_singular('helmet')) {
        $helmetId = get_the_ID();
        $brandId  = helmetsan_get_brand_id($helmetId);
        $brandName = helmetsan_get_brand_name($helmetId);
        $family    = (string) get_post_meta($helmetId, 'helmet_family', true);

        // 1. Helmets Archive
        $items[] = [
            'name' => __('Helmets', 'helmetsan-theme'),
            'url'  => get_post_type_archive_link('helmet'),
        ];

        // 2. Brand
        if ($brandId > 0 && $brandName !== '') {
            $items[] = [
                'name' => $brandName,
                'url'  => get_permalink($brandId),
            ];
        }

        // 3. Family (optional search link)
        if ($family !== '') {
            $items[] = [
                'name' => $family,
                'url'  => add_query_arg('helmet_family', $family, get_post_type_archive_link('helmet')),
            ];
        }

        // 4. Current Post
        $items[] = [
            'name' => get_the_title(),
            'url'  => get_permalink(),
        ];
    } elseif (is_singular('brand')) {
        $items[] = [
            'name' => __('Brands', 'helmetsan-theme'),
            'url'  => get_post_type_archive_link('brand'),
        ];
        $items[] = [
            'name' => get_the_title(),
            'url'  => get_permalink(),
        ];
    } elseif (is_post_type_archive('helmet')) {
        $items[] = [
            'name' => __('Helmets', 'helmetsan-theme'),
            'url'  => get_post_type_archive_link('helmet'),
        ];
    } elseif (is_post_type_archive('brand')) {
        $items[] = [
            'name' => __('Brands', 'helmetsan-theme'),
            'url'  => get_post_type_archive_link('brand'),
        ];
    } elseif (is_post_type_archive('accessory')) {
        $items[] = [
            'name' => __('Accessories', 'helmetsan-theme'),
            'url'  => get_post_type_archive_link('accessory') ?: home_url('/accessories/'),
        ];
    } elseif (is_singular('accessory')) {
        $items[] = [
            'name' => __('Accessories', 'helmetsan-theme'),
            'url'  => get_post_type_archive_link('accessory') ?: home_url('/accessories/'),
        ];
        $catTerms = get_the_terms(get_the_ID(), 'accessory_category');
        if (is_array($catTerms) && ! empty($catTerms) && $catTerms[0] instanceof WP_Term) {
            $items[] = [
                'name' => $catTerms[0]->name,
                'url'  => get_term_link($catTerms[0]),
            ];
        }
        $items[] = [
            'name' => get_the_title(),
            'url'  => get_permalink(),
        ];
    } elseif (is_tax('accessory_category')) {
        $items[] = [
            'name' => __('Accessories', 'helmetsan-theme'),
            'url'  => get_post_type_archive_link('accessory') ?: home_url('/accessories/'),
        ];
        $term = get_queried_object();
        if ($term instanceof WP_Term) {
            $items[] = [
                'name' => $term->name,
                'url'  => get_term_link($term),
            ];
        }
    }

    if (! $echo) {
        return $items;
    }

    if (count($items) <= 1) {
        return '';
    }

    $html = '<nav class="hs-breadcrumb" aria-label="' . esc_attr__('Breadcrumb', 'helmetsan-theme') . '"><ol class="hs-breadcrumb__list">';
    foreach ($items as $i => $item) {
        $isLast = ($i === count($items) - 1);
        $html .= '<li class="hs-breadcrumb__item">';
        if ($isLast) {
            $html .= '<span class="hs-breadcrumb__current" aria-current="page">' . esc_html($item['name']) . '</span>';
        } else {
            $html .= '<a href="' . esc_url($item['url']) . '">' . esc_html($item['name']) . '</a>';
            $html .= '<span class="hs-breadcrumb__sep" aria-hidden="true">/</span>';
        }
        $html .= '</li>';
    }
    $html .= '</ol></nav>';

    echo $html; // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped
    return $html;
}/**
 * Get premium sitewide SVGs to replace standard emojis.
 *
 * @param string $icon Name of the icon (e.g. 'helmet', 'safety', 'analysis')
 * @param string $class Additional CSS classes
 * @return string SVG HTML
 */
function helmetsan_get_icon(string $icon, string $class = ''): string
{
    $icons = [
        'helmet' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M4.5 12a8.5 8.5 0 1 1 15 0c0 4.7-3.8 8.5-8.5 8.5a8.5 8.5 0 0 1-6.5-17.5"/><path d="M4.5 12h15"/></svg>',
        'safety' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 22s8-4 8-10V5l-8-3-8 3v7c0 6 8 10 8 10z"/></svg>',
        'analysis' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><path d="M9.09 9a3 3 0 0 1 5.83 1c0 2-3 3-3 3"/><line x1="12" y1="17" x2="12.01" y2="17"/></svg>',
        'store' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/></svg>',
        'specs' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><line x1="21" y1="10" x2="3" y2="10"/><line x1="21" y1="6" x2="3" y2="6"/><line x1="21" y1="14" x2="3" y2="14"/><line x1="21" y1="18" x2="3" y2="18"/></svg>',
        'check' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>',
        'cart' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><circle cx="9" cy="21" r="1"/><circle cx="20" cy="21" r="1"/><path d="M1 1h4l2.68 13.39a2 2 0 0 0 2 1.61h9.72a2 2 0 0 0 2-1.61L23 6H6"/></svg>',
        'tag' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M20.59 13.41l-7.17 7.17a2 2 0 0 1-2.83 0L2 12V2h10l8.59 8.59a2 2 0 0 1 0 2.82z"/><line x1="7" y1="7" x2="7.01" y2="7"/></svg>',
        'star' => '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><polygon points="12 2 15.09 8.26 22 9.27 17 14.14 18.18 21.02 12 17.77 5.82 21.02 7 14.14 2 9.27 8.91 8.26 12 2"/></svg>',
    ];

    $svg = $icons[$icon] ?? '';
    if ($svg === '') {
        return '';
    }

    $finalClass = 'hs-icon hs-icon--' . $icon . ($class ? ' ' . $class : '');
    return str_replace('<svg ', '<svg class="' . esc_attr($finalClass) . '" ', $svg);
}

/**
 * Calculate the average rating for a specific product based on its reviews.
 *
 * @param int $productId The ID of the product (helmet/accessory).
 * @return float Average rating (0-5).
 */
function helmetsan_get_average_rating(int $productId): float
{
    return (float) get_post_meta($productId, '_wc_average_rating', true);
}

/**
 * Get rating distribution for a specific product.
 *
 * @param int $productId
 * @return array<int,int> Array with rating as key and count as value.
 */
function helmetsan_get_rating_distribution(int $productId): array
{
    $distribution = get_post_meta($productId, '_wc_rating_count', true);
    if (!is_array($distribution)) {
        return [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0];
    }
    
    // Ensure all keys exist
    return [
        5 => isset($distribution[5]) ? (int)$distribution[5] : 0,
        4 => isset($distribution[4]) ? (int)$distribution[4] : 0,
        3 => isset($distribution[3]) ? (int)$distribution[3] : 0,
        2 => isset($distribution[2]) ? (int)$distribution[2] : 0,
        1 => isset($distribution[1]) ? (int)$distribution[1] : 0,
    ];
}

/**
 * Query Local Dealers stocking a specific Helmet Brand within the visitor's Region.
 */
function helmetsan_get_local_dealers(int $helmetId, string $brandName, string $countryCode): array {
    $cacheKey = "hs_local_dealers_{$helmetId}_{$countryCode}";
    $cached = wp_cache_get($cacheKey, 'helmetsan');
    if ($cached !== false) {
        return $cached;
    }

    // Query dealers matching the brand and country taxonomies/meta
    $args = [
        'post_type'      => 'dealer',
        'post_status'    => 'publish',
        'posts_per_page' => 10,
        'tax_query'      => [
            'relation' => 'AND',
            [
                'taxonomy' => 'region',
                'field'    => 'slug',
                'terms'    => [strtolower($countryCode), 'global'],
            ]
        ],
        'meta_query'     => [
            'relation' => 'AND',
            [
                'key'     => 'dealer_offline_store',
                'value'   => '1',
                'compare' => '='
            ]
        ]
    ];

    $query = new WP_Query($args);
    $dealers = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $id = get_the_ID();
            
            // Validate that the brand is stocked by parsing the dealer_brands_json meta field
            $brandsStocked = json_decode((string) get_post_meta($id, 'dealer_brands_json', true), true);
            if (is_array($brandsStocked) && in_array($brandName, $brandsStocked, true)) {
                $dealers[] = [
                    'id'       => $id,
                    'title'    => get_the_title(),
                    'phone'    => get_post_meta($id, 'dealer_phone', true),
                    'email'    => get_post_meta($id, 'dealer_email', true),
                    'address'  => get_post_meta($id, 'dealer_address', true),
                    'city'     => get_post_meta($id, 'dealer_city', true),
                    'website'  => get_post_meta($id, 'dealer_website', true),
                    'services' => json_decode((string) get_post_meta($id, 'dealer_services_json', true), true) ?: [],
                    'geo'      => json_decode((string) get_post_meta($id, 'dealer_geo_json', true), true)
                ];
            }
        }
        wp_reset_postdata();
    }

    wp_cache_set($cacheKey, $dealers, 'helmetsan', 12 * HOUR_IN_SECONDS);
    return $dealers;
}

/**
 * Query Distributors supplying a given Brand in a particular Region.
 */
function helmetsan_get_brand_distributors(string $brandName, string $regionCode): array {
    $args = [
        'post_type'      => 'distributor',
        'post_status'    => 'publish',
        'posts_per_page' => -1,
        'tax_query'      => [
            [
                'taxonomy' => 'region',
                'field'    => 'slug',
                'terms'    => [strtolower($regionCode), 'global']
            ]
        ]
    ];

    $query = new WP_Query($args);
    $distributors = [];

    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $id = get_the_ID();
            
            // Filter by brand association
            $brandsHandled = json_decode((string) get_post_meta($id, 'distributor_brands_json', true), true);
            if (is_array($brandsHandled) && in_array($brandName, $brandsHandled, true)) {
                $distributors[] = [
                    'id'         => $id,
                    'title'      => get_the_title(),
                    'type'       => get_post_meta($id, 'distributor_type', true),
                    'website'    => get_post_meta($id, 'distributor_website', true),
                    'phone'      => get_post_meta($id, 'distributor_phone', true),
                    'email'      => get_post_meta($id, 'distributor_email', true),
                    'warehouses' => json_decode((string) get_post_meta($id, 'distributor_warehouses_json', true), true) ?: [],
                    'contacts'   => json_decode((string) get_post_meta($id, 'distributor_contacts_json', true), true) ?: []
                ];
            }
        }
        wp_reset_postdata();
    }

    return $distributors;
}

/**
 * Find a physical Dealer CPT ID by their associated marketplace ID.
 */
function helmetsan_get_dealer_by_marketplace(string $marketplaceId): int {
    $cacheKey = "hs_dealer_by_mp_" . sanitize_title($marketplaceId);
    $cached = wp_cache_get($cacheKey, 'helmetsan');
    if ($cached !== false) {
        return (int) $cached;
    }

    $posts = get_posts([
        'post_type'      => 'dealer',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'meta_query'     => [
            [
                'key'     => 'dealer_marketplaces_json',
                'value'   => '"' . sanitize_text_field($marketplaceId) . '"',
                'compare' => 'LIKE'
            ]
        ],
        'fields'         => 'ids'
    ]);

    $dealerId = (is_array($posts) && $posts !== []) ? (int) $posts[0] : 0;
    wp_cache_set($cacheKey, $dealerId, 'helmetsan', 12 * HOUR_IN_SECONDS);
    return $dealerId;
}

/**
 * Convert a two-letter country code into a native flag emoji using HTML entity decoders.
 *
 * @param string $code ISO 3166-1 alpha-2 country code.
 * @return string UTF-8 flag emoji or empty string.
 */
function helmetsan_get_country_flag(string $code): string
{
    $code = strtoupper(trim($code));
    if (strlen($code) !== 2) {
        return '';
    }
    
    // Fallback static mapping for safety
    $staticMap = [
        'US' => '🇺🇸', 'IN' => '🇮🇳', 'DE' => '🇩🇪', 'GB' => '🇬🇧', 'UK' => '🇬🇧', 'FR' => '🇫🇷',
        'IT' => '🇮🇹', 'ES' => '🇪🇸', 'CA' => '🇨🇦', 'AU' => '🇦🇺', 'JP' => '🇯🇵', 'BR' => '🇧🇷',
        'PL' => '🇵🇱', 'MX' => '🇲🇽', 'AE' => '🇦🇪', 'NG' => '🇳🇬', 'KE' => '🇰🇪', 'EG' => '🇪🇬',
        'MA' => '🇲🇦', 'GH' => '🇬🇭', 'UG' => '🇺🇬', 'TZ' => '🇹🇿'
    ];
    if (isset($staticMap[$code])) {
        return $staticMap[$code];
    }

    $c1 = ord($code[0]) - 65 + 0x1F1E6;
    $c2 = ord($code[1]) - 65 + 0x1F1E6;
    
    return html_entity_decode('&#' . $c1 . ';&#' . $c2 . ';', ENT_NOQUOTES, 'UTF-8');
}

/**
 * Canonical country data normalizer for Helmetsan intelligence platform.
 * Normalizes variations like 'USA', 'United States', 'US' -> United States / 🇺🇸.
 * Handles 'International' gracefully with 🌐 badge.
 */
function helmetsan_normalize_country(string $raw): array
{
    $clean = trim($raw);
    $key   = strtolower($clean);

    $map = [
        'usa'            => ['name' => 'United States',  'flag' => '🇺🇸', 'iso' => 'US', 'is_international' => false],
        'united states'  => ['name' => 'United States',  'flag' => '🇺🇸', 'iso' => 'US', 'is_international' => false],
        'us'             => ['name' => 'United States',  'flag' => '🇺🇸', 'iso' => 'US', 'is_international' => false],
        'japan'          => ['name' => 'Japan',          'flag' => '🇯🇵', 'iso' => 'JP', 'is_international' => false],
        'italy'          => ['name' => 'Italy',          'flag' => '🇮🇹', 'iso' => 'IT', 'is_international' => false],
        'germany'        => ['name' => 'Germany',        'flag' => '🇩🇪', 'iso' => 'DE', 'is_international' => false],
        'france'         => ['name' => 'France',         'flag' => '🇫🇷', 'iso' => 'FR', 'is_international' => false],
        'uk'             => ['name' => 'United Kingdom', 'flag' => '🇬🇧', 'iso' => 'GB', 'is_international' => false],
        'united kingdom' => ['name' => 'United Kingdom', 'flag' => '🇬🇧', 'iso' => 'GB', 'is_international' => false],
        'great britain'  => ['name' => 'United Kingdom', 'flag' => '🇬🇧', 'iso' => 'GB', 'is_international' => false],
        'south korea'    => ['name' => 'South Korea',    'flag' => '🇰🇷', 'iso' => 'KR', 'is_international' => false],
        'korea'          => ['name' => 'South Korea',    'flag' => '🇰🇷', 'iso' => 'KR', 'is_international' => false],
        'spain'          => ['name' => 'Spain',          'flag' => '🇪🇸', 'iso' => 'ES', 'is_international' => false],
        'austria'        => ['name' => 'Austria',        'flag' => '🇦🇹', 'iso' => 'AT', 'is_international' => false],
        'sweden'         => ['name' => 'Sweden',         'flag' => '🇸🇪', 'iso' => 'SE', 'is_international' => false],
        'switzerland'    => ['name' => 'Switzerland',    'flag' => '🇨🇭', 'iso' => 'CH', 'is_international' => false],
        'thailand'       => ['name' => 'Thailand',       'flag' => '🇹🇭', 'iso' => 'TH', 'is_international' => false],
        'india'          => ['name' => 'India',          'flag' => '🇮🇳', 'iso' => 'IN', 'is_international' => false],
        'canada'         => ['name' => 'Canada',         'flag' => '🇨🇦', 'iso' => 'CA', 'is_international' => false],
        'australia'      => ['name' => 'Australia',      'flag' => '🇦🇺', 'iso' => 'AU', 'is_international' => false],
        'brazil'         => ['name' => 'Brazil',         'flag' => '🇧🇷', 'iso' => 'BR', 'is_international' => false],
    ];

    if (isset($map[$key])) {
        return $map[$key];
    }

    if ($clean === '' || $key === 'international' || $key === 'unknown' || $key === 'global') {
        return ['name' => 'International', 'flag' => '🌐', 'iso' => 'INT', 'is_international' => true];
    }

    return ['name' => ucwords($clean), 'flag' => '🌐', 'iso' => 'GLOBAL', 'is_international' => false];
}

/**
 * Clear mega menu HTML cache transients when menus or content are updated.
 */
function helmetsan_clear_mega_menu_cache(): void
{
    $languages = function_exists('pll_languages_list') ? pll_languages_list() : ['en', 'de', 'zh'];
    $types = ['helmet', 'brands', 'accessories', 'motorcycles'];
    
    foreach ($types as $type) {
        foreach ($languages as $lang) {
            delete_transient('hs_mega_menu_' . $type . '_' . $lang);
        }
    }

    foreach ($languages as $lang) {
        delete_transient('hs_homepage_counts_' . $lang);
    }

    // Automatically purge Cloudflare Edge cache if configured
    if (class_exists('Helmetsan\Core\Cloudflare\CloudflareCacheService')) {
        $cf = new \Helmetsan\Core\Cloudflare\CloudflareCacheService();
        $cf->purgeEverything();
    }
}
add_action('wp_update_nav_menu', 'helmetsan_clear_mega_menu_cache');
add_action('save_post', 'helmetsan_clear_mega_menu_cache');
add_action('edited_term', 'helmetsan_clear_mega_menu_cache');

/**
 * Active Cache Push: Asynchronously pre-warm updated posts and translations.
 */
function helmetsan_trigger_active_cache_push($postId): void
{
    if (wp_is_post_autosave($postId) || wp_is_post_revision($postId)) {
        return;
    }

    if (get_post_status($postId) !== 'publish') {
        return;
    }

    if (function_exists('helmetsan_core')) {
        $perf = helmetsan_core()->config()->performanceConfig();
        if (!empty($perf['enable_active_cache_push'])) {
            helmetsan_core()->cacheWarming()->queuePostWarming((int) $postId);
        }
    }
}
add_action('save_post', 'helmetsan_trigger_active_cache_push', 20);

/**
 * Active Cache Push: Asynchronously pre-warm homepage caches when menus or terms change.
 */
function helmetsan_trigger_general_cache_push(): void
{
    if (function_exists('helmetsan_core')) {
        $perf = helmetsan_core()->config()->performanceConfig();
        if (!empty($perf['enable_active_cache_push'])) {
            helmetsan_core()->cacheWarming()->queueHomepageWarming();
        }
    }
}
add_action('wp_update_nav_menu', 'helmetsan_trigger_general_cache_push', 20);
add_action('edited_term', 'helmetsan_trigger_general_cache_push', 20);

/**
 * Resolve optimal image URL format (AVIF -> WebP -> PNG/JPG) for high-performance delivery.
 */
function helmetsan_theme_resolve_image_url(string $relPath): string
{
    $themeDir = get_stylesheet_directory_uri();
    $themePath = get_stylesheet_directory();
    
    $cleanRel = '/' . ltrim($relPath, '/');
    $pathWithoutExt = preg_replace('/\.(png|jpe?g|webp|avif)$/i', '', $cleanRel);
    
    if (file_exists($themePath . $pathWithoutExt . '.avif')) {
        return $themeDir . $pathWithoutExt . '.avif';
    }
    if (file_exists($themePath . $pathWithoutExt . '.webp')) {
        return $themeDir . $pathWithoutExt . '.webp';
    }
    if (file_exists($themePath . $cleanRel)) {
        return $themeDir . $cleanRel;
    }
    return $themeDir . $cleanRel;
}

