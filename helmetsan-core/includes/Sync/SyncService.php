<?php

declare(strict_types=1);

namespace Helmetsan\Core\Sync;

use Helmetsan\Core\Accessory\AccessoryService;
use Helmetsan\Core\Brands\BrandService;
use Helmetsan\Core\Ingestion\IngestionService;
use Helmetsan\Core\Motorcycle\MotorcycleService;
use Helmetsan\Core\Repository\JsonRepository;
use Helmetsan\Core\SafetyStandard\SafetyStandardService;
use Helmetsan\Core\Support\Config;
use Helmetsan\Core\Support\Logger;

final class SyncService
{
    public function __construct(
        private readonly JsonRepository $repository,
        private readonly Logger $logger,
        private readonly Config $config,
        private readonly LogRepository $logs,
        private readonly ?BrandService $brands = null,
        private readonly ?IngestionService $ingestion = null,
        private readonly ?AccessoryService $accessories = null,
        private readonly ?MotorcycleService $motorcycles = null,
        private readonly ?SafetyStandardService $safetyStandards = null
    ) {
    }

    public function pull(
        int $limit = 500,
        bool $dryRun = false,
        ?string $pathOverride = null,
        ?bool $applyBrands = null,
        ?bool $applyHelmets = null,
        ?string $profile = null,
        array $audit = []
    ): array
    {
        $cfg = $this->config->githubConfig();
        $check = $this->validateConfig($cfg);
        if (! $check['ok']) {
            $this->logSync('pull', 'error', [
                'mode'       => 'pull',
                'message'    => (string) ($check['message'] ?? 'Sync config validation failed'),
                'remote_path'=> (string) ($cfg['remote_path'] ?? ''),
            ]);
            return $check;
        }

        $branch     = (string) $cfg['branch'];
        $remoteBase = $this->normalizedRemoteBase($pathOverride ?? (string) $cfg['remote_path']);
        $jsonOnly   = ! empty($cfg['sync_json_only']);
        $savedProfile = $this->sanitizeProfile((string) ($cfg['sync_run_profile'] ?? 'pull-only'));
        $profileLock = ! empty($cfg['sync_profile_lock']);
        $requestedProfile = $profile !== null ? $this->sanitizeProfile($profile) : null;

        $profileName = $savedProfile;
        $profileSource = 'saved';
        if ($requestedProfile !== null) {
            if ($profileLock) {
                $profileSource = 'locked_saved';
            } else {
                $profileName = $requestedProfile;
                $profileSource = 'override';
            }
        }

        $flags = $this->resolvePullApplyFlags($profileName, $applyBrands, $applyHelmets, $profileLock);
        $applyBrands = $flags['apply_brands'];
        $applyHelmets = $flags['apply_helmets'];

        $ref = $this->request('GET', $this->apiBase($cfg) . '/git/ref/heads/' . rawurlencode($branch), $cfg);
        if (! $ref['ok']) {
            $this->logSync('pull', 'error', [
                'mode'       => 'pull',
                'branch'     => $branch,
                'remote_path'=> $remoteBase,
                'message'    => (string) ($ref['message'] ?? 'Failed to fetch branch ref'),
                'payload'    => $ref,
            ]);
            return $ref;
        }

        $sha = $ref['data']['object']['sha'] ?? '';
        if (! is_string($sha) || $sha === '') {
            $error = ['ok' => false, 'message' => 'Unable to resolve branch SHA'];
            $this->logSync('pull', 'error', [
                'mode'       => 'pull',
                'branch'     => $branch,
                'remote_path'=> $remoteBase,
                'message'    => (string) $error['message'],
                'payload'    => $ref,
            ]);
            return $error;
        }

        $tree = $this->request('GET', $this->apiBase($cfg) . '/git/trees/' . rawurlencode($sha) . '?recursive=1', $cfg);
        if (! $tree['ok']) {
            $this->logSync('pull', 'error', [
                'mode'       => 'pull',
                'branch'     => $branch,
                'remote_path'=> $remoteBase,
                'message'    => (string) ($tree['message'] ?? 'Failed to fetch repository tree'),
                'payload'    => $tree,
            ]);
            return $tree;
        }

        $items = $tree['data']['tree'] ?? [];
        if (! is_array($items)) {
            $error = ['ok' => false, 'message' => 'Invalid tree response'];
            $this->logSync('pull', 'error', [
                'mode'       => 'pull',
                'branch'     => $branch,
                'remote_path'=> $remoteBase,
                'message'    => (string) $error['message'],
                'payload'    => $tree,
            ]);
            return $error;
        }

        $selected = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $type = isset($item['type']) ? (string) $item['type'] : '';
            $path = isset($item['path']) ? (string) $item['path'] : '';
            if ($type !== 'blob' || $path === '') {
                continue;
            }
            if ($remoteBase !== '' && strpos($path, $remoteBase . '/') !== 0 && $path !== $remoteBase) {
                continue;
            }
            if ($jsonOnly && strtolower((string) pathinfo($path, PATHINFO_EXTENSION)) !== 'json') {
                continue;
            }
            $selected[] = $item;
        }

