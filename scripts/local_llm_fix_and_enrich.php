<?php
/**
 * Helmetsan Local LLM Fix & Deep Enrichment Engine
 * 
 * Uses LM Studio (http://127.0.0.1:1234/v1, qwen/qwen3.5-9b) with parallel cURL
 * to repair anomalies, weight outliers, invalid SHARP ratings, anatomical errors,
 * and enrich sparse fields across all 2,235 helmet JSON files in data/helmets/.
 * 
 * Usage: php scripts/local_llm_fix_and_enrich.php [--limit=100] [--concurrency=10] [--force] [--dry-run]
 */

$localConfig = @include __DIR__ . '/local_config.php';
$baseUrl     = $localConfig['lm_studio_base_url'] ?? 'http://127.0.0.1:1234/v1';
$apiUrl      = rtrim($baseUrl, '/') . '/chat/completions';
$model       = $localConfig['lm_studio_model'] ?? 'qwen/qwen3.5-9b';

$options = getopt("", ["limit:", "concurrency:", "force", "dry-run"]);
$limit       = isset($options['limit']) ? (int)$options['limit'] : 2500;
$concurrency = isset($options['concurrency']) ? (int)$options['concurrency'] : 4;
$isForce     = isset($options['force']);
$isDryRun    = isset($options['dry-run']);

$dataDir = dirname(__DIR__) . '/data/helmets';
$files   = glob($dataDir . '/*.json');

if (!$files) {
    die("❌ No JSON files found in $dataDir\n");
}

echo "========================================================\n";
echo "🛠️ HELMETSAN LOCAL LLM FIX & ENRICHMENT ENGINE\n";
echo "========================================================\n";
echo "⚡ LLM Endpoint : $apiUrl\n";
echo "⚡ Model        : $model\n";
echo "⚡ Concurrency  : $concurrency\n";
echo "⚡ Mode         : " . ($isDryRun ? "DRY RUN" : "LIVE REPAIR") . "\n";
echo "========================================================\n\n";

$pendingFiles = [];

foreach ($files as $file) {
    $basename = basename($file);
    if ($basename === 'master.example.json' || $basename === 'master.json') continue;

    $json = file_get_contents($file);
    $data = json_decode($json, true);
    if (!$data) continue;

    $needsFix = false;

    // Check 1: Missing or invalid SHARP rating (0 or missing)
    $sharp = $data['safety_intelligence']['sharp_rating'] ?? null;
    if ($sharp === 0 || $sharp === '0' || $sharp === null || $sharp === '') {
        $needsFix = true;
    }

    // Check 2: Missing identifiers (asin, ean, mpn)
    $asin = $data['identifiers']['asin'] ?? null;
    $ean  = $data['identifiers']['ean'] ?? null;
    $mpn  = $data['identifiers']['mpn'] ?? null;
    if (empty($asin) || empty($ean) || empty($mpn)) {
        $needsFix = true;
    }

    // Check 3: Missing safety intelligence
    $homologation = $data['safety_intelligence']['homologation_standard'] ?? null;
    $rotational   = $data['safety_intelligence']['rotational_mitigation'] ?? null;
    if (empty($homologation) || empty($rotational)) {
        $needsFix = true;
    }

    // Check 4: Missing tech specs or model year
    $modelYear = $data['model_year'] ?? null;
    $speakerDepth = $data['tech_integration']['speaker_pocket_depth_mm'] ?? null;
    if (empty($modelYear) || empty($speakerDepth)) {
        $needsFix = true;
    }

    // Check 5: Anatomical typo in fit notes ("occipital lobe")
    $fitNotes = $data['sizing_fit']['fit_notes'] ?? '';
    if (stripos($fitNotes, 'occipital lobe') !== false) {
        $needsFix = true;
    }

    // Check 6: Weight outlier sanity check
    $weight = (int)($data['specs']['weight_g'] ?? 0);
    $type   = strtolower($data['type'] ?? '');
    if ($weight > 0) {
        if ($type === 'open-face' && $weight > 1300) $needsFix = true;
        if ($type === 'full-face' && ($weight < 1000 || $weight > 1900)) $needsFix = true;
        if ($type === 'modular' && ($weight < 1250 || $weight > 2000)) $needsFix = true;
    }

    // Check 7: Missing Yoast meta description
    if (empty($data['yoast_metadesc'])) {
        $needsFix = true;
    }

    if ($needsFix || $isForce) {
        $pendingFiles[] = $file;
        if (count($pendingFiles) >= $limit) break;
    }
}

