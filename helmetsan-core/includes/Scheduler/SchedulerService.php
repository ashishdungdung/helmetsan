<?php

declare(strict_types=1);

namespace Helmetsan\Core\Scheduler;

use Helmetsan\Core\AI\AiService;
use Helmetsan\Core\AI\FillMissingService;
use Helmetsan\Core\Alerts\AlertService;
use Helmetsan\Core\Health\HealthService;
use Helmetsan\Core\Ingestion\IngestionService;
use Helmetsan\Core\Ingestion\LogRepository as IngestionLogRepository;
use Helmetsan\Core\Seo\AiSeoDescriptionProvider;
use Helmetsan\Core\Seo\YoastSeoSeeder;
use Helmetsan\Core\Support\Config;
use Helmetsan\Core\Sync\LogRepository as SyncLogRepository;
use Helmetsan\Core\Sync\SyncService;

final class SchedulerService
{
    public const HOOK_SYNC_PULL = 'helmetsan_cron_sync_pull';
    public const HOOK_RETRY_FAILED = 'helmetsan_cron_retry_failed';
    public const HOOK_CLEANUP_LOGS = 'helmetsan_cron_cleanup_logs';
    public const HOOK_HEALTH_SNAPSHOT = 'helmetsan_cron_health_snapshot';
    public const HOOK_INGESTION = 'helmetsan_cron_ingestion';
    public const HOOK_ENRICHMENT = 'helmetsan_cron_enrichment';
    public const HOOK_IMAGE_ENRICHMENT = 'helmetsan_cron_image_enrichment';
    public const HOOK_R2_BACKUPS = 'helmetsan_cron_r2_backups';

    public function __construct(
        private readonly Config $config,
        private readonly SyncService $sync,
        private readonly IngestionService $ingestion,
        private readonly IngestionLogRepository $ingestionLogs,
        private readonly SyncLogRepository $syncLogs,
        private readonly HealthService $health,
        private readonly AlertService $alerts,
        private readonly ?AiService $aiService = null
    ) {
    }

    public function register(): void
    {
        add_filter('cron_schedules', [$this, 'cronSchedules']);

        add_action(self::HOOK_SYNC_PULL, [$this, 'runSyncPull']);
        add_action(self::HOOK_RETRY_FAILED, [$this, 'runRetryFailed']);
        add_action(self::HOOK_CLEANUP_LOGS, [$this, 'runCleanupLogs']);
        add_action(self::HOOK_HEALTH_SNAPSHOT, [$this, 'runHealthSnapshot']);
        add_action(self::HOOK_INGESTION, [$this, 'runIngestion']);
        add_action(self::HOOK_ENRICHMENT, [$this, 'runEnrichment']);
        add_action(self::HOOK_IMAGE_ENRICHMENT, [$this, 'runImageEnrichment']);
        add_action(self::HOOK_R2_BACKUPS, [$this, 'runR2Backups']);

        add_action('init', [$this, 'scheduleEvents']);
    }

    public function activate(): void
    {
        $this->scheduleEvents();
    }

    public function deactivate(): void
    {
        $this->clearHook(self::HOOK_SYNC_PULL);
        $this->clearHook(self::HOOK_RETRY_FAILED);
        $this->clearHook(self::HOOK_CLEANUP_LOGS);
        $this->clearHook(self::HOOK_HEALTH_SNAPSHOT);
        $this->clearHook(self::HOOK_INGESTION);
        $this->clearHook(self::HOOK_ENRICHMENT);
        $this->clearHook(self::HOOK_IMAGE_ENRICHMENT);
        $this->clearHook(self::HOOK_R2_BACKUPS);
    }

    /**
     * @param array<string,array<string,mixed>> $schedules
     * @return array<string,array<string,mixed>>
     */
    public function cronSchedules(array $schedules): array
    {
        $schedules['helmetsan_1h'] = ['interval' => 3600, 'display' => 'Helmetsan Every 1 Hour'];
        $schedules['helmetsan_6h'] = ['interval' => 6 * 3600, 'display' => 'Helmetsan Every 6 Hours'];
        $schedules['helmetsan_12h'] = ['interval' => 12 * 3600, 'display' => 'Helmetsan Every 12 Hours'];
        $schedules['helmetsan_24h'] = ['interval' => 86400, 'display' => 'Helmetsan Every 24 Hours'];

        return $schedules;
    }

