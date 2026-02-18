<?php
/**
 * Local Logo Fetcher
 * Downloads logos from Clearbit to local directory.
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

$save_dir = __DIR__ . '/../helmetsan-core/data/logos';

echo "🚀 Fetching Logos Locally...\n";

foreach ($brand_domains as $brand => $domain) {
    $slug = sanitize_title($brand);
    $url = "https://logo.clearbit.com/" . $domain;
    $file = "$save_dir/$slug.png";

    if (file_exists($file)) {
        echo "✅ Exists: $brand\n";
        continue;
    }

    echo "⬇️ Downloading $brand ($domain)... ";
    
    $options = [
        "http" => [
            "method" => "GET",
            "header" => "User-Agent: Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36\r\n" .
                        "Accept: image/png,image/*;q=0.8\r\n"
        ]
    ];
    $context = stream_context_create($options);
    $content = @file_get_contents($url, false, $context);

    if ($content) {
        file_put_contents($file, $content);
        echo "OK\n";
    } else {
        echo "FAILED\n";
    }
}

function sanitize_title($title) {
    return strtolower(trim(preg_replace('/[^A-Za-z0-9-]+/', '-', $title)));
}
