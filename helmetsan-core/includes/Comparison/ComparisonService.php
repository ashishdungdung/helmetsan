<?php

declare(strict_types=1);

namespace Helmetsan\Core\Comparison;

use WP_Post;

final class ComparisonService
{
    private const NONCE_ACTION = 'helmetsan_comparison_meta';
    private const NONCE_FIELD  = '_helmetsan_comparison_nonce';

    public function register(): void
    {
        add_action('add_meta_boxes_comparison', [$this, 'registerMetaBox']);
        add_action('save_post_comparison', [$this, 'saveMeta'], 10, 2);
    }

    public function registerMetaBox(): void
    {
        add_meta_box(
            'helmetsan_comparison_details',
            'Comparison Details',
            [$this, 'renderMetaBox'],
            'comparison',
            'normal',
            'high'
        );
    }

    public function renderMetaBox(WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        $relHelmets = get_post_meta($post->ID, 'rel_helmets', true);
        $relHelmetsDisplay = '';
        if (is_array($relHelmets) && $relHelmets !== []) {
            $relHelmetsDisplay = implode(', ', array_map('intval', $relHelmets));
        }

        $fields = [
            'rel_helmets_display'              => ['label' => 'Linked Helmet IDs (comma-sep)', 'type' => 'text', 'hint' => 'Post IDs of helmets in this comparison', 'value' => $relHelmetsDisplay, 'key' => 'rel_helmets_display'],
            'comparison_parameters_json'       => ['label' => 'Parameters (JSON)',             'type' => 'textarea', 'rows' => 4, 'hint' => 'e.g. [{"key":"weight","label":"Weight","unit":"g"}]'],
            'comparison_scores_json'           => ['label' => 'Scores (JSON)',                 'type' => 'textarea', 'rows' => 4, 'hint' => 'Structured scores per helmet per parameter'],
            'comparison_recommendations_json'  => ['label' => 'Recommendations (JSON)',        'type' => 'textarea', 'rows' => 3, 'hint' => 'e.g. [{"helmet_id":42,"reason":"Best value"}]'],
        ];

        echo '<table class="form-table" role="presentation"><tbody>';
        foreach ($fields as $key => $field) {
            $label = esc_html($field['label']);
            $metaKey = $field['key'] ?? $key;
            $value = isset($field['value']) ? $field['value'] : (string) get_post_meta($post->ID, $metaKey, true);
            $id    = esc_attr('helmetsan_' . $key);
            $name  = esc_attr($key);

            echo '<tr><th scope="row"><label for="' . $id . '">' . $label . '</label></th><td>';

            if ($field['type'] === 'textarea') {
                $rows = isset($field['rows']) ? (int) $field['rows'] : 3;
                echo '<textarea id="' . $id . '" name="' . $name . '" rows="' . $rows . '" class="large-text">' . esc_textarea($value) . '</textarea>';
            } else {
                echo '<input type="text" id="' . $id . '" name="' . $name . '" value="' . esc_attr($value) . '" class="regular-text" />';
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

        // Parse comma-separated helmet IDs
        if (isset($_POST['rel_helmets_display'])) {
            $raw = sanitize_text_field(wp_unslash((string) $_POST['rel_helmets_display']));
            $ids = array_values(array_unique(array_filter(array_map('intval', explode(',', $raw)))));
            update_post_meta($postId, 'rel_helmets', $ids);
        }

        $jsonFields = ['comparison_parameters_json', 'comparison_scores_json', 'comparison_recommendations_json'];
        foreach ($jsonFields as $key) {
            if (isset($_POST[$key])) {
                update_post_meta($postId, $key, sanitize_textarea_field(wp_unslash((string) $_POST[$key])));
            }
        }
    }

    /**
     * @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    public function upsertFromPayload(array $data, string $sourceFile = '', bool $dryRun = false): array
    {
        $externalId = isset($data['id']) ? sanitize_title((string) $data['id']) : '';
        $title = isset($data['title']) && (string) $data['title'] !== ''
            ? sanitize_text_field((string) $data['title'])
            : '';

        $helmetRefs = isset($data['helmet_ids']) && is_array($data['helmet_ids']) ? $data['helmet_ids'] : [];
        if ($title === '' && $helmetRefs !== []) {
            $title = sanitize_text_field(implode(' vs ', array_map('strval', array_slice($helmetRefs, 0, 3))));
        }

        if ($title === '') {
            return ['ok' => false, 'message' => 'Comparison payload missing title/helmet_ids'];
        }

        $existingId = 0;
        if ($externalId !== '') {
            $existingId = $this->findByExternalId($externalId);
        }
        if ($existingId <= 0) {
            $existing = get_page_by_path(sanitize_title($title), OBJECT, 'comparison');
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
            'post_type'   => 'comparison',
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
            update_post_meta($postId, '_comparison_unique_id', $externalId);
        }
        if ($sourceFile !== '') {
            update_post_meta($postId, '_source_file', $sourceFile);
        }

        $resolvedHelmetIds = $this->resolveHelmetIds($helmetRefs);
        if ($resolvedHelmetIds !== []) {
            update_post_meta($postId, 'rel_helmets', $resolvedHelmetIds);
        }

        $this->setJsonMeta($postId, 'comparison_parameters_json', $data['parameters'] ?? null);
        $this->setJsonMeta($postId, 'comparison_scores_json', $data['scores'] ?? null);
        $this->setJsonMeta($postId, 'comparison_recommendations_json', $data['recommendations'] ?? null);

        return ['ok' => true, 'action' => $action, 'post_id' => $postId];
    }

    /**
     * @param array<int,mixed> $helmetRefs
     * @return array<int,int>
     */
    private function resolveHelmetIds(array $helmetRefs): array
    {
        $ids = [];
        foreach ($helmetRefs as $ref) {
            $value = sanitize_title((string) $ref);
            if ($value === '') {
                continue;
            }

            $posts = get_posts([
                'post_type'   => 'helmet',
                'post_status' => 'any',
                'numberposts' => 1,
                'meta_key'    => '_helmet_unique_id',
                'meta_value'  => $value,
                'fields'      => 'ids',
            ]);
            if (is_array($posts) && $posts !== []) {
                $ids[] = (int) $posts[0];
                continue;
            }

            $byPath = get_page_by_path($value, OBJECT, 'helmet');
            if ($byPath instanceof WP_Post) {
                $ids[] = (int) $byPath->ID;
            }
        }

        return array_values(array_unique(array_filter($ids)));
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
            'post_type'   => 'comparison',
            'post_status' => 'any',
            'numberposts' => 1,
            'meta_key'    => '_comparison_unique_id',
            'meta_value'  => $externalId,
            'fields'      => 'ids',
        ]);

        if (! is_array($posts) || $posts === []) {
            return 0;
        }

        return (int) $posts[0];
    }
}
