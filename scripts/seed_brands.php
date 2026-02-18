<?php
/**
 * Seeding Script for Helmet Brands
 * 
 * Usage: wp eval-file scripts/seed_brands.php
 */

$brands = [
    "100%", "509", "6D Helmets", "AFX", "AGV", "Airoh", "Alpinestars", 
    "Answer Racing", "Arai", "Bell", "BILT", "Biltwell", "BMW", 
    "Cardo Systems", "EVS", "Fasthouse", "Fly Racing", "Fox Racing", 
    "FXR", "GMAX", "Hedon", "Highway 21", "HJC", "Icon", "Kabuto", 
    "Kali Protectives", "Kini Red Bull", "Klim", "LaZer", "Leatt", 
    "LS2", "Nexx", "Nolan", "O'Neal", "One Industries", "Ruby", 
    "Schuberth", "Scorpion EXO", "Sedici", "Sena", "Shark", "Shoei", 
    "Simpson", "Skid Lid", "Speed and Strength", "Thor", 
    "Troy Lee Designs", "Vespa", "X-Lite", "Z1R"
];

echo "🚀 Starting Brand Seeding...\n";

$count_created = 0;
$count_verified = 0;

foreach ($brands as $brand_name) {
    // Check if brand exists
    $existing = get_page_by_title($brand_name, OBJECT, 'brand');

    if ($existing) {
        echo "✅ Verified: $brand_name\n";
        $count_verified++;
    } else {
        // Create new brand post
        $post_id = wp_insert_post([
            'post_title'  => $brand_name,
            'post_type'   => 'brand',
            'post_status' => 'publish',
            'post_author' => 1, // Assign to admin
        ]);

        if (is_wp_error($post_id)) {
            echo "❌ Error creating $brand_name: " . $post_id->get_error_message() . "\n";
        } else {
            echo "✨ Created: $brand_name (ID: $post_id)\n";
            $count_created++;
        }
    }
}

echo "\n🎉 Seeding Complete!\n";
echo "Created: $count_created\n";
echo "Existing: $count_verified\n";
echo "Total Brands: " . count($brands) . "\n";
