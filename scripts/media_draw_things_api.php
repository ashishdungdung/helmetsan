<?php
/**
 * scripts/media_draw_things_api.php
 * Script to automate image generation via Draw Things Local API (Mac).
 * 
 * Instructions:
 * 1. Open Draw Things on your Mac.
 * 2. Enable "API Server" in settings (usually port 7860).
 */

require_once __DIR__ . '/media_pipeline_prompts.php';

$helmetDir = __DIR__ . '/../data/helmets';
$mediaDir  = __DIR__ . '/../data/media/draw_things';
$apiUrl    = 'http://127.0.0.1:7860/sdapi/v1/txt2img'; // Standard SD API port

if (!is_dir($mediaDir)) mkdir($mediaDir, 0755, true);

$files = glob("$helmetDir/*.json");

echo "🖥 Draw Things Local Automation (M4 Pro)...\n";

foreach ($files as $f) {
    $data = json_decode(file_get_contents($f), true);
    $id = $data['id'];
    $savePath = "$mediaDir/{$id}_draw_things.png";
    
    if (file_exists($savePath)) continue;

    echo "🖼 Sending $id to local GPU...\n";
    
    $prompt = generate_helmet_prompt($data);
    
    $payload = [
        'prompt' => $prompt,
        'steps' => 20,
        'width' => 1024,
        'height' => 1024,
        'cfg_scale' => 7.5,
        'sampler_name' => 'Euler a'
    ];

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $result = json_decode($response, true);
    curl_close($ch);
    
    if (isset($result['images'][0])) {
        file_put_contents($savePath, base64_decode($result['images'][0]));
        echo "✅ Rendered and saved via M4 Pro: $id\n";
    } else {
        echo "❌ API Error for $id. Make sure Draw Things API Server is running on $apiUrl\n";
        // break; // Stop on first error to prevent flooding
    }
}

echo "✨ M4 Pro Render Batch complete.\n";
echo "💡 To ingest these images into WordPress, run:\n";
echo "   wp helmetsan media ingest-local --dir=draw_things --allow-root\n";
