<?php

declare(strict_types=1);

namespace Helmetsan\Core\Health;

use Helmetsan\Core\Repository\JsonRepository;
use Helmetsan\Core\Support\Config;
use Helmetsan\Core\Validation\Validator;

final class HealthService
{
    public function __construct(
        private readonly Validator $validator,
        private readonly JsonRepository $repository
    ) {
    }

    public function report(): array
    {
        global $wpdb;

        $schemaVersion = '1.0.0';
        $jsonFiles     = $this->repository->listJsonFiles();

        $helmetCount      = (int) wp_count_posts('helmet')->publish;
        $brandCount       = (int) wp_count_posts('brand')->publish;
        $accessoryCount   = (int) wp_count_posts('accessory')->publish;
        $integrityResults = $this->validator->validateIntegrity();
        $logTable         = $wpdb->prefix . 'helmetsan_ingest_logs';
        $logTableExists   = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $logTable)) === $logTable);
        $failedIngests    = 0;
        $totalLogs        = 0;
        $syncLogTable     = $wpdb->prefix . 'helmetsan_sync_logs';
        $syncTableExists  = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $syncLogTable)) === $syncLogTable);
        $syncTotalLogs    = 0;
        $syncErrorLogs    = 0;
        $revenueTable     = $wpdb->prefix . 'helmetsan_clicks';
        $revenueTableExists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $revenueTable)) === $revenueTable);
        $revenueRows      = 0;
        $analyticsTable   = $wpdb->prefix . 'helmetsan_analytics_events';
        $analyticsTableExists = ($wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $analyticsTable)) === $analyticsTable);
        $analyticsRows    = 0;
        $githubCfg        = (new Config())->githubConfig();
        $alertsCfg        = (new Config())->alertsConfig();
        $githubConfigured = ! empty($githubCfg['owner']) && ! empty($githubCfg['repo']) && ! empty($githubCfg['token']);

        if ($logTableExists) {
            $failedIngests = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$logTable} WHERE status IN ('failed','rejected')");
            $totalLogs     = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$logTable}");
        }
        if ($syncTableExists) {
            $syncTotalLogs = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$syncLogTable}");
            $syncErrorLogs = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$syncLogTable} WHERE status IN ('error')");
        }
        if ($revenueTableExists) {
            $revenueRows = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$revenueTable}");
        }
        if ($analyticsTableExists) {
            $analyticsRows = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$analyticsTable}");
        }

        return [
            'status'   => $integrityResults['ok'] ? 'healthy' : 'degraded',
            'schema'   => [
                'version'          => $schemaVersion,
                'migration_needed' => false,
            ],
            'database' => [
                'cpt_helmet_rows'    => $helmetCount,
                'cpt_brand_rows'     => $brandCount,
                'cpt_accessory_rows' => $accessoryCount,
            ],
            'repository' => [
                'root_exists' => $this->repository->exists(),
                'json_files'  => count($jsonFiles),
            ],
            'github_sync' => [
                'enabled'    => ! empty($githubCfg['enabled']),
                'configured' => $githubConfigured,
                'owner'      => isset($githubCfg['owner']) ? (string) $githubCfg['owner'] : '',
                'repo'       => isset($githubCfg['repo']) ? (string) $githubCfg['repo'] : '',
                'branch'     => isset($githubCfg['branch']) ? (string) $githubCfg['branch'] : '',
                'remote_path'=> isset($githubCfg['remote_path']) ? (string) $githubCfg['remote_path'] : '',
                'sync_run_profile' => isset($githubCfg['sync_run_profile']) ? (string) $githubCfg['sync_run_profile'] : 'pull-only',
                'sync_profile_lock' => ! empty($githubCfg['sync_profile_lock']),
                'push_mode'  => isset($githubCfg['push_mode']) ? (string) $githubCfg['push_mode'] : 'commit',
                'pr_reuse_open' => ! empty($githubCfg['pr_reuse_open']),
                'pr_auto_merge' => ! empty($githubCfg['pr_auto_merge']),
            ],
            'ingestion_logs' => [
                'table_exists' => $logTableExists,
                'rows'         => $totalLogs,
                'failed_rows'  => $failedIngests,
                'lock_active'  => (get_transient('helmetsan_ingest_lock') !== false),
            ],
            'sync_logs' => [
                'table_exists' => $syncTableExists,
                'rows'         => $syncTotalLogs,
                'error_rows'   => $syncErrorLogs,
            ],
            'revenue' => [
                'table_exists' => $revenueTableExists,
                'rows'         => $revenueRows,
            ],
            'analytics_events' => [
                'table_exists' => $analyticsTableExists,
                'rows'         => $analyticsRows,
            ],
            'scheduler' => [
                'enabled' => ! empty((new Config())->schedulerConfig()['enable_scheduler']),
                'next_runs' => [
                    'sync_pull' => wp_next_scheduled('helmetsan_cron_sync_pull'),
                    'retry_failed' => wp_next_scheduled('helmetsan_cron_retry_failed'),
                    'cleanup_logs' => wp_next_scheduled('helmetsan_cron_cleanup_logs'),
                    'health_snapshot' => wp_next_scheduled('helmetsan_cron_health_snapshot'),
                ],
            ],
            'alerts' => [
                'enabled' => ! empty($alertsCfg['enabled']),
                'email_enabled' => ! empty($alertsCfg['email_enabled']),
                'email_to' => isset($alertsCfg['to_email']) ? (string) $alertsCfg['to_email'] : '',
                'slack_enabled' => ! empty($alertsCfg['slack_enabled']),
            ],
            'integrity' => [
                'ok'     => $integrityResults['ok'],
                'errors' => $integrityResults['errors'],
            ],
        ];
    }
}
