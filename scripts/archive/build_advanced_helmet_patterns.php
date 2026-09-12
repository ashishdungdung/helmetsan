<?php
/**
 * Helmetsan Advanced Helmet Data Patterns & Intelligence Engine
 * 
 * Computes 4 specialized engineering data patterns across all 2,235 helmets in RAM:
 * 1. Aero-Acoustic Noise & Drag Pattern Matrix
 * 2. Riding Intent & Ergonomic Use Case Patterns
 * 3. Anatomical Fitment & 3D Morphometry Patterns
 * 4. Multi-Regional Homologation & Safety Compliance Patterns
 * 
 * Exports summary data to data/advanced_helmet_patterns.json.
 */

$rootDir     = dirname(__DIR__);
$helmetFiles = glob($rootDir . '/data/helmets/*.json') ?: [];

$startTime = microtime(true);

echo "========================================================\n";
echo "🧬 HELMETSAN ADVANCED HELMET DATA PATTERNS ENGINE\n";
echo "========================================================\n";
echo "⚡ Analyzing " . count($helmetFiles) . " helmets in RAM...\n\n";

$patterns = [
    'generated_at' => date('c'),
    'total_helmets' => count($helmetFiles),
    'aero_acoustic_matrix' => [
        'ultra_quiet_under_80db' => 0,
        'moderate_80_85db'       => 0,
        'high_airflow_over_85db' => 0,
        'low_drag_under_0_30cd'  => 0,
        'standard_drag_0_30_0_34cd' => 0,
        'peak_visor_drag_over_0_35cd' => 0,
    ],
    'riding_intent_matrix' => [
        'track_racing'     => 0,
        'highway_touring'  => 0,
        'adventure_offroad'=> 0,
        'urban_commuter'   => 0,
    ],
    'anatomical_morphometry' => [
        'intermediate_oval' => 0,
        'round_oval'        => 0,
        'long_oval'         => 0,
        'eqrs_equipped'     => 0,
    ],
    'safety_compliance_matrix' => [
        'ece_22_06'   => 0,
        'ece_22_05'   => 0,
        'snell_m2020' => 0,
        'fim_racing'  => 0,
        'sharp_5_star'=> 0,
        'sharp_4_star'=> 0,
    ],
];

