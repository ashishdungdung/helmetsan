<?php

declare(strict_types=1);

namespace Helmetsan\Core\SafetyStandard;

use WP_Post;

final class SafetyStandardService
{
    private const NONCE_ACTION = 'helmetsan_safety_standard_meta';
    private const NONCE_FIELD  = '_helmetsan_safety_standard_nonce';

    /**
     * Register admin hooks for the meta box.
     */
    public function register(): void
    {
        add_action('add_meta_boxes_safety_standard', [$this, 'registerMetaBox']);
        add_action('save_post_safety_standard', [$this, 'saveMeta'], 10, 2);
    }

    public function registerMetaBox(): void
    {
        add_meta_box(
            'helmetsan_safety_standard_details',
            'Safety Standard Details',
            [$this, 'renderMetaBox'],
            'safety_standard',
            'normal',
            'high'
        );
    }

    public function renderMetaBox(WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        $fields = $this->getFieldDefinitions();
        $values = [];
        foreach ($fields as $key => $field) {
            $values[$key] = (string) get_post_meta($post->ID, $key, true);
        }

        echo '<table class="form-table" role="presentation"><tbody>';
        foreach ($fields as $key => $field) {
            $label = esc_html($field['label']);
            $value = $values[$key];
            $id    = esc_attr('helmetsan_' . $key);
            $name  = esc_attr($key);

            echo '<tr>';
            echo '<th scope="row"><label for="' . $id . '">' . $label . '</label></th>';
            echo '<td>';

            if ($field['type'] === 'textarea') {
                $rows = isset($field['rows']) ? (int) $field['rows'] : 4;
                echo '<textarea id="' . $id . '" name="' . $name . '" rows="' . $rows . '" class="large-text">' . esc_textarea($value) . '</textarea>';
                if (isset($field['hint'])) {
                    echo '<p class="description">' . esc_html($field['hint']) . '</p>';
                }
            } elseif ($field['type'] === 'select') {
                echo '<select id="' . $id . '" name="' . $name . '">';
                foreach ($field['options'] as $optVal => $optLabel) {
                    $selected = selected($value, $optVal, false);
                    echo '<option value="' . esc_attr((string) $optVal) . '"' . $selected . '>' . esc_html($optLabel) . '</option>';
                }
                echo '</select>';
            } else {
                $type = esc_attr($field['type']);
                echo '<input type="' . $type . '" id="' . $id . '" name="' . $name . '" value="' . esc_attr($value) . '" class="regular-text" />';
                if (isset($field['hint'])) {
                    echo '<p class="description">' . esc_html($field['hint']) . '</p>';
                }
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

        foreach ($this->getFieldDefinitions() as $key => $field) {
            if (! isset($_POST[$key])) {
                continue;
            }

            $raw = wp_unslash($_POST[$key]);

            if ($field['type'] === 'url') {
                update_post_meta($postId, $key, esc_url_raw((string) $raw));
            } elseif ($field['type'] === 'number') {
                update_post_meta($postId, $key, (string) absint((string) $raw));
            } elseif ($field['type'] === 'textarea') {
                update_post_meta($postId, $key, sanitize_textarea_field((string) $raw));
            } else {
                update_post_meta($postId, $key, sanitize_text_field((string) $raw));
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
        $name = isset($data['name']) && (string) $data['name'] !== ''
            ? sanitize_text_field((string) $data['name'])
            : '';
        $title = isset($data['title']) && (string) $data['title'] !== ''
            ? sanitize_text_field((string) $data['title'])
            : $name;

        if ($title === '') {
            return ['ok' => false, 'message' => 'Safety standard payload missing name/title'];
        }

        $existingId = 0;
        if ($externalId !== '') {
            $existingId = $this->findByExternalId($externalId);
        }
        if ($existingId <= 0) {
            $existing = get_page_by_path(sanitize_title($title), OBJECT, 'safety_standard');
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
            'post_type'   => 'safety_standard',
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
            update_post_meta($postId, '_safety_standard_unique_id', $externalId);
        }
        if ($sourceFile !== '') {
            update_post_meta($postId, '_source_file', $sourceFile);
        }

        // Scalar meta fields from payload
        if (isset($data['issuing_body'])) {
            update_post_meta($postId, 'standard_issuing_body', sanitize_text_field((string) $data['issuing_body']));
        }
        if (isset($data['year_introduced'])) {
            update_post_meta($postId, 'standard_year_introduced', (string) absint((string) $data['year_introduced']));
        }
        if (isset($data['last_updated'])) {
            update_post_meta($postId, 'standard_last_updated', (string) absint((string) $data['last_updated']));
        }
        if (isset($data['status'])) {
            update_post_meta($postId, 'standard_status', sanitize_text_field((string) $data['status']));
        }
        if (isset($data['story'])) {
            update_post_meta($postId, 'standard_story', sanitize_textarea_field((string) $data['story']));
        }
        if (isset($data['official_reference_url'])) {
            update_post_meta($postId, 'official_reference_url', esc_url_raw((string) $data['official_reference_url']));
        }

        // JSON meta fields
        $this->setJsonMeta($postId, 'standard_regions_json', $data['regions'] ?? null);
        $this->setJsonMeta($postId, 'mandatory_markets_json', $data['mandatory_markets'] ?? null);
        $this->setJsonMeta($postId, 'test_focus_json', $data['test_focus'] ?? null);
        $this->setJsonMeta($postId, 'standard_timeline_json', $data['timeline'] ?? null);
        $this->setJsonMeta($postId, 'standard_testing_protocol_json', $data['testing_protocol'] ?? null);
        $this->setJsonMeta($postId, 'standard_performance_specs_json', $data['performance_specs'] ?? null);

        if ($name !== '') {
            wp_set_object_terms($postId, $name, 'certification', false);
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

    private function findByExternalId(string $externalId): int
    {
        $posts = get_posts([
            'post_type'   => 'safety_standard',
            'post_status' => 'any',
            'numberposts' => 1,
            'meta_key'    => '_safety_standard_unique_id',
            'meta_value'  => $externalId,
            'fields'      => 'ids',
        ]);

        if (! is_array($posts) || $posts === []) {
            return 0;
        }

        return (int) $posts[0];
    }

    /**
     * Field definitions for the admin meta box.
     *
     * @return array<string, array<string, mixed>>
     */
    private function getFieldDefinitions(): array
    {
        return [
            'standard_issuing_body' => [
                'label' => 'Issuing Body',
                'type'  => 'text',
                'hint'  => 'e.g. NHTSA, UNECE, Snell Memorial Foundation',
            ],
            'standard_year_introduced' => [
                'label' => 'Year Introduced',
                'type'  => 'number',
            ],
            'standard_last_updated' => [
                'label' => 'Last Updated Year',
                'type'  => 'number',
            ],
            'official_reference_url' => [
                'label' => 'Official Reference URL',
                'type'  => 'url',
            ],
            'standard_status' => [
                'label'   => 'Status',
                'type'    => 'select',
                'options' => [
                    ''           => '— Select —',
                    'active'     => 'Active',
                    'superseded' => 'Superseded',
                    'regional'   => 'Regional',
                    'voluntary'  => 'Voluntary',
                ],
            ],
            'standard_story' => [
                'label' => 'Story & History',
                'type'  => 'textarea',
                'rows'  => 6,
                'hint'  => 'Narrative overview of the standard\'s history and significance.',
            ],
            'standard_regions_json' => [
                'label' => 'Active Regions (JSON array)',
                'type'  => 'textarea',
                'rows'  => 3,
                'hint'  => 'e.g. ["USA","Canada","Mexico"]',
            ],
            'mandatory_markets_json' => [
                'label' => 'Mandatory Markets (JSON array)',
                'type'  => 'textarea',
                'rows'  => 3,
                'hint'  => 'Markets where this standard is legally required.',
            ],
            'test_focus_json' => [
                'label' => 'Test Focus Areas (JSON array)',
                'type'  => 'textarea',
                'rows'  => 3,
                'hint'  => 'e.g. ["impact attenuation","retention system","penetration"]',
            ],
            'standard_timeline_json' => [
                'label' => 'Timeline Events (JSON array)',
                'type'  => 'textarea',
                'rows'  => 4,
                'hint'  => 'e.g. [{"year":1974,"event":"Standard first published"}]',
            ],
            'standard_testing_protocol_json' => [
                'label' => 'Testing Protocol (JSON)',
                'type'  => 'textarea',
                'rows'  => 4,
                'hint'  => 'Structured JSON describing the test methodology.',
            ],
            'standard_performance_specs_json' => [
                'label' => 'Performance Specs (JSON)',
                'type'  => 'textarea',
                'rows'  => 4,
                'hint'  => 'Structured JSON of pass/fail thresholds.',
            ],
        ];
    }
}
