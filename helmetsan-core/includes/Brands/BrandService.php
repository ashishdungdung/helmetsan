<?php

declare(strict_types=1);

namespace Helmetsan\Core\Brands;

use Helmetsan\Core\Support\HelmetTypeNormalizer;
use WP_Post;

final class BrandService
{
    public const META_ORIGIN_COUNTRY = 'brand_origin_country';
    public const META_WARRANTY_TERMS = 'brand_warranty_terms';
    public const META_SUPPORT_URL = 'brand_support_url';
    public const META_SUPPORT_EMAIL = 'brand_support_email';
    public const META_MANUFACTURING_ETHOS = 'brand_manufacturing_ethos';
    public const META_DISTRIBUTOR_REGIONS = 'brand_distributor_regions';
    public const META_SIZE_CHART_JSON = 'brand_size_chart_json';
    public const META_TOTAL_MODELS = 'brand_total_models';
    public const META_HELMET_TYPES = 'brand_helmet_types';
    public const META_CERT_COVERAGE = 'brand_certification_coverage';
    public const META_HELMET_TYPES_JSON = 'brand_helmet_types_json';
    public const META_MOTTO = 'brand_motto';
    public const META_STORY = 'brand_story';
    public const META_FOUNDED_YEAR = 'brand_founded_year';

