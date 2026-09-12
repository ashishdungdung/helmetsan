<?php
/**
 * Discover and Generate New Helmets (Local AI)
 * 
 * 1. Takes a brand.
 * 2. Asks AI for missing real-world models.
 * 3. Generates the full technical JSON for each.
 */

$dataDir = dirname(__DIR__) . '/data/helmets';
$apiUrl = 'http://localhost:1234/v1/chat/completions';
$model = 'local';

$brand = $argv[1] ?? 'Alpinestars';
$limit = (int)($argv[2] ?? 5);

echo "🔍 Discovering NEW helmets for $brand..." . PHP_EOL;

// Get existing IDs for this brand
$existingFiles = glob($dataDir . '/' . strtolower($brand) . '_*.json');
$existingIds = array_map(fn($f) => basename($f, '.json'), $existingFiles);

$prompt = "You are a motorcycle gear researcher. List $limit REAL WORLD MOTORCYCLE HELMET models (Not boots, Not gloves) for the brand '$brand' that are NOT in this list: " . implode(', ', $existingIds) . ".
IMPORTANT: For Alpinestars, only list models from the 'Supertech R10' or 'SM' (SM5, SM8, SM10) series. Do NOT list 'Tech 7', 'Tech 10', or 'SMX' as those are boots.
Return ONLY a comma-separated list of model names. No brand names, no extra text.";

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
$newModels = array_map('trim', explode(',', $rawList));

echo "✨ AI suggested: " . implode(', ', $newModels) . PHP_EOL;

foreach ($newModels as $modelName) {
    if (empty($modelName)) continue;
    
    // Normalize model name (remove brand if AI included it)
    $cleanModel = trim(str_ireplace($brand, '', $modelName));
    $id = strtolower(str_replace([' ', '-'], '_', $brand . ' ' . $cleanModel));
    $id = preg_replace('/_+/', '_', $id);
    $file = "$dataDir/$id.json";
    
    if (file_exists($file)) {
        echo "⏩ Skipping $modelName (already exists as $id)" . PHP_EOL;
        continue;
    }

    echo "🏗 Generating full JSON for $modelName..." . PHP_EOL;

    $genPrompt = "Generate a full technical specification JSON for the '$brand $modelName' helmet.
Include:
- title, brand, type (Full Face, Modular, etc)
- price (retail USD)
- specs (material, weight_g, certifications, warranty_years, strap_type)
- head_shape
- aero_acoustic_profile (noise_db_at_100kph, ventilation_efficiency_score)
- fitment_coordinates (internal_length_mm, internal_width_mm, crown_depth_mm)
- technical_analysis (3 paragraphs)
- marketing_description (2 paragraphs)
- yoast_metadesc, yoast_focuskw

Return ONLY valid JSON. No markdown code blocks.";

    $postData['messages'] = [['role' => 'user', 'content' => $genPrompt]];
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($postData));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    curl_setopt($ch, CURLOPT_TIMEOUT, 120);
    $response = curl_exec($ch);
    curl_close($ch);

    $resData = json_decode($response, true);
    $content = trim($resData['choices'][0]['message']['content'] ?? '');
    
    if (preg_match('/\{.*\}/s', $content, $matches)) {
        $content = $matches[0];
    }

    $helmetData = json_decode($content, true);
    if ($helmetData) {
        $helmetData['entity'] = 'helmet';
        $helmetData['id'] = $id;
        $helmetData['deep_enriched'] = true;
        
        file_put_contents($file, json_encode($helmetData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo "✅ Created: $id.json" . PHP_EOL;
    } else {
        echo "❌ Failed to generate JSON for $modelName" . PHP_EOL;
    }
}
