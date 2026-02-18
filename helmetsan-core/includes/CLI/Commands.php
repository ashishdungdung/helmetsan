<?php

declare(strict_types=1);

namespace Helmetsan\Core\CLI;

use Helmetsan\Core\Alerts\AlertService;
use Helmetsan\Core\Analytics\SmokeTestService;
use Helmetsan\Core\Brands\BrandService;
use Helmetsan\Core\Docs\DocsService;
use Helmetsan\Core\GoLive\ChecklistService;
use Helmetsan\Core\Health\HealthService;
use Helmetsan\Core\Analytics\EventRepository;
use Helmetsan\Core\Ingestion\IngestionService;
use Helmetsan\Core\Ingestion\LogRepository;
use Helmetsan\Core\ImportExport\ExportService;
use Helmetsan\Core\ImportExport\ImportService;
use Helmetsan\Core\Media\MediaEngine;
use Helmetsan\Core\Revenue\RevenueService;
use Helmetsan\Core\Scheduler\SchedulerService;
use Helmetsan\Core\Seed\Seeder;
use Helmetsan\Core\Seo\SchemaService;
use Helmetsan\Core\Sync\LogRepository as SyncLogRepository;
use Helmetsan\Core\Sync\SyncService;
use Helmetsan\Core\Validation\Validator;
use Helmetsan\Core\WooBridge\WooBridgeService;

final class Commands
{
    public function __construct(
        private readonly HealthService $health,
        private readonly Seeder $seeder,
        private readonly IngestionService $ingestion,
        private readonly SyncService $sync,
        private readonly Validator $validator,
        private readonly SmokeTestService $smoke,
        private readonly ChecklistService $checklist,
        private readonly DocsService $docs,
        private readonly LogRepository $ingestionLogs,
        private readonly ImportService $importService,
        private readonly ExportService $exportService,
        private readonly SyncLogRepository $syncLogs,
        private readonly SchemaService $schema,
        private readonly RevenueService $revenue,
        private readonly EventRepository $analyticsEvents,
        private readonly SchedulerService $scheduler,
        private readonly AlertService $alerts,
        private readonly BrandService $brands,
        private readonly MediaEngine $media,
        private readonly WooBridgeService $wooBridge
    ) {
    }

    public function register(): void
    {
        \WP_CLI::add_command('helmetsan health', [$this, 'health']);
        \WP_CLI::add_command('helmetsan seed', [$this, 'seed']);
        \WP_CLI::add_command('helmetsan ingest', [$this, 'ingest']);
        \WP_CLI::add_command('helmetsan sync', [$this, 'sync']);
        \WP_CLI::add_command('helmetsan validate', [$this, 'validate']);
        \WP_CLI::add_command('helmetsan analytics smoke-test', [$this, 'analyticsSmokeTest']);
        \WP_CLI::add_command('helmetsan go-live checklist', [$this, 'goLiveChecklist']);
        \WP_CLI::add_command('helmetsan docs build-index', [$this, 'docsBuildIndex']);
        \WP_CLI::add_command('helmetsan retry-failed', [$this, 'retryFailed']);
        \WP_CLI::add_command('helmetsan import', [$this, 'importData']);
        \WP_CLI::add_command('helmetsan export', [$this, 'exportData']);
        \WP_CLI::add_command('helmetsan unlock-ingestion', [$this, 'unlockIngestion']);
        \WP_CLI::add_command('helmetsan sync-logs', [$this, 'syncLogs']);
        \WP_CLI::add_command('helmetsan sync-logs-cleanup', [$this, 'cleanupSyncLogs']);
        \WP_CLI::add_command('helmetsan ingest-logs-cleanup', [$this, 'cleanupIngestionLogs']);
        \WP_CLI::add_command('helmetsan seo schema-check', [$this, 'schemaCheck']);
        \WP_CLI::add_command('helmetsan revenue report', [$this, 'revenueReport']);
        \WP_CLI::add_command('helmetsan analytics report', [$this, 'analyticsReport']);
        \WP_CLI::add_command('helmetsan scheduler status', [$this, 'schedulerStatus']);
        \WP_CLI::add_command('helmetsan scheduler run', [$this, 'schedulerRun']);
        \WP_CLI::add_command('helmetsan alerts test', [$this, 'alertsTest']);
        \WP_CLI::add_command('helmetsan brand cascade', [$this, 'brandCascade']);
        \WP_CLI::add_command('helmetsan media backfill-brand-logos', [$this, 'mediaBackfillBrandLogos']);
        \WP_CLI::add_command('helmetsan woo-bridge sync', [$this, 'wooBridgeSync']);
    }