    /**
     * @return array<string,string>
     */
    public function fieldMap(): array
    {
        return [
            self::META_ORIGIN_COUNTRY => 'Origin Country',
            self::META_WARRANTY_TERMS => 'Warranty Terms',
            self::META_SUPPORT_URL => 'Global Support URL',
            self::META_SUPPORT_EMAIL => 'Support Email',
            self::META_MANUFACTURING_ETHOS => 'Manufacturing Ethos',
            self::META_DISTRIBUTOR_REGIONS => 'Distributor Regions (one per line)',
            self::META_SIZE_CHART_JSON => 'Size Chart JSON',
            self::META_TOTAL_MODELS => 'Total Helmet Models (approx)',
            self::META_HELMET_TYPES => 'Helmet Types (comma separated)',
            self::META_HELMET_TYPES_JSON => 'Helmet Types JSON (read-only sync cache)',
            self::META_CERT_COVERAGE => 'Certification Coverage (comma separated)',
            self::META_MOTTO => 'Brand Motto',
            self::META_STORY => 'Brand Story / History',
            self::META_FOUNDED_YEAR => 'Founded Year',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function upsertFromPayload(array $data, string $sourceFile = '', bool $dryRun = false): array
    {
        $idRaw = isset($data['id']) ? sanitize_title((string) $data['id']) : '';
        $title = isset($data['title']) && is_string($data['title']) && $data['title'] !== ''
            ? sanitize_text_field($data['title'])
            : '';
        if ($title === '') {
            return ['ok' => false, 'message' => 'Brand payload missing title'];
        }

        $existingId = 0;
        if ($idRaw !== '') {
            $existingId = $this->findByExternalId($idRaw);
        }
        if ($existingId <= 0) {
            $existing = get_page_by_path(sanitize_title($title), OBJECT, 'brand');
            if ($existing instanceof WP_Post) {
                $existingId = (int) $existing->ID;
            }
        }

        $hashPayload = wp_json_encode($data);
        $payloadHash = hash('sha256', is_string($hashPayload) ? $hashPayload : serialize($data));
        if ($existingId > 0) {
            $oldHash = (string) get_post_meta($existingId, '_source_hash', true);
            if ($oldHash !== '' && hash_equals($oldHash, $payloadHash)) {
                return ['ok' => true, 'action' => 'skipped', 'post_id' => $existingId, 'external_id' => $idRaw];
            }
        }

        if ($dryRun) {
            return ['ok' => true, 'action' => 'dry-run', 'post_id' => $existingId, 'external_id' => $idRaw];
        }

        $postArgs = [
            'post_type' => 'brand',
            'post_title' => $title,
            'post_status' => 'publish',
        ];
        if ($existingId > 0) {
            $postArgs['ID'] = $existingId;
            $result = wp_update_post($postArgs, true);
            $action = 'updated';
        } else {
            $result = wp_insert_post($postArgs, true);
            $action = 'created';
        }

        if (is_wp_error($result)) {
            return ['ok' => false, 'message' => $result->get_error_message()];
        }

        $postId = (int) $result;
        $profile = isset($data['profile']) && is_array($data['profile']) ? $data['profile'] : [];

        $this->writeBrandProfileFromArray($postId, $profile);
        update_post_meta($postId, '_source_hash', $payloadHash);
        if ($sourceFile !== '') {
            update_post_meta($postId, '_source_file', $sourceFile);
        }
        if ($idRaw !== '') {
            update_post_meta($postId, '_brand_unique_id', $idRaw);
        }

        return [
            'ok' => true,
            'action' => $action,
            'post_id' => $postId,
            'external_id' => $idRaw,
        ];
    }

    public function register(): void
    {
        add_action('add_meta_boxes_brand', [$this, 'registerMetaBox']);
        add_action('save_post_brand', [$this, 'saveBrandMeta'], 10, 2);
    }

    public function registerMetaBox(): void
    {
        add_meta_box(
            'helmetsan_brand_profile',
            'Brand Profile',
            [$this, 'renderMetaBox'],
            'brand',
            'normal',
            'default'
        );
    }

    public function renderMetaBox(WP_Post $post): void
    {
        wp_nonce_field('helmetsan_brand_meta', 'helmetsan_brand_meta_nonce');
        echo '<table class="form-table"><tbody>';
        foreach ($this->fieldMap() as $key => $label) {
            $value = (string) get_post_meta($post->ID, $key, true);
            echo '<tr><th><label for="' . esc_attr($key) . '">' . esc_html($label) . '</label></th><td>';
            if ($key === self::META_MANUFACTURING_ETHOS || $key === self::META_DISTRIBUTOR_REGIONS || $key === self::META_SIZE_CHART_JSON || $key === self::META_STORY) {
                echo '<textarea id="' . esc_attr($key) . '" name="helmetsan_brand[' . esc_attr($key) . ']" rows="4" class="large-text">' . esc_textarea($value) . '</textarea>';
            } else {
                $type = ($key === self::META_SUPPORT_EMAIL) ? 'email' : (($key === self::META_SUPPORT_URL) ? 'url' : 'text');
                echo '<input type="' . esc_attr($type) . '" id="' . esc_attr($key) . '" name="helmetsan_brand[' . esc_attr($key) . ']" value="' . esc_attr($value) . '" class="regular-text" />';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';
    }

    public function saveBrandMeta(int $postId, WP_Post $post): void
    {
        if ($post->post_type !== 'brand') {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }
        if (! current_user_can('edit_post', $postId)) {
            return;
        }
        $nonce = isset($_POST['helmetsan_brand_meta_nonce']) ? (string) $_POST['helmetsan_brand_meta_nonce'] : '';
        if (! wp_verify_nonce($nonce, 'helmetsan_brand_meta')) {
            return;
        }
        $input = isset($_POST['helmetsan_brand']) && is_array($_POST['helmetsan_brand']) ? $_POST['helmetsan_brand'] : [];

        foreach ($this->fieldMap() as $key => $label) {
            if ($key === self::META_HELMET_TYPES_JSON) {
                continue;
            }
            $raw = isset($input[$key]) ? (string) $input[$key] : '';
            $value = match ($key) {
                self::META_SUPPORT_URL => esc_url_raw($raw),
                self::META_SUPPORT_EMAIL => sanitize_email($raw),
                default => sanitize_textarea_field($raw),
            };
            update_post_meta($postId, $key, $value);
        }
        $this->syncBrandHelmetTypeTerms($postId, (string) get_post_meta($postId, self::META_HELMET_TYPES, true));
    }

    /**
     * @return array<string,mixed>
     */
    public function exportPayloadByPostId(int $postId): array
    {
        $post = get_post($postId);
        if (! ($post instanceof WP_Post) || $post->post_type !== 'brand') {
            return ['ok' => false, 'message' => 'Brand post not found'];
        }

        $externalId = (string) get_post_meta($postId, '_brand_unique_id', true);
        if ($externalId === '') {
            $externalId = sanitize_title($post->post_title) . '-' . (string) $postId;
        }

        return [
            'ok' => true,
            'payload' => [
                'entity' => 'brand',
                'id' => $externalId,
                'title' => (string) $post->post_title,
                'profile' => [
                    'origin_country' => (string) get_post_meta($postId, self::META_ORIGIN_COUNTRY, true),
                    'warranty_terms' => (string) get_post_meta($postId, self::META_WARRANTY_TERMS, true),
                    'support_url' => (string) get_post_meta($postId, self::META_SUPPORT_URL, true),
                    'support_email' => (string) get_post_meta($postId, self::META_SUPPORT_EMAIL, true),
                    'manufacturing_ethos' => (string) get_post_meta($postId, self::META_MANUFACTURING_ETHOS, true),
                    'distributor_regions' => (string) get_post_meta($postId, self::META_DISTRIBUTOR_REGIONS, true),
                    'size_chart_json' => (string) get_post_meta($postId, self::META_SIZE_CHART_JSON, true),
                    'total_models' => (string) get_post_meta($postId, self::META_TOTAL_MODELS, true),
                    'helmet_types' => (string) get_post_meta($postId, self::META_HELMET_TYPES, true),
                    'certification_coverage' => (string) get_post_meta($postId, self::META_CERT_COVERAGE, true),
                    'motto' => (string) get_post_meta($postId, self::META_MOTTO, true),
                    'story' => (string) get_post_meta($postId, self::META_STORY, true),
                    'founded_year' => (string) get_post_meta($postId, self::META_FOUNDED_YEAR, true),
                ],
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function cascadeToHelmets(int $brandId, string $source = 'manual'): array
    {
        $brand = get_post($brandId);
        if (! ($brand instanceof WP_Post) || $brand->post_type !== 'brand') {
            return ['ok' => false, 'message' => 'Invalid brand id'];
        }

        $fields = [
            'brand_name_cached' => $brand->post_title,
            self::META_ORIGIN_COUNTRY => (string) get_post_meta($brandId, self::META_ORIGIN_COUNTRY, true),
            self::META_WARRANTY_TERMS => (string) get_post_meta($brandId, self::META_WARRANTY_TERMS, true),
            self::META_SUPPORT_URL => (string) get_post_meta($brandId, self::META_SUPPORT_URL, true),
            self::META_SUPPORT_EMAIL => (string) get_post_meta($brandId, self::META_SUPPORT_EMAIL, true),
            self::META_MANUFACTURING_ETHOS => (string) get_post_meta($brandId, self::META_MANUFACTURING_ETHOS, true),
            self::META_DISTRIBUTOR_REGIONS => (string) get_post_meta($brandId, self::META_DISTRIBUTOR_REGIONS, true),
            self::META_SIZE_CHART_JSON => (string) get_post_meta($brandId, self::META_SIZE_CHART_JSON, true),
            self::META_TOTAL_MODELS => (string) get_post_meta($brandId, self::META_TOTAL_MODELS, true),
            self::META_HELMET_TYPES => (string) get_post_meta($brandId, self::META_HELMET_TYPES, true),
            self::META_CERT_COVERAGE => (string) get_post_meta($brandId, self::META_CERT_COVERAGE, true),
            self::META_MOTTO => (string) get_post_meta($brandId, self::META_MOTTO, true),
            self::META_STORY => (string) get_post_meta($brandId, self::META_STORY, true),
            self::META_FOUNDED_YEAR => (string) get_post_meta($brandId, self::META_FOUNDED_YEAR, true),
        ];

        $q = new \WP_Query([
            'post_type'      => 'helmet',
            'post_status'    => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'   => 'rel_brand',
                    'value' => $brandId,
                ],
            ],
        ]);

        $updated = 0;
        $ids = is_array($q->posts) ? $q->posts : [];

        foreach ($ids as $helmetId) {
            $helmetId = (int) $helmetId;
            foreach ($fields as $key => $value) {
                update_post_meta($helmetId, $key, $value);
            }
            update_post_meta($helmetId, 'brand_cascade_at', current_time('mysql'));
            update_post_meta($helmetId, 'brand_cascade_source', sanitize_text_field($source));
            $updated++;
        }

        wp_reset_postdata();

        return [
            'ok' => true,
            'brand_id' => $brandId,
            'brand' => $brand->post_title,
            'updated_helmets' => $updated,
        ];
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function listBrandOverview(): array
    {
        global $wpdb;

        $brands = get_posts([
            'post_type' => 'brand',
            'post_status' => ['publish', 'draft', 'pending', 'private'],
            'posts_per_page' => -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ]);

        $rows = [];
        foreach ($brands as $brand) {
            if (! ($brand instanceof WP_Post)) {
                continue;
            }
            $count = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT COUNT(*) FROM {$wpdb->postmeta} pm
                 INNER JOIN {$wpdb->posts} p ON p.ID = pm.post_id
                 WHERE pm.meta_key = 'rel_brand'
                   AND pm.meta_value = %d
                   AND p.post_type = 'helmet'
                   AND p.post_status IN ('publish','draft','pending','private')",
                $brand->ID
            ));

            $rows[] = [
                'id' => (int) $brand->ID,
                'title' => (string) $brand->post_title,
                'helmets' => $count,
                'warranty' => (string) get_post_meta($brand->ID, self::META_WARRANTY_TERMS, true),
                'support_url' => (string) get_post_meta($brand->ID, self::META_SUPPORT_URL, true),
                'origin_country' => (string) get_post_meta($brand->ID, self::META_ORIGIN_COUNTRY, true),
                'total_models' => (string) get_post_meta($brand->ID, self::META_TOTAL_MODELS, true),
                'helmet_types' => (string) get_post_meta($brand->ID, self::META_HELMET_TYPES, true),
                'certification_coverage' => (string) get_post_meta($brand->ID, self::META_CERT_COVERAGE, true),
            ];
        }

        return $rows;
    }

    /**
     * @param array<string,mixed> $profile
     */
    private function writeBrandProfileFromArray(int $postId, array $profile): void
    {
        $mapping = [
            'origin_country' => self::META_ORIGIN_COUNTRY,
            'warranty_terms' => self::META_WARRANTY_TERMS,
            'support_url' => self::META_SUPPORT_URL,
            'support_email' => self::META_SUPPORT_EMAIL,
            'manufacturing_ethos' => self::META_MANUFACTURING_ETHOS,
            'distributor_regions' => self::META_DISTRIBUTOR_REGIONS,
            'size_chart_json' => self::META_SIZE_CHART_JSON,
            'total_models' => self::META_TOTAL_MODELS,
            'helmet_types' => self::META_HELMET_TYPES,
            'certification_coverage' => self::META_CERT_COVERAGE,
            'motto' => self::META_MOTTO,
            'story' => self::META_STORY,
            'founded_year' => self::META_FOUNDED_YEAR,
        ];

        foreach ($mapping as $inputKey => $metaKey) {
            $raw = isset($profile[$inputKey]) ? (string) $profile[$inputKey] : '';
            $value = match ($metaKey) {
                self::META_SUPPORT_URL => esc_url_raw($raw),
                self::META_SUPPORT_EMAIL => sanitize_email($raw),
                default => sanitize_textarea_field($raw),
            };
            update_post_meta($postId, $metaKey, $value);
        }
        $this->syncBrandHelmetTypeTerms($postId, (string) get_post_meta($postId, self::META_HELMET_TYPES, true));
    }

    private function findByExternalId(string $externalId): int
    {
        $posts = get_posts([
            'post_type' => 'brand',
            'post_status' => 'any',
            'numberposts' => 1,
            'meta_key' => '_brand_unique_id',
            'meta_value' => $externalId,
            'fields' => 'ids',
        ]);

        if (! is_array($posts) || $posts === []) {
            return 0;
        }

        return (int) $posts[0];
    }

    /**
     * Sync brand helmet-type relationship to taxonomy + normalized JSON cache.
     */
    private function syncBrandHelmetTypeTerms(int $postId, string $rawTypes): void
    {
        $items = array_filter(array_map('trim', explode(',', $rawTypes)));
        $normalized = HelmetTypeNormalizer::normalizeArray($items);
        update_post_meta($postId, self::META_HELMET_TYPES_JSON, wp_json_encode($normalized, JSON_UNESCAPED_SLASHES));
        wp_set_object_terms($postId, $normalized, 'helmet_type', false);
    }

    // normalizeHelmetType() removed — use HelmetTypeNormalizer::toLabel() instead.
}
