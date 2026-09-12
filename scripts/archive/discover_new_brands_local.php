<?php
/**
 * Discover and Generate New Brands (Local AI)
 */

$dataDir = dirname(__DIR__) . '/data/brands';
$apiUrl = 'http://localhost:1234/v1/chat/completions';
$model = 'local';

echo "🔍 Discovering NEW brands..." . PHP_EOL;

$files = glob($dataDir . '/*.json');
$existingBrands = array_map(fn($f) => basename($f, '.json'), $files);

$prompt = "List 5 REAL WORLD motorcycle helmet brands that are NOT in this list: " . implode(', ', $existingBrands) . ".
Focus on international brands like KYT, Suomy, Lazer, etc.
Return ONLY a comma-separated list of brand names.";

$postData = [
    'model' => $model,
    'messages' => [['role' => 'user', 'content' => $prompt]],
    'temperature' => 0.1
];

$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
$response = curl_exec($ch);
curl_close($ch);

$resData = json_decode($response, true);
$rawList = trim($resData['choices'][0]['message']['content'] ?? '');
$newBrands = array_map('trim', explode(',', $rawList));

echo "✨ AI suggested: " . implode(', ', $newBrands) . PHP_EOL;

foreach ($newBrands as $brandName) {
    if (empty($brandName)) continue;
    
    $id = strtolower(str_replace([' ', '-'], '_', $brandName));
    $file = "$dataDir/$id.json";
    
    if (file_exists($file)) continue;

    echo "🏗 Generating profile for $brandName..." . PHP_EOL;

    $genPrompt = "Generate a brand profile JSON for '$brandName'.
Include:
- origin_country
- warranty_terms
- support_url
- manufacturing_ethos
- story (2 paragraphs)
- motto
- founded_year (integer)

Return ONLY valid JSON.";

    $postData['messages'] = [['role' => 'user', 'content' => $genPrompt]];
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    $response = curl_exec($ch);
    curl_close($ch);

    $resData = json_decode($response, true);
    $content = trim($resData['choices'][0]['message']['content'] ?? '');
    
    if (preg_match('/\{.*\}/s', $content, $matches)) {
        $content = $matches[0];
    }

    $profile = json_decode($content, true);
    if ($profile) {
        $data = [
            'entity' => 'brand',
            'id' => $id,
            'title' => $brandName,
            'profile' => $profile
        ];
        
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo "✅ Created: $id.json" . PHP_EOL;
    }
}
