<?php
/**
 * Helmetsan Deep Enrichment Engine - Production Edition (v4)
 * 
 * A robust, high-parallelism engine for deep data harvesting.
 * Codified Patterns: Turbo Architecture, Smart Retry, State Sovereignty.
 */

// --- Configuration ---
$config = [
    'data_dir'      => dirname(__DIR__) . '/data/helmets',
    'api_url'       => 'http://192.168.2.240:1234/v1/chat/completions',
    'model'         => 'qwen/qwen3.5-9b',
    'concurrency'   => 10,
    'max_retries'   => 2,
    'log_path'      => dirname(__DIR__) . '/logs/deep_enrichment_engine.log',
    'error_log'     => dirname(__DIR__) . '/logs/deep_enrichment_errors.log',
    'timeout'       => 180,
    'pacing_usleep' => 1000000 // 1s between batches
];

// --- CLI Arguments ---
$options = getopt("", ["limit:", "concurrency:", "dry-run", "force"]);
$limit = isset($options['limit']) ? (int)$options['limit'] : 100;
$concurrency = isset($options['concurrency']) ? (int)$options['concurrency'] : $config['concurrency'];
$isDryRun = isset($options['dry-run']);
$isForce = isset($options['force']);

echo "🔥 Helmetsan Enrichment Engine v4 (Production)" . PHP_EOL;
echo "⚡ Mode: " . ($isDryRun ? "DRY RUN" : "LIVE") . " | Parallelism: $concurrency" . PHP_EOL;

// --- Main Execution ---
$pendingFiles = getPendingFiles($config['data_dir'], $limit, $isForce);

if (empty($pendingFiles)) {
    exit("✅ Catalog fully enriched. Use --force to re-process." . PHP_EOL);
}

if ($isDryRun) {
    echo "🔍 Found " . count($pendingFiles) . " helmets needing enrichment (Dry Run Mode)." . PHP_EOL;
    foreach ($pendingFiles as $file) echo "   - " . basename($file) . PHP_EOL;
    exit();
}

$chunks = array_chunk($pendingFiles, $concurrency);
foreach ($chunks as $chunk) {
    processBatch($chunk, $config);
    usleep($config['pacing_usleep']);
}

echo "🏁 Pass Complete." . PHP_EOL;

// --- Engine Core ---

/**
 * Identifies files that haven't been deep-enriched yet.
 */
function getPendingFiles($dataDir, $limit, $isForce) {
    $files = glob($dataDir . '/*.json');
    $pending = [];
    foreach ($files as $file) {
        if (count($pending) >= $limit) break;
        if (basename($file) === 'master.example.json' || basename($file) === 'master.json') continue;
        
        if ($isForce) {
            $pending[] = $file;
            continue;
        }

        $data = json_decode(file_get_contents($file), true);
        if (!isset($data['deep_enriched']) || $data['deep_enriched'] !== true) {
            $pending[] = $file;
        }
    }
    return $pending;
}

/**
 * Processes a chunk of files using curl_multi.
 */
function processBatch($chunk, $config) {
    $attempts = array_fill_keys($chunk, 1);
    $activeFiles = $chunk;

    while (!empty($activeFiles)) {
        $mh = curl_multi_init();
        $handles = [];

        foreach ($activeFiles as $file) {
            $data = json_decode(file_get_contents($file), true);
            $prompt = buildPrompt($data);
            
            $ch = curl_init($config['api_url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
                'model' => $config['model'],
                'messages' => [
                    ['role' => 'system', 'content' => 'Return JSON only.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.1,
                'max_tokens' => 600
            ]));
            curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
            curl_setopt($ch, CURLOPT_TIMEOUT, $config['timeout']);
            
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
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_multi_remove_handle($mh, $ch);

            $parsed = parseResponse($response);
            if ($parsed && $httpCode === 200) {
                persistData($file, $parsed);
                echo "✅ " . basename($file) . PHP_EOL;
            } else {
                if ($attempts[$file] <= $config['max_retries']) {
                    echo "🔄 Retry " . $attempts[$file] . ": " . basename($file) . PHP_EOL;
                    $attempts[$file]++;
                    $nextRoundFiles[] = $file;
                } else {
                    echo "❌ Failed: " . basename($file) . PHP_EOL;
                    logError($file, $response, $config['error_log']);
                }
            }
        }
        curl_multi_close($mh);
        $activeFiles = $nextRoundFiles;
    }
}

/**
 * Extracts and cleans JSON from AI response.
 */
function parseResponse($response) {
    if (!$response) return null;
    $data = json_decode($response, true);
    $content = $data['choices'][0]['message']['content'] ?? '';
    if (preg_match('/\{.*\}/s', $content, $matches)) {
        return json_decode($matches[0], true);
    }
    return null;
}

/**
 * Merges AI data into the local JSON file.
 */
function persistData($file, $deep) {
    $data = json_decode(file_get_contents($file), true);
    
    // Technical Identifiers
    $data['identifiers'] = array_merge($data['identifiers'] ?? [], array_filter([
        'asin' => $deep['asin'] ?? null,
        'ean' => $deep['ean'] ?? null,
        'mpn' => $deep['mpn'] ?? null
    ]));

    // Safety Intelligence
    $data['safety_intelligence'] = array_merge($data['safety_intelligence'] ?? [], array_filter([
        'homologation_standard' => $deep['homologation_standard'] ?? null,
        'sharp_rating' => $deep['sharp_rating'] ?? null,
        'rotational_mitigation' => $deep['rotational_mitigation'] ?? null
    ]));

    // Sizing & Fit
    $data['sizing_fit'] = array_merge($data['sizing_fit'] ?? [], array_filter([
        'fit_notes' => $deep['fit_notes'] ?? null,
        'head_shape' => $deep['head_shape'] ?? null
    ]));

    $data['deep_enriched'] = true;
    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
}

/**
 * Builds the expert prompt.
 */
function buildPrompt($data) {
    return "Expert Analysis: {$data['brand']} {$data['title']} ({$data['type']}).
Required Metadata (JSON):
- asin, ean, mpn
- homologation_standard (e.g., ECE 22.06, DOT)
- sharp_rating (1-5 if applicable, else 0)
- rotational_mitigation (e.g., MIPS, AIM, None)
- fit_notes (Description of sizing feel)
- head_shape (e.g., Intermediate Oval, Long Oval, Round Oval)";
}

/**
 * Global Error Logging.
 */
function logError($file, $response, $logPath) {
    $msg = "[" . date('Y-m-d H:i:s') . "] " . basename($file) . " | Response: " . $response . PHP_EOL;
    file_put_contents($logPath, $msg, FILE_APPEND);
}
