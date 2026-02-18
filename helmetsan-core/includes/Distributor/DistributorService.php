<?php

declare(strict_types=1);

namespace Helmetsan\Core\Distributor;

use WP_Post;

final class DistributorService
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
            'post_type' => 'distributor',
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
            'post_type' => 'distributor',
            'post_status' => 'any',
            'numberposts' => 1,
            'meta_key' => '_distributor_unique_id',
            'meta_value' => $externalId,
            'fields' => 'ids',
        ]);

        if (! is_array($posts) || $posts === []) {
            return 0;
        }

        return (int) $posts[0];
    }
}
