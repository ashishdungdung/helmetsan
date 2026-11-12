<?php
define('WP_USE_THEMES', false);
require_once '/var/www/helmetsan.com/public/wp-load.php';

global $wpdb;

$valid_slugs = [
  'anti-fog-insert-universal', 'balaclava-coolmax', 'bluetooth-comms-kit',
  'cardo-spirit-hd', 'cheek-pad-set', 'cooling-vest-insert',
  'dark-smoke-visor', 'fidlock-chin-strap', 'hearing-protection-earplugs',
  'helmet-bag-premium', 'helmet-cam-mount', 'helmet-dryer',
  'helmet-interior-liner-set', 'helmet-lock-cable', 'helmet-sanitizer-spray',
  'helmet-stand', 'iridium-visor-agv', 'led-brake-light',
  'neck-gaiter-windproof', 'photochromic-visor', 'pinlock-120-lens',
  'rain-visor-sealant', 'sena-50s-mesh', 'transition-visor',
  'visor-cleaning-kit', 'visor-tear-offs'
];
$slugs_in = "'" . implode("','", $valid_slugs) . "'";

$bogus_ids = $wpdb->get_col("
    SELECT DISTINCT p.ID 
    FROM {$wpdb->posts} p 
    LEFT JOIN {$wpdb->postmeta} pm_file ON p.ID = pm_file.post_id AND pm_file.meta_key = '_source_file' 
    LEFT JOIN {$wpdb->postmeta} pm_uid ON p.ID = pm_uid.post_id AND pm_uid.meta_key = '_accessory_unique_id' 
    WHERE p.post_type = 'accessory' 
      AND (pm_file.meta_value NOT LIKE '%accessories%' OR pm_file.meta_value IS NULL) 
      AND (pm_uid.meta_value NOT IN ($slugs_in) OR pm_uid.meta_value IS NULL)
");

echo "Found " . count($bogus_ids) . " bogus accessory posts to delete.\n";

if (empty($bogus_ids)) {
    echo "Nothing to delete.\n";
    exit(0);
}

// Delete in batches of 500
$chunks = array_chunk($bogus_ids, 500);
$total_deleted = 0;

foreach ($chunks as $chunk) {
    $ids_str = implode(',', array_map('intval', $chunk));
    
    // Delete postmeta
    $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE post_id IN ($ids_str)");
    // Delete term relationships
    $wpdb->query("DELETE FROM {$wpdb->term_relationships} WHERE object_id IN ($ids_str)");
    // Delete posts
    $deleted = $wpdb->query("DELETE FROM {$wpdb->posts} WHERE ID IN ($ids_str)");
    
    $total_deleted += $deleted;
    echo "Deleted batch of $deleted posts...\n";
}

echo "Finished! Successfully deleted $total_deleted bogus accessory posts.\n";

$remaining_en = $wpdb->get_var("
    SELECT count(DISTINCT p.ID)
    FROM {$wpdb->posts} p
    JOIN {$wpdb->term_relationships} tr ON p.ID = tr.object_id
    JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
    JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
    WHERE p.post_type = 'accessory' 
      AND p.post_status = 'publish'
      AND tt.taxonomy = 'language'
      AND t.slug = 'en'
");
$remaining_total = $wpdb->get_var("SELECT count(ID) FROM {$wpdb->posts} WHERE post_type = 'accessory' AND post_status = 'publish'");

echo "Remaining published accessories: Total=$remaining_total, English=$remaining_en\n";