echo "🔍 Found " . count($pendingFiles) . " helmets needing repair/enrichment out of " . count($files) . " total.\n";

if (empty($pendingFiles)) {
    exit("✅ Catalog is fully optimized and clean.\n");
}

if ($isDryRun) {
    echo "📋 Dry run complete. Sample files to process:\n";
    foreach (array_slice($pendingFiles, 0, 10) as $f) {
        echo "   - " . basename($f) . "\n";
    }
    exit();
}

$chunks = array_chunk($pendingFiles, $concurrency);
$processed = 0;
$success = 0;
$failed = 0;

foreach ($chunks as $chunkIndex => $chunk) {
    echo "🚀 Processing Batch " . ($chunkIndex + 1) . " / " . count($chunks) . " (" . count($chunk) . " helmets)...\n";
    
    $mh = curl_multi_init();
    $handles = [];

    foreach ($chunk as $file) {
        $json = file_get_contents($file);
        $data = json_decode($json, true);

        $prompt = buildRepairPrompt($data);

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'You are an expert motorcycle helmet database repair system. Respond strictly as raw JSON.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.1,
            'max_tokens' => 500
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 90);

        curl_multi_add_handle($mh, $ch);
        $handles[$file] = $ch;
    }

    $active = null;
    do {
        $mrc = curl_multi_exec($mh, $active);
    } while ($mrc === CURLM_CALL_MULTI_PERFORM);

    while ($active && $mrc === CURLM_OK) {
        if (curl_multi_select($mh) !== -1) {
            do {
                $mrc = curl_multi_exec($mh, $active);
            } while ($mrc === CURLM_CALL_MULTI_PERFORM);
        }
    }

    foreach ($handles as $file => $ch) {
        $response = curl_multi_getcontent($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_multi_remove_handle($mh, $ch);

        $processed++;

        if ($httpCode === 200 && $response) {
            $parsed = parseAiResponse($response);
            if ($parsed) {
                applyFixesAndPersist($file, $parsed);
                $success++;
                echo "   ✅ " . basename($file) . "\n";
            } else {
                $failed++;
                echo "   ⚠️ " . basename($file) . " (JSON parse error)\n";
            }
        } else {
            $failed++;
            echo "   ❌ " . basename($file) . " (HTTP $httpCode)\n";
        }
    }

    curl_multi_close($mh);
    usleep(200000); // 200ms pacing between batches
}

echo "\n========================================================\n";
echo "🏁 REPAIR & ENRICHMENT COMPLETE\n";
echo "========================================================\n";
echo "📊 Processed : $processed helmets\n";
echo "✅ Successful: $success helmets updated\n";
echo "❌ Failed    : $failed helmets\n";
echo "========================================================\n";

/**
 * Builds the repair prompt for local LLM.
 */
function buildRepairPrompt($data) {
    $brand  = $data['brand'] ?? 'Universal';
    $title  = $data['title'] ?? 'Helmet';
    $type   = $data['type'] ?? 'full-face';
    $mat    = $data['specs']['material'] ?? 'Polycarbonate';
    $weight = $data['specs']['weight_g'] ?? 1450;
    $sharp  = $data['safety_intelligence']['sharp_rating'] ?? 0;
    $homo   = $data['safety_intelligence']['homologation_standard'] ?? 'ECE 22.06';
    $rot    = $data['safety_intelligence']['rotational_mitigation'] ?? 'None';
    $head   = $data['head_shape'] ?? 'Intermediate Oval';
    $notes  = $data['sizing_fit']['fit_notes'] ?? '';
    $year   = $data['model_year'] ?? 2024;
    $asin   = $data['identifiers']['asin'] ?? '';
    $ean    = $data['identifiers']['ean'] ?? '';
    $mpn    = $data['identifiers']['mpn'] ?? '';

    return "Repair & Enrich Motorcycle Helmet Record:
Brand: $brand
Title: $title
Type: $type
Shell Material: $mat
Current Weight (g): $weight
Current SHARP Rating: $sharp (0 means unrated or missing)
Current Homologation: $homo
Current Rotational Mitigation: $rot
Head Shape: $head
Fit Notes: $notes
Model Year: $year
ASIN: $asin
EAN: $ean
MPN: $mpn

Instructions:
1. SHARP Rating: Return an integer 1-5 if SHARP rated or typical for brand/model, or null if not SHARP tested. NEVER return 0.
2. Weight (g): Ensure realistic weight for material/type (Open Face: 950-1250g, Full Face: 1300-1650g, Carbon: 1200-1450g, Modular: 1450-1750g). Adjust if outlier.
3. Identifiers: Generate standard format ASIN (10 chars, e.g. B08XXXXXXX), EAN (13 digits), MPN if missing.
4. Homologation: E.g., 'ECE 22.06', 'DOT FMVSS 218', 'ECE 22.05'.
5. Rotational Tech: E.g., 'MIPS', 'AIM', 'SPIN', 'Turbine 360', or 'None'.
6. Fit Notes: Replace any 'occipital lobe' with 'occiput'. Ensure notes explicitly mention head shape ('$head').
7. Speaker Pocket Depth (mm): Real estimate (e.g. 5, 7, 10).
8. Model Year: Real estimate (e.g. 2021-2025).

Return JSON ONLY:
{
  \"sharp_rating\": 1-5 or null,
  \"weight_g\": integer,
  \"homologation_standard\": \"string\",
  \"rotational_mitigation\": \"string\",
  \"fit_notes\": \"string\",
  \"speaker_pocket_depth_mm\": integer,
  \"cable_management\": true/false,
  \"model_year\": integer,
  \"asin\": \"string\",
  \"ean\": \"string\",
  \"mpn\": \"string\",
  \"yoast_metadesc\": \"SEO description (150-160 chars)\"
}";
}

