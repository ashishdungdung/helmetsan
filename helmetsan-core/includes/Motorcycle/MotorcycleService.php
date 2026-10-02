<?php

declare(strict_types=1);

namespace Helmetsan\Core\Motorcycle;

use WP_Post;

final class MotorcycleService
{
    private const NONCE_ACTION = 'helmetsan_motorcycle_meta';
    private const NONCE_FIELD  = '_helmetsan_motorcycle_nonce';

    /**
     * Register admin hooks for the meta box.
     */
    public function register(): void
    {
        add_action('add_meta_boxes_motorcycle', [$this, 'registerMetaBox']);
        add_action('save_post_motorcycle', [$this, 'saveMeta'], 10, 2);
    }

    public function registerMetaBox(): void
    {
        add_meta_box(
            'helmetsan_motorcycle_details',
            'Motorcycle Details',
            [$this, 'renderMetaBox'],
            'motorcycle',
            'normal',
            'high'
        );
    }

    public function renderMetaBox(WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        $fields = [
            'motorcycle_make'               => ['label' => 'Make',                          'type' => 'text'],
            'motorcycle_model'              => ['label' => 'Model',                         'type' => 'text'],
            'bike_segment'                  => ['label' => 'Segment',                       'type' => 'text',   'hint' => 'e.g. sport, adventure, cruiser, touring'],
            'engine_cc'                     => ['label' => 'Engine CC',                     'type' => 'number'],
            'recommended_helmet_types_json' => ['label' => 'Recommended Helmet Types (JSON)', 'type' => 'textarea', 'rows' => 3, 'hint' => 'e.g. ["Full Face","Adventure / Dual Sport"]'],
        ];

        echo '<table class="form-table" role="presentation"><tbody>';
        foreach ($fields as $key => $field) {
            $label = esc_html($field['label']);
            $value = (string) get_post_meta($post->ID, $key, true);
            $id    = esc_attr('helmetsan_' . $key);
            $name  = esc_attr($key);

            echo '<tr>';
            echo '<th scope="row"><label for="' . $id . '">' . $label . '</label></th>';
            echo '<td>';

            if ($field['type'] === 'textarea') {
                $rows = isset($field['rows']) ? (int) $field['rows'] : 3;
                echo '<textarea id="' . $id . '" name="' . $name . '" rows="' . $rows . '" class="large-text">' . esc_textarea($value) . '</textarea>';
            } else {
                $type = esc_attr($field['type']);
                echo '<input type="' . $type . '" id="' . $id . '" name="' . $name . '" value="' . esc_attr($value) . '" class="regular-text" />';
            }

            if (isset($field['hint'])) {
                echo '<p class="description">' . esc_html($field['hint']) . '</p>';
            }

            echo '</td></tr>';
        }
        echo '</tbody></table>';
    }

