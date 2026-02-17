<?php

declare(strict_types=1);

namespace Helmetsan\Core\ImportExport;

use Helmetsan\Core\Brands\BrandService;
use Helmetsan\Core\Ingestion\IngestionService;
use Helmetsan\Core\Support\Config;

final class ImportService
{
    public function __construct(
        private readonly IngestionService $ingestion,
        private readonly Config $config,
        private readonly BrandService $brands
    ) {
    }

    public function importJsonFile(string $filePath, bool $dryRun = false, int $batchSize = 100): array
    {
        if ($filePath === '' || ! file_exists($filePath)) {
            return [
                'ok'      => false,
                'message' => 'Import file not found',
            ];
        }

        $raw = file_get_contents($filePath);
        if ($raw === false) {
            return [
                'ok'      => false,
                'message' => 'Unable to read import file',
            ];
        }

        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            return [
                'ok'      => false,
                'message' => 'Invalid JSON payload',
            ];
        }

        $records = $this->normalizeRecords($decoded);
        if ($records === []) {
            return [
                'ok'      => false,
                'message' => 'No importable records found',
            ];
        }

        $uploads = wp_upload_dir();
        $baseDir = isset($uploads['basedir']) && is_string($uploads['basedir']) ? $uploads['basedir'] : WP_CONTENT_DIR . '/uploads';
        $tmpDir = trailingslashit($baseDir) . 'helmetsan-runtime/imports';
        if (! is_dir($tmpDir)) {
            wp_mkdir_p($tmpDir);
        }

        $files = [];
        $brandAccepted = 0;
        $brandRejected = 0;
        $brandSkipped = 0;

        foreach ($records as $index => $record) {
            $entity = $this->detectEntity($record);

            if ($entity === 'brand') {
                $result = $this->brands->upsertFromPayload($record, $filePath, $dryRun);
                if (! empty($result['ok'])) {
                    $action = isset($result['action']) ? (string) $result['action'] : '';
                    if ($action === 'skipped' || $action === 'dry-run') {
                        $brandSkipped++;
                    } else {
                        $brandAccepted++;
                    }
                } else {
                    $brandRejected++;
                }
                continue;
            }

            if (! isset($record['id']) || ! is_string($record['id']) || $record['id'] === '') {
                $record['id'] = 'imported-' . (string) $index;
            }

            $slug = sanitize_file_name((string) $record['id']);
            $path = trailingslashit($tmpDir) . $slug . '.json';
            $json = wp_json_encode($record, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
            if (! is_string($json)) {
                continue;
            }
            file_put_contents($path, $json);
            $files[] = $path;
        }

        $helmetResult = [
            'ok' => true,
            'processed' => 0,
            'accepted' => 0,
            'rejected' => 0,
            'dry_run' => $dryRun,
            'skipped' => 0,
            'created' => 0,
            'updated' => 0,
            'batch_size' => $batchSize,
            'batches' => 0,
            'source_path' => 'import-service',
        ];
        if ($files !== []) {
            $helmetResult = $this->ingestion->ingestFiles($files, $batchSize, null, $dryRun, 'import-service');
        }

        $totalAccepted = (int) ($helmetResult['accepted'] ?? 0) + $brandAccepted;
        $totalRejected = (int) ($helmetResult['rejected'] ?? 0) + $brandRejected;
        $totalSkipped = (int) ($helmetResult['skipped'] ?? 0) + $brandSkipped;

        if ($files === [] && $brandAccepted === 0 && $brandRejected === 0 && $brandSkipped === 0) {
            return [
                'ok'      => false,
                'message' => 'No temporary import files generated',
            ];
        }

        $result = [
            'ok' => true,
            'import_file' => $filePath,
            'records' => count($records),
            'accepted' => $totalAccepted,
            'rejected' => $totalRejected,
            'skipped' => $totalSkipped,
            'dry_run' => $dryRun,
            'helmet' => $helmetResult,
            'brand' => [
                'accepted' => $brandAccepted,
                'rejected' => $brandRejected,
                'skipped' => $brandSkipped,
            ],
        ];

        return $result;
    }

    /**
     * @param array<string,mixed>|array<int,mixed> $decoded
     * @return array<int, array<string,mixed>>
     */
    private function normalizeRecords(array $decoded): array
    {
        if ($decoded === []) {
            return [];
        }

        $isList = array_keys($decoded) === range(0, count($decoded) - 1);
        if ($isList) {
            $rows = [];
            foreach ($decoded as $item) {
                if (is_array($item)) {
                    $rows[] = $item;
                }
            }
            return $rows;
        }

        return [$decoded];
    }

    /**
     * @param array<string,mixed> $record
     */
    private function detectEntity(array $record): string
    {
        $entity = isset($record['entity']) ? sanitize_key((string) $record['entity']) : '';
        if (in_array($entity, ['helmet', 'brand'], true)) {
            return $entity;
        }

        if (isset($record['profile']) && is_array($record['profile'])) {
            return 'brand';
        }

        return 'helmet';
    }
}