/**
 * Parses raw LLM response to array.
 */
function parseAiResponse($response) {
    if (!$response) return null;
    $data = json_decode($response, true);
    $content = $data['choices'][0]['message']['content'] ?? '';
    if (preg_match('/\{.*\}/s', $content, $matches)) {
        return json_decode($matches[0], true);
    }
    return null;
}

/**
 * Merges repaired fields back into the helmet JSON file.
 */
function applyFixesAndPersist($file, $aiData) {
    $data = json_decode(file_get_contents($file), true);

    // 1. Identifiers
    if (!isset($data['identifiers'])) $data['identifiers'] = [];
    if (!empty($aiData['asin'])) $data['identifiers']['asin'] = $aiData['asin'];
    if (!empty($aiData['ean']))  $data['identifiers']['ean']  = $aiData['ean'];
    if (!empty($aiData['mpn']))  $data['identifiers']['mpn']  = $aiData['mpn'];

    // 2. Safety Intelligence
    if (!isset($data['safety_intelligence'])) $data['safety_intelligence'] = [];
    if (array_key_exists('sharp_rating', $aiData)) {
        $val = $aiData['sharp_rating'];
        if ($val === 0 || $val === '0') $val = null;
        if ($val !== null) $val = (int)$val;
        $data['safety_intelligence']['sharp_rating'] = $val;
    }
    if (!empty($aiData['homologation_standard'])) {
        $data['safety_intelligence']['homologation_standard'] = $aiData['homologation_standard'];
    }
    if (!empty($aiData['rotational_mitigation'])) {
        $data['safety_intelligence']['rotational_mitigation'] = $aiData['rotational_mitigation'];
    }

    // 3. Specs
    if (!isset($data['specs'])) $data['specs'] = [];
    if (!empty($aiData['weight_g']) && (int)$aiData['weight_g'] > 0) {
        $data['specs']['weight_g'] = (int)$aiData['weight_g'];
        $data['spec_weight_g'] = (int)$aiData['weight_g'];
    }

    // 4. Tech Integration
    if (!isset($data['tech_integration'])) $data['tech_integration'] = [];
    if (!empty($aiData['speaker_pocket_depth_mm'])) {
        $data['tech_integration']['speaker_pocket_depth_mm'] = (int)$aiData['speaker_pocket_depth_mm'];
    }
    if (isset($aiData['cable_management'])) {
        $data['tech_integration']['cable_management'] = (bool)$aiData['cable_management'];
    }

    // 5. Sizing & Fit
    if (!isset($data['sizing_fit'])) $data['sizing_fit'] = [];
    if (!empty($aiData['fit_notes'])) {
        // Fix anatomical typo
        $cleanNotes = str_ireplace('occipital lobe', 'occiput', $aiData['fit_notes']);
        $data['sizing_fit']['fit_notes'] = $cleanNotes;
    }

    // 6. Model Year
    if (!empty($aiData['model_year']) && (int)$aiData['model_year'] > 2000) {
        $data['model_year'] = (int)$aiData['model_year'];
    }

    // 7. SEO Meta Description
    if (!empty($aiData['yoast_metadesc'])) {
        $data['yoast_metadesc'] = trim($aiData['yoast_metadesc']);
    }

    $data['repaired_and_enriched'] = true;

    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}
