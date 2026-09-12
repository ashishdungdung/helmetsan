<?php

declare(strict_types=1);

namespace Helmetsan\Core\Core;

final class DatabaseManager
{
    public function ensureTables(): void
    {
        $this->ensureHelmetIndexTable();
        $this->ensureHealsTable();
        $this->ensureApiKeysTable();
        $this->ensureSlugRedirectsTable();
    }

    private function ensureHealsTable(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'helmetsan_heals';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            entity_type varchar(100) NOT NULL,
            item_id varchar(255) NOT NULL,
            file_path text NOT NULL,
            issues text NOT NULL,
            fix_patch longtext NOT NULL,
            original_values longtext NULL,
            ai_mode varchar(50) NOT NULL DEFAULT 'local',
            applied tinyint(1) NOT NULL DEFAULT 0,
            reverted tinyint(1) NOT NULL DEFAULT 0,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY entity_type (entity_type),
            KEY item_id (item_id),
            KEY created_at (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    private function ensureHelmetIndexTable(): void
    {
        global $wpdb;

        $table = $wpdb->prefix . 'helmetsan_product_index';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            post_id bigint(20) unsigned NOT NULL,
            post_type varchar(20) NOT NULL DEFAULT 'helmet',
            category varchar(100) DEFAULT '',
            brand_id bigint(20) unsigned DEFAULT 0,
            price_inr decimal(10,2) DEFAULT NULL,
            price_usd decimal(10,2) DEFAULT NULL,
            weight_g int(11) DEFAULT NULL,
            sharp_rating int(1) DEFAULT 0,
            homologation varchar(100) DEFAULT '',
            noise_db decimal(5,2) DEFAULT NULL,
            ventilation_score int(2) DEFAULT 0,
            strap_type varchar(100) DEFAULT '',
            comms_ready varchar(100) DEFAULT '',
            is_pinlock tinyint(1) DEFAULT 0,
            is_electric tinyint(1) DEFAULT 0,
            is_snow tinyint(1) DEFAULT 0,
            color varchar(50) DEFAULT '',
            certifications text,
            features text,
            user_rating decimal(3,2) DEFAULT 0,
            review_count int(11) DEFAULT 0,
            updated_at datetime NOT NULL,
            PRIMARY KEY (post_id),
            KEY post_type (post_type),
            KEY category (category),
            KEY brand_id (brand_id),
            KEY price_usd (price_usd),
            KEY weight_g (weight_g),
            KEY brand_price (brand_id, price_usd),
            KEY sharp_rating (sharp_rating),
            KEY user_rating (user_rating),
            KEY updated_at (updated_at)
        ) {$charset};";

        $tableReviews = $wpdb->prefix . 'helmetsan_reviews';
        $sqlReviews = "CREATE TABLE {$tableReviews} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            post_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned DEFAULT 0,
            author_name varchar(100) DEFAULT '',
            author_email varchar(100) DEFAULT '',
            rating tinyint(1) NOT NULL DEFAULT 0,
            content text,
            pros text,
            cons text,
            helpful_votes int(11) DEFAULT 0,
            unhelpful_votes int(11) DEFAULT 0,
            status varchar(20) DEFAULT 'pending',
            country_code varchar(10) DEFAULT '',
            created_at datetime NOT NULL,
            updated_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY post_id (post_id),
            KEY rating (rating),
            KEY status (status),
            KEY created_at (created_at)
        ) {$charset};";

        $tableVotes = $wpdb->prefix . 'helmetsan_review_votes';
        $sqlVotes = "CREATE TABLE {$tableVotes} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            review_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned DEFAULT 0,
            ip_address varchar(45) DEFAULT '',
            vote_type varchar(10) DEFAULT 'helpful',
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY review_id (review_id),
            KEY ip_address (ip_address)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
        dbDelta($sqlReviews);
        dbDelta($sqlVotes);
    }

