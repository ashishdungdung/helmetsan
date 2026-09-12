<?php
/**
 * Advanced Helmet Data Enrichment (Phase 2.1)
 * 
 * Targets: Aero/Acoustic, Fitment, SEO, and Long Descriptions.
 * API: LM Studio (http://localhost:1234/v1/chat/completions)
 * Model: qwen/qwen3.5-9b (or similar)
 * 
 * Usage: php scripts/enrich_helmets_advanced_local.php --limit=5 --concurrency=2
 */

$dataDir = dirname(__DIR__) . '/data/helmets';
$apiUrl = 'http://localhost:1234/v1/chat/completions';
$model = 'local'; // LM Studio often uses 'local' or the loaded model name

// CLI Defaults
$options = getopt("", ["limit:", "concurrency:"]);
$limit = isset($options['limit']) ? (int)$options['limit'] : 5;
$concurrency = isset($options['concurrency']) ? (int)$options['concurrency'] : 2;

$processedCount = 0;
$errorCount = 0;

echo "🚀 Starting Advanced Helmet Data Enrichment (Local AI)..." . PHP_EOL;
echo "⚡ Concurrency: $concurrency | Limit: $limit" . PHP_EOL;

$files = glob($dataDir . '/*.json');
if ($files === false || empty($files)) {
    die("❌ No JSON files found in $dataDir" . PHP_EOL);
}

// Filter files that are missing advanced specs
$pendingFiles = [];
foreach ($files as $file) {
    if (count($pendingFiles) >= $limit) break;
    if (basename($file) === 'master.example.json' || basename($file) === 'master.json') continue;
    
    $json = file_get_contents($file);
    if (!$json) continue;
    $data = json_decode($json, true);
    if (!$data) continue;
    
    // Check if missing advanced attributes
    $hasAero = !empty($data['aero_acoustic_profile']);
    $hasFitment = !empty($data['fitment_coordinates']);
    $hasMarketing = !empty($data['marketing_description']);
    $hasSEO = !empty($data['yoast_metadesc']);
    
    if (!$hasAero || !$hasFitment || !$hasMarketing || !$hasSEO) {
        $pendingFiles[] = $file;
    }
}

if (empty($pendingFiles)) {
    die("✅ All helmets already have advanced enrichment or limit reached." . PHP_EOL);
}

$chunks = array_chunk($pendingFiles, $concurrency);

foreach ($chunks as $chunk) {
    $mh = curl_multi_init();
    $handles = [];

    foreach ($chunk as $file) {
        $json = file_get_contents($file);
        $data = json_decode($json, true);
        $title = $data['title'] ?? 'Motorcycle Helmet';
        $brand = $data['brand'] ?? 'Universal';
        $type = $data['type'] ?? 'Full Face';

        $prompt = "You are a motorcycle helmet expert. Provide advanced technical data and SEO content for this helmet:
Title: $title
Brand: $brand
Type: $type

Requirements:
1. Aero/Acoustic Profile: Estimate noise level at 100kph (dB), ventilation efficiency (1-10), and drag coefficient (Cd).
2. Fitment Coordinates: Estimate internal shape (e.g. 'Intermediate Oval'), internal length/width (mm), and crown depth (mm) for a Size Large.
3. Descriptions: 
   - technical_analysis: A short 2-3 sentence paragraph focusing on engineering.
   - marketing_description: A premium 150-word description highlighting style and performance.
4. SEO:
   - yoast_title: SEO title under 60 chars.
   - yoast_focuskw: Primary focus keyword (lowercase).
   - yoast_metadesc: Benefit-led meta description (150-160 chars).

Return ONLY a valid JSON object with these exact keys:
- aero_acoustic_profile (object: {noise_db_at_100kph, ventilation_efficiency_score, drag_coefficient})
- fitment_coordinates (object: {internal_shape_3d, internal_length_mm, internal_width_mm, crown_depth_mm})
- technical_analysis (string)
- marketing_description (string)
- yoast_title (string)
- yoast_focuskw (string)
- yoast_metadesc (string)

No extra text, no markdown. Use realistic technical data based on brand reputation.";

        $postData = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'You return high-fidelity motorcycle helmet data as JSON only.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.4,
            'max_tokens' => 1000
        ];

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 180);
        
        curl_multi_add_handle($mh, $ch);
        $handles[$file] = $ch;
        echo "📖 Analyzing: $title ($brand)..." . PHP_EOL;
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
        $error = curl_error($ch);
        curl_multi_remove_handle($mh, $ch);

        if ($error) {
            echo "❌ API Error for $file: $error" . PHP_EOL;
            $errorCount++;
            continue;
        }

        $responseData = json_decode($response, true);
        $content = trim($responseData['choices'][0]['message']['content'] ?? '');
        
        // Cleanup JSON markdown if present
        if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
            $content = $matches[1];
        } elseif (preg_match('/\{.*\}/s', $content, $matches)) {
            $content = $matches[0];
        }

        $enriched = json_decode($content, true);

        if (empty($enriched)) {
            echo "⚠️ Failed to parse AI response for " . basename($file) . PHP_EOL;
            $errorCount++;
            continue;
        }

        // Update JSON file
        $json = file_get_contents($file);
        $data = json_decode($json, true);
        
        foreach ($enriched as $key => $val) {
            if (!empty($val)) {
                $data[$key] = $val;
            }
        }
        
        $data['deep_enriched'] = true;

        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        echo "✅ Advanced Enrichment Complete: " . basename($file) . PHP_EOL;
        $processedCount++;
    }
    curl_multi_close($mh);
}

echo PHP_EOL . "🏁 Advanced enrichment complete!" . PHP_EOL;
echo "📊 Processed: $processedCount | Errors: $errorCount" . PHP_EOL;
