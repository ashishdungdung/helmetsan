<?php

declare(strict_types=1);

namespace Helmetsan\Core\Ingestion;

use Helmetsan\Core\Repository\JsonRepository;
use Helmetsan\Core\Support\Logger;
use Helmetsan\Core\Validation\Validator;

final class IngestionService
{
    /**
     * Allowed canonical helmet types.
     *
     * @var array<string,string>
     */
    private const HELMET_TYPE_CANONICAL = [
        'full face' => 'Full Face',
        'modular' => 'Modular',
        'open face' => 'Open Face',
        'half' => 'Half',
        'dirt' => 'Dirt / MX',
        'dirt / mx' => 'Dirt / MX',
        'dirt / motocross' => 'Dirt / MX',
        'adventure' => 'Adventure / Dual Sport',
        'dual sport' => 'Adventure / Dual Sport',
        'adventure / dual sport' => 'Adventure / Dual Sport',
        'touring' => 'Touring',
        'track' => 'Track / Race',
        'track / race' => 'Track / Race',
        'youth' => 'Youth',
        'snow' => 'Snow',
        'snowmobile' => 'Snow',
        'carbon fiber' => 'Carbon Fiber',
        'graphics' => 'Graphics',
        'sale' => 'Sale',
    ];

    private const LOCK_KEY = 'helmetsan_ingest_lock';
    private const LOCK_TTL = 300;

    public function __construct(
        private readonly Validator $validator,
        private readonly JsonRepository $repository,
        private readonly Logger $logger,
        private readonly LogRepository $logs
    ) {
    }

    public function ingestPath(string $path, int $batchSize = 100, ?int $limit = null, bool $dryRun = false): array
    {
        $files = $this->repository->listJsonFiles($path);
        return $this->ingestFiles($files, $batchSize, $limit, $dryRun, $path);
    }

    public function forceUnlock(): void
    {
        $this->releaseLock();
    }

    public function lockActive(): bool
    {
        return get_transient(self::LOCK_KEY) !== false;
    }

