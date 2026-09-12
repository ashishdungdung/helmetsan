<?php
/**
 * Deep Audit of Helmet Data Sparsity
 * 
 * Analyzes all helmet JSON files in data/helmets/ and reports on missing fields
 * based on the helmet.schema.json.
 */

$dataDir = dirname(__DIR__) . '/data/helmets';
$files = glob($dataDir . '/*.json');

if (!$files) {
    die("❌ No JSON files found in $dataDir\n");
}

$total = 0;
$stats = [
    'top_level' => [],
    'identifiers' => [],
    'specs' => [],
    'safety' => [],
    'tech' => [],
    'sizing' => [],
];

$fields = [
    'top_level' => ['id', 'title', 'brand', 'type', 'helmet_family', 'head_shape', 'model_year', 'marketplace_links'],
    'identifiers' => ['asin', 'ean', 'upc', 'gtin', 'sku', 'mpn'],
    'specs' => ['weight_g', 'material', 'shell_sizes_count', 'warranty_years', 'strap_type'],
    'safety' => ['homologation_standard', 'sharp_rating', 'rotational_mitigation'],
    'tech' => ['comms_cutout_type', 'speaker_pocket_depth_mm', 'cable_management', 'hud_ready'],
    'sizing' => ['head_shape', 'fit_notes', 'size_chart'],
];

// Initialize counters
foreach ($fields as $group => $names) {
    foreach ($names as $name) {
        $stats[$group][$name] = 0;
    }
}

echo "🔍 Scanning " . count($files) . " helmets...\n";

foreach ($files as $file) {
    if (basename($file) === 'master.example.json' || basename($file) === 'master.json') continue;
    
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    if (!$data) continue;
    
    $total++;
    
    // Check Top Level
    foreach ($fields['top_level'] as $f) {
        if (!empty($data[$f])) $stats['top_level'][$f]++;
    }
    
    // Check Identifiers
    if (!empty($data['identifiers'])) {
        foreach ($fields['identifiers'] as $f) {
            if (!empty($data['identifiers'][$f])) $stats['identifiers'][$f]++;
        }
    }
    
    // Check Specs
    if (!empty($data['specs'])) {
        foreach ($fields['specs'] as $f) {
            if (!empty($data['specs'][$f])) $stats['specs'][$f]++;
        }
    }
    
    // Check Safety
    if (!empty($data['safety_intelligence'])) {
        foreach ($fields['safety'] as $f) {
            if (!empty($data['safety_intelligence'][$f])) $stats['safety'][$f]++;
        }
    }
    
    // Check Tech
    if (!empty($data['tech_integration'])) {
        foreach ($fields['tech'] as $f) {
            if (!empty($data['tech_integration'][$f])) $stats['tech'][$f]++;
        }
    }
    
    // Check Sizing
    if (!empty($data['sizing_fit'])) {
        foreach ($fields['sizing'] as $f) {
            if (!empty($data['sizing_fit'][$f])) $stats['sizing'][$f]++;
        }
    }
}

echo "\n📊 GLOBAL DATA INTEGRITY REPORT ($total Helmets Total)\n";
echo "========================================================\n";

foreach ($fields as $group => $names) {
    echo "\n[" . strtoupper($group) . "]\n";
    foreach ($names as $name) {
        $count = $stats[$group][$name];
        $percent = round(($count / $total) * 100, 1);
        $bar = str_repeat("█", floor($percent/5));
        printf("%-25s : %5.1f%% (%d/%d) %s\n", $name, $percent, $count, $total, $bar);
    }
}

echo "\n💡 Recommendation: Fields with < 90% coverage should be prioritized in the next AI pass.\n";
