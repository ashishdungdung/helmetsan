<?php
/**
 * Verification script for Correction Center 2.0
 */
require_once __DIR__ . '/../../../../wp-load.php';

use Helmetsan\Core\AI\HealService;

// Mock some data
$fileName = 'helmet_test-verify.json';
$root = helmetsan_core()->config->dataRoot();
$corrPath = $root . '/corrections/' . $fileName;

if (!is_dir(dirname($corrPath))) {
    mkdir(dirname($corrPath), 0755, true);
}

// Create a mock master file if it doesn't exist
$masterDir = $root . '/helmets';
if (!is_dir($masterDir)) {
    mkdir($masterDir, 0755, true);
}
$masterPath = $masterDir . '/test-verify.json';
file_put_contents($masterPath, json_encode([
    'id' => 'test-verify',
    'title' => 'Original Helmet',
    'rating' => 4.0
], JSON_PRETTY_PRINT));

// Create a mock correction
file_put_contents($corrPath, json_encode([
    'id' => 'test-verify',
    'title' => 'Healed Helmet',
    'rating' => 4.5
], JSON_PRETTY_PRINT));

echo "Staged correction created: {$fileName}\n";

// Attempt to commit via HealService
$healService = new HealService(
    helmetsan_core()->repository,
    helmetsan_core()->ingestion,
    helmetsan_core()->heals,
    helmetsan_core()->logger
);

$result = $healService->commitCorrection($fileName);

if ($result['ok']) {
    echo "SUCCESS: " . $result['message'] . "\n";
    $finalData = json_decode(file_get_contents($masterPath), true);
    if ($finalData['title'] === 'Healed Helmet' && $finalData['rating'] === 4.5) {
        echo "VERIFIED: Master file updated correctly.\n";
    } else {
        echo "FAILED: Master file data mismatch.\n";
    }
} else {
    echo "ERROR: " . $result['message'] . "\n";
}

// Cleanup mock
@unlink($masterPath);
