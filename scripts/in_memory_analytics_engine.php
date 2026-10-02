<?php

ini_set('memory_limit', '512M');
/**
 * Helmetsan In-Memory Analytical Engine
 * 
 * Loads all 2,218 helmet JSONs, 60 brand JSONs, and 26 accessory JSONs directly into RAM.
 * Performs high-speed in-memory multi-dimensional statistical analysis:
 * - Homologation Distribution by Origin Country
 * - Category Weight Benchmarks (Mean / Min / Max / Outliers)
 * - Communications Cutout & Speaker Depth Index
 * - Direct Helmet ↔ Accessory In-Memory Fitment Graph Mapping
 */

$rootDir        = dirname(__DIR__);
$dataDir        = $rootDir . '/data';
$helmetFiles    = glob($dataDir . '/helmets/*.json') ?: [];
$brandFiles     = glob($dataDir . '/brands/*.json') ?: [];
$accessoryFiles = glob($dataDir . '/accessories/*.json') ?: [];

$startTime = microtime(true);

echo "========================================================\n";
echo "🧠 HELMETSAN IN-MEMORY ANALYTICAL ENGINE\n";
echo "========================================================\n";
echo "⚡ Loading dataset into RAM memory structures...\n";

// RAM Data Cache
$RAM = [
    'helmets'     => [],
    'brands'      => [],
    'accessories' => [],
];

// Load Brands into Memory
foreach ($brandFiles as $f) {
    $bn = basename($f, '.json');
    if ($bn === 'master.example' || $bn === 'master') continue;
    $d = json_decode(file_get_contents($f), true);
    if ($d) {
        $id = $d['id'] ?? $bn;
        $RAM['brands'][$id] = $d;
    }
}

// Load Helmets into Memory
foreach ($helmetFiles as $f) {
    $bn = basename($f, '.json');
    if ($bn === 'master.example' || $bn === 'master') continue;
    $d = json_decode(file_get_contents($f), true);
    if ($d) {
        $id = $d['id'] ?? $bn;
        $RAM['helmets'][$id] = $d;
    }
}

// Load Accessories into Memory
foreach ($accessoryFiles as $f) {
    $bn = basename($f, '.json');
    if ($bn === 'master.example' || $bn === 'master') continue;
    $d = json_decode(file_get_contents($f), true);
    if ($d) {
        $id = $d['id'] ?? $bn;
        $RAM['accessories'][$id] = $d;
    }
}

$loadTime = round((microtime(true) - $startTime) * 1000, 2);
echo "✅ Loaded " . count($RAM['helmets']) . " Helmets, " . count($RAM['brands']) . " Brands, and " . count($RAM['accessories']) . " Accessories into RAM in {$loadTime} ms.\n\n";

// --- IN-MEMORY ANALYSIS 1: Homologation & Safety Standard Distribution ---
echo "--------------------------------------------------------\n";
echo "🛡️ IN-MEMORY ANALYSIS 1: Safety Standards & Certification\n";
echo "--------------------------------------------------------\n";
$safetyStats = [];
foreach ($RAM['helmets'] as $h) {
    $std = $h['safety_intelligence']['homologation_standard'] ?? 'Unspecified';
    if (!isset($safetyStats[$std])) $safetyStats[$std] = 0;
    $safetyStats[$std]++;
}
arsort($safetyStats);
foreach ($safetyStats as $std => $count) {
    $pct = round(($count / count($RAM['helmets'])) * 100, 1);
    echo sprintf("   %-20s : %4d helmets (%5.1f%%)\n", $std, $count, $pct);
}

// --- IN-MEMORY ANALYSIS 2: Category Weight Distribution Benchmarks ---
echo "\n--------------------------------------------------------\n";
echo "⚖️ IN-MEMORY ANALYSIS 2: Weight Benchmarks by Category\n";
echo "--------------------------------------------------------\n";
$weightStats = [];
foreach ($RAM['helmets'] as $h) {
    $type   = $h['type'] ?? 'Full Face';
    $weight = (int)($h['specs']['weight_g'] ?? 0);
    if ($weight > 0) {
        if (!isset($weightStats[$type])) {
            $weightStats[$type] = ['weights' => [], 'min' => 9999, 'max' => 0];
        }
        $weightStats[$type]['weights'][] = $weight;
        $weightStats[$type]['min'] = min($weightStats[$type]['min'], $weight);
        $weightStats[$type]['max'] = max($weightStats[$type]['max'], $weight);
    }
}

foreach ($weightStats as $type => $data) {
    $arr   = $data['weights'];
    $avg   = round(array_sum($arr) / count($arr));
    sort($arr);
    $med   = $arr[(int)(count($arr) / 2)];
    echo sprintf("   %-22s | Count: %4d | Avg: %4dg | Med: %4dg | Min: %4dg | Max: %4dg\n",
        $type, count($arr), $avg, $med, $data['min'], $data['max']);
}

// --- IN-MEMORY ANALYSIS 3: Tech Cutout & Audio Integration Index ---
echo "\n--------------------------------------------------------\n";
echo "🎧 IN-MEMORY ANALYSIS 3: Communications & Audio Readiness\n";
echo "--------------------------------------------------------\n";
$techStats = ['cutout_types' => [], 'speaker_depth' => 0, 'hud_ready' => 0];
foreach ($RAM['helmets'] as $h) {
    $cutout = $h['tech_integration']['comms_cutout_type'] ?? 'None';
    if (!isset($techStats['cutout_types'][$cutout])) $techStats['cutout_types'][$cutout] = 0;
    $techStats['cutout_types'][$cutout]++;

    if (!empty($h['tech_integration']['speaker_pocket_depth_mm'])) $techStats['speaker_depth']++;
    if (!empty($h['tech_integration']['hud_ready']) && $h['tech_integration']['hud_ready'] === true) $techStats['hud_ready']++;
}

