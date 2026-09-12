<?php
/**
 * Helmetsan Multi-Tier In-Memory Helmet Audit Engine
 * 
 * Performs 4-tier deep memory audit across all 2,235 helmet files:
 * Tier 1: Core Product Identity & Schema Validation
 * Tier 2: Direct Technical Details & Physical Specs
 * Tier 3: Safety Intelligence & Aero-Acoustic Metrics
 * Tier 4: Single Helmet Page UX & Editorial Content Completeness
 */

$rootDir     = dirname(__DIR__);
$helmetFiles = glob($rootDir . '/data/helmets/*.json') ?: [];

$startTime = microtime(true);

echo "========================================================\n";
echo "🛡️ HELMETSAN MULTI-TIER IN-MEMORY HELMET AUDIT ENGINE\n";
echo "========================================================\n";

$auditResults = [
    'total_helmets' => 0,
    'tier1_identity' => ['pass' => 0, 'fail' => 0, 'missing' => []],
    'tier2_specs'    => ['pass' => 0, 'fail' => 0, 'missing' => []],
    'tier3_safety'   => ['pass' => 0, 'fail' => 0, 'missing' => []],
    'tier4_page_ux'  => ['pass' => 0, 'fail' => 0, 'missing' => []],
    'score_distribution' => [
        '100%' => 0,
        '90-99%' => 0,
        '75-89%' => 0,
        '<75%' => 0,
    ],
];

$helmetsData = [];

foreach ($helmetFiles as $f) {
    $bn = basename($f, '.json');
    if ($bn === 'master.example' || $bn === 'master') continue;
    $d = json_decode(file_get_contents($f), true);
    if (!$d) continue;

    $auditResults['total_helmets']++;
    $id    = $d['id'] ?? $bn;
    $title = $d['title'] ?? $bn;

    $t1Score = 0; $t1Max = 5;
    $t2Score = 0; $t2Max = 5;
    $t3Score = 0; $t3Max = 5;
    $t4Score = 0; $t4Max = 5;

    // TIER 1: Core Identity
    if (!empty($d['title'])) $t1Score++;
    if (!empty($d['brand'])) $t1Score++;
    if (!empty($d['type'])) $t1Score++;
    if (!empty($d['head_shape'])) $t1Score++;
    if (!empty($d['model_year'])) $t1Score++;

    // TIER 2: Direct Specs
    if (!empty($d['specs']['weight_g']) && $d['specs']['weight_g'] > 500) $t2Score++;
    if (!empty($d['specs']['material'])) $t2Score++;
    if (!empty($d['specs']['warranty_years'])) $t2Score++;
    if (!empty($d['specs']['strap_type'])) $t2Score++;
    if (!empty($d['specs']['shell_sizes_count'])) $t2Score++;

    // TIER 3: Safety Intelligence & Aero-Acoustic
    if (!empty($d['safety_intelligence']['homologation_standard'])) $t3Score++;
    if (isset($d['safety_intelligence']['sharp_rating']) && $d['safety_intelligence']['sharp_rating'] !== null) $t3Score++;
    if (isset($d['safety_intelligence']['rotational_mitigation']) && $d['safety_intelligence']['rotational_mitigation'] !== false) $t3Score++;
    if (!empty($d['aero_acoustic_profile']['noise_db_at_100kph'])) $t3Score++;
    if (!empty($d['aero_acoustic_profile']['drag_coefficient'])) $t3Score++;

    // TIER 4: Page UX & Content
    if (!empty($d['technical_analysis'])) $t4Score++;
    if (!empty($d['marketing_description'])) $t4Score++;
    if (!empty($d['sizing_fit']['fit_notes'])) $t4Score++;
    if (!empty($d['yoast_metadesc'])) $t4Score++;
    if (!empty($d['geo_media']) || !empty($d['marketplace_links'])) $t4Score++;

    $totalScore = $t1Score + $t2Score + $t3Score + $t4Score;
    $maxScore   = $t1Max + $t2Max + $t3Max + $t4Max; // 20
    $pctScore   = round(($totalScore / $maxScore) * 100);

    if ($pctScore === 100) {
        $auditResults['score_distribution']['100%']++;
    } elseif ($pctScore >= 90) {
        $auditResults['score_distribution']['90-99%']++;
    } elseif ($pctScore >= 75) {
        $auditResults['score_distribution']['75-89%']++;
    } else {
        $auditResults['score_distribution']['<75%']++;
    }

    if ($t1Score === $t1Max) $auditResults['tier1_identity']['pass']++; else $auditResults['tier1_identity']['fail']++;
    if ($t2Score === $t2Max) $auditResults['tier2_specs']['pass']++; else $auditResults['tier2_specs']['fail']++;
    if ($t3Score === $t3Max) $auditResults['tier3_safety']['pass']++; else $auditResults['tier3_safety']['fail']++;
    if ($t4Score === $t4Max) $auditResults['tier4_page_ux']['pass']++; else $auditResults['tier4_page_ux']['fail']++;
}

$total = $auditResults['total_helmets'];

echo "📊 MULTI-TIER HELMET AUDIT SUMMARY ($total Helmets Audited)\n";
echo "========================================================\n\n";

echo "1. Tier 1 (Core Product Identity):\n";
echo "   - 100% Pass Rate : {$auditResults['tier1_identity']['pass']} helmets (" . round(($auditResults['tier1_identity']['pass'] / $total) * 100, 1) . "%)\n";

echo "\n2. Tier 2 (Direct Technical Specs & Materials):\n";
echo "   - 100% Pass Rate : {$auditResults['tier2_specs']['pass']} helmets (" . round(($auditResults['tier2_specs']['pass'] / $total) * 100, 1) . "%)\n";

echo "\n3. Tier 3 (Safety Intelligence & Aero-Acoustic Metrics):\n";
echo "   - 100% Pass Rate : {$auditResults['tier3_safety']['pass']} helmets (" . round(($auditResults['tier3_safety']['pass'] / $total) * 100, 1) . "%)\n";

echo "\n4. Tier 4 (Single Page UX & Editorial Content):\n";
echo "   - 100% Pass Rate : {$auditResults['tier4_page_ux']['pass']} helmets (" . round(($auditResults['tier4_page_ux']['pass'] / $total) * 100, 1) . "%)\n";

echo "\n🏆 OVERALL PAGE COMPLETENESS SCORE DISTRIBUTION:\n";
foreach ($auditResults['score_distribution'] as $tier => $count) {
    $pct = round(($count / $total) * 100, 1);
    echo sprintf("   - %-10s : %4d helmets (%5.1f%%)\n", $tier, $count, $pct);
}

$reportFile = $rootDir . '/logs/multi_tier_helmet_audit_report.json';
file_put_contents($reportFile, json_encode($auditResults, JSON_PRETTY_PRINT));

$execTime = round((microtime(true) - $startTime) * 1000, 2);
echo "\n========================================================\n";
echo "✅ Multi-Tier Memory Audit Complete in {$execTime} ms. Report saved to logs/multi_tier_helmet_audit_report.json\n";
echo "========================================================\n";
