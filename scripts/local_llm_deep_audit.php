<?php
/**
 * Helmetsan Local LLM Deep Audit Engine
 * 
 * Uses local LM Studio instance (http://127.0.0.1:1234/v1) to perform
 * multi-dimensional data quality, safety consistency, and schema audit
 * across all helmet catalog JSON files.
 * 
 * Usage: php scripts/local_llm_deep_audit.php [--sample=20] [--output=report.md]
 */

$localConfig = @include __DIR__ . '/local_config.php';
$baseUrl     = $localConfig['lm_studio_base_url'] ?? 'http://127.0.0.1:1234/v1';
$apiUrl      = rtrim($baseUrl, '/') . '/chat/completions';
$model       = $localConfig['lm_studio_model'] ?? 'qwen/qwen3.5-9b';

$options = getopt("", ["sample:", "output:"]);
$sampleLimit = isset($options['sample']) ? (int)$options['sample'] : 20;
$outputFile  = isset($options['output']) ? $options['output'] : dirname(__DIR__) . '/logs/local_llm_deep_audit_report.md';

$dataDir = dirname(__DIR__) . '/data/helmets';
$files   = glob($dataDir . '/*.json');

if (!$files) {
    die("❌ No JSON files found in $dataDir\n");
}

echo "========================================================\n";
echo "🤖 HELMETSAN LOCAL LLM DEEP AUDIT ENGINE\n";
echo "========================================================\n";
echo "⚡ LLM Endpoint : $apiUrl\n";
echo "⚡ Model        : $model\n";
echo "⚡ Total Files  : " . count($files) . "\n";
echo "⚡ LLM Sample   : $sampleLimit helmets\n";
echo "========================================================\n\n";

// --- STEP 1: QUANTITATIVE CATALOG AUDIT ---
echo "📊 [1/3] Running Quantitative Data Sparsity Audit...\n";

$total = 0;
$fieldCounts = [
    'id' => 0, 'title' => 0, 'brand' => 0, 'type' => 0, 'helmet_family' => 0,
    'head_shape' => 0, 'model_year' => 0, 'price_retail_usd' => 0, 'yoast_metadesc' => 0,
    'asin' => 0, 'ean' => 0, 'mpn' => 0,
    'weight_g' => 0, 'material' => 0, 'shell_sizes_count' => 0, 'warranty_years' => 0, 'strap_type' => 0,
    'homologation_standard' => 0, 'sharp_rating' => 0, 'rotational_mitigation' => 0,
    'comms_cutout_type' => 0, 'speaker_pocket_depth_mm' => 0, 'cable_management' => 0,
    'fit_notes' => 0
];

$parsedHelmets = [];

foreach ($files as $file) {
    $basename = basename($file);
    if ($basename === 'master.example.json' || $basename === 'master.json') continue;

    $json = file_get_contents($file);
    $data = json_decode($json, true);
    if (!$data) continue;

    $total++;
    $parsedHelmets[$file] = $data;

    // Field existence checks
    if (!empty($data['id'])) $fieldCounts['id']++;
    if (!empty($data['title'])) $fieldCounts['title']++;
    if (!empty($data['brand'])) $fieldCounts['brand']++;
    if (!empty($data['type'])) $fieldCounts['type']++;
    if (!empty($data['helmet_family'])) $fieldCounts['helmet_family']++;
    if (!empty($data['head_shape'])) $fieldCounts['head_shape']++;
    if (!empty($data['model_year'])) $fieldCounts['model_year']++;
    if (!empty($data['price_retail_usd'])) $fieldCounts['price_retail_usd']++;
    if (!empty($data['yoast_metadesc'])) $fieldCounts['yoast_metadesc']++;

    if (!empty($data['identifiers']['asin'])) $fieldCounts['asin']++;
    if (!empty($data['identifiers']['ean'])) $fieldCounts['ean']++;
    if (!empty($data['identifiers']['mpn'])) $fieldCounts['mpn']++;

    if (!empty($data['specs']['weight_g'])) $fieldCounts['weight_g']++;
    if (!empty($data['specs']['material'])) $fieldCounts['material']++;
    if (!empty($data['specs']['shell_sizes_count'])) $fieldCounts['shell_sizes_count']++;
    if (!empty($data['specs']['warranty_years'])) $fieldCounts['warranty_years']++;
    if (!empty($data['specs']['strap_type'])) $fieldCounts['strap_type']++;

    if (!empty($data['safety_intelligence']['homologation_standard'])) $fieldCounts['homologation_standard']++;
    if (!empty($data['safety_intelligence']['sharp_rating'])) $fieldCounts['sharp_rating']++;
    if (!empty($data['safety_intelligence']['rotational_mitigation'])) $fieldCounts['rotational_mitigation']++;

    if (!empty($data['tech_integration']['comms_cutout_type'])) $fieldCounts['comms_cutout_type']++;
    if (!empty($data['tech_integration']['speaker_pocket_depth_mm'])) $fieldCounts['speaker_pocket_depth_mm']++;
    if (!empty($data['tech_integration']['cable_management'])) $fieldCounts['cable_management']++;

    if (!empty($data['sizing_fit']['fit_notes'])) $fieldCounts['fit_notes']++;
}

