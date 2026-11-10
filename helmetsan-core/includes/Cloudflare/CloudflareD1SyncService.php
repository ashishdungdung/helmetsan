<?php

declare(strict_types=1);

namespace Helmetsan\Core\Cloudflare;

/**
 * Enterprise Cloudflare D1 Serverless SQL Sync Service.
 *
 * Replicates the Helmetsan motorcycle catalog and taxonomy matrix to Cloudflare D1 SQLite at the edge.
 * Enables edge workers to execute sub-10ms localized queries, facet searches, and sitemap slicing
 * globally across Cloudflare's 330+ locations without origin roundtrips.
 */
final class CloudflareD1SyncService
{
    private const DEFAULT_ACCOUNT_ID = 'a06a4ae5652491dfb8376dc23488f6a0';
    private const DEFAULT_DATABASE_ID = 'fa1bce22-6e10-4d65-9795-a4f0ef5e0df6';

    /**
     * Register lifecycle hooks for real-time edge sync.
     */
    public static function register(): void
    {
        add_action('save_post_helmet', [self::class, 'onPostSaved'], 25, 2);
        add_action('wp_trash_post', [self::class, 'onPostDeleted'], 25, 1);
        add_action('before_delete_post', [self::class, 'onPostDeleted'], 25, 1);
        add_action('save_post_brand', [self::class, 'onBrandSaved'], 25, 2);
    }

    public static function onPostSaved(int $postId, ?\WP_Post $post = null): void
    {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (! $post || $post->post_status !== 'publish' || $post->post_type !== 'helmet') {
            self::deleteHelmet($postId);
            return;
        }

        self::syncHelmet($postId);
    }

    public static function onPostDeleted(int $postId): void
    {
        self::deleteHelmet($postId);
    }

    public static function onBrandSaved(int $postId, ?\WP_Post $post = null): void
    {
        if ($post && $post->post_status === 'publish' && $post->post_type === 'brand') {
            self::syncSingleBrand($postId);
        }
    }