    /**
     * @param array<int, string> $files
     */
    public function ingestFiles(array $files, int $batchSize = 100, ?int $limit = null, bool $dryRun = false, string $sourcePath = 'selected-files'): array
    {
        if (! $this->acquireLock()) {
            return [
                'ok'      => false,
                'locked'  => true,
                'message' => 'Ingestion is already running. Try again after current job completes.',
            ];
        }

        try {
        if ($limit !== null && $limit > 0) {
            $files = array_slice($files, 0, $limit);
        }

        // De-duplicate file list to avoid accidental re-processing.
        $files = array_values(array_unique($files));

        $batchSize = max(1, $batchSize);
        $batches   = array_chunk($files, $batchSize);
        $ok    = 0;
        $fail  = 0;
        $skipped = 0;
        $updated = 0;
        $created = 0;

        foreach ($batches as $index => $batch) {
            $this->logger->info('Processing batch ' . (string) ($index + 1) . ' with ' . (string) count($batch) . ' files');

            foreach ($batch as $file) {
                $data = $this->repository->read($file);
                if ($data === []) {
                    $fail++;
                    $this->logger->info('Ingest rejected: empty/invalid file ' . $file);
                    $this->logs->add($file, 'rejected', 'Empty or invalid JSON payload');
                    continue;
                }

                $schema = $this->validator->validateSchema($data);
                $logic  = $this->validator->validateLogic($data);

                if (! $schema['ok'] || ! $logic['ok']) {
                    $fail++;
                    $this->logger->info('Ingest rejected: ' . $file);
                    $errors = array_merge($schema['errors'] ?? [], $logic['errors'] ?? []);
                    $this->logs->add(
                        $file,
                        'rejected',
                        implode('; ', array_map('strval', $errors)),
                        isset($data['id']) ? (string) $data['id'] : ''
                    );
                    continue;
                }

                $encoded = wp_json_encode($data);
                $payloadHash = hash('sha256', is_string($encoded) ? $encoded : serialize($data));
                $postId      = $this->findHelmetPostId((string) $data['id']);
                $existingHash = $postId > 0 ? (string) get_post_meta($postId, '_source_hash', true) : '';

                if ($existingHash !== '' && hash_equals($existingHash, $payloadHash)) {
                    $skipped++;
                    $this->logs->add($file, 'skipped', 'Hash unchanged; record skipped', (string) $data['id'], $postId);
                    continue;
                }

                if ($dryRun) {
                    $skipped++;
                    $this->logs->add($file, 'dry-run', 'Validated in dry-run mode', (string) $data['id'], $postId);
                    continue;
                }

                $transactionStarted = $this->startTransaction();
                $upsert = $this->upsertHelmet($data, $file, $payloadHash, $postId);
                if (! $upsert['ok']) {
                    if ($transactionStarted) {
                        $this->rollbackTransaction();
                    }
                    $fail++;
                    $this->logs->add(
                        $file,
                        'failed',
                        'Database upsert failed',
                        (string) $data['id'],
                        $postId
                    );
                    continue;
                }
                if ($transactionStarted) {
                    $this->commitTransaction();
                }

                $ok++;
                if ($upsert['action'] === 'created') {
                    $created++;
                } else {
                    $updated++;
                }
                $this->logs->add(
                    $file,
                    $upsert['action'],
                    'Entity upsert successful',
                    (string) $data['id'],
                    (int) $upsert['post_id']
                );
            }
        }

        return [
            'ok'           => true,
            'processed'    => count($files),
            'accepted'     => $ok,
            'rejected'     => $fail,
            'dry_run'      => $dryRun,
            'skipped'      => $skipped,
            'created'      => $created,
            'updated'      => $updated,
            'batch_size'   => $batchSize,
            'batches'      => count($batches),
            'source_path'  => $sourcePath,
        ];
        } finally {
            $this->releaseLock();
        }
    }

    private function findHelmetPostId(string $externalId): int
    {
        $posts = get_posts([
            'post_type'   => 'helmet',
            'post_status' => 'any',
            'numberposts' => 1,
            'meta_key'    => '_helmet_unique_id',
            'meta_value'  => $externalId,
            'fields'      => 'ids',
        ]);

        if (! is_array($posts) || $posts === []) {
            return 0;
        }

        return (int) $posts[0];
    }