    /**
     * ## OPTIONS
     * [--format=<format>]
     * : table|json. Default json.
     */
    public function health(array $args, array $assoc): void
    {
        $format = (string) ($assoc['format'] ?? 'json');
        $report = $this->health->report();
        $this->renderAssoc($report, $format);
    }

    /**
     * ## OPTIONS
     * --set=<set>
     * : Seeder set name.
     * [--force]
     * : Force seed even if data exists.
     */
    public function seed(array $args, array $assoc): void
    {
        $set   = (string) ($assoc['set'] ?? 'start-pack-v1');
        $force = isset($assoc['force']);
        $out   = $this->seeder->seed($set, $force);
        \WP_CLI::line(wp_json_encode($out, JSON_PRETTY_PRINT));
    }

    /**
     * ## OPTIONS
     * [--path=<path>]
     * : Relative path under data root.
     * [--batch-size=<n>]
     * : Batch size. Default 100.
     * [--limit=<n>]
     * : Process only first N files.
     * [--dry-run]
     * : Validate only, do not write.
     */
    public function ingest(array $args, array $assoc): void
    {
        $path      = (string) ($assoc['path'] ?? '');
        $batchSize = isset($assoc['batch-size']) ? max(1, (int) $assoc['batch-size']) : 100;
        $limit     = isset($assoc['limit']) ? max(1, (int) $assoc['limit']) : null;
        $dryRun    = isset($assoc['dry-run']);

        $out = $this->ingestion->ingestPath($path, $batchSize, $limit, $dryRun);
        \WP_CLI::line(wp_json_encode($out, JSON_PRETTY_PRINT));
    }

    /**
     * ## OPTIONS
     * <action>
     * : push|pull
     * [--limit=<n>]
     * : Max files to process. Default 500.
     * [--dry-run]
     * : Evaluate only, do not write.
     * [--path=<remotePath>]
     * : Override configured GitHub remote path.
     * [--apply-brands]
     * : For pull: auto-apply downloaded brand JSON into WordPress brand records.
     * [--apply-helmets]
     * : For pull: auto-apply downloaded helmet JSON into WordPress helmets.
     * [--profile=<profile>]
     * : pull-only|pull+brands|pull+all. Overrides saved sync profile.
     * [--mode=<mode>]
     * : push mode: commit|pr (only for push).
     * [--pr-title=<title>]
     * : Pull request title when --mode=pr.
     * [--auto-merge]
     * : Auto-merge PR when mode=pr.
     */
    public function sync(array $args, array $assoc): void
    {
        $action = (string) ($args[0] ?? 'pull');
        $limit  = isset($assoc['limit']) ? max(1, (int) $assoc['limit']) : 500;
        $dryRun = isset($assoc['dry-run']);
        $path   = isset($assoc['path']) ? (string) $assoc['path'] : null;
        $applyBrands = array_key_exists('apply-brands', $assoc) ? true : null;
        $applyHelmets = array_key_exists('apply-helmets', $assoc) ? true : null;
        $profile = isset($assoc['profile']) ? (string) $assoc['profile'] : null;
        $mode   = isset($assoc['mode']) ? (string) $assoc['mode'] : null;
        $prTitle= isset($assoc['pr-title']) ? (string) $assoc['pr-title'] : null;
        $autoMerge = isset($assoc['auto-merge']) ? true : null;

        $out = ($action === 'push')
            ? $this->sync->push($limit, $dryRun, $path, $mode, $prTitle, $autoMerge)
            : $this->sync->pull($limit, $dryRun, $path, $applyBrands, $applyHelmets, $profile, [
                'source' => 'wp-cli',
                'trigger_user' => 'wp-cli',
            ]);
        \WP_CLI::line(wp_json_encode($out, JSON_PRETTY_PRINT));
    }

