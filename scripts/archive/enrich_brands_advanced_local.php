<?php
/**
 * Advanced Brand Data Enrichment (Phase 2.1)
 * 
 * Targets: Story, Motto, Founded Year.
 * API: LM Studio (http://localhost:1234/v1/chat/completions)
 * Model: local
 * 
 * Usage: php scripts/enrich_brands_advanced_local.php
 */

$dataDir = dirname(__DIR__) . '/data/brands';
$apiUrl = 'http://localhost:1234/v1/chat/completions';
$model = 'local';

echo "🚀 Starting Advanced Brand Data Enrichment (Local AI)..." . PHP_EOL;

$files = glob($dataDir . '/*.json');
if ($files === false || empty($files)) {
    die("❌ No JSON files found in $dataDir" . PHP_EOL);
}

$processedCount = 0;

foreach ($files as $file) {
    $json = file_get_contents($file);
    $data = json_decode($json, true);
    if (!$data) continue;
    
    $title = $data['title'] ?? basename($file, '.json');
    
    // Check if missing brand story or motto
    if (!empty($data['profile']['story']) && !empty($data['profile']['motto'])) {
        echo "✅ Skipping $title (already enriched)" . PHP_EOL;
        continue;
    }

    $prompt = "You are a motorcycle industry historian. Provide background information for this helmet brand:
Brand: $title

Requirements:
1. Story: A 2-paragraph history of the brand, its founding, and its contribution to helmet safety.
2. Motto: The brand's official motto or a characteristic slogan.
3. Founded Year: The exact year the brand was established (4 digits).

Return ONLY a valid JSON object with these exact keys:
- story (string)
- motto (string)
- founded_year (integer)

No extra text, no markdown.";

    $postData = [
        'model' => $model,
        'messages' => [
            ['role' => 'system', 'content' => 'You return brand history as JSON only.'],
            ['role' => 'user', 'content' => $prompt]
        ],
        'temperature' => 0.3,
        'max_tokens' => 800
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 60);
    
    echo "📖 Researching Brand: $title..." . PHP_EOL;
    $response = curl_exec($ch);
    $error = curl_error($ch);
    curl_close($ch);

    if ($error) {
        echo "❌ API Error: $error" . PHP_EOL;
        continue;
    }

    $responseData = json_decode($response, true);
    $content = trim($responseData['choices'][0]['message']['content'] ?? '');
    
    if (preg_match('/```json\s*(.*?)\s*```/s', $content, $matches)) {
        $content = $matches[1];
    } elseif (preg_match('/\{.*\}/s', $content, $matches)) {
        $content = $matches[0];
    }

    $enriched = json_decode($content, true);

    if (empty($enriched)) {
        echo "⚠️ Failed to parse AI response for $title" . PHP_EOL;
        continue;
    }

    foreach ($enriched as $key => $val) {
        if (!empty($val)) {
            $data['profile'][$key] = $val;
        }
    }

    file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

    echo "✅ Brand Enriched: $title" . PHP_EOL;
    $processedCount++;
}

echo PHP_EOL . "🏁 Brand enrichment complete! Processed $processedCount brands." . PHP_EOL;
