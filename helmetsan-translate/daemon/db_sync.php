<?php
/**
 * Helmetsan Translate - Background Database Synchronization Bridge
 * 
 * Synchronizes translated payloads into MariaDB with Polylang cluster bindings,
 * taxonomy mapping, Yoast SEO sync, media linking, and edge cache invalidation.
 */

declare(strict_types=1);

if (!defined('ABSPATH')) {
    require_once dirname(__DIR__, 4) . '/wp-load.php';
}

if (!function_exists('pll_set_post_language') || !function_exists('pll_save_post_translations')) {
    echo json_encode(['error' => 'Polylang functions not available']);
    exit(1);
}

// Read input payload
$payload_file = $args[0] ?? ($argv[1] ?? null);
if (!$payload_file || !file_exists($payload_file)) {
    echo json_encode(['error' => "Payload file not found: " . var_export($payload_file, true)]);
    exit(1);
}

$raw = file_get_contents($payload_file);
$data = json_decode($raw, true);

if (!$data || empty($data['source_post_id']) || empty($data['translations'])) {
    echo json_encode(['error' => 'Invalid JSON payload structure']);
    exit(1);
}

$source_id = (int) $data['source_post_id'];
$source_post = get_post($source_id);

if (!$source_post) {
    echo json_encode(['error' => "Source post ID $source_id does not exist"]);
    exit(1);
}

$post_type = $source_post->post_type;
$source_lang = pll_get_post_language($source_id, 'slug') ?: 'en';
pll_set_post_language($source_id, $source_lang);

// Get existing translation group
$translations = pll_get_post_translations($source_id) ?: [];
$translations[$source_lang] = $source_id;

$options = HelmetsanTranslate\Settings::get_options();
$meta_copy_as_is = array_filter(array_map('trim', explode(',', $options['seo']['meta_copy_as_is'] ?? '')));
$default_copy = ['_thumbnail_id', '_hs_helmet_price', '_hs_weight_grams', 'brand_logo_id', 'brand_banner_id', 'price_usd', 'price_eur', 'price_gbp'];
$all_copy = array_unique(array_merge($default_copy, $meta_copy_as_is));

$results = ['success' => true, 'source_id' => $source_id, 'synced' => []];

foreach ($data['translations'] as $target_lang => $trans) {
    $existing_id = $translations[$target_lang] ?? pll_get_post($source_id, $target_lang);

    $post_title = $trans['title'] ?? $trans['post_title'] ?? $source_post->post_title;
    $raw_content = $trans['content'] ?? $trans['post_content'] ?? $source_post->post_content;
    $post_excerpt = $trans['excerpt'] ?? $trans['post_excerpt'] ?? $source_post->post_excerpt;

    // Content Normalization (internal link prefixing + affiliate preservation)
    $post_content = HelmetsanTranslate\ContentNormalizer::normalize($raw_content, $target_lang);

    // Slug policy: smart (canonical for helmets/bikes/brands, translated for blog/pages)
    if (in_array($post_type, ['post', 'page', 'safety_standard'], true) && !empty($trans['slug'])) {
        $post_name = sanitize_title($trans['slug']);
    } else {
        $post_name = $source_post->post_name;
    }

    $post_data = [
        'post_title'   => $post_title,
        'post_name'    => $post_name,
        'post_content' => $post_content,
        'post_excerpt' => $post_excerpt,
        'post_status'  => $source_post->post_status, // Sync draft/publish status
        'post_type'    => $post_type,
        'post_author'  => $source_post->post_author
    ];

    if ($existing_id && get_post($existing_id)) {
        $post_data['ID'] = $existing_id;
        $target_id = wp_update_post($post_data);
    } else {
        $target_id = wp_insert_post($post_data);
    }

    if (is_wp_error($target_id) || !$target_id) {
        $results['synced'][$target_lang] = [
            'status' => 'error',
            'message' => is_wp_error($target_id) ? $target_id->get_error_message() : 'Failed to save post'
        ];
        continue;
    }

    // Bind Polylang language
    pll_set_post_language($target_id, $target_lang);
    $translations[$target_lang] = $target_id;

    // Copy non-translated metadata from source post
    foreach ($all_copy as $m_key) {
        $val = get_post_meta($source_id, $m_key, true);
        if ($val !== '' && $val !== false && $val !== null) {
            update_post_meta($target_id, $m_key, $val);
        }
    }

    // Sync featured image
    $thumb_id = get_post_thumbnail_id($source_id);
    if ($thumb_id) {
        set_post_thumbnail($target_id, $thumb_id);
    }

    // Sync taxonomies with hierarchy preservation
    HelmetsanTranslate\TaxonomyMapper::sync_post_taxonomies($source_id, $target_id, $target_lang);

    // Sync Yoast SEO metadata
    $seo_title = $trans['seo_title'] ?? $trans['meta']['_yoast_wpseo_title'] ?? '';
    $seo_desc  = $trans['seo_desc'] ?? $trans['meta']['_yoast_wpseo_metadesc'] ?? '';
    HelmetsanTranslate\SeoIntegrator::sync_meta($target_id, $seo_title, $seo_desc);

    $results['synced'][$target_lang] = ['status' => 'success', 'post_id' => $target_id];
}

// Atomically save Polylang translations cluster
pll_save_post_translations($translations);

// Purge edge cache
$urls_to_purge = [];
foreach ($translations as $l => $pid) {
    $perm = get_permalink($pid);
    if ($perm) $urls_to_purge[] = $perm;
}
HelmetsanTranslate\CachePurger::purge_urls($urls_to_purge);

// Ping IndexNow
HelmetsanTranslate\SeoIntegrator::ping_indexnow($urls_to_purge);

echo json_encode($results);
