<?php
/**
 * Helmetsan All-Helmets In-Memory Analytical Matrix
 * 
 * Performs 8 comprehensive statistical & engineering passes on all 2,235 helmets in RAM:
 * 1. Brand Market Distribution
 * 2. Category / Form-Factor Breakdown
 * 3. Head Shape Anatomical Fit Matrix
 * 4. Shell Material Composition Engineering
 * 5. Weight Spectrum & Category Benchmarks
 * 6. Price Tier Distribution (Budget / Mid / Premium / Flagship)
 * 7. Safety Standard & Homologation Matrix
 * 8. Tech Integration & Feature Readiness (EQRS, Pinlock, Comms)
 */

$rootDir     = dirname(__DIR__);
$helmetFiles = glob($rootDir . '/data/helmets/*.json') ?: [];

$startTime = microtime(true);

echo "========================================================\n";
echo "🧠 HELMETSAN ALL-HELMETS IN-MEMORY ANALYTICAL MATRIX\n";
echo "========================================================\n";

$helmets = [];
foreach ($helmetFiles as $f) {
    $bn = basename($f, '.json');
    if ($bn === 'master.example' || $bn === 'master') continue;
    $d = json_decode(file_get_contents($f), true);
    if ($d) {
        $helmets[$d['id'] ?? $bn] = $d;
    }
}

$total = count($helmets);
$loadTime = round((microtime(true) - $startTime) * 1000, 2);
echo "✅ Loaded $total helmets into RAM memory array in {$loadTime} ms.\n\n";

// --- PASS 1: BRAND MARKET DISTRIBUTION ---
$brands = [];
foreach ($helmets as $h) {
    $b = $h['brand'] ?? 'Unknown';
    if (!isset($brands[$b])) $brands[$b] = 0;
    $brands[$b]++;
}
arsort($brands);

// --- PASS 2: CATEGORY BREAKDOWN ---
$categories = [];
foreach ($helmets as $h) {
    $c = $h['type'] ?? 'Unspecified';
    if (!isset($categories[$c])) $categories[$c] = 0;
    $categories[$c]++;
}
arsort($categories);

// --- PASS 3: HEAD SHAPE FIT MATRIX ---
$headShapes = [];
foreach ($helmets as $h) {
    $hs = $h['head_shape'] ?? $h['sizing_fit']['head_shape'] ?? 'Intermediate Oval';
    if (!isset($headShapes[$hs])) $headShapes[$hs] = 0;
    $headShapes[$hs]++;
}
arsort($headShapes);

// --- PASS 4: SHELL MATERIAL COMPOSITION ---
$materials = [];
foreach ($helmets as $h) {
    $m = $h['specs']['material'] ?? 'Thermoplastic Polycarbonate';
    if (!isset($materials[$m])) $materials[$m] = 0;
    $materials[$m]++;
}
arsort($materials);

// --- PASS 5: WEIGHT SPECTRUM PER CATEGORY ---
$weightStats = [];
foreach ($helmets as $h) {
    $type   = $h['type'] ?? 'Full Face';
    $weight = (int)($h['specs']['weight_g'] ?? 0);
    if ($weight > 0) {
        if (!isset($weightStats[$type])) $weightStats[$type] = [];
        $weightStats[$type][] = $weight;
    }
}

// --- PASS 6: PRICE TIER BREAKDOWN ---
$priceTiers = [
    'Budget (< $150)'          => 0,
    'Mid-Tier ($150 - $400)'   => 0,
    'Premium ($400 - $800)'    => 0,
    'Ultra-Flagship ($800+)'   => 0,
    'Unspecified Price'        => 0,
];
foreach ($helmets as $h) {
    $priceJson = $h['price_json'] ?? null;
    $priceArr  = is_string($priceJson) ? json_decode($priceJson, true) : (is_array($priceJson) ? $priceJson : null);
    $price     = is_array($priceArr) ? ($priceArr['current'] ?? null) : ($h['price']['current'] ?? null);

    if ($price === null || !is_numeric($price) || $price <= 0) {
        $priceTiers['Unspecified Price']++;
    } elseif ($price < 150) {
        $priceTiers['Budget (< $150)']++;
    } elseif ($price <= 400) {
        $priceTiers['Mid-Tier ($150 - $400)']++;
    } elseif ($price <= 800) {
        $priceTiers['Premium ($400 - $800)']++;
    } else {
        $priceTiers['Ultra-Flagship ($800+)']++;
    }
}

// --- PASS 7: SAFETY STANDARDS MATRIX ---
$safetyStds = [];
foreach ($helmets as $h) {
    $std = $h['safety_intelligence']['homologation_standard'] ?? 'Unspecified';
    if (!isset($safetyStds[$std])) $safetyStds[$std] = 0;
    $safetyStds[$std]++;
}
arsort($safetyStds);

// --- PASS 8: TECH INTEGRATION, SAFETY FEATURES & CONTENT AUDIT ---
$techStats = [
    'pinlock_ready'         => 0,
    'eqrs_system'           => 0,
    'sun_visor_integrated'  => 0,
    'speaker_cutouts'       => 0,
    'rotational_mitigation' => 0,
    'technical_analysis'    => 0,
    'aero_acoustic_profile' => 0,
    'fitment_coordinates'   => 0,
    'yoast_metadesc'        => 0,
];

