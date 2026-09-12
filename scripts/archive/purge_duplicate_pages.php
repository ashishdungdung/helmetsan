<?php
/**
 * Purge duplicate or redundant WordPress pages by slug.
 * DESTRUCTIVE: Permanently deletes posts.
 *
 * Usage (from WordPress public root):
 *   wp eval-file scripts/purge_duplicate_pages.php --allow-root
 *   wp eval-file scripts/purge_duplicate_pages.php --allow-root --dry-run  # List only, no delete
 *   DRY_RUN=1 wp eval-file scripts/purge_duplicate_pages.php --allow-root # Same (when argv not passed)
 */

$dry_run = (getenv('DRY_RUN') !== false && getenv('DRY_RUN') !== '')
    || (isset($argv) && is_array($argv) && in_array('--dry-run', $argv, true));

if (! defined('ABSPATH')) {
    require_once 'wp-load.php'; // Run from WordPress public root
}

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
        if ($dry_run) {
            echo "[DRY-RUN] Would delete page: $slug (ID: {$page->ID})\n";
        } else {
            echo "Deleting page: $slug (ID: {$page->ID})\n";
            wp_delete_post($page->ID, true);
        }
    } else {
        echo "Page not found: $slug\n";
    }
}

// Flush rewrite rules
flush_rewrite_rules();
echo "Rewrite rules flushed.\n";