    public function saveMeta(int $postId, WP_Post $post): void
    {
        if (
            ! isset($_POST[self::NONCE_FIELD]) ||
            ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST[self::NONCE_FIELD])), self::NONCE_ACTION)
        ) {
            return;
        }

        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
            return;
        }

        if (! current_user_can('edit_post', $postId)) {
            return;
        }

        $textFields = ['motorcycle_make', 'motorcycle_model', 'bike_segment'];
        foreach ($textFields as $key) {
            if (isset($_POST[$key])) {
                update_post_meta($postId, $key, sanitize_text_field(wp_unslash((string) $_POST[$key])));
            }
        }

        if (isset($_POST['engine_cc'])) {
            update_post_meta($postId, 'engine_cc', (float) $_POST['engine_cc']);
        }

        if (isset($_POST['recommended_helmet_types_json'])) {
            update_post_meta($postId, 'recommended_helmet_types_json', sanitize_textarea_field(wp_unslash((string) $_POST['recommended_helmet_types_json'])));
        }
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function upsertFromPayload(array $data, string $sourceFile = '', bool $dryRun = false): array
    {
        $externalId = isset($data['id']) ? sanitize_title((string) $data['id']) : '';
        $make = isset($data['brand']) ? sanitize_text_field((string) $data['brand']) : (isset($data['make']) ? sanitize_text_field((string) $data['make']) : '');
        $model = isset($data['model']) ? sanitize_text_field((string) $data['model']) : '';
        $segment = isset($data['category']) ? sanitize_text_field((string) $data['category']) : (isset($data['segment']) ? sanitize_text_field((string) $data['segment']) : '');
        $title = isset($data['title']) && (string) $data['title'] !== ''
            ? sanitize_text_field((string) $data['title'])
            : trim($make . ' ' . $model);

        if ($title === '') {
            return ['ok' => false, 'message' => 'Motorcycle payload missing title or brand/model'];
        }

        $existingId = 0;
        if ($externalId !== '') {
            $existingId = $this->findByExternalId($externalId);
        }
        if ($existingId <= 0) {
            global $wpdb;
            $slug = sanitize_title($title);
            $existingId = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT ID FROM {$wpdb->posts} WHERE post_name = %s AND post_type = 'motorcycle' AND post_status != 'trash' LIMIT 1",
                $slug
            ));
        }

        $hashPayload = wp_json_encode($data);
        $payloadHash = hash('sha256', is_string($hashPayload) ? $hashPayload : serialize($data));
        if ($existingId > 0) {
            $oldHash = (string) get_post_meta($existingId, '_source_hash', true);
            if ($oldHash !== '' && hash_equals($oldHash, $payloadHash)) {
                return ['ok' => true, 'action' => 'skipped', 'post_id' => $existingId];
            }
        }

        if ($dryRun) {
            return ['ok' => true, 'action' => 'dry-run', 'post_id' => $existingId];
        }

        $postArgs = [
            'post_type'    => 'motorcycle',
            'post_title'   => $title,
            'post_name'    => $externalId !== '' ? $externalId : sanitize_title($title),
            'post_content' => isset($data['description']) ? wp_kses_post((string) $data['description']) : '',
            'post_status'  => 'publish',
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
        update_post_meta($postId, '_source_hash', $payloadHash);
        if ($externalId !== '') {
            update_post_meta($postId, '_motorcycle_unique_id', $externalId);
        }
        if ($sourceFile !== '') {
            update_post_meta($postId, '_source_file', $sourceFile);
        }

        if ($make !== '') {
            update_post_meta($postId, 'motorcycle_make', $make);
            update_post_meta($postId, 'brand', $make);
        }
        if ($model !== '') {
            update_post_meta($postId, 'motorcycle_model', $model);
        }
        if ($segment !== '') {
            update_post_meta($postId, 'bike_segment', $segment);
            update_post_meta($postId, 'category', $segment);
        }
        $engineCc = $data['displacement_cc'] ?? $data['engine_cc'] ?? null;
        if ($engineCc !== null) {
            update_post_meta($postId, 'engine_cc', (float) $engineCc);
            update_post_meta($postId, 'displacement_cc', (float) $engineCc);
        }
        if (isset($data['power_hp'])) {
            update_post_meta($postId, 'power_hp', (float) $data['power_hp']);
        }
        if (isset($data['torque_nm'])) {
            update_post_meta($postId, 'torque_nm', (float) $data['torque_nm']);
        }
        if (isset($data['curb_weight_kg'])) {
            update_post_meta($postId, 'curb_weight_kg', (float) $data['curb_weight_kg']);
        }
        if (isset($data['riding_position'])) {
            update_post_meta($postId, 'riding_position', sanitize_text_field((string) $data['riding_position']));
        }
        if (isset($data['price']) && is_array($data['price'])) {
            update_post_meta($postId, 'price_usd', (float) ($data['price']['usd'] ?? 0));
            update_post_meta($postId, 'price_inr', (float) ($data['price']['inr'] ?? 0));
            $this->setJsonMeta($postId, 'pricing_json', $data['price']);
        }
        if (isset($data['yoast_title'])) {
            update_post_meta($postId, '_yoast_wpseo_title', sanitize_text_field((string) $data['yoast_title']));
        }
        if (isset($data['yoast_metadesc'])) {
            update_post_meta($postId, '_yoast_wpseo_metadesc', sanitize_text_field((string) $data['yoast_metadesc']));
        }

        $this->setJsonMeta($postId, 'recommended_helmet_types_json', $data['recommended_helmet_types'] ?? null);

        if ($segment !== '') {
            wp_set_object_terms($postId, [$segment], 'motorcycle_segment', false);
        }
        if ($make !== '') {
            wp_set_object_terms($postId, [$make], 'motorcycle_make', false);
        }

        if (isset($data['regions']) && is_array($data['regions'])) {
            $terms = array_filter(array_map(
                static fn($item): string => sanitize_text_field((string) $item),
                $data['regions']
            ));
            if ($terms !== []) {
                wp_set_object_terms($postId, array_values($terms), 'region', false);
            }
        }

        return ['ok' => true, 'action' => $action, 'post_id' => $postId];
    }

    private function setJsonMeta(int $postId, string $metaKey, mixed $value): void
    {
        if ($value === null) {
            return;
        }
        $json = wp_json_encode($value, JSON_UNESCAPED_SLASHES);
        if (is_string($json) && $json !== '') {
            update_post_meta($postId, $metaKey, $json);
        }
    }

    /** @var array<string,int> */
    private static array $lookupCache = [];

    /**
     * Pre-warm lookup cache for a batch of external IDs to eliminate N SQL queries during bulk ingest.
     *
     * @param list<string> $externalIds
     */
    public function warmLookupCache(array $externalIds): void
    {
        global $wpdb;
        $slugs = [];
        $map = [];
        foreach ($externalIds as $eid) {
            $eid = trim((string) $eid);
            if ($eid === '' || array_key_exists($eid, self::$lookupCache)) {
                continue;
            }
            $slug = sanitize_title($eid);
            $slugs[] = $slug;
            $map[$slug] = $eid;
        }
        if ($slugs === []) {
            return;
        }
        $placeholders = implode(',', array_fill(0, count($slugs), '%s'));
        $sql = $wpdb->prepare(
            "SELECT ID, post_name FROM {$wpdb->posts} WHERE post_type = 'motorcycle' AND post_name IN ($placeholders) AND post_status NOT IN ('trash', 'auto-draft')",
            ...$slugs
        );
        $results = $wpdb->get_results($sql, ARRAY_A);
        if (is_array($results)) {
            foreach ($results as $row) {
                $pName = (string) ($row['post_name'] ?? '');
                if (isset($map[$pName])) {
                    self::$lookupCache[$map[$pName]] = (int) $row['ID'];
                }
            }
        }
    }

    public function findByExternalId(string $externalId): int
    {
        $externalId = trim($externalId);
        if ($externalId === '') {
            return 0;
        }

        if (array_key_exists($externalId, self::$lookupCache)) {
            return self::$lookupCache[$externalId];
        }

        global $wpdb;
        $slug = sanitize_title($externalId);

        // 1. Primary path: indexed post_name on wp_posts
        $id = (int) $wpdb->get_var($wpdb->prepare(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'motorcycle' AND post_name = %s AND post_status NOT IN ('trash', 'auto-draft') ORDER BY ID ASC LIMIT 1",
            $slug
        ));

        // 2. Compatibility path for legacy items created before slug unification
        if ($id <= 0) {
            $id = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_motorcycle_unique_id' AND meta_value = %s ORDER BY post_id ASC LIMIT 1",
                $externalId
            ));
        }

        // Cache both hits and misses to prevent repeating redundant scans
        self::$lookupCache[$externalId] = $id;

        return $id;
    }
}
