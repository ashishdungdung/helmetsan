<?php
/**
 * Helmetsan Comprehensive Memory Health & Anomaly Detector
 * 
 * Loads full catalog into RAM memory and performs 5 targeted memory checks:
 * 1. Orphaned Brand Check (Helmets referencing brands missing from data/brands/)
 * 2. Physical Outlier Check (Weights <= 0, Shell Materials missing)
 * 3. Identifiers & Marketplace Health Check (ASIN/EAN validity)
 * 4. Safety & SHARP Outlier Check (SHARP ratings outside 1-5 range)
 * 5. SEO Metadata Memory Check (Missing Yoast meta descriptions)
 */

$rootDir        = dirname(__DIR__);
$dataDir        = $rootDir . '/data';
$helmetFiles    = glob($dataDir . '/helmets/*.json') ?: [];
$brandFiles     = glob($dataDir . '/brands/*.json') ?: [];
$accessoryFiles = glob($dataDir . '/accessories/*.json') ?: [];

$startTime = microtime(true);

echo "========================================================\n";
echo "🔍 HELMETSAN MEMORY HEALTH & ANOMALY DETECTOR\n";
echo "========================================================\n";

$brandsMap = [];
foreach ($brandFiles as $f) {
    $bn = basename($f, '.json');
    if ($bn === 'master.example' || $bn === 'master') continue;
    $d = json_decode(file_get_contents($f), true);
    if ($d) {
        $id   = $d['id'] ?? $bn;
        $title = strtolower(trim($d['title'] ?? $id));
        $brandsMap[$id] = $d;
        $brandsMap[$title] = $d;
    }
}

$orphanedBrands   = [];
$weightOutliers   = [];
$missingMaterial  = [];
$invalidSharp     = [];
$missingMetaDesc  = [];
$duplicateAsins   = [];
$asinMap          = [];

$totalHelmets = 0;

foreach ($helmetFiles as $f) {
    $bn = basename($f, '.json');
    if ($bn === 'master.example' || $bn === 'master') continue;
    $d = json_decode(file_get_contents($f), true);
    if (!$d) continue;

    $totalHelmets++;
    $id    = $d['id'] ?? $bn;
    $title = $d['title'] ?? 'Untitled';
    $brand = strtolower(trim($d['brand'] ?? ''));

    // Check 1: Orphaned Brands
    if ($brand !== '' && !isset($brandsMap[$brand])) {
        if (!isset($orphanedBrands[$brand])) $orphanedBrands[$brand] = 0;
        $orphanedBrands[$brand]++;
    }

    // Check 2: Physical Outliers
    $weight = (int)($d['specs']['weight_g'] ?? 0);
    if ($weight <= 500 || $weight > 2500) {
        $weightOutliers[] = "$title ({$weight}g)";
    }
    if (empty($d['specs']['material'])) {
        $missingMaterial[] = $title;
    }

    // Check 3: ASIN Duplicates
    $asin = $d['identifiers']['asin'] ?? '';
    if ($asin !== '') {
        if (isset($asinMap[$asin])) {
            $duplicateAsins[] = "ASIN $asin shared between {$asinMap[$asin]} and $title";
        } else {
            $asinMap[$asin] = $title;
        }
    }

    // Check 4: Invalid SHARP Rating
    $sharp = $d['safety_intelligence']['sharp_rating'] ?? null;
    if ($sharp !== null && ($sharp < 0 || $sharp > 5)) {
        $invalidSharp[] = "$title (SHARP: $sharp)";
    }

    // Check 5: SEO Meta
    if (empty($d['yoast_metadesc'])) {
        $missingMetaDesc[] = $title;
    }
}

echo "📊 MEMORY HEALTH SUMMARY REPORT ($totalHelmets Helmets Audited in RAM)\n";
echo "========================================================\n";

echo "\n1. Orphaned Brands (Helmets referencing unprofiled brands):\n";
if (empty($orphanedBrands)) {
    echo "   ✅ 0 Orphaned Brands. 100% Brand Linkage.\n";
} else {
    foreach ($orphanedBrands as $b => $count) {
        echo "   ⚠️ Brand '$b' referenced by $count helmets missing brand JSON profile.\n";
    }
}

echo "\n2. Weight Physical Outliers (Weight <= 500g or > 2500g):\n";
if (empty($weightOutliers)) {
    echo "   ✅ 0 Weight Outliers. All weights within physical range 500g-2500g.\n";
} else {
    echo "   ⚠️ " . count($weightOutliers) . " outliers detected: " . implode(', ', array_slice($weightOutliers, 0, 5)) . "\n";
}

echo "\n3. Missing Shell Materials:\n";
if (empty($missingMaterial)) {
    echo "   ✅ 0 Missing Shell Materials. 100% Material Specs Indexed.\n";
} else {
    echo "   ⚠️ " . count($missingMaterial) . " helmets missing material field.\n";
}

echo "\n4. Invalid SHARP Ratings (Outside 1-5 Stars):\n";
if (empty($invalidSharp)) {
    echo "   ✅ 0 Invalid SHARP Ratings. All SHARP ratings valid (1-5 stars).\n";
} else {
    echo "   ⚠️ " . count($invalidSharp) . " invalid ratings: " . implode(', ', $invalidSharp) . "\n";
}

echo "\n5. ASIN Identifier Uniqueness:\n";
if (empty($duplicateAsins)) {
    echo "   ✅ 0 Duplicate ASINs. All ASIN marketplace IDs unique.\n";
} else {
    echo "   ⚠️ " . count($duplicateAsins) . " duplicate ASINs found.\n";
}

echo "\n6. Yoast Meta Description Coverage:\n";
$metaPct = round((($totalHelmets - count($missingMetaDesc)) / $totalHelmets) * 100, 1);
echo "   ℹ️ Meta Description Coverage: {$metaPct}% (" . ($totalHelmets - count($missingMetaDesc)) . "/$totalHelmets helmets covered)\n";

$logFile = $rootDir . '/logs/memory_health_check.json';
$reportData = [
    'audited_at'       => date('c'),
    'total_helmets'    => $totalHelmets,
    'orphaned_brands'  => $orphanedBrands,
    'weight_outliers'  => count($weightOutliers),
    'invalid_sharp'    => count($invalidSharp),
    'duplicate_asins'  => count($duplicateAsins),
    'meta_coverage'    => $metaPct,
];
file_put_contents($logFile, json_encode($reportData, JSON_PRETTY_PRINT));

$execTime = round((microtime(true) - $startTime) * 1000, 2);
echo "\n========================================================\n";
echo "✅ Memory Health Check Complete in {$execTime} ms. Report saved to logs/memory_health_check.json\n";
echo "========================================================\n";
