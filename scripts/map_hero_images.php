<?php
/**
 * scripts/map_hero_images.php
 * Maps the newly generated cinematic hero images to their respective JSON files.
 */

$mapping = [
    'shoei_rf_1400.json' => 'shoei_rf_1400_hero_1778308051019.png',
    'arai_corsair_x.json' => 'arai_corsair_x_hero_1778308080995.png',
    'agv_pista_gp_rr.json' => 'agv_pista_gp_rr_hero_carbon_1778308118456.png',
    'bell_bullitt.json' => 'bell_bullitt_chrome_hero_1778309443595.png',
    'hjc_rpha_11_pro.json' => 'hjc_rpha_11_pro_hero_venom_1778309467201.png',
    'scorpion_exo_r1_air.json' => 'scorpion_exo_r1_air_hero_carbon_1778309506853.png',
    'schuberth_c5.json' => 'schuberth_c5_hero_grey_1778309530226.png',
    'airoh_aviator_3.json' => 'airoh_aviator_3_hero_mx_1778309558829.png'
];

$helmetDir = __DIR__ . '/../data/helmets';
$mediaBaseUrl = 'https://helmetsan.com/wp-content/uploads/helmets/'; // Production assumption

foreach ($mapping as $file => $image) {
    $path = "$helmetDir/$file";
    if (file_exists($path)) {
        $data = json_decode(file_get_contents($path), true);
        
        // Add the new high-fidelity image at the front of geo_media for the parent
        $newImageUrl = $mediaBaseUrl . $image;
        
        // Check if already present in parent
        if (!in_array($newImageUrl, $data['geo_media'])) {
            array_unshift($data['geo_media'], $newImageUrl);
            echo "🖼 Mapped $image to parent in $file\n";
        }

        // Also update variants if they exist
        if (isset($data['variants']) && is_array($data['variants'])) {
            foreach ($data['variants'] as &$variant) {
                if (isset($variant['geo_media']) && is_array($variant['geo_media'])) {
                    if (!in_array($newImageUrl, $variant['geo_media'])) {
                        array_unshift($variant['geo_media'], $newImageUrl);
                        echo "🖼 Mapped $image to variant {$variant['id']} in $file\n";
                    }
                }
            }
        }
        
        file_put_contents($path, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