    /**
     * ## OPTIONS
     * <type>
     * : schema|logic|integrity
     * [--file=<file>]
     * : Optional absolute file path for schema/logic checks.
     */
    public function validate(array $args, array $assoc): void
    {
        $type = (string) ($args[0] ?? 'integrity');

        if ($type === 'integrity') {
            \WP_CLI::line(wp_json_encode($this->validator->validateIntegrity(), JSON_PRETTY_PRINT));
            return;
        }

        $file = (string) ($assoc['file'] ?? '');
        if ($file === '' || ! file_exists($file)) {
            \WP_CLI::error('Provide --file for schema/logic validation');
            return;
        }

        $raw  = file_get_contents($file);
        $data = json_decode((string) $raw, true);

        if (! is_array($data)) {
            \WP_CLI::error('Invalid JSON input file');
            return;
        }

        $result = ($type === 'schema') ? $this->validator->validateSchema($data) : $this->validator->validateLogic($data);
        \WP_CLI::line(wp_json_encode($result, JSON_PRETTY_PRINT));
    }

    public function analyticsSmokeTest(array $args, array $assoc): void
    {
        \WP_CLI::line(wp_json_encode($this->smoke->run(), JSON_PRETTY_PRINT));
    }

    public function goLiveChecklist(array $args, array $assoc): void
    {
        $report = $this->checklist->report();
        \WP_CLI::line(wp_json_encode($report, JSON_PRETTY_PRINT));
    }

    public function docsBuildIndex(array $args, array $assoc): void
    {
        $files = $this->docs->listDocs();
        \WP_CLI::line(wp_json_encode(['docs' => $files, 'count' => count($files)], JSON_PRETTY_PRINT));
    }

    /**
     * Retry failed/rejected ingestion logs.
     *
     * ## OPTIONS
     * [--limit=<n>]
     * : Max log rows to evaluate. Default 100.
     * [--batch-size=<n>]
     * : Ingestion batch size. Default 50.
     * [--dry-run]
     * : Validate retry candidates without writing.
     */
    public function retryFailed(array $args, array $assoc): void
    {
        if (! $this->ingestionLogs->tableExists()) {
            \WP_CLI::error('Ingestion log table not found.');
            return;
        }

        $limit     = isset($assoc['limit']) ? max(1, (int) $assoc['limit']) : 100;
        $batchSize = isset($assoc['batch-size']) ? max(1, (int) $assoc['batch-size']) : 50;
        $dryRun    = isset($assoc['dry-run']);

        $failedRows   = $this->ingestionLogs->fetch(1, $limit, 'failed', '');
        $rejectedRows = $this->ingestionLogs->fetch(1, $limit, 'rejected', '');
        $rows         = array_merge($failedRows, $rejectedRows);

        if ($rows === []) {
            \WP_CLI::line(wp_json_encode([
                'ok'      => true,
                'message' => 'No failed/rejected log rows found.',
            ], JSON_PRETTY_PRINT));
            return;
        }

        usort($rows, static function (array $a, array $b): int {
            $idA = isset($a['id']) ? (int) $a['id'] : 0;
            $idB = isset($b['id']) ? (int) $b['id'] : 0;
            return $idB <=> $idA;
        });

        $rows  = array_slice($rows, 0, $limit);
        $files = [];

        foreach ($rows as $row) {
            $file = isset($row['source_file']) ? (string) $row['source_file'] : '';
            if ($file === '' || ! file_exists($file)) {
                continue;
            }
            $files[] = $file;
        }

        $files = array_values(array_unique($files));
        if ($files === []) {
            \WP_CLI::line(wp_json_encode([
                'ok'      => true,
                'message' => 'No retryable source files found for selected logs.',
            ], JSON_PRETTY_PRINT));
            return;
        }

        $result = $this->ingestion->ingestFiles($files, $batchSize, null, $dryRun, 'cli-retry-failed');
        $result['candidate_logs'] = count($rows);
        $result['retry_files']    = count($files);

        \WP_CLI::line(wp_json_encode($result, JSON_PRETTY_PRINT));
    }

