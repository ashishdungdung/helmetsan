<?php
/**
 * Migration Script: JSON to WP Menu
 *
 * Usage: wp eval-file inc/migration-json-to-menu.php
 */

function helmetsan_migrate_json_to_menu($type, $jsonFile, $menuName, $location) {
    echo "Migrating $type...\n";

    // 1. Read JSON
    $jsonPath = get_stylesheet_directory() . '/data/' . $jsonFile;
    if (! file_exists($jsonPath)) {
        echo "  Error: JSON file not found: $jsonPath\n";
        return;
    }
    
    $data = json_decode(file_get_contents($jsonPath), true);
    if (! $data || empty($data['columns'])) {
        echo "  Error: Invalid JSON data\n";
        return;
    }

    // 2. Create/Get Menu
    $menuId = wp_create_nav_menu($menuName);
    if (is_wp_error($menuId)) {
        // Menu might exist, try to get it
        $menuKey = get_term_by('name', $menuName, 'nav_menu');
        if ($menuKey) {
            $menuId = $menuKey->term_id;
            echo "  Menu exists ($menuId). Clearing items...\n";
            // Clear existing items to avoid duplicates during re-run
            $items = wp_get_nav_menu_items($menuId);
            if ($items) {
                foreach ($items as $item) {
                    wp_delete_post($item->ID, true);
                }
            }
        } else {
            echo "  Error creating menu: " . $menuId->get_error_message() . "\n";
            return;
        }
    } else {
        echo "  Created new menu: $menuName ($menuId)\n";
    }

    // 3. Assign to Location
    $locations = get_theme_mod('nav_menu_locations');
    $locations[$location] = $menuId;
    set_theme_mod('nav_menu_locations', $locations);
    echo "  Assigned to location: $location\n";

    // 4. Populate Items
    foreach ($data['columns'] as $col) {
        // Column Heading (Parent)
        $cleanHeading = str_replace('View all', '', $col['heading']);
        $cleanHeading = trim($cleanHeading);
        
        // If there is a standard 'View all' link, we might want to use it, else '#'
        $parentUrl = '#';

        $parentId = wp_update_nav_menu_item($menuId, 0, [
            'menu-item-title'   => $cleanHeading,
            'menu-item-url'     => $parentUrl,
            'menu-item-status'  => 'publish',
            'menu-item-type'    => 'custom',
        ]);

        if (is_wp_error($parentId)) {
            echo "  Error creating parent $cleanHeading\n";
            continue;
        }

        // Items
        if (! empty($col['items'])) {
            foreach ($col['items'] as $item) {
                // Handle string items (Brand names) vs Object items
                if (is_string($item)) {
                    $label = $item;
                } else {
                    $label = $item['label'];
                }

                $url = '#';

                // Simple logic to approximate the dynamic URL generation
                // This "freezes" the logic. 
                if ($type === 'brand') {
                    // /brand/slug/
                    $url = home_url('/brand/' . sanitize_title($label) . '/');
                } elseif ($type === 'accessory') {
                    // /accessory-category/slug/ or /accessories/?section=...
                    // For now, map to search or best guess. 
                    // Most accessory items in current JSON are simple labels.
                    // Let's assume a search or broad category if not specific.
                    $url = home_url('/?s=' . urlencode($label) . '&post_type=product');
                } elseif ($type === 'motorcycle') {
                    // /motorcycles/?type=...
                    $url = home_url('/motorcycles/?type=' . urlencode($label));
                } elseif ($type === 'helmet') {
                    // /helmet-type/slug/
                    $url = home_url('/helmet-type/' . sanitize_title($label) . '/');
                }

                wp_update_nav_menu_item($menuId, 0, [
                    'menu-item-title'   => $label,
                    'menu-item-url'     => $url,
                    'menu-item-status'  => 'publish',
                    'menu-item-parent-id' => $parentId,
                    'menu-item-type'    => 'custom',
                ]);
            }
        }
        
        // Category Lists (Brands, Accessories often have 'categories' key in my manual JSON? 
        // No, the schema uses 'items' list with text strings.
        // The previous step created specific logic for "featured" vs "text". 
        // The JSON I created had "items": [{"label": "Name", ...}] format? 
        // Let's check the keys used in template tags or JSON files.
        // Re-reading code I wrote: brands-mega-menu.json used 'items' array of strings or objects?
        // Let's look at a file content to be sure.
    }
    
    echo "  Done.\n";
}

// Run Migrations
helmetsan_migrate_json_to_menu('brand', 'brands-mega-menu.json', 'Brands Mega Menu', 'mega_brands');
helmetsan_migrate_json_to_menu('accessory', 'accessories-mega-menu.json', 'Accessories Mega Menu', 'mega_accessories');
helmetsan_migrate_json_to_menu('motorcycle', 'motorcycles-mega-menu.json', 'Motorcycles Mega Menu', 'mega_motorcycles');
// Helmets is complex (families etc), let's skip autogenerating it for now or do a simple version
// helmetsan_migrate_json_to_menu('helmet', 'helmet-mega-menu.json', 'Helmets Mega Menu', 'mega_helmets');