        $selected = array_slice($selected, 0, max(1, $limit));

        $downloaded = 0;
        $failed     = 0;
        $brandFiles = [];
        $helmetFiles = [];
        $accessoryFiles = [];
        $motorcycleFiles = [];
        $safetyStandardFiles = [];
        foreach ($selected as $item) {
            $path = (string) ($item['path'] ?? '');
            $blobSha = (string) ($item['sha'] ?? '');
            if ($path === '' || $blobSha === '') {
                $failed++;
                continue;
            }

            $relative = $remoteBase !== '' ? ltrim(substr($path, strlen($remoteBase)), '/') : $path;
            $local    = trailingslashit($this->repository->rootPath()) . $relative;

            if ($dryRun) {
                $downloaded++;
                continue;
            }

            $blob = $this->request('GET', $this->apiBase($cfg) . '/git/blobs/' . rawurlencode($blobSha), $cfg);
            if (! $blob['ok']) {
                $failed++;
                continue;
            }

            $encoding = isset($blob['data']['encoding']) ? (string) $blob['data']['encoding'] : '';
            $content  = isset($blob['data']['content']) ? (string) $blob['data']['content'] : '';
            if ($encoding !== 'base64' || $content === '') {
                $failed++;
                continue;
            }

            $decoded = base64_decode(str_replace("\n", '', $content), true);
            if (! is_string($decoded)) {
                $failed++;
                continue;
            }

            $dir = dirname($local);
            if (! is_dir($dir)) {
                wp_mkdir_p($dir);
            }

            $written = file_put_contents($local, $decoded);
            if ($written === false) {
                $failed++;
                continue;
            }

            $downloaded++;
            if ($this->isBrandFile($relative)) {
                $brandFiles[] = $local;
            } elseif ($this->isHelmetFile($relative)) {
                $helmetFiles[] = $local;
            } elseif ($this->isAccessoryFile($relative)) {
                $accessoryFiles[] = $local;
            } elseif ($this->isMotorcycleFile($relative)) {
                $motorcycleFiles[] = $local;
            } elseif ($this->isSafetyStandardFile($relative)) {
                $safetyStandardFiles[] = $local;
            }
        }

        $brandApply = [
            'enabled' => $applyBrands,
            'files' => count($brandFiles),
            'processed' => 0,
            'accepted' => 0,
            'skipped' => 0,
            'rejected' => 0,
            'failed' => 0,
        ];
        if ($applyBrands && $brandFiles !== []) {
            $brandApply = $this->applyBrandFiles($brandFiles, $dryRun);
            $failed += (int) ($brandApply['failed'] ?? 0) + (int) ($brandApply['rejected'] ?? 0);
        }

        $helmetApply = [
            'enabled' => $applyHelmets,
            'files' => count($helmetFiles),
            'processed' => 0,
            'accepted' => 0,
            'skipped' => 0,
            'rejected' => 0,
            'failed' => 0,
        ];
        $accessoryApply = [
            'enabled' => $applyHelmets,
            'files' => count($accessoryFiles),
            'processed' => 0,
            'accepted' => 0,
            'skipped' => 0,
            'rejected' => 0,
            'failed' => 0,
        ];
        $motorcycleApply = [
            'enabled' => $applyHelmets,
            'files' => count($motorcycleFiles),
            'processed' => 0,
            'accepted' => 0,
            'skipped' => 0,
            'rejected' => 0,
            'failed' => 0,
        ];
        $safetyStandardApply = [
            'enabled' => $applyHelmets,
            'files' => count($safetyStandardFiles),
            'processed' => 0,
            'accepted' => 0,
            'skipped' => 0,
            'rejected' => 0,
            'failed' => 0,
        ];
        if ($applyHelmets && $helmetFiles !== []) {
            $helmetApply = $this->applyHelmetFiles($helmetFiles, $dryRun);
            $failed += (int) ($helmetApply['failed'] ?? 0) + (int) ($helmetApply['rejected'] ?? 0);
        }
        if ($applyHelmets && $accessoryFiles !== []) {
            $accessoryApply = $this->applyAccessoryFiles($accessoryFiles, $dryRun);
            $failed += (int) ($accessoryApply['failed'] ?? 0) + (int) ($accessoryApply['rejected'] ?? 0);
        }
        if ($applyHelmets && $motorcycleFiles !== []) {
            $motorcycleApply = $this->applyMotorcycleFiles($motorcycleFiles, $dryRun);
            $failed += (int) ($motorcycleApply['failed'] ?? 0) + (int) ($motorcycleApply['rejected'] ?? 0);
        }
        if ($applyHelmets && $safetyStandardFiles !== []) {
            $safetyStandardApply = $this->applySafetyStandardFiles($safetyStandardFiles, $dryRun);
            $failed += (int) ($safetyStandardApply['failed'] ?? 0) + (int) ($safetyStandardApply['rejected'] ?? 0);
        }

