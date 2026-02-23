<?php

declare(strict_types=1);

namespace Helmetsan\Core\Revenue;

use Helmetsan\Core\Geo\GeoService;
use Helmetsan\Core\Support\Config;

final class RevenueService
{
    /** Country code (e.g. IN, US) → preferred Amazon marketplace ID for geo fallback */
    private const COUNTRY_TO_AMAZON_MARKETPLACE = [
        'US' => 'amazon-us',
        'IN' => 'amazon-in',
        'UK' => 'amazon-uk',
        'GB' => 'amazon-uk',
        'DE' => 'amazon-de',
        'FR' => 'amazon-fr',
        'CA' => 'amazon-ca',
        'IT' => 'amazon-it',
        'ES' => 'amazon-es',
        'JP' => 'amazon-jp',
        'AU' => 'amazon-au',
    ];

    public function __construct(
        private readonly Config $config,
        private readonly ?GeoService $geo = null,
    ) {}

    public function register(): void
    {
        add_action('init', [$this, 'registerRewrite']);
        add_filter('query_vars', [$this, 'registerQueryVars']);
        add_action('template_redirect', [$this, 'handleRedirect']);
    }

    public function ensureTable(): void
    {
        global $wpdb;

        $table = $this->tableName();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            created_at datetime NOT NULL,
            helmet_id bigint(20) unsigned NOT NULL,
            marketplace_id varchar(50) NOT NULL DEFAULT '',
            click_source varchar(50) NOT NULL,
            affiliate_network varchar(50) NOT NULL,
            destination_url text NOT NULL,
            referer text,
            user_agent text,
            ip_hash varchar(64) DEFAULT '',
            PRIMARY KEY (id),
            KEY helmet_id (helmet_id),
            KEY marketplace_id (marketplace_id),
            KEY click_source (click_source),
            KEY affiliate_network (affiliate_network),
            KEY created_at (created_at)
        ) {$charset};";;

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function tableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'helmetsan_clicks';
    }

    public function registerRewrite(): void
    {
        add_rewrite_rule('^go/([^/]+)/?$', 'index.php?helmetsan_go=$matches[1]', 'top');
    }

    /**
     * @param array<int,string> $vars
     * @return array<int,string>
     */
    public function registerQueryVars(array $vars): array
    {
        $vars[] = 'helmetsan_go';

        return $vars;
    }

    public function handleRedirect(): void
    {
        $settings = $this->config->revenueConfig();
        if (empty($settings['enable_redirect_tracking'])) {
            return;
        }

        $slug = get_query_var('helmetsan_go');
        if (! is_string($slug) || $slug === '') {
            return;
        }

        $helmets = get_posts([
            'name'           => $slug,
            'post_type'      => 'helmet',
            'posts_per_page' => 1,
            'post_status'    => 'any',
        ]);
        $post = !empty($helmets) ? $helmets[0] : null;
        if (! $post instanceof \WP_Post) {
            $accessories = get_posts([
                'name'           => $slug,
                'post_type'      => 'accessory',
                'posts_per_page' => 1,
                'post_status'    => 'any',
            ]);
            $post = !empty($accessories) ? $accessories[0] : null;
        }
        if (! $post instanceof \WP_Post) {
            wp_safe_redirect(home_url('/'), 302);
            exit;
        }

        $helmetId = (int) $post->ID;
        $marketplaceId = isset($_GET['marketplace']) ? sanitize_text_field((string) $_GET['marketplace']) : '';
        $source = isset($_GET['source']) ? sanitize_text_field((string) $_GET['source']) : 'direct';

        // Try multi-network URL first (with normalized marketplace ID)
        $destination = '';
        $network = '';

        if ($marketplaceId !== '') {
            $result = $this->buildMultiNetworkUrl($helmetId, $marketplaceId, $settings);
            $destination = $result['url'];
            $network = $result['network'];
            // No stored link for this marketplace: use ASIN-based regional Amazon URL so e.g. India sees Amazon India
            if ($destination === '' && str_starts_with(strtolower($marketplaceId), 'amazon-')) {
                $destination = $this->buildLegacyAmazonUrlForRegion($helmetId, $marketplaceId, $settings);
                if ($destination !== '') {
                    $network = 'amazon';
                }
            }
            // No stored Flipkart link: redirect to Flipkart search by helmet title (India)
            if ($destination === '' && str_starts_with(strtolower($marketplaceId), 'flipkart-')) {
                $destination = $this->buildFlipkartSearchUrl($helmetId);
                if ($destination !== '') {
                    $network = 'flipkart';
                }
            }
        }

        // When no marketplace or "static": try geo-driven default from stored links
        if ($destination === '' && $this->geo !== null) {
            $country = $this->geo->getCountry();
            $preferredMp = self::COUNTRY_TO_AMAZON_MARKETPLACE[strtoupper($country)] ?? 'amazon-us';
            $result = $this->buildMultiNetworkUrl($helmetId, $preferredMp, $settings);
            if ($result['url'] !== '') {
                $destination = $result['url'];
                $network = $result['network'];
                $marketplaceId = $preferredMp;
            }
        }

        // Legacy fallback (ASIN / affiliate_url); for geo fallback prefer regional Amazon when possible
        if ($destination === '' && $marketplaceId !== '' && str_starts_with(strtolower($marketplaceId), 'amazon-')) {
            $destination = $this->buildLegacyAmazonUrlForRegion($helmetId, $marketplaceId, $settings);
            if ($destination !== '') {
                $network = 'amazon';
            }
        }
        if ($destination === '') {
            $destination = $this->buildLegacyUrl($helmetId, $settings);
            $network = $settings['default_affiliate_network'] ?? 'amazon';
        }

        if ($destination === '') {
            wp_safe_redirect(get_permalink($helmetId), 302);
            exit;
        }

        $this->logClick($helmetId, $source, $network, $destination, $marketplaceId);

        $code = isset($settings['redirect_status_code']) ? (int) $settings['redirect_status_code'] : 302;
        if (! in_array($code, [301, 302, 307, 308], true)) {
            $code = 302;
        }

        wp_redirect($destination, $code);
        exit;
    }

    /**
     * Build affiliate URL for a specific marketplace using affiliate_links_json.
     *
     * @param array<string,mixed> $settings
     * @return array{url: string, network: string}
     */
    public function buildMultiNetworkUrl(int $helmetId, string $marketplaceId, array $settings): array
    {
        $linksJson = (string) get_post_meta($helmetId, 'affiliate_links_json', true);
        $links = json_decode($linksJson, true);

        if (!is_array($links)) {
            return ['url' => '', 'network' => ''];
        }

        // Normalize: stored keys use hyphens (amazon-us); allow lookup by amazon_us
        $key = $marketplaceId;
        if (!isset($links[$key])) {
            $key = str_replace('_', '-', strtolower($marketplaceId));
        }
        if (!isset($links[$key])) {
            return ['url' => '', 'network' => ''];
        }

        $entry = $links[$key];
        $network = $entry['network'] ?? 'direct';
        $url = $entry['url'] ?? '';

        if ($url === '') {
            return ['url' => '', 'network' => $network];
        }

        $networkCfg = $settings['affiliate_networks'][$network] ?? [];

        $affiliateUrl = match ($network) {
            'amazon'   => $this->buildAmazonUrl($url, $entry, $networkCfg, $helmetId, $marketplaceId),
            'cj'       => $this->buildCjUrl($url, $entry, $networkCfg, $helmetId),
            'allegro'  => $this->buildAllegroUrl($url, $entry, $networkCfg),
            'jumia'    => $this->buildJumiaUrl($url, $entry, $networkCfg),
            'flipkart' => $this->buildFlipkartUrl($url, $entry, $networkCfg),
            default    => $url,
        };

        return ['url' => esc_url_raw($affiliateUrl), 'network' => $network];
    }

    /**
     * Get all affiliate links for a post (helmet or accessory).
     *
     * @return array<string, array{url: string, network: string, marketplace_name: string}>
     */
    public function getAffiliateLinks(int $postId): array
    {
        $linksJson = (string) get_post_meta($postId, 'affiliate_links_json', true);
        $links = json_decode($linksJson, true);

        return is_array($links) ? $links : [];
    }

    /**
     * Preferred Amazon marketplace ID for a country (for geo fallback row).
     *
     * @param string|null $country ISO country code e.g. IN, US; if null uses GeoService
     */
    public function getGeoAmazonMarketplaceId(?string $country = null): string
    {
        if ($country === null && $this->geo !== null) {
            $country = $this->geo->getCountry();
        }
        $key = $country !== '' ? strtoupper($country) : 'US';
        if ($key === 'GB') {
            $key = 'UK';
        }

        return self::COUNTRY_TO_AMAZON_MARKETPLACE[$key] ?? 'amazon-us';
    }

    // ─── Network-specific URL builders ───────────────────────────────────

    private function buildAmazonUrl(string $url, array $entry, array $cfg, int $helmetId, string $marketplaceId = ''): string
    {
        $url = $this->normalizeAmazonSearchQuery($url, $helmetId);

        $tag = $entry['tag'] ?? '';

        if ($tag === '') {
            $tag = $this->getAmazonTagOverride($helmetId);
        }

        // Apply geo-specific tag from settings if available
        if ($tag === '') {
            $settings = $this->config->revenueConfig();
            if ($marketplaceId === 'amazon-uk' && ($settings['amazon_tag_uk'] ?? '') !== '') {
                $tag = $settings['amazon_tag_uk'];
            } elseif ($marketplaceId === 'amazon-in' && ($settings['amazon_tag_in'] ?? '') !== '') {
                $tag = $settings['amazon_tag_in'];
            } elseif ($marketplaceId === 'amazon-de' && ($settings['amazon_tag_de'] ?? '') !== '') {
                $tag = $settings['amazon_tag_de'];
            } elseif ($marketplaceId === 'amazon-fr' && ($settings['amazon_tag_fr'] ?? '') !== '') {
                $tag = $settings['amazon_tag_fr'];
            }
        }

        if ($tag === '') {
            $tag = $cfg['tag'] ?? 'helmetsan-20';
        }

        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . 'tag=' . rawurlencode($tag);
    }

    /**
     * If the URL is an Amazon search URL and the query looks like the post slug (e.g. ls2-explorer-carbon-solid-lg),
     * replace it with the post title (e.g. LS2 Explorer Carbon Solid LG) so Amazon search works properly.
     */
    private function normalizeAmazonSearchQuery(string $url, int $helmetId): string
    {
        if (! str_contains($url, '/s?') && ! str_contains($url, '/s/')) {
            return $url;
        }
        $parsed = wp_parse_url($url);
        if (! is_array($parsed) || ! isset($parsed['query'])) {
            return $url;
        }
        parse_str($parsed['query'], $params);
        $k = isset($params['k']) ? (string) $params['k'] : '';
        if ($k === '') {
            return $url;
        }
        $slug = (string) get_post_field('post_name', $helmetId);
        $kNorm = strtolower(str_replace(['-', ' '], '', $k));
        $slugNorm = strtolower(str_replace(['-', ' '], '', $slug));
        if ($slug === '' || $kNorm !== $slugNorm) {
            return $url;
        }
        $title = (string) get_post_field('post_title', $helmetId);
        if ($title === '') {
            return $url;
        }
        $params['k'] = $title;
        $newQuery = http_build_query($params);
        $path = $parsed['path'] ?? '/s';
        $host = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? 'www.amazon.com');
        return $host . $path . '?' . $newQuery;
    }

    private function getAmazonTagOverride(int $helmetId): string
    {
        // 1. Check Brand Level
        $brands = get_the_terms($helmetId, 'helmet_brand');
        if (is_array($brands) && count($brands) > 0) {
            $brandTag = (string) get_term_meta($brands[0]->term_id, 'amazon_tag_override', true);
            if ($brandTag !== '') {
                return $brandTag;
            }
        }

        // 2. Check Category (Type) Level
        $types = get_the_terms($helmetId, 'helmet_type');
        if (is_array($types) && count($types) > 0) {
            $typeTag = (string) get_term_meta($types[0]->term_id, 'amazon_tag_override', true);
            if ($typeTag !== '') {
                return $typeTag;
            }
        }

        return '';
    }

    private function buildCjUrl(string $url, array $entry, array $cfg, int $helmetId): string
    {
        $websiteId = $cfg['website_id'] ?? '';
        $sid = $entry['sid'] ?? (string) $helmetId;
        if ($websiteId === '') {
            return $url;
        }
        return 'https://www.anrdoezrs.net/links/' . rawurlencode($websiteId)
            . '/type/dlg/sid/' . rawurlencode($sid)
            . '/' . $url;
    }

    private function buildAllegroUrl(string $url, array $entry, array $cfg): string
    {
        $affId = $entry['aff_id'] ?? $cfg['aff_id'] ?? '';
        if ($affId === '') {
            return $url;
        }
        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . 'aff_id=' . rawurlencode($affId);
    }

    private function buildJumiaUrl(string $url, array $entry, array $cfg): string
    {
        $affId = $entry['aff_id'] ?? $cfg['aff_id'] ?? '';
        if ($affId === '') {
            return $url;
        }
        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . 'aff_id=' . rawurlencode($affId);
    }

    private function buildFlipkartUrl(string $url, array $entry, array $cfg): string
    {
        $affId = $entry['aff_id'] ?? $cfg['aff_id'] ?? '';
        if ($affId === '') {
            $affId = $this->config->marketplaceConfig()['flipkart_affiliate_id'] ?? '';
        }
        if ($affId === '') {
            return $url;
        }
        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . 'affid=' . rawurlencode($affId);
    }

    /**
     * Flipkart search URL when no product link is stored (India). Uses helmet title as search query.
     * If the title looks like a slug (e.g. shoei-x-15-marquez-7-md), converts to readable phrase for better search.
     */
    private function buildFlipkartSearchUrl(int $helmetId): string
    {
        $title = (string) get_post_field('post_title', $helmetId);
        if ($title === '') {
            return '';
        }
        $slug = (string) get_post_field('post_name', $helmetId);
        $query = $this->searchQueryFromTitleOrSlug($title, $slug);
        $settings = $this->config->revenueConfig();
        $affId = $settings['affiliate_networks']['flipkart']['aff_id'] ?? '';
        if ($affId === '') {
            $affId = $this->config->marketplaceConfig()['flipkart_affiliate_id'] ?? '';
        }
        $url = 'https://www.flipkart.com/search?q=' . rawurlencode($query);
        if ($affId !== '') {
            $url .= '&affid=' . rawurlencode($affId);
        }
        return $url;
    }

    /**
     * Prefer human-readable title for search; if title looks like a slug, convert to readable phrase.
     */
    private function searchQueryFromTitleOrSlug(string $title, string $slug): string
    {
        $trimmed = trim($title);
        if ($trimmed === '') {
            $trimmed = $slug;
        }
        if ($trimmed === '') {
            return '';
        }
        $norm = strtolower(str_replace(['-', ' '], '', $trimmed));
        $slugNorm = strtolower(str_replace(['-', ' '], '', $slug));
        if ($slug !== '' && $norm === $slugNorm) {
            return ucwords(str_replace('-', ' ', $slug));
        }
        return $trimmed;
    }

    /**
     * Whether Flipkart is enabled in Marketplace settings (so theme can show Flipkart row for IN visitors).
     */
    public function hasFlipkartEnabled(): bool
    {
        $cfg = $this->config->marketplaceConfig();
        return ! empty($cfg['flipkart_enabled']);
    }

    /**
     * Build Amazon product URL for a region when no stored marketplace_links (ASIN fallback).
     * Ensures e.g. Indian users get amazon.in with India tag.
     *
     * @param array<string,mixed> $settings
     */
    private function buildLegacyAmazonUrlForRegion(int $helmetId, string $marketplaceId, array $settings): string
    {
        $asin = (string) get_post_meta($helmetId, 'affiliate_asin', true);
        $mp = strtolower($marketplaceId);
        $domains = [
            'amazon-us' => 'https://www.amazon.com',
            'amazon-in' => 'https://www.amazon.in',
            'amazon-uk' => 'https://www.amazon.co.uk',
            'amazon-de' => 'https://www.amazon.de',
            'amazon-fr' => 'https://www.amazon.fr',
            'amazon-ca' => 'https://www.amazon.ca',
            'amazon-it' => 'https://www.amazon.it',
            'amazon-es' => 'https://www.amazon.es',
            'amazon-jp' => 'https://www.amazon.co.jp',
            'amazon-au' => 'https://www.amazon.com.au',
        ];
        $base = $domains[$mp] ?? 'https://www.amazon.com';

        $tag = $this->getAmazonTagOverride($helmetId);
        if ($tag === '') {
            if ($mp === 'amazon-uk' && ($settings['amazon_tag_uk'] ?? '') !== '') {
                $tag = $settings['amazon_tag_uk'];
            } elseif ($mp === 'amazon-in' && ($settings['amazon_tag_in'] ?? '') !== '') {
                $tag = $settings['amazon_tag_in'];
            } elseif ($mp === 'amazon-de' && ($settings['amazon_tag_de'] ?? '') !== '') {
                $tag = $settings['amazon_tag_de'];
            } elseif ($mp === 'amazon-fr' && ($settings['amazon_tag_fr'] ?? '') !== '') {
                $tag = $settings['amazon_tag_fr'];
            } else {
                $tag = $settings['amazon_tag'] ?? 'helmetsan-20';
            }
        }

        if ($asin !== '') {
            return $base . '/dp/' . rawurlencode($asin) . '?tag=' . rawurlencode($tag);
        }

        // No ASIN: fall back to search by helmet title so the row still works on every helmet page
        $title = (string) get_post_field('post_title', $helmetId);
        if ($title === '') {
            return '';
        }
        $query = rawurlencode($title);
        return $base . '/s?k=' . $query . '&tag=' . rawurlencode($tag);
    }

    /**
     * Legacy URL builder (backward-compatible).
     *
     * @param array<string,mixed> $settings
     */
    private function buildLegacyUrl(int $helmetId, array $settings): string
    {
        $custom = (string) get_post_meta($helmetId, 'affiliate_url', true);
        if ($custom !== '') {
            return esc_url_raw($custom);
        }

        $asin = (string) get_post_meta($helmetId, 'affiliate_asin', true);
        if ($asin === '') {
            return '';
        }

        $tag = $this->getAmazonTagOverride($helmetId);
        if ($tag === '') {
            $tag = $settings['amazon_tag'] ?? 'helmetsan-20';
        }

        return 'https://www.amazon.com/dp/' . rawurlencode($asin) . '?tag=' . rawurlencode($tag);
    }

    private function logClick(int $helmetId, string $source, string $network, string $destination, string $marketplaceId = ''): void
    {
        global $wpdb;

        if (! $this->tableExists()) {
            return;
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        $ipHash = $ip !== '' ? hash('sha256', $ip . wp_salt('auth')) : '';

        $wpdb->insert(
            $this->tableName(),
            [
                'created_at'        => current_time('mysql'),
                'helmet_id'         => $helmetId,
                'marketplace_id'    => sanitize_text_field($marketplaceId),
                'click_source'      => sanitize_text_field($source),
                'affiliate_network' => sanitize_text_field($network),
                'destination_url'   => esc_url_raw($destination),
                'referer'           => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw((string) $_SERVER['HTTP_REFERER']) : '',
                'user_agent'        => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field((string) $_SERVER['HTTP_USER_AGENT']) : '',
                'ip_hash'           => $ipHash,
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    public function tableExists(): bool
    {
        global $wpdb;

        $table = $this->tableName();
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

        return $exists === $table;
    }

    /**
     * @return array<string,mixed>
     */
    public function report(int $days = 30): array
    {
        global $wpdb;

        if (! $this->tableExists()) {
            return [
                'ok'      => false,
                'message' => 'Revenue table not found',
            ];
        }

        $days = max(1, $days);
        $from = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        $table = $this->tableName();

        $total = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE created_at >= %s', $from));

        $bySourceRows = $wpdb->get_results($wpdb->prepare(
            'SELECT click_source, COUNT(*) as total FROM ' . $table . ' WHERE created_at >= %s GROUP BY click_source ORDER BY total DESC',
            $from
        ), ARRAY_A);

        $byNetworkRows = $wpdb->get_results($wpdb->prepare(
            'SELECT affiliate_network, COUNT(*) as total FROM ' . $table . ' WHERE created_at >= %s GROUP BY affiliate_network ORDER BY total DESC',
            $from
        ), ARRAY_A);

        $topHelmetRows = $wpdb->get_results($wpdb->prepare(
            'SELECT helmet_id, COUNT(*) as total FROM ' . $table . ' WHERE created_at >= %s GROUP BY helmet_id ORDER BY total DESC LIMIT 10',
            $from
        ), ARRAY_A);

        $bySource = [];
        if (is_array($bySourceRows)) {
            foreach ($bySourceRows as $row) {
                $key = isset($row['click_source']) ? (string) $row['click_source'] : '';
                if ($key !== '') {
                    $bySource[$key] = isset($row['total']) ? (int) $row['total'] : 0;
                }
            }
        }

        $byNetwork = [];
        if (is_array($byNetworkRows)) {
            foreach ($byNetworkRows as $row) {
                $key = isset($row['affiliate_network']) ? (string) $row['affiliate_network'] : '';
                if ($key !== '') {
                    $byNetwork[$key] = isset($row['total']) ? (int) $row['total'] : 0;
                }
            }
        }

        $topHelmets = [];
        if (is_array($topHelmetRows)) {
            foreach ($topHelmetRows as $row) {
                $helmetId = isset($row['helmet_id']) ? (int) $row['helmet_id'] : 0;
                if ($helmetId <= 0) {
                    continue;
                }
                $topHelmets[] = [
                    'helmet_id' => $helmetId,
                    'title'     => get_the_title($helmetId),
                    'clicks'    => isset($row['total']) ? (int) $row['total'] : 0,
                ];
            }
        }

        return [
            'ok'          => true,
            'days'        => $days,
            'from'        => $from,
            'total_clicks' => $total,
            'by_source'   => $bySource,
            'by_network'  => $byNetwork,
            'top_helmets' => $topHelmets,
        ];
    }

    /**
     * Report clicks grouped by marketplace.
     *
     * @return array<string, int>
     */
    public function reportByMarketplace(int $days = 30): array
    {
        global $wpdb;

        if (! $this->tableExists()) {
            return [];
        }

        $from  = gmdate('Y-m-d H:i:s', time() - (max(1, $days) * DAY_IN_SECONDS));
        $table = $this->tableName();

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT marketplace_id, COUNT(*) as total FROM ' . $table . ' WHERE created_at >= %s AND marketplace_id != "" GROUP BY marketplace_id ORDER BY total DESC',
            $from
        ), ARRAY_A);

        $result = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $key = (string) ($row['marketplace_id'] ?? '');
                if ($key !== '') {
                    $result[$key] = (int) ($row['total'] ?? 0);
                }
            }
        }

        return $result;
    }
}
