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
        $make = isset($data['make']) ? sanitize_text_field((string) $data['make']) : '';
        $model = isset($data['model']) ? sanitize_text_field((string) $data['model']) : '';
        $segment = isset($data['segment']) ? sanitize_text_field((string) $data['segment']) : '';
        $title = isset($data['title']) && (string) $data['title'] !== ''
            ? sanitize_text_field((string) $data['title'])
            : trim($make . ' ' . $model);

        if ($title === '') {
            return ['ok' => false, 'message' => 'Motorcycle payload missing make/model'];
        }

        $existingId = 0;
        if ($externalId !== '') {
            $existingId = $this->findByExternalId($externalId);
        }
        if ($existingId <= 0) {
            $existing = get_page_by_path(sanitize_title($title), OBJECT, 'motorcycle');
            if ($existing instanceof WP_Post) {
                $existingId = (int) $existing->ID;
            }
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
            'post_type'   => 'motorcycle',
            'post_title'  => $title,
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
        update_post_meta($postId, '_source_hash', $payloadHash);
        if ($externalId !== '') {
            update_post_meta($postId, '_motorcycle_unique_id', $externalId);
        }
        if ($sourceFile !== '') {
            update_post_meta($postId, '_source_file', $sourceFile);
        }

        if ($make !== '') {
            update_post_meta($postId, 'motorcycle_make', $make);
        }
        if ($model !== '') {
            update_post_meta($postId, 'motorcycle_model', $model);
        }
        if ($segment !== '') {
            update_post_meta($postId, 'bike_segment', $segment);
        }
        if (isset($data['engine_cc'])) {
            update_post_meta($postId, 'engine_cc', (float) $data['engine_cc']);
        }
        $this->setJsonMeta($postId, 'recommended_helmet_types_json', $data['recommended_helmet_types'] ?? null);

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

    private function findByExternalId(string $externalId): int
    {
        $posts = get_posts([
            'post_type'   => 'motorcycle',
            'post_status' => 'any',
            'numberposts' => 1,
            'meta_key'    => '_motorcycle_unique_id',
            'meta_value'  => $externalId,
            'fields'      => 'ids',
        ]);

        if (! is_array($posts) || $posts === []) {
            return 0;
        }

        return (int) $posts[0];
    }
}
