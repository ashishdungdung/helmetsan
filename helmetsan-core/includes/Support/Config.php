<?php

declare(strict_types=1);

namespace Helmetsan\Core\Support;

final class Config
{
    public const OPTION_ANALYTICS = 'helmetsan_analytics';
    public const OPTION_ENGINE    = 'helmetsan_engine';
    public const OPTION_GITHUB    = 'helmetsan_github';
    public const OPTION_REVENUE   = 'helmetsan_revenue';
    public const OPTION_SCHEDULER = 'helmetsan_scheduler';
    public const OPTION_ALERTS    = 'helmetsan_alerts';
    public const OPTION_MEDIA     = 'helmetsan_media';

    public function analyticsDefaults(): array
    {
        return [
            'enable_analytics'                         => false,
            'analytics_respect_monsterinsights'       => true,
            'ga4_measurement_id'                      => '',
            'gtm_container_id'                        => '',
            'enable_enhanced_event_tracking'          => false,
            'enable_internal_search_tracking'         => false,
            'enable_heatmap_clarity'                  => false,
            'clarity_project_id'                      => '',
            'enable_heatmap_hotjar'                   => false,
            'hotjar_site_id'                          => '',
            'hotjar_version'                          => '6',
        ];
    }

    public function dataRoot(): string
    {
        return WP_CONTENT_DIR . '/uploads/helmetsan-data';
    }

    public function engineDefaults(): array
    {
        return [
            'default_batch_size'      => 100,
            'max_batch_size'          => 500,
            'default_ai_enabled'      => false,
            'dedupe_processing'       => true,
            'skip_unchanged_entities' => true,
        ];
    }

    public function githubDefaults(): array
    {
        return [
            'enabled'      => false,
            'owner'        => '',
            'repo'         => '',
            'token'        => '',
            'branch'       => 'main',
            'remote_path'  => '',
            'sync_json_only' => true,
            'sync_run_profile' => 'pull-only',
            'sync_profile_lock' => false,
            'push_mode'    => 'commit',
            'pr_branch_prefix' => 'helmetsan-sync',
            'pr_reuse_open' => true,
            'pr_auto_merge' => false,
        ];
    }