echo "   Done scanning $total helmets.\n\n";

// --- STEP 2: LOCAL LLM QUALITATIVE AUDIT ---
echo "🧠 [2/3] Running Local LLM Qualitative Audit on $sampleLimit sampled helmets...\n";

// Select a stratified sample (mix of full-face, modular, open-face, off-road)
$sampleKeys = array_rand($parsedHelmets, min($sampleLimit, count($parsedHelmets)));
if (!is_array($sampleKeys)) $sampleKeys = [$sampleKeys];

$llmAuditResults = [];
$auditPassCount  = 0;
$auditFlagCount  = 0;

foreach ($sampleKeys as $filePath) {
    $item = $parsedHelmets[$filePath];
    $title = $item['title'] ?? 'Unknown Title';
    $brand = $item['brand'] ?? 'Unknown Brand';
    $type  = $item['type'] ?? 'Unknown Type';
    
    echo "   🔍 Auditing: [$brand] $title... ";

    $prompt = "You are a senior quality auditor for a motorcycle helmet database. Audit the following helmet JSON record for logical consistency, accuracy, and completeness:

Title: {$title}
Brand: {$brand}
Type: {$type}
Weight (g): " . ($item['specs']['weight_g'] ?? 'Missing') . "
Shell Material: " . ($item['specs']['material'] ?? 'Missing') . "
Homologation: " . ($item['safety_intelligence']['homologation_standard'] ?? 'Missing') . "
SHARP Rating: " . ($item['safety_intelligence']['sharp_rating'] ?? 'Missing') . "
Rotational Tech: " . ($item['safety_intelligence']['rotational_mitigation'] ?? 'Missing') . "
Fit Notes: " . ($item['sizing_fit']['fit_notes'] ?? 'Missing') . "
Meta Description: " . ($item['yoast_metadesc'] ?? 'Missing') . "

Evaluate the following:
1. Weight Sanity (Is weight reasonable for helmet type/material, e.g. 1000g-2000g)?
2. Safety Standard Realism (Does homologation make sense for brand/type)?
3. Fit & Head Shape Logic (Are fit notes and head shape coherent)?
4. SEO Quality (Is meta description compelling and proper length)?

Respond strictly as JSON with keys:
{
  \"status\": \"PASS\" or \"FLAG\",
  \"quality_score\": 1-100,
  \"findings\": [list of issues or positive notes],
  \"recommendation\": \"action item summary\"
}";

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => 'You are a precise data quality audit engine that responds ONLY in raw valid JSON.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.1,
        'max_tokens' => 350
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);

    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200 && $response) {
        $resData = json_decode($response, true);
        $content = $resData['choices'][0]['message']['content'] ?? '';
        
        if (preg_match('/\{.*\}/s', $content, $matches)) {
            $eval = json_decode($matches[0], true);
        } else {
            $eval = null;
        }

        if ($eval && isset($eval['status'])) {
            $status = strtoupper($eval['status']);
            if ($status === 'PASS') {
                $auditPassCount++;
                echo "✅ PASS (Score: " . ($eval['quality_score'] ?? 100) . ")\n";
            } else {
                $auditFlagCount++;
                echo "⚠️ FLAG (Score: " . ($eval['quality_score'] ?? 50) . ")\n";
            }
            $llmAuditResults[] = [
                'file' => basename($filePath),
                'title' => "$brand $title",
                'status' => $status,
                'score' => $eval['quality_score'] ?? 'N/A',
                'findings' => implode('; ', $eval['findings'] ?? []),
                'recommendation' => $eval['recommendation'] ?? 'N/A'
            ];
        } else {
            echo "⚠️ Unparsed AI response\n";
        }
    } else {
        echo "❌ HTTP $httpCode error\n";
    }
}

