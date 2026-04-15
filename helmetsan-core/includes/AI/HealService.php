<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI;

use Helmetsan\Core\Ingestion\IngestionService;
use Helmetsan\Core\Repository\JsonRepository;
use Helmetsan\Core\Support\Logger;

/**
 * Service to execute data healing: applying patches to master files and triggering ingestion.
 */
final class HealService
{
    public function __construct(
        private readonly JsonRepository $repository,
        private readonly IngestionService $ingestion,
        private readonly HealRepository $heals,
        private readonly Logger $logger
    ) {
    }

    /**
     * Commits a staged correction to the master data file and refreshes the database.
     *
     * @param string $fileName The correction file name in data/corrections/
     * @param array|null $manualContent Optional manual override of the AI's content
     * @return array{ok: bool, message: string}
     */
    public function commitCorrection(string $fileName, ?array $manualContent = null): array
    {
        $root = $this->repository->rootPath();
        $corrPath = $root . '/corrections/' . $fileName;

        if (! file_exists($corrPath)) {
            return ['ok' => false, 'message' => 'Correction file not found: ' . $fileName];
        }

        // 1. Load the corrected data (either from file or manual override)
        $correctedData = $manualContent ?? $this->repository->read($corrPath);
        if ($correctedData === []) {
            return ['ok' => false, 'message' => 'Invalid corrected data.'];
        }

        $entityId = (string) ($correctedData['id'] ?? '');
        if ($entityId === '') {
            return ['ok' => false, 'message' => 'Correction data missing ID.'];
        }

        // 2. Resolve Master File Path
        // The correction file name is expected to be {entity}_{id}.json
        $parts = explode('_', str_replace('.json', '', $fileName));
        $type = $parts[0] ?? 'helmet';
        
        // Find the master file
        $masterPath = $this->findMasterFile($type, $entityId);
        if (! $masterPath) {
            return ['ok' => false, 'message' => "Master file for {$type} {$entityId} not found."];
        }

        // 3. Apply Patch
        $currentMasterData = $this->repository->read($masterPath);
        $originalState = $currentMasterData;
        
        // Merge correction into master
        $updatedMasterData = array_replace_recursive($currentMasterData, $correctedData);
        
        $success = file_put_contents(
            $masterPath, 
            wp_json_encode($updatedMasterData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE)
        ) !== false;

        if (! $success) {
            return ['ok' => false, 'message' => 'Failed to write to master file.'];
        }

        // 4. Trace in Database
        $this->heals->logHeal([
            'entity_type'     => $type,
            'item_id'         => $entityId,
            'file_path'       => $masterPath,
            'issues'          => 'Manual Correction Review',
            'fix_patch'       => $correctedData,
            'original_values' => $originalState,
            'ai_mode'         => 'manual_review',
            'applied'         => 1
        ]);

        // 5. Auto-Ingest
        $ingestResult = $this->ingestion->ingestFiles([$masterPath]);

        // 6. Cleanup Correction File
        @unlink($corrPath);

        $this->logger->info("Manually committed correction for {$type} {$entityId}. Master updated and ingested.");

        return [
            'ok'      => true, 
            'message' => "Successfully committed {$type} {$entityId} to master. Ingestion: " . ($ingestResult['ok'] ? 'Success' : 'Failed')
        ];
    }

    /**
     * Resolve a master file path based on type and ID.
     */
    private function findMasterFile(string $type, string $id): ?string
    {
        $root = $this->repository->rootPath();
        
        // Most entities are stored in split files: data/{type}/{id}.json
        // Categories: helmets, brands, accessories, dealers, etc.
        $standardPath = $root . '/' . $this->pluralize($type) . '/' . $id . '.json';
        if (file_exists($standardPath)) {
            return $standardPath;
        }

        // Fallback: search subdirectories
        $files = $this->repository->listJsonFiles($this->pluralize($type));
        foreach ($files as $f) {
            if (basename($f) === $id . '.json') {
                return $f;
            }
        }

        return null;
    }

    private function pluralize(string $type): string
    {
        if (str_ends_with($type, 'y')) {
            return substr($type, 0, -1) . 'ies';
        }
        return $type . 's';
    }
}