    public function register(): void
    {
        add_action('save_post_helmet', [$this, 'indexProduct']);
        add_action('save_post_accessory', [$this, 'indexProduct']);
        add_action('wp_trash_post', [$this, 'removeProductFromIndex']);
        add_action('deleted_post', [$this, 'removeProductFromIndex']);
    }

    public function removeProductFromIndex(int $postId): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'helmetsan_product_index';
        $wpdb->delete($table, ['post_id' => $postId]);
    }

    public function indexProduct(int $postId): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        global $wpdb;
        $table = $wpdb->prefix . 'helmetsan_product_index';
        $post = get_post($postId);

        if (!$post || !in_array($post->post_type, ['helmet', 'accessory'], true) || $post->post_status !== 'publish') {
            $wpdb->delete($table, ['post_id' => $postId]);
            return;
        }

        $category = '';
        if ($post->post_type === 'helmet') {
            $terms = wp_get_post_terms($postId, 'helmet_type', ['fields' => 'names']);
            $category = (!is_wp_error($terms) && !empty($terms)) ? $terms[0] : '';
        } else {
            $category = (string) get_post_meta($postId, 'accessory_type', true);
        }

        $brandId = (int) get_post_meta($postId, 'rel_brand', true);
        
        $priceInr = get_post_meta($postId, 'price_retail_inr', true);
        if (!is_numeric($priceInr)) {
            $priceInr = get_post_meta($postId, 'price_inr', true);
        }
        $priceInr = is_numeric($priceInr) ? (float) $priceInr : null;

        $priceUsd = get_post_meta($postId, 'price_retail_usd', true);
        if (!is_numeric($priceUsd)) {
            $priceUsd = get_post_meta($postId, 'price_usd', true);
        }
        $priceUsd = is_numeric($priceUsd) ? (float) $priceUsd : null;

        // Helmet Specifics
        $weight = ($post->post_type === 'helmet') ? get_post_meta($postId, 'spec_weight_g', true) : null;
        $weight = is_numeric($weight) ? (int) $weight : null;

        $sharp = ($post->post_type === 'helmet') ? get_post_meta($postId, 'sharp_rating', true) : 0;
        $sharp = is_numeric($sharp) ? (int) $sharp : 0;

        $homologation = (string) get_post_meta($postId, 'homologation_standard', true);
        
        $noise = ($post->post_type === 'helmet') ? get_post_meta($postId, 'noise_db_at_100kph', true) : null;
        $noise = is_numeric($noise) ? (float) $noise : null;

        $ventilation = ($post->post_type === 'helmet') ? get_post_meta($postId, 'ventilation_score', true) : 0;
        $ventilation = is_numeric($ventilation) ? (int) $ventilation : 0;

        $strap = (string) get_post_meta($postId, 'strap_type', true);
        $comms = (string) get_post_meta($postId, 'comms_ready', true);

        // Accessory Specifics
        $isPinlock = (string) get_post_meta($postId, 'accessory_pinlock_ready', true) === 'yes' ? 1 : 0;
        $isElectric = (string) get_post_meta($postId, 'accessory_electric_compatible', true) === 'yes' ? 1 : 0;
        $isSnow = (string) get_post_meta($postId, 'accessory_snow_compatible', true) === 'yes' ? 1 : 0;
        $color = (string) get_post_meta($postId, 'accessory_color', true);

        $certs = wp_get_post_terms($postId, 'certification', ['fields' => 'names']);
        $certStr = (!is_wp_error($certs) && !empty($certs)) ? implode(', ', $certs) : '';

        $features = wp_get_post_terms($postId, 'feature_tag', ['fields' => 'names']);
        $featStr = (!is_wp_error($features) && !empty($features)) ? implode(', ', $features) : '';

        $userRating = get_post_meta($postId, '_wc_average_rating', true);
        $userRating = is_numeric($userRating) ? (float) $userRating : 0.0;

        $reviewCount = get_post_meta($postId, '_wc_review_count', true);
        $reviewCount = is_numeric($reviewCount) ? (int) $reviewCount : 0;

        $wpdb->replace(
            $table,
            [
                'post_id' => $postId,
                'post_type' => $post->post_type,
                'category' => $category,
                'brand_id' => $brandId,
                'price_inr' => $priceInr,
                'price_usd' => $priceUsd,
                'weight_g' => $weight,
                'sharp_rating' => $sharp,
                'homologation' => $homologation,
                'noise_db' => $noise,
                'ventilation_score' => $ventilation,
                'strap_type' => $strap,
                'comms_ready' => $comms,
                'is_pinlock' => $isPinlock,
                'is_electric' => $isElectric,
                'is_snow' => $isSnow,
                'color' => $color,
                'certifications' => $certStr,
                'features' => $featStr,
                'user_rating' => $userRating,
                'review_count' => $reviewCount,
                'updated_at' => current_time('mysql'),
            ],
            [
                '%d', '%s', '%s', '%d', '%f', '%f', '%d', '%d', '%s', '%f', '%d', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%f', '%d', '%s'
            ]
        );
    }

    /**
     * Get filter metadata (min/max price, available categories, etc.) for a set of IDs.
     * This allows for high-performance dynamic sidebar filters.
     */
    public function getFilterMetadata(array $postIds): array
    {
        if (empty($postIds)) {
            return [];
        }

        global $wpdb;
        $table = $wpdb->prefix . 'helmetsan_product_index';
        $ids = implode(',', array_map('intval', $postIds));

        $stats = $wpdb->get_row("SELECT MIN(price_usd) as min_price, MAX(price_usd) as max_price FROM {$table} WHERE post_id IN ({$ids})");
        
        $categories = $wpdb->get_results("SELECT category, COUNT(*) as count FROM {$table} WHERE post_id IN ({$ids}) AND category != '' GROUP BY category ORDER BY count DESC");
        
        $brands = $wpdb->get_results("
            SELECT b.post_title as name, b.post_name as slug, COUNT(*) as count 
            FROM {$table} idx 
            JOIN {$wpdb->posts} b ON idx.brand_id = b.ID 
            WHERE idx.post_id IN ({$ids}) 
            GROUP BY idx.brand_id 
            ORDER BY count DESC
        ");

        return [
            'price' => [
                'min' => (float) ($stats->min_price ?? 0),
                'max' => (float) ($stats->max_price ?? 0),
            ],
            'categories' => $categories,
            'brands' => $brands,
        ];
    }

    /**
     * Ensure the API keys table exists for the tiered Data API gateway.
     */
    private function ensureApiKeysTable(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'helmetsan_api_keys';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            api_key varchar(64) NOT NULL,
            key_prefix varchar(12) NOT NULL,
            label varchar(255) DEFAULT '',
            tier varchar(20) NOT NULL DEFAULT 'registered',
            daily_limit int(11) NOT NULL DEFAULT 500,
            owner_email varchar(255) DEFAULT '',
            is_active tinyint(1) NOT NULL DEFAULT 1,
            created_at datetime NOT NULL,
            last_used datetime DEFAULT NULL,
            total_requests bigint(20) unsigned DEFAULT 0,
            PRIMARY KEY (id),
            UNIQUE KEY api_key (api_key),
            KEY key_prefix (key_prefix),
            KEY tier (tier),
            KEY is_active (is_active)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function ensureSlugRedirectsTable(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . 'helmetsan_slug_redirects';
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            source_slug varchar(200) NOT NULL,
            post_type varchar(50) NOT NULL DEFAULT 'helmet',
            target_url varchar(255) NOT NULL,
            redirect_status smallint(5) unsigned NOT NULL DEFAULT 301,
            hit_count bigint(20) unsigned NOT NULL DEFAULT 0,
            last_accessed datetime DEFAULT NULL,
            created_at datetime NOT NULL,
            PRIMARY KEY (id),
            UNIQUE KEY source_slug (source_slug),
            KEY post_type (post_type)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }
}
