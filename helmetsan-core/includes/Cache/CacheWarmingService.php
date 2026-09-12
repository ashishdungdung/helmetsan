<?php

declare(strict_types=1);

namespace Helmetsan\Core\Cache;

use Helmetsan\Core\Support\BackgroundTaskService;

/**
 * Handles pre-warming of edge and origin caches asynchronously.
 * Offloads HTTP GET requests to Action Scheduler / WP Cron.
 */
final class CacheWarmingService
{
    private BackgroundTaskService $tasks;

    public function __construct(BackgroundTaskService $tasks)
    {
        $this->tasks = $tasks;
    }

    /**
     * Register hook listener.
     */
    public function register(): void
    {
        add_action('helmetsan_warm_urls', [$this, 'warmUrls'], 10, 1);
        add_action('init', [$this, 'bypassPageCache']);
        add_action('template_redirect', [$this, 'addCacheHeaders'], 10);

        // Active Cache Push: purge and warm on product data changes
        add_action('save_post_helmet', [$this, 'onProductUpdated'], 20, 1);
        add_action('save_post_accessory', [$this, 'onProductUpdated'], 20, 1);
        add_action('save_post_motorcycle', [$this, 'onProductUpdated'], 20, 1);
    }

    /**
     * Triggered when a product is saved/updated.
     * Clears Cloudflare edge cache and queues pre-warming in background.
     */
    public function onProductUpdated(int $postId): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        $urls = $this->buildProductUrlList($postId);

        if (empty($urls)) {
            return;
        }

        // 1. Purge targeted URLs from Cloudflare
        $cf = new \Helmetsan\Core\Cloudflare\CloudflareCacheService();
        $cf->purgeUrls($urls);

