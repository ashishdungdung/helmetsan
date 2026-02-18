<?php
/**
 * Seeding Script for Helmet Brands (Enriched - Google Favicons)
 * 
 * Usage: wp eval-file scripts/seed_brands.php
 */

$brand_domains = [
    "100%" => "100percent.com",
    "509" => "ride509.com",
    "6D Helmets" => "6dhelmets.com",
    "AFX" => "afxhelmets.com",
    "AGV" => "agv.com",
    "Airoh" => "airoh.com",
    "Alpinestars" => "alpinestars.com",
    "Answer Racing" => "answerracing.com",
    "Arai" => "araiamericas.com",
    "Bell" => "bellhelmets.com",
    "BILT" => "cyclegear.com",
    "Biltwell" => "biltwellinc.com",
    "BMW" => "bmwmotorcycles.com",
    "Cardo Systems" => "cardosystems.com",
    "EVS" => "evs-sports.com",
    "Fasthouse" => "fasthouse.com",
    "Fly Racing" => "flyracing.com",
    "Fox Racing" => "foxracing.com",
    "FXR" => "fxrracing.com",
    "GMAX" => "gmaxhelmetsusa.com",
    "Hedon" => "hedon.com",
    "Highway 21" => "highway21.com",
    "HJC" => "hjchelmets.com",
    "Icon" => "rideicon.com",
    "Kabuto" => "ogkkabuto.co.jp",
    "Kali Protectives" => "kaliprotectives.com",
    "Kini Red Bull" => "kini.at",
    "Klim" => "klim.com",
    "LaZer" => "lazerhelmets.com",
    "Leatt" => "leatt.com",
    "LS2" => "ls2helmets.com",
    "Nexx" => "nexx-helmets.com",
    "Nolan" => "nolan-helmets.com",
    "O'Neal" => "oneal.com",
    "One Industries" => "oneindustries.com",
    "Ruby" => "ateliers-ruby.com",
    "Schuberth" => "schuberth.com",
    "Scorpion EXO" => "scorpionusa.com",
    "Sedici" => "cyclegear.com",
    "Sena" => "sena.com",
    "Shark" => "shark-helmets.com",
    "Shoei" => "shoei-helmets.com",
    "Simpson" => "simpsonraceproducts.com",
    "Skid Lid" => "skidlid.com",
    "Speed and Strength" => "ssgear.com",
    "Thor" => "thormx.com",
    "Troy Lee Designs" => "troyleedesigns.com",
    "Vespa" => "vespa.com",
    "X-Lite" => "x-lite.it",
    "Z1R" => "z1r.com"
];

require_once(ABSPATH . 'wp-admin/includes/media.php');
require_once(ABSPATH . 'wp-admin/includes/file.php');
require_once(ABSPATH . 'wp-admin/includes/image.php');

function sideload_brand_logo($url, $post_id, $desc) {
    // 1. Download to temp file
    // Note: Google Favicon API usually returns Content-Type: image/png
    $tmp = download_url($url);

    if (is_wp_error($tmp)) {
        return $tmp;
    }

    // 2. Fix Extension
    // Since Google returns PNG, we force .png
    $file_array = [
        'name'     => sanitize_file_name($desc) . '.png',
        'tmp_name' => $tmp
    ];

    // 3. Handle Sideload
    $id = media_handle_sideload($file_array, $post_id, $desc);

    if (is_wp_error($id)) {
        @unlink($file_array['tmp_name']);
    }

    return $id;
}

echo "🚀 Starting Brand Seeding (Google High-Res)...\n";

$count_created = 0;
$count_updated = 0;

foreach ($brand_domains as $brand_name => $domain) {
    // 1. Get or Create Brand
    $brand_post = get_page_by_title($brand_name, OBJECT, 'brand');
    $post_id = null;

    if ($brand_post) {
        $post_id = $brand_post->ID;
        // echo "🔹 Found: $brand_name\n";
    } else {
        $post_id = wp_insert_post([
            'post_title'  => $brand_name,
            'post_type'   => 'brand',
            'post_status' => 'publish',
            'post_author' => 1,
        ]);
        if (is_wp_error($post_id)) {
            echo "❌ Error creating $brand_name: " . $post_id->get_error_message() . "\n";
            continue;
        }
        echo "✨ Created: $brand_name\n";
        $count_created++;
    }

    // 2. Enrich with Logo
    if (!has_post_thumbnail($post_id)) {
        echo "   🖼️  Fetching logo for $brand_name ($domain)...\n";
        
        // Google Favicon V2 - Size 256
        $logo_url = "https://t2.gstatic.com/faviconV2?client=SOCIAL&type=FAVICON&fallback_opts=TYPE,SIZE,URL&url=http://" . $domain . "&size=256";
        
        $img_id = sideload_brand_logo($logo_url, $post_id, "$brand_name Logo");

        if (is_wp_error($img_id)) {
             echo "   ⚠️  Failed: " . $img_id->get_error_message() . "\n";
        } else {
            set_post_thumbnail($post_id, $img_id);
            echo "   ✅ Logo attached (ID: $img_id)\n";
            $count_updated++;
        }
    }
}

echo "\n🎉 Seeding Complete!\n";
echo "Created: $count_created\n";
echo "Logos Added: $count_updated\n";
echo "Total Brands: " . count($brand_domains) . "\n";

// --- Data Sync Export ---
echo "\n🔄 Exporting to JSON Repository...\n";

$syncManagerPath = __DIR__ . '/helmetsan-core/includes/Data/SyncManager.php';
if (file_exists($syncManagerPath)) {
    require_once $syncManagerPath;
}

if (class_exists('Helmetsan\\Core\\Data\\SyncManager')) {
    try {
        if (!defined('HELMETSAN_CORE_DIR')) {
            define('HELMETSAN_CORE_DIR', __DIR__ . '/helmetsan-core/');
        }
        $sync = new \Helmetsan\Core\Data\SyncManager();
        $path = $sync->exportBrands();
        echo "✅ Exported to: $path\n";
    } catch (Exception $e) {
        echo "❌ Export Failed: " . $e->getMessage() . "\n";
    }
} else {
    echo "⚠️ SyncManager class not found. Skipping export.\n";
}
