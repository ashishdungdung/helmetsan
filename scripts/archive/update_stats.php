<?php
/**
 * Update Project Stats (Remote or Local with DB access)
 * Intended to be run via wp eval-file.
 */

if (!defined('ABSPATH')) {
    exit;
}

echo "📊 Gathering Project Statistics...\n";

// 1. Get Brand Count
$brand_count = wp_count_posts('brand')->publish;

// 2. Get Helmet Count
$helmet_count = wp_count_posts('helmet')->publish;

// 3. Get Logo Coverage
$args = [
    'post_type'      => 'brand',
    'posts_per_page' => -1,
    'meta_query'     => [
        [
            'key'     => '_thumbnail_id',
            'compare' => 'EXISTS',
        ],
    ],
];
$brands_with_logos = count(get_posts($args));
$logo_percentage = ($brand_count > 0) ? round(($brands_with_logos / $brand_count) * 100) : 0;

// 3b. Parent models (helmets with post_parent = 0)
$parent_models = count(get_posts([
    'post_type'      => 'helmet',
    'post_status'    => 'publish',
    'post_parent'    => 0,
    'posts_per_page' => -1,
    'fields'         => 'ids',
]));

// 4. Generate Dashboard Markdown
$last_update = date('Y-m-d H:i:s');
$dashboard = <<<EOD

## 📊 Project Dashboard (Status: Live)

| Metric | Value | Status |
| :--- | :--- | :--- |
| **Brands in Catalog** | $brand_count | ✅ |
| **Helmets Indexed** | $helmet_count | ✅ Live |
| **Parent Models** | $parent_models | ✅ |
| **Logo Coverage** | $logo_percentage% ($brands_with_logos/$brand_count) | 🎨 Enriched |
| **Last Sync** | $last_update | 📡 Active |

> _Stats generated automatically by `scripts/update_stats.php`_

EOD;

echo "\n--- DASHBOARD START ---\n";
echo $dashboard;
echo "--- DASHBOARD END ---\n";

// 5. Update README.md (if exists in root)
$readme_path = ABSPATH . 'README.md'; // Correct path in web root
if (file_exists($readme_path)) {
    $readme = file_get_contents($readme_path);
    // Use markers to replace content
    $start_marker = "<!-- STATS_START -->";
    $end_marker = "<!-- STATS_END -->";
    
    if (strpos($readme, $start_marker) !== false && strpos($readme, $end_marker) !== false) {
        $pattern = "/$start_marker.*?$end_marker/s";
        $replacement = "$start_marker\n$dashboard\n$end_marker";
        $new_readme = preg_replace($pattern, $replacement, $readme);
        file_put_contents($readme_path, $new_readme);
        echo "✅ README.md updated with latest stats.\n";
    } else {
        echo "⚠️  Stats markers not found in README.md. Please add <!-- STATS_START --> and <!-- STATS_END -->.\n";
    }
} else {
    echo "⚠️  README.md not found at $readme_path.\n";
}
