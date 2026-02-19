<?php
require_once 'wp-load.php';

$pages_to_delete = [
    'accessory', // ID 430 (Conflict)
    'accessories', // ID 103 (Redundant)
    'brands', // ID 101 (Redundant)
    'safety-standards', // ID 105 (Redundant)
    'motorcycles', // ID 104 (Redundant)
    'dealers', // ID 106 (Redundant)
    'technologies', // ID 431
    'certification-marks', // ID 432
    'regions', // ID 433
    'feature-tags', // ID 434
    'distributors', // ID 435
];

foreach ($pages_to_delete as $slug) {
    $page = get_page_by_path($slug);
    if ($page) {
        echo "Deleting page: $slug (ID: {$page->ID})\n";
        wp_delete_post($page->ID, true);
    } else {
        echo "Page not found: $slug\n";
    }
}

// Flush rewrite rules
flush_rewrite_rules();
echo "Rewrite rules flushed.\n";
