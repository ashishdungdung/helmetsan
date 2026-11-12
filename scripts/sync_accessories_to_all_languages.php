<?php
define('WP_USE_THEMES', false);
require_once '/var/www/helmetsan.com/public/wp-load.php';

$accessoriesDir = '/var/www/helmetsan.com/public/data/accessories';
$files = glob($accessoriesDir . '/*.json');

echo "Syncing " . count($files) . " accessory files to English and multilingual posts...\n";

$slug_map = [
    'anti-fog-insert-universal' => ['pinlock-70-maxvision-anti-fog-insert', 'anti-fog-insert-universal'],
    'balaclava-coolmax' => ['alpinestars-race-v3-balaclava', 'balaclava-coolmax'],
    'bluetooth-comms-kit' => ['bluetooth-comms-kit', 'cardo-packtalk-edge'],
    'cardo-spirit-hd' => ['cardo-spirit-hd-duo', 'cardo-spirit-hd'],
    'cheek-pad-set' => ['cheek-pad-set', 'shoei-rf-1400-multi-density-cheek-pads'],
    'cooling-vest-insert' => ['macna-cooling-vest-evo', 'cooling-vest-insert'],
    'dark-smoke-visor' => ['hjc-hj-31-dark-smoke-face-shield', 'dark-smoke-visor'],
    'fidlock-chin-strap' => ['fidlock-v-buckle-magnetic-chin-strap-kit', 'fidlock-chin-strap'],
    'hearing-protection-earplugs' => ['nonoise-motorsport-filtered-earplugs', 'hearing-protection-earplugs'],
    'helmet-bag-premium' => ['shoei-premium-fleece-lined-helmet-bag', 'helmet-bag-premium'],
    'helmet-cam-mount' => ['gopro-chin-mount-kit', 'helmet-cam-mount'],
    'helmet-dryer' => ['capit-helmet-dryer-pro', 'helmet-dryer'],
    'helmet-interior-liner-set' => ['shoei-rf-1400-complete-interior-liner-set', 'helmet-interior-liner-set'],
    'helmet-lock-cable' => ['helmetlok-carabiner-helmet-lock', 'helmet-lock-cable'],
    'helmet-sanitizer-spray' => ['motul-m2-helmet-interior-cleaner', 'helmet-sanitizer-spray'],
    'helmet-stand' => ['bikemaster-deluxe-helmet-stand', 'helmet-stand'],
    'iridium-visor-agv' => ['agv-pista-gp-rr-iridium-gold-race-visor', 'iridium-visor-agv'],
    'led-brake-light' => ['brake-free-smart-led-helmet-light', 'led-brake-light'],
    'neck-gaiter-windproof' => ['revit-windproof-neck-tube', 'neck-gaiter-windproof'],
    'photochromic-visor' => ['photochromic-visor', 'bell-panovision-protint-photochromic-shield'],
    'pinlock-120-lens' => ['pinlock-120-lens'],
    'rain-visor-sealant' => ['rain-x-visor-rain-repellent', 'rain-visor-sealant'],
    'sena-50s-mesh' => ['sena-50s-mesh-2-0', 'sena-50s-mesh'],
    'transition-visor' => ['shoei-cwr-f2-transitions-photochromic-shield', 'transition-visor'],
    'visor-cleaning-kit' => ['visor-cleaning-kit', 'muc-off-helmet-visor-cleaning-kit'],
    'visor-tear-offs' => ['100-speedlab-vision-system-tear-offs-pack-of-20', 'visor-tear-offs']
];

global $wpdb;

