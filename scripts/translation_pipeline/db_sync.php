<?php
/**
 * Helmetsan Multilingual Database Synchronization Worker
 * 
 * Ingests translated payloads, creates/updates localized posts, 
 * clones essential meta/taxonomies, and binds Polylang translation clusters.
 * 
 * Usage: wp eval-file db_sync.php --allow-root <json_input_file>
 */

if (!defined('ABSPATH')) {
    require_once dirname(__DIR__, 2) . '/public/wp-load.php';
}

if (!function_exists('pll_set_post_language') || !function_exists('pll_save_post_translations')) {
    echo json_encode(['error' => 'Polylang functions not available']);
    exit(1);
}

// Read input payload: In WP-CLI eval-file, positional args are passed in $args
$payload_file = null;
if (isset($args) && is_array($args) && !empty($args)) {
    $payload_file = $args[0];
} elseif (isset($argv) && is_array($argv) && count($argv) > 1) {
    $payload_file = $argv[1];
}

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

$source_id = intval($data['source_post_id']);
$source_post = get_post($source_id);

if (!$source_post) {
    echo json_encode(['error' => "Source post ID $source_id does not exist"]);
    exit(1);
}

$post_type = $source_post->post_type;
$source_lang = pll_get_post_language($source_id) ?: 'en';
pll_set_post_language($source_id, $source_lang);

// Get existing translation group
$translations = pll_get_post_translations($source_id);
if (!is_array($translations)) {
    $translations = [$source_lang => $source_id];
}

$results = ['source_id' => $source_id, 'post_type' => $post_type, 'synced' => []];

// Keys to always copy from source if not translated
$meta_whitelist_copy = [
    '_thumbnail_id',
    'brand_logo_id',
    'brand_banner_id',
    'brand_country_origin',
    'brand_established_year',
    'brand_official_website',
    'brand_parent_company',
    'price_usd',
    'price_eur',
    'price_gbp',
    'price_inr',
    'price_jpy',
    'pricing_json',
    'power_hp',
    'torque_nm',
    'engine_cc',
    'curb_weight_kg',
    'riding_position',
    'recommended_helmet_types_json'
];

foreach ($data['translations'] as $target_lang => $trans) {
    $existing_id = $translations[$target_lang] ?? pll_get_post($source_id, $target_lang);

    $post_title = $trans['post_title'] ?? $source_post->post_title;
    $post_content = $trans['post_content'] ?? $source_post->post_content;
    $post_excerpt = $trans['post_excerpt'] ?? $source_post->post_excerpt;
    $post_name = $source_post->post_name; // Keep clean canonical slug

    $post_data = [
        'post_title'   => $post_title,
        'post_name'    => $post_name,
        'post_content' => $post_content,
        'post_excerpt' => $post_excerpt,
        'post_status'  => 'publish',
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
        $results['synced'][$target_lang] = ['status' => 'error', 'message' => is_wp_error($target_id) ? $target_id->get_error_message() : 'Failed to save post'];
        continue;
    }

    // Set Polylang language
    pll_set_post_language($target_id, $target_lang);
    $translations[$target_lang] = $target_id;

    // Copy non-translated metadata from source post
    foreach ($meta_whitelist_copy as $m_key) {
        $val = get_post_meta($source_id, $m_key, true);
        if ($val !== '' && $val !== false && $val !== null) {
            update_post_meta($target_id, $m_key, $val);
        }
    }

    // Update translated metadata
    if (!empty($trans['meta']) && is_array($trans['meta'])) {
        foreach ($trans['meta'] as $meta_k => $meta_v) {
            update_post_meta($target_id, $meta_k, $meta_v);
        }
    }

    $results['synced'][$target_lang] = ['status' => 'success', 'post_id' => $target_id];
}

// Bind all translations into Polylang cluster
pll_save_post_translations($translations);

// Output clean JSON
echo json_encode([
    'success' => true,
    'source_id' => $source_id,
    'translations_cluster' => $translations,
    'details' => $results
]);
