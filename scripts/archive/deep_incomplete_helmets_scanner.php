<?php
/**
 * Helmetsan Deep Incomplete Helmets RAM Scanner & Inventory Engine
 * 
 * Loads all 2,235 helmets into RAM and scans for micro-incompleteness across 15 fields:
 * - Identifiers (ASIN, EAN, SKU, MPN)
 * - Specs (Weight, Material, Shells, Warranty, Strap)
 * - Safety & Aero-Acoustics (Homologation, SHARP, Rotational, Noise dB, Drag, Vent Score)
 * - 3D Fitment Coordinates (Internal Length mm, Width mm, Crown Depth mm)
 * - Editorial & Marketplace (Technical Analysis, Yoast, Fit Notes, Media)
 */

$rootDir     = dirname(__DIR__);
$helmetFiles = glob($rootDir . '/data/helmets/*.json') ?: [];

$startTime = microtime(true);

echo "========================================================\n";
echo "🔍 HELMETSAN DEEP INCOMPLETE HELMETS RAM SCANNER\n";
echo "========================================================\n";

$inventory = [
    'total_helmets' => count($helmetFiles),
    'fully_complete' => 0,
    'partially_incomplete' => 0,
    'missing_field_breakdown' => [
        'asin'                    => 0,
        'ean'                     => 0,
        'sku'                     => 0,
        'mpn'                     => 0,
        'weight_g'                => 0,
        'material'                => 0,
        'shell_sizes_count'       => 0,
        'warranty_years'          => 0,
        'strap_type'              => 0,
        'homologation_standard'   => 0,
        'sharp_rating'            => 0,
        'rotational_mitigation'   => 0,
        'noise_db_at_100kph'      => 0,
        'drag_coefficient'        => 0,
        'internal_length_mm'      => 0,
        'technical_analysis'      => 0,
        'fit_notes'               => 0,
        'yoast_metadesc'          => 0,
        'geo_media'               => 0,
    ],
    'incomplete_list' => [],
];

foreach ($helmetFiles as $f) {
    $bn = basename($f, '.json');
    if ($bn === 'master.example' || $bn === 'master') continue;
    $d = json_decode(file_get_contents($f), true);
    if (!$d) continue;

    $id    = $d['id'] ?? $bn;
    $title = $d['title'] ?? $bn;

    $missing = [];

    if (empty($d['identifiers']['asin'])) { $inventory['missing_field_breakdown']['asin']++; $missing[] = 'asin'; }
    if (empty($d['identifiers']['ean'])) { $inventory['missing_field_breakdown']['ean']++; $missing[] = 'ean'; }
    if (empty($d['identifiers']['sku'])) { $inventory['missing_field_breakdown']['sku']++; $missing[] = 'sku'; }
    if (empty($d['identifiers']['mpn'])) { $inventory['missing_field_breakdown']['mpn']++; $missing[] = 'mpn'; }

    if (empty($d['specs']['weight_g']) || $d['specs']['weight_g'] <= 500) { $inventory['missing_field_breakdown']['weight_g']++; $missing[] = 'weight_g'; }
    if (empty($d['specs']['material'])) { $inventory['missing_field_breakdown']['material']++; $missing[] = 'material'; }
    if (empty($d['specs']['shell_sizes_count'])) { $inventory['missing_field_breakdown']['shell_sizes_count']++; $missing[] = 'shell_sizes_count'; }
    if (empty($d['specs']['warranty_years'])) { $inventory['missing_field_breakdown']['warranty_years']++; $missing[] = 'warranty_years'; }
    if (empty($d['specs']['strap_type'])) { $inventory['missing_field_breakdown']['strap_type']++; $missing[] = 'strap_type'; }

    if (empty($d['safety_intelligence']['homologation_standard'])) { $inventory['missing_field_breakdown']['homologation_standard']++; $missing[] = 'homologation_standard'; }
    if (!isset($d['safety_intelligence']['sharp_rating']) || $d['safety_intelligence']['sharp_rating'] === null) { $inventory['missing_field_breakdown']['sharp_rating']++; $missing[] = 'sharp_rating'; }
    if (!isset($d['safety_intelligence']['rotational_mitigation']) || $d['safety_intelligence']['rotational_mitigation'] === false) { $inventory['missing_field_breakdown']['rotational_mitigation']++; $missing[] = 'rotational_mitigation'; }

    if (empty($d['aero_acoustic_profile']['noise_db_at_100kph'])) { $inventory['missing_field_breakdown']['noise_db_at_100kph']++; $missing[] = 'noise_db_at_100kph'; }
    if (empty($d['aero_acoustic_profile']['drag_coefficient'])) { $inventory['missing_field_breakdown']['drag_coefficient']++; $missing[] = 'drag_coefficient'; }

    if (empty($d['fitment_coordinates']['internal_length_mm'])) { $inventory['missing_field_breakdown']['internal_length_mm']++; $missing[] = 'internal_length_mm'; }

    if (empty($d['technical_analysis'])) { $inventory['missing_field_breakdown']['technical_analysis']++; $missing[] = 'technical_analysis'; }
    if (empty($d['sizing_fit']['fit_notes'])) { $inventory['missing_field_breakdown']['fit_notes']++; $missing[] = 'fit_notes'; }
    if (empty($d['yoast_metadesc'])) { $inventory['missing_field_breakdown']['yoast_metadesc']++; $missing[] = 'yoast_metadesc'; }
    if (empty($d['geo_media'])) { $inventory['missing_field_breakdown']['geo_media']++; $missing[] = 'geo_media'; }

    if (empty($missing)) {
        $inventory['fully_complete']++;
    } else {
        $inventory['partially_incomplete']++;
        $inventory['incomplete_list'][$id] = [
            'file'    => $f,
            'title'   => $title,
            'missing' => $missing,
        ];
    }
}

$total = $inventory['total_helmets'];

echo "📊 RAM INCOMPLETENESS INVENTORY SUMMARY ($total Helmets Audited)\n";
echo "========================================================\n";
echo "   - 100% Fully Complete Helmets  : {$inventory['fully_complete']} (" . round(($inventory['fully_complete'] / $total) * 100, 1) . "%)\n";
echo "   - Partially Incomplete Helmets : {$inventory['partially_incomplete']} (" . round(($inventory['partially_incomplete'] / $total) * 100, 1) . "%)\n\n";

echo "📋 Missing Field Breakdown:\n";
foreach ($inventory['missing_field_breakdown'] as $field => $count) {
    if ($count > 0) {
        $pct = round(($count / $total) * 100, 1);
        echo sprintf("   - %-26s : %4d helmets (%5.1f%%)\n", $field, $count, $pct);
    }
}

$inventoryFile = $rootDir . '/logs/incomplete_helmets_inventory.json';
file_put_contents($inventoryFile, json_encode($inventory, JSON_PRETTY_PRINT));

$execTime = round((microtime(true) - $startTime) * 1000, 2);
echo "\n========================================================\n";
echo "✅ Incompleteness Scan Complete in {$execTime} ms. Inventory saved to logs/incomplete_helmets_inventory.json\n";
echo "========================================================\n";
