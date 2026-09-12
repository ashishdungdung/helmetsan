<?php
/**
 * scripts/generate_marketplace_links.php
 * Heuristic generation of affiliate-ready marketplace URLs based on ASIN/EAN/Title.
 */

$helmetDir = __DIR__ . '/../data/helmets';
$tag = 'vtete-20'; // User's Amazon tag from previous conversations

echo "🛒 Generating marketplace links...\n";

foreach (glob("$helmetDir/*.json") as $f) {
    $raw = file_get_contents($f);
    $data = json_decode($raw, true);
    $modified = false;
    
    if (!isset($data['marketplace_links'])) {
        $data['marketplace_links'] = [];
    }
    
    $links = $data['marketplace_links'];
    $title = $data['title'] ?? '';
    $asin  = $data['identifiers']['asin'] ?? '';
    
    // Amazon Link
    if ($asin) {
        $amazonUrl = "https://www.amazon.com/dp/$asin?tag=$tag";
        if (!isset($links['amazon']) || strpos($links['amazon'], 'tag=') === false) {
            $links['amazon'] = $amazonUrl;
            $modified = true;
        }
    } elseif ($title) {
        $searchUrl = "https://www.amazon.com/s?k=" . urlencode($title) . "&tag=$tag";
        if (!isset($links['amazon'])) {
            $links['amazon'] = $searchUrl;
            $modified = true;
        }
    }
    
    // Revzilla Search Link
    if ($title && !isset($links['revzilla'])) {
        $links['revzilla'] = "https://www.revzilla.com/search?_utf8=✓&query=" . urlencode($title);
        $modified = true;
    }

    if ($modified) {
        $data['marketplace_links'] = $links;
        file_put_contents($f, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
        echo "🛒 Links added: " . basename($f) . "\n";
    }
}

echo "✅ Marketplace linking complete.\n";
