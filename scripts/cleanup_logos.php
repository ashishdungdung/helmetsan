<?php
/**
 * Cleanup Brand Logos
 * Detaches and deletes featured images from Brand posts so they can be re-seeded.
 */

$args = [
    'post_type'      => 'brand',
    'posts_per_page' => -1,
    'fields'         => 'ids',
];

$brand_ids = get_posts($args);

echo "🗑️  Cleaning up " . count($brand_ids) . " brand logos...\n";

foreach ($brand_ids as $brand_id) {
    $thumb_id = get_post_thumbnail_id($brand_id);
    
    if ($thumb_id) {
        // Delete the attachment (this deletes the file from disk too)
        $deleted = wp_delete_attachment($thumb_id, true);
        if ($deleted) {
            echo "✅ Deleted logo for Brand $brand_id (Attach ID: $thumb_id)\n";
        } else {
            echo "❌ Failed to delete logo for Brand $brand_id\n";
        }
    } else {
        echo "Example: Brand $brand_id has no logo.\n";
    }
}

echo "🎉 Cleanup Complete.\n";