foreach ($helmets as $h) {
    // Pinlock check across JSON structures
    $jsonStr = strtolower(json_encode($h));
    if (stripos($jsonStr, 'pinlock') !== false) {
        $techStats['pinlock_ready']++;
    }

    // EQRS check
    if (stripos($jsonStr, 'eqrs') !== false || stripos($jsonStr, 'emergency release') !== false || stripos($jsonStr, 'emergency quick release') !== false) {
        $techStats['eqrs_system']++;
    }

    // Sun visor check
    if (stripos($jsonStr, 'sun visor') !== false || stripos($jsonStr, 'drop-down visor') !== false || !empty($h['specs']['sun_visor_integrated'])) {
        $techStats['sun_visor_integrated']++;
    }

    // Speaker cutouts check
    if (!empty($h['tech_integration']['comms_cutout_type']) && $h['tech_integration']['comms_cutout_type'] !== 'None') {
        $techStats['speaker_cutouts']++;
    }

    // Rotational MIPS check
    if (!empty($h['safety_intelligence']['rotational_mitigation']) && $h['safety_intelligence']['rotational_mitigation'] !== false && strtolower((string)$h['safety_intelligence']['rotational_mitigation']) !== 'none') {
        $techStats['rotational_mitigation']++;
    }

    // Content Depth Checks
    if (!empty($h['technical_analysis']) || !empty($h['marketing_description'])) {
        $techStats['technical_analysis']++;
    }
    if (!empty($h['aero_acoustic_profile']['noise_db_at_100kph'])) {
        $techStats['aero_acoustic_profile']++;
    }
    if (!empty($h['fitment_coordinates']['internal_length_mm'])) {
        $techStats['fitment_coordinates']++;
    }
    if (!empty($h['yoast_metadesc'])) {
        $techStats['yoast_metadesc']++;
    }
}

// OUTPUT REPORT
$md = "# Helmetsan Global Catalog In-Memory Analytical Matrix\n\n";
$md .= "**Total Helmets Analysed:** `$total`\n";
$md .= "**Memory Load Time:** `{$loadTime} ms`\n";
$md .= "**Execution Timestamp:** `" . date('Y-m-d H:i:s') . "`\n\n";

$md .= "## 1. Top Manufacturer Market Distribution\n\n";
$md .= "| Manufacturer | Helmet Count | Catalog Share |\n| :--- | :--- | :--- |\n";
$topCount = 0;
foreach ($brands as $b => $count) {
    $pct = round(($count / $total) * 100, 1);
    $md .= "| **$b** | `$count` | `$pct%` |\n";
    if (++$topCount >= 15) break;
}

$md .= "\n## 2. Category / Form-Factor Breakdown\n\n";
$md .= "| Category | Count | Percentage |\n| :--- | :--- | :--- |\n";
foreach ($categories as $cat => $count) {
    $pct = round(($count / $total) * 100, 1);
    $md .= "| **$cat** | `$count` | `$pct%` |\n";
}

$md .= "\n## 3. Head Shape Anatomical Fit Matrix\n\n";
$md .= "| Head Shape | Count | Share |\n| :--- | :--- | :--- |\n";
foreach ($headShapes as $hs => $count) {
    $pct = round(($count / $total) * 100, 1);
    $md .= "| **$hs** | `$count` | `$pct%` |\n";
}

$md .= "\n## 4. Shell Material Engineering Composition\n\n";
$md .= "| Shell Material | Count | Share |\n| :--- | :--- | :--- |\n";
$matCount = 0;
foreach ($materials as $m => $count) {
    $pct = round(($count / $total) * 100, 1);
    $md .= "| **$m** | `$count` | `$pct%` |\n";
    if (++$matCount >= 10) break;
}

$md .= "\n## 5. Category Weight Spectrum Benchmarks\n\n";
$md .= "| Category | Sample Count | Average Weight | Median Weight | Weight Range |\n| :--- | :--- | :--- | :--- | :--- |\n";
foreach ($weightStats as $cat => $arr) {
    if (count($arr) === 0) continue;
    sort($arr);
    $avg = round(array_sum($arr) / count($arr));
    $med = $arr[(int)(count($arr) / 2)];
    $min = min($arr);
    $max = max($arr);
    $md .= "| **$cat** | `" . count($arr) . "` | `{$avg}g` | `{$med}g` | `{$min}g - {$max}g` |\n";
}

$md .= "\n## 6. Price Tier Breakdown\n\n";
$md .= "| Price Tier | Helmet Count | Share |\n| :--- | :--- | :--- |\n";
foreach ($priceTiers as $tier => $count) {
    $pct = round(($count / $total) * 100, 1);
    $md .= "| **$tier** | `$count` | `$pct%` |\n";
}

$md .= "\n## 7. Homologation Safety Standards\n\n";
$md .= "| Safety Standard | Count | Share |\n| :--- | :--- | :--- |\n";
$stdCount = 0;
foreach ($safetyStds as $std => $count) {
    $pct = round(($count / $total) * 100, 1);
    $md .= "| **$std** | `$count` | `$pct%` |\n";
    if (++$stdCount >= 10) break;
}

$md .= "\n## 8. Content Depth & Feature Readiness Audit\n\n";
$md .= "| Feature / Content Depth | Equipped Count | Coverage |\n| :--- | :--- | :--- |\n";
foreach ($techStats as $feat => $count) {
    $pct = round(($count / $total) * 100, 1);
    $lbl = ucwords(str_replace('_', ' ', $feat));
    $md .= "| **$lbl** | `$count` | `$pct%` |\n";
}

$reportFile = $rootDir . '/logs/all_helmets_memory_analysis.md';
file_put_contents($reportFile, $md);

$execTime = round((microtime(true) - $startTime) * 1000, 2);
echo "========================================================\n";
echo "✅ Analysis complete in {$execTime} ms! Report saved to logs/all_helmets_memory_analysis.md\n";
echo "========================================================\n";
