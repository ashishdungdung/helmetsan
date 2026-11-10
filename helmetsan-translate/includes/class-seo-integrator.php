<?php
declare(strict_types=1);

namespace HelmetsanTranslate;

defined('ABSPATH') || exit;

class SeoIntegrator {
    public static function sync_meta(int $target_post_id, string $title, string $desc): void {
        if (!empty($title)) {
            update_post_meta($target_post_id, '_yoast_wpseo_title', $title);
        }
        if (!empty($desc)) {
            update_post_meta($target_post_id, '_yoast_wpseo_metadesc', $desc);
        }

        // Notify Yoast SEO indexable builder
        if (class_exists('\YoastSEO')) {
            \YoastSEO()->meta->for_post($target_post_id);
        }

        // Flush sitemap cache transients
        self::flush_sitemap_cache();
    }

    public static function flush_sitemap_cache(): void {
        global $wpdb;
        $wpdb->query("DELETE FROM {$wpdb->options} WHERE option_name LIKE '%yoast_seo_sitemap_%'");
    }

    public static function ping_indexnow(array $urls): void {
        $options = Settings::get_options();
        if (empty($options['seo']['indexnow_enabled'])) return;

        $key = $options['seo']['indexnow_key'] ?? 'c9a72e8140db4e5fb3d6812975ef83a0';
        if (empty($key) || empty($urls)) return;

        // Bridge into helmetsan-core IndexNowService if available
        if (class_exists('\\Helmetsan\\Core\\Seo\\IndexNowService')) {
            $service = new \Helmetsan\Core\Seo\IndexNowService();
            $service->submitBatch($urls);
            return;
        }

        $host = parse_url(home_url(), PHP_URL_HOST) ?: 'helmetsan.com';
        $payload = [
            'host' => $host,
            'key' => $key,
            'urlList' => array_slice(array_values($urls), 0, 100),
            'keyLocation' => "https://{$host}/{$key}.txt"
        ];

        wp_remote_post('https://api.indexnow.org/indexnow', [
            'timeout' => 5,
            'blocking' => false,
            'headers' => ['Content-Type' => 'application/json; charset=utf-8'],
            'body' => json_encode($payload)
        ]);
    }
}
