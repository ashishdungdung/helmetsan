<?php
/**
 * Backfill size taxonomy terms for helmet posts.
 * Run via: wp eval-file scripts/backfill_size_taxonomy.php --allow-root
 */

if (!defined('ABSPATH')) {
    exit('Direct access not permitted.');
}

global $wpdb;

echo "=== Backfilling Helmet Size Taxonomy ===\n";

// Map normalized size keys to term_taxonomy_ids
$ttMap = [];
$rows = $wpdb->get_results("
    SELECT t.slug, tt.term_taxonomy_id 
    FROM {$wpdb->term_taxonomy} tt 
    JOIN {$wpdb->terms} t ON tt.term_id = t.term_id 
    WHERE tt.taxonomy = 'size'
");

foreach ($rows as $r) {
    $ttMap[$r->slug] = (int) $r->term_taxonomy_id;
}

if (empty($ttMap)) {
    exit("No size taxonomy terms found.\n");
}

echo "Size terms found: " . count($ttMap) . " (" . implode(', ', array_keys($ttMap)) . ")\n";

// Normalizer function
$normalizeSize = function (string $raw) use ($ttMap): array {
    $raw = strtolower(trim($raw));
    $matched = [];

    switch ($raw) {
        case 'xs':
        case 'extra-small':
        case 'extra small':
            if (isset($ttMap['xs'])) $matched[] = $ttMap['xs'];
            break;
        case 's':
        case 'sm':
        case 'small':
            if (isset($ttMap['s'])) $matched[] = $ttMap['s'];
            if (isset($ttMap['sm'])) $matched[] = $ttMap['sm'];
            break;
        case 'm':
        case 'md':
        case 'med':
        case 'medium':
            if (isset($ttMap['m'])) $matched[] = $ttMap['m'];
            if (isset($ttMap['md'])) $matched[] = $ttMap['md'];
            break;
        case 'l':
        case 'lg':
        case 'large':
            if (isset($ttMap['l'])) $matched[] = $ttMap['l'];
            if (isset($ttMap['lg'])) $matched[] = $ttMap['lg'];
            break;
        case 'xl':
        case 'x-large':
        case 'extra-large':
            if (isset($ttMap['xl'])) $matched[] = $ttMap['xl'];
            break;
        case '2xl':
        case 'xxl':
            if (isset($ttMap['2xl'])) $matched[] = $ttMap['2xl'];
            if (isset($ttMap['xxl'])) $matched[] = $ttMap['xxl'];
            break;
        case '3xl':
        case 'xxxl':
            if (isset($ttMap['3xl'])) $matched[] = $ttMap['3xl'];
            if (isset($ttMap['xxxl'])) $matched[] = $ttMap['xxxl'];
            break;
        case '4xl':
        case 'xxxxl':
            if (isset($ttMap['4xl'])) $matched[] = $ttMap['4xl'];
            break;
        default:
            if (isset($ttMap[$raw])) {
                $matched[] = $ttMap[$raw];
            }
            break;
    }

    return $matched;
};

// 1. Process brand_size_chart_json
echo "Processing brand_size_chart_json...\n";
$metaRows = $wpdb->get_results("
    SELECT post_id, meta_value 
    FROM {$wpdb->postmeta} 
    WHERE meta_key = 'brand_size_chart_json' AND meta_value != ''
");

$insertPairs = [];
$totalHelmets = 0;

foreach ($metaRows as $row) {
    $postId = (int) $row->post_id;
    $chart = json_decode($row->meta_value, true);
    if (!is_array($chart)) {
        continue;
    }
    $totalHelmets++;
    foreach (array_keys($chart) as $sizeKey) {
        $tts = $normalizeSize((string) $sizeKey);
        foreach ($tts as $ttId) {
            $insertPairs[] = "({$postId}, {$ttId}, 0)";
        }
    }
}

// 2. Process variants_json
echo "Processing variants_json...\n";
$variantRows = $wpdb->get_results("
    SELECT post_id, meta_value 
    FROM {$wpdb->postmeta} 
    WHERE meta_key = 'variants_json' AND meta_value != ''
");

foreach ($variantRows as $row) {
    $postId = (int) $row->post_id;
    $variants = json_decode($row->meta_value, true);
    if (!is_array($variants)) {
        continue;
    }
    foreach ($variants as $v) {
        if (!empty($v['size'])) {
            $tts = $normalizeSize((string) $v['size']);
            foreach ($tts as $ttId) {
                $insertPairs[] = "({$postId}, {$ttId}, 0)";
            }
        }
    }
}

$insertPairs = array_unique($insertPairs);
echo "Total size relationships to insert: " . count($insertPairs) . "\n";

// Batch insert in chunks of 5000
$chunkSize = 5000;
$chunks = array_chunk($insertPairs, $chunkSize);
foreach ($chunks as $idx => $chunk) {
    $valuesSql = implode(',', $chunk);
    $wpdb->query("INSERT IGNORE INTO {$wpdb->term_relationships} (object_id, term_taxonomy_id, term_order) VALUES {$valuesSql}");
    echo "  Inserted chunk " . ($idx + 1) . "/" . count($chunks) . "\n";
}

// Update term counts
echo "Updating term counts...\n";
$ttIds = array_values($ttMap);
wp_update_term_count_now($ttIds, 'size');

echo "✅ Backfill complete!\n";
