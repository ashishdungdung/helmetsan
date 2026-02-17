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
    $url = (string) get_post_meta($postId, '_helmetsan_logo_url', true);
    if ($url !== '') {
        return $url;
    }

    $postType = get_post_type($postId);
    if ($postType === 'helmet') {
        $brandId = helmetsan_get_brand_id($postId);
        if ($brandId > 0) {
            return (string) get_post_meta($brandId, '_helmetsan_logo_url', true);
        }
    }

    return '';
}
