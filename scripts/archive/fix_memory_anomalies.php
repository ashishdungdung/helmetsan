<?php
/**
 * Helmetsan Memory Anomaly Repair Script
 * 
 * Repairs data anomalies identified by Memory Health Audit:
 * 1. Caps invalid SHARP ratings > 5 to max 5 (or null if unverified).
 * 2. Corrects physical weight outlier (Shoei TC-1 2800g -> 1450g).
 */

$dataDir = dirname(__DIR__) . '/data/helmets';
$files   = glob($dataDir . '/*.json');

$fixedSharp = 0;
$fixedWeight = 0;

foreach ($files as $f) {
    $bn = basename($f);
    if ($bn === 'master.example.json' || $bn === 'master.json') continue;
    
    $json = file_get_contents($f);
    $data = json_decode($json, true);
    if (!$data) continue;
    
    $updated = false;

    // 1. Repair Invalid SHARP Rating (> 5)
    $sharp = $data['safety_intelligence']['sharp_rating'] ?? null;
    if ($sharp !== null && is_numeric($sharp) && (int)$sharp > 5) {
        // If SHARP > 5, cap at 5
        $data['safety_intelligence']['sharp_rating'] = 5;
        $updated = true;
        $fixedSharp++;
    }

    // 2. Repair Weight Outlier (Shoei TC-1 2800g -> 1450g)
    $weight = (int)($data['specs']['weight_g'] ?? 0);
    if ($weight > 2500 && strpos($f, 'shoei_tc_1') !== false) {
        $data['specs']['weight_g'] = 1450;
        $updated = true;
        $fixedWeight++;
    }

    if ($updated) {
        file_put_contents($f, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
}

echo "✅ Repaired $fixedSharp SHARP rating anomalies and $fixedWeight weight outliers.\n";
