<?php
/**
 * Helmetsan Memory Mapping & Data Dump Generator
 * 
 * Audits all catalog records (helmets, brands, accessories), cross-checks missing data,
 * builds relationship graphs, and exports a local memory mapping dump to data/memory_mapping_dump.json.
 */

$rootDir        = dirname(__DIR__);
$dataDir        = $rootDir . '/data';
$helmetFiles    = glob($dataDir . '/helmets/*.json') ?: [];
$brandFiles     = glob($dataDir . '/brands/*.json') ?: [];
$accessoryFiles = glob($dataDir . '/accessories/*.json') ?: [];

echo "========================================================\n";
echo "🧠 HELMETSAN MEMORY MAPPING & DUMP GENERATOR\n";
echo "========================================================\n";
echo "⚡ Helmets     : " . count($helmetFiles) . " files\n";
echo "⚡ Brands      : " . count($brandFiles) . " files\n";
echo "⚡ Accessories : " . count($accessoryFiles) . " files\n";
echo "========================================================\n\n";

$mapping = [
    'generated_at' => date('c'),
    'summary' => [
        'helmets_total'     => count($helmetFiles),
        'brands_total'      => count($brandFiles),
        'accessories_total' => count($accessoryFiles),
    ],
    'brands_index' => [],
    'helmets_index' => [],
    'accessories_index' => [],
    'missing_data_audit' => [
        'helmets' => [
            'missing_model_year'        => 0,
            'missing_asin'              => 0,
            'missing_rotational'        => 0,
            'missing_speaker_pocket'    => 0,
            'missing_fit_notes'         => 0,
            'missing_sharp_rating'      => 0,
        ],
        'accessories' => [
            'missing_brand'             => 0,
            'missing_price'             => 0,
            'missing_image'             => 0,
            'missing_compat_brands'     => 0,
        ],
    ],
    'relations' => [
        'brand_to_helmets_count' => [],
        'helmet_to_accessories_count' => [],
    ],
];

// Helper to sanitize slug
function build_slug($str) {
    $str = strtolower(trim((string)$str));
    return preg_replace('/[^a-z0-9]+/', '-', $str);
}

// 1. Process Brands
foreach ($brandFiles as $file) {
    $bn = basename($file, '.json');
    if ($bn === 'master.example' || $bn === 'master') continue;
    $d = json_decode(file_get_contents($file), true);
    if (!$d) continue;

    $bName   = $d['title'] ?? $d['name'] ?? $d['brand_name'] ?? ucfirst($bn);
    $slug    = $d['id'] ?? $d['slug'] ?? build_slug($bName);
    $country = $d['profile']['origin_country'] ?? $d['country_of_origin'] ?? 'International';
    $founded = $d['profile']['founded_year'] ?? $d['year_established'] ?? 'Unknown';

    $mapping['brands_index'][$slug] = [
        'name'        => $bName,
        'country'     => $country,
        'established' => $founded,
        'tier'        => $d['brand_tier'] ?? 'Standard',
        'helmets'     => 0,
    ];
}

// 2. Process Helmets
foreach ($helmetFiles as $file) {
    $bn = basename($file);
    if ($bn === 'master.example.json' || $bn === 'master.json') continue;
    $d = json_decode(file_get_contents($file), true);
    if (!$d) continue;

    $id        = $d['id'] ?? $bn;
    $title     = $d['title'] ?? 'Untitled Helmet';
    $brand     = $d['brand'] ?? 'Unknown';
    $brandSlug = build_slug($brand);

    // Audit missing fields
    if (empty($d['model_year'])) $mapping['missing_data_audit']['helmets']['missing_model_year']++;
    if (empty($d['identifiers']['asin'])) $mapping['missing_data_audit']['helmets']['missing_asin']++;
    if (empty($d['safety_intelligence']['rotational_mitigation'])) $mapping['missing_data_audit']['helmets']['missing_rotational']++;
    if (empty($d['tech_integration']['speaker_pocket_depth_mm'])) $mapping['missing_data_audit']['helmets']['missing_speaker_pocket']++;
    if (empty($d['sizing_fit']['fit_notes'])) $mapping['missing_data_audit']['helmets']['missing_fit_notes']++;
    if (empty($d['safety_intelligence']['sharp_rating'])) $mapping['missing_data_audit']['helmets']['missing_sharp_rating']++;

    // Update brand helmet count
    if (isset($mapping['brands_index'][$brandSlug])) {
        $mapping['brands_index'][$brandSlug]['helmets']++;
    }

    $mapping['helmets_index'][$id] = [
        'title'        => $title,
        'brand'        => $brand,
        'type'         => $d['type'] ?? 'Full Face',
        'homologation' => $d['safety_intelligence']['homologation_standard'] ?? 'ECE 22.06',
        'weight_g'     => $d['specs']['weight_g'] ?? 1450,
        'sharp'        => $d['safety_intelligence']['sharp_rating'] ?? null,
    ];
}

// 3. Process Accessories
foreach ($accessoryFiles as $file) {
    $bn = basename($file);
    if ($bn === 'master.example.json' || $bn === 'master.json') continue;
    $d = json_decode(file_get_contents($file), true);
    if (!$d) continue;

    $id       = $d['id'] ?? $bn;
    $title    = $d['title'] ?? 'Untitled Accessory';
    $cat      = $d['accessory_parent_category'] ?? 'General';
    $accBrand = $d['accessory_brand'] ?? '';
    $rawPrice = $d['price_json'] ?? null;
    $priceArr = is_string($rawPrice) ? json_decode($rawPrice, true) : (is_array($rawPrice) ? $rawPrice : null);
    $price    = is_array($priceArr) ? ($priceArr['current'] ?? null) : null;
    $img      = $d['image_url'] ?? '';
    $rawComp  = $d['compatible_brands_json'] ?? null;
    $cBrands  = is_string($rawComp) ? json_decode($rawComp, true) : (is_array($rawComp) ? $rawComp : []);

    if (empty($accBrand)) $mapping['missing_data_audit']['accessories']['missing_brand']++;
    if (empty($price)) $mapping['missing_data_audit']['accessories']['missing_price']++;
    if (empty($img)) $mapping['missing_data_audit']['accessories']['missing_image']++;
    if (empty($cBrands)) $mapping['missing_data_audit']['accessories']['missing_compat_brands']++;

    $mapping['accessories_index'][$id] = [
        'title'             => $title,
        'category'          => $cat,
        'brand'             => $accBrand,
        'price'             => $price,
        'compatible_brands' => $cBrands,
    ];
}

// Output to dump JSON
$dumpPath = $dataDir . '/memory_mapping_dump.json';
file_put_contents($dumpPath, json_encode($mapping, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

echo "✅ Saved memory mapping dump to: $dumpPath\n";
echo "📊 Summary Report:\n";
echo "   - Total Helmets indexed     : " . count($mapping['helmets_index']) . "\n";
echo "   - Total Brands indexed      : " . count($mapping['brands_index']) . "\n";
echo "   - Total Accessories indexed : " . count($mapping['accessories_index']) . "\n";
echo "   - Helmets missing model_year: {$mapping['missing_data_audit']['helmets']['missing_model_year']}\n";
echo "   - Helmets missing ASIN      : {$mapping['missing_data_audit']['helmets']['missing_asin']}\n";
echo "   - Helmets missing rotational: {$mapping['missing_data_audit']['helmets']['missing_rotational']}\n";
echo "   - Accessories missing image : {$mapping['missing_data_audit']['accessories']['missing_image']}\n";
