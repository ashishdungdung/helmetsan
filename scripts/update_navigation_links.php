<?php
require_once 'wp-load.php';

$mappings = [
    'accessories' => 'accessory',
    'brands' => 'brand',
    'safety-standards' => 'safety_standard',
    'motorcycles' => 'motorcycle',
    'dealers' => 'dealer',
];

$menus = wp_get_nav_menus();
foreach ($menus as $menu) {
    $items = wp_get_nav_menu_items($menu->term_id);
    foreach ($items as $item) {
        $slug = basename(untrailingslashit($item->url));
        if (isset($mappings[$slug])) {
            $new_url = get_post_type_archive_link($mappings[$slug]);
            if ($new_url) {
                echo "Updating menu item '{$item->title}' from '{$item->url}' to '$new_url'\n";
                update_post_meta($item->ID, '_menu_item_url', $new_url);
            }
        }
    }
}

echo "Navigation links updated.\n";