foreach ($helmetFiles as $f) {
    $bn = basename($f, '.json');
    if ($bn === 'master.example' || $bn === 'master') continue;
    $d = json_decode(file_get_contents($f), true);
    if (!$d) continue;

    $type   = strtolower($d['type'] ?? 'full face');
    $db     = (float)($d['aero_acoustic_profile']['noise_db_at_100kph'] ?? 82.5);
    $cd     = (float)($d['aero_acoustic_profile']['drag_coefficient'] ?? 0.31);
    $shape  = strtolower($d['head_shape'] ?? 'intermediate oval');
    $std    = strtolower($d['safety_intelligence']['homologation_standard'] ?? 'ece 22.06');
    $sharp  = (int)($d['safety_intelligence']['sharp_rating'] ?? 0);

    // 1. Aero-Acoustic Matrix
    if ($db < 80.0) $patterns['aero_acoustic_matrix']['ultra_quiet_under_80db']++;
    elseif ($db <= 85.0) $patterns['aero_acoustic_matrix']['moderate_80_85db']++;
    else $patterns['aero_acoustic_matrix']['high_airflow_over_85db']++;

    if ($cd < 0.30) $patterns['aero_acoustic_matrix']['low_drag_under_0_30cd']++;
    elseif ($cd <= 0.34) $patterns['aero_acoustic_matrix']['standard_drag_0_30_0_34cd']++;
    else $patterns['aero_acoustic_matrix']['peak_visor_drag_over_0_35cd']++;

    // 2. Riding Intent Matrix
    if (strpos($type, 'race') !== false || strpos($type, 'track') !== false) {
        $patterns['riding_intent_matrix']['track_racing']++;
    } elseif (strpos($type, 'touring') !== false || strpos($type, 'modular') !== false) {
        $patterns['riding_intent_matrix']['highway_touring']++;
    } elseif (strpos($type, 'adventure') !== false || strpos($type, 'dirt') !== false || strpos($type, 'off-road') !== false) {
        $patterns['riding_intent_matrix']['adventure_offroad']++;
    } else {
        $patterns['riding_intent_matrix']['urban_commuter']++;
    }

    // 3. Anatomical Fitment
    if (strpos($shape, 'intermediate') !== false) $patterns['anatomical_morphometry']['intermediate_oval']++;
    elseif (strpos($shape, 'round') !== false) $patterns['anatomical_morphometry']['round_oval']++;
    elseif (strpos($shape, 'long') !== false) $patterns['anatomical_morphometry']['long_oval']++;

    $jsonStr = strtolower(json_encode($d));
    if (strpos($jsonStr, 'eqrs') !== false || strpos($jsonStr, 'emergency release') !== false) {
        $patterns['anatomical_morphometry']['eqrs_equipped']++;
    }

    // 4. Safety Compliance
    if (strpos($std, '22.06') !== false) $patterns['safety_compliance_matrix']['ece_22_06']++;
    if (strpos($std, '22.05') !== false) $patterns['safety_compliance_matrix']['ece_22_05']++;
    if (strpos($std, 'snell') !== false) $patterns['safety_compliance_matrix']['snell_m2020']++;
    if (strpos($std, 'fim') !== false) $patterns['safety_compliance_matrix']['fim_racing']++;

    if ($sharp === 5) $patterns['safety_compliance_matrix']['sharp_5_star']++;
    elseif ($sharp === 4) $patterns['safety_compliance_matrix']['sharp_4_star']++;
}

$outputFile = $rootDir . '/data/advanced_helmet_patterns.json';
file_put_contents($outputFile, json_encode($patterns, JSON_PRETTY_PRINT));

$execTime = round((microtime(true) - $startTime) * 1000, 2);
echo "📊 ADVANCED DATA PATTERNS COMPUTED IN {$execTime} ms:\n";
echo "========================================================\n";
echo "1. Aero-Acoustics:\n";
echo "   - Ultra Quiet (< 80 dB)       : {$patterns['aero_acoustic_matrix']['ultra_quiet_under_80db']} helmets\n";
echo "   - Moderate (80 - 85 dB)       : {$patterns['aero_acoustic_matrix']['moderate_80_85db']} helmets\n";
echo "   - High Airflow / Race (> 85dB): {$patterns['aero_acoustic_matrix']['high_airflow_over_85db']} helmets\n";
echo "   - Low Drag (< 0.30 Cd)        : {$patterns['aero_acoustic_matrix']['low_drag_under_0_30cd']} helmets\n";

echo "\n2. Riding Intent:\n";
echo "   - Urban / Commuter Full-Face  : {$patterns['riding_intent_matrix']['urban_commuter']} helmets\n";
echo "   - Highway Touring / Modular   : {$patterns['riding_intent_matrix']['highway_touring']} helmets\n";
echo "   - Adventure & Off-road        : {$patterns['riding_intent_matrix']['adventure_offroad']} helmets\n";
echo "   - Track & Circuit Racing      : {$patterns['riding_intent_matrix']['track_racing']} helmets\n";

echo "\n3. Safety Standards:\n";
echo "   - ECE 22.06 Compliant         : {$patterns['safety_compliance_matrix']['ece_22_06']} helmets\n";
echo "   - FIM Circuit Homologated     : {$patterns['safety_compliance_matrix']['fim_racing']} helmets\n";
echo "   - SHARP 5-Star Rating         : {$patterns['safety_compliance_matrix']['sharp_5_star']} helmets\n";

echo "\n========================================================\n";
echo "✅ Saved Advanced Helmet Patterns to data/advanced_helmet_patterns.json\n";
echo "========================================================\n";
