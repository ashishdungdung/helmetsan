<?php
/**
 * Merge duplicate/fragmented brand posts into canonical brand entities.
 * Usage: wp eval-file scripts/merge_duplicate_brands.php --allow-root
 */

if (!defined('ABSPATH')) {
    exit('Direct access not permitted.');
}

global $wpdb;

echo "=== Consolidating & Merging Duplicate Brand Posts ===\n\n";

$merges = [
    // [staleId, canonicalId, canonicalTitle]
    [14600, 28724, 'Apex'],
    [12949, 29658, 'Bering'],
    [12547, 29828, 'DMD'],
    [12898, 29969, 'Hawk'],
    [13000, 30490, 'Macna'],
    [786, 24, 'Scorpion EXO'],
    [12682, 31448, 'Spada'],
    [28468, 247, '6D'],
    [31386, 26, 'SMK'],
    [30776, 4119, 'OGK Kabuto'],
    [30515, 12477, 'Moose Racing'],
    [31463, 277, 'Speed and Strength'],
    [31847, 279, 'Troy Lee Designs'],
    [30243, 2920, 'Joe Rocket'],
];

$metaKeysToCopy = [
    'brand_support_url',
    'brand_support_email',
    'brand_origin_country',
    'brand_founded_year',
    'brand_manufacturing_ethos',
    'brand_motto',
    'brand_story',
    'brand_certification_coverage',
    'brand_helmet_types',
    '_helmetsan_logo_url',
    '_helmetsan_logo_attachment_id',
    '_thumbnail_id',
];

foreach ($merges as $mergeDef) {
    [$staleId, $canonicalId, $canonicalTitle] = $mergeDef;

    $stalePost = get_post($staleId);
    $canonicalPost = get_post($canonicalId);

    if (!$stalePost || !$canonicalPost) {
        echo "⚠️ Skipping pair [{$staleId} -> {$canonicalId}]: one or both posts not found.\n";
        continue;
    }

    echo "Processing '{$stalePost->post_title}' (#{$staleId}) -> '{$canonicalPost->post_title}' (#{$canonicalId})...\n";

    // 1. Copy missing metadata from stale to canonical
    foreach ($metaKeysToCopy as $key) {
        $staleVal = get_post_meta($staleId, $key, true);
        $canonVal = get_post_meta($canonicalId, $key, true);

        if (!empty($staleVal) && (empty($canonVal) || $canonVal === 'http://helmetsan.com/wp-content/plugins/helmetsan-core/assets/defaults/svg/brand-badge.svg')) {
            update_post_meta($canonicalId, $key, $staleVal);
            echo "   Copied meta '{$key}'\n";
        }
    }

    // 2. Reassign all helmet rel_brand meta from staleId to canonicalId
    $updatedHelmets = $wpdb->query($wpdb->prepare(
        "UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE meta_key = 'rel_brand' AND meta_value = %s",
        (string) $canonicalId,
        (string) $staleId
    ));
    echo "   Reassigned {$updatedHelmets} helmet rel_brand pointers.\n";

    // 3. Ensure canonical post has clean title
    if (!empty($canonicalTitle) && $canonicalPost->post_title !== $canonicalTitle) {
        wp_update_post([
            'ID' => $canonicalId,
            'post_title' => $canonicalTitle,
        ]);
        echo "   Updated title to '{$canonicalTitle}'.\n";
    }

    // 4. Trash stale post
    wp_update_post([
        'ID' => $staleId,
        'post_status' => 'trash',
    ]);
    echo "   Trashed stale post #{$staleId}.\n";

    // 5. Invalidate caches
    wp_cache_delete('helmetsan_brand_count_' . $staleId);
    wp_cache_delete('helmetsan_brand_count_' . $canonicalId);
}

echo "\nFlushing WordPress Object Cache...\n";
wp_cache_flush();

echo "✅ All duplicate brand entities successfully merged!\n";