echo "\n📝 [3/3] Generating Audit Report...\n";

// --- BUILD REPORT CONTENT ---
$report = "# Helmetsan Local LLM Deep Audit Report\n\n";
$report .= "**Execution Date:** " . date('Y-m-d H:i:s') . "\n";
$report .= "**LLM Provider:** LM Studio (`$apiUrl`)\n";
$report .= "**Model Used:** `$model`\n";
$report .= "**Total Helmets Analyzed:** `$total`\n\n";

$report .= "## 1. Quantitative Data Coverage Matrix\n\n";
$report .= "| Subsystem / Field | Count | Coverage % | Status |\n";
$report .= "| :--- | :--- | :--- | :--- |\n";

foreach ($fieldCounts as $field => $count) {
    $pct = round(($count / $total) * 100, 1);
    $statusIcon = ($pct >= 90.0) ? "🟢 PASS" : (($pct >= 70.0) ? "🟡 WARN" : "🔴 CRITICAL");
    $report .= "| `" . sprintf("%-22s", $field) . "` | " . sprintf("%5d", $count) . " / $total | " . sprintf("%5.1f%%", $pct) . " | $statusIcon |\n";
}

$report .= "\n## 2. Qualitative Local LLM Audit (Sampled $sampleLimit Records)\n\n";
$report .= "- **Passed Audits:** $auditPassCount / " . count($llmAuditResults) . "\n";
$report .= "- **Flagged Audits:** $auditFlagCount / " . count($llmAuditResults) . "\n\n";

$report .= "| File | Title | Score | Status | Findings | Recommendation |\n";
$report .= "| :--- | :--- | :--- | :--- | :--- | :--- |\n";

foreach ($llmAuditResults as $res) {
    $icon = ($res['status'] === 'PASS') ? '✅' : '⚠️';
    $report .= "| `{$res['file']}` | {$res['title']} | {$res['score']} | $icon {$res['status']} | {$res['findings']} | {$res['recommendation']} |\n";
}

$report .= "\n## 3. Key Findings & Strategic Action Plan\n\n";
$report .= "### High-Priority Data Gaps (< 75% Coverage):\n";
foreach ($fieldCounts as $field => $count) {
    $pct = round(($count / $total) * 100, 1);
    if ($pct < 75.0) {
        $report .= "- **`$field`**: `{$pct}%` coverage ($count/$total helmets). Needs enrichment pass.\n";
    }
}

$report .= "\n### Recommended Next Steps:\n";
$report .= "1. Run `php scripts/enrich_helmets_specs_local.php --limit=200` to fill missing technical specs (warranty, strap type, visor features).\n";
$report .= "2. Run `php scripts/enrich_helmets_deep_pass.php --force --limit=100` to enrich missing identifiers (ASIN, EAN, MPN) and safety standards.\n";
$report .= "3. Run `php scripts/enrich_helmets_json_local.php` to populate remaining meta descriptions.\n";

@mkdir(dirname($outputFile), 0755, true);
file_put_contents($outputFile, $report);

echo "========================================================\n";
echo "✅ Audit Complete! Report saved to: $outputFile\n";
echo "========================================================\n";
