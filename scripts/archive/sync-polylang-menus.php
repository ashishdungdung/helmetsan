<?php
/**
 * Sync Polylang Menu Locations to ensure all languages are mapped correctly.
 * Supports smart language fallback mapping: uses translated menus (e.g. menu-slug-de) if they exist,
 * and falls back to the default English menu (menu-slug) otherwise, preventing vanished layouts.
 * 
 * Run on server: wp eval-file /var/www/helmetsan.com/public/scripts/sync-polylang-menus.php --allow-root
 */

if (! defined('ABSPATH')) {
    require_once 'wp-load.php';
}

if (! function_exists('pll_languages_list')) {
    echo "❌ Error: Polylang is not active or installed.\n";
    exit(1);
}

// 1. Get languages
$languages = pll_languages_list();
if (empty($languages)) {
    echo "❌ Error: No languages defined in Polylang.\n";
    exit(1);
}
echo "Found languages: " . implode(', ', $languages) . "\n";

// 2. Map registered menu locations to default menu slugs
$location_to_slug = [
    'primary'          => 'helmetsan-primary',
    'secondary'        => 'helmetsan-secondary',
    'footer'           => 'helmetsan-footer',
    'legal'            => 'helmetsan-legal',
    'mega_brands'      => 'brands-mega-menu',
    'mega_accessories' => 'accessories-mega-menu',
    'mega_motorcycles' => 'motorcycles-mega-menu',
];

$theme = get_stylesheet();
echo "Active theme: {$theme}\n";

// 3. Load polylang options
$polylang_options = get_option('polylang');
if (! is_array($polylang_options)) {
    $polylang_options = [];
}
if (! isset($polylang_options['nav_menus'])) {
    $polylang_options['nav_menus'] = [];
}
if (! isset($polylang_options['nav_menus'][$theme])) {
    $polylang_options['nav_menus'][$theme] = [];
}

// 4. Map locations to menu IDs (with translation fallback)
$updated = false;
foreach ($location_to_slug as $location => $default_slug) {
    // Check if the default menu exists
    $default_menu = get_term_by('slug', $default_slug, 'nav_menu');
    if (!$default_menu) {
        echo "⚠️ Warning: Default menu with slug '{$default_slug}' not found. Skipping location '{$location}'.\n";
        continue;
    }
    
    $default_id = (int) $default_menu->term_id;
    $lang_map = [];
    
    foreach ($languages as $lang) {
        // Try to find a translated menu (e.g. helmetsan-primary-de or helmetsan-primary-zh)
        $lang_slug = $default_slug . '-' . $lang;
        $lang_menu = get_term_by('slug', $lang_slug, 'nav_menu');
        
        if ($lang_menu) {
            $lang_map[$lang] = (int) $lang_menu->term_id;
            echo "   -> [{$lang}] Found translated menu ID {$lang_map[$lang]} ('{$lang_slug}') for location '{$location}'.\n";
        } else {
            $lang_map[$lang] = $default_id;
            echo "   -> [{$lang}] No translation found ('{$lang_slug}'). Falling back to ID {$default_id} ('{$default_slug}') for location '{$location}'.\n";
        }
    }
    
    $polylang_options['nav_menus'][$theme][$location] = $lang_map;
    $updated = true;
}

// 5. Save option
if ($updated) {
    update_option('polylang', $polylang_options);
    echo "✅ Success: Polylang menu mappings updated and saved.\n";
} else {
    echo "ℹ️ No mappings were updated.\n";
}
