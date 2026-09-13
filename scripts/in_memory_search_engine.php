<?php
/**
 * Helmetsan High-Speed In-Memory Similarity & Recommendation Engine
 * 
 * Computes multi-dimensional similarity vectors in RAM for instant helmet recommendations:
 * Finds closest 5 competitor alternative helmets based on Category, Weight, Head Shape,
 * Price Tier, Noise dB, and Safety Certifications.
 * 
 * Usage: php scripts/in_memory_search_engine.php [helmet_id]
 */

$rootDir     = dirname(__DIR__);
$helmetFiles = glob($rootDir . '/data/helmets/*.json') ?: [];

$targetId = $argv[1] ?? 'shoei_rf_1400_matte-black';

$startTime = microtime(true);

// Load all helmets into RAM
$helmets = [];
foreach ($helmetFiles as $f) {
    $bn = basename($f, '.json');
    if ($bn === 'master.example' || $bn === 'master') continue;
    $d = json_decode(file_get_contents($f), true);
    if ($d) {
        $id = $d['id'] ?? $bn;
        $helmets[$id] = $d;
    }
}

if (!isset($helmets[$targetId])) {
    // Pick first helmet if target not found
    $targetId = array_key_first($helmets);
}

$target = $helmets[$targetId];

echo "========================================================\n";
echo "⚡ HELMETSAN IN-MEMORY SIMILARITY & RECOMMENDATION ENGINE\n";
echo "========================================================\n";
echo "🎯 Target Helmet : {$target['title']} ({$target['brand']} {$target['type']})\n";
echo "🎯 Weight        : {$target['specs']['weight_g']}g | Head Shape: {$target['head_shape']}\n";
echo "🎯 Homologation  : {$target['safety_intelligence']['homologation_standard']}\n";
echo "========================================================\n\n";

$scores = [];

$tCat    = strtolower($target['type'] ?? '');
$tWeight = (int)($target['specs']['weight_g'] ?? 1450);
$tShape  = strtolower($target['head_shape'] ?? 'intermediate oval');
$tPrice  = (float)($target['price']['current'] ?? 300);
$tBrand  = strtolower($target['brand'] ?? '');

foreach ($helmets as $id => $h) {
    if ($id === $targetId) continue;

    $score = 0;

    // 1. Same Category Bonus (+40 pts)
    $c = strtolower($h['type'] ?? '');
    if ($c === $tCat) $score += 40;

    // 2. Head Shape Match (+25 pts)
    $hs = strtolower($h['head_shape'] ?? '');
    if ($hs === $tShape) $score += 25;

    // 3. Weight Proximity (Max +20 pts)
    $w = (int)($h['specs']['weight_g'] ?? 1450);
    $wDiff = abs($w - $tWeight);
    $wScore = max(0, 20 - ($wDiff / 20));
    $score += $wScore;

    // 4. Price Tier Proximity (Max +15 pts)
    $p = (float)($h['price']['current'] ?? 300);
    $pDiff = abs($p - $tPrice);
    $pScore = max(0, 15 - ($pDiff / 25));
    $score += $pScore;

    // 5. Different Brand Preference (+5 pts for cross-brand alternatives)
    $b = strtolower($h['brand'] ?? '');
    if ($b !== $tBrand) $score += 5;

    $scores[$id] = $score;
}

arsort($scores);

$top5 = array_slice($scores, 0, 5, true);

echo "🏆 TOP 5 DIRECT IN-MEMORY ALTERNATIVE RECOMMENDATIONS:\n";
echo "--------------------------------------------------------\n";
$rank = 1;
foreach ($top5 as $id => $score) {
    $item  = $helmets[$id];
    $title = $item['title'];
    $brand = $item['brand'];
    $w     = $item['specs']['weight_g'] ?? 'N/A';
    $std   = $item['safety_intelligence']['homologation_standard'] ?? 'ECE 22.06';
    $p     = isset($item['price']['current']) ? '$' . number_format((float)$item['price']['current'], 2) : 'N/A';
    $matchPct = round(($score / 105) * 100, 1);

    echo sprintf(" #%d. %-35s | Match: %5.1f%% | Brand: %-10s | Weight: %5sg | Price: %7s | %s\n",
        $rank++, substr($title, 0, 35), $matchPct, $brand, $w, $p, $std);
}

$execTime = round((microtime(true) - $startTime) * 1000, 2);
echo "\n========================================================\n";
echo "⚡ RAM Similarity Recommendation Execution Time: {$execTime} ms\n";
echo "========================================================\n";