    /**
     * @param array<string, mixed> $data
     * @return array{ok: bool, action: string, post_id: int}
     */
    private function upsertHelmet(array $data, string $sourceFile, string $hash, int $postId): array
    {
        $title = isset($data['title']) && is_string($data['title']) && $data['title'] !== ''
            ? $data['title']
            : (string) $data['id'];

        $postArgs = [
            'post_type'    => 'helmet',
            'post_title'   => sanitize_text_field($title),
            'post_status'  => 'publish',
            'post_content' => $this->buildDescription($data),
        ];

        if ($postId > 0) {
            $postArgs['ID'] = $postId;
            $result = wp_update_post($postArgs, true);
            $action = 'updated';
        } else {
            $result = wp_insert_post($postArgs, true);
            $action = 'created';
        }

        if (is_wp_error($result)) {
            $this->logger->info('Ingest upsert failed for ' . $sourceFile . ': ' . $result->get_error_message());
            return ['ok' => false, 'action' => 'failed', 'post_id' => 0];
        }

        $resolvedPostId = (int) $result;

        update_post_meta($resolvedPostId, '_helmet_unique_id', (string) $data['id']);
        update_post_meta($resolvedPostId, '_source_hash', $hash);
        update_post_meta($resolvedPostId, '_source_file', $sourceFile);

        if (isset($data['specs']['weight_g'])) {
            update_post_meta($resolvedPostId, 'spec_weight_g', (int) $data['specs']['weight_g']);
            $lbs = round(((float) $data['specs']['weight_g']) / 453.59237, 2);
            update_post_meta($resolvedPostId, 'spec_weight_lbs', (string) $lbs);
        }

        if (isset($data['specs']['weight_lbs'])) {
            update_post_meta($resolvedPostId, 'spec_weight_lbs', (string) ((float) $data['specs']['weight_lbs']));
        }

        if (isset($data['specs']['material']) && is_string($data['specs']['material'])) {
            update_post_meta($resolvedPostId, 'spec_shell_material', sanitize_text_field($data['specs']['material']));
        }

        if (isset($data['price']) && is_array($data['price']) && isset($data['price']['current'])) {
            update_post_meta($resolvedPostId, 'price_retail_usd', (string) $data['price']['current']);
        }

        if (isset($data['helmet_family']) && is_string($data['helmet_family']) && $data['helmet_family'] !== '') {
            update_post_meta($resolvedPostId, 'helmet_family', sanitize_text_field($data['helmet_family']));
        }
        if (isset($data['head_shape']) && is_string($data['head_shape']) && $data['head_shape'] !== '') {
            update_post_meta($resolvedPostId, 'head_shape', sanitize_text_field($data['head_shape']));
        }

        if (isset($data['affiliate']) && is_array($data['affiliate']) && isset($data['affiliate']['amazon_asin'])) {
            update_post_meta($resolvedPostId, 'affiliate_asin', sanitize_text_field((string) $data['affiliate']['amazon_asin']));
        }

        $jsonMetaMap = [
            'geo_pricing' => 'geo_pricing_json',
            'geo_legality' => 'geo_legality_json',
            'certification_documents' => 'certification_documents_json',
            'geo_media' => 'geo_media_json',
            'variants' => 'variants_json',
            'product_details' => 'product_details_json',
            'part_numbers' => 'part_numbers_json',
            'sizing_fit' => 'sizing_fit_json',
            'related_videos' => 'related_videos_json',
            'features' => 'features_json',
            'helmet_types' => 'helmet_types_json',
        ];
        foreach ($jsonMetaMap as $jsonKey => $metaKey) {
            if (! isset($data[$jsonKey])) {
                continue;
            }
            $json = wp_json_encode($data[$jsonKey], JSON_UNESCAPED_SLASHES);
            if (is_string($json) && $json !== '') {
                update_post_meta($resolvedPostId, $metaKey, $json);
            }
        }

        if (isset($data['specs']['certifications']) && is_array($data['specs']['certifications'])) {
            $terms = array_filter(array_map(
                static fn($value): string => sanitize_text_field((string) $value),
                $data['specs']['certifications']
            ));
            if ($terms !== []) {
                wp_set_object_terms($resolvedPostId, array_values($terms), 'certification', false);
            }
        }

        $normalizedTypes = [];
        if (isset($data['type']) && is_string($data['type']) && $data['type'] !== '') {
            $normalized = $this->normalizeHelmetType((string) $data['type']);
            if ($normalized !== '') {
                $normalizedTypes[] = $normalized;
            }
        }
        if (isset($data['helmet_types']) && is_array($data['helmet_types'])) {
            foreach ($data['helmet_types'] as $rawType) {
                $normalized = $this->normalizeHelmetType((string) $rawType);
                if ($normalized !== '') {
                    $normalizedTypes[] = $normalized;
                }
            }
        }
        $normalizedTypes = array_values(array_unique($normalizedTypes));
        if ($normalizedTypes !== []) {
            wp_set_object_terms($resolvedPostId, $normalizedTypes, 'helmet_type', false);
        }
        if (isset($data['features']) && is_array($data['features'])) {
            $featureTerms = array_filter(array_map(
                static fn($value): string => sanitize_text_field((string) $value),
                $data['features']
            ));
            if ($featureTerms !== []) {
                wp_set_object_terms($resolvedPostId, array_values($featureTerms), 'feature_tag', false);
            }
        }

        if (isset($data['brand']) && is_string($data['brand']) && $data['brand'] !== '') {
            $brandId = $this->findOrCreateBrand($data['brand']);
            if ($brandId > 0) {
                update_post_meta($resolvedPostId, 'rel_brand', $brandId);
            }
        }

        return ['ok' => true, 'action' => $action, 'post_id' => $resolvedPostId];
    }