echo "   Cutout Integration Types:\n";
foreach ($techStats['cutout_types'] as $type => $count) {
    echo sprintf("     - %-25s : %4d helmets\n", $type, $count);
}
echo "   - Speaker Pocket Depth Index : {$techStats['speaker_depth']} helmets with measured mm depth\n";
echo "   - HUD Hardware Ready         : {$techStats['hud_ready']} helmets HUD compatible\n";

// --- IN-MEMORY ANALYSIS 4: Helmet ↔ Accessory Fitment Graph ---
echo "\n--------------------------------------------------------\n";
echo "🔗 IN-MEMORY ANALYSIS 4: Fitment Compatibility Graph\n";
echo "--------------------------------------------------------\n";

$typeAlias = static function (string $t): string {
    $t = strtolower(trim($t));
    return match ($t) {
        'motocross', 'dirt / motocross', 'dirt/mx', 'off-road', 'off road', 'mx', 'dirt' => 'dirt / mx',
        'race', 'track', 'track / race', 'track / circuit', 'circuit' => 'track / race',
        'adventure', 'dual sport', 'dual-sport', 'adventure / dual-sport' => 'adventure / dual sport',
        'modular full face', 'flip-up', 'flip up' => 'modular',
        'jet', 'open-face', 'open face' => 'open face',
        'shorty', 'half helmet' => 'half',
        'touring full face' => 'full face',
        default => $t,
    };
};

$isUniversalBrand = static function (string $b): bool {
    $b = strtolower(trim($b));
    return $b === 'universal' || $b === 'all' || $b === 'all brands';
};

// Normalize slug for id pins: catalog uses underscores, pins often use hyphens.
$slugKey = static function (string $id): string {
    return strtolower(trim(str_replace(['-', ' '], '_', $id)));
};

$fitmentGraph = [];
foreach ($RAM['accessories'] as $accId => $acc) {
    $cBrands = $acc['compatible_brands'] ?? [];
    if (!is_array($cBrands)) {
        $cBrands = [];
    }
    $cTypes = [];
    foreach (($acc['compatible_helmet_types'] ?? []) as $t) {
        $alias = $typeAlias((string) $t);
        if ($alias !== '' && $alias !== 'all') {
            $cTypes[$alias] = true;
        }
    }
    $cTypes = array_keys($cTypes);

    // compatible_helmet_ids are known-good pins (verified fits), NOT a hard
    // allow-list. They only act as a filter when they are the sole constraint.
    $cIds = [];
    foreach (($acc['compatible_helmet_ids'] ?? []) as $id) {
        $k = $slugKey((string) $id);
        if ($k !== '') {
            $cIds[$k] = true;
        }
    }

    $namedBrands = [];
    $explicitUniversal = false;
    foreach ($cBrands as $b) {
        if ($isUniversalBrand((string) $b)) {
            $explicitUniversal = true;
            continue;
        }
        $namedBrands[] = strtolower(trim((string) $b));
    }
    // Universal only when there is NO named brand constraint.
    // [Universal, Shoei, Arai] is marketing fluff — real constraint is the named brands.
    // Explicit Universal + empty types wins even when example id pins are present.
    $universal = $explicitUniversal && $namedBrands === [];
    if ($universal && $cTypes === []) {
        $idsOnly = false;
    } else {
        $idsOnly = ($namedBrands === [] && $cTypes === [] && $cIds !== [] && !$universal);
    }
    if ($namedBrands === [] && $cTypes === [] && $cIds === []) {
        $universal = true;
        $idsOnly = false;
    }

    $matchCount = 0;
    foreach ($RAM['helmets'] as $hId => $h) {
        $hBrand = strtolower(trim((string) ($h['brand'] ?? '')));
        $hType  = $typeAlias((string) ($h['type'] ?? ''));
        $hKey   = $slugKey((string) $hId);

        $brandOk = $universal || in_array($hBrand, $namedBrands, true);
        $typeOk  = $cTypes === [] || in_array($hType, $cTypes, true);
        $idOk    = !$idsOnly || isset($cIds[$hKey]);
        if ($brandOk && $typeOk && $idOk) {
            $matchCount++;
        }
    }
    $title = $acc['title'] ?? $accId;
    $cat   = $acc['accessory_parent_category'] ?? $acc['type'] ?? 'General';
    if ($universal) {
        $brandStr = 'Universal';
    } elseif ($idsOnly) {
        $brandStr = 'pinned ids';
    } else {
        $brandStr = $namedBrands !== []
            ? implode(', ', array_map(static fn ($b) => ucfirst($b), $namedBrands))
            : 'by type';
        if ($cTypes !== []) {
            $brandStr .= ' +types';
        }
    }
    echo sprintf("   %-42s | %-16s | Fits: %-22s | %4d helmets\n", substr($title, 0, 42), substr($cat, 0, 16), substr($brandStr, 0, 22), $matchCount);
}

$totalExecutionTime = round((microtime(true) - $startTime) * 1000, 2);
echo "\n========================================================\n";
echo "⚡ Total In-Memory Analytical Execution Time: {$totalExecutionTime} ms\n";
echo "========================================================\n";
