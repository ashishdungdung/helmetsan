<?php

declare(strict_types=1);

namespace Helmetsan\Core\Core;

final class DatabaseManager
{
    public function ensureTables(): void
    {
        $this->ensureHelmetIndexTable();
    }

    private function ensureHelmetIndexTable(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'helmetsan_helmet_index';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            post_id bigint(20) unsigned NOT NULL,
            helmet_type varchar(100) DEFAULT '',
            brand_id bigint(20) unsigned DEFAULT 0,
            price_inr decimal(10,2) DEFAULT NULL,
            price_usd decimal(10,2) DEFAULT NULL,
            weight_g int(11) DEFAULT NULL,
            certifications text,
            features text,
            updated_at datetime NOT NULL,
            PRIMARY KEY (post_id),
            KEY helmet_type (helmet_type),
            KEY brand_id (brand_id),
            KEY price_inr (price_inr),
            KEY updated_at (updated_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function register(): void
    {
        add_action('save_post_helmet', [$this, 'indexHelmet']);
    }

    public function indexHelmet(int $postId): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'helmetsan_helmet_index';
        $post = get_post($postId);

        if (!$post || $post->post_type !== 'helmet' || $post->post_status !== 'publish') {
            $wpdb->delete($table, ['post_id' => $postId]);
            return;
        }

        $helmetType = '';
        $terms = wp_get_post_terms($postId, 'helmet_type', ['fields' => 'names']);
        if (!is_wp_error($terms) && !empty($terms)) {
            $helmetType = $terms[0];
        }

        $brandId = (int) get_post_meta($postId, 'rel_brand', true);
        
        $priceInr = get_post_meta($postId, 'price_inr', true);
        $priceInr = is_numeric($priceInr) ? (float) $priceInr : null;

        $priceUsd = get_post_meta($postId, 'price_usd', true);
        $priceUsd = is_numeric($priceUsd) ? (float) $priceUsd : null;

        $weight = get_post_meta($postId, 'spec_weight_g', true);
        $weight = is_numeric($weight) ? (int) $weight : null;

        $certs = wp_get_post_terms($postId, 'certification', ['fields' => 'names']);
        $certStr = (!is_wp_error($certs) && !empty($certs)) ? implode(', ', $certs) : '';

        $features = wp_get_post_terms($postId, 'feature_tag', ['fields' => 'names']);
        $featStr = (!is_wp_error($features) && !empty($features)) ? implode(', ', $features) : '';

        $wpdb->replace(
            $table,
            [
                'post_id' => $postId,
                'helmet_type' => $helmetType,
                'brand_id' => $brandId,
                'price_inr' => $priceInr,
                'price_usd' => $priceUsd,
                'weight_g' => $weight,
                'certifications' => $certStr,
                'features' => $featStr,
                'updated_at' => current_time('mysql'),
            ],
            [
                '%d', '%s', '%d', '%f', '%f', '%d', '%s', '%s', '%s'
            ]
        );
    }
}
