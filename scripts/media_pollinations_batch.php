<?php
/**
 * scripts/media_pollinations_batch.php
 * Batch generator using Pollinations.ai (Free API).
 */

require_once __DIR__ . '/media_pipeline_prompts.php';

$helmetDir = __DIR__ . '/../data/helmets';
$mediaDir  = __DIR__ . '/../data/media/helmets';
$limit     = 50; // Per run
$count     = 0;

if (!is_dir($mediaDir)) mkdir($mediaDir, 0755, true);

$files = glob("$helmetDir/*.json");
shuffle($files); // Process in random order to cover variety

echo "🚀 Starting Pollinations AI Batch (Limit: $limit)...\n";

foreach ($files as $f) {
    if ($count >= $limit) break;
    
    $data = json_decode(file_get_contents($f), true);
    $id   = $data['id'];
    
    // Skip if already has a non-placeholder image
    $hasRealImage = false;
    foreach ($data['geo_media'] ?? [] as $img) {
        if (strpos($img, 'placehold.co') === false && strpos($img, 'pollinations.ai') === false) {
            $hasRealImage = true;
            break;
        }
    }
    
    if ($hasRealImage) continue;

    echo "🎨 Generating for $id...\n";
    
    $prompt = generate_helmet_prompt($data);
    $encodedPrompt = urlencode($prompt);
    
    // Pollinations URL
    $url = "https://image.pollinations.ai/prompt/{$encodedPrompt}?width=1024&height=1024&nologo=true&seed=" . rand(1, 999999);
    
    $filename = "{$id}_ai_pollination.jpg";
    $savePath = "$mediaDir/$filename";
    
    // Download
    $content = file_get_contents($url);
    if ($content && strlen($content) > 10000) { // Basic sanity check for image data
        file_put_contents($savePath, $content);
        echo "✅ Saved: $filename\n";
        $count++;
        
        // Brief sleep to be nice to the free API
        usleep(500000); 
    } else {
        echo "❌ API Error for $id. Skip.\n";
    }
}

echo "✨ Batch complete.\n";
echo "💡 To ingest these images into WordPress, run:\n";
echo "   wp helmetsan media ingest-local --dir=helmets --allow-root\n";
echo "🏁 Generated $count images.\n";
