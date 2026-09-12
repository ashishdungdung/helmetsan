<?php

declare(strict_types=1);

namespace Helmetsan\Core\Analytics;

use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\DateRange;
use Google\Analytics\Data\V1beta\Dimension;
use Google\Analytics\Data\V1beta\Metric;
use Google\Analytics\Data\V1beta\RunReportRequest;
use Google\Analytics\Data\V1beta\RunRealtimeReportRequest;
use Google\Analytics\Data\V1beta\Filter;
use Google\Analytics\Data\V1beta\FilterExpression;
use Google\Analytics\Data\V1beta\FilterExpressionList;
use Google\Analytics\Admin\V1beta\Client\AnalyticsAdminServiceClient;
use Google\Analytics\Admin\V1beta\CustomDimension;
use Google\Analytics\Admin\V1beta\CustomDimension\DimensionScope;
use Google\Analytics\Admin\V1beta\CreateCustomDimensionRequest;
use Google\Analytics\Admin\V1beta\ListCustomDimensionsRequest;
use Helmetsan\Core\Support\Config;

final class GoogleAnalyticsService
{
    private Config $config;

    public function __construct(?Config $config = null)
    {
        $this->config = $config ?? new Config();
    }

    /**
     * Retrieve the active GA4 Property ID from constants, environment, or wp_options.
     */
    public function getPropertyId(): string
    {
        if (defined('HELMETSAN_GA4_PROPERTY_ID') && (string) HELMETSAN_GA4_PROPERTY_ID !== '') {
            return trim((string) HELMETSAN_GA4_PROPERTY_ID);
        }
        $env = getenv('GA4_PROPERTY_ID');
        if (is_string($env) && trim($env) !== '') {
            return trim($env);
        }
        $settings = function_exists('get_option') ? get_option(Config::OPTION_ANALYTICS, []) : [];
        return isset($settings['ga4_property_id']) ? trim((string) $settings['ga4_property_id']) : '';
    }

    /**
     * Parse and retrieve Google Service Account credentials.
     */
    private function getCredentials(): ?array
    {
        $candidatePaths = [];

        if (defined('HELMETSAN_GA_KEY_PATH') && file_exists(HELMETSAN_GA_KEY_PATH)) {
            $candidatePaths[] = HELMETSAN_GA_KEY_PATH;
        }

        $coreDir = defined('HELMETSAN_CORE_DIR') ? HELMETSAN_CORE_DIR : '';
        if ($coreDir !== '') {
            $candidatePaths[] = $coreDir . 'keys/google-service-account.json';
            $candidatePaths[] = $coreDir . 'keys/google-search-console.json';
        }

        $rootDir = dirname(__DIR__, 2);
        $candidatePaths[] = $rootDir . '/secrets/ash-site-502901-cd0bf333dc7c.json';

        foreach ($candidatePaths as $path) {
            if (file_exists($path)) {
                $content = (string) file_get_contents($path);
                $decoded = json_decode($content, true);
                if (is_array($decoded) && isset($decoded['client_email'], $decoded['private_key'])) {
                    return $decoded;
                }
            }
        }

        $settings = function_exists('get_option') ? get_option(Config::OPTION_ANALYTICS, []) : [];
        $keyJson = isset($settings['google_service_account_key']) ? trim((string) $settings['google_service_account_key']) : '';
        if ($keyJson !== '') {
            $decoded = json_decode($keyJson, true);
            if (is_array($decoded) && isset($decoded['client_email'], $decoded['private_key'])) {
                return $decoded;
            }
        }

        return null;
    }

    /**
     * Safely sanitize transient cache keys across WordPress and CLI/standalone runtimes.
     */
    private function sanitizeKey(string $key): string
    {
        if (function_exists('sanitize_key')) {
            return sanitize_key($key);
        }
        return (string) preg_replace('/[^a-z0-9_-]/', '', strtolower($key));
    }

