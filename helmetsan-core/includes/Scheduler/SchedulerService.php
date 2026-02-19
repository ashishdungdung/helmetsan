<?php

declare(strict_types=1);

namespace Helmetsan\Core\Scheduler;

use Helmetsan\Core\Alerts\AlertService;
use Helmetsan\Core\Health\HealthService;
use Helmetsan\Core\Ingestion\IngestionService;
use Helmetsan\Core\Ingestion\LogRepository as IngestionLogRepository;
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

    public function __construct(
        private readonly Config $config,
        private readonly SyncService $sync,
        private readonly IngestionService $ingestion,
        private readonly IngestionLogRepository $ingestionLogs,
        private readonly SyncLogRepository $syncLogs,
        private readonly HealthService $health,
        private readonly AlertService $alerts
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
    }

    /**
     * @param array<string,array<string,mixed>> $schedules
     * @return array<string,array<string,mixed>>
     */
    public function cronSchedules(array $schedules): array
    {
        $schedules['helmetsan_1h'] = ['interval' => HOUR_IN_SECONDS, 'display' => 'Helmetsan Every 1 Hour'];
        $schedules['helmetsan_6h'] = ['interval' => 6 * HOUR_IN_SECONDS, 'display' => 'Helmetsan Every 6 Hours'];
        $schedules['helmetsan_12h'] = ['interval' => 12 * HOUR_IN_SECONDS, 'display' => 'Helmetsan Every 12 Hours'];
        $schedules['helmetsan_24h'] = ['interval' => DAY_IN_SECONDS, 'display' => 'Helmetsan Every 24 Hours'];

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
            default => ['ok' => false, 'message' => 'Unknown task: ' . $task],
        };
    }

    /**
     * @return array<string,mixed>
     */
    public function runSyncPull(): array
    {
        $cfg = $this->config->schedulerConfig();
        $github = $this->config->githubConfig();
        $limit = max(1, (int) ($cfg['sync_pull_limit'] ?? 200));
        $profile = (string) ($github['sync_run_profile'] ?? 'pull-only');

        $result = $this->sync->pull($limit, false, null, null, null, $profile, [
            'source' => 'scheduler',
        ]);
        $this->maybeAlert('sync_pull', $result);

        return $result;
    }

    /**
     * @return array<string,mixed>
     */
    public function runRetryFailed(): array
    {
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
        $path = $this->config->dataRoot();
        if (! is_dir($path)) {
            $result = ['ok' => false, 'message' => 'Data root not found: ' . $path];
            $this->maybeAlert('ingestion', $result);
            return $result;
        }

        $result = $this->ingestion->ingestPath($path, 100, null, false);
        $this->maybeAlert('ingestion', $result);

        return $result;
    }

    private function ensureEvent(string $hook, string $recurrence): void
    {
        if (! wp_next_scheduled($hook)) {
            wp_schedule_event(time() + MINUTE_IN_SECONDS, $recurrence, $hook);
        }
    }

    private function clearHook(string $hook): void
    {
        while ($timestamp = wp_next_scheduled($hook)) {
            wp_unschedule_event($timestamp, $hook);
        }
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