    /**
     * Execute SQL query against Cloudflare D1 REST API.
     *
     * @param string $sql
     * @param list<mixed> $params
     * @return array{success: bool, results: list<mixed>, error: ?string}
     */
    public static function executeQuery(string $sql, array $params = []): array
    {
        $accountId = defined('HELMETSAN_CLOUDFLARE_ACCOUNT_ID') ? (string) HELMETSAN_CLOUDFLARE_ACCOUNT_ID : self::DEFAULT_ACCOUNT_ID;
        $dbId = defined('HELMETSAN_CLOUDFLARE_D1_DATABASE_ID') ? (string) HELMETSAN_CLOUDFLARE_D1_DATABASE_ID : self::DEFAULT_DATABASE_ID;
        $apiToken = defined('HELMETSAN_CLOUDFLARE_API_TOKEN') ? (string) HELMETSAN_CLOUDFLARE_API_TOKEN : '';

        if ($apiToken === '' || $accountId === '' || $dbId === '') {
            return ['success' => false, 'results' => [], 'error' => 'Missing Cloudflare D1 credentials'];
        }

        $url = "https://api.cloudflare.com/client/v4/accounts/{$accountId}/d1/database/{$dbId}/query";

        $body = ['sql' => $sql];
        if (! empty($params)) {
            $body['params'] = $params;
        }

        $response = wp_remote_post($url, [
            'timeout' => 15,
            'headers' => [
                'Authorization' => 'Bearer ' . $apiToken,
                'Content-Type'  => 'application/json',
            ],
            'body'    => (string) wp_json_encode($body),
        ]);

        if (is_wp_error($response)) {
            return ['success' => false, 'results' => [], 'error' => $response->get_error_message()];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $resBody = (string) wp_remote_retrieve_body($response);
        $data = json_decode($resBody, true);

        if ($code !== 200 || empty($data['success'])) {
            $msg = ! empty($data['errors'][0]['message']) ? $data['errors'][0]['message'] : "HTTP {$code}: {$resBody}";
            return ['success' => false, 'results' => [], 'error' => $msg];
        }

        $results = $data['result'][0]['results'] ?? [];
        return ['success' => true, 'results' => (array) $results, 'error' => null];
    }

    /**
     * Sync single helmet record into Cloudflare D1.
     */
    public static function syncHelmet(int $postId): bool
    {
        $post = get_post($postId);
        if (! $post || $post->post_status !== 'publish' || $post->post_type !== 'helmet') {
            return false;
        }

        $record = self::extractHelmetRecord($post);
        $sql = "INSERT INTO helmets (id, lang, slug, title, brand, helmet_type, certification, head_shape, weight_g, noise_db, price_usd, sharp_rating, canonical_url, image_url, updated_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP)
                ON CONFLICT(id) DO UPDATE SET
                    lang = excluded.lang,
                    slug = excluded.slug,
                    title = excluded.title,
                    brand = excluded.brand,
                    helmet_type = excluded.helmet_type,
                    certification = excluded.certification,
                    head_shape = excluded.head_shape,
                    weight_g = excluded.weight_g,
                    noise_db = excluded.noise_db,
                    price_usd = excluded.price_usd,
                    sharp_rating = excluded.sharp_rating,
                    canonical_url = excluded.canonical_url,
                    image_url = excluded.image_url,
                    updated_at = CURRENT_TIMESTAMP";

        $params = [
            $record['id'],
            $record['lang'],
            $record['slug'],
            $record['title'],
            $record['brand'],
            $record['helmet_type'],
            $record['certification'],
            $record['head_shape'],
            $record['weight_g'],
            $record['noise_db'],
            $record['price_usd'],
            $record['sharp_rating'],
            $record['canonical_url'],
            $record['image_url'],
        ];

        $res = self::executeQuery($sql, $params);
        return $res['success'];
    }

    /**
     * Delete helmet from Cloudflare D1.
     */
    public static function deleteHelmet(int $postId): bool
    {
        $res = self::executeQuery("DELETE FROM helmets WHERE id = ?", [$postId]);
        return $res['success'];
    }

    /**
     * Sync a batch of helmet posts into Cloudflare D1 SQLite.
     *
     * @param list<int> $postIds
     * @return int Count of successfully synced helmets
     */
    public static function syncBatch(array $postIds): int
    {
        if (empty($postIds)) {
            return 0;
        }

        $records = [];
        foreach ($postIds as $id) {
            $post = get_post($id);
            if ($post && $post->post_status === 'publish' && $post->post_type === 'helmet') {
                $records[] = self::extractHelmetRecord($post);
            }
        }

        if (empty($records)) {
            return 0;
        }

        // Format direct SQL literals to avoid Cloudflare D1 100-parameter limit
        $rows = [];
        foreach ($records as $r) {
            $id = (int) $r['id'];
            $lang = "'" . esc_sql((string) $r['lang']) . "'";
            $slug = "'" . esc_sql((string) $r['slug']) . "'";
            $title = "'" . esc_sql((string) $r['title']) . "'";
            $brand = "'" . esc_sql((string) $r['brand']) . "'";
            $type = "'" . esc_sql((string) $r['helmet_type']) . "'";
            $cert = "'" . esc_sql((string) $r['certification']) . "'";
            $shape = "'" . esc_sql((string) $r['head_shape']) . "'";
            $weight = (int) $r['weight_g'];
            $noise = (float) $r['noise_db'];
            $price = (float) $r['price_usd'];
            $sharp = (int) $r['sharp_rating'];
            $canon = "'" . esc_sql((string) $r['canonical_url']) . "'";
            $img = "'" . esc_sql((string) $r['image_url']) . "'";

            $rows[] = "({$id}, {$lang}, {$slug}, {$title}, {$brand}, {$type}, {$cert}, {$shape}, {$weight}, {$noise}, {$price}, {$sharp}, {$canon}, {$img}, CURRENT_TIMESTAMP)";
        }

        $sql = "INSERT INTO helmets (id, lang, slug, title, brand, helmet_type, certification, head_shape, weight_g, noise_db, price_usd, sharp_rating, canonical_url, image_url, updated_at)
                VALUES " . implode(', ', $rows) . "
                ON CONFLICT(id) DO UPDATE SET
                    lang = excluded.lang,
                    slug = excluded.slug,
                    title = excluded.title,
                    brand = excluded.brand,
                    helmet_type = excluded.helmet_type,
                    certification = excluded.certification,
                    head_shape = excluded.head_shape,
                    weight_g = excluded.weight_g,
                    noise_db = excluded.noise_db,
                    price_usd = excluded.price_usd,
                    sharp_rating = excluded.sharp_rating,
                    canonical_url = excluded.canonical_url,
                    image_url = excluded.image_url,
                    updated_at = CURRENT_TIMESTAMP";

        $res = self::executeQuery($sql);
        return $res['success'] ? count($records) : 0;
    }

    /**
     * Synchronize all brands into Cloudflare D1.
     */
    public static function syncBrands(): int
    {
        global $wpdb;
        $brands = $wpdb->get_results("SELECT ID, post_name, post_title FROM {$wpdb->posts} WHERE post_type = 'brand' AND post_status = 'publish'");

        if (empty($brands)) {
            return 0;
        }

        $count = 0;
        foreach (array_chunk($brands, 50) as $chunk) {
            $rows = [];
            foreach ($chunk as $b) {
                $postId = (int) $b->ID;
                $lang = "'" . esc_sql(self::resolveLanguageForPost($b)) . "'";
                $slug = "'" . esc_sql((string) $b->post_name) . "'";
                $name = "'" . esc_sql((string) $b->post_title) . "'";
                $country = "'" . esc_sql((string) get_post_meta($postId, 'brand_origin_country', true)) . "'";
                $url = "'" . esc_sql((string) get_permalink($postId)) . "'";

                $rows[] = "({$postId}, {$lang}, {$slug}, {$name}, {$country}, 0, {$url})";
            }

            $sql = "INSERT INTO brands (id, lang, slug, name, origin_country, helmet_count, canonical_url)
                    VALUES " . implode(', ', $rows) . "
                    ON CONFLICT(id) DO UPDATE SET
                        lang = excluded.lang,
                        slug = excluded.slug,
                        name = excluded.name,
                        origin_country = excluded.origin_country,
                        canonical_url = excluded.canonical_url";

            $res = self::executeQuery($sql);
            if ($res['success']) {
                $count += count($chunk);
            }
        }

        return $count;
    }

    public static function syncSingleBrand(int $postId): bool
    {
        $post = get_post($postId);
        if (! $post || $post->post_status !== 'publish' || $post->post_type !== 'brand') {
            return false;
        }

        $lang = self::resolveLanguageForPost($post);
        $country = (string) get_post_meta($postId, 'brand_origin_country', true);
        $url = (string) get_permalink($postId);

        $sql = "INSERT INTO brands (id, lang, slug, name, origin_country, canonical_url)
                VALUES (?, ?, ?, ?, ?, ?)
                ON CONFLICT(id) DO UPDATE SET
                    lang = excluded.lang,
                    slug = excluded.slug,
                    name = excluded.name,
                    origin_country = excluded.origin_country,
                    canonical_url = excluded.canonical_url";

        $res = self::executeQuery($sql, [$postId, $lang, $post->post_name, $post->post_title, $country, $url]);
        return $res['success'];
    }

    /**
     * @return array{id: int, lang: string, slug: string, title: string, brand: string, helmet_type: string, certification: string, head_shape: string, weight_g: int, noise_db: float, price_usd: float, sharp_rating: int, canonical_url: string, image_url: string}
     */
    private static function extractHelmetRecord(\WP_Post $post): array
    {
        $id = (int) $post->ID;
        $lang = self::resolveLanguageForPost($post);

        $brand = '';
        $terms = wp_get_post_terms($id, 'brand', ['fields' => 'slugs']);
        if (! empty($terms) && ! is_wp_error($terms)) {
            $brand = (string) $terms[0];
        } else {
            $rel = get_post_meta($id, 'rel_brand', true);
            if ($rel) {
                $bPost = get_post((int) $rel);
                if ($bPost) {
                    $brand = $bPost->post_name;
                }
            }
        }

        $type = '';
        $tTerms = wp_get_post_terms($id, 'helmet_type', ['fields' => 'slugs']);
        if (! empty($tTerms) && ! is_wp_error($tTerms)) {
            $type = (string) $tTerms[0];
        }

        $cert = '';
        $cTerms = wp_get_post_terms($id, 'safety_standard', ['fields' => 'slugs']);
        if (! empty($cTerms) && ! is_wp_error($cTerms)) {
            $cert = (string) $cTerms[0];
        }

        $thumbId = get_post_thumbnail_id($id);
        $imageUrl = $thumbId ? (string) wp_get_attachment_image_url($thumbId, 'large') : '';

        return [
            'id'            => $id,
            'lang'          => $lang,
            'slug'          => $post->post_name,
            'title'         => $post->post_title,
            'brand'         => $brand,
            'helmet_type'   => $type,
            'certification' => $cert,
            'head_shape'    => (string) get_post_meta($id, 'head_shape', true),
            'weight_g'      => (int) get_post_meta($id, 'spec_weight_g', true),
            'noise_db'      => (float) get_post_meta($id, 'noise_db_at_100kph', true),
            'price_usd'     => (float) get_post_meta($id, 'price_retail_usd', true),
            'sharp_rating'  => (int) get_post_meta($id, 'sharp_rating', true),
            'canonical_url' => (string) get_permalink($id),
            'image_url'     => $imageUrl,
        ];
    }

    private static function resolveLanguageForPost(object $post): string
    {
        $id = (int) $post->ID;
        if (function_exists('pll_get_post_language')) {
            $lang = (string) pll_get_post_language($id);
            if ($lang !== '') {
                return $lang;
            }
        }

        if (preg_match('/-([a-z]{2})$/', (string) $post->post_name, $m)) {
            return $m[1];
        }

        return 'en';
    }
}