    /**
     * Run anomaly detection on daily country sessions and return flagged anomalies.
     *
     * @return array{ok: bool, anomalies: array<int, array{country: string, target_date: string, today: int, average: float, factor: float}>, message?: string}
     */
    public function detectTrafficAnomalies(): array
    {
        $settings = function_exists('get_option') ? get_option(Config::OPTION_ANALYTICS, []) : [];
        $propertyId = $this->getPropertyId();

        $keyJson = '';
        $defaultKeyPath = defined('HELMETSAN_CORE_DIR') ? HELMETSAN_CORE_DIR . 'keys/google-service-account.json' : '';

        if (defined('HELMETSAN_GA_KEY_PATH') && file_exists(HELMETSAN_GA_KEY_PATH)) {
            $keyJson = (string) file_get_contents(HELMETSAN_GA_KEY_PATH);
        } elseif ($defaultKeyPath !== '' && file_exists($defaultKeyPath)) {
            $keyJson = (string) file_get_contents($defaultKeyPath);
        } else {
            $keyJson = isset($settings['google_service_account_key']) ? trim((string) $settings['google_service_account_key']) : '';
        }

        if ($propertyId === '' || $keyJson === '') {
            return [
                'ok' => false,
                'anomalies' => [],
                'message' => 'Google Analytics Property ID or Service Account Key is missing.',
            ];
        }

        $credentials = json_decode($keyJson, true);
        if (!is_array($credentials)) {
            return [
                'ok' => false,
                'anomalies' => [],
                'message' => 'Invalid Service Account JSON key format.',
            ];
        }

        try {
            $client = new BetaAnalyticsDataClient([
                'credentials' => $credentials,
            ]);

            // Query GA4 Data for the last 8 days (from 8daysAgo to yesterday)
            $request = new RunReportRequest([
                'property' => 'properties/' . $propertyId,
                'date_ranges' => [
                    new DateRange([
                        'start_date' => '8daysAgo',
                        'end_date' => 'yesterday',
                    ]),
                ],
                'dimensions' => [
                    new Dimension(['name' => 'date']),
                    new Dimension(['name' => 'country']),
                ],
                'metrics' => [
                    new Metric(['name' => 'sessions']),
                ],
            ]);

            $response = $client->runReport($request);

            $data = [];
            $allDates = [];

            foreach ($response->getRows() as $row) {
                $date = $row->getDimensionValues()[0]->getValue(); // YYYYMMDD
                $country = $row->getDimensionValues()[1]->getValue();
                $sessions = (int) $row->getMetricValues()[0]->getValue();

                $data[$country][$date] = $sessions;
                $allDates[] = $date;
            }

            $allDates = array_values(array_unique($allDates));
            sort($allDates);

            if (count($allDates) < 3) {
                return [
                    'ok' => true,
                    'anomalies' => [],
                    'message' => 'Not enough historical data returned from GA4 to establish a baseline.',
                ];
            }

            // The latest date is our audit target (usually yesterday in GA4 property timezone)
            $auditDate = end($allDates);
            // The rest are historical baseline dates
            $baselineDates = array_slice($allDates, 0, -1);

            $anomalies = [];
            $threshold = (float) ($settings['analytics_anomaly_threshold'] ?? 3.0);
            $minSessions = (int) ($settings['analytics_anomaly_min_sessions'] ?? 500);

            foreach ($data as $country => $dates) {
                $auditSessions = $dates[$auditDate] ?? 0;

                // Calculate average sessions during baseline dates
                $baselineVals = [];
                foreach ($baselineDates as $d) {
                    if (isset($dates[$d])) {
                        $baselineVals[] = $dates[$d];
                    }
                }

                // If this country had no traffic during the baseline, assume 0
                if (count($baselineVals) === 0) {
                    continue;
                }

                $avg = array_sum($baselineVals) / count($baselineVals);

                if ($avg <= 0) {
                    $avg = 1.0;
                }

                $factor = $auditSessions / $avg;

                if ($auditSessions >= $minSessions && $factor >= $threshold) {
                    $anomalies[] = [
                        'country'     => $country,
                        'target_date' => $auditDate,
                        'today'       => $auditSessions,
                        'average'     => round($avg, 2),
                        'factor'      => round($factor, 2),
                    ];
                }
            }

            return [
                'ok' => true,
                'anomalies' => $anomalies,
            ];
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'anomalies' => [],
                'message' => 'GA4 API Query failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Retrieve high-level GA4 traffic overview for the period.
     *
     * @return array{ok: bool, active_users: int, sessions: int, page_views: int, avg_session_duration: float, bounce_rate: float, timestamp: string, message?: string}
     */
    public function getOverviewMetrics(string|int $period = '30daysAgo', bool $force = false): array
    {
        $periodStr = is_numeric($period) ? "{$period}daysAgo" : (string) $period;
        $transientKey = 'helmetsan_ga4_overview_' . $this->sanitizeKey($periodStr);
        if (!$force && function_exists('get_transient')) {
            $cached = get_transient($transientKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $credentials = $this->getCredentials();
        $propertyId = $this->getPropertyId();

        if (!$credentials || $propertyId === '') {
            return [
                'ok'                   => false,
                'active_users'         => 0,
                'sessions'             => 0,
                'page_views'           => 0,
                'avg_session_duration' => 0.0,
                'bounce_rate'          => 0.0,
                'timestamp'            => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s'),
                'message'              => 'GA4 Property ID or Credentials missing.',
            ];
        }

        if (!class_exists(BetaAnalyticsDataClient::class)) {
            return [
                'ok'      => false,
                'message' => 'BetaAnalyticsDataClient class not found.',
            ];
        }

        try {
            $client = new BetaAnalyticsDataClient(['credentials' => $credentials]);
            $request = new RunReportRequest([
                'property'    => 'properties/' . $propertyId,
                'date_ranges' => [
                    new DateRange(['start_date' => $periodStr, 'end_date' => 'today']),
                ],
                'metrics'     => [
                    new Metric(['name' => 'activeUsers']),
                    new Metric(['name' => 'sessions']),
                    new Metric(['name' => 'screenPageViews']),
                    new Metric(['name' => 'averageSessionDuration']),
                    new Metric(['name' => 'bounceRate']),
                ],
            ]);

            $response = $client->runReport($request);
            $rows = $response->getRows();
            $first = (count($rows) > 0) ? $rows[0] : null;

            $activeUsers = $first ? (int) $first->getMetricValues()[0]->getValue() : 0;
            $sessions = $first ? (int) $first->getMetricValues()[1]->getValue() : 0;
            $pageViews = $first ? (int) $first->getMetricValues()[2]->getValue() : 0;
            $avgDuration = $first ? round((float) $first->getMetricValues()[3]->getValue(), 1) : 0.0;
            $bounceRate = $first ? round(((float) $first->getMetricValues()[4]->getValue()) * 100, 1) : 0.0;

            $result = [
                'ok'                   => true,
                'active_users'         => $activeUsers,
                'sessions'             => $sessions,
                'page_views'           => $pageViews,
                'avg_session_duration' => $avgDuration,
                'bounce_rate'          => $bounceRate,
                'timestamp'            => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s'),
            ];

            if (function_exists('set_transient')) {
                set_transient($transientKey, $result, 3600);
            }

            return $result;
        } catch (\Exception $e) {
            return [
                'ok'                   => false,
                'active_users'         => 0,
                'sessions'             => 0,
                'page_views'           => 0,
                'avg_session_duration' => 0.0,
                'bounce_rate'          => 0.0,
                'timestamp'            => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s'),
                'message'              => 'GA4 Overview query failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Retrieve realtime active users from GA4 (last 30 minutes).
     *
     * @return array{ok: bool, active_users: int, countries: array<int, array{country: string, active_users: int}>, timestamp: string, message?: string}
     */
    public function getRealtimeActiveUsers(bool $force = false): array
    {
        $transientKey = 'helmetsan_ga4_realtime';
        if (!$force && function_exists('get_transient')) {
            $cached = get_transient($transientKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $credentials = $this->getCredentials();
        $propertyId = $this->getPropertyId();

        if (!$credentials || $propertyId === '') {
            return [
                'ok'           => false,
                'active_users' => 0,
                'countries'    => [],
                'timestamp'    => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s'),
                'message'      => 'GA4 Property ID or Credentials missing.',
            ];
        }

        if (!class_exists(BetaAnalyticsDataClient::class)) {
            return [
                'ok'           => false,
                'active_users' => 0,
                'countries'    => [],
                'timestamp'    => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s'),
                'message'      => 'BetaAnalyticsDataClient class not found.',
            ];
        }

        try {
            $client = new BetaAnalyticsDataClient(['credentials' => $credentials]);
            $request = new RunRealtimeReportRequest([
                'property'   => 'properties/' . $propertyId,
                'dimensions' => [
                    new Dimension(['name' => 'country']),
                ],
                'metrics'    => [
                    new Metric(['name' => 'activeUsers']),
                ],
            ]);

            $response = $client->runRealtimeReport($request);
            $totalActive = 0;
            $countries = [];

            foreach ($response->getRows() as $row) {
                $country = (string) $row->getDimensionValues()[0]->getValue();
                $users = (int) $row->getMetricValues()[0]->getValue();
                $totalActive += $users;
                $countries[] = [
                    'country'      => $country,
                    'active_users' => $users,
                ];
            }

            $result = [
                'ok'           => true,
                'active_users' => $totalActive,
                'countries'    => array_slice($countries, 0, 5),
                'timestamp'    => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s'),
            ];

            if (function_exists('set_transient')) {
                set_transient($transientKey, $result, 60);
            }

            return $result;
        } catch (\Exception $e) {
            return [
                'ok'           => false,
                'active_users' => 0,
                'countries'    => [],
                'timestamp'    => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s'),
                'message'      => 'GA4 Realtime query failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Retrieve device category breakdown (mobile vs desktop vs tablet) from GA4.
     *
     * @return array{ok: bool, devices: array<string, array{sessions: int, active_users: int, share: float}>, total_sessions: int, timestamp: string, message?: string}
     */
    public function getDeviceBreakdown(string|int $period = '30daysAgo', bool $force = false): array
    {
        $periodStr = is_numeric($period) ? "{$period}daysAgo" : (string) $period;
        $transientKey = 'helmetsan_ga4_devices_' . $this->sanitizeKey($periodStr);
        if (!$force && function_exists('get_transient')) {
            $cached = get_transient($transientKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $credentials = $this->getCredentials();
        $propertyId = $this->getPropertyId();

        if (!$credentials || $propertyId === '') {
            return [
                'ok'             => false,
                'devices'        => [],
                'total_sessions' => 0,
                'timestamp'      => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s'),
                'message'        => 'GA4 Property ID or Credentials missing.',
            ];
        }

        if (!class_exists(BetaAnalyticsDataClient::class)) {
            return [
                'ok'             => false,
                'devices'        => [],
                'total_sessions' => 0,
                'timestamp'      => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s'),
                'message'        => 'BetaAnalyticsDataClient class not found.',
            ];
        }

        try {
            $client = new BetaAnalyticsDataClient(['credentials' => $credentials]);
            $request = new RunReportRequest([
                'property'    => 'properties/' . $propertyId,
                'date_ranges' => [
                    new DateRange(['start_date' => $periodStr, 'end_date' => 'today']),
                ],
                'dimensions'  => [
                    new Dimension(['name' => 'deviceCategory']),
                ],
                'metrics'     => [
                    new Metric(['name' => 'sessions']),
                    new Metric(['name' => 'activeUsers']),
                ],
            ]);

            $response = $client->runReport($request);
            $devices = [];
            $totalSessions = 0;

            foreach ($response->getRows() as $row) {
                $category = strtolower(trim((string) $row->getDimensionValues()[0]->getValue()));
                $sessions = (int) $row->getMetricValues()[0]->getValue();
                $activeUsers = (int) $row->getMetricValues()[1]->getValue();

                $devices[$category] = [
                    'sessions'     => $sessions,
                    'active_users' => $activeUsers,
                    'share'        => 0.0,
                ];
                $totalSessions += $sessions;
            }

            foreach ($devices as $cat => $data) {
                $devices[$cat]['share'] = $totalSessions > 0 ? round(($data['sessions'] / $totalSessions) * 100, 1) : 0.0;
            }

            $result = [
                'ok'             => true,
                'devices'        => $devices,
                'total_sessions' => $totalSessions,
                'timestamp'      => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s'),
            ];

            if (function_exists('set_transient')) {
                set_transient($transientKey, $result, 3600);
            }

            return $result;
        } catch (\Exception $e) {
            return [
                'ok'             => false,
                'devices'        => [],
                'total_sessions' => 0,
                'timestamp'      => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s'),
                'message'        => 'GA4 Device Breakdown query failed: ' . $e->getMessage(),
            ];
        }
    }

    /**
     * Retrieve cached traffic anomaly summary for UI presentation.
     *
     * @return array{ok: bool, count: int, anomalies: array<int, array{country: string, target_date: string, today: int, average: float, factor: float}>, message?: string}
     */
    public function getTrafficAnomaliesSummary(bool $force = false): array
    {
        $transientKey = 'helmetsan_ga4_anomalies_summary';
        if (!$force && function_exists('get_transient')) {
            $cached = get_transient($transientKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $res = $this->detectTrafficAnomalies();
        $anomalies = $res['anomalies'] ?? [];
        $result = [
            'ok'        => $res['ok'] ?? false,
            'count'     => count($anomalies),
            'anomalies' => $anomalies,
            'message'   => $res['message'] ?? '',
        ];

        if (function_exists('set_transient')) {
            set_transient($transientKey, $result, 3600);
        }

        return $result;
    }

    /**
     * Retrieve top geographic visitor origins from GA4.
     *
     * @return array<int, array{country: string, sessions: int, active_users: int}>
     */
    public function getTopCountries(int $limit = 5, string $period = '30daysAgo', bool $force = false): array
    {
        $transientKey = 'helmetsan_ga4_countries_' . $this->sanitizeKey($period);
        if (!$force && function_exists('get_transient')) {
            $cached = get_transient($transientKey);
            if (is_array($cached)) {
                return array_slice($cached, 0, $limit);
            }
        }

        $credentials = $this->getCredentials();
        $propertyId = $this->getPropertyId();

        if (!$credentials || $propertyId === '') {
            return [];
        }

        if (!class_exists(BetaAnalyticsDataClient::class)) {
            return [];
        }

        try {
            $client = new BetaAnalyticsDataClient(['credentials' => $credentials]);
            $request = new RunReportRequest([
                'property'    => 'properties/' . $propertyId,
                'date_ranges' => [
                    new DateRange(['start_date' => $period, 'end_date' => 'today']),
                ],
                'dimensions'  => [
                    new Dimension(['name' => 'country']),
                ],
                'metrics'     => [
                    new Metric(['name' => 'sessions']),
                    new Metric(['name' => 'activeUsers']),
                ],
            ]);

            $response = $client->runReport($request);
            $countries = [];

            foreach ($response->getRows() as $row) {
                $country = (string) $row->getDimensionValues()[0]->getValue();
                $sessions = (int) $row->getMetricValues()[0]->getValue();
                $activeUsers = (int) $row->getMetricValues()[1]->getValue();

                $countries[] = [
                    'country'      => $country,
                    'sessions'     => $sessions,
                    'active_users' => $activeUsers,
                ];
            }

            usort($countries, fn($a, $b) => $b['sessions'] <=> $a['sessions']);

            if (function_exists('set_transient')) {
                set_transient($transientKey, $countries, 3600);
            }

            return array_slice($countries, 0, $limit);
        } catch (\Exception $e) {
            error_log('GA4 getTopCountries failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Retrieve the most viewed helmets.
     */
    public function getTrendingHelmets(int $limit = 10, string $period = '30daysAgo'): array
    {
        $credentials = $this->getCredentials();
        $propertyId = $this->getPropertyId();

        if (!$credentials || $propertyId === '') {
            return [];
        }

        try {
            $client = new BetaAnalyticsDataClient(['credentials' => $credentials]);
            $request = new RunReportRequest([
                'property' => 'properties/' . $propertyId,
                'date_ranges' => [
                    new DateRange(['start_date' => $period, 'end_date' => 'today']),
                ],
                'dimensions' => [
                    new Dimension(['name' => 'pagePath']),
                ],
                'metrics' => [
                    new Metric(['name' => 'screenPageViews']),
                ],
                'dimension_filter' => new FilterExpression([
                    'filter' => new Filter([
                        'field_name' => 'pagePath',
                        'string_filter' => new Filter\StringFilter([
                            'match_type' => Filter\StringFilter\MatchType::BEGINS_WITH,
                            'value' => '/helmets/',
                        ]),
                    ]),
                ]),
            ]);

            $response = $client->runReport($request);
            $trending = [];

            foreach ($response->getRows() as $row) {
                $path = $row->getDimensionValues()[0]->getValue();
                $views = (int) $row->getMetricValues()[0]->getValue();

                if (preg_match('/^\/helmets\/([a-zA-Z0-9-_]+)\/?$/', $path, $matches)) {
                    $slug = $matches[1];
                    $post = function_exists('get_page_by_path') ? get_page_by_path($slug, OBJECT, 'helmet') : null;
                    if ($post instanceof \WP_Post) {
                        $trending[] = [
                            'post_id' => $post->ID,
                            'title'   => $post->post_title,
                            'slug'    => $slug,
                            'views'   => $views,
                        ];
                    }
                }
                if (count($trending) >= $limit) {
                    break;
                }
            }

            return $trending;
        } catch (\Exception $e) {
            error_log('GA4 getTrendingHelmets failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Synchronize GA4 page views for all helmet CPTs into WordPress postmeta cache.
     */
    public function syncHelmetPageViews(): array
    {
        $trending = $this->getTrendingHelmets(2000, '30daysAgo');
        if (empty($trending)) {
            return ['ok' => false, 'message' => 'No page views retrieved from GA4.'];
        }

        $count = 0;
        foreach ($trending as $item) {
            update_post_meta($item['post_id'], '_hs_ga_views_30d', $item['views']);
            $count++;
        }

        return ['ok' => true, 'count' => $count];
    }

    /**
     * Identify high-traffic, low-conversion product pages.
     *
     * @return array<int, array{post_id: int, title: string, slug: string, views: int, clicks: int, conversion_rate: float}>
     */
    public function getLowConversionAudits(int $minViews = 5, string $period = '30daysAgo', int $limit = 10, bool $force = false): array
    {
        $cacheKey = 'hs_ga4_low_conv_' . md5($period . '_' . $minViews . '_' . $limit);
        if (!$force && function_exists('get_transient')) {
            $cached = get_transient($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $credentials = $this->getCredentials();
        $propertyId = $this->getPropertyId();

        if (!$credentials || $propertyId === '') {
            return [];
        }

        try {
            $client = new BetaAnalyticsDataClient(['credentials' => $credentials]);
            
            // Query 1: Get views
            $viewsRequest = new RunReportRequest([
                'property' => 'properties/' . $propertyId,
                'date_ranges' => [
                    new DateRange(['start_date' => $period, 'end_date' => 'today']),
                ],
                'dimensions' => [
                    new Dimension(['name' => 'pagePath']),
                ],
                'metrics' => [
                    new Metric(['name' => 'screenPageViews']),
                ],
                'dimension_filter' => new FilterExpression([
                    'filter' => new Filter([
                        'field_name' => 'pagePath',
                        'string_filter' => new Filter\StringFilter([
                            'match_type' => Filter\StringFilter\MatchType::BEGINS_WITH,
                            'value' => '/helmets/',
                        ]),
                    ]),
                ]),
            ]);

            $viewsResponse = $client->runReport($viewsRequest);
            $pageViews = [];
            foreach ($viewsResponse->getRows() as $row) {
                $path = $row->getDimensionValues()[0]->getValue();
                $views = (int) $row->getMetricValues()[0]->getValue();
                if ($views >= $minViews && preg_match('/^\/helmets\/([a-zA-Z0-9-_]+)\/?$/', $path)) {
                    $pageViews[$path] = $views;
                }
            }

            if (empty($pageViews)) {
                return [];
            }

            // Query 2: Get outbound clicks
            $leadsRequest = new RunReportRequest([
                'property' => 'properties/' . $propertyId,
                'date_ranges' => [
                    new DateRange(['start_date' => $period, 'end_date' => 'today']),
                ],
                'dimensions' => [
                    new Dimension(['name' => 'pagePath']),
                ],
                'metrics' => [
                    new Metric(['name' => 'eventCount']),
                ],
                'dimension_filter' => new FilterExpression([
                    'and_group' => new FilterExpressionList([
                        'expressions' => [
                            new FilterExpression([
                                'filter' => new Filter([
                                    'field_name' => 'eventName',
                                    'string_filter' => new Filter\StringFilter([
                                        'match_type' => Filter\StringFilter\MatchType::EXACT,
                                        'value' => 'generate_lead',
                                    ]),
                                ]),
                            ]),
                            new FilterExpression([
                                'filter' => new Filter([
                                    'field_name' => 'pagePath',
                                    'string_filter' => new Filter\StringFilter([
                                        'match_type' => Filter\StringFilter\MatchType::BEGINS_WITH,
                                        'value' => '/helmets/',
                                    ]),
                                ]),
                            ]),
                        ],
                    ]),
                ]),
            ]);

            $leadsResponse = $client->runReport($leadsRequest);
            $conversions = [];
            foreach ($leadsResponse->getRows() as $row) {
                $path = $row->getDimensionValues()[0]->getValue();
                $clicks = (int) $row->getMetricValues()[0]->getValue();
                $conversions[$path] = $clicks;
            }

            $audits = [];
            foreach ($pageViews as $path => $views) {
                $clicks = $conversions[$path] ?? 0;
                $rate = $views > 0 ? ($clicks / $views) * 100 : 0;

                if ($rate < 1.5) {
                    if (preg_match('/^\/helmets\/([a-zA-Z0-9-_]+)\/?$/', $path, $matches)) {
                        $slug = $matches[1];
                        $post = function_exists('get_page_by_path') ? get_page_by_path($slug, OBJECT, 'helmet') : null;
                        if ($post instanceof \WP_Post) {
                            $audits[] = [
                                'post_id'         => $post->ID,
                                'title'           => $post->post_title,
                                'slug'            => $slug,
                                'views'           => $views,
                                'clicks'          => $clicks,
                                'conversion_rate' => round($rate, 2),
                            ];
                        }
                    }
                }
            }

            usort($audits, fn($a, $b) => $b['views'] <=> $a['views']);
            $audits = array_slice($audits, 0, $limit);

            if (function_exists('set_transient')) {
                set_transient($cacheKey, $audits, 3600);
            }

            return $audits;
        } catch (\Exception $e) {
            error_log('GA4 getLowConversionAudits failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Retrieve acquisition traffic channels breakdown (Direct, Organic Search, AI Assistants, Social, Referral).
     *
     * @return array<int, array{channel: string, source_medium: string, sessions: int, active_users: int, is_ai: bool}>
     */
    public function getAcquisitionChannels(string $period = '30daysAgo', bool $force = false): array
    {
        $cacheKey = 'hs_ga4_acquisition_' . md5($period);
        if (!$force && function_exists('get_transient')) {
            $cached = get_transient($cacheKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $credentials = $this->getCredentials();
        $propertyId = $this->getPropertyId();

        if (!$credentials || $propertyId === '') {
            return [];
        }

        try {
            $client = new BetaAnalyticsDataClient(['credentials' => $credentials]);
            $request = new RunReportRequest([
                'property' => 'properties/' . $propertyId,
                'date_ranges' => [
                    new DateRange(['start_date' => $period, 'end_date' => 'today']),
                ],
                'dimensions' => [
                    new Dimension(['name' => 'sessionDefaultChannelGroup']),
                    new Dimension(['name' => 'sessionSourceMedium']),
                ],
                'metrics' => [
                    new Metric(['name' => 'sessions']),
                    new Metric(['name' => 'activeUsers']),
                ],
                'limit' => 50,
            ]);

            $response = $client->runReport($request);
            $channels = [];
            $aiKeywords = ['ai-assistant', 'chatgpt', 'perplexity', 'copilot', 'claude', 'gemini', 'poe', 'anthropic', 'openai'];

            foreach ($response->getRows() as $row) {
                $group = $row->getDimensionValues()[0]->getValue();
                $sourceMedium = $row->getDimensionValues()[1]->getValue();
                $sessions = (int) $row->getMetricValues()[0]->getValue();
                $users = (int) $row->getMetricValues()[1]->getValue();

                $isAi = false;
                $lowerSource = strtolower($sourceMedium);
                $lowerGroup = strtolower($group);
                foreach ($aiKeywords as $aiKw) {
                    if (str_contains($lowerSource, $aiKw) || str_contains($lowerGroup, $aiKw)) {
                        $isAi = true;
                        break;
                    }
                }

                $channels[] = [
                    'channel'       => $group,
                    'source_medium' => $sourceMedium,
                    'sessions'      => $sessions,
                    'active_users'  => $users,
                    'is_ai'         => $isAi,
                ];
            }

            usort($channels, fn($a, $b) => $b['sessions'] <=> $a['sessions']);

            if (function_exists('set_transient')) {
                set_transient($cacheKey, $channels, 3600);
            }

            return $channels;
        } catch (\Exception $e) {
            error_log('GA4 getAcquisitionChannels failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Isolate and report referrals originating from generative AI platforms and AI search engines.
     *
     * @return array{total_sessions: int, platforms: array<string, int>, items: array<int, array{source_medium: string, sessions: int, active_users: int}>}
     */
    public function getAiReferrals(string $period = '30daysAgo', bool $force = false): array
    {
        $channels = $this->getAcquisitionChannels($period, $force);
        $aiItems = array_filter($channels, fn($c) => !empty($c['is_ai']));

        $totalAiSessions = 0;
        $platforms = [];

        foreach ($aiItems as $item) {
            $totalAiSessions += $item['sessions'];
            $sm = strtolower($item['source_medium']);
            $platformName = 'AI Assistant';
            if (str_contains($sm, 'chatgpt') || str_contains($sm, 'openai')) {
                $platformName = 'ChatGPT';
            } elseif (str_contains($sm, 'perplexity')) {
                $platformName = 'Perplexity';
            } elseif (str_contains($sm, 'copilot')) {
                $platformName = 'Microsoft Copilot';
            } elseif (str_contains($sm, 'claude') || str_contains($sm, 'anthropic')) {
                $platformName = 'Claude';
            } elseif (str_contains($sm, 'gemini')) {
                $platformName = 'Google Gemini';
            }
            $platforms[$platformName] = ($platforms[$platformName] ?? 0) + $item['sessions'];
        }

        return [
            'total_sessions' => $totalAiSessions,
            'platforms'      => $platforms,
            'items'          => array_values($aiItems),
        ];
    }

    /**
     * Retrieve traffic campaign and acquisition referrals.
     */
    public function getCampaignAttribution(string $period = '30daysAgo'): array
    {
        $credentials = $this->getCredentials();
        $propertyId = $this->getPropertyId();

        if (!$credentials || $propertyId === '') {
            return [];
        }

        try {
            $client = new BetaAnalyticsDataClient(['credentials' => $credentials]);
            $request = new RunReportRequest([
                'property' => 'properties/' . $propertyId,
                'date_ranges' => [
                    new DateRange(['start_date' => $period, 'end_date' => 'today']),
                ],
                'dimensions' => [
                    new Dimension(['name' => 'sessionSourceMedium']),
                    new Dimension(['name' => 'sessionCampaignName']),
                ],
                'metrics' => [
                    new Metric(['name' => 'sessions']),
                    new Metric(['name' => 'activeUsers']),
                ],
                'limit' => 20,
            ]);

            $response = $client->runReport($request);
            $attribution = [];

            foreach ($response->getRows() as $row) {
                $sourceMedium = $row->getDimensionValues()[0]->getValue();
                $campaign = $row->getDimensionValues()[1]->getValue();
                $sessions = (int) $row->getMetricValues()[0]->getValue();

                $attribution[] = [
                    'source_medium' => $sourceMedium,
                    'campaign'      => $campaign,
                    'leads'         => $sessions,
                ];
            }

            usort($attribution, fn($a, $b) => $b['leads'] <=> $a['leads']);

            return $attribution;
        } catch (\Exception $e) {
            error_log('GA4 getCampaignAttribution failed: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Programmatically verify and create required Custom Dimensions.
     */
    public function ensureCustomDimensions(): array
    {
        $credentials = $this->getCredentials();
        $propertyId = $this->getPropertyId();

        if (!$credentials || $propertyId === '') {
            return ['ok' => false, 'message' => 'Analytics credentials or property ID missing.'];
        }

        try {
            $client = new AnalyticsAdminServiceClient([
                'credentials' => $credentials,
            ]);

            $parent = 'properties/' . $propertyId;

            // Fetch existing custom dimensions
            $existing = [];
            $response = $client->listCustomDimensions(
                new ListCustomDimensionsRequest(['parent' => $parent])
            );
            foreach ($response->iterateAllElements() as $dim) {
                $existing[$dim->getParameterName()] = $dim->getName();
            }

            $dimensionsToCreate = [
                'href' => [
                    'display_name' => 'Affiliate Link URL',
                    'description'  => 'Target destination of outbound affiliate clicks',
                ],
                'text' => [
                    'display_name' => 'Affiliate Link Text',
                    'description'  => 'Anchor text of outbound affiliate links',
                ],
            ];

            $createdCount = 0;
            foreach ($dimensionsToCreate as $param => $info) {
                if (!isset($existing[$param])) {
                    $customDimension = new CustomDimension([
                        'parameter_name' => $param,
                        'display_name'   => $info['display_name'],
                        'description'    => $info['description'],
                        'scope'          => DimensionScope::EVENT,
                    ]);

                    $request = new CreateCustomDimensionRequest([
                        'parent'           => $parent,
                        'custom_dimension' => $customDimension,
                    ]);

                    $client->createCustomDimension($request);
                    $createdCount++;
                }
            }

            return [
                'ok' => true,
                'message' => sprintf('Checked custom dimensions. Created %d missing dimensions.', $createdCount),
                'dimensions' => $existing,
            ];
        } catch (\Exception $e) {
            return [
                'ok' => false,
                'message' => 'Failed to synchronize custom dimensions: ' . $e->getMessage(),
            ];
        }
    }
}
