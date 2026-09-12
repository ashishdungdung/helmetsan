<?php

declare(strict_types=1);

namespace Helmetsan\Core\Analytics;

use Google\Auth\Credentials\ServiceAccountCredentials;
use Helmetsan\Core\Support\Config;

/**
 * Google Search Console API Service for Helmetsan.
 * 
 * Provides automated search performance analytics, ranking intelligence,
 * top query and landing page reporting, and sitemap management.
 */
final class GoogleSearchConsoleService
{
    private const DEFAULT_SITE = 'sc-domain:helmetsan.com';
    private const TRANSIENT_OVERVIEW = 'helmetsan_gsc_overview_30d';
    private const TRANSIENT_QUERIES = 'helmetsan_gsc_queries_30d';
    private const TRANSIENT_PAGES = 'helmetsan_gsc_pages_30d';
    private const TRANSIENT_STATUS = 'helmetsan_gsc_status';

    public function __construct(private readonly ?Config $config = null)
    {
    }

    /**
     * Retrieve Service Account credentials for Google Search Console.
     */
    private function getCredentials(): ?array
    {
        $candidatePaths = [];

        if (defined('HELMETSAN_GSC_KEY_PATH') && file_exists(HELMETSAN_GSC_KEY_PATH)) {
            $candidatePaths[] = HELMETSAN_GSC_KEY_PATH;
        }

        $coreDir = defined('HELMETSAN_CORE_DIR') ? HELMETSAN_CORE_DIR : '';
        if ($coreDir !== '') {
            $candidatePaths[] = $coreDir . 'keys/google-search-console.json';
            $candidatePaths[] = $coreDir . 'keys/google-service-account.json';
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

        // Check wp_options fallback
        if (function_exists('get_option')) {
            $settings = get_option(Config::OPTION_ANALYTICS, []);
            if (!empty($settings['gsc_service_account_key'])) {
                $decoded = json_decode((string) $settings['gsc_service_account_key'], true);
                if (is_array($decoded)) {
                    return $decoded;
                }
            }
        }

        return null;
    }

    /**
     * Obtain a valid OAuth2 access token for Search Console.
     */
    private function getAccessToken(): ?string
    {
        $credsArray = $this->getCredentials();
        if (!$credsArray) {
            return null;
        }

        if (!class_exists(ServiceAccountCredentials::class)) {
            return null;
        }

        try {
            $creds = new ServiceAccountCredentials(
                [
                    'https://www.googleapis.com/auth/webmasters.readonly',
                    'https://www.googleapis.com/auth/webmasters',
                ],
                $credsArray
            );
            $token = $creds->fetchAuthToken();
            return isset($token['access_token']) ? (string) $token['access_token'] : null;
        } catch (\Exception $e) {
            error_log('Helmetsan GSC Auth Error: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * Check if Search Console service account is configured and operational.
     */
    public function getSiteStatus(bool $force = false): array
    {
        if (!$force && function_exists('get_transient')) {
            $cached = get_transient(self::TRANSIENT_STATUS);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $token = $this->getAccessToken();
        if (!$token) {
            return [
                'ok'         => false,
                'connected'  => false,
                'message'    => 'Search Console Service Account key not found or invalid.',
                'sites'      => [],
                'permission' => 'none',
            ];
        }

        $url = 'https://www.googleapis.com/webmasters/v3/sites';
        $response = $this->makeRequest('GET', $url, $token);

        if (!$response['ok']) {
            return [
                'ok'         => false,
                'connected'  => false,
                'message'    => $response['error'] ?? 'Failed to query GSC sites.',
                'sites'      => [],
                'permission' => 'none',
            ];
        }

        $entries = $response['data']['siteEntry'] ?? [];
        $siteUrls = [];
        $permission = 'none';

        foreach ($entries as $site) {
            $siteUrl = (string) ($site['siteUrl'] ?? '');
            $siteUrls[] = $siteUrl;
            if ($siteUrl === self::DEFAULT_SITE || $siteUrl === 'https://helmetsan.com/') {
                $permission = (string) ($site['permissionLevel'] ?? 'siteFullUser');
            }
        }

        $result = [
            'ok'         => true,
            'connected'  => !empty($siteUrls),
            'sites'      => $siteUrls,
            'permission' => $permission,
            'client'     => $this->getCredentials()['client_email'] ?? 'service-account',
            'timestamp'  => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s'),
        ];

        if (function_exists('set_transient')) {
            set_transient(self::TRANSIENT_STATUS, $result, 3600);
        }

        return $result;
    }

    /**
     * Retrieve aggregated performance overview from Search Console.
     */
    public function getOverviewMetrics(int $days = 30, bool $force = false): array
    {
        $transientKey = "helmetsan_gsc_overview_{$days}d";
        if (!$force && function_exists('get_transient')) {
            $cached = get_transient($transientKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $token = $this->getAccessToken();
        if (!$token) {
            return [
                'ok'          => false,
                'clicks'      => 0,
                'impressions' => 0,
                'ctr'         => 0.0,
                'position'    => 0.0,
                'message'     => 'Search Console not authenticated.',
            ];
        }

        $site = urlencode(self::DEFAULT_SITE);
        $url = "https://www.googleapis.com/webmasters/v3/sites/{$site}/searchAnalytics/query";

        $body = [
            'startDate' => date('Y-m-d', strtotime("-{$days} days")),
            'endDate'   => date('Y-m-d', strtotime('-2 days')),
        ];

        $response = $this->makeRequest('POST', $url, $token, $body);

        if (!$response['ok']) {
            return [
                'ok'          => false,
                'clicks'      => 0,
                'impressions' => 0,
                'ctr'         => 0.0,
                'position'    => 0.0,
                'message'     => $response['error'] ?? 'API error',
            ];
        }

        $rows = $response['data']['rows'] ?? [];
        $first = $rows[0] ?? [];

        $result = [
            'ok'          => true,
            'clicks'      => (int) ($first['clicks'] ?? 0),
            'impressions' => (int) ($first['impressions'] ?? 0),
            'ctr'         => round(((float) ($first['ctr'] ?? 0.0)) * 100, 2),
            'position'    => round((float) ($first['position'] ?? 0.0), 1),
            'days'        => $days,
            'timestamp'   => function_exists('current_time') ? current_time('mysql') : date('Y-m-d H:i:s'),
        ];

        if (function_exists('set_transient')) {
            set_transient($transientKey, $result, 3600);
        }

        return $result;
    }

    /**
     * Retrieve top ranking search queries with impressions, clicks, and average position.
     *
     * @return array<int, array{query: string, clicks: int, impressions: int, ctr: float, position: float}>
     */
    public function getTopQueries(int $limit = 10, int $days = 30, bool $force = false): array
    {
        $transientKey = "helmetsan_gsc_queries_{$days}d";
        if (!$force && function_exists('get_transient')) {
            $cached = get_transient($transientKey);
            if (is_array($cached)) {
                return array_slice($cached, 0, $limit);
            }
        }

        $token = $this->getAccessToken();
        if (!$token) {
            return [];
        }

        $site = urlencode(self::DEFAULT_SITE);
        $url = "https://www.googleapis.com/webmasters/v3/sites/{$site}/searchAnalytics/query";

        $body = [
            'startDate'  => date('Y-m-d', strtotime("-{$days} days")),
            'endDate'    => date('Y-m-d', strtotime('-2 days')),
            'dimensions' => ['query'],
            'rowLimit'   => max(10, $limit),
        ];

        $response = $this->makeRequest('POST', $url, $token, $body);
        if (!$response['ok']) {
            return [];
        }

        $rows = $response['data']['rows'] ?? [];
        $queries = [];

        foreach ($rows as $r) {
            $queries[] = [
                'query'       => (string) ($r['keys'][0] ?? 'unknown'),
                'clicks'      => (int) ($r['clicks'] ?? 0),
                'impressions' => (int) ($r['impressions'] ?? 0),
                'ctr'         => round(((float) ($r['ctr'] ?? 0.0)) * 100, 2),
                'position'    => round((float) ($r['position'] ?? 0.0), 1),
            ];
        }

        if (function_exists('set_transient')) {
            set_transient($transientKey, $queries, 3600);
        }

        return array_slice($queries, 0, $limit);
    }

    /**
     * Retrieve high-leverage "striking distance" queries (ranking positions 4 - 25 with impressions).
     *
     * @return array<int, array{query: string, clicks: int, impressions: int, ctr: float, position: float, opportunity_score: float}>
     */
    public function getStrikingDistanceQueries(int $limit = 10, int $days = 30, bool $force = false): array
    {
        $allQueries = $this->getTopQueries(100, $days, $force);
        $striking = [];

        foreach ($allQueries as $q) {
            $pos = (float) $q['position'];
            $imp = (int) $q['impressions'];
            if ($pos >= 4.0 && $pos <= 25.0 && $imp > 0) {
                $oppScore = round($imp * (26.0 - $pos), 1);
                $striking[] = array_merge($q, ['opportunity_score' => $oppScore]);
            }
        }

        usort($striking, fn($a, $b) => $b['opportunity_score'] <=> $a['opportunity_score']);

        return array_slice($striking, 0, $limit);
    }

    /**
     * Retrieve top organic search landing pages with impressions, clicks, and average position.
     *
     * @return array<int, array{page: string, title?: string, clicks: int, impressions: int, ctr: float, position: float}>
     */
    public function getTopPages(int $limit = 10, int $days = 30, bool $force = false): array
    {
        $transientKey = "helmetsan_gsc_pages_{$days}d";
        if (!$force && function_exists('get_transient')) {
            $cached = get_transient($transientKey);
            if (is_array($cached)) {
                return array_slice($cached, 0, $limit);
            }
        }

        $token = $this->getAccessToken();
        if (!$token) {
            return [];
        }

        $site = urlencode(self::DEFAULT_SITE);
        $url = "https://www.googleapis.com/webmasters/v3/sites/{$site}/searchAnalytics/query";

        $body = [
            'startDate'  => date('Y-m-d', strtotime("-{$days} days")),
            'endDate'    => date('Y-m-d', strtotime('-2 days')),
            'dimensions' => ['page'],
            'rowLimit'   => max(10, $limit),
        ];

        $response = $this->makeRequest('POST', $url, $token, $body);
        if (!$response['ok']) {
            return [];
        }

        $rows = $response['data']['rows'] ?? [];
        $pages = [];

        foreach ($rows as $r) {
            $rawUrl = (string) ($r['keys'][0] ?? '');
            $path = parse_url($rawUrl, PHP_URL_PATH) ?: '/';

            $pages[] = [
                'url'         => $rawUrl,
                'path'        => $path,
                'clicks'      => (int) ($r['clicks'] ?? 0),
                'impressions' => (int) ($r['impressions'] ?? 0),
                'ctr'         => round(((float) ($r['ctr'] ?? 0.0)) * 100, 2),
                'position'    => round((float) ($r['position'] ?? 0.0), 1),
            ];
        }

        if (function_exists('set_transient')) {
            set_transient($transientKey, $pages, 3600);
        }

        return array_slice($pages, 0, $limit);
    }

    /**
     * Retrieve list of submitted sitemaps and indexation status from Search Console.
     *
     * @return array<int, array{path: string, last_submitted: string, is_pending: bool, is_sitemaps_index: bool, last_downloaded: string, warnings: int, errors: int}>
     */
    public function getSitemapsList(bool $force = false): array
    {
        $transientKey = 'helmetsan_gsc_sitemaps';
        if (!$force && function_exists('get_transient')) {
            $cached = get_transient($transientKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $token = $this->getAccessToken();
        if (!$token) {
            return [];
        }

        $site = urlencode(self::DEFAULT_SITE);
        $url = "https://www.googleapis.com/webmasters/v3/sites/{$site}/sitemaps";

        $response = $this->makeRequest('GET', $url, $token);
        if (!$response['ok']) {
            return [];
        }

        $entries = $response['data']['sitemap'] ?? [];
        $sitemaps = [];

        foreach ($entries as $item) {
            $sitemaps[] = [
                'path'              => (string) ($item['path'] ?? ''),
                'last_submitted'    => (string) ($item['lastSubmitted'] ?? ''),
                'is_pending'        => (bool) ($item['isPending'] ?? false),
                'is_sitemaps_index' => (bool) ($item['isSitemapsIndex'] ?? false),
                'last_downloaded'   => (string) ($item['lastDownloaded'] ?? ''),
                'warnings'          => (int) ($item['warnings'] ?? 0),
                'errors'            => (int) ($item['errors'] ?? 0),
            ];
        }

        if (function_exists('set_transient')) {
            set_transient($transientKey, $sitemaps, 3600);
        }

        return $sitemaps;
    }

    /**
     * Retrieve search appearance breakdown (rich snippets, merchant listings) from Search Console.
     *
     * @return array<int, array{appearance: string, clicks: int, impressions: int, ctr: float, position: float}>
     */
    public function getSearchAppearance(int $days = 30, bool $force = false): array
    {
        $transientKey = "helmetsan_gsc_appearance_{$days}d";
        if (!$force && function_exists('get_transient')) {
            $cached = get_transient($transientKey);
            if (is_array($cached)) {
                return $cached;
            }
        }

        $token = $this->getAccessToken();
        if (!$token) {
            return [];
        }

        $site = urlencode(self::DEFAULT_SITE);
        $url = "https://www.googleapis.com/webmasters/v3/sites/{$site}/searchAnalytics/query";

        $body = [
            'startDate'  => date('Y-m-d', strtotime("-{$days} days")),
            'endDate'    => date('Y-m-d', strtotime('-2 days')),
            'dimensions' => ['searchAppearance'],
            'rowLimit'   => 20,
        ];

        $response = $this->makeRequest('POST', $url, $token, $body);
        if (!$response['ok']) {
            return [];
        }

        $rows = $response['data']['rows'] ?? [];
        $appearances = [];

        foreach ($rows as $r) {
            $rawName = (string) ($r['keys'][0] ?? 'rich_result');
            $appearances[] = [
                'appearance'  => $rawName,
                'clicks'      => (int) ($r['clicks'] ?? 0),
                'impressions' => (int) ($r['impressions'] ?? 0),
                'ctr'         => round(((float) ($r['ctr'] ?? 0.0)) * 100, 2),
                'position'    => round((float) ($r['position'] ?? 0.0), 1),
            ];
        }

        if (function_exists('set_transient')) {
            set_transient($transientKey, $appearances, 3600);
        }

        return $appearances;
    }

    /**
     * Submit sitemap to Google Search Console.
     */
    public function submitSitemap(string $sitemapUrl): array
    {
        $token = $this->getAccessToken();
        if (!$token) {
            return ['ok' => false, 'message' => 'Not authenticated to GSC.'];
        }

        $site = urlencode(self::DEFAULT_SITE);
        $feedpath = urlencode($sitemapUrl);
        $url = "https://www.googleapis.com/webmasters/v3/sites/{$site}/sitemaps/{$feedpath}";

        $response = $this->makeRequest('PUT', $url, $token);
        if ($response['ok']) {
            return ['ok' => true, 'message' => "Sitemap successfully submitted: {$sitemapUrl}"];
        }

        return ['ok' => false, 'message' => $response['error'] ?? 'Submission failed'];
    }

    /**
     * Dispatch HTTP request to Google API.
     */
    private function makeRequest(string $method, string $url, string $token, array $body = []): array
    {
        $headers = [
            'Authorization' => 'Bearer ' . $token,
            'Content-Type'  => 'application/json',
            'Accept'        => 'application/json',
        ];

        if ($method !== 'GET') {
            if (!empty($body)) {
                $encoded = function_exists('wp_json_encode') ? (string) wp_json_encode($body) : (string) json_encode($body);
                $headers['Content-Length'] = (string) strlen($encoded);
            } else {
                $headers['Content-Length'] = '0';
                $encoded = '';
            }
        }

        if (function_exists('wp_remote_request')) {
            $args = [
                'method'    => $method,
                'headers'   => $headers,
                'timeout'   => 10,
                'sslverify' => true,
            ];

            if ($method !== 'GET') {
                $args['body'] = $encoded;
            }

            $resp = wp_remote_request($url, $args);
            if (is_wp_error($resp)) {
                return ['ok' => false, 'error' => $resp->get_error_message()];
            }

            $code = wp_remote_retrieve_response_code($resp);
            $rawBody = wp_remote_retrieve_body($resp);
            $json = json_decode($rawBody, true);

            if ($code >= 200 && $code < 300) {
                return ['ok' => true, 'code' => $code, 'data' => is_array($json) ? $json : []];
            }

            $errMessage = $json['error']['message'] ?? "HTTP {$code} error";
            return ['ok' => false, 'code' => $code, 'error' => $errMessage];
        }

        // Fallback for standalone/cURL environments
        $ch = curl_init($url);
        $curlHeaders = [];
        foreach ($headers as $k => $v) {
            $curlHeaders[] = "{$k}: {$v}";
        }

        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST  => $method,
            CURLOPT_HTTPHEADER     => $curlHeaders,
            CURLOPT_TIMEOUT        => 10,
        ]);

        if ($method !== 'GET' && !empty($body)) {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($body));
        }

        $res = curl_exec($ch);
        $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (PHP_VERSION_ID < 80000) {
            curl_close($ch);
        }

        $json = json_decode((string) $res, true);
        if ($code >= 200 && $code < 300) {
            return ['ok' => true, 'code' => $code, 'data' => is_array($json) ? $json : []];
        }

        return ['ok' => false, 'code' => $code, 'error' => $json['error']['message'] ?? "HTTP {$code}"];
    }
}
