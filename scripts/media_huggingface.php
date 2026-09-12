<?php
/**
 * scripts/media_huggingface.php
 * Script to generate high-quality images via Hugging Face Inference API.
 */

require_once __DIR__ . '/media_pipeline_prompts.php';

$hf_token = getenv('HF_TOKEN');
$model_id = 'black-forest-labs/FLUX.1-schnell';
$apiUrl   = "https://api-inference.huggingface.co/models/$model_id";

if (!$hf_token) {
    echo "⚠️ HF_TOKEN environment variable not set. Please export it first.\n";
    exit(1);
}

$helmetDir = __DIR__ . '/../data/helmets';
$mediaDir  = __DIR__ . '/../data/media/hf_flux';

if (!is_dir($mediaDir)) mkdir($mediaDir, 0755, true);

$files = glob("$helmetDir/*.json");

foreach ($files as $f) {
    $data = json_decode((string)file_get_contents($f), true);
    if (!is_array($data) || empty($data['id'])) continue;
    
    $id = $data['id'];
    $savePath = "$mediaDir/{$id}_hf_flux.webp";

    if (file_exists($savePath)) continue;

    echo "🚀 Requesting FLUX.1 render for $id...\n";
    
    $prompt = generate_helmet_prompt($data);
    
    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 30);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(['inputs' => $prompt]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        'Authorization: Bearer ' . $hf_token,
        'Content-Type: application/json'
    ]);
    
    $response = curl_exec($ch);
    $info = curl_getinfo($ch);
    curl_close($ch);
    
    if ($info['http_code'] === 200) {
        file_put_contents($savePath, $response);
        echo "✅ High-Fidelity Flux Render Saved: $id\n";
    } elseif ($info['http_code'] === 503) {
        echo "⏳ Model is loading for $id. Skip for now.\n";
    } else {
        echo "❌ Error for $id: HTTP " . $info['http_code'] . "\n";
    }

    // Brief sleep to be nice to the API
    usleep(500000); 
}

echo "✨ Hugging Face Batch complete.\n";
echo "💡 To ingest these images into WordPress, run:\n";
echo "   wp helmetsan media ingest-local --dir=hf_flux --allow-root\n";
