<?php

declare(strict_types=1);

/**
 * Deterministic AED Pricing Backfill Script
 *
 * Backfills 'aed' in data/helmets/*.json using the fixed USD peg (1 USD = 3.67 AED)
 * to unlock Amazon UAE Associates affiliate routing (vtete0c-21) and eliminate
 * missing-currency validation warnings.
 *
 * Uses atomic writes (temp file + rename) to guarantee file integrity.
 */

$rootDir = dirname(__DIR__);
$helmetsDir = $rootDir . '/data/helmets';

if (! is_dir($helmetsDir)) {
    fwrite(STDERR, "❌ Directory not found: {$helmetsDir}\n");
    exit(1);
}

$files = glob($helmetsDir . '/*.json');
if ($files === false || empty($files)) {
    fwrite(STDERR, "❌ No helmet JSON files found in {$helmetsDir}\n");
    exit(1);
}

$startTime = microtime(true);
$updated = 0;
$skipped = 0;
$errors = 0;

echo "========================================================\n";
echo "🇦🇪 HELMETSAN DETERMINISTIC AED PRICING BACKFILL\n";
echo "========================================================\n";
echo "Target directory: {$helmetsDir}\n";
echo "Files found:      " . count($files) . "\n\n";

foreach ($files as $file) {
    $content = file_get_contents($file);
    if ($content === false) {
        fwrite(STDERR, "❌ Cannot read file: {$file}\n");
        $errors++;
        continue;
    }

    $data = json_decode($content, true);
    if (! is_array($data)) {
        fwrite(STDERR, "❌ Invalid JSON in file: {$file}\n");
        $errors++;
        continue;
    }

    if (! isset($data['price']) || ! is_array($data['price']) || ! isset($data['price']['usd']) || ! is_numeric($data['price']['usd'])) {
        $skipped++;
        continue;
    }

    $usd = (float) $data['price']['usd'];
    $aed = round($usd * 3.67, 2);
    if ($aed == (int) $aed) {
        $aed = (int) $aed;
    }

    // Preserve canonical key order: usd, inr, eur, gbp, aed, jpy
    $oldPrice = $data['price'];
    $newPrice = [];
    $newPrice['usd'] = $oldPrice['usd'];
    if (isset($oldPrice['inr'])) {
        $newPrice['inr'] = $oldPrice['inr'];
    }
    if (isset($oldPrice['eur'])) {
        $newPrice['eur'] = $oldPrice['eur'];
    }
    if (isset($oldPrice['gbp'])) {
        $newPrice['gbp'] = $oldPrice['gbp'];
    }
    $newPrice['aed'] = $aed;
    if (isset($oldPrice['jpy'])) {
        $newPrice['jpy'] = $oldPrice['jpy'];
    }
    foreach ($oldPrice as $k => $v) {
        if (! isset($newPrice[$k])) {
            $newPrice[$k] = $v;
        }
    }

    $data['price'] = $newPrice;

    // Atomic write with 2-space indentation to match existing catalog format
    $rawJson = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    $jsonEncoded = preg_replace_callback('/^(?: {4})+/m', function ($matches) {
        return str_repeat('  ', (int) (strlen($matches[0]) / 4));
    }, (string) $rawJson) . "\n";
    $tempFile = tempnam($helmetsDir, 'hs_aed_');
    if ($tempFile === false) {
        fwrite(STDERR, "❌ Failed to create temp file for {$file}\n");
        $errors++;
        continue;
    }

    if (file_put_contents($tempFile, $jsonEncoded) === false) {
        fwrite(STDERR, "❌ Failed to write temp file for {$file}\n");
        unlink($tempFile);
        $errors++;
        continue;
    }

    if (! rename($tempFile, $file)) {
        fwrite(STDERR, "❌ Failed to atomically overwrite {$file}\n");
        unlink($tempFile);
        $errors++;
        continue;
    }

    $updated++;
}

$elapsed = round((microtime(true) - $startTime) * 1000, 2);

echo "✅ Backfill complete in {$elapsed} ms\n";
echo "   - Updated: {$updated} files\n";
echo "   - Skipped: {$skipped} files\n";
echo "   - Errors:  {$errors} files\n";
echo "========================================================\n";

if ($errors > 0) {
    exit(1);
}
exit(0);
