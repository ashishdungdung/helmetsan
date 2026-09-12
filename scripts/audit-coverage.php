<?php
/**
 * Script to audit helmet data coverage for the new technical fields.
 */
require_once __DIR__ . '/../wp-load.php';

if (php_sapi_name() !== 'cli') {
    die("This script must be run from CLI.\n");
}

global $wpdb;

$fields = [
    'sharp_rating',
    'homologation_standard',
    'noise_db_at_100kph',
    'ventilation_score',
    'comms_ready',
    'strap_type',
    'spec_weight_g',
    'spec_shell_material'
];

$total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'helmet' AND post_status = 'publish'");

echo "Total Helmets: $total\n";
echo str_repeat('-', 60) . "\n";
echo sprintf("%-25s | %-10s | %-10s\n", "Field", "Count", "Coverage %");
echo str_repeat('-', 60) . "\n";

foreach ($fields as $field) {
    $count = (int) $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM {$wpdb->postmeta} pm 
         JOIN {$wpdb->posts} p ON p.ID = pm.post_id 
         WHERE p.post_type = 'helmet' AND p.post_status = 'publish' 
         AND pm.meta_key = %s AND pm.meta_value != '' AND pm.meta_value != '[]' AND pm.meta_value != 'N/A'",
        $field
    ));
    
    $percentage = $total > 0 ? ($count / $total) * 100 : 0;
    echo sprintf("%-25s | %-10d | %-10.1f%%\n", $field, $count, $percentage);
}

// Brand-wise Sparsity
echo "\nTop 5 Brands by Data Sparsity (missing SHARP rating):\n";
echo str_repeat('-', 60) . "\n";

$brands = $wpdb->get_results("
    SELECT pm.meta_value as brand_id, COUNT(p.ID) as helmet_count 
    FROM {$wpdb->posts} p
    JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = 'rel_brand'
    WHERE p.post_type = 'helmet' AND p.post_status = 'publish'
    GROUP BY pm.meta_value
    ORDER BY helmet_count DESC
    LIMIT 20
");

foreach ($brands as $brand) {
    $brand_id = (int) $brand->brand_id;
    if ($brand_id <= 0) continue;
    
    $brand_name = get_the_title($brand_id);
    
    $missing_sharp = (int) $wpdb->get_var($wpdb->prepare("
        SELECT COUNT(p.ID) FROM {$wpdb->posts} p
        JOIN {$wpdb->postmeta} pm_brand ON p.ID = pm_brand.post_id AND pm_brand.meta_key = 'rel_brand' AND pm_brand.meta_value = %d
        LEFT JOIN {$wpdb->postmeta} pm_sharp ON p.ID = pm_sharp.post_id AND pm_sharp.meta_key = 'sharp_rating'
        WHERE p.post_type = 'helmet' AND p.post_status = 'publish'
        AND (pm_sharp.meta_value IS NULL OR pm_sharp.meta_value = '' OR pm_sharp.meta_value = '0')
    ", $brand_id));
    
    $missing_pct = ($missing_sharp / $brand->helmet_count) * 100;
    if ($missing_pct > 50) {
        echo sprintf("%-25s | %d helmets | %.1f%% missing SHARP\n", $brand_name, $brand->helmet_count, $missing_pct);
    }
}
