<?php
/**
 * Ultimate Parallel Helmet Certification Enrichment Script
 * 
 * Target: data/helmets/*.json
 * Groups by Model/Brand to minimize AI calls.
 * 
 * Usage: php scripts/enrich_certificates_parallel.php
 */

$opts = getopt('', ['test']);
$isTest = isset($opts['test']);
$dataDir = dirname(__DIR__) . '/data/helmets';
$apiUrl = 'http://192.168.2.240:1234/v1/chat/completions';
$model = 'qwen/qwen3.5-9b';
$concurrency = 4;
$processedFiles = 0;
$apiCalls = 0;

echo "🚀 Starting Ultimate Parallel Certification Enrichment..." . PHP_EOL;

$files = glob($dataDir . '/*.json');
if ($files === false || empty($files)) {
    die("❌ No JSON files found in $dataDir" . PHP_EOL);
}

// Group files by unique brand/model key
$groups = [];
foreach ($files as $file) {
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    if (!$data) continue;

    // We only enrich if certifications are missing or only "DOT"
    $currentCerts = $data['specs']['certifications'] ?? [];
    if (count($currentCerts) > 1 || (count($currentCerts) === 1 && $currentCerts[0] !== 'DOT')) {
        continue;
    }

    $brand = $data['brand'] ?? 'Unknown';
    $modelKey = $data['parent_id'] ?? $data['helmet_family'] ?? $data['title'] ?? basename($file, '.json');
    $cacheKey = strtolower($brand . ' ' . $modelKey);

    if (!isset($groups[$cacheKey])) {
        $groups[$cacheKey] = [
            'brand' => $brand,
            'model' => $modelKey,
            'files' => []
        ];
    }
    $groups[$cacheKey]['files'][] = $file;
}

if ($isTest) {
    $groups = array_slice($groups, 0, 10, true);
    echo "🧪 TEST MODE: Processing " . count($groups) . " unique models." . PHP_EOL;
}

echo "📂 Total unique models to process: " . count($groups) . PHP_EOL;

$uniqueKeys = array_keys($groups);
$chunks = array_chunk($uniqueKeys, $concurrency);

foreach ($chunks as $chunk) {
    $mh = curl_multi_init();
    $handles = [];

    foreach ($chunk as $key) {
        $brand = $groups[$key]['brand'];
        $modelName = $groups[$key]['model'];

        $prompt = "Motorcycle safety expert task: Return ONLY a JSON array of official safety certifications for '$brand $modelName' helmet. 
Options: DOT, ECE 22.05, ECE 22.06, SNELL M2020, FIM FRHPhe-01, SHARP 5-Star. 
Example response: [\"DOT\", \"ECE 22.06\"]";

        $postData = [
            'model' => $model,
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.1,
            'max_tokens' => 50
        ];

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        curl_multi_add_handle($mh, $ch);
        $handles[$key] = $ch;
        echo "📖 Queued: $brand $modelName..." . PHP_EOL;
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

    foreach ($handles as $key => $ch) {
        $response = curl_multi_getcontent($ch);
        $error = curl_error($ch);
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);

        $brand = $groups[$key]['brand'];
        $modelName = $groups[$key]['model'];

        if ($error) {
            echo "❌ API Error for $brand $modelName: $error" . PHP_EOL;
            continue;
        }

        $responseData = json_decode($response, true);
        $respStr = trim($responseData['choices'][0]['message']['content'] ?? '[]');
        
        // Sanitize for JSON array
        if (preg_match('/\[.*\]/s', $respStr, $matches)) {
            $certs = json_decode($matches[0], true);
        } else {
            $certs = [];
        }

        if (empty($certs)) {
             // Default to DOT if AI fails but we know it's a helmet
            $certs = ["DOT"];
            echo "⚠️ AI failed for $brand $modelName (Raw: $respStr). Defaulting to DOT." . PHP_EOL;
        }

        foreach ($groups[$key]['files'] as $file) {
            $json = file_get_contents($file);
            $data = json_decode($json, true);
            applyCerts($data, $certs);
            file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $processedFiles++;
        }
        echo "✅ Enriched $brand $modelName -> " . implode(', ', $certs) . " (" . count($groups[$key]['files']) . " files)" . PHP_EOL;
        $apiCalls++;
    }
    curl_multi_close($mh);
}

function applyCerts(&$data, $certs) {
    if (isset($data['certifications'])) $data['certifications'] = $certs;
    if (isset($data['specs'])) $data['specs']['certifications'] = $certs;
    if (isset($data['variants']) && is_array($data['variants'])) {
        foreach ($data['variants'] as &$variant) {
            if (isset($variant['specs'])) $variant['specs']['certifications'] = $certs;
            if (isset($variant['certifications'])) $variant['certifications'] = $certs;
        }
    }
}

echo "🏁 Enrichment complete! Processed $processedFiles files via $apiCalls AI calls." . PHP_EOL;
