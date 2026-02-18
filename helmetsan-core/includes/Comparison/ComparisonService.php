<?php

declare(strict_types=1);

namespace Helmetsan\Core\Comparison;

use WP_Post;

final class ComparisonService
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
            'post_type' => 'comparison',
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
                'post_type' => 'helmet',
                'post_status' => 'any',
                'numberposts' => 1,
                'meta_key' => '_helmet_unique_id',
                'meta_value' => $value,
                'fields' => 'ids',
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
            'post_type' => 'comparison',
            'post_status' => 'any',
            'numberposts' => 1,
            'meta_key' => '_comparison_unique_id',
            'meta_value' => $externalId,
            'fields' => 'ids',
        ]);

        if (! is_array($posts) || $posts === []) {
            return 0;
        }

        return (int) $posts[0];
    }
}