    /**
     * @param array<string, mixed> $data
     */
    private function buildDescription(array $data): string
    {
        if (isset($data['product_details']['description']) && is_string($data['product_details']['description']) && $data['product_details']['description'] !== '') {
            return wp_kses_post($data['product_details']['description']);
        }

        $title = isset($data['title']) ? (string) $data['title'] : '';
        $type  = isset($data['type']) ? (string) $data['type'] : '';
        $brand = isset($data['brand']) ? (string) $data['brand'] : '';

        $parts = array_filter([$title, $type !== '' ? 'Type: ' . $type : '', $brand !== '' ? 'Brand: ' . $brand : '']);

        return implode(' | ', $parts);
    }

    private function findOrCreateBrand(string $brandName): int
    {
        $existing = get_page_by_path(sanitize_title($brandName), OBJECT, 'brand');
        if ($existing instanceof \WP_Post) {
            return (int) $existing->ID;
        }

        $brandId = wp_insert_post([
            'post_type'   => 'brand',
            'post_title'  => sanitize_text_field($brandName),
            'post_status' => 'publish',
        ], true);

        if (is_wp_error($brandId)) {
            return 0;
        }

        return (int) $brandId;
    }

    private function normalizeHelmetType(string $raw): string
    {
        $value = strtolower(trim(sanitize_text_field($raw)));
        if ($value === '') {
            return '';
        }
        $value = preg_replace('/\\s+/', ' ', $value) ?? $value;

        if (isset(self::HELMET_TYPE_CANONICAL[$value])) {
            return self::HELMET_TYPE_CANONICAL[$value];
        }

        if (str_contains($value, 'full') && str_contains($value, 'face')) {
            return 'Full Face';
        }
        if (str_contains($value, 'modular')) {
            return 'Modular';
        }
        if (str_contains($value, 'open') && str_contains($value, 'face')) {
            return 'Open Face';
        }
        if ($value === 'half helmet' || $value === 'half helmets') {
            return 'Half';
        }
        if (str_contains($value, 'adventure') || str_contains($value, 'dual sport')) {
            return 'Adventure / Dual Sport';
        }
        if (str_contains($value, 'dirt') || str_contains($value, 'motocross') || str_contains($value, 'mx')) {
            return 'Dirt / MX';
        }
        if (str_contains($value, 'tour')) {
            return 'Touring';
        }
        if (str_contains($value, 'track') || str_contains($value, 'race')) {
            return 'Track / Race';
        }
        if (str_contains($value, 'youth')) {
            return 'Youth';
        }
        if (str_contains($value, 'snow')) {
            return 'Snow';
        }
        if (str_contains($value, 'carbon')) {
            return 'Carbon Fiber';
        }
        if (str_contains($value, 'graphic')) {
            return 'Graphics';
        }
        if (str_contains($value, 'sale') || str_contains($value, 'closeout')) {
            return 'Sale';
        }

        return '';
    }

    private function startTransaction(): bool
    {
        global $wpdb;

        $result = $wpdb->query('START TRANSACTION');

        return $result !== false;
    }

    private function commitTransaction(): void
    {
        global $wpdb;
        $wpdb->query('COMMIT');
    }

    private function rollbackTransaction(): void
    {
        global $wpdb;
        $wpdb->query('ROLLBACK');
    }

    private function acquireLock(): bool
    {
        $active = get_transient(self::LOCK_KEY);
        if ($active !== false) {
            return false;
        }

        return set_transient(self::LOCK_KEY, 1, self::LOCK_TTL);
    }

    private function releaseLock(): void
    {
        delete_transient(self::LOCK_KEY);
    }
}
