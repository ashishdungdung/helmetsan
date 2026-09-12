<?php
/**
 * scripts/link_engine_local.php
 * Scans helmet descriptions and injects internal cross-links into the JSON.
 */

$helmetDir = __DIR__ . '/../data/helmets';
$brandDir  = __DIR__ . '/../data/brands';

// 1. Build Keyword Map
echo "🛰 Building keyword map...\n";
$keywords = [];

// Brands
foreach (glob("$brandDir/*.json") as $f) {
    $data = json_decode(file_get_contents($f), true);
    $name = $data['title'] ?? '';
    if ($name) {
        $keywords[$name] = [
            'type' => 'brand',
            'id'   => $data['id']
        ];
    }
}

// Models (Parents only)
foreach (glob("$helmetDir/*.json") as $f) {
    if (strpos($f, '_') !== false && count(explode('_', basename($f))) > 2) continue; // Skip variants for speed
    $data = json_decode(file_get_contents($f), true);
    if (!isset($data['parent_id'])) {
        $name = $data['title'] ?? '';
        if ($name) {
            $keywords[$name] = [
                'type' => 'helmet',
                'id'   => $data['id']
            ];
        }
    }
}

echo "🔍 Scanning " . count(glob("$helmetDir/*.json")) . " files...\n";

foreach (glob("$helmetDir/*.json") as $f) {
    $raw = file_get_contents($f);
    $data = json_decode($raw, true);
    $modified = false;
    
    $fields = ['marketing_description', 'technical_analysis'];
    $crossLinks = $data['cross_links'] ?? [];
    
    foreach ($fields as $field) {
        if (!isset($data[$field])) continue;
        
        foreach ($keywords as $kw => $info) {
            // Avoid self-linking
            if ($info['id'] === $data['id']) continue;
            
            // Case-insensitive search for the keyword as a whole word
            if (preg_match("/\b" . preg_quote($kw, '/') . "\b/i", $data[$field])) {
                $link = [
                    'keyword' => $kw,
                    'target_id' => $info['id'],
                    'target_type' => $info['type']
                ];
                
                // Add to cross_links if not already there
                $exists = false;
                foreach ($crossLinks as $cl) {
                    if ($cl['target_id'] === $info['id']) {
                        $exists = true;
                        break;
                    }
                }
                
                if (!$exists) {
                    $crossLinks[] = $link;
                    $modified = true;
                }
            }
        }
    }
    
    if ($modified) {
        $data['cross_links'] = $crossLinks;
        file_put_contents($f, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo "🔗 Linked: " . basename($f) . "\n";
    }
}

echo "✅ Internal linking complete.\n";
