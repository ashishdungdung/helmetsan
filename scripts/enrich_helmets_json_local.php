<?php
/**
 * Standalone Helmet JSON Enrichment Script
 * 
 * Target: data/helmets/*.json
 * API: LM Studio (http://192.168.2.240:1234/v1/chat/completions)
 * Model: qwen/qwen3.5-9b
 * 
 * Usage: php scripts/enrich_helmets_json_local.php
 */

$dataDir = dirname(__DIR__) . '/data/helmets';
$apiUrl = 'http://192.168.2.240:1234/v1/chat/completions';
$model = 'qwen/qwen3.5-9b';
$limit = 50;
$concurrency = 4;
$processedCount = 0;

echo "🚀 Starting Parallel Helmet JSON Enrichment (Local AI)..." . PHP_EOL;
echo "⚡ Concurrency: $concurrency" . PHP_EOL;

$files = glob($dataDir . '/*.json');
if ($files === false || empty($files)) {
    die("❌ No JSON files found in $dataDir" . PHP_EOL);
}

$pendingFiles = [];
foreach ($files as $file) {
    if (count($pendingFiles) >= $limit) break;
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    if ($data && empty($data['yoast_metadesc'])) {
        $pendingFiles[] = $file;
    }
}

if (empty($pendingFiles)) {
    die("✅ All helmets already have meta descriptions." . PHP_EOL);
}

$chunks = array_chunk($pendingFiles, $concurrency);

foreach ($chunks as $chunk) {
    $mh = curl_multi_init();
    $handles = [];

    foreach ($chunk as $file) {
        $json = file_get_contents($file);
        $data = json_decode($json, true);
        $title = $data['title'] ?? 'Motorcycle Helmet';
        $brand = basename($file);
        if (strpos($brand, '-') !== false) {
            $brandParts = explode('-', $brand);
            $brand = ucfirst($brandParts[0]);
        }

        $specs = [];
        if (!empty($data['spec_weight_g'])) $specs[] = "Weight: {$data['spec_weight_g']}g";
        if (!empty($data['spec_shell_material'])) $specs[] = "Material: {$data['spec_shell_material']}";
        if (!empty($data['head_shape'])) $specs[] = "Fit: {$data['head_shape']}";
        $specStr = implode(', ', $specs);

        $prompt = "You are an SEO expert for Helmetsan, a premium motorcycle helmet comparison site.
Generate a benefit-led SEO meta description for the following helmet:
Title: $title
Brand: $brand
Specs: $specStr

Requirements:
- Length: 150-160 characters.
- Tone: Professional, authoritative, and exciting for riders.
- Content: Mention key safety tech or features. End with a call-to-action (CTA).
- Format: Plain text only, no quotes, no extra words.

Example: Discover the $title, a premium $brand helmet featuring advanced shell tech and superior ventilation. Ride with peak safety and style. Shop now at Helmetsan!";

        $postData = [
            'model' => $model,
            'messages' => [
                ['role' => 'system', 'content' => 'You generate concise, benefit-led SEO meta descriptions (150-160 chars) for motorcycle helmets.'],
                ['role' => 'user', 'content' => $prompt]
            ],
            'temperature' => 0.7,
            'max_tokens' => 100
        ];

        $ch = curl_init($apiUrl);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        curl_setopt($ch, CURLOPT_TIMEOUT, 30);
        
        curl_multi_add_handle($mh, $ch);
        $handles[$file] = $ch;
        echo "📖 Queued: $title ($brand)..." . PHP_EOL;
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
        curl_close($ch);

        if ($error) {
            echo "❌ API Error for $file: $error" . PHP_EOL;
            continue;
        }

        $responseData = json_decode($response, true);
        $metaDesc = trim($responseData['choices'][0]['message']['content'] ?? '');

        if (empty($metaDesc)) {
            echo "⚠️ Empty response for " . basename($file) . PHP_EOL;
            continue;
        }

        $metaDesc = preg_replace('/^["\']|["\']$/u', '', $metaDesc);
        $metaDesc = trim($metaDesc);
        
        $json = file_get_contents($file);
        $data = json_decode($json, true);
        $data['yoast_metadesc'] = $metaDesc;
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        echo "✅ Enriched: " . basename($file) . " -> " . substr($metaDesc, 0, 40) . "..." . PHP_EOL;
        $processedCount++;
    }
    curl_multi_close($mh);
}

echo "🏁 Enrichment complete! Processed $processedCount helmets." . PHP_EOL;