    public function scheduleEvents(): void
    {
        $cfg = $this->config->schedulerConfig();
        $enabled = ! empty($cfg['enable_scheduler']);

        if (! $enabled) {
            $this->deactivate();
            return;
        }

        $syncRecurrence = $this->recurrenceFromHours((int) ($cfg['sync_pull_interval_hours'] ?? 6));

        if (! empty($cfg['sync_pull_enabled'])) {
            $this->ensureEvent(self::HOOK_SYNC_PULL, $syncRecurrence);
        } else {
            $this->clearHook(self::HOOK_SYNC_PULL);
        }

        if (! empty($cfg['retry_failed_enabled'])) {
            $this->ensureEvent(self::HOOK_RETRY_FAILED, 'daily');
        } else {
            $this->clearHook(self::HOOK_RETRY_FAILED);
        }

        if (! empty($cfg['cleanup_logs_enabled'])) {
            $this->ensureEvent(self::HOOK_CLEANUP_LOGS, 'daily');
        } else {
            $this->clearHook(self::HOOK_CLEANUP_LOGS);
        }

        if (! empty($cfg['health_snapshot_enabled'])) {
            $this->ensureEvent(self::HOOK_HEALTH_SNAPSHOT, 'daily');
        } else {
            $this->clearHook(self::HOOK_HEALTH_SNAPSHOT);
        }

        $ingestionRecurrence = $this->recurrenceFromHours((int) ($cfg['ingestion_interval_hours'] ?? 6));
        $this->ensureEvent(self::HOOK_INGESTION, $ingestionRecurrence);

        if (! empty($cfg['enrichment_enabled']) && $this->aiService !== null && $this->aiService->hasAnyConfiguredProvider()) {
            $enrichmentRecurrence = $this->recurrenceFromHours((int) ($cfg['enrichment_interval_hours'] ?? 24));
            $this->ensureEvent(self::HOOK_ENRICHMENT, $enrichmentRecurrence);
        } else {
            $this->clearHook(self::HOOK_ENRICHMENT);
        }

        if (! empty($cfg['r2_backups_enabled'])) {
            $r2BackupsRecurrence = $this->recurrenceFromHours((int) ($cfg['r2_backups_interval_hours'] ?? 24));
            $this->ensureEvent(self::HOOK_R2_BACKUPS, $r2BackupsRecurrence);
        } else {
            $this->clearHook(self::HOOK_R2_BACKUPS);
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function status(): array
    {
        return [
            'ok' => true,
            'next_runs' => [
                'sync_pull'       => wp_next_scheduled(self::HOOK_SYNC_PULL),
                'retry_failed'    => wp_next_scheduled(self::HOOK_RETRY_FAILED),
                'cleanup_logs'    => wp_next_scheduled(self::HOOK_CLEANUP_LOGS),
                'health_snapshot' => wp_next_scheduled(self::HOOK_HEALTH_SNAPSHOT),
                'ingestion'       => wp_next_scheduled(self::HOOK_INGESTION),
                'enrichment'      => wp_next_scheduled(self::HOOK_ENRICHMENT),
                'r2_backups'      => wp_next_scheduled(self::HOOK_R2_BACKUPS),
            ],
            'settings' => $this->config->schedulerConfig(),
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function runTask(string $task): array
    {
        return match ($task) {
            'sync_pull' => $this->runSyncPull(),
            'retry_failed' => $this->runRetryFailed(),
            'cleanup_logs' => $this->runCleanupLogs(),
            'health_snapshot' => $this->runHealthSnapshot(),
            'ingestion' => $this->runIngestion(),
            'enrichment' => $this->runEnrichment(),
            'image_enrichment' => $this->runImageEnrichment(),
            'r2_backups' => $this->runR2Backups(),
            default => ['ok' => false, 'message' => 'Unknown task: ' . $task],
        };
    }

    /**
     * @return array<string,mixed>
     */
    public function runSyncPull(): array
    {
        if (! $this->acquireLock('sync_pull', 1800)) {
            $msg = 'Sync pull is already running. Mutex lock blocked execution.';
            $this->maybeAlert('sync_pull', ['ok' => false, 'message' => $msg]);
            return ['ok' => false, 'message' => $msg];
        }

        try {
            $cfg = $this->config->schedulerConfig();
            $github = $this->config->githubConfig();
            $limit = max(1, (int) ($cfg['sync_pull_limit'] ?? 200));
            $profile = (string) ($github['sync_run_profile'] ?? 'pull-only');

            $result = $this->sync->pull($limit, false, null, null, null, $profile, [
                'source' => 'scheduler',
            ]);
            $this->maybeAlert('sync_pull', $result);

            return $result;
        } finally {
            $this->releaseLock('sync_pull');
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function runRetryFailed(): array
    {
        if (! $this->acquireLock('retry_failed', 1800)) {
            return ['ok' => false, 'message' => 'Retry failed is already running. Mutex lock blocked execution.'];
        }

        try {
            if (! $this->ingestionLogs->tableExists()) {
                $result = ['ok' => false, 'message' => 'Ingestion log table not found'];
                $this->maybeAlert('retry_failed', $result);
                return $result;
            }

            $cfg = $this->config->schedulerConfig();
            $limit = max(1, (int) ($cfg['retry_failed_limit'] ?? 100));
            $batch = max(1, (int) ($cfg['retry_failed_batch_size'] ?? 50));

            $failedRows   = $this->ingestionLogs->fetch(1, $limit, 'failed', '');
            $rejectedRows = $this->ingestionLogs->fetch(1, $limit, 'rejected', '');
            $rows         = array_merge($failedRows, $rejectedRows);

            usort($rows, static function (array $a, array $b): int {
                $idA = isset($a['id']) ? (int) $a['id'] : 0;
                $idB = isset($b['id']) ? (int) $b['id'] : 0;
                return $idB <=> $idA;
            });

            $rows  = array_slice($rows, 0, $limit);
            $files = [];
            foreach ($rows as $row) {
                $file = isset($row['source_file']) ? (string) $row['source_file'] : '';
                if ($file !== '' && file_exists($file)) {
                    $files[] = $file;
                }
            }

            $files = array_values(array_unique($files));

            if ($files === []) {
                return ['ok' => true, 'message' => 'No retryable files found', 'candidate_logs' => count($rows)];
            }

            $result = $this->ingestion->ingestFiles($files, $batch, null, false, 'scheduler-retry-failed');
            $result['candidate_logs'] = count($rows);
            $result['retry_files'] = count($files);
            $this->maybeAlert('retry_failed', $result);

            return $result;
        } finally {
            $this->releaseLock('retry_failed');
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function runCleanupLogs(): array
    {
        $cfg = $this->config->schedulerConfig();
        $days = max(1, (int) ($cfg['cleanup_logs_days'] ?? 30));

        $syncDeleted = $this->syncLogs->cleanupOlderThanDays($days, null);
        $ingestionDeleted = $this->ingestionLogs->cleanupOlderThanDays($days, null);

        return [
            'ok' => true,
            'days' => $days,
            'sync_deleted' => $syncDeleted,
            'ingestion_deleted' => $ingestionDeleted,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function runHealthSnapshot(): array
    {
        $snapshot = $this->health->report();
        update_option('helmetsan_last_health_snapshot', [
            'created_at' => current_time('mysql'),
            'report' => $snapshot,
        ], false);

        $result = [
            'ok' => true,
            'created_at' => current_time('mysql'),
        ];
        $this->maybeAlert('health_snapshot', $result, $snapshot);

        return $result;
    }

    /**
     * @return array<string,mixed>
     */
    public function runIngestion(): array
    {
        if (! $this->acquireLock('ingestion', 3600)) {
            $msg = 'Ingestion is already running. Mutex lock blocked execution.';
            $this->maybeAlert('ingestion', ['ok' => false, 'message' => $msg]);
            return ['ok' => false, 'message' => $msg];
        }

        try {
            $path = $this->config->dataRoot();
            if (! is_dir($path)) {
                $result = ['ok' => false, 'message' => 'Data root not found: ' . $path];
                $this->maybeAlert('ingestion', $result);
                return $result;
            }

            $result = $this->ingestion->ingestPath($path, 100, null, false);
            $this->maybeAlert('ingestion', $result);

            return $result;
        } finally {
            $this->releaseLock('ingestion');
        }
    }

    /**
     * Run fill-missing then SEO seed for enabled post types (helmets/brands/accessories).
     * Uses per-type limits from scheduler config. Requires AI configured.
     *
     * @return array<string,mixed>
     */
    public function runEnrichment(): array
    {
        if ($this->aiService === null || ! $this->aiService->hasAnyConfiguredProvider()) {
            return ['ok' => false, 'message' => 'Enrichment requires at least one configured AI provider.'];
        }
        if (! $this->acquireLock('enrichment', 3600)) {
            return ['ok' => false, 'message' => 'Enrichment is already running. Mutex lock blocked execution.'];
        }

        try {
            $cfg = $this->config->schedulerConfig();

            $typesConfig = [
                'helmet' => [
                    'enabled_key'     => 'enrichment_helmets_enabled',
                    'fill_limit_key'  => 'enrichment_helmets_fill_limit',
                    'seo_limit_key'   => 'enrichment_helmets_seo_limit',
                    'fill_taxonomies' => true,
                ],
                'brand' => [
                    'enabled_key'     => 'enrichment_brands_enabled',
                    'fill_limit_key'  => 'enrichment_brands_fill_limit',
                    'seo_limit_key'   => 'enrichment_brands_seo_limit',
                    'fill_taxonomies' => false,
                ],
                'accessory' => [
                    'enabled_key'     => 'enrichment_accessories_enabled',
                    'fill_limit_key'  => 'enrichment_accessories_fill_limit',
                    'seo_limit_key'   => 'enrichment_accessories_seo_limit',
                    'fill_taxonomies' => true,
                ],
            ];

            $fillService = new FillMissingService($this->aiService);
            $aiProvider = new AiSeoDescriptionProvider($this->aiService);
            $seeder = new YoastSeoSeeder($aiProvider);

            $results = [];
            $anyEnabled = false;

            foreach ($typesConfig as $postType => $meta) {
                $enabledKey = $meta['enabled_key'];
                if (empty($cfg[$enabledKey])) {
                    continue;
                }
                $anyEnabled = true;

                // Fallback to global limits if per-type limits are not set (backwards compatibility).
                $globalFill = (int) ($cfg['enrichment_fill_limit'] ?? 50);
                $globalSeo  = (int) ($cfg['enrichment_seo_limit'] ?? 100);

                $fillLimit = max(1, (int) ($cfg[$meta['fill_limit_key']] ?? $globalFill));
                $seoLimit  = max(0, (int) ($cfg[$meta['seo_limit_key']] ?? $globalSeo));

                $fillResult = $fillService->run(
                    $postType,
                    $fillLimit,
                    0,
                    false,
                    null,
                    true,
                    false,
                    (bool) $meta['fill_taxonomies'],
                    null,
                    null,
                    86400
                );

                if ($postType === 'helmet') {
                    $seoResult = $seeder->seedHelmets($seoLimit > 0 ? $seoLimit : 0, 0, false);
                } elseif ($postType === 'brand') {
                    $seoResult = $seeder->seedBrands($seoLimit > 0 ? $seoLimit : 0, 0, false);
                } else { // accessory
                    $seoResult = $seeder->seedAccessories($seoLimit > 0 ? $seoLimit : 0, 0, false);
                }

                $results[$postType] = [
                    'fill_missing' => $fillResult,
                    'seo_seed'     => $seoResult,
                ];
            }

            // Optionally seed SEO for taxonomy term archives (helmet_type, region, certification, etc.).
            if (! empty($cfg['enrichment_seo_terms_enabled'])) {
                $termResults = [];
                foreach (YoastSeoSeeder::getTaxonomiesForTermSeo() as $tax) {
                    $res = $seeder->seedTermsForTaxonomy($tax, 0, 0, false);
                    $termResults[$tax] = $res;
                }
                $results['terms'] = ['seo_seed' => $termResults];
            }

            // Optionally seed SEO for other CPTs (safety_standard, dealer, distributor, technology, etc.).
            if (! empty($cfg['enrichment_seo_other_cpts_enabled'])) {
                $otherLimit = max(0, (int) ($cfg['enrichment_seo_other_cpts_limit'] ?? 100));
                $otherResults = [];
                foreach (YoastSeoSeeder::getOtherCptTypesForSeo() as $cpt) {
                    $res = $seeder->seedCpt($cpt, $otherLimit, 0, false);
                    $otherResults[$cpt] = $res;
                }
                $results['other_cpts'] = ['seo_seed' => $otherResults];
            }

            if (! $anyEnabled && empty($results['terms']) && empty($results['other_cpts'])) {
                return ['ok' => false, 'message' => 'No enrichment post types are enabled in scheduler settings.'];
            }

            return [
                'ok'      => true,
                'results' => $results,
            ];
        } finally {
            $this->releaseLock('enrichment');
        }
    }

    /**
     * Run the heavy image enrichment pipeline in the background.
     *
     * @return array<string,mixed>
     */
    public function runImageEnrichment(): array
    {
        if (! $this->acquireLock('image_enrichment', 7200)) {
            return ['ok' => false, 'message' => 'Image enrichment is already running.'];
        }

        try {
            $cfg = $this->config->schedulerConfig();
            $limit = max(1, (int) ($cfg['image_enrichment_limit'] ?? 50));
            
            $service = new \Helmetsan\Core\Media\HelmetImageEnrichmentService(
                new \Helmetsan\Core\Media\MediaEngine($this->config),
                $this->aiService,
                new \Helmetsan\Core\Media\RevZillaImageService()
            );

            $result = $service->run(
                $limit, // limit
                true,   // onlyMissingThumb
                false,  // useAiWhenNoEan
                false,  // dryRun
                null,   // onProgress
                true,   // useEan
                true,   // useRevZilla
                ! empty($cfg['image_enrichment_search']), // useAi (or search)
                ! empty($cfg['image_enrichment_gallery']), // gallery
                ! empty($cfg['image_enrichment_search']), // search
                empty($cfg['image_enrichment_no_high_res']) // highRes
            );

            $this->maybeAlert('image_enrichment', $result);
            return $result;
        } finally {
            $this->releaseLock('image_enrichment');
        }
    }

    /**
     * @return array<string,mixed>
     */
    public function runR2Backups(): array
    {
        if (! $this->acquireLock('r2_backups', 3600)) {
            $msg = 'R2 Backups is already running. Mutex lock blocked execution.';
            $this->maybeAlert('r2_backups', ['ok' => false, 'message' => $msg]);
            return ['ok' => false, 'message' => $msg];
        }

        try {
            $r2Service = new \Helmetsan\Core\Media\CloudflareR2Service($this->config);
            $backupService = new \Helmetsan\Core\Backup\BackupService(
                $this->config,
                new \Helmetsan\Core\Support\Logger(),
                $r2Service
            );
            $result = $backupService->runBackup();
            $this->maybeAlert('r2_backups', $result);
            return $result;
        } finally {
            $this->releaseLock('r2_backups');
        }
    }

    private function ensureEvent(string $hook, string $recurrence): void
    {
        if (! wp_next_scheduled($hook)) {
            wp_schedule_event(time() + 60, $recurrence, $hook);
        }
    }

    private function clearHook(string $hook): void
    {
        while ($timestamp = wp_next_scheduled($hook)) {
            wp_unschedule_event($timestamp, $hook);
        }
    }

    private function acquireLock(string $task, int $expiration = 3600): bool
    {
        $lockName = 'hs_cron_lock_' . $task;
        
        // Use wp_cache_add if an object cache is available for better atomicity
        if (function_exists('wp_cache_add')) {
            $lockAcquired = wp_cache_add($lockName, time(), 'transient', $expiration);
            if ($lockAcquired) {
                // Also set the transient as a persistent fallback
                set_transient($lockName, time(), $expiration);
                return true;
            }
        }

        // Fallback for environments without object cache
        if (get_transient($lockName)) {
            return false;
        }
        
        set_transient($lockName, time(), $expiration);
        return true;
    }

    private function releaseLock(string $task): void
    {
        $lockName = 'hs_cron_lock_' . $task;
        if (function_exists('wp_cache_delete')) {
            wp_cache_delete($lockName, 'transient');
        }
        delete_transient($lockName);
    }

    private function recurrenceFromHours(int $hours): string
    {
        return match (true) {
            $hours <= 1 => 'helmetsan_1h',
            $hours <= 6 => 'helmetsan_6h',
            $hours <= 12 => 'helmetsan_12h',
            default => 'helmetsan_24h',
        };
    }

    /**
     * @param array<string,mixed> $result
     * @param array<string,mixed> $snapshot
     */
    private function maybeAlert(string $task, array $result, array $snapshot = []): void
    {
        $cfg = $this->config->alertsConfig();
        if (empty($cfg['enabled'])) {
            return;
        }

        $ok = ! empty($result['ok']);
        if (! $ok) {
            $flag = match ($task) {
                'sync_pull' => ! empty($cfg['alert_on_sync_error']),
                'retry_failed', 'ingestion' => ! empty($cfg['alert_on_ingest_error']),
                default => true,
            };
            if ($flag) {
                $this->alerts->send(
                    'error',
                    'Scheduler task failed: ' . $task,
                    (string) ($result['message'] ?? 'Unknown scheduler error'),
                    $result
                );
            }
            return;
        }

        if ($task === 'health_snapshot' && ! empty($cfg['alert_on_health_warning']) && isset($snapshot['status']) && $snapshot['status'] !== 'healthy') {
            $this->alerts->send(
                'warning',
                'Health snapshot degraded',
                'Health status is ' . (string) $snapshot['status'],
                $snapshot
            );
        }
    }
}