        $result = [
            'ok'         => true,
            'action'     => 'pull',
            'dry_run'    => $dryRun,
            'selected'   => count($selected),
            'downloaded' => $downloaded,
            'failed'     => $failed,
            'branch'     => $branch,
            'remote_path'=> $remoteBase,
            'profile'    => $profileName,
            'profile_saved' => $savedProfile,
            'profile_requested' => $requestedProfile,
            'profile_source' => $profileSource,
            'profile_locked' => $profileLock,
            'audit' => $audit,
            'brand_auto_apply' => $brandApply,
            'helmet_auto_apply' => $helmetApply,
            'accessory_auto_apply' => $accessoryApply,
            'motorcycle_auto_apply' => $motorcycleApply,
            'safety_standard_auto_apply' => $safetyStandardApply,
        ];

        $this->logSync('pull', $failed > 0 ? 'partial' : 'success', [
            'mode'        => 'pull',
            'branch'      => $branch,
            'target_branch'=> $branch,
            'remote_path' => $remoteBase,
            'processed'   => count($selected),
            'pushed'      => $downloaded,
            'failed'      => $failed,
            'message'     => 'Pull completed (' . $profileSource . ')',
            'payload'     => $result,
        ]);

        return $result;
    }

    /**
     * @param array<int,string> $files
     * @return array<string,mixed>
     */
    private function applyBrandFiles(array $files, bool $dryRun): array
    {
        $result = [
            'enabled' => true,
            'files' => count($files),
            'processed' => 0,
            'accepted' => 0,
            'skipped' => 0,
            'rejected' => 0,
            'failed' => 0,
        ];

        if (! $this->brands instanceof BrandService) {
            $result['failed'] = count($files);
            $result['message'] = 'Brand service unavailable';
            return $result;
        }

        foreach ($files as $file) {
            $data = $this->repository->read($file);
            if ($data === []) {
                $result['rejected']++;
                continue;
            }

            if (! isset($data['entity']) && isset($data['profile']) && is_array($data['profile'])) {
                $data['entity'] = 'brand';
            }

            $isBrand = isset($data['entity']) && sanitize_key((string) $data['entity']) === 'brand';
            if (! $isBrand) {
                $result['skipped']++;
                continue;
            }

            $upsert = $this->brands->upsertFromPayload($data, $file, $dryRun);
            if (! empty($upsert['ok'])) {
                $action = isset($upsert['action']) ? (string) $upsert['action'] : '';
                if ($action === 'skipped' || $action === 'dry-run') {
                    $result['skipped']++;
                } else {
                    $result['accepted']++;
                }
            } else {
                $result['failed']++;
            }
            $result['processed']++;
        }

        return $result;
    }

    private function isBrandFile(string $relativePath): bool
    {
        $path = str_replace('\\', '/', strtolower($relativePath));
        return strpos('/' . ltrim($path, '/'), '/brands/') !== false;
    }

    private function isHelmetFile(string $relativePath): bool
    {
        $path = str_replace('\\', '/', strtolower($relativePath));
        return strpos('/' . ltrim($path, '/'), '/helmets/') !== false;
    }

    private function isAccessoryFile(string $relativePath): bool
    {
        $path = str_replace('\\', '/', strtolower($relativePath));
        return strpos('/' . ltrim($path, '/'), '/accessories/') !== false;
    }

    private function isMotorcycleFile(string $relativePath): bool
    {
        $path = str_replace('\\', '/', strtolower($relativePath));
        return strpos('/' . ltrim($path, '/'), '/motorcycles/') !== false;
    }

    private function isSafetyStandardFile(string $relativePath): bool
    {
        $path = str_replace('\\', '/', strtolower($relativePath));
        return strpos('/' . ltrim($path, '/'), '/safety-standards/') !== false;
    }

    private function isPushExcludedPath(string $relativePath): bool
    {
        $path = str_replace('\\', '/', ltrim($relativePath, '/'));
        $segments = explode('/', strtolower($path));

        foreach ($segments as $segment) {
            if ($segment === '_imports' || $segment === '_exports' || $segment === '_bootstrap' || $segment === 'helmetsan-runtime') {
                return true;
            }
        }

        return false;
    }

    private function sanitizeProfile(string $profile): string
    {
        $profile = strtolower(trim($profile));
        if (! in_array($profile, ['pull-only', 'pull+brands', 'pull+all'], true)) {
            return 'pull-only';
        }

        return $profile;
    }

    /**
     * @return array{apply_brands: bool, apply_helmets: bool}
     */
    private function resolvePullApplyFlags(string $profile, ?bool $applyBrands, ?bool $applyHelmets, bool $profileLock): array
    {
        $defaults = match ($profile) {
            'pull+brands' => ['apply_brands' => true, 'apply_helmets' => false],
            'pull+all' => ['apply_brands' => true, 'apply_helmets' => true],
            default => ['apply_brands' => false, 'apply_helmets' => false],
        };

        if ($profileLock) {
            return $defaults;
        }

        if ($applyBrands !== null) {
            $defaults['apply_brands'] = $applyBrands;
        }
        if ($applyHelmets !== null) {
            $defaults['apply_helmets'] = $applyHelmets;
        }

        return $defaults;
    }

    /**
     * @param array<int,string> $files
     * @return array<string,mixed>
     */
    private function applyHelmetFiles(array $files, bool $dryRun): array
    {
        $result = [
            'enabled' => true,
            'files' => count($files),
            'processed' => 0,
            'accepted' => 0,
            'skipped' => 0,
            'rejected' => 0,
            'failed' => 0,
        ];

        if (! $this->ingestion instanceof IngestionService) {
            $result['failed'] = count($files);
            $result['message'] = 'Ingestion service unavailable';
            return $result;
        }

        $helmetFiles = [];
        foreach ($files as $file) {
            $relative = ltrim(str_replace($this->repository->rootPath(), '', $file), '/');
            if (! $this->isHelmetFile($relative)) {
                $result['skipped']++;
                continue;
            }
            $data = $this->repository->read($file);
            if ($data === []) {
                $result['rejected']++;
                continue;
            }
            $entity = isset($data['entity']) ? sanitize_key((string) $data['entity']) : '';
            if ($entity !== 'helmet') {
                $result['skipped']++;
                continue;
            }
            $helmetFiles[] = $file;
        }

        if ($helmetFiles === []) {
            return $result;
        }

        $ingested = $this->ingestion->ingestFiles($helmetFiles, 100, null, $dryRun, 'sync-pull-auto-apply-helmets');

        $result['processed'] += (int) ($ingested['processed'] ?? 0);
        $result['accepted']  = (int) ($ingested['accepted'] ?? 0);
        $result['skipped']   += (int) ($ingested['skipped'] ?? 0);
        $result['rejected']  += (int) ($ingested['rejected'] ?? 0);
        $result['failed']    = ! empty($ingested['ok']) ? 0 : 1;
        $result['ingestion'] = $ingested;

        return $result;
    }

    /**
     * @param array<int,string> $files
     * @return array<string,mixed>
     */
    private function applyAccessoryFiles(array $files, bool $dryRun): array
    {
        $result = [
            'enabled' => true,
            'files' => count($files),
            'processed' => 0,
            'accepted' => 0,
            'skipped' => 0,
            'rejected' => 0,
            'failed' => 0,
        ];

        if (! $this->accessories instanceof AccessoryService) {
            $result['failed'] = count($files);
            $result['message'] = 'Accessory service unavailable';
            return $result;
        }

        foreach ($files as $file) {
            $data = $this->repository->read($file);
            if ($data === []) {
                $result['rejected']++;
                continue;
            }
            if (sanitize_key((string) ($data['entity'] ?? '')) !== 'accessory') {
                $result['skipped']++;
                continue;
            }
            $upsert = $this->accessories->upsertFromPayload($data, $file, $dryRun);
            if (! empty($upsert['ok'])) {
                $action = isset($upsert['action']) ? (string) $upsert['action'] : '';
                if ($action === 'skipped' || $action === 'dry-run') {
                    $result['skipped']++;
                } else {
                    $result['accepted']++;
                }
            } else {
                $result['failed']++;
            }
            $result['processed']++;
        }

        return $result;
    }

    /**
     * @param array<int,string> $files
     * @return array<string,mixed>
     */
    private function applyMotorcycleFiles(array $files, bool $dryRun): array
    {
        $result = [
            'enabled' => true,
            'files' => count($files),
            'processed' => 0,
            'accepted' => 0,
            'skipped' => 0,
            'rejected' => 0,
            'failed' => 0,
        ];

        if (! $this->motorcycles instanceof MotorcycleService) {
            $result['failed'] = count($files);
            $result['message'] = 'Motorcycle service unavailable';
            return $result;
        }

        foreach ($files as $file) {
            $data = $this->repository->read($file);
            if ($data === []) {
                $result['rejected']++;
                continue;
            }
            if (sanitize_key((string) ($data['entity'] ?? '')) !== 'motorcycle') {
                $result['skipped']++;
                continue;
            }
            $upsert = $this->motorcycles->upsertFromPayload($data, $file, $dryRun);
            if (! empty($upsert['ok'])) {
                $action = isset($upsert['action']) ? (string) $upsert['action'] : '';
                if ($action === 'skipped' || $action === 'dry-run') {
                    $result['skipped']++;
                } else {
                    $result['accepted']++;
                }
            } else {
                $result['failed']++;
            }
            $result['processed']++;
        }

        return $result;
    }

    /**
     * @param array<int,string> $files
     * @return array<string,mixed>
     */
    private function applySafetyStandardFiles(array $files, bool $dryRun): array
    {
        $result = [
            'enabled' => true,
            'files' => count($files),
            'processed' => 0,
            'accepted' => 0,
            'skipped' => 0,
            'rejected' => 0,
            'failed' => 0,
        ];

        if (! $this->safetyStandards instanceof SafetyStandardService) {
            $result['failed'] = count($files);
            $result['message'] = 'Safety standard service unavailable';
            return $result;
        }

        foreach ($files as $file) {
            $data = $this->repository->read($file);
            if ($data === []) {
                $result['rejected']++;
                continue;
            }
            if (sanitize_key((string) ($data['entity'] ?? '')) !== 'safety_standard') {
                $result['skipped']++;
                continue;
            }
            $upsert = $this->safetyStandards->upsertFromPayload($data, $file, $dryRun);
            if (! empty($upsert['ok'])) {
                $action = isset($upsert['action']) ? (string) $upsert['action'] : '';
                if ($action === 'skipped' || $action === 'dry-run') {
                    $result['skipped']++;
                } else {
                    $result['accepted']++;
                }
            } else {
                $result['failed']++;
            }
            $result['processed']++;
        }

        return $result;
    }

    public function push(
        int $limit = 500,
        bool $dryRun = false,
        ?string $pathOverride = null,
        ?string $modeOverride = null,
        ?string $prTitle = null,
        ?bool $autoMergeOverride = null
    ): array
    {
        $cfg = $this->config->githubConfig();
        $check = $this->validateConfig($cfg);
        if (! $check['ok']) {
            $this->logSync('push', 'error', [
                'mode'       => 'push',
                'message'    => (string) ($check['message'] ?? 'Sync config validation failed'),
                'remote_path'=> (string) ($cfg['remote_path'] ?? ''),
            ]);
            return $check;
        }

        $branch     = (string) $cfg['branch'];
        $remoteBase = $this->normalizedRemoteBase($pathOverride ?? (string) $cfg['remote_path']);
        $mode       = $modeOverride !== null && $modeOverride !== '' ? $modeOverride : (string) ($cfg['push_mode'] ?? 'commit');
        $mode       = in_array($mode, ['commit', 'pr'], true) ? $mode : 'commit';
        $targetBranch = $branch;
        $autoMerge = $autoMergeOverride !== null ? $autoMergeOverride : ! empty($cfg['pr_auto_merge']);
        $reusedPr = null;

        if ($mode === 'pr' && ! $dryRun) {
            $prefix = (string) ($cfg['pr_branch_prefix'] ?? 'helmetsan-sync');
            $prefix = sanitize_title($prefix);

            if (! empty($cfg['pr_reuse_open'])) {
                $existingPr = $this->findReusableOpenPullRequest($cfg, $branch, $prefix);
                if ($existingPr['ok']) {
                    $targetBranch = (string) ($existingPr['branch'] ?? $targetBranch);
                    $reusedPr = [
                        'number' => $existingPr['number'] ?? null,
                        'url'    => $existingPr['url'] ?? null,
                        'reused' => true,
                    ];
                }
            }

            if ($reusedPr === null) {
                $targetBranch = $prefix . '-' . gmdate('Ymd-His');
                $created = $this->createBranchFromBase($cfg, $branch, $targetBranch);
                if (! $created['ok']) {
                    $this->logSync('push', 'error', [
                        'mode'         => $mode,
                        'branch'       => $branch,
                        'target_branch'=> $targetBranch,
                        'remote_path'  => $remoteBase,
                        'message'      => (string) ($created['message'] ?? 'Failed to create PR branch'),
                        'payload'      => $created,
                    ]);
                    return $created;
                }
            }
        }

        $files = $this->repository->listJsonFiles();
        $files = array_slice($files, 0, max(1, $limit));

        $remoteMapResult = $this->loadRemoteTreeMap($cfg, $branch);
        if (! $remoteMapResult['ok']) {
            $this->logSync('push', 'error', [
                'mode'         => $mode,
                'branch'       => $branch,
                'target_branch'=> $targetBranch,
                'remote_path'  => $remoteBase,
                'message'      => (string) ($remoteMapResult['message'] ?? 'Failed to load remote tree'),
                'payload'      => $remoteMapResult,
            ]);
            return $remoteMapResult;
        }
        /** @var array<string,string> $remoteMap */
        $remoteMap = is_array($remoteMapResult['map'] ?? null) ? $remoteMapResult['map'] : [];

        $pushed = 0;
        $failed = 0;
        $skipped = 0;

        foreach ($files as $file) {
            $relative = ltrim(str_replace($this->repository->rootPath(), '', $file), '/');
            if ($this->isPushExcludedPath($relative)) {
                $skipped++;
                continue;
            }
            $remotePath = $remoteBase !== '' ? $remoteBase . '/' . $relative : $relative;

            $raw = file_get_contents($file);
            if ($raw === false) {
                $failed++;
                continue;
            }

            $localBlobSha = $this->gitBlobSha($raw);
            $remoteSha    = isset($remoteMap[$remotePath]) ? (string) $remoteMap[$remotePath] : '';

            if ($remoteSha !== '' && hash_equals($remoteSha, $localBlobSha)) {
                $skipped++;
                continue;
            }

            if ($dryRun) {
                $pushed++;
                continue;
            }

            $payload = [
                'message' => 'Helmetsan sync push: ' . $relative,
                'content' => base64_encode($raw),
                'branch'  => $targetBranch,
            ];

            if ($remoteSha !== '') {
                $payload['sha'] = $remoteSha;
            }

            $write = $this->request(
                'PUT',
                $this->apiBase($cfg) . '/contents/' . $this->encodePath($remotePath),
                $cfg,
                $payload
            );

            if (! $write['ok']) {
                $failed++;
                continue;
            }

            $pushed++;
        }

        $pr = $reusedPr;
        if ($mode === 'pr' && ! $dryRun && $pushed > 0) {
            if ($reusedPr === null) {
                $prCreate = $this->createPullRequest(
                    $cfg,
                    $targetBranch,
                    $branch,
                    $prTitle !== null && $prTitle !== '' ? $prTitle : 'Helmetsan sync update ' . gmdate('Y-m-d H:i:s')
                );
                if ($prCreate['ok']) {
                    $pr = [
                        'number' => $prCreate['data']['number'] ?? null,
                        'url'    => $prCreate['data']['html_url'] ?? null,
                        'reused' => false,
                    ];
                } else {
                    $failed++;
                }
            }

            if ($autoMerge && is_array($pr) && ! empty($pr['number'])) {
                $merge = $this->mergePullRequest($cfg, (int) $pr['number'], (string) $targetBranch);
                $pr['auto_merged'] = (bool) ($merge['ok'] ?? false);
                if (! ($merge['ok'] ?? false)) {
                    $pr['merge_message'] = (string) ($merge['message'] ?? 'Merge failed');
                    $failed++;
                }
            }
        }

        $result = [
            'ok'          => true,
            'action'      => 'push',
            'mode'        => $mode,
            'dry_run'     => $dryRun,
            'processed'   => count($files),
            'pushed'      => $pushed,
            'skipped'     => $skipped,
            'failed'      => $failed,
            'branch'      => $branch,
            'target_branch'=> $targetBranch,
            'remote_path' => $remoteBase,
            'pull_request'=> $pr,
            'auto_merge'  => $autoMerge,
        ];

        $status = $failed > 0 ? 'partial' : 'success';
        $this->logSync('push', $status, [
            'mode'         => $mode,
            'branch'       => $branch,
            'target_branch'=> $targetBranch,
            'remote_path'  => $remoteBase,
            'processed'    => count($files),
            'pushed'       => $pushed,
            'skipped'      => $skipped,
            'failed'       => $failed,
            'message'      => 'Push completed',
            'payload'      => $result,
        ]);

        return $result;
    }

    /**
     * @param array<string,mixed> $cfg
     * @return array<string,mixed>
     */
    private function validateConfig(array $cfg): array
    {
        if (empty($cfg['enabled'])) {
            return ['ok' => false, 'message' => 'GitHub sync is disabled in settings'];
        }
        if (empty($cfg['owner']) || empty($cfg['repo']) || empty($cfg['token'])) {
            return ['ok' => false, 'message' => 'Missing GitHub owner/repo/token configuration'];
        }

        return ['ok' => true];
    }

    /**
     * @param array<string,mixed> $cfg
     */
    private function apiBase(array $cfg): string
    {
        return 'https://api.github.com/repos/' . rawurlencode((string) $cfg['owner']) . '/' . rawurlencode((string) $cfg['repo']);
    }

    private function normalizedRemoteBase(string $remotePath): string
    {
        return trim($remotePath, '/');
    }

    /**
     * @param array<string,mixed> $cfg
     * @return array<string,mixed>
     */
    private function loadRemoteTreeMap(array $cfg, string $branch): array
    {
        $ref = $this->request('GET', $this->apiBase($cfg) . '/git/ref/heads/' . rawurlencode($branch), $cfg);
        if (! $ref['ok']) {
            return $ref;
        }

        $branchSha = $ref['data']['object']['sha'] ?? '';
        if (! is_string($branchSha) || $branchSha === '') {
            return ['ok' => false, 'message' => 'Unable to resolve branch SHA for push'];
        }

        $tree = $this->request('GET', $this->apiBase($cfg) . '/git/trees/' . rawurlencode($branchSha) . '?recursive=1', $cfg);
        if (! $tree['ok']) {
            return $tree;
        }

        $items = $tree['data']['tree'] ?? [];
        if (! is_array($items)) {
            return ['ok' => false, 'message' => 'Invalid tree response for push'];
        }

        $map = [];
        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $type = isset($item['type']) ? (string) $item['type'] : '';
            $path = isset($item['path']) ? (string) $item['path'] : '';
            $sha  = isset($item['sha']) ? (string) $item['sha'] : '';
            if ($type !== 'blob' || $path === '' || $sha === '') {
                continue;
            }
            $map[$path] = $sha;
        }

        return [
            'ok'   => true,
            'map'  => $map,
            'sha'  => $branchSha,
        ];
    }

    private function gitBlobSha(string $content): string
    {
        $header = 'blob ' . strlen($content) . "\0";

        return sha1($header . $content);
    }

    /**
     * @param array<string,mixed> $cfg
     * @return array<string,mixed>
     */
    private function createBranchFromBase(array $cfg, string $baseBranch, string $newBranch): array
    {
        $ref = $this->request('GET', $this->apiBase($cfg) . '/git/ref/heads/' . rawurlencode($baseBranch), $cfg);
        if (! $ref['ok']) {
            return $ref;
        }

        $sha = $ref['data']['object']['sha'] ?? '';
        if (! is_string($sha) || $sha === '') {
            return ['ok' => false, 'message' => 'Unable to resolve base branch SHA for PR mode'];
        }

        $create = $this->request('POST', $this->apiBase($cfg) . '/git/refs', $cfg, [
            'ref' => 'refs/heads/' . $newBranch,
            'sha' => $sha,
        ]);

        return $create;
    }

    /**
     * @param array<string,mixed> $cfg
     * @return array<string,mixed>
     */
    private function createPullRequest(array $cfg, string $headBranch, string $baseBranch, string $title): array
    {
        return $this->request('POST', $this->apiBase($cfg) . '/pulls', $cfg, [
            'title' => $title,
            'head'  => $headBranch,
            'base'  => $baseBranch,
            'body'  => 'Automated PR generated by Helmetsan Core sync engine.',
        ]);
    }

    /**
     * @param array<string,mixed> $cfg
     * @return array<string,mixed>
     */
    private function findReusableOpenPullRequest(array $cfg, string $baseBranch, string $prefix): array
    {
        $open = $this->request(
            'GET',
            $this->apiBase($cfg) . '/pulls?state=open&base=' . rawurlencode($baseBranch) . '&per_page=50',
            $cfg
        );

        if (! $open['ok']) {
            return ['ok' => false];
        }

        $items = $open['data'];
        if (! is_array($items)) {
            return ['ok' => false];
        }

        foreach ($items as $item) {
            if (! is_array($item)) {
                continue;
            }
            $headRef = isset($item['head']['ref']) ? (string) $item['head']['ref'] : '';
            if ($headRef === '' || strpos($headRef, $prefix) !== 0) {
                continue;
            }

            return [
                'ok'     => true,
                'branch' => $headRef,
                'number' => isset($item['number']) ? (int) $item['number'] : null,
                'url'    => isset($item['html_url']) ? (string) $item['html_url'] : null,
            ];
        }

        return ['ok' => false];
    }

    /**
     * @param array<string,mixed> $cfg
     * @return array<string,mixed>
     */
    private function mergePullRequest(array $cfg, int $number, string $branch): array
    {
        return $this->request('PUT', $this->apiBase($cfg) . '/pulls/' . (string) $number . '/merge', $cfg, [
            'commit_title' => 'Helmetsan auto-merge: ' . $branch,
            'merge_method' => 'squash',
        ], [405, 409, 422]);
    }

    private function encodePath(string $path): string
    {
        return str_replace('%2F', '/', rawurlencode($path));
    }

    /**
     * @param array<string,mixed> $cfg
     * @param array<string,mixed> $body
     * @param array<int,int> $allowedHttpErrors
     * @return array<string,mixed>
     */
    private function request(string $method, string $url, array $cfg, array $body = [], array $allowedHttpErrors = []): array
    {
        $args = [
            'method'  => $method,
            'timeout' => 30,
            'headers' => [
                'Authorization'       => 'Bearer ' . (string) $cfg['token'],
                'Accept'              => 'application/vnd.github+json',
                'X-GitHub-Api-Version'=> '2022-11-28',
                'User-Agent'          => 'Helmetsan-Core/' . HELMETSAN_CORE_VERSION,
            ],
        ];

        if ($body !== []) {
            $args['headers']['Content-Type'] = 'application/json';
            $args['body'] = wp_json_encode($body);
        }

        $response = wp_remote_request($url, $args);
        if (is_wp_error($response)) {
            $this->logger->info('GitHub request failed: ' . $response->get_error_message());
            return [
                'ok'      => false,
                'message' => $response->get_error_message(),
            ];
        }

        $code = (int) wp_remote_retrieve_response_code($response);
        $raw  = (string) wp_remote_retrieve_body($response);
        $data = json_decode($raw, true);

        if ($code >= 200 && $code < 300) {
            return [
                'ok'   => true,
                'code' => $code,
                'data' => is_array($data) ? $data : [],
            ];
        }

        if (in_array($code, $allowedHttpErrors, true)) {
            return [
                'ok'   => false,
                'code' => $code,
                'data' => is_array($data) ? $data : [],
                'message' => 'Handled HTTP error ' . (string) $code,
            ];
        }

        $message = is_array($data) && isset($data['message']) ? (string) $data['message'] : 'GitHub API error';
        $this->logger->info('GitHub API error ' . (string) $code . ': ' . $message);

        return [
            'ok'      => false,
            'code'    => $code,
            'message' => $message,
            'data'    => is_array($data) ? $data : [],
        ];
    }

    /**
     * @param array<string,mixed> $data
     */
    private function logSync(string $action, string $status, array $data): void
    {
        $this->logs->add([
            'action'        => $action,
            'mode'          => isset($data['mode']) ? (string) $data['mode'] : '',
            'status'        => $status,
            'branch'        => isset($data['branch']) ? (string) $data['branch'] : '',
            'target_branch' => isset($data['target_branch']) ? (string) $data['target_branch'] : '',
            'remote_path'   => isset($data['remote_path']) ? (string) $data['remote_path'] : '',
            'processed'     => isset($data['processed']) ? (int) $data['processed'] : 0,
            'pushed'        => isset($data['pushed']) ? (int) $data['pushed'] : 0,
            'skipped'       => isset($data['skipped']) ? (int) $data['skipped'] : 0,
            'failed'        => isset($data['failed']) ? (int) $data['failed'] : 0,
            'message'       => isset($data['message']) ? (string) $data['message'] : '',
            'payload'       => isset($data['payload']) ? $data['payload'] : '',
        ]);
    }
}