        // 2. Queue pre-warming for the purged URLs
        $this->tasks->dispatch('helmetsan_warm_urls', ['urls' => $urls]);
    }

    /**
     * Bypass cache on pre-warm bypass token matching.
     */
    public function bypassPageCache(): void
    {
        $bypassToken = defined('HELMETSAN_PREWARM_BYPASS_TOKEN') ? HELMETSAN_PREWARM_BYPASS_TOKEN : '';
        if ($bypassToken === '') {
            return;
        }

        if (isset($_GET['hs_warm_bypass']) && $_GET['hs_warm_bypass'] === $bypassToken) {
            if (!defined('DONOTCACHEPAGE')) {
                define('DONOTCACHEPAGE', true);
            }
            // Send headers to bypass Nginx FastCGI microcache
            header('X-Accel-Expires: 0');
            header('Cache-Control: no-cache, must-revalidate, max-age=0');
        }
    }

    /**
     * Build list of related URLs for a given product ID (HTML, JSON API, translations, homepages).
     *
     * @return array<int, string>
     */
    private function buildProductUrlList(int $postId): array
    {
        $urls = [];

        // 1. Target post URL (HTML, JSON API, and Markdown formats)
        $url = get_permalink($postId);
        if (is_string($url) && $url !== '') {
            $urls[] = $url;
            $urls[] = add_query_arg('format', 'json', $url);
            $urls[] = add_query_arg('format', 'md', $url);
        }

        // 2. Polylang translations of the post
        if (function_exists('pll_get_post_translations')) {
            $translations = pll_get_post_translations($postId);
            if (is_array($translations)) {
                foreach ($translations as $lang => $transPostId) {
                    $transUrl = get_permalink($transPostId);
                    if (is_string($transUrl) && $transUrl !== '') {
                        $urls[] = $transUrl;
                        $urls[] = add_query_arg('format', 'json', $transUrl);
                        $urls[] = add_query_arg('format', 'md', $transUrl);
                    }
                }
            }
        }

        // 3. Homepage URLs for all active languages
        $homepages = $this->getHomepageUrls();
        $urls = array_merge($urls, $homepages);

        return array_values(array_unique(array_filter($urls)));
    }

    /**
     * Build list of primary catalog archive URLs to pre-warm (pages 1-3 and clean archive).
     *
     * @return array<int, string>
     */
    public function getCatalogArchiveUrls(): array
    {
        $archiveUrl = get_post_type_archive_link('helmet');
        if (!is_string($archiveUrl) || $archiveUrl === '') {
            $archiveUrl = home_url('/helmets/');
        }

        $urls = [
            $archiveUrl,
            add_query_arg('paged', 2, $archiveUrl),
            add_query_arg('paged', 3, $archiveUrl),
        ];

        return $urls;
    }

    /**
     * Dispatch background cache push task for a specific post.
     */
    public function queuePostWarming(int $postId): void
    {
        $urls = $this->buildProductUrlList($postId);

        if (!empty($urls)) {
            $this->tasks->dispatch('helmetsan_warm_urls', ['urls' => $urls]);
        }
    }

    /**
     * Dispatch warming tasks for homepages only (e.g. on menu or taxonomy term edits).
     */
    public function queueHomepageWarming(): void
    {
        $urls = $this->getHomepageUrls();
        if (!empty($urls)) {
            $this->tasks->dispatch('helmetsan_warm_urls', ['urls' => $urls]);
        }
    }

    /**
     * Background execution of pre-warming HTTP GET requests.
     *
     * @param array<int, string> $urls URLs to warm.
     */
    public function warmUrls(array $urls): void
    {
        $bypassToken = defined('HELMETSAN_PREWARM_BYPASS_TOKEN') ? HELMETSAN_PREWARM_BYPASS_TOKEN : '';

        foreach ($urls as $url) {
            if (empty($url)) {
                continue;
            }

            // Append hs_warm_bypass parameter if token is configured
            if ($bypassToken !== '') {
                $url = add_query_arg('hs_warm_bypass', $bypassToken, $url);
            }

            // Perform a non-blocking asynchronous request to the URL.
            // X-Helmetsan-Prewarm bypasses Cloudflare/Nginx cache-control on headers if we ever want to force refresh,
            // or lets us identify pre-warm requests in logs.
            wp_remote_get($url, [
                'timeout'    => 10,
                'sslverify'  => false,
                'user-agent' => 'Helmetsan Prewarmer/1.0',
                'headers'    => [
                    'X-Helmetsan-Prewarm' => '1',
                ],
            ]);
        }
    }

    /**
     * Retrieve homepage URLs for all registered Polylang languages.
     *
     * @return array<int, string>
     */
    private function getHomepageUrls(): array
    {
        $urls = [];

        if (function_exists('pll_languages_list')) {
            $languages = pll_languages_list();
            if (is_array($languages)) {
                foreach ($languages as $lang) {
                    if (function_exists('pll_home_url')) {
                        $home = pll_home_url($lang);
                        if (is_string($home) && $home !== '') {
                            $urls[] = $home;
                        }
                    }
                }
            }
        }

        if (empty($urls)) {
            $urls[] = home_url('/');
        }

        return $urls;
    }

    /**
     * Send cache-friendly headers for Cloudflare Edge Caching / Edge Assembly.
     */
    public function addCacheHeaders(): void
    {
        $cfSettings = get_option('helmetsan_cloudflare', []);
        $enabled = !empty($cfSettings['enable_edge_assembly']);
        if (!$enabled) {
            return;
        }

        // Do not cache admin, REST requests, or cron
        if (is_admin() || (defined('REST_REQUEST') && REST_REQUEST) || (function_exists('wp_doing_cron') && wp_doing_cron())) {
            return;
        }

        // Do not cache cart/checkout or account pages
        if (function_exists('is_cart') && is_cart()) return;
        if (function_exists('is_checkout') && is_checkout()) return;
        if (function_exists('is_account_page') && is_account_page()) return;

        // Bypass cache for logged-in users
        if (is_user_logged_in()) {
            header('Cache-Control: no-cache, must-revalidate, max-age=0');
            header('X-Accel-Expires: 0');
            return;
        }

        // Determine tiered TTLs based on content archetype
        $format = sanitize_key($_GET['format'] ?? '');
        $isFormatNegotiation = in_array($format, ['json', 'md'], true);

        if ($isFormatNegotiation) {
            // API JSON / Markdown responses: 30m browser, 1h edge (price/stock may update)
            $browserTtl = 1800;
            $edgeTtl = 3600;
        } elseif (is_singular(['helmet', 'accessory'])) {
            // Product single pages: 1h browser, 24h edge (specs rarely change)
            $browserTtl = 3600;
            $edgeTtl = 86400;
        } elseif (is_singular('brand') || is_tax(['helmet_brand', 'brand'])) {
            // Brand hub pages: 1h browser, 12h edge
            $browserTtl = 3600;
            $edgeTtl = 43200;
        } elseif (function_exists('is_page') && is_page('comparison')) {
            // Comparison pages: 1h browser, 24h edge (specs static)
            $browserTtl = 3600;
            $edgeTtl = 86400;
        } elseif (is_post_type_archive('helmet') || is_archive() || is_search()) {
            // Archive / faceted search pages: 15m browser, 1h edge (filters fluctuate)
            $browserTtl = 900;
            $edgeTtl = 3600;
        } else {
            // Default / Homepages: 1h browser, 12h edge
            $browserTtl = 3600;
            $edgeTtl = 43200;
        }

        header(sprintf('Cache-Control: public, max-age=%d, s-maxage=%d', $browserTtl, $edgeTtl));
        header(sprintf('X-Accel-Expires: %d', $edgeTtl));
    }
}