    public function revenueDefaults(): array
    {
        return [
            'enable_redirect_tracking' => true,
            'default_affiliate_network'=> 'amazon',
            'amazon_tag'               => 'helmetsan-20',
            'redirect_status_code'     => 302,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function revenueConfig(): array
    {
        $saved = get_option(self::OPTION_REVENUE, []);
        $cfg   = wp_parse_args(is_array($saved) ? $saved : [], $this->revenueDefaults());

        return $cfg;
    }

    public function schedulerDefaults(): array
    {
        return [
            'enable_scheduler'         => false,
            'sync_pull_enabled'        => false,
            'sync_pull_interval_hours' => 6,
            'sync_pull_limit'          => 200,
            'sync_pull_apply_brands'   => true,
            'sync_pull_apply_helmets'  => false,
            'retry_failed_enabled'     => false,
            'retry_failed_limit'       => 100,
            'retry_failed_batch_size'  => 50,
            'cleanup_logs_enabled'     => true,
            'cleanup_logs_days'        => 30,
            'health_snapshot_enabled'  => true,
        ];
    }

    public function alertsDefaults(): array
    {
        return [
            'enabled'                => false,
            'email_enabled'          => true,
            'to_email'               => '',
            'subject_prefix'         => '[Helmetsan]',
            'slack_enabled'          => false,
            'slack_webhook_url'      => '',
            'alert_on_sync_error'    => true,
            'alert_on_ingest_error'  => true,
            'alert_on_health_warning'=> false,
        ];
    }

    public function mediaDefaults(): array
    {
        return [
            'enable_media_engine'   => true,
            'simpleicons_enabled'   => true,
            'brandfetch_enabled'    => true,
            'brandfetch_token'      => '',
            'logodev_enabled'       => true,
            'logodev_publishable_key' => '',
            'logodev_secret_key'    => '',
            // Backward compatibility with previous single-key setup.
            'logodev_token'         => '',
            'wikimedia_enabled'     => true,
            'cache_ttl_hours'       => 12,
            'auto_sideload_enabled' => false,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function schedulerConfig(): array
    {
        $saved = get_option(self::OPTION_SCHEDULER, []);
        return wp_parse_args(is_array($saved) ? $saved : [], $this->schedulerDefaults());
    }

    /**
     * @return array<string,mixed>
     */
    public function alertsConfig(): array
    {
        $saved = get_option(self::OPTION_ALERTS, []);
        $cfg   = wp_parse_args(is_array($saved) ? $saved : [], $this->alertsDefaults());

        if (defined('HELMETSAN_ALERTS_TO_EMAIL') && HELMETSAN_ALERTS_TO_EMAIL !== '') {
            $cfg['to_email'] = (string) HELMETSAN_ALERTS_TO_EMAIL;
        }
        if (defined('HELMETSAN_ALERTS_SLACK_WEBHOOK') && HELMETSAN_ALERTS_SLACK_WEBHOOK !== '') {
            $cfg['slack_webhook_url'] = (string) HELMETSAN_ALERTS_SLACK_WEBHOOK;
        }

        return $cfg;
    }

    /**
     * @return array<string,mixed>
     */
    public function githubConfig(): array
    {
        $saved = get_option(self::OPTION_GITHUB, []);
        $cfg   = wp_parse_args(is_array($saved) ? $saved : [], $this->githubDefaults());

        if (defined('HELMETSAN_GITHUB_OWNER') && HELMETSAN_GITHUB_OWNER !== '') {
            $cfg['owner'] = (string) HELMETSAN_GITHUB_OWNER;
        }
        if (defined('HELMETSAN_GITHUB_REPO') && HELMETSAN_GITHUB_REPO !== '') {
            $cfg['repo'] = (string) HELMETSAN_GITHUB_REPO;
        }
        if (defined('HELMETSAN_GITHUB_TOKEN') && HELMETSAN_GITHUB_TOKEN !== '') {
            $cfg['token'] = (string) HELMETSAN_GITHUB_TOKEN;
        }
        if (defined('HELMETSAN_GITHUB_BRANCH') && HELMETSAN_GITHUB_BRANCH !== '') {
            $cfg['branch'] = (string) HELMETSAN_GITHUB_BRANCH;
        }
        if (defined('HELMETSAN_GITHUB_REMOTE_PATH') && HELMETSAN_GITHUB_REMOTE_PATH !== '') {
            $cfg['remote_path'] = (string) HELMETSAN_GITHUB_REMOTE_PATH;
        }

        return $cfg;
    }

    /**
     * @return array<string,mixed>
     */
    public function mediaConfig(): array
    {
        $saved = get_option(self::OPTION_MEDIA, []);
        $cfg   = wp_parse_args(is_array($saved) ? $saved : [], $this->mediaDefaults());

        if (defined('HELMETSAN_BRANDFETCH_TOKEN') && HELMETSAN_BRANDFETCH_TOKEN !== '') {
            $cfg['brandfetch_token'] = (string) HELMETSAN_BRANDFETCH_TOKEN;
        }
        if (defined('HELMETSAN_LOGODEV_TOKEN') && HELMETSAN_LOGODEV_TOKEN !== '') {
            $cfg['logodev_token'] = (string) HELMETSAN_LOGODEV_TOKEN;
        }
        if (defined('HELMETSAN_LOGODEV_PUBLISHABLE_KEY') && HELMETSAN_LOGODEV_PUBLISHABLE_KEY !== '') {
            $cfg['logodev_publishable_key'] = (string) HELMETSAN_LOGODEV_PUBLISHABLE_KEY;
        }
        if (defined('HELMETSAN_LOGODEV_SECRET_KEY') && HELMETSAN_LOGODEV_SECRET_KEY !== '') {
            $cfg['logodev_secret_key'] = (string) HELMETSAN_LOGODEV_SECRET_KEY;
        }

        if ((string) ($cfg['logodev_publishable_key'] ?? '') === '' && (string) ($cfg['logodev_token'] ?? '') !== '') {
            $cfg['logodev_publishable_key'] = (string) $cfg['logodev_token'];
        }
        if ((string) ($cfg['logodev_secret_key'] ?? '') === '' && (string) ($cfg['logodev_token'] ?? '') !== '') {
            $cfg['logodev_secret_key'] = (string) $cfg['logodev_token'];
        }

        return $cfg;
    }
}
