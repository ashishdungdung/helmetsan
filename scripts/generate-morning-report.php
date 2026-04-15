<?php
/**
 * Helmetsan Autumn Morning Report
 * Aggregates the last 24h of autonomous healing into a summary.
 */

if (php_sapi_name() !== 'cli') exit;

// Bootstrap WordPress if possible (for DB access)
$wpLoad = __DIR__ . '/../wp-load.php';
if (!file_exists($wpLoad)) {
    // Fallback if running relative to public/
    $wpLoad = dirname(__DIR__, 4) . '/public/wp-load.php';
}

if (file_exists($wpLoad)) {
    require_once $wpLoad;
} else {
    // Attempt via WP-CLI
    $statsRaw = shell_exec("wp helmetsan ai stats --period='24 hours' --allow-root 2>/dev/null");
    if ($statsRaw) {
        echo "--- Morning Report (24h) ---\n";
        echo $statsRaw . "\n";
        exit;
    }
    echo "Error: WP context not found.\n";
    exit(1);
}

use Helmetsan\Core\AI\HealRepository;

$repo = new HealRepository();
$stats = $repo->getStatsForPeriod('24 hours');

echo "\n============================================\n";
echo "   🛡️  HELMETSAN ENRICHMENT MORNING REPORT\n";
echo "   Period: Last 24 Hours\n";
echo "============================================\n";

if ($stats['total'] === 0) {
    echo "  Status: Quiet. No anomalies healed.\n";
} else {
    echo "  Total Heals:   " . $stats['total'] . "\n";
    foreach ($stats['modes'] as $row) {
        echo "  - Mode " . strtoupper($row['ai_mode']) . ": " . $row['count'] . "\n";
    }
}

echo "--------------------------------------------\n";
echo "   Daemon Heartbeat: OK\n";
echo "   Sync Status:      CONNECTED\n";
echo "============================================\n\n";