    /**
     * Import JSON data into helmets.
     *
     * ## OPTIONS
     * --file=<file>
     * : Absolute path to JSON file.
     * [--batch-size=<n>]
     * : Batch size for ingestion.
     * [--dry-run]
     * : Validate only.
     */
    public function importData(array $args, array $assoc): void
    {
        $file = (string) ($assoc['file'] ?? '');
        if ($file === '') {
            \WP_CLI::error('Provide --file=<absolute-path-to-json>');
            return;
        }

        $batchSize = isset($assoc['batch-size']) ? max(1, (int) $assoc['batch-size']) : 100;
        $dryRun    = isset($assoc['dry-run']);

        $result = $this->importService->importJsonFile($file, $dryRun, $batchSize);
        \WP_CLI::line(wp_json_encode($result, JSON_PRETTY_PRINT));
    }

    /**
     * Export helmet data to JSON.
     *
     * ## OPTIONS
     * --post-id=<id>
     * : Post ID.
     * [--entity=<entity>]
     * : helmet|brand. Default helmet.
     * [--out=<file>]
     * : Optional output file path.
     */
    public function exportData(array $args, array $assoc): void
    {
        $postId = isset($assoc['post-id']) ? (int) $assoc['post-id'] : 0;
        if ($postId <= 0) {
            \WP_CLI::error('Provide --post-id=<helmet-post-id>');
            return;
        }

        $entity = isset($assoc['entity']) ? sanitize_key((string) $assoc['entity']) : 'helmet';
        $entity = in_array($entity, ['helmet', 'brand'], true) ? $entity : 'helmet';
        $out = isset($assoc['out']) ? (string) $assoc['out'] : null;
        $result = $this->exportService->exportByPostId($postId, $entity, $out);
        \WP_CLI::line(wp_json_encode($result, JSON_PRETTY_PRINT));
    }

    public function unlockIngestion(array $args, array $assoc): void
    {
        $wasActive = $this->ingestion->lockActive();
        $this->ingestion->forceUnlock();

        \WP_CLI::line(wp_json_encode([
            'ok'         => true,
            'was_locked' => $wasActive,
            'message'    => 'Ingestion lock cleared.',
        ], JSON_PRETTY_PRINT));
    }

    /**
     * Inspect sync logs from CLI.
     *
     * ## OPTIONS
     * [--status=<status>]
     * : Filter by status (success|partial|error|info|all).
     * [--action=<action>]
     * : Filter by action (pull|push|all).
     * [--search=<text>]
     * : Search in branch/target/path/message.
     * [--limit=<n>]
     * : Rows per page (default 20).
     * [--page=<n>]
     * : Page number (default 1).
     * [--tail]
     * : Shorthand for latest rows (page=1).
     * [--id=<id>]
     * : Show full log row and payload for one log id.
     * [--format=<format>]
     * : json|table (default json).
     */
    public function syncLogs(array $args, array $assoc): void
    {
        if (! $this->syncLogs->tableExists()) {
            \WP_CLI::error('Sync log table not found.');
            return;
        }

        $format = (string) ($assoc['format'] ?? 'json');
        $id     = isset($assoc['id']) ? (int) $assoc['id'] : 0;

        if ($id > 0) {
            $row = $this->syncLogs->findById($id);
            if (! is_array($row)) {
                \WP_CLI::error('Sync log entry not found for id=' . (string) $id);
                return;
            }
            \WP_CLI::line(wp_json_encode($row, JSON_PRETTY_PRINT));
            return;
        }

        $status = isset($assoc['status']) ? (string) $assoc['status'] : 'all';
        $action = isset($assoc['action']) ? (string) $assoc['action'] : 'all';
        $search = isset($assoc['search']) ? (string) $assoc['search'] : '';
        $limit  = isset($assoc['limit']) ? max(1, (int) $assoc['limit']) : 20;
        $page   = isset($assoc['page']) ? max(1, (int) $assoc['page']) : 1;

        if (isset($assoc['tail'])) {
            $page = 1;
        }

        $total = $this->syncLogs->count($status, $action, $search);
        $rows  = $this->syncLogs->fetch($page, $limit, $status, $action, $search);

        if ($format === 'table') {
            $tableRows = [];
            foreach ($rows as $row) {
                $tableRows[] = [
                    'id'            => isset($row['id']) ? (string) $row['id'] : '',
                    'created_at'    => isset($row['created_at']) ? (string) $row['created_at'] : '',
                    'action'        => isset($row['action']) ? (string) $row['action'] : '',
                    'mode'          => isset($row['mode']) ? (string) $row['mode'] : '',
                    'status'        => isset($row['status']) ? (string) $row['status'] : '',
                    'branch'        => isset($row['branch']) ? (string) $row['branch'] : '',
                    'target_branch' => isset($row['target_branch']) ? (string) $row['target_branch'] : '',
                    'processed'     => isset($row['processed']) ? (string) $row['processed'] : '0',
                    'pushed'        => isset($row['pushed']) ? (string) $row['pushed'] : '0',
                    'skipped'       => isset($row['skipped']) ? (string) $row['skipped'] : '0',
                    'failed'        => isset($row['failed']) ? (string) $row['failed'] : '0',
                ];
            }
            \WP_CLI\Utils\format_items('table', $tableRows, [
                'id',
                'created_at',
                'action',
                'mode',
                'status',
                'branch',
                'target_branch',
                'processed',
                'pushed',
                'skipped',
                'failed',
            ]);
            \WP_CLI::line('total=' . (string) $total . ' page=' . (string) $page . ' limit=' . (string) $limit);
            return;
        }

        \WP_CLI::line(wp_json_encode([
            'ok'      => true,
            'total'   => $total,
            'page'    => $page,
            'limit'   => $limit,
            'status'  => $status,
            'action'  => $action,
            'search'  => $search,
            'rows'    => $rows,
        ], JSON_PRETTY_PRINT));
    }

