<?php

use Helmetsan\Core\Plugin;

// require_once __DIR__ . '/../wp-load.php'; // Removed for WP-CLI eval-file compatibility

$posts = get_posts([
    'post_type' => 'helmet',
    'numberposts' => -1,
    'post_status' => 'any',
    'fields' => 'ids'
]);

echo "Found " . count($posts) . " helmets to delete.\n";

foreach ($posts as $id) {
    wp_delete_post($id, true);
}

echo "All helmets deleted.\n";
