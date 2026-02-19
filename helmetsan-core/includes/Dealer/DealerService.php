<?php

declare(strict_types=1);

namespace Helmetsan\Core\Dealer;

use WP_Post;

final class DealerService
{
    private const NONCE_ACTION = 'helmetsan_dealer_meta';
    private const NONCE_FIELD  = '_helmetsan_dealer_nonce';

    public function register(): void
    {
        add_action('add_meta_boxes_dealer', [$this, 'registerMetaBox']);
        add_action('save_post_dealer', [$this, 'saveMeta'], 10, 2);
    }

    public function registerMetaBox(): void
    {
        add_meta_box(
            'helmetsan_dealer_details',
            'Dealer Details',
            [$this, 'renderMetaBox'],
            'dealer',
            'normal',
            'high'
        );
    }

    public function renderMetaBox(WP_Post $post): void
    {
        wp_nonce_field(self::NONCE_ACTION, self::NONCE_FIELD);

        $fields = [
            'dealer_type'         => ['label' => 'Type',          'type' => 'select', 'options' => ['' => '— Select —', 'authorized' => 'Authorized', 'independent' => 'Independent', 'online' => 'Online Only']],
            'dealer_website'      => ['label' => 'Website',       'type' => 'url'],
            'dealer_phone'        => ['label' => 'Phone',         'type' => 'text'],
            'dealer_email'        => ['label' => 'Email',         'type' => 'email'],
            'dealer_address'      => ['label' => 'Address',       'type' => 'text'],
            'dealer_city'         => ['label' => 'City',          'type' => 'text'],
            'dealer_country_code' => ['label' => 'Country Code',  'type' => 'text', 'hint' => 'ISO 3166-1 alpha-2, e.g. US, IN, GB'],
            'dealer_region_code'  => ['label' => 'Region/State',  'type' => 'text'],
            'dealer_online_store' => ['label' => 'Online Store?', 'type' => 'checkbox'],
            'dealer_offline_store'=> ['label' => 'Physical Store?','type' => 'checkbox'],
            'dealer_brands_json'  => ['label' => 'Brands Stocked (JSON array)', 'type' => 'textarea', 'rows' => 3, 'hint' => 'e.g. ["Shoei","Arai","AGV"]'],
            'dealer_services_json'=> ['label' => 'Services (JSON array)',        'type' => 'textarea', 'rows' => 3, 'hint' => 'e.g. ["fitting","repair","custom paint"]'],
        ];

        echo '<table class="form-table" role="presentation"><tbody>';
        foreach ($fields as $key => $field) {
            $label = esc_html($field['label']);
            $value = (string) get_post_meta($post->ID, $key, true);
            $id    = esc_attr('helmetsan_' . $key);
            $name  = esc_attr($key);

            echo '<tr><th scope="row"><label for="' . $id . '">' . $label . '</label></th><td>';

            if ($field['type'] === 'checkbox') {
                $checked = checked($value, '1', false);
                echo '<input type="checkbox" id="' . $id . '" name="' . $name . '" value="1"' . $checked . ' />';
            } elseif ($field['type'] === 'select') {
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

        $textFields = ['dealer_type', 'dealer_phone', 'dealer_email', 'dealer_address', 'dealer_city', 'dealer_country_code', 'dealer_region_code'];
        foreach ($textFields as $key) {
            if (isset($_POST[$key])) {
                update_post_meta($postId, $key, sanitize_text_field(wp_unslash((string) $_POST[$key])));
            }
        }

        if (isset($_POST['dealer_website'])) {
            update_post_meta($postId, 'dealer_website', esc_url_raw(wp_unslash((string) $_POST['dealer_website'])));
        }

        update_post_meta($postId, 'dealer_online_store', isset($_POST['dealer_online_store']) ? '1' : '0');
        update_post_meta($postId, 'dealer_offline_store', isset($_POST['dealer_offline_store']) ? '1' : '0');

        foreach (['dealer_brands_json', 'dealer_services_json'] as $key) {
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
            return ['ok' => false, 'message' => 'Dealer payload missing title/name'];
        }

        $existingId = 0;
        if ($externalId !== '') {
            $existingId = $this->findByExternalId($externalId);
        }
        if ($existingId <= 0) {
            $existing = get_page_by_path(sanitize_title($title), OBJECT, 'dealer');
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
            'post_type'   => 'dealer',
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
            update_post_meta($postId, '_dealer_unique_id', $externalId);
        }
        if ($sourceFile !== '') {
            update_post_meta($postId, '_source_file', $sourceFile);
        }

        $this->setStringMeta($postId, 'dealer_type', $data['type'] ?? null);
        $this->setStringMeta($postId, 'dealer_website', $data['website'] ?? null, true);
        $this->setStringMeta($postId, 'dealer_phone', $data['phone'] ?? null);
        $this->setStringMeta($postId, 'dealer_email', $data['email'] ?? null);
        $this->setStringMeta($postId, 'dealer_address', $data['address'] ?? null);
        $this->setStringMeta($postId, 'dealer_city', $data['city'] ?? null);
        $this->setStringMeta($postId, 'dealer_country_code', $data['country_code'] ?? null);
        $this->setStringMeta($postId, 'dealer_region_code', $data['region_code'] ?? null);

        update_post_meta($postId, 'dealer_online_store', ! empty($data['online_store']) ? '1' : '0');
        update_post_meta($postId, 'dealer_offline_store', ! empty($data['offline_store']) ? '1' : '0');

        $this->setJsonMeta($postId, 'dealer_geo_json', $data['geo'] ?? null);
        $this->setJsonMeta($postId, 'dealer_brands_json', $data['brands_stocked'] ?? null);
        $this->setJsonMeta($postId, 'dealer_marketplaces_json', $data['marketplaces'] ?? null);
        $this->setJsonMeta($postId, 'dealer_services_json', $data['services'] ?? null);

        if (isset($data['brands_stocked']) && is_array($data['brands_stocked'])) {
            $terms = array_filter(array_map(
                static fn($item): string => sanitize_text_field((string) $item),
                $data['brands_stocked']
            ));
            if ($terms !== []) {
                wp_set_object_terms($postId, array_values($terms), 'feature_tag', false);
            }
        }

        $regionTerms = [];
        if (isset($data['region_code']) && (string) $data['region_code'] !== '') {
            $regionTerms[] = sanitize_text_field((string) $data['region_code']);
        }
        if (isset($data['country_code']) && (string) $data['country_code'] !== '') {
            $regionTerms[] = strtoupper(sanitize_text_field((string) $data['country_code']));
        }
        if ($regionTerms !== []) {
            wp_set_object_terms($postId, array_values(array_unique($regionTerms)), 'region', false);
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
            'post_type'   => 'dealer',
            'post_status' => 'any',
            'numberposts' => 1,
            'meta_key'    => '_dealer_unique_id',
            'meta_value'  => $externalId,
            'fields'      => 'ids',
        ]);

        if (! is_array($posts) || $posts === []) {
            return 0;
        }

        return (int) $posts[0];
    }
}