    /**
     * Cleanup old sync logs.
     *
     * ## OPTIONS
     * [--days=<n>]
     * : Delete logs older than N days. Default 30.
     * [--status=<status>]
     * : Optional status filter.
     */
    public function cleanupSyncLogs(array $args, array $assoc): void
    {
        if (! $this->syncLogs->tableExists()) {
            \WP_CLI::error('Sync log table not found.');
            return;
        }

        $days   = isset($assoc['days']) ? max(1, (int) $assoc['days']) : 30;
        $status = isset($assoc['status']) ? (string) $assoc['status'] : null;

        $deleted = $this->syncLogs->cleanupOlderThanDays($days, $status);
        \WP_CLI::line(wp_json_encode([
            'ok'      => true,
            'table'   => 'sync_logs',
            'days'    => $days,
            'status'  => $status ?? 'all',
            'deleted' => $deleted,
        ], JSON_PRETTY_PRINT));
    }

    /**
     * Cleanup old ingestion logs.
     *
     * ## OPTIONS
     * [--days=<n>]
     * : Delete logs older than N days. Default 30.
     * [--status=<status>]
     * : Optional status filter.
     */
    public function cleanupIngestionLogs(array $args, array $assoc): void
    {
        if (! $this->ingestionLogs->tableExists()) {
            \WP_CLI::error('Ingestion log table not found.');
            return;
        }

        $days   = isset($assoc['days']) ? max(1, (int) $assoc['days']) : 30;
        $status = isset($assoc['status']) ? (string) $assoc['status'] : null;

        $deleted = $this->ingestionLogs->cleanupOlderThanDays($days, $status);
        \WP_CLI::line(wp_json_encode([
            'ok'      => true,
            'table'   => 'ingestion_logs',
            'days'    => $days,
            'status'  => $status ?? 'all',
            'deleted' => $deleted,
        ], JSON_PRETTY_PRINT));
    }

    /**
     * Check helmet schema completeness.
     *
     * ## OPTIONS
     * [--limit=<n>]
     * : Number of posts to inspect. Default 200.
     * [--offset=<n>]
     * : Offset for pagination. Default 0.
     * [--format=<format>]
     * : json|table (default json).
     */
    public function schemaCheck(array $args, array $assoc): void
    {
        $limit  = isset($assoc['limit']) ? max(1, (int) $assoc['limit']) : 200;
        $offset = isset($assoc['offset']) ? max(0, (int) $assoc['offset']) : 0;
        $format = (string) ($assoc['format'] ?? 'json');

        $report = $this->schema->audit($limit, $offset);

        if ($format === 'table') {
            $rows = [];
            foreach ($report['issues'] as $issue) {
                if (! is_array($issue)) {
                    continue;
                }
                $rows[] = [
                    'post_id' => isset($issue['post_id']) ? (string) $issue['post_id'] : '',
                    'title'   => isset($issue['title']) ? (string) $issue['title'] : '',
                    'missing' => isset($issue['missing']) && is_array($issue['missing']) ? implode(',', $issue['missing']) : '',
                ];
            }

            if ($rows === []) {
                \WP_CLI::line('No schema issues found.');
            } else {
                \WP_CLI\Utils\format_items('table', $rows, ['post_id', 'title', 'missing']);
            }
            \WP_CLI::line('checked=' . (string) $report['checked'] . ' valid=' . (string) $report['valid'] . ' invalid=' . (string) $report['invalid']);
            return;
        }

        \WP_CLI::line(wp_json_encode($report, JSON_PRETTY_PRINT));
    }

