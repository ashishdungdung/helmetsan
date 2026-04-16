<?php
/**
 * Standalone Helmet Technical Specs Enrichment Script
 * 
 * Target: data/helmets/*.json
 * API: LM Studio (http://192.168.2.240:1234/v1/chat/completions)
 * Model: qwen/qwen3.5-9b (or similar)
 * 
 * Usage: php scripts/enrich_helmets_specs_local.php --limit=100 --concurrency=10
 */

$dataDir = dirname(__DIR__) . '/data/helmets';
$apiUrl = 'http://192.168.2.240:1234/v1/chat/completions';
$model = 'qwen/qwen3.5-9b';

// CLI Defaults
$options = getopt("", ["limit:", "concurrency:"]);
$limit = isset($options['limit']) ? (int)$options['limit'] : 50;
$concurrency = isset($options['concurrency']) ? (int)$options['concurrency'] : 2;

$processedCount = 0;
$errorCount = 0;

echo "🚀 Starting Parallel Technical Specs Enrichment (Local AI)..." . PHP_EOL;
echo "⚡ Concurrency: $concurrency | Limit: $limit" . PHP_EOL;

$files = glob($dataDir . '/*.json');
if ($files === false || empty($files)) {
    die("❌ No JSON files found in $dataDir" . PHP_EOL);
}

// Filter files that are missing technical specs
$pendingFiles = [];
foreach ($files as $file) {
    if (count($pendingFiles) >= $limit) break;
    if (basename($file) === 'master.example.json' || basename($file) === 'master.json') continue;
    
    $json = file_get_contents($file);
    if (!$json) continue;
    $data = json_decode($json, true);
    if (!$data) continue;
    
    // Check if missing ANY critical technical specs or if the data is sparse
    $hasWarranty = !empty($data['specs']['warranty_years']);
    $hasStrap = !empty($data['specs']['strap_type']);
    $hasVisor = !empty($data['features_data']['visor']);
    $hasComms = !empty($data['tech_integration']['comms_cutout_type']);
    $hasShellSizes = !empty($data['specs']['shell_sizes_count']);
    
    // If any of these are missing, we enrich
    if (!$hasWarranty || !$hasStrap || !$hasVisor || !$hasComms || !$hasShellSizes) {
        $pendingFiles[] = $file;
    }
}

if (empty($pendingFiles)) {
    die("✅ All helmets already have comprehensive technical specifications or limit reached." . PHP_EOL);
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

        $prompt = "You are a motorcycle helmet technical expert. Find the following technical specifications for this helmet:
Title: $title
Brand: $brand

Requirements:
1. Warranty (Years): Typical manufacturer warranty length (e.g., 5, 2, 1). If unknown, estimate based on brand standards.
2. Strap Type: E.g., 'Double D-Ring', 'Micrometric', 'Fidlock'.
3. Visor Features: A list of features like 'Pinlock Ready', 'Pinlock Included', 'Integrated Sun Visor', 'Optically Correct'.
4. Communication Readiness: E.g., 'Generic Speaker Cutouts', 'Sena Integrated', 'Cardo Ready'.
5. Shell Sizes: Number of distinct outer shell sizes produced for this model (integer, e.g., 1, 2, 3, 4).

Return ONLY a valid JSON object with these exact keys:
- warranty_years (integer)
- strap_type (string)
- visor_features (array of strings)
- comms_readiness (string)
- shell_sizes_count (integer)

No extra text, no markdown code blocks. If unknown, use reasonable estimates for top brands.";

        $postData = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'You return technical helmet specs as JSON only.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.1,
            'max_tokens' => 250,
            'response_format' => ['type' => 'text']
        ];

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 120);
        
        curl_multi_add_handle($mh, $ch);
        $handles[$file] = $ch;
        echo "📖 Researching: $title ($brand)..." . PHP_EOL;
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
        
        if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
            $content = $matches[1];
        } elseif (preg_match('/\{.*\}/s', $content, $matches)) {
            $content = $matches[0];
        }

        $specs = json_decode($content, true);

        if (empty($specs)) {
            echo "⚠️ Failed to parse AI response for " . basename($file) . PHP_EOL;
            file_put_contents('enrichment_error.log', "--- " . basename($file) . " ---\n" . $response . "\n\n", FILE_APPEND);
            $errorCount++;
            continue;
        }

        // Update JSON file
        $json = file_get_contents($file);
        $data = json_decode($json, true);
        
        if (isset($specs['warranty_years'])) {
            $data['specs']['warranty_years'] = (int) $specs['warranty_years'];
        }
        if (!empty($specs['strap_type'])) {
            $data['specs']['strap_type'] = $specs['strap_type'];
        }
        if (isset($specs['shell_sizes_count'])) {
            $data['specs']['shell_sizes_count'] = (int) $specs['shell_sizes_count'];
        }
        if (!empty($specs['visor_features'])) {
            $data['features_data']['visor'] = $specs['visor_features'];
        }
        if (!empty($specs['comms_readiness'])) {
            $data['tech_integration']['comms_cutout_type'] = $specs['comms_readiness'];
        }

        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        echo "✅ Enriched: " . basename($file) . PHP_EOL;
        $processedCount++;
    }
    curl_multi_close($mh);
}

echo PHP_EOL . "🏁 Enrichment complete!" . PHP_EOL;
echo "📊 Processed: $processedCount | Errors: $errorCount" . PHP_EOL;
