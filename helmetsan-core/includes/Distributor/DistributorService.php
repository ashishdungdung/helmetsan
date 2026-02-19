<?php

declare(strict_types=1);

namespace Helmetsan\Core\Distributor;

use WP_Post;

final class DistributorService
{
    private const NONCE_ACTION = 'helmetsan_distributor_meta';
    private const NONCE_FIELD  = '_helmetsan_distributor_nonce';

    public function register(): void
    {
        add_action('add_meta_boxes_distributor', [$this, 'registerMetaBox']);
        add_action('save_post_distributor', [$this, 'saveMeta'], 10, 2);
    }

    public function registerMetaBox(): void
    {
        add_meta_box(
            'helmetsan_distributor_details',
            'Distributor Details',
            [$this, 'renderMetaBox'],
            'distributor',
            'normal',
            'high'
        );
    }

    public function renderMetaBox(WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        $fields = [
            'distributor_type'         => ['label' => 'Type',         'type' => 'select', 'options' => ['' => '— Select —', 'exclusive' => 'Exclusive', 'non_exclusive' => 'Non-Exclusive', 'regional' => 'Regional']],
            'distributor_website'      => ['label' => 'Website',      'type' => 'url'],
            'distributor_phone'        => ['label' => 'Phone',        'type' => 'text'],
            'distributor_email'        => ['label' => 'Email',        'type' => 'email'],
            'distributor_address'      => ['label' => 'Address',      'type' => 'text'],
            'distributor_country_code' => ['label' => 'Country Code', 'type' => 'text', 'hint' => 'ISO 3166-1 alpha-2'],
            'distributor_regions_json' => ['label' => 'Regions (JSON array)',   'type' => 'textarea', 'rows' => 3, 'hint' => 'e.g. ["South Asia","Southeast Asia"]'],
            'distributor_countries_json'=> ['label' => 'Countries (JSON array)', 'type' => 'textarea', 'rows' => 3, 'hint' => 'e.g. ["IN","PK","BD"]'],
            'distributor_brands_json'  => ['label' => 'Brands (JSON array)',    'type' => 'textarea', 'rows' => 3, 'hint' => 'e.g. ["Shoei","Arai"]'],
            'distributor_warehouses_json' => ['label' => 'Warehouses (JSON)',   'type' => 'textarea', 'rows' => 3],
            'distributor_contacts_json'=> ['label' => 'Contacts (JSON)',        'type' => 'textarea', 'rows' => 3],
        ];

        echo '<table class="form-table" role="presentation"><tbody>';
        foreach ($fields as $key => $field) {
            $label = esc_html($field['label']);
            $value = (string) get_post_meta($post->ID, $key, true);
            $id    = esc_attr('helmetsan_' . $key);
            $name  = esc_attr($key);

            echo '<tr><th scope="row"><label for="' . $id . '">' . $label . '</label></th><td>';

            if ($field['type'] === 'select') {
                echo '<select id="' . $id . '" name="' . $name . '">';
                foreach ($field['options'] as $optVal => $optLabel) {
                    echo '<option value="' . esc_attr((string) $optVal) . '"' . selected($value, $optVal, false) . '>' . esc_html($optLabel) . '</option>';
                }
                echo '</select>';
            } elseif ($field['type'] === 'textarea') {
                $rows = isset($field['rows']) ? (int) $field['rows'] : 3;
                echo '<textarea id="' . $id . '" name="' . $name . '" rows="' . $rows . '" class="large-text">' . esc_textarea($value) . '</textarea>';
            } else {
                echo '<input type="' . esc_attr($field['type']) . '" id="' . $id . '" name="' . $name . '" value="' . esc_attr($value) . '" class="regular-text" />';
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

        $textFields = ['distributor_type', 'distributor_phone', 'distributor_email', 'distributor_address', 'distributor_country_code'];
        foreach ($textFields as $key) {
            if (isset($_POST[$key])) {
                update_post_meta($postId, $key, sanitize_text_field(wp_unslash((string) $_POST[$key])));
            }
        }

        if (isset($_POST['distributor_website'])) {
            update_post_meta($postId, 'distributor_website', esc_url_raw(wp_unslash((string) $_POST['distributor_website'])));
        }

        $jsonFields = ['distributor_regions_json', 'distributor_countries_json', 'distributor_brands_json', 'distributor_warehouses_json', 'distributor_contacts_json'];
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
            : (isset($data['name']) ? sanitize_text_field((string) $data['name']) : '');

        if ($title === '') {
            return ['ok' => false, 'message' => 'Distributor payload missing title/name'];
        }

        $existingId = 0;
        if ($externalId !== '') {
            $existingId = $this->findByExternalId($externalId);
        }
        if ($existingId <= 0) {
            $existing = get_page_by_path(sanitize_title($title), OBJECT, 'distributor');
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
            'post_type'   => 'distributor',
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
            update_post_meta($postId, '_distributor_unique_id', $externalId);
        }
        if ($sourceFile !== '') {
            update_post_meta($postId, '_source_file', $sourceFile);
        }

        $this->setStringMeta($postId, 'distributor_type', $data['type'] ?? null);
        $this->setStringMeta($postId, 'distributor_website', $data['website'] ?? null, true);
        $this->setStringMeta($postId, 'distributor_phone', $data['phone'] ?? null);
        $this->setStringMeta($postId, 'distributor_email', $data['email'] ?? null);
        $this->setStringMeta($postId, 'distributor_address', $data['address'] ?? null);
        $this->setStringMeta($postId, 'distributor_country_code', $data['country_code'] ?? null);

        $this->setJsonMeta($postId, 'distributor_regions_json', $data['regions'] ?? null);
        $this->setJsonMeta($postId, 'distributor_countries_json', $data['countries'] ?? null);
        $this->setJsonMeta($postId, 'distributor_brands_json', $data['brands'] ?? null);
        $this->setJsonMeta($postId, 'distributor_warehouses_json', $data['warehouses'] ?? null);
        $this->setJsonMeta($postId, 'distributor_contacts_json', $data['contacts'] ?? null);

        if (isset($data['regions']) && is_array($data['regions'])) {
            $regionTerms = array_filter(array_map(
                static fn($item): string => sanitize_text_field((string) $item),
                $data['regions']
            ));
            if ($regionTerms !== []) {
                wp_set_object_terms($postId, array_values($regionTerms), 'region', false);
            }
        }

        return ['ok' => true, 'action' => $action, 'post_id' => $postId];
    }

    private function setStringMeta(int $postId, string $metaKey, mixed $value, bool $isUrl = false): void
    {
        if (! is_scalar($value)) {
            return;
        }
        $raw = (string) $value;
        if ($raw === '') {
            return;
        }
        $clean = $isUrl ? esc_url_raw($raw) : sanitize_text_field($raw);
        update_post_meta($postId, $metaKey, $clean);
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
            'post_type'   => 'distributor',
            'post_status' => 'any',
            'numberposts' => 1,
            'meta_key'    => '_distributor_unique_id',
            'meta_value'  => $externalId,
            'fields'      => 'ids',
        ]);

        if (! is_array($posts) || $posts === []) {
            return 0;
        }

        return (int) $posts[0];
    }
}