    /**
     * Revenue click report.
     *
     * ## OPTIONS
     * [--days=<n>]
     * : Report window in days. Default 30.
     * [--format=<format>]
     * : json|table (default json).
     */
    public function revenueReport(array $args, array $assoc): void
    {
        $days = isset($assoc['days']) ? max(1, (int) $assoc['days']) : 30;
        $format = (string) ($assoc['format'] ?? 'json');
        $report = $this->revenue->report($days);

        if (! ($report['ok'] ?? false)) {
            \WP_CLI::error((string) ($report['message'] ?? 'Revenue report failed'));
            return;
        }

        if ($format === 'table') {
            $top = isset($report['top_helmets']) && is_array($report['top_helmets']) ? $report['top_helmets'] : [];
            $rows = [];
            foreach ($top as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $rows[] = [
                    'helmet_id' => isset($row['helmet_id']) ? (string) $row['helmet_id'] : '',
                    'title'     => isset($row['title']) ? (string) $row['title'] : '',
                    'clicks'    => isset($row['clicks']) ? (string) $row['clicks'] : '0',
                ];
            }
            if ($rows !== []) {
                \WP_CLI\Utils\format_items('table', $rows, ['helmet_id', 'title', 'clicks']);
            }
            \WP_CLI::line('days=' . (string) $days . ' total_clicks=' . (string) ($report['total_clicks'] ?? 0));
            return;
        }

        \WP_CLI::line(wp_json_encode($report, JSON_PRETTY_PRINT));
    }

    /**
     * Analytics event report.
     *
     * ## OPTIONS
     * [--days=<n>]
     * : Report window in days. Default 7.
     * [--format=<format>]
     * : json|table (default json).
     */
    public function analyticsReport(array $args, array $assoc): void
    {
        if (! $this->analyticsEvents->tableExists()) {
            \WP_CLI::error('Analytics events table not found.');
            return;
        }

        $days = isset($assoc['days']) ? max(1, (int) $assoc['days']) : 7;
        $format = (string) ($assoc['format'] ?? 'json');
        $total = $this->analyticsEvents->total($days);
        $byEvent = $this->analyticsEvents->countByEvent($days);

        if ($format === 'table') {
            $rows = [];
            foreach ($byEvent as $name => $count) {
                $rows[] = ['event' => (string) $name, 'count' => (string) $count];
            }
            if ($rows !== []) {
                \WP_CLI\Utils\format_items('table', $rows, ['event', 'count']);
            }
            \WP_CLI::line('days=' . (string) $days . ' total_events=' . (string) $total);
            return;
        }

        \WP_CLI::line(wp_json_encode([
            'ok'           => true,
            'days'         => $days,
            'total_events' => $total,
            'by_event'     => $byEvent,
        ], JSON_PRETTY_PRINT));
    }

    /**
     * Sync helmets to WooCommerce product/variations.
     *
     * ## OPTIONS
     * [--helmet-id=<id>]
     * : Sync a single helmet post id.
     * [--limit=<n>]
     * : Batch size when helmet-id is not provided. Default 100.
     * [--dry-run]
     * : Validate/match only without persisting.
     */
    public function wooBridgeSync(array $args, array $assoc): void
    {
        $helmetId = isset($assoc['helmet-id']) ? max(0, (int) $assoc['helmet-id']) : 0;
        $limit = isset($assoc['limit']) ? max(1, (int) $assoc['limit']) : 100;
        $dryRun = isset($assoc['dry-run']);

        $result = $helmetId > 0
            ? $this->wooBridge->syncHelmet($helmetId, $dryRun)
            : $this->wooBridge->syncBatch($limit, $dryRun);

        \WP_CLI::line(wp_json_encode($result, JSON_PRETTY_PRINT));
    }

