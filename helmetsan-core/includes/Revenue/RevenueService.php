<?php

declare(strict_types=1);

namespace Helmetsan\Core\Revenue;

use Helmetsan\Core\Support\Config;

final class RevenueService
{
    public function __construct(private readonly Config $config)
    {
    }

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

        $helmet = get_page_by_path($slug, OBJECT, 'helmet');
        if (! $helmet instanceof \WP_Post) {
            wp_safe_redirect(home_url('/'), 302);
            exit;
        }

        $helmetId = (int) $helmet->ID;
        $marketplaceId = isset($_GET['marketplace']) ? sanitize_text_field((string) $_GET['marketplace']) : '';
        $source = isset($_GET['source']) ? sanitize_text_field((string) $_GET['source']) : 'direct';

        // Try multi-network URL first, then legacy fallback
        $destination = '';
        $network = '';

        if ($marketplaceId !== '') {
            $result = $this->buildMultiNetworkUrl($helmetId, $marketplaceId, $settings);
            $destination = $result['url'];
            $network = $result['network'];
        }

        // Legacy fallback
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

        if (!is_array($links) || !isset($links[$marketplaceId])) {
            return ['url' => '', 'network' => ''];
        }

        $entry = $links[$marketplaceId];
        $network = $entry['network'] ?? 'direct';
        $url = $entry['url'] ?? '';

        if ($url === '') {
            return ['url' => '', 'network' => $network];
        }

        $networkCfg = $settings['affiliate_networks'][$network] ?? [];

        $affiliateUrl = match ($network) {
            'amazon' => $this->buildAmazonUrl($url, $entry, $networkCfg),
            'cj'     => $this->buildCjUrl($url, $entry, $networkCfg, $helmetId),
            'allegro'=> $this->buildAllegroUrl($url, $entry, $networkCfg),
            'jumia'  => $this->buildJumiaUrl($url, $entry, $networkCfg),
            default  => $url,
        };

        return ['url' => esc_url_raw($affiliateUrl), 'network' => $network];
    }

    /**
     * Get all affiliate links for a helmet.
     *
     * @return array<string, array{url: string, network: string, marketplace_name: string}>
     */
    public function getAffiliateLinks(int $helmetId): array
    {
        $linksJson = (string) get_post_meta($helmetId, 'affiliate_links_json', true);
        $links = json_decode($linksJson, true);

        return is_array($links) ? $links : [];
    }

    // ─── Network-specific URL builders ───────────────────────────────────

    private function buildAmazonUrl(string $url, array $entry, array $cfg): string
    {
        $tag = $entry['tag'] ?? $cfg['tag'] ?? 'helmetsan-20';
        $separator = str_contains($url, '?') ? '&' : '?';
        return $url . $separator . 'tag=' . rawurlencode($tag);
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

        $tag = $settings['amazon_tag'] ?? 'helmetsan-20';
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
            'total_clicks'=> $total,
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
