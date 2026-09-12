<?php
/**
 * scripts/media_pipeline_prompts.php
 * Generates cinematic image prompts based on helmet technical metadata.
 */

function generate_helmet_prompt($helmetData) {
    $title = $helmetData['title'] ?? 'Motorcycle Helmet';
    $color = $helmetData['color'] ?? 'Black';
    $finish = $helmetData['finish'] ?? 'Gloss';
    $material = $helmetData['specs']['material'] ?? 'Polycarbonate';
    $type = $helmetData['type'] ?? 'Full Face';
    
    $materialVisual = "";
    if (stripos($material, 'carbon') !== false) {
        $materialVisual = "highly detailed exposed carbon fiber weave texture, ";
    }
    
    $lighting = "dramatic studio lighting with rim highlights";
    if ($type === 'Dirt / MX') {
        $lighting = "atmospheric outdoor motocross track lighting, sunset, dust particles";
    } elseif ($type === 'Modular' || $type === 'Full Face') {
        $lighting = "cinematic dark studio photography, neon accents, sharp highlights";
    }
    
    $prompt = "Professional 8k studio product photography of a $title motorcycle helmet. ";
    $prompt .= "Features: $finish $color finish, $materialVisual floating in air. ";
    $prompt .= "Lighting: $lighting. ";
    $prompt .= "Composition: side view or three-quarter view, blurred background, hyper-realistic, octane render, unreal engine 5 style.";
    
    return $prompt;
}

// Self-test: only runs when this file is invoked directly (not when require'd)
if (php_sapi_name() === 'cli' && realpath($argv[0] ?? '') === realpath(__FILE__)) {
    $testFiles = [
        __DIR__ . '/../data/helmets/bell_bullitt.json',
        __DIR__ . '/../data/helmets/hjc_rpha_11_pro.json',
        __DIR__ . '/../data/helmets/scorpion_exo_r1_air.json'
    ];

    foreach ($testFiles as $f) {
        if (file_exists($f)) {
            $data = json_decode(file_get_contents($f), true);
            echo "🎨 Prompt for " . $data['title'] . ":\n" . generate_helmet_prompt($data) . "\n\n";
        }
    }
}
