<?php

declare(strict_types=1);

namespace Helmetsan\Core\Cloudflare;

use WP_Error;

/**
 * Handles purging and probing Cloudflare Edge Cache.
 */
class CloudflareCacheService
{
    /**
     * Cloudflare restricts 'files' purge array to a maximum of 30 items per request.
     */
    public const PURGE_URL_BATCH_LIMIT = 30;

    /**
     * Secret key for Cloudflare Worker direct edge purge/probe.
     */
    public const DEFAULT_PURGE_SECRET = 'helmetsan-edge-purge-secret-2026';

    /**
     * Worker purge and probe endpoint.
     */
    public const WORKER_PURGE_ENDPOINT = '/__edge-cache/purge';

    /**
     * Register AJAX diagnostic and purge hooks.
     */
    public function registerAjaxHooks(): void
    {
        add_action('wp_ajax_helmetsan_edge_cache_probe', [$this, 'ajaxProbe']);
        add_action('wp_ajax_helmetsan_edge_cache_purge', [$this, 'ajaxPurge']);
    }

    /**
     * Run real-time diagnostic probe against Cloudflare Edge Cache Worker.
     *
     * @return array<string, mixed>
     */
    public function probe(): array
    {
        if (!function_exists('wp_remote_get') || !function_exists('home_url')) {
            return [
                'status'  => 'error',
                'message' => 'WordPress HTTP API not loaded.',
            ];
        }

        $secret = defined('HELMETSAN_EDGE_PURGE_SECRET') ? HELMETSAN_EDGE_PURGE_SECRET : self::DEFAULT_PURGE_SECRET;
        $url = home_url(self::WORKER_PURGE_ENDPOINT);

        $start = microtime(true);
        $response = wp_remote_get($url, [
            'timeout'   => 5,
            'sslverify' => true,
            'headers'   => [
                'x-purge-token' => $secret,
                'X-Purge-Key'   => $secret,
            ],
        ]);
        $durationMs = round((microtime(true) - $start) * 1000, 2);

        if (is_wp_error($response)) {
            return [
                'status'      => 'error',
                'message'     => $response->get_error_message(),
                'duration_ms' => $durationMs,
                'timestamp'   => current_time('mysql'),
            ];
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $headers = wp_remote_retrieve_headers($response);
        $json = json_decode($body, true);

        return [
            'status'      => ($code === 200 && is_array($json) && ($json['status'] ?? '') === 'online') ? 'online' : 'error',
            'http_code'   => $code,
            'worker'      => $json['worker'] ?? 'unknown',
            'version'     => $json['version'] ?? 'unknown',
            'colo'        => $json['colo'] ?? ($headers['cf-ray'] ?? 'UNKNOWN'),
            'city'        => $json['city'] ?? 'Global Edge',
            'country'     => $json['country'] ?? '',
            'duration_ms' => $durationMs,
            'timestamp'   => current_time('mysql'),
        ];
    }

    /**
     * Purge everything from the Cloudflare zone cache and Edge Worker.
     */
    public function purgeEverything(bool $blocking = true): bool|WP_Error
    {
        $zoneId = defined('HELMETSAN_CLOUDFLARE_ZONE_ID') ? HELMETSAN_CLOUDFLARE_ZONE_ID : '';
        $apiToken = defined('HELMETSAN_CLOUDFLARE_API_TOKEN') ? HELMETSAN_CLOUDFLARE_API_TOKEN : '';

        if (empty($zoneId) || empty($apiToken)) {
            return new WP_Error('cf_not_configured', 'Cloudflare API credentials are not defined.');
        }

        // 1. Direct Cloudflare Edge Worker Purge
        $this->purgeWorker(['purge_everything' => true], $blocking);

        $url = sprintf('https://api.cloudflare.com/client/v4/zones/%s/purge_cache', urlencode($zoneId));

        $response = wp_remote_post($url, [
            'headers' => [
                'Authorization' => 'Bearer ' . $apiToken,
                'Content-Type'  => 'application/json',
            ],
            'body'    => wp_json_encode(['purge_everything' => true]),
            'timeout' => $blocking ? 15 : 1,
            'blocking' => $blocking,
        ]);

        if ($blocking && is_wp_error($response)) {
            return new WP_Error('cf_network_error', 'Failed to contact Cloudflare: ' . $response->get_error_message());
        }

        if ($blocking && !is_wp_error($response)) {
            $code = wp_remote_retrieve_response_code($response);
            $body = wp_remote_retrieve_body($response);
            $data = json_decode($body, true);

            if ($code !== 200 || empty($data['success'])) {
                $err = $data['errors'][0]['message'] ?? 'Unknown Cloudflare API error.';
                return new WP_Error('cf_api_error', 'Cloudflare Cache Purge Failed: ' . $err);
            }
        }

        return true;
    }

    /**
     * Purge specific URLs from the Cloudflare zone cache and Edge Worker.
     *
     * @param array<int, string> $urls URLs to purge.
     */
    public function purgeUrls(array $urls, bool $blocking = true): bool|WP_Error
    {
        if (empty($urls)) {
            return true;
        }

        $zoneId = defined('HELMETSAN_CLOUDFLARE_ZONE_ID') ? HELMETSAN_CLOUDFLARE_ZONE_ID : '';
        $apiToken = defined('HELMETSAN_CLOUDFLARE_API_TOKEN') ? HELMETSAN_CLOUDFLARE_API_TOKEN : '';

        if (empty($zoneId) || empty($apiToken)) {
            return new WP_Error('cf_not_configured', 'Cloudflare API credentials are not defined.');
        }

        $uniqueUrls = array_values(array_unique($urls));

        // 1. Direct Cloudflare Edge Worker Purge
        $this->purgeWorker(['urls' => $uniqueUrls], $blocking);

        // Cloudflare restricts 'files' purge array to a maximum of 30 items per request
        $batches = array_chunk($uniqueUrls, self::PURGE_URL_BATCH_LIMIT);
        $url = sprintf('https://api.cloudflare.com/client/v4/zones/%s/purge_cache', urlencode($zoneId));

        foreach ($batches as $batch) {
            $response = wp_remote_post($url, [
                'headers' => [
                    'Authorization' => 'Bearer ' . $apiToken,
                    'Content-Type'  => 'application/json',
                ],
                'body'    => wp_json_encode(['files' => $batch]),
                'timeout' => $blocking ? 15 : 1,
                'blocking' => $blocking,
            ]);

            if ($blocking && is_wp_error($response)) {
                return new WP_Error('cf_network_error', 'Failed to contact Cloudflare: ' . $response->get_error_message());
            }

            if ($blocking && !is_wp_error($response)) {
                $code = wp_remote_retrieve_response_code($response);
                $body = wp_remote_retrieve_body($response);
                $data = json_decode($body, true);

                if ($code !== 200 || empty($data['success'])) {
                    $err = $data['errors'][0]['message'] ?? 'Unknown Cloudflare API error.';
                    return new WP_Error('cf_api_error', 'Cloudflare URL Cache Purge Failed: ' . $err);
                }
            }
        }

        return true;
    }

    /**
     * Dispatches invalidation directly to the Edge Worker.
     *
     * @param array<string, mixed> $payload
     */
    private function purgeWorker(array $payload, bool $blocking = true): bool
    {
        if (!function_exists('wp_remote_post') || !function_exists('home_url')) {
            return false;
        }

        $secret = defined('HELMETSAN_EDGE_PURGE_SECRET') ? HELMETSAN_EDGE_PURGE_SECRET : self::DEFAULT_PURGE_SECRET;
        $url = home_url(self::WORKER_PURGE_ENDPOINT);

        $response = wp_remote_post($url, [
            'timeout'   => $blocking ? 5 : 1,
            'blocking'  => $blocking,
            'sslverify' => true,
            'headers'   => [
                'Content-Type'  => 'application/json',
                'x-purge-token' => $secret,
                'X-Purge-Key'   => $secret,
            ],
            'body'      => wp_json_encode($payload),
        ]);

        return !is_wp_error($response);
    }

    /**
     * AJAX probe handler.
     */
    public function ajaxProbe(): void
    {
        check_ajax_referer('helmetsan_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }
        $probe = $this->probe();
        wp_send_json_success($probe);
    }

    /**
     * AJAX purge handler.
     */
    public function ajaxPurge(): void
    {
        check_ajax_referer('helmetsan_admin_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Unauthorized'], 403);
        }

        $url = isset($_POST['url']) ? esc_url_raw(wp_unslash($_POST['url'])) : '';
        if ($url) {
            $res = $this->purgeUrls([$url], false);
        } else {
            $res = $this->purgeEverything(false);
        }

        if (is_wp_error($res)) {
            wp_send_json_error(['message' => $res->get_error_message()], 500);
        }

        wp_send_json_success(['status' => 'purged']);
    }

    /**
     * Purge all localized variations of paths across registered languages.
     *
     * @param array<int, string> $relativePaths e.g. ['/', '/helmets/']
     */
    public function purgeMultilingualPaths(array $relativePaths): bool|WP_Error
    {
        $languages = function_exists('pll_languages_list') ? pll_languages_list() : ['en', 'de', 'zh'];
        $defaultLang = function_exists('pll_default_language') ? pll_default_language() : 'en';
        $fullUrls = [];

        foreach ($relativePaths as $path) {
            $cleanPath = '/' . ltrim($path, '/');

            // Default / master language URL
            $fullUrls[] = home_url($cleanPath);

            // Localized language paths (e.g. /de/path, /zh/path)
            foreach ($languages as $lang) {
                if ($lang === $defaultLang) {
                    continue;
                }
                $fullUrls[] = home_url('/' . $lang . $cleanPath);
            }
        }

        return $this->purgeUrls($fullUrls);
    }
}

