<?php

declare(strict_types=1);

namespace Helmetsan\Core\ImportExport;

use Helmetsan\Core\Accessory\AccessoryService;
use Helmetsan\Core\Brands\BrandService;
use Helmetsan\Core\Commerce\CommerceService;
use Helmetsan\Core\Comparison\ComparisonService;
use Helmetsan\Core\Dealer\DealerService;
use Helmetsan\Core\Distributor\DistributorService;
use Helmetsan\Core\Ingestion\IngestionService;
use Helmetsan\Core\Motorcycle\MotorcycleService;
use Helmetsan\Core\Recommendation\RecommendationService;
use Helmetsan\Core\SafetyStandard\SafetyStandardService;
use Helmetsan\Core\Support\Config;

final class ImportService
{
    public function __construct(
        private readonly IngestionService $ingestion,
        private readonly Config $config,
        private readonly BrandService $brands,
        private readonly AccessoryService $accessories,
        private readonly MotorcycleService $motorcycles,
        private readonly SafetyStandardService $safetyStandards,
        private readonly DealerService $dealers,
        private readonly DistributorService $distributors,
        private readonly ComparisonService $comparisons,
        private readonly RecommendationService $recommendations,
        private readonly CommerceService $commerce
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
        $accessoryAccepted = 0;
        $accessoryRejected = 0;
        $accessorySkipped = 0;
        $motorcycleAccepted = 0;
        $motorcycleRejected = 0;
        $motorcycleSkipped = 0;
        $safetyAccepted = 0;
        $safetyRejected = 0;
        $safetySkipped = 0;
        $dealerAccepted = 0;
        $dealerRejected = 0;
        $dealerSkipped = 0;
        $distributorAccepted = 0;
        $distributorRejected = 0;
        $distributorSkipped = 0;
        $comparisonAccepted = 0;
        $comparisonRejected = 0;
        $comparisonSkipped = 0;
        $recommendationAccepted = 0;
        $recommendationRejected = 0;
        $recommendationSkipped = 0;
        $commerceAccepted = 0;
        $commerceRejected = 0;
        $commerceSkipped = 0;

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
            if ($entity === 'accessory') {
                $result = $this->accessories->upsertFromPayload($record, $filePath, $dryRun);
                if (! empty($result['ok'])) {
                    $action = isset($result['action']) ? (string) $result['action'] : '';
                    if ($action === 'skipped' || $action === 'dry-run') {
                        $accessorySkipped++;
                    } else {
                        $accessoryAccepted++;
                    }
                } else {
                    $accessoryRejected++;
                }
                continue;
            }
            if ($entity === 'motorcycle') {
                $result = $this->motorcycles->upsertFromPayload($record, $filePath, $dryRun);
                if (! empty($result['ok'])) {
                    $action = isset($result['action']) ? (string) $result['action'] : '';
                    if ($action === 'skipped' || $action === 'dry-run') {
                        $motorcycleSkipped++;
                    } else {
                        $motorcycleAccepted++;
                    }
                } else {
                    $motorcycleRejected++;
                }
                continue;
            }
            if ($entity === 'safety_standard') {
                $result = $this->safetyStandards->upsertFromPayload($record, $filePath, $dryRun);
                if (! empty($result['ok'])) {
                    $action = isset($result['action']) ? (string) $result['action'] : '';
                    if ($action === 'skipped' || $action === 'dry-run') {
                        $safetySkipped++;
                    } else {
                        $safetyAccepted++;
                    }
                } else {
                    $safetyRejected++;
                }
                continue;
            }
            if ($entity === 'dealer') {
                $result = $this->dealers->upsertFromPayload($record, $filePath, $dryRun);
                if (! empty($result['ok'])) {
                    $action = isset($result['action']) ? (string) $result['action'] : '';
                    if ($action === 'skipped' || $action === 'dry-run') {
                        $dealerSkipped++;
                    } else {
                        $dealerAccepted++;
                    }
                } else {
                    $dealerRejected++;
                }
                continue;
            }
            if ($entity === 'distributor') {
                $result = $this->distributors->upsertFromPayload($record, $filePath, $dryRun);
                if (! empty($result['ok'])) {
                    $action = isset($result['action']) ? (string) $result['action'] : '';
                    if ($action === 'skipped' || $action === 'dry-run') {
                        $distributorSkipped++;
                    } else {
                        $distributorAccepted++;
                    }
                } else {
                    $distributorRejected++;
                }
                continue;
            }
            if ($entity === 'comparison') {
                $result = $this->comparisons->upsertFromPayload($record, $filePath, $dryRun);
                if (! empty($result['ok'])) {
                    $action = isset($result['action']) ? (string) $result['action'] : '';
                    if ($action === 'skipped' || $action === 'dry-run') {
                        $comparisonSkipped++;
                    } else {
                        $comparisonAccepted++;
                    }
                } else {
                    $comparisonRejected++;
                }
                continue;
            }
            if ($entity === 'recommendation') {
                $result = $this->recommendations->upsertFromPayload($record, $filePath, $dryRun);
                if (! empty($result['ok'])) {
                    $action = isset($result['action']) ? (string) $result['action'] : '';
                    if ($action === 'skipped' || $action === 'dry-run') {
                        $recommendationSkipped++;
                    } else {
                        $recommendationAccepted++;
                    }
                } else {
                    $recommendationRejected++;
                }
                continue;
            }
            if (in_array($entity, ['currency', 'marketplace', 'pricing', 'offer'], true)) {
                $result = $this->commerce->upsertFromPayload($record, $filePath, $dryRun);
                if (! empty($result['ok'])) {
                    $action = isset($result['action']) ? (string) $result['action'] : '';
                    if ($action === 'skipped' || $action === 'dry-run') {
                        $commerceSkipped++;
                    } else {
                        $commerceAccepted++;
                    }
                } else {
                    $commerceRejected++;
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

        $totalAccepted = (int) ($helmetResult['accepted'] ?? 0) + $brandAccepted + $accessoryAccepted + $motorcycleAccepted + $safetyAccepted + $dealerAccepted + $distributorAccepted + $comparisonAccepted + $recommendationAccepted + $commerceAccepted;
        $totalRejected = (int) ($helmetResult['rejected'] ?? 0) + $brandRejected + $accessoryRejected + $motorcycleRejected + $safetyRejected + $dealerRejected + $distributorRejected + $comparisonRejected + $recommendationRejected + $commerceRejected;
        $totalSkipped = (int) ($helmetResult['skipped'] ?? 0) + $brandSkipped + $accessorySkipped + $motorcycleSkipped + $safetySkipped + $dealerSkipped + $distributorSkipped + $comparisonSkipped + $recommendationSkipped + $commerceSkipped;

        if ($files === [] && $brandAccepted === 0 && $brandRejected === 0 && $brandSkipped === 0 && $accessoryAccepted === 0 && $accessoryRejected === 0 && $accessorySkipped === 0 && $motorcycleAccepted === 0 && $motorcycleRejected === 0 && $motorcycleSkipped === 0 && $safetyAccepted === 0 && $safetyRejected === 0 && $safetySkipped === 0 && $dealerAccepted === 0 && $dealerRejected === 0 && $dealerSkipped === 0 && $distributorAccepted === 0 && $distributorRejected === 0 && $distributorSkipped === 0 && $comparisonAccepted === 0 && $comparisonRejected === 0 && $comparisonSkipped === 0 && $recommendationAccepted === 0 && $recommendationRejected === 0 && $recommendationSkipped === 0 && $commerceAccepted === 0 && $commerceRejected === 0 && $commerceSkipped === 0) {
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
            'accessory' => [
                'accepted' => $accessoryAccepted,
                'rejected' => $accessoryRejected,
                'skipped' => $accessorySkipped,
            ],
            'motorcycle' => [
                'accepted' => $motorcycleAccepted,
                'rejected' => $motorcycleRejected,
                'skipped' => $motorcycleSkipped,
            ],
            'safety_standard' => [
                'accepted' => $safetyAccepted,
                'rejected' => $safetyRejected,
                'skipped' => $safetySkipped,
            ],
            'dealer' => [
                'accepted' => $dealerAccepted,
                'rejected' => $dealerRejected,
                'skipped' => $dealerSkipped,
            ],
            'distributor' => [
                'accepted' => $distributorAccepted,
                'rejected' => $distributorRejected,
                'skipped' => $distributorSkipped,
            ],
            'comparison' => [
                'accepted' => $comparisonAccepted,
                'rejected' => $comparisonRejected,
                'skipped' => $comparisonSkipped,
            ],
            'recommendation' => [
                'accepted' => $recommendationAccepted,
                'rejected' => $recommendationRejected,
                'skipped' => $recommendationSkipped,
            ],
            'commerce' => [
                'accepted' => $commerceAccepted,
                'rejected' => $commerceRejected,
                'skipped' => $commerceSkipped,
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
        if (in_array($entity, ['helmet', 'brand', 'accessory', 'motorcycle', 'safety_standard', 'dealer', 'distributor', 'comparison', 'recommendation', 'currency', 'marketplace', 'pricing', 'offer'], true)) {
            return $entity;
        }

        if (isset($record['profile']) && is_array($record['profile'])) {
            return 'brand';
        }

        return 'helmet';
    }
}
