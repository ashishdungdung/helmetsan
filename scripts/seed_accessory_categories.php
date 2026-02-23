<?php
/**
 * Seed Accessory Categories & Fix Page Config
 * Run: wp eval-file scripts/seed_accessory_categories.php (from repo root on server)
 *   or: php scripts/seed_accessory_categories.php (from WP public root, where wp-load.php lives)
 */

if (! defined('ABSPATH')) {
    require_once 'wp-load.php';
}

$categories = [
    // Existing / hub categories
    'Visors & Shields' => 'Premium optical-grade shields, anti-fog inserts, and adaptive tinting solutions.',
    'Communications' => 'Integrated and universal Bluetooth systems, mesh intercoms, and high-fidelity audio.',
    'Bluetooth Headsets' => 'Bluetooth helmet communication systems, headsets, and intercoms.',
    'Mesh Intercoms' => 'Mesh network intercom systems for rider-to-rider and group communication.',
    'Helmet Cameras' => 'Action cameras, dashcams, and mounts for helmet recording.',
    'Audio Kits' => 'Helmet speakers, microphones, and audio upgrade kits.',
    'GPS Navigation' => 'GPS units and mounts for motorcycle navigation.',
    'Smart Helmet Add-ons' => 'Connectivity and smart features for helmets.',
    'Maintenance & Care' => 'Specialized cleaners, anti-microbial treatments, and protective wax for shell longevity.',
    'Electronics' => 'Integrated lighting, backup batteries, and smart dashcam integrations.',
    'Inner Liners' => 'Replacement comfort liners, cheek pads, and moisture-wicking headliners.',
    // Mega-menu: Visors & Optics
    'Face Shields' => 'Full-face and modular helmet face shields and visors.',
    'Pinlock Inserts' => 'Anti-fog Pinlock lens inserts for visors.',
    'Tear-Offs' => 'Visor tear-off strips for dirt and racing.',
    'Goggles' => 'MX and open-face goggles.',
    'Replacement Lenses' => 'Replacement lenses for visors and goggles.',
    'Anti-Fog Solutions' => 'Anti-fog treatments and inserts.',
    'Sun Visors' => 'Internal sun visors and tinted options.',
    // Mega-menu: Comfort & Care
    'Cheek Pads' => 'Replacement cheek pads for fit and comfort.',
    'Liners' => 'Comfort liners and headliners.',
    'Helmet Cleaners' => 'Cleaning products for helmet shells and interiors.',
    'Visor Cleaners' => 'Cleaning solutions for visors and lenses.',
    'Helmet Bags' => 'Carry bags and storage for helmets.',
    'Balaclavas' => 'Helmet liners and balaclavas.',
    'Breath Guards' => 'Breath deflectors and guards.',
    // Mega-menu: Safety & Parts
    'Breath Boxes' => 'Breath box replacements for modular helmets.',
    'Peak Visors' => 'Peak and peak visor replacements.',
    'Replacement Vents' => 'Vent parts and replacements.',
    'Pivot Kits' => 'Visor pivot and mechanism kits.',
    'Chin Curtains' => 'Chin curtain replacements.',
    'Reflective Stickers' => 'Reflective decals and safety stickers.',
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
    update_post_meta($page_acc->ID, '_wp_page_template', 'page-accessories.php');
    echo "Fixed template for page: accessories (category cards + catalog)\n";
}

flush_rewrite_rules(false);
echo "Seeding and config fix complete.\n";
