<?php
/**
 * scripts/fix_missing_brands_final.php
 * Final pass to generate high-fidelity, non-hallucinated brand profiles.
 */

$missingBrands = [
    'Apex', 'GMax', 'Sedici', 'Fox Racing', 'Troy Lee Designs', 'Lazer', '6D', 
    'GIVI', 'Premier', 'Suomy', 'OGK Kabuto', 'Z1R', 'Torc', 'CKX', 'Daytona', 
    'Fly Racing', 'Zox', 'Vemar', 'WCL', 'Speed and Strength', 'ILM', 'Bilmola', 
    'ROOF', 'Sena', 'Spada', 'Hawk', 'Bering', 'Macna', 'Moose Racing', 
    'Marushin', 'Leatt', 'Joe Rocket', 'DMD', 'Scorpion'
];

$dataRoot = __DIR__ . '/../data/brands';
$apiUrl = 'http://localhost:1234/v1/chat/completions';

foreach ($missingBrands as $brandName) {
    $id = strtolower(str_replace(' ', '-', $brandName));
    $path = "$dataRoot/$id.json";
    
    if (file_exists($path)) {
        echo "⏩ Skipping $brandName (exists)\n";
        continue;
    }

    echo "🏗 Generating profile for $brandName...\n";

    $prompt = "You are a motorcycle helmet industry historian. Provide a FACTUAL brand profile for '$brandName'. 
    Rules:
    1. Do NOT hallucinate. If you don't know the founding year, use a reasonable estimate or leave as 0.
    2. Focus on MOTORCYCLING history. 
    3. Suomy is a racing helmet brand founded by Umberto Monti in 1997, NOT a leather bag brand.
    4. KYT is Indonesian.
    5. Scorpion (ScorpionExo) is a major global brand.
    
    Return ONLY a JSON object with this structure:
    {
        \"entity\": \"brand\",
        \"id\": \"$id\",
        \"title\": \"$brandName\",
        \"profile\": {
            \"brand_name\": \"$brandName\",
            \"origin_country\": \"(string)\",
            \"warranty_terms\": \"(string)\",
            \"support_url\": \"(url)\",
            \"manufacturing_ethos\": \"(1 sentence about how they make things)\",
            \"story\": \"(2-3 paragraphs of factual history and market positioning)\",
            \"motto\": \"(string)\",
            \"founded_year\": (int)
        }
    }";

    $ch = curl_init($apiUrl);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
        'model' => 'local-model',
        'messages' => [['role' => 'user', 'content' => $prompt]],
        'temperature' => 0.2
    ]));
    curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
    
    $response = curl_exec($ch);
    $data = json_decode($response, true);
    $content = $data['choices'][0]['message']['content'] ?? '';
    
    // Clean markdown blocks
    $content = preg_replace('/```json\s*|```/', '', $content);
    $content = trim($content);
    
    if (json_decode($content)) {
        file_put_contents($path, $content);
        echo "✅ Created: $id.json\n";
    } else {
        echo "❌ Failed to generate valid JSON for $brandName\n";
    }
}
