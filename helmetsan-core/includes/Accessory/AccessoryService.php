<?php

declare(strict_types=1);

namespace Helmetsan\Core\Accessory;

use Helmetsan\Core\Support\HelmetTypeNormalizer;
use WP_Post;

final class AccessoryService
{
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
        $type = isset($data['type']) ? sanitize_text_field((string) $data['type']) : '';

        if ($title === '') {
            return ['ok' => false, 'message' => 'Accessory payload missing title'];
        }

        $existingId = 0;
        if ($externalId !== '') {
            $existingId = $this->findByExternalId($externalId);
        }
        if ($existingId <= 0) {
            $existing = get_page_by_path(sanitize_title($title), OBJECT, 'accessory');
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
            'post_type' => 'accessory',
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
        update_post_meta($postId, '_source_hash', $payloadHash);
        if ($externalId !== '') {
            update_post_meta($postId, '_accessory_unique_id', $externalId);
        }
        if ($sourceFile !== '') {
            update_post_meta($postId, '_source_file', $sourceFile);
        }
        if ($type !== '') {
            update_post_meta($postId, 'accessory_type', $type);
        }
        if (isset($data['parent_category']) && is_string($data['parent_category']) && $data['parent_category'] !== '') {
            update_post_meta($postId, 'accessory_parent_category', sanitize_text_field($data['parent_category']));
        }
        if (isset($data['subcategory']) && is_string($data['subcategory']) && $data['subcategory'] !== '') {
            update_post_meta($postId, 'accessory_subcategory', sanitize_text_field($data['subcategory']));
        }
        if (isset($data['color']) && is_string($data['color']) && $data['color'] !== '') {
            update_post_meta($postId, 'accessory_color', sanitize_text_field($data['color']));
        }
        if (isset($data['youth_adult']) && is_string($data['youth_adult']) && $data['youth_adult'] !== '') {
            update_post_meta($postId, 'accessory_youth_adult', sanitize_text_field($data['youth_adult']));
        }
        update_post_meta($postId, 'accessory_electric_compatible', ! empty($data['electric_compatible']) ? '1' : '0');
        update_post_meta($postId, 'accessory_pinlock_ready', ! empty($data['pinlock_ready']) ? '1' : '0');
        update_post_meta($postId, 'accessory_snow_compatible', ! empty($data['snow_compatible']) ? '1' : '0');

        $this->setJsonMeta($postId, 'compatible_helmet_types_json', $data['compatible_helmet_types'] ?? null);
        $this->setJsonMeta($postId, 'compatible_brands_json', $data['compatible_brands'] ?? null);
        $this->setJsonMeta($postId, 'compatible_helmet_families_json', $data['compatible_helmet_families'] ?? null);
        $this->setJsonMeta($postId, 'compatibility_json', $data['compatibility'] ?? null);
        $this->setJsonMeta($postId, 'accessory_features_json', $data['features'] ?? null);
        $this->setJsonMeta($postId, 'accessory_global_filters_json', $data['global_filters'] ?? null);
        $this->setJsonMeta($postId, 'price_json', $data['price'] ?? null);

        $categoryTerms = [];
        if (isset($data['parent_category']) && is_string($data['parent_category']) && $data['parent_category'] !== '') {
            $categoryTerms[] = sanitize_text_field($data['parent_category']);
        }
        if (isset($data['subcategory']) && is_string($data['subcategory']) && $data['subcategory'] !== '') {
            $categoryTerms[] = sanitize_text_field($data['subcategory']);
        }
        if ($categoryTerms !== []) {
            wp_set_object_terms($postId, array_values(array_unique($categoryTerms)), 'accessory_category', false);
        }

        $compatibleHelmetTypes = [];
        if (isset($data['compatible_helmet_types']) && is_array($data['compatible_helmet_types'])) {
            $compatibleHelmetTypes = HelmetTypeNormalizer::normalizeArray($data['compatible_helmet_types']);
        }
        if ($compatibleHelmetTypes !== []) {
            wp_set_object_terms($postId, $compatibleHelmetTypes, 'helmet_type', false);
        } else {
            wp_set_object_terms($postId, [], 'helmet_type', false);
        }

        if (isset($data['features']) && is_array($data['features'])) {
            $terms = array_filter(array_map(
                static fn($item): string => sanitize_text_field((string) $item),
                $data['features']
            ));
            if ($terms !== []) {
                wp_set_object_terms($postId, array_values($terms), 'feature_tag', false);
            }
        }
        if (isset($data['global_filters']) && is_array($data['global_filters'])) {
            $terms = array_filter(array_map(
                static fn($item): string => sanitize_text_field((string) $item),
                $data['global_filters']
            ));
            if ($terms !== []) {
                wp_set_object_terms($postId, array_values($terms), 'feature_tag', true);
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
            'post_type' => 'accessory',
            'post_status' => 'any',
            'numberposts' => 1,
            'meta_key' => '_accessory_unique_id',
            'meta_value' => $externalId,
            'fields' => 'ids',
        ]);

        if (! is_array($posts) || $posts === []) {
            return 0;
        }

        return (int) $posts[0];
    }

    // normalizeHelmetType() removed — use HelmetTypeNormalizer::toLabel() instead.
}
