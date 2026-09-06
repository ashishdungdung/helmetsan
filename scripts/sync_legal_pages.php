<?php
/**
 * Helmetsan Legal Pages Synchronizer
 *
 * Reads HTML content from the /legal directory and synchronizes it to WordPress
 * database records with verification, parent linkage, and logging.
 *
 * Usage:
 *   php scripts/sync_legal_pages.php [--dry-run]
 */

declare(strict_types=1);

// Find WordPress wp-load.php
$wpLoadPaths = [
    __DIR__ . '/../public/wp-load.php',
    __DIR__ . '/../../public/wp-load.php',
    '/var/www/helmetsan.com/public/wp-load.php',
];

$wpLoaded = false;
foreach ($wpLoadPaths as $path) {
    if (file_exists($path)) {
        require_once $path;
        $wpLoaded = true;
        break;
    }
}

$isDryRun = in_array('--dry-run', $argv ?? [], true);

echo "=====================================================\n";
echo " Helmetsan Legal Pages Synchronization Engine\n";
echo "=====================================================\n";
echo "Mode: " . ($isDryRun ? "DRY-RUN (no changes applied)" : "LIVE APPLY") . "\n";
echo "WordPress Context: " . ($wpLoaded ? "LOADED" : "STANDALONE CLI") . "\n\n";

$legalDir = realpath(__DIR__ . '/../legal');
if (! $legalDir || ! is_dir($legalDir)) {
    fwrite(STDERR, "Error: Legal content directory not found at $legalDir\n");
    exit(1);
}

$pages = [
    [
        'id'          => 109,
        'slug'        => 'legal',
        'title'       => 'Legal',
        'file'        => 'legal-hub.html',
        'parent_id'   => 0,
        'menu_order'  => 0,
    ],
    [
        'id'          => 164,
        'slug'        => 'ai-policy',
        'title'       => 'AI Policy',
        'file'        => 'ai-policy.html',
        'parent_id'   => 109,
        'menu_order'  => 1,
    ],
    [
        'id'          => 112,
        'slug'        => 'affiliate-disclosure',
        'title'       => 'Affiliate Disclosure',
        'file'        => 'affiliate-disclosure.html',
        'parent_id'   => 109,
        'menu_order'  => 2,
    ],
    [
        'id'          => 113,
        'slug'        => 'disclaimer',
        'title'       => 'Disclaimer',
        'file'        => 'disclaimer.html',
        'parent_id'   => 109,
        'menu_order'  => 3,
    ],
    [
        'id'          => 114,
        'slug'        => 'cookie-policy',
        'title'       => 'Cookie Policy',
        'file'        => 'cookie-policy.html',
        'parent_id'   => 109,
        'menu_order'  => 4,
    ],
    [
        'id'          => 111,
        'slug'        => 'terms-of-use',
        'title'       => 'Terms of Use',
        'file'        => 'terms-of-use.html',
        'parent_id'   => 109,
        'menu_order'  => 5,
    ],
    [
        'id'          => 3,
        'slug'        => 'privacy-policy',
        'title'       => 'Privacy Policy',
        'file'        => 'privacy-policy.html',
        'parent_id'   => 109,
        'menu_order'  => 6,
    ],
];

$successCount = 0;

foreach ($pages as $p) {
    $filePath = $legalDir . '/' . $p['file'];
    if (! file_exists($filePath)) {
        echo "[ERROR] Missing source file: {$filePath}\n";
        continue;
    }

    $content = file_get_contents($filePath);
    $bytes = strlen($content);

    echo sprintf("-> [%-20s] ID: %-5d | File: %-25s | Size: %6d bytes\n", $p['slug'], $p['id'], $p['file'], $bytes);

    if (! $wpLoaded) {
        // Output format check only
        $successCount++;
        continue;
    }

    if ($isDryRun) {
        $existing = get_post($p['id']);
        if ($existing) {
            echo "   [DRY-RUN] Target exists: '{$existing->post_title}' (Status: {$existing->post_status})\n";
        } else {
            echo "   [DRY-RUN] Target page ID {$p['id']} will be created or slug-matched.\n";
        }
        $successCount++;
        continue;
    }

    // Live update
    $postData = [
        'ID'           => $p['id'],
        'post_title'   => $p['title'],
        'post_name'    => $p['slug'],
        'post_content' => $content,
        'post_status'  => 'publish',
        'post_type'    => 'page',
        'post_parent'  => $p['parent_id'],
        'menu_order'   => $p['menu_order'],
    ];

    $updatedId = wp_update_post($postData, true);
    if (is_wp_error($updatedId)) {
        echo "   [ERROR] Failed to update post ID {$p['id']}: " . $updatedId->get_error_message() . "\n";
    } else {
        echo "   [OK] Successfully synchronized post ID {$updatedId} ('{$p['title']}')\n";
        $successCount++;
    }
}

echo "\nCompleted. Successfully processed {$successCount} of " . count($pages) . " legal documents.\n";
