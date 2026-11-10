<?php
declare(strict_types=1);

namespace HelmetsanTranslate;

defined('ABSPATH') || exit;

class CachePurger {
    public static function purge_urls(array $urls): void {
        if (empty($urls)) return;

        // 1. Local Nginx Helper Cache Purge
        if (class_exists('\Nginx_Helper')) {
            foreach ($urls as $url) {
                do_action('rt_nginx_helper_purge_url', $url);
            }
        }

        // 2. Cloudflare Zone API Purge
        $options = Settings::get_options();
        $cf = $options['cloudflare'] ?? [];

        if (empty($cf['purge_enabled']) || empty($cf['zone_id'])) return;

        $token = Encryption::decrypt($cf['api_token'] ?? '');
        if (empty($token)) return;

        $endpoint = "https://api.cloudflare.com/client/v4/zones/{$cf['zone_id']}/purge_cache";
        wp_remote_post($endpoint, [
            'timeout' => 5,
            'blocking' => false,
            'headers' => [
                'Authorization' => 'Bearer ' . $token,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode(['files' => array_values($urls)])
        ]);
    }
}
