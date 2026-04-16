<?php
/**
 * Deep Enrichment Engine - TURBO EDITION (v3)
 * 
 * Improvements:
 * - Smart Retry: 2 attempts on malformed AI responses
 * - Parallelism: 10 concurrent requests
 * - State Tracking: deep_enriched flag prevents redundancy
 */

$dataDir = dirname(__DIR__) . '/data/helmets';
$apiUrl = 'http://192.168.2.240:1234/v1/chat/completions';
$model = 'qwen/qwen3.5-9b';

$options = getopt("", ["limit:", "concurrency:"]);
$limit = isset($options['limit']) ? (int)$options['limit'] : 100;
$concurrency = isset($options['concurrency']) ? (int)$options['concurrency'] : 10;
$maxRetries = 2;

echo "🔥 TURBO Enrichment Mode Active!" . PHP_EOL;
echo "⚡ Parallelism: $concurrency | Retry Budget: $maxRetries" . PHP_EOL;

$files = glob($dataDir . '/*.json');
$pendingFiles = [];
foreach ($files as $file) {
    if (count($pendingFiles) >= $limit) break;
    if (basename($file) === 'master.example.json' || basename($file) === 'master.json') continue;
    
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    if (!isset($data['deep_enriched']) || $data['deep_enriched'] !== true) {
        $pendingFiles[] = $file;
    }
}

if (empty($pendingFiles)) exit("✅ Catalog fully deep-enriched." . PHP_EOL);

$chunks = array_chunk($pendingFiles, $concurrency);
$totalToProcess = count($pendingFiles);
$completed = 0; $errors = 0; $retries = 0;

foreach ($chunks as $chunk) {
    processBatch($chunk, $apiUrl, $model, $maxRetries);
}

function processBatch($chunk, $apiUrl, $model, $maxRetries) {
    global $completed, $errors, $retries;
    
    // Track attempts per file in this batch
    $attempts = array_fill_keys($chunk, 1);
    $activeFiles = $chunk;

    while (!empty($activeFiles)) {
        $mh = curl_multi_init();
        $handles = [];

        foreach ($activeFiles as $file) {
            $data = json_decode(file_get_contents($file), true);
            $prompt = getPrompt($data['title'], $data['brand'], $data['type']);
            $postData = [
                'model' => $model,
                'messages' => [['role' => 'system', 'content' => 'JSON output only.'], ['role' => 'user', 'content' => $prompt]],
                'temperature' => 0.1,
                'max_tokens' => 600
            ];

            $ch = curl_init($apiUrl);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, 180);
            
            curl_multi_add_handle($mh, $ch);
            $handles[$file] = $ch;
        }

        $active = null;
        do { $mrc = curl_multi_exec($mh, $active); } while ($mrc === CURLM_CALL_MULTI_PERFORM);
        while ($active && $mrc === CURLM_OK) {
            if (curl_multi_select($mh) !== -1) {
                do { $mrc = curl_multi_exec($mh, $active); } while ($mrc === CURLM_CALL_MULTI_PERFORM);
            }
        }

        $nextRoundFiles = [];
        foreach ($handles as $file => $ch) {
            $response = curl_multi_getcontent($ch);
            $error = curl_error($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_multi_remove_handle($mh, $ch);

            $success = false;
            if (!$error && $httpCode === 200) {
                $responseData = json_decode($response, true);
                $content = trim($responseData['choices'][0]['message']['content'] ?? '');
                if (preg_match('/\{.*\}/s', $content, $matches)) $content = $matches[0];
                $deep = json_decode($content, true);

                if (!empty($deep)) {
                    saveData($file, $deep);
                    echo "✅ " . basename($file) . PHP_EOL;
                    $completed++;
                    $success = true;
                }
            }

            if (!$success) {
                if ($attempts[$file] <= $maxRetries) {
                    echo "🔄 Retry " . $attempts[$file] . "/" . $maxRetries . ": " . basename($file) . " (Code $httpCode)" . PHP_EOL;
                    $attempts[$file]++;
                    $retries++;
                    $nextRoundFiles[] = $file;
                } else {
                    echo "❌ Failed: " . basename($file) . " (Err: $error)" . PHP_EOL;
                    file_put_contents('logs/deep_enrichment_error.log', "[" . date('H:i:s') . "] Failed: " . basename($file) . " Response: " . $response . PHP_EOL, FILE_APPEND);
                    $errors++;
                }
            }
        }
        curl_multi_close($mh);
        $activeFiles = $nextRoundFiles;
        if (!empty($activeFiles)) usleep(1000000); // 1s wait before retries
    }
}

function saveData($file, $deep) {
    $data = json_decode(file_get_contents($file), true);
    $data['model_year'] = $deep['model_year'] ?? $data['model_year'] ?? null;
    $data['identifiers'] = array_merge($data['identifiers'] ?? [], array_filter([
        'asin' => $deep['asin'] ?? null,
        'ean' => $deep['ean'] ?? null,
        'mpn' => $deep['mpn'] ?? null
    ]));
    $data['safety_intelligence'] = array_merge($data['safety_intelligence'] ?? [], array_filter([
        'homologation_standard' => $deep['homologation_standard'] ?? null,
        'sharp_rating' => $deep['sharp_rating'] ?? null,
        'rotational_mitigation' => $deep['rotational_mitigation'] ?? null
    ]));
    $data['tech_integration'] = array_merge($data['tech_integration'] ?? [], array_filter([
        'speaker_pocket_depth_mm' => $deep['speaker_pocket_depth_mm'] ?? null,
        'cable_management' => $deep['cable_management'] ?? null,
        'hud_ready' => $deep['hud_ready'] ?? null
    ]));
    $data['sizing_fit'] = array_merge($data['sizing_fit'] ?? [], array_filter([
        'fit_notes' => $deep['fit_notes'] ?? null,
        'head_shape' => $deep['head_shape'] ?? null
    ]));
    $data['deep_enriched'] = true;
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

function getPrompt($title, $brand, $type) {
    return "Helmet: $title | Brand: $brand | Type: $type
Return JSON with these EXACT keys: asin, ean, mpn, model_year, homologation_standard, sharp_rating (int), rotational_mitigation, speaker_pocket_depth_mm (int), cable_management (bool), hud_ready (bool), fit_notes, head_shape.";
}

echo PHP_EOL . "🏁 Turbo Pass Complete! Success: $completed | Errors: $errors | Total Retries: $retries" . PHP_EOL;
