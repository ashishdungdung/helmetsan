<?php
/**
 * Seed Accessory Categories & Fix Page Config
 */

require_once 'wp-load.php';

$categories = [
    'Visors & Shields' => 'Premium optical-grade shields, anti-fog inserts, and adaptive tinting solutions.',
    'Communications' => 'Integrated and universal Bluetooth systems, mesh intercoms, and high-fidelity audio.',
    'Maintenance & Care' => 'Specialized cleaners, anti-microbial treatments, and protective wax for shell longevity.',
    'Electronics' => 'Integrated lighting, backup batteries, and smart dashcam integrations.',
    'Inner Liners' => 'Replacement comfort liners, cheek pads, and moisture-wicking headliners.',
];

foreach ($categories as $name => $desc) {
    if (!term_exists($name, 'accessory_category')) {
        wp_insert_term($name, 'accessory_category', ['description' => $desc]);
        echo "Created category: $name\n";
    }
}

// Fix Accessory Page Source
$page = get_page_by_path('accessory');
if ($page) {
    update_post_meta($page->ID, 'hs_hub_source', 'accessory_category');
    update_post_meta($page->ID, 'hs_hub_type', 'taxonomy');
    echo "Fixed meta for page: accessory\n";
}

$page_acc = get_page_by_path('accessories');
if ($page_acc) {
    update_post_meta($page_acc->ID, '_wp_page_template', 'page-hub.php');
    update_post_meta($page_acc->ID, 'hs_hub_source', 'accessory_category');
    update_post_meta($page_acc->ID, 'hs_hub_type', 'taxonomy');
    echo "Fixed meta for page: accessories\n";
}

echo "Seeding and config fix complete.\n";
