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
            click_source varchar(50) NOT NULL,
            affiliate_network varchar(50) NOT NULL,
            destination_url text NOT NULL,
            referer text,
            user_agent text,
            ip_hash varchar(64) DEFAULT '',
            PRIMARY KEY (id),
            KEY helmet_id (helmet_id),
            KEY click_source (click_source),
            KEY affiliate_network (affiliate_network),
            KEY created_at (created_at)
        ) {$charset};";

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

        $destination = $this->buildDestinationUrl((int) $helmet->ID, $settings);
        if ($destination === '') {
            wp_safe_redirect(get_permalink((int) $helmet->ID), 302);
            exit;
        }

        $source = isset($_GET['source']) ? sanitize_text_field((string) $_GET['source']) : 'direct';
        $network = isset($settings['default_affiliate_network']) ? (string) $settings['default_affiliate_network'] : 'affiliate';

        $this->logClick((int) $helmet->ID, $source, $network, $destination);

        $code = isset($settings['redirect_status_code']) ? (int) $settings['redirect_status_code'] : 302;
        if (! in_array($code, [301, 302, 307, 308], true)) {
            $code = 302;
        }

        wp_safe_redirect($destination, $code);
        exit;
    }

    /**
     * @param array<string,mixed> $settings
     */
    private function buildDestinationUrl(int $helmetId, array $settings): string
    {
        $custom = (string) get_post_meta($helmetId, 'affiliate_url', true);
        if ($custom !== '') {
            return esc_url_raw($custom);
        }

        $asin = (string) get_post_meta($helmetId, 'affiliate_asin', true);
        if ($asin === '') {
            return '';
        }

        $tag = isset($settings['amazon_tag']) ? sanitize_text_field((string) $settings['amazon_tag']) : 'helmetsan-20';

        return 'https://www.amazon.com/dp/' . rawurlencode($asin) . '?tag=' . rawurlencode($tag);
    }

    private function logClick(int $helmetId, string $source, string $network, string $destination): void
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
                'click_source'      => sanitize_text_field($source),
                'affiliate_network' => sanitize_text_field($network),
                'destination_url'   => esc_url_raw($destination),
                'referer'           => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw((string) $_SERVER['HTTP_REFERER']) : '',
                'user_agent'        => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field((string) $_SERVER['HTTP_USER_AGENT']) : '',
                'ip_hash'           => $ipHash,
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s']
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
}
