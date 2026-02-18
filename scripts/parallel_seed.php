<?php
/**
 * Parallel Brand Seeder
 * Uses curl_multi to hit the REST API endpoints in parallel.
 */

$brand_domains = [
    "100%" => "100percent.com",
    "509" => "ride509.com",
    "6D Helmets" => "6dhelmets.com",
    "AFX" => "afxhelmets.com",
    "AGV" => "agv.com",
    "Airoh" => "airoh.com",
    "Alpinestars" => "alpinestars.com",
    "Answer Racing" => "answerracing.com",
    "Arai" => "araiamericas.com",
    "Bell" => "bellhelmets.com",
    "BILT" => "cyclegear.com",
    "Biltwell" => "biltwellinc.com",
    "BMW" => "bmwmotorcycles.com",
    "Cardo Systems" => "cardosystems.com",
    "EVS" => "evs-sports.com",
    "Fasthouse" => "fasthouse.com",
    "Fly Racing" => "flyracing.com",
    "Fox Racing" => "foxracing.com",
    "FXR" => "fxrracing.com",
    "GMAX" => "gmaxhelmetsusa.com",
    "Hedon" => "hedon.com",
    "Highway 21" => "highway21.com",
    "HJC" => "hjchelmets.com",
    "Icon" => "rideicon.com",
    "Kabuto" => "ogkkabuto.co.jp",
    "Kali Protectives" => "kaliprotectives.com",
    "Kini Red Bull" => "kini.at",
    "Klim" => "klim.com",
    "LaZer" => "lazerhelmets.com",
    "Leatt" => "leatt.com",
    "LS2" => "ls2helmets.com",
    "Nexx" => "nexx-helmets.com",
    "Nolan" => "nolan-helmets.com",
    "O'Neal" => "oneal.com",
    "One Industries" => "oneindustries.com",
    "Ruby" => "ateliers-ruby.com",
    "Schuberth" => "schuberth.com",
    "Scorpion EXO" => "scorpionusa.com",
    "Sedici" => "cyclegear.com",
    "Sena" => "sena.com",
    "Shark" => "shark-helmets.com",
    "Shoei" => "shoei-helmets.com",
    "Simpson" => "simpsonraceproducts.com",
    "Skid Lid" => "skidlid.com",
    "Speed and Strength" => "ssgear.com",
    "Thor" => "thormx.com",
    "Troy Lee Designs" => "troyleedesigns.com",
    "Vespa" => "vespa.com",
    "X-Lite" => "x-lite.it",
    "Z1R" => "z1r.com"
];

$base_url = "https://helmetsan.com/wp-json/hs/v1/brands";
$secret = "hs_parallel_secret_2026";

echo "🚀 Starting Parallel Seeding via REST API...\n";

// 1. Batch Create/Get Brands
echo "📦 Batch creating brands... ";
$ch = curl_init("$base_url/batch");
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    "Content-Type: application/json",
    "X-Helmetsan-Secret: $secret"
]);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode(array_keys($brand_domains)));
$response = curl_exec($ch);
$brands_data = json_decode($response, true);
curl_close($ch);

if (!is_array($brands_data)) {
    die("❌ Error in batch creation: $response\n");
}
echo "OK (" . count($brands_data) . " brands processed)\n";

// 2. Parallel Enrichment (Logo fetching)
echo "🖼️  Starting parallel logo enrichment...\n";
$mh = curl_multi_init();
$curls = [];

foreach ($brands_data as $brand) {
    if (isset($brand['id']) && isset($brand_domains[$brand['name']])) {
        $id = $brand['id'];
        $domain = $brand_domains[$brand['name']];
        
        $ch = curl_init("$base_url/$id/enrich");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            "X-Helmetsan-Secret: $secret"
        ]);
        curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query(['domain' => $domain]));
        
        curl_multi_add_handle($mh, $ch);
        $curls[$id] = [
            'handle' => $ch,
            'name'   => $brand['name']
        ];
    }
}

// Execute parallel requests
$active = null;
do {
    $mrc = curl_multi_exec($mh, $active);
} while ($mrc == CURLM_CALL_MULTI_PERFORM);

while ($active && $mrc == CURLM_OK) {
    if (curl_multi_select($mh) != -1) {
        do {
            $mrc = curl_multi_exec($mh, $active);
        } while ($mrc == CURLM_CALL_MULTI_PERFORM);
    }
}

// Collect results
foreach ($curls as $id => $data) {
    $response = curl_multi_getcontent($data['handle']);
    $info = curl_getinfo($data['handle']);
    $name = $data['name'];
    
    if ($info['http_code'] == 200) {
        echo "✅ Enriched: $name\n";
    } else {
        echo "❌ Failed to enrich: $name (HTTP {$info['http_code']})\n";
        echo "   Response: $response\n";
    }
    
    curl_multi_remove_handle($mh, $data['handle']);
}

curl_multi_close($mh);

echo "\n🎉 Parallel Seeding Complete!\n";
