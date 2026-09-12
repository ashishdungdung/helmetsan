<?php
/**
 * scripts/generate_prompt_manifest.php
 * Generates a full CSV/JSON manifest of cinematic prompts for all helmets.
 * Perfect for batch importing into tools like Draw Things or Midjourney.
 */

require_once __DIR__ . '/media_pipeline_prompts.php';

$helmetDir = __DIR__ . '/../data/helmets';
$manifestFile = __DIR__ . '/../data/media/prompt_manifest.csv';

if (!is_dir(dirname($manifestFile))) mkdir(dirname($manifestFile), 0755, true);

$files = glob("$helmetDir/*.json");
$fp = fopen($manifestFile, 'w');

// Header
fputcsv($fp, ['id', 'title', 'brand', 'prompt']);

echo "📝 Generating manifest for " . count($files) . " helmets...\n";

foreach ($files as $f) {
    $data = json_decode(file_get_contents($f), true);
    $prompt = generate_helmet_prompt($data);
    
    fputcsv($fp, [
        $data['id'],
        $data['title'],
        $data['brand'],
        $prompt
    ]);
}

fclose($fp);
echo "✅ Manifest created at: data/media/prompt_manifest.csv\n";