    public function schedulerStatus(array $args, array $assoc): void
    {
        \WP_CLI::line(wp_json_encode($this->scheduler->status(), JSON_PRETTY_PRINT));
    }

    /**
     * ## OPTIONS
     * --task=<task>
     * : sync_pull|retry_failed|cleanup_logs|health_snapshot
     */
    public function schedulerRun(array $args, array $assoc): void
    {
        $task = isset($assoc['task']) ? (string) $assoc['task'] : '';
        if ($task === '') {
            \WP_CLI::error('Provide --task=<sync_pull|retry_failed|cleanup_logs|health_snapshot>');
            return;
        }

        $result = $this->scheduler->runTask($task);
        \WP_CLI::line(wp_json_encode($result, JSON_PRETTY_PRINT));
    }

    /**
     * ## OPTIONS
     * [--title=<title>]
     * : Optional alert title.
     * [--message=<message>]
     * : Optional alert message.
     */
    public function alertsTest(array $args, array $assoc): void
    {
        $title = isset($assoc['title']) ? (string) $assoc['title'] : 'Helmetsan test alert';
        $message = isset($assoc['message']) ? (string) $assoc['message'] : 'Manual test alert from CLI';
        $result = $this->alerts->send('info', $title, $message, [
            'source' => 'wp-cli',
            'timestamp' => current_time('mysql'),
        ]);

        \WP_CLI::line(wp_json_encode($result, JSON_PRETTY_PRINT));
    }

    /**
     * ## OPTIONS
     * [--brand-id=<id>]
     * : Single brand post ID to cascade.
     * [--all]
     * : Cascade all brands.
     */
    public function brandCascade(array $args, array $assoc): void
    {
        $runAll = isset($assoc['all']);
        $brandId = isset($assoc['brand-id']) ? (int) $assoc['brand-id'] : 0;

        if (! $runAll && $brandId <= 0) {
            \WP_CLI::error('Provide --brand-id=<id> or --all');
            return;
        }

        $totalUpdated = 0;
        $results = [];

        if ($runAll) {
            $rows = $this->brands->listBrandOverview();
            foreach ($rows as $row) {
                $id = isset($row['id']) ? (int) $row['id'] : 0;
                if ($id <= 0) {
                    continue;
                }
                $result = $this->brands->cascadeToHelmets($id, 'cli-cascade-all');
                $results[] = $result;
                if (! empty($result['ok'])) {
                    $totalUpdated += (int) ($result['updated_helmets'] ?? 0);
                }
            }
        } else {
            $result = $this->brands->cascadeToHelmets($brandId, 'cli-cascade-one');
            $results[] = $result;
            if (! empty($result['ok'])) {
                $totalUpdated += (int) ($result['updated_helmets'] ?? 0);
            }
        }

        \WP_CLI::line(wp_json_encode([
            'ok' => true,
            'total_updated_helmets' => $totalUpdated,
            'runs' => $results,
        ], JSON_PRETTY_PRINT));
    }

    /**
     * Backfill brand logos into Media Library and bind them on brand posts.
     *
     * ## OPTIONS
     * [--limit=<n>]
     * : Process first N brand posts.
     * [--force]
     * : Re-import and overwrite even if logo meta already exists.
     * [--dry-run]
     * : Simulate only.
     */
    public function mediaBackfillBrandLogos(array $args, array $assoc): void
    {
        $limit = isset($assoc['limit']) ? max(1, (int) $assoc['limit']) : 0;
        $force = isset($assoc['force']);
        $dryRun = isset($assoc['dry-run']);

        $result = $this->media->backfillBrandLogos($limit, $force, $dryRun);
        \WP_CLI::line(wp_json_encode($result, JSON_PRETTY_PRINT));
    }

    private function renderAssoc(array $assoc, string $format): void
    {
        if ($format === 'table') {
            $rows = [];
            foreach ($assoc as $key => $value) {
                $rows[] = [
                    'key'   => (string) $key,
                    'value' => is_scalar($value) ? (string) $value : wp_json_encode($value),
                ];
            }
            \WP_CLI\Utils\format_items('table', $rows, ['key', 'value']);
            return;
        }

        \WP_CLI::line(wp_json_encode($assoc, JSON_PRETTY_PRINT));
    }
}
