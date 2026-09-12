<?php
/**
 * CLI script to query Google Live Intelligence (GA4 & GSC)
 * Usage: php scripts/google_intelligence_cli.php [--period=30] [--force]
 */

$webDir = dirname(__DIR__);
$bootstrapPath = $webDir . '/tests/bootstrap.php';
if (file_exists($bootstrapPath)) {
    require_once $bootstrapPath;
}

// Ensure GA4 property ID is present if not defined in wp_options
if (!getenv('GA4_PROPERTY_ID') && !getenv('HELMETSAN_GA4_PROPERTY_ID')) {
    putenv('GA4_PROPERTY_ID=525320520');
}

$options = getopt('', ['period::', 'force::']);
$period = isset($options['period']) && in_array((int)$options['period'], [7, 30, 90], true)
    ? (int)$options['period']
    : 30;
$force = isset($options['force']);

$periodStr = $period . 'daysAgo';

try {
    $ga = new \Helmetsan\Core\Analytics\GoogleAnalyticsService();
    $gsc = new \Helmetsan\Core\Analytics\GoogleSearchConsoleService();

    $output = [
        'ok' => true,
        'period' => $period,
        'ga' => $ga->getOverviewMetrics($periodStr, $force),
        'realtime' => $ga->getRealtimeActiveUsers($force),
        'devices' => $ga->getDeviceBreakdown($period, $force),
        'anomalies' => $ga->getTrafficAnomaliesSummary($force),
        'trending' => $ga->getTrendingHelmets(5, $periodStr),
        'channels' => $ga->getAcquisitionChannels($periodStr),
        'ai_referrals' => $ga->getAiReferrals($periodStr),
        'gsc' => $gsc->getOverviewMetrics($period, $force),
        'queries' => $gsc->getTopQueries(5, $period, $force),
        'striking' => $gsc->getStrikingDistanceQueries(5, $period, $force),
        'pages' => $gsc->getTopPages(5, $period, $force),
        'sitemaps' => $gsc->getSitemapsList($force),
        'appearance' => $gsc->getSearchAppearance($period, $force),
    ];

    echo json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
} catch (\Throwable $e) {
    echo json_encode([
        'ok' => false,
        'error' => $e->getMessage(),
    ]);
    exit(1);
}
