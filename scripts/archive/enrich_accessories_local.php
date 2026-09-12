<?php
/**
 * Accessory Technical Enrichment Script
 * 
 * Targets: Pinlock, Electric, Snow compatibility and Color/Subcategory.
 * API: LM Studio (http://localhost:1234/v1/chat/completions)
 * Model: local
 * 
 * Usage: php scripts/enrich_accessories_local.php --limit=50
 */

$dataDir = dirname(__DIR__) . '/data/accessories';
$apiUrl = 'http://localhost:1234/v1/chat/completions';
$model = 'local';

// CLI Defaults
$options = getopt("", ["limit:"]);
$limit = isset($options['limit']) ? (int)$options['limit'] : 50;

echo "🚀 Starting Accessory Technical Enrichment (Local AI)..." . PHP_EOL;

$files = glob($dataDir . '/*.json');
if ($files === false || empty($files)) {
    die("❌ No JSON files found in $dataDir" . PHP_EOL);
}

$processedCount = 0;

foreach ($files as $file) {
    if ($processedCount >= $limit) break;
    
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    if (!$data) continue;
    
    $title = $data['title'] ?? basename($file);
    
    // Skip if already has technical flags
    if (isset($data['accessory_pinlock_ready']) && isset($data['accessory_electric_compatible'])) {
        continue;
    }

    $prompt = "You are a motorcycle gear expert. Analyze this accessory and provide technical flags:
Title: $title

Requirements:
1. Pinlock Ready: Is it a visor/shield that supports Pinlock inserts? (0 or 1).
2. Electric Compatible: Is it heated or for snow/cold weather electric use? (0 or 1).
3. Snow Compatible: Is it designed for snowmobile helmets? (0 or 1).
4. Subcategory: More specific type (e.g. 'Photochromic Visor', 'Mesh Intercom', 'Cheek Pads').

Return ONLY a valid JSON object with these exact keys:
- pinlock_ready (integer)
- electric_compatible (integer)
- snow_compatible (integer)
- subcategory (string)

No extra text, no markdown.";

    $postData = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => 'You return technical accessory flags as JSON only.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.1
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    echo "📖 Researching Accessory: $title..." . PHP_EOL;
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo "❌ API Error: $error" . PHP_EOL;
        continue;
    }

    $responseData = json_decode($response, true);
    $content = trim($responseData['choices'][0]['message']['content'] ?? '');
    
    if (preg_match('/\{.*\}/s', $content, $matches)) {
        $content = $matches[0];
    }

    $enriched = json_decode($content, true);

    if (empty($enriched)) {
        echo "⚠️ Failed to parse AI response for $title" . PHP_EOL;
        continue;
    }

    $data['accessory_pinlock_ready'] = (int) ($enriched['pinlock_ready'] ?? 0);
    $data['accessory_electric_compatible'] = (int) ($enriched['electric_compatible'] ?? 0);
    $data['accessory_snow_compatible'] = (int) ($enriched['snow_compatible'] ?? 0);
    $data['accessory_subcategory'] = $enriched['subcategory'] ?? '';

    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    echo "✅ Accessory Enriched: $title" . PHP_EOL;
    $processedCount++;
}

echo PHP_EOL . "🏁 Accessory enrichment complete! Processed $processedCount items." . PHP_EOL;
