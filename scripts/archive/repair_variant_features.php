<?php

/**
 * Script to forcefully push 'features_json' from parent helmets down to their variants.
 */

require_once 'wp-load.php';

echo "Starting variant feature sync...\n";

$variants = get_posts([
    'post_type'      => 'helmet',
    'post_status'    => 'any',
    'posts_per_page' => -1,
    'post_parent__not_in' => [0], // Only variants (have a parent)
]);

$updated = 0;
$skipped = 0;

foreach ($variants as $variant) {
    $parentId = $variant->post_parent;

    // Check if variant already has features_json
    $childFeatures = get_post_meta($variant->ID, 'features_json', true);

    // If it's literally missing or empty string, we want to copy from parent
    if (empty($childFeatures)) {
        $parentFeatures = get_post_meta($parentId, 'features_json', true);

        if (!empty($parentFeatures)) {
            update_post_meta($variant->ID, 'features_json', $parentFeatures);
            $updated++;
            echo "Updated variant {$variant->ID} from parent {$parentId}\n";
        } else {
            $skipped++;
        }
    } else {
        $skipped++;
    }
}

echo "\nDone. Updated $updated variants. Skipped $skipped.\n";
