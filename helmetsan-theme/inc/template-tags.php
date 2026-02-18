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

function helmetsan_get_brand_id(int $helmetId): int
{
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

function helmetsan_get_helmet_price(int $helmetId): string
{
    $price = get_post_meta($helmetId, 'price_retail_usd', true);
    if (! is_numeric((string) $price)) {
        return 'N/A';
    }

    return '$' . number_format((float) $price, 2);
}

function helmetsan_get_certifications(int $helmetId): string
{
    $terms = get_the_terms($helmetId, 'certification');
    if (! is_array($terms) || $terms === []) {
        return 'N/A';
    }

    $names = array_map(static fn ($term): string => (string) $term->name, $terms);
    return implode(', ', $names);
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
            return (string) get_post_meta($brandId, '_helmetsan_logo_url', true);
        }
    }

    return '';
}

/**
 * @return array<string,mixed>
 */
function helmetsan_get_helmet_mega_menu_data(): array
{
    $paths = [
        WP_CONTENT_DIR . '/uploads/helmetsan-data/catalogs/helmet-mega-menu.json',
        get_stylesheet_directory() . '/data/helmet-mega-menu.json',
        ABSPATH . '../data/catalogs/helmet-mega-menu.json',
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

function helmetsan_mega_menu_item_url(string $label, string $heading): string
{
    $label = trim($label);
    $heading = trim($heading);
    $helmetsArchive = (string) get_post_type_archive_link('helmet');
    if ($helmetsArchive === '') {
        $helmetsArchive = (string) home_url('/helmets/');
    }
    if ($label === '') {
        return $helmetsArchive;
    }

    if (stripos($heading, 'Brand') !== false) {
        return (string) add_query_arg(['brand_slug' => sanitize_title($label)], $helmetsArchive);
    }

    if (stripos($heading, 'Type') !== false || helmetsan_is_type_label($label)) {
        $slug = helmetsan_find_term_slug_by_label('helmet_type', $label);
        return (string) add_query_arg(['helmet_type' => $slug], $helmetsArchive);
    }

    if (stripos($heading, 'Safety') !== false || helmetsan_is_certification_label($label)) {
        $slug = helmetsan_find_term_slug_by_label('certification', $label);
        return (string) add_query_arg(['certification' => $slug], $helmetsArchive);
    }

    if (stripos($heading, 'Model') !== false || str_contains(strtolower($label), 'rf1400') || str_contains(strtolower($label), 'pista')) {
        return (string) add_query_arg(['helmet_family' => $label], $helmetsArchive);
    }

    if (stripos($heading, 'Style') !== false || stripos($heading, 'Feature') !== false || stripos($heading, 'Riding') !== false) {
        $slug = helmetsan_find_term_slug_by_label('feature_tag', $label);
        return (string) add_query_arg(['feature' => $slug], $helmetsArchive);
    }

    return (string) add_query_arg(['feature' => helmetsan_find_term_slug_by_label('feature_tag', $label)], $helmetsArchive);
}

function helmetsan_render_helmet_mega_menu(): void
{
    $menu = helmetsan_get_helmet_mega_menu_data();
    if ($menu === []) {
        return;
    }

    $columns = isset($menu['columns']) && is_array($menu['columns']) ? $menu['columns'] : [];
    $highlights = isset($menu['highlight_blocks']) && is_array($menu['highlight_blocks']) ? $menu['highlight_blocks'] : [];
    $families = isset($menu['family_examples']) && is_array($menu['family_examples']) ? $menu['family_examples'] : [];
    if ($columns === []) {
        return;
    }

    $brandsFooterUrl = (string) get_post_type_archive_link('brand');
    if ($brandsFooterUrl === '') {
        $brandsFooterUrl = (string) home_url('/brands/');
    }

    echo '<section class="hs-mega-menu" aria-label="Helmet mega menu">';
    echo '<div class="hs-mega-menu__inner">';
    echo '<div class="hs-mega-menu__grid">';
    foreach ($columns as $column) {
        if (! is_array($column)) {
            continue;
        }
        $heading = isset($column['heading']) ? (string) $column['heading'] : '';
        $items = isset($column['items']) && is_array($column['items']) ? $column['items'] : [];
        if ($heading === '') {
            continue;
        }
        echo '<article class="hs-mega-menu__col">';
        echo '<h3>' . esc_html($heading) . '</h3>';
        echo '<ul>';
        foreach ($items as $item) {
            $label = sanitize_text_field((string) $item);
            if ($label === '') {
                continue;
            }
            $url = helmetsan_mega_menu_item_url($label, $heading);
            echo '<li><a href="' . esc_url($url) . '">' . esc_html($label) . '</a></li>';
        }
        echo '</ul>';
        if (isset($column['footer']) && (string) $column['footer'] !== '') {
            $footerLabel = sanitize_text_field((string) $column['footer']);
            echo '<p class="hs-mega-menu__footer"><a href="' . esc_url($brandsFooterUrl) . '">' . esc_html($footerLabel) . '</a></p>';
        }
        echo '</article>';
    }
    echo '</div>';

    if ($highlights !== [] || $families !== []) {
        echo '<div class="hs-mega-menu__bottom">';
        foreach ($highlights as $block) {
            if (! is_array($block)) {
                continue;
            }
            $heading = isset($block['heading']) ? (string) $block['heading'] : '';
            $items = isset($block['items']) && is_array($block['items']) ? $block['items'] : [];
            if ($heading === '') {
                continue;
            }
            echo '<div class="hs-mega-menu__highlight">';
            echo '<strong>' . esc_html($heading) . '</strong>';
            if ($items !== []) {
                echo '<p>';
                foreach ($items as $idx => $item) {
                    $label = sanitize_text_field((string) $item);
                    if ($label === '') {
                        continue;
                    }
                    $url = (stripos($heading, 'Accessory') !== false)
                        ? add_query_arg(['accessory_category' => helmetsan_find_term_slug_by_label('accessory_category', $label)], (string) get_post_type_archive_link('accessory'))
                        : ((stripos($heading, 'Sale') !== false)
                            ? add_query_arg(['feature' => helmetsan_find_term_slug_by_label('feature_tag', 'Sale')], (string) get_post_type_archive_link('helmet'))
                            : helmetsan_mega_menu_item_url($label, 'Features & Style'));
                    if ($idx > 0) {
                        echo ' <span aria-hidden="true">·</span> ';
                    }
                    echo '<a href="' . esc_url((string) $url) . '">' . esc_html($label) . '</a>';
                }
                echo '</p>';
            }
            echo '</div>';
        }
        if ($families !== []) {
            echo '<div class="hs-mega-menu__families">';
            echo '<strong>Helmet Families</strong>';
            echo '<p>' . esc_html(implode(' · ', array_map('strval', $families))) . '</p>';
            echo '</div>';
        }
        echo '</div>';
    }

    echo '<div class="hs-mega-menu-mobile">';
    echo '<h3 class="hs-mega-menu-mobile__title">Helmets</h3>';
    foreach ($columns as $column) {
        if (! is_array($column)) {
            continue;
        }
        $heading = isset($column['heading']) ? (string) $column['heading'] : '';
        $items = isset($column['items']) && is_array($column['items']) ? $column['items'] : [];
        if ($heading === '') {
            continue;
        }
        echo '<details class="hs-mobile-nav-group">';
        echo '<summary>' . esc_html($heading) . '</summary>';
        echo '<ul>';
        foreach ($items as $item) {
            $label = sanitize_text_field((string) $item);
            if ($label === '') {
                continue;
            }
            $url = helmetsan_mega_menu_item_url($label, $heading);
            echo '<li><a href="' . esc_url($url) . '">' . esc_html($label) . '</a></li>';
        }
        if (isset($column['footer']) && (string) $column['footer'] !== '') {
            $footerLabel = sanitize_text_field((string) $column['footer']);
            echo '<li><a href="' . esc_url($brandsFooterUrl) . '">' . esc_html($footerLabel) . '</a></li>';
        }
        echo '</ul>';
        echo '</details>';
    }
    if ($highlights !== []) {
        foreach ($highlights as $block) {
            if (! is_array($block)) {
                continue;
            }
            $heading = isset($block['heading']) ? (string) $block['heading'] : '';
            $items = isset($block['items']) && is_array($block['items']) ? $block['items'] : [];
            if ($heading === '') {
                continue;
            }
            echo '<details class="hs-mobile-nav-group">';
            echo '<summary>' . esc_html($heading) . '</summary>';
            echo '<ul>';
            foreach ($items as $item) {
                $label = sanitize_text_field((string) $item);
                if ($label === '') {
                    continue;
                }
                $url = (stripos($heading, 'Accessory') !== false)
                    ? add_query_arg(['accessory_category' => helmetsan_find_term_slug_by_label('accessory_category', $label)], (string) get_post_type_archive_link('accessory'))
                    : add_query_arg(['feature' => helmetsan_find_term_slug_by_label('feature_tag', 'Sale')], (string) get_post_type_archive_link('helmet'));
                echo '<li><a href="' . esc_url((string) $url) . '">' . esc_html($label) . '</a></li>';
            }
            echo '</ul>';
            echo '</details>';
        }
    }
    if ($families !== []) {
        echo '<details class="hs-mobile-nav-group">';
        echo '<summary>Helmet Models</summary><ul>';
        foreach ($families as $family) {
            $label = sanitize_text_field((string) $family);
            if ($label === '') {
                continue;
            }
            $url = add_query_arg(['helmet_family' => $label], (string) get_post_type_archive_link('helmet'));
            echo '<li><a href="' . esc_url((string) $url) . '">' . esc_html($label) . '</a></li>';
        }
        echo '</ul></details>';
    }
    echo '</div>';

    echo '</div>';
    echo '</section>';
}