foreach ($files as $file) {
    if (strpos($file, 'master.example.json') !== false) continue;
    $slug = basename($file, '.json');
    $data = json_decode(file_get_contents($file), true);
    if (!$data) continue;

    $known_slugs = $slug_map[$slug] ?? [$slug];
    $known_slugs[] = $slug;
    $slug_clause = "'" . implode("','", array_unique($known_slugs)) . "'";

    // Find all post IDs across all languages for this accessory
    $post_ids = $wpdb->get_col("
        SELECT DISTINCT p.ID 
        FROM {$wpdb->posts} p
        LEFT JOIN {$wpdb->postmeta} pm_uid ON p.ID = pm_uid.post_id AND pm_uid.meta_key = '_accessory_unique_id'
        WHERE p.post_type = 'accessory' 
          AND (p.post_name IN ($slug_clause) OR pm_uid.meta_value = '$slug')
    ");

    foreach ($post_ids as $postId) {
        $postId = (int)$postId;
        $lang = function_exists('pll_get_post_language') ? pll_get_post_language($postId) : 'en';

        // Update title and content for English
        if ($lang === 'en' || empty($lang)) {
            $wpdb->update($wpdb->posts, [
                'post_title' => $data['title'],
                'post_content' => $data['description'] ?? '',
                'post_excerpt' => $data['editorial_overview'] ?? ''
            ], ['ID' => $postId]);
        }

        // Update core metadata
        update_post_meta($postId, '_accessory_unique_id', $slug);
        update_post_meta($postId, 'accessory_brand', $data['brand']);
        update_post_meta($postId, 'accessory_type', $data['type']);
        update_post_meta($postId, 'accessory_parent_category', $data['parent_category']);
        update_post_meta($postId, 'accessory_subcategory', $data['subcategory']);
        update_post_meta($postId, 'accessory_color', $data['color']);
        update_post_meta($postId, 'accessory_youth_adult', $data['youth_adult']);
        update_post_meta($postId, 'accessory_pinlock_ready', (string)$data['pinlock_ready']);
        update_post_meta($postId, 'accessory_electric_compatible', (string)$data['electric_compatible']);
        update_post_meta($postId, 'accessory_snow_compatible', (string)$data['snow_compatible']);

        // JSON metadata
        update_post_meta($postId, 'price_json', wp_json_encode($data['price'], JSON_UNESCAPED_SLASHES));
        update_post_meta($postId, 'geo_pricing_json', wp_json_encode($data['geo_pricing'], JSON_UNESCAPED_SLASHES));
        update_post_meta($postId, 'compatible_helmet_types_json', wp_json_encode($data['compatible_helmet_types'], JSON_UNESCAPED_SLASHES));
        update_post_meta($postId, 'compatible_brands_json', wp_json_encode($data['compatible_brands'], JSON_UNESCAPED_SLASHES));
        update_post_meta($postId, 'compatible_helmet_ids_json', wp_json_encode($data['compatible_helmet_ids'], JSON_UNESCAPED_SLASHES));
        update_post_meta($postId, 'compatible_helmet_families_json', wp_json_encode($data['compatible_helmet_families'], JSON_UNESCAPED_SLASHES));
        update_post_meta($postId, 'accessory_features_json', wp_json_encode($data['features'], JSON_UNESCAPED_SLASHES));

        // Identifiers
        if (!empty($data['identifiers']['sku'])) {
            update_post_meta($postId, 'sku', $data['identifiers']['sku']);
        }
        if (!empty($data['identifiers']['mpn'])) {
            update_post_meta($postId, 'mpn', $data['identifiers']['mpn']);
        }
        if (!empty($data['identifiers']['asin'])) {
            update_post_meta($postId, 'affiliate_asin', $data['identifiers']['asin']);
        }

        // Affiliate links
        if (!empty($data['affiliate_links'])) {
            update_post_meta($postId, 'affiliate_links_json', wp_json_encode($data['affiliate_links'], JSON_UNESCAPED_SLASHES));
        }

        // Taxonomies
        $categories = array_filter([$data['parent_category'], $data['subcategory']]);
        wp_set_object_terms($postId, $categories, 'accessory_category', false);

        if (!empty($data['compatible_helmet_types'])) {
            wp_set_object_terms($postId, $data['compatible_helmet_types'], 'helmet_type', false);
        }
    }

    echo "Synced {$slug} across " . count($post_ids) . " posts.\n";
}

echo "All accessory posts fully synchronized across all languages!\n";
