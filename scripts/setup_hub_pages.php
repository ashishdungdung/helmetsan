<?php
/**
 * Setup Hub Pages Script
 */

require_once 'wp-load.php';

$hubs = [
    [
        'title' => 'Accessory Discovery',
        'slug'  => 'accessory',
        'type'  => 'taxonomy',
        'source' => 'accessory_category',
        'hero' => 'accessories_hub_hero'
    ],
    [
        'title' => 'Safety Standards',
        'slug'  => 'safety-standards',
        'type'  => 'post_type',
        'source' => 'safety_standard',
        'hero' => 'safety_standards_hub_hero'
    ],
    [
        'title' => 'Brand Directory',
        'slug'  => 'brands',
        'type'  => 'post_type',
        'source' => 'brand',
        'hero' => 'brands_hub_hero'
    ],
    [
        'title' => 'Motorcycle Compatibility',
        'slug'  => 'motorcycles',
        'type'  => 'post_type',
        'source' => 'motorcycle',
        'hero' => 'motorcycles_hub_hero'
    ],
    [
        'title' => 'Authorized Dealers',
        'slug'  => 'dealers',
        'type'  => 'post_type',
        'source' => 'dealer',
        'hero' => 'dealers_hub_hero'
    ],
    [
        'title' => 'Helmet Technologies',
        'slug'  => 'technologies',
        'type'  => 'post_type',
        'source' => 'technology',
        'hero' => 'technologies_hub_hero'
    ],
    [
        'title' => 'Certification Marks',
        'slug'  => 'certification-marks',
        'type'  => 'taxonomy',
        'source' => 'certification',
        'hero' => 'certification_marks_hub_hero'
    ],
    [
        'title' => 'Riding Regions',
        'slug'  => 'regions',
        'type'  => 'taxonomy',
        'source' => 'region',
        'hero' => 'regions_hub_hero'
    ],
    [
        'title' => 'Feature Index',
        'slug'  => 'feature-tags',
        'type'  => 'taxonomy',
        'source' => 'feature_tag',
        'hero' => 'feature_tags_hub_hero'
    ],
    [
        'title' => 'Distributors',
        'slug'  => 'distributors',
        'type'  => 'post_type',
        'source' => 'distributor',
        'hero' => 'distributors_hub_hero'
    ]
];

foreach ($hubs as $hub) {
    $page = get_page_by_path($hub['slug']);
    $page_id = 0;
    
    if (!$page) {
        $page_id = wp_insert_post([
            'post_title' => $hub['title'],
            'post_name'  => $hub['slug'],
            'post_type'  => 'page',
            'post_status' => 'publish'
        ]);
        echo "Created page: {$hub['title']}\n";
    } else {
        $page_id = $page->ID;
        echo "Updating existing page: {$hub['title']}\n";
    }

    if ($page_id) {
        update_post_meta($page_id, '_wp_page_template', 'page-hub.php');
        update_post_meta($page_id, 'hs_hub_type', $hub['type']);
        update_post_meta($page_id, 'hs_hub_source', $hub['source']);
        
        // Hub Hero image mapping
        $themeDir = get_stylesheet_directory_uri();
        update_post_meta($page_id, 'hs_hub_hero', $themeDir . '/assets/images/hubs/' . $hub['hero'] . '.png');
    }
}

echo "Hub pages setup complete.\n";
