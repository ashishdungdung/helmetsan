<?php

declare(strict_types=1);

namespace Helmetsan\Core\Admin;

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
use Helmetsan\Core\Revenue\RevenueService;
use Helmetsan\Core\Scheduler\SchedulerService;
use Helmetsan\Core\Support\Config;
use Helmetsan\Core\Sync\SyncService;
use Helmetsan\Core\Sync\LogRepository as SyncLogRepository;
use Helmetsan\Core\WooBridge\WooBridgeService;

final class Admin
{
    public function __construct(
        private readonly HealthService $health,
        private readonly SmokeTestService $smoke,
        private readonly ChecklistService $checklist,
        private readonly DocsService $docs,
        private readonly Config $config,
        private readonly LogRepository $ingestionLogs,
        private readonly IngestionService $ingestion,
        private readonly SyncService $sync,
        private readonly SyncLogRepository $syncLogs,
        private readonly RevenueService $revenue,
        private readonly ImportService $importService,
        private readonly ExportService $exportService,
        private readonly EventRepository $analyticsEvents,
        private readonly SchedulerService $scheduler,
        private readonly AlertService $alerts,
        private readonly BrandService $brands,
        private readonly WooBridgeService $wooBridge
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'registerMenu']);
        add_action('admin_init', [$this, 'registerSettings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAssets']);
        add_filter('admin_body_class', [$this, 'addAdminBodyClass']);
        add_action('admin_init', [$this, 'handleIngestionActions']);
        add_action('admin_init', [$this, 'handleBrandActions']);
        add_action('admin_post_helmetsan_sync_pull', [$this, 'handleSyncPullAction']);
        add_action('admin_post_helmetsan_import', [$this, 'handleImportAction']);
        add_action('admin_post_helmetsan_export', [$this, 'handleExportAction']);
        add_action('admin_post_helmetsan_media_api_test', [$this, 'handleMediaApiTestAction']);
        add_action('admin_post_helmetsan_woo_bridge_sync', [$this, 'handleWooBridgeSyncAction']);
        add_action('admin_post_helmetsan_scheduler_task', [$this, 'handleSchedulerTaskAction']);
    }

    public function registerMenu(): void
    {
        add_menu_page('Helmetsan', 'Helmetsan', 'manage_options', 'helmetsan-dashboard', [$this, 'dashboardPage'], 'dashicons-admin-site', 2);

        add_submenu_page('helmetsan-dashboard', 'Catalog', 'Catalog', 'manage_options', 'helmetsan-catalog', [$this, 'catalogPage']);
        add_submenu_page('helmetsan-dashboard', 'Commerce Engines', 'Commerce Engines', 'manage_options', 'helmetsan-commerce-engines', [$this, 'commerceEnginesPage']);
        add_submenu_page('helmetsan-dashboard', 'Woo Bridge', 'Woo Bridge', 'manage_options', 'helmetsan-woo-bridge', [$this, 'wooBridgePage']);
        add_submenu_page('helmetsan-dashboard', 'Brands', 'Brands', 'manage_options', 'helmetsan-brands', [$this, 'brandsPage']);
        add_submenu_page('helmetsan-dashboard', 'Ingestion', 'Ingestion', 'manage_options', 'helmetsan-ingestion', [$this, 'ingestionPage']);
        add_submenu_page('helmetsan-dashboard', 'Sync Logs', 'Sync Logs', 'manage_options', 'helmetsan-sync-logs', [$this, 'syncLogsPage']);
        add_submenu_page('helmetsan-dashboard', 'Repo Health', 'Repo Health', 'manage_options', 'helmetsan-repo-health', [$this, 'repoHealthPage']);
        add_submenu_page('helmetsan-dashboard', 'Analytics', 'Analytics', 'manage_options', 'helmetsan-analytics', [$this, 'analyticsPage']);
        add_submenu_page('helmetsan-dashboard', 'Revenue', 'Revenue', 'manage_options', 'helmetsan-revenue', [$this, 'revenuePage']);
        add_submenu_page('helmetsan-dashboard', 'Contributions', 'Contributions', 'manage_options', 'helmetsan-contributions', [$this, 'contributionsPage']);
        add_submenu_page('helmetsan-dashboard', 'Import/Export', 'Import/Export', 'manage_options', 'helmetsan-import-export', [$this, 'importExportPage']);
        add_submenu_page('helmetsan-dashboard', 'Go Live', 'Go Live', 'manage_options', 'helmetsan-go-live', [$this, 'goLivePage']);
        add_submenu_page('helmetsan-dashboard', 'Docs', 'Docs', 'manage_options', 'helmetsan-docs', [$this, 'docsPage']);
        add_submenu_page('helmetsan-dashboard', 'Settings', 'Settings', 'manage_options', 'helmetsan-settings', [$this, 'settingsPage']);
    }

    public function enqueueAssets(string $hook): void
    {
        if (! $this->isHelmetsanAdmin()) {
            return;
        }

        wp_enqueue_style(
            'helmetsan-admin-mac-ui',
            HELMETSAN_CORE_URL . 'assets/admin/mac-ui.css',
            [],
            HELMETSAN_CORE_VERSION
        );
        wp_enqueue_script(
            'helmetsan-admin-mac-ui',
            HELMETSAN_CORE_URL . 'assets/admin/mac-ui.js',
            [],
            HELMETSAN_CORE_VERSION,
            true
        );
    }

    public function addAdminBodyClass(string $classes): string
    {
        if (! $this->isHelmetsanAdmin()) {
            return $classes;
        }

        return trim($classes . ' helmetsan-admin helmetsan-mac-ui');
    }

    private function isHelmetsanAdmin(): bool
    {
        $page = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';
        return str_starts_with($page, 'helmetsan-');
    }

    private function renderAppHeader(string $title, string $subtitle = ''): void
    {
        $links = [
            'helmetsan-dashboard' => 'Discover',
            'helmetsan-catalog' => 'Catalog',
            'helmetsan-commerce-engines' => 'Commerce',
            'helmetsan-woo-bridge' => 'Woo Bridge',
            'helmetsan-brands' => 'Brands',
            'helmetsan-media-engine' => 'Media',
            'helmetsan-sync-logs' => 'Sync',
            'helmetsan-analytics' => 'Analytics',
            'helmetsan-go-live' => 'Go Live',
            'helmetsan-settings' => 'Settings',
        ];
        $active = isset($_GET['page']) ? sanitize_key((string) $_GET['page']) : '';

        echo '<div class="hs-shell-header">';
        echo '<div class="hs-shell-title-wrap">';
        echo '<h1 class="hs-shell-title">' . esc_html($title) . '</h1>';
        if ($subtitle !== '') {
            echo '<p class="hs-shell-subtitle">' . esc_html($subtitle) . '</p>';
        }
        echo '</div>';
        echo '<div class="hs-shell-tabs">';
        foreach ($links as $slug => $label) {
            $url = add_query_arg(['page' => $slug], admin_url('admin.php'));
            $class = $slug === $active ? 'hs-tab is-active' : 'hs-tab';
            echo '<a class="' . esc_attr($class) . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
        echo '</div>';
        echo '</div>';
    }

    /**
     * @param array<int,array{label:string,value:string,page:string}> $cards
     */
    private function renderMetricCards(array $cards): void
    {
        echo '<div class="hs-card-grid">';
        foreach ($cards as $card) {
            $url = add_query_arg(['page' => $card['page']], admin_url('admin.php'));
            echo '<a class="hs-card" href="' . esc_url($url) . '">';
            echo '<span class="hs-card-label">' . esc_html($card['label']) . '</span>';
            echo '<strong class="hs-card-value">' . esc_html($card['value']) . '</strong>';
            echo '</a>';
        }
        echo '</div>';
    }

    /**
     * @return array<string,mixed>
     */
    private function engineSnapshot(): array
    {
        $cached = get_transient('helmetsan_engine_snapshot');
        if (is_array($cached)) {
            return $cached;
        }

        global $wpdb;

        $dealerCount = (int) wp_count_posts('dealer')->publish;
        $distributorCount = (int) wp_count_posts('distributor')->publish;
        $comparisonCount = (int) wp_count_posts('comparison')->publish;
        $recommendationCount = (int) wp_count_posts('recommendation')->publish;
        $marketplaces = \Helmetsan\Core\Commerce\CommerceService::readMarketplacesIndex();
        $marketplaceCount = is_array($marketplaces) ? count($marketplaces) : 0;

        $pricingCount = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = 'pricing_records_json' AND meta_value <> ''"
        );
        $offersCount = (int) $wpdb->get_var(
            "SELECT COUNT(DISTINCT post_id) FROM {$wpdb->postmeta} WHERE meta_key = 'best_offer_json' AND meta_value <> ''"
        );

        $result = [
            'dealers' => $dealerCount,
            'distributors' => $distributorCount,
            'comparisons' => $comparisonCount,
            'recommendations' => $recommendationCount,
            'marketplaces' => $marketplaceCount,
            'pricing' => $pricingCount,
            'offers' => $offersCount,
        ];
        
        set_transient('helmetsan_engine_snapshot', $result, HOUR_IN_SECONDS);
        return $result;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function getPostTypeRows(string $postType, int $limit = 5): array
    {
        $posts = get_posts([
            'post_type' => $postType,
            'post_status' => ['publish', 'draft', 'private', 'pending'],
            'posts_per_page' => max(1, $limit),
            'orderby' => 'modified',
            'order' => 'DESC',
        ]);

        $rows = [];
        foreach ($posts as $post) {
            if (! ($post instanceof \WP_Post)) {
                continue;
            }
            $rows[] = [
                'id' => (int) $post->ID,
                'title' => (string) $post->post_title,
                'status' => (string) $post->post_status,
                'modified' => (string) $post->post_modified,
            ];
        }

        return $rows;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function getMarketplaceRows(int $limit = 5): array
    {
        $index = \Helmetsan\Core\Commerce\CommerceService::readMarketplacesIndex();
        if (! is_array($index) || $index === []) {
            return [];
        }

        $rows = [];
        foreach ($index as $item) {
            if (! is_array($item)) {
                continue;
            }
            $rows[] = [
                'id' => (string) ($item['id'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'countries' => isset($item['country_codes']) && is_array($item['country_codes']) ? implode(', ', array_map('strval', $item['country_codes'])) : '',
                'website' => (string) ($item['website'] ?? ''),
            ];
        }

        return array_slice($rows, 0, max(1, $limit));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function getPricingRows(int $limit = 5): array
    {
        $posts = get_posts([
            'post_type' => 'helmet',
            'post_status' => 'publish',
            'posts_per_page' => max(1, $limit * 2),
            'meta_query' => [
                [
                    'key' => 'pricing_records_json',
                    'compare' => 'EXISTS',
                ],
            ],
        ]);

        $rows = [];
        foreach ($posts as $post) {
            if (! ($post instanceof \WP_Post)) {
                continue;
            }
            $raw = (string) get_post_meta($post->ID, 'pricing_records_json', true);
            $decoded = json_decode($raw, true);
            if (! is_array($decoded) || $decoded === []) {
                continue;
            }
            $first = $decoded[0];
            if (! is_array($first)) {
                continue;
            }
            $rows[] = [
                'helmet' => (string) $post->post_title,
                'country' => strtoupper((string) ($first['country_code'] ?? '')),
                'currency' => strtoupper((string) ($first['currency'] ?? '')),
                'price' => isset($first['current_price']) ? (string) $first['current_price'] : '',
                'marketplace' => (string) ($first['marketplace_id'] ?? ''),
            ];
            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    private function getOfferRows(int $limit = 5): array
    {
        $posts = get_posts([
            'post_type' => 'helmet',
            'post_status' => 'publish',
            'posts_per_page' => max(1, $limit * 2),
            'meta_query' => [
                [
                    'key' => 'best_offer_json',
                    'compare' => 'EXISTS',
                ],
            ],
        ]);

        $rows = [];
        foreach ($posts as $post) {
            if (! ($post instanceof \WP_Post)) {
                continue;
            }
            $raw = (string) get_post_meta($post->ID, 'best_offer_json', true);
            $best = json_decode($raw, true);
            if (! is_array($best) || $best === []) {
                continue;
            }
            $rows[] = [
                'helmet' => (string) $post->post_title,
                'country' => strtoupper((string) ($best['country_code'] ?? '')),
                'shop' => (string) ($best['shop_name'] ?? ''),
                'currency' => strtoupper((string) ($best['currency'] ?? '')),
                'price' => isset($best['offer_price']) ? (string) $best['offer_price'] : '',
            ];
            if (count($rows) >= $limit) {
                break;
            }
        }

        return $rows;
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    private function renderMiniTable(string $title, array $headers, array $rows): void
    {
        echo '<div class="hs-panel">';
        echo '<h3>' . esc_html($title) . '</h3>';
        echo '<table class="widefat striped hs-table-compact"><thead><tr>';
        foreach ($headers as $header) {
            echo '<th>' . esc_html((string) $header) . '</th>';
        }
        echo '</tr></thead><tbody>';
        if ($rows === []) {
            echo '<tr><td colspan="' . esc_attr((string) count($headers)) . '">No records yet.</td></tr>';
        } else {
            foreach ($rows as $row) {
                echo '<tr>';
                foreach (array_keys($headers) as $key) {
                    echo '<td>' . esc_html((string) ($row[$key] ?? '')) . '</td>';
                }
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        echo '</div>';
    }

    private function renderStatusPill(string $text, bool $ok): string
    {
        $class = $ok ? 'hs-pill hs-pill--ok' : 'hs-pill hs-pill--fail';
        return '<span class="' . esc_attr($class) . '">' . esc_html($text) . '</span>';
    }

    private function renderScoreBar(int $score): string
    {
        $safeScore = max(0, min(100, $score));
        $class = $safeScore >= 80 ? 'hs-scorebar hs-scorebar--ok' : 'hs-scorebar hs-scorebar--warn';
        return '<div class="' . esc_attr($class) . '"><span style="width:' . esc_attr((string) $safeScore) . '%;"></span></div>';
    }

    public function registerSettings(): void
    {
        register_setting('helmetsan_settings', Config::OPTION_ANALYTICS, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitizeAnalytics'],
            'default'           => $this->config->analyticsDefaults(),
        ]);

        register_setting('helmetsan_settings', Config::OPTION_GITHUB, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitizeGithub'],
            'default'           => $this->config->githubDefaults(),
        ]);

        register_setting('helmetsan_settings', Config::OPTION_REVENUE, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitizeRevenue'],
            'default'           => $this->config->revenueDefaults(),
        ]);

        register_setting('helmetsan_settings', Config::OPTION_SCHEDULER, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitizeScheduler'],
            'default'           => $this->config->schedulerDefaults(),
        ]);

        register_setting('helmetsan_settings', Config::OPTION_ALERTS, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitizeAlerts'],
            'default'           => $this->config->alertsDefaults(),
        ]);

        register_setting('helmetsan_settings', Config::OPTION_MEDIA, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitizeMedia'],
            'default'           => $this->config->mediaDefaults(),
        ]);

        register_setting('helmetsan_settings', Config::OPTION_WOO_BRIDGE, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitizeWooBridge'],
            'default'           => $this->config->wooBridgeDefaults(),
        ]);

        register_setting('helmetsan_settings', Config::OPTION_MARKETPLACE, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitizeMarketplace'],
            'default'           => $this->config->marketplaceDefaults(),
        ]);

        register_setting('helmetsan_settings', Config::OPTION_GEO, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitizeGeo'],
            'default'           => $this->config->geoDefaults(),
        ]);

        register_setting('helmetsan_settings', Config::OPTION_FEATURES, [
            'type'              => 'array',
            'sanitize_callback' => [$this, 'sanitizeFeatures'],
            'default'           => $this->config->featuresDefaults(),
        ]);
    }

    public function sanitizeFeatures($value): array
    {
        $defaults = $this->config->featuresDefaults();
        if (!is_array($value)) {
            return $defaults;
        }

        $clean = [];
        $clean['enable_technical_analysis'] = !empty($value['enable_technical_analysis']);
        $clean['enable_ai_chatbot']         = !empty($value['enable_ai_chatbot']);

        return array_merge($defaults, $clean);
    }

    public function sanitizeAnalytics($value): array
    {
        $defaults = $this->config->analyticsDefaults();
        $value    = is_array($value) ? $value : [];
        $merged   = wp_parse_args($value, $defaults);

        $bools = [
            'enable_analytics',
            'analytics_respect_monsterinsights',
            'enable_enhanced_event_tracking',
            'enable_internal_search_tracking',
            'enable_heatmap_clarity',
            'enable_heatmap_hotjar',
        ];

        foreach ($bools as $key) {
            $merged[$key] = ! empty($merged[$key]);
        }

        $merged['ga4_measurement_id'] = sanitize_text_field((string) $merged['ga4_measurement_id']);
        $merged['gtm_container_id']   = sanitize_text_field((string) $merged['gtm_container_id']);
        $merged['clarity_project_id'] = sanitize_text_field((string) $merged['clarity_project_id']);
        $merged['hotjar_site_id']     = sanitize_text_field((string) $merged['hotjar_site_id']);
        $merged['hotjar_version']     = sanitize_text_field((string) $merged['hotjar_version']);

        return $merged;
    }

    public function sanitizeGithub($value): array
    {
        $defaults = $this->config->githubDefaults();
        $value    = is_array($value) ? $value : [];
        $merged   = wp_parse_args($value, $defaults);

        $merged['enabled']       = ! empty($merged['enabled']);
        $merged['sync_json_only']= ! empty($merged['sync_json_only']);
        $merged['sync_profile_lock'] = ! empty($merged['sync_profile_lock']);
        $merged['pr_reuse_open'] = ! empty($merged['pr_reuse_open']);
        $merged['pr_auto_merge'] = ! empty($merged['pr_auto_merge']);
        $merged['owner']         = sanitize_text_field((string) $merged['owner']);
        $merged['repo']          = sanitize_text_field((string) $merged['repo']);
        $merged['token']         = sanitize_text_field((string) $merged['token']);
        $merged['branch']        = sanitize_text_field((string) $merged['branch']);
        $merged['remote_path']   = trim(sanitize_text_field((string) $merged['remote_path']), '/');
        $profile = sanitize_text_field((string) $merged['sync_run_profile']);
        $merged['sync_run_profile'] = in_array($profile, ['pull-only', 'pull+brands', 'pull+all'], true) ? $profile : 'pull-only';
        $pushMode = sanitize_text_field((string) $merged['push_mode']);
        $merged['push_mode'] = in_array($pushMode, ['commit', 'pr'], true) ? $pushMode : 'commit';
        $merged['pr_branch_prefix'] = sanitize_text_field((string) $merged['pr_branch_prefix']);

        return $merged;
    }

    public function sanitizeRevenue($value): array
    {
        $defaults = $this->config->revenueDefaults();
        $value    = is_array($value) ? $value : [];
        $merged   = wp_parse_args($value, $defaults);

        $merged['enable_redirect_tracking'] = ! empty($merged['enable_redirect_tracking']);
        $merged['default_affiliate_network'] = sanitize_text_field((string) $merged['default_affiliate_network']);
        $merged['amazon_tag'] = sanitize_text_field((string) $merged['amazon_tag']);
        $code = (int) $merged['redirect_status_code'];
        $merged['redirect_status_code'] = in_array($code, [301, 302, 307, 308], true) ? $code : 302;

        return $merged;
    }

    public function sanitizeScheduler($value): array
    {
        $defaults = $this->config->schedulerDefaults();
        $value    = is_array($value) ? $value : [];
        $merged   = wp_parse_args($value, $defaults);

        $merged['enable_scheduler'] = ! empty($merged['enable_scheduler']);
        $merged['sync_pull_enabled'] = ! empty($merged['sync_pull_enabled']);
        $merged['sync_pull_apply_brands'] = ! empty($merged['sync_pull_apply_brands']);
        $merged['sync_pull_apply_helmets'] = ! empty($merged['sync_pull_apply_helmets']);
        $merged['retry_failed_enabled'] = ! empty($merged['retry_failed_enabled']);
        $merged['cleanup_logs_enabled'] = ! empty($merged['cleanup_logs_enabled']);
        $merged['health_snapshot_enabled'] = ! empty($merged['health_snapshot_enabled']);

        $merged['sync_pull_interval_hours'] = max(1, (int) $merged['sync_pull_interval_hours']);
        $merged['sync_pull_limit'] = max(1, (int) $merged['sync_pull_limit']);
        $merged['retry_failed_limit'] = max(1, (int) $merged['retry_failed_limit']);
        $merged['retry_failed_batch_size'] = max(1, (int) $merged['retry_failed_batch_size']);
        $merged['cleanup_logs_days'] = max(1, (int) $merged['cleanup_logs_days']);

        return $merged;
    }

    public function sanitizeAlerts($value): array
    {
        $defaults = $this->config->alertsDefaults();
        $value    = is_array($value) ? $value : [];
        $merged   = wp_parse_args($value, $defaults);

        $bools = [
            'enabled',
            'email_enabled',
            'slack_enabled',
            'alert_on_sync_error',
            'alert_on_ingest_error',
            'alert_on_health_warning',
        ];
        foreach ($bools as $key) {
            $merged[$key] = ! empty($merged[$key]);
        }

        $merged['to_email'] = sanitize_email((string) $merged['to_email']);
        $merged['subject_prefix'] = sanitize_text_field((string) $merged['subject_prefix']);
        $merged['slack_webhook_url'] = esc_url_raw((string) $merged['slack_webhook_url']);

        return $merged;
    }

    public function sanitizeMedia($value): array
    {
        $defaults = $this->config->mediaDefaults();
        $value    = is_array($value) ? $value : [];
        $merged   = wp_parse_args($value, $defaults);
        $existing = wp_parse_args((array) get_option(Config::OPTION_MEDIA, []), $defaults);
        $existing = wp_parse_args((array) get_option(Config::OPTION_MEDIA, []), $defaults);

        $bools = [
            'enable_media_engine',
            'simpleicons_enabled',
            'brandfetch_enabled',
            'logodev_enabled',
            'wikimedia_enabled',
            'auto_sideload_enabled',
        ];
        foreach ($bools as $key) {
            $merged[$key] = ! empty($merged[$key]);
        }

        $brandfetchToken = sanitize_text_field((string) $merged['brandfetch_token']);
        $logodevPublishable = sanitize_text_field((string) ($merged['logodev_publishable_key'] ?? ''));
        $logodevSecret = sanitize_text_field((string) ($merged['logodev_secret_key'] ?? ''));
        $logodevToken = sanitize_text_field((string) ($merged['logodev_token'] ?? ''));
        // Keep existing tokens if field is submitted empty (masked UI behavior).
        $merged['brandfetch_token'] = $brandfetchToken !== '' ? $brandfetchToken : (string) ($existing['brandfetch_token'] ?? '');
        $merged['logodev_publishable_key'] = $logodevPublishable !== '' ? $logodevPublishable : (string) ($existing['logodev_publishable_key'] ?? '');
        $merged['logodev_secret_key'] = $logodevSecret !== '' ? $logodevSecret : (string) ($existing['logodev_secret_key'] ?? '');
        $merged['logodev_token'] = $logodevToken !== '' ? $logodevToken : (string) ($existing['logodev_token'] ?? '');
        $merged['cache_ttl_hours'] = max(1, (int) $merged['cache_ttl_hours']);

        return $merged;
    }

    public function sanitizeWooBridge($value): array
    {
        $defaults = $this->config->wooBridgeDefaults();
        $value    = is_array($value) ? $value : [];
        $merged   = wp_parse_args($value, $defaults);

        $merged['enable_bridge'] = ! empty($merged['enable_bridge']);
        $merged['auto_sync_on_save'] = ! empty($merged['auto_sync_on_save']);
        $merged['publish_products'] = ! empty($merged['publish_products']);
        $merged['default_currency'] = strtoupper(sanitize_text_field((string) $merged['default_currency']));
        $merged['sync_limit_default'] = max(1, (int) $merged['sync_limit_default']);

        return $merged;
    }

    public function sanitizeMarketplace($value): array
    {
        $defaults = $this->config->marketplaceDefaults();
        $value    = is_array($value) ? $value : [];
        $merged   = wp_parse_args($value, $defaults);
        $existing = wp_parse_args((array) get_option(Config::OPTION_MARKETPLACE, []), $defaults);

        // Booleans
        $bools = ['amazon_enabled', 'allegro_enabled', 'jumia_enabled'];
        foreach ($bools as $key) {
            $merged[$key] = ! empty($merged[$key]);
        }

        // Secrets (masked)
        $secrets = [
            'amazon_client_secret', 'amazon_refresh_token',
            'allegro_client_secret', 'allegro_refresh_token',
            'jumia_api_key'
        ];
        foreach ($secrets as $key) {
            $val = sanitize_text_field((string) ($merged[$key] ?? ''));
            $merged[$key] = $val !== '' ? $val : (string) ($existing[$key] ?? '');
        }

        // Standard text
        $text = ['amazon_client_id', 'amazon_affiliate_tag', 'allegro_client_id', 'allegro_affiliate_id', 'jumia_affiliate_id'];
        foreach ($text as $key) {
            $merged[$key] = sanitize_text_field((string) ($merged[$key] ?? ''));
        }

        // Feed configs are complex nested arrays, simplicity for now: maintain existing structure if not posted
        // In a real UI, we'd iterate `affiliate_feeds` and sanitize each.
        // For MVP, we presume the settings page might not expose full feed editing yet, or we just trust admin input for now (sanitized recursively).
        if (isset($value['affiliate_feeds']) && is_array($value['affiliate_feeds'])) {
            $merged['affiliate_feeds'] = $value['affiliate_feeds']; // TODO: Deep sanitize
        } else {
             $merged['affiliate_feeds'] = $defaults['affiliate_feeds'];
        }

        return $merged;
    }

    public function sanitizeGeo($value): array
    {
        $defaults = $this->config->geoDefaults();
        $value    = is_array($value) ? $value : [];
        $merged   = wp_parse_args($value, $defaults);

        $merged['mode'] = in_array(($merged['mode'] ?? ''), ['auto', 'force'], true) ? $merged['mode'] : 'auto';
        $merged['force_country'] = strtoupper(substr(sanitize_text_field((string) ($merged['force_country'] ?? '')), 0, 2));

        if (isset($value['supported_countries']) && is_string($value['supported_countries'])) {
            // UI sends JSON string for the map
            $decoded = json_decode(stripslashes($value['supported_countries']), true);
            if (is_array($decoded)) {
                $merged['supported_countries'] = $decoded;
            }
        }

        return $merged;
    }

    public function handleMediaApiTestAction(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('helmetsan_media_api_test');

        $provider = isset($_REQUEST['provider']) ? sanitize_key((string) $_REQUEST['provider']) : '';
        $cfg = $this->config->mediaConfig();
        $status = 'error';
        $details = '';

        if ($provider === 'brandfetch') {
            $token = (string) ($cfg['brandfetch_token'] ?? '');
            if ($token === '') {
                $status = 'missing_token';
                $details = 'Brandfetch token is empty.';
            } else {
                $resp = wp_remote_get(
                    'https://api.brandfetch.io/v2/brands/apple.com',
                    [
                        'timeout' => 8,
                        'headers' => [
                            'Accept' => 'application/json',
                            'Authorization' => 'Bearer ' . $token,
                        ],
                    ]
                );
                if (is_wp_error($resp)) {
                    $status = 'error';
                    $details = $resp->get_error_message();
                } else {
                    $code = (int) wp_remote_retrieve_response_code($resp);
                    $status = ($code >= 200 && $code < 300) ? 'ok' : 'error';
                    $details = 'HTTP ' . $code;
                }
            }
        } elseif ($provider === 'logodev') {
            $token = (string) ($cfg['logodev_secret_key'] ?? '');
            if ($token === '') {
                $token = (string) ($cfg['logodev_publishable_key'] ?? '');
            }
            if ($token === '') {
                $token = (string) ($cfg['logodev_token'] ?? '');
            }
            if ($token === '') {
                $status = 'missing_token';
                $details = 'Logo.dev token is empty.';
            } else {
                $url = add_query_arg(['token' => $token], 'https://img.logo.dev/apple.com');
                $resp = wp_remote_get($url, ['timeout' => 8]);
                if (is_wp_error($resp)) {
                    $status = 'error';
                    $details = $resp->get_error_message();
                } else {
                    $code = (int) wp_remote_retrieve_response_code($resp);
                    $status = ($code >= 200 && $code < 300) ? 'ok' : 'error';
                    $details = 'HTTP ' . $code;
                }
            }
        } else {
            $status = 'error';
            $details = 'Unsupported provider.';
        }

        $url = add_query_arg(
            [
                'page' => 'helmetsan-settings',
                'media_test_provider' => $provider,
                'media_test_status' => $status,
                'media_test_details' => rawurlencode($details),
            ],
            admin_url('admin.php')
        );

        wp_safe_redirect($url);
        exit;
    }

    public function handleSchedulerTaskAction(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('helmetsan_scheduler_task');
        $task = isset($_REQUEST['task']) ? sanitize_key((string) $_REQUEST['task']) : '';

        if ($task !== '') {
            $this->scheduler->runTask($task);
        }

        $url = add_query_arg(['page' => 'helmetsan-settings', 'stab' => 'scheduler', 'task_run' => $task], admin_url('admin.php'));
        wp_safe_redirect($url);
        exit;
    }

    public function dashboardPage(): void
    {
        $report = $this->health->report();
        $goLive = $this->checklist->report();
        $syncRows = $this->syncLogs->tableExists() ? $this->syncLogs->fetch(1, 5, 'all', 'all', '') : [];
        $repoStatus = (string) ($report['status'] ?? 'unknown');
        $engines = $this->engineSnapshot();

        echo '<div class="wrap helmetsan-wrap">';
        $this->renderAppHeader('Helmetsan', 'Mission control for repository, sync, analytics, and go-live readiness.');

        echo '<section class="hs-hero">';
        echo '<div class="hs-hero__meta">';
        echo '<p class="hs-eyebrow">Control Center</p>';
        echo '<h2>Repository and commerce operations at a glance</h2>';
        echo '<p>Manage CPT integrity, ingestion quality, sync execution, and launch readiness from one interface.</p>';
        echo '</div>';
        echo '<div class="hs-hero__status">';
        echo '<p><strong>Repo Health:</strong> ' . wp_kses_post($this->renderStatusPill(strtoupper($repoStatus), $repoStatus === 'healthy')) . '</p>';
        echo '<p><strong>Go Live:</strong> ' . wp_kses_post($this->renderStatusPill(! empty($goLive['pass']) ? 'PASS' : 'FAIL', ! empty($goLive['pass']))) . '</p>';
        echo '<p><strong>Score:</strong> ' . esc_html((string) ((int) ($goLive['score'] ?? 0))) . '/100</p>';
        echo wp_kses_post($this->renderScoreBar((int) ($goLive['score'] ?? 0)));
        echo '</div>';
        echo '</section>';

        $cards = [
            ['label' => 'Status', 'value' => $repoStatus, 'page' => 'helmetsan-repo-health'],
            ['label' => 'Helmets', 'value' => (string) ($report['database']['cpt_helmet_rows'] ?? 0), 'page' => 'helmetsan-catalog'],
            ['label' => 'Brands', 'value' => (string) ($report['database']['cpt_brand_rows'] ?? 0), 'page' => 'helmetsan-brands'],
            ['label' => 'Pricing Models', 'value' => (string) ($engines['pricing'] ?? 0), 'page' => 'helmetsan-catalog'],
            ['label' => 'Offer Models', 'value' => (string) ($engines['offers'] ?? 0), 'page' => 'helmetsan-catalog'],
            ['label' => 'Sync Logs', 'value' => (string) ($report['sync_logs']['rows'] ?? 0), 'page' => 'helmetsan-sync-logs'],
            ['label' => 'Analytics Events', 'value' => (string) ($report['analytics_events']['rows'] ?? 0), 'page' => 'helmetsan-analytics'],
            ['label' => 'Go-Live Score', 'value' => (string) ((int) ($goLive['score'] ?? 0)) . '/100', 'page' => 'helmetsan-go-live'],
        ];
        $this->renderMetricCards($cards);

        echo '<div class="hs-grid hs-grid--2">';
        echo '<div class="hs-panel">';
        echo '<h3>Quick Actions</h3>';
        echo '<div class="hs-action-row">';
        echo '<a class="button button-primary" href="' . esc_url(add_query_arg(['page' => 'helmetsan-sync-logs'], admin_url('admin.php'))) . '">Run/Review Sync</a>';
        echo '<a class="button" href="' . esc_url(add_query_arg(['page' => 'helmetsan-catalog'], admin_url('admin.php'))) . '">Open Catalog</a>';
        echo '<a class="button" href="' . esc_url(add_query_arg(['page' => 'helmetsan-go-live'], admin_url('admin.php'))) . '">Open Go Live</a>';
        echo '</div>';
        echo '</div>';

        echo '<div class="hs-panel">';
        echo '<h3>Recent Sync Activity</h3>';
        if ($syncRows === []) {
            echo '<p>No sync activity logged yet.</p>';
        } else {
            echo '<table class="widefat striped hs-table-compact"><thead><tr><th>Time</th><th>Action</th><th>Status</th><th>Message</th></tr></thead><tbody>';
            foreach ($syncRows as $row) {
                $rowStatus = (string) ($row['status'] ?? 'info');
                $ok = in_array($rowStatus, ['success', 'info'], true);
                echo '<tr>';
                echo '<td>' . esc_html((string) ($row['created_at'] ?? '')) . '</td>';
                echo '<td><code>' . esc_html((string) ($row['action'] ?? '')) . '</code></td>';
                echo '<td>' . wp_kses_post($this->renderStatusPill(strtoupper($rowStatus), $ok)) . '</td>';
                echo '<td>' . esc_html((string) ($row['message'] ?? '')) . '</td>';
                echo '</tr>';
            }
            echo '</tbody></table>';
        }
        echo '</div>';
        echo '</div>';

        echo '<div class="hs-panel">';
        echo '<h3>Commerce & Network Engines</h3>';
        echo '<div class="hs-card-grid hs-card-grid--engine">';
        echo '<a class="hs-card" href="' . esc_url(admin_url('edit.php?post_type=dealer')) . '"><span class="hs-card-label">Dealers</span><strong class="hs-card-value">' . esc_html((string) ($engines['dealers'] ?? 0)) . '</strong></a>';
        echo '<a class="hs-card" href="' . esc_url(admin_url('edit.php?post_type=distributor')) . '"><span class="hs-card-label">Distributors</span><strong class="hs-card-value">' . esc_html((string) ($engines['distributors'] ?? 0)) . '</strong></a>';
        echo '<a class="hs-card" href="' . esc_url(admin_url('edit.php?post_type=comparison')) . '"><span class="hs-card-label">Comparisons</span><strong class="hs-card-value">' . esc_html((string) ($engines['comparisons'] ?? 0)) . '</strong></a>';
        echo '<a class="hs-card" href="' . esc_url(admin_url('edit.php?post_type=recommendation')) . '"><span class="hs-card-label">Recommendations</span><strong class="hs-card-value">' . esc_html((string) ($engines['recommendations'] ?? 0)) . '</strong></a>';
        echo '<a class="hs-card" href="' . esc_url(add_query_arg(['page' => 'helmetsan-catalog'], admin_url('admin.php'))) . '"><span class="hs-card-label">Marketplaces</span><strong class="hs-card-value">' . esc_html((string) ($engines['marketplaces'] ?? 0)) . '</strong></a>';
        echo '<a class="hs-card" href="' . esc_url(add_query_arg(['page' => 'helmetsan-catalog'], admin_url('admin.php'))) . '"><span class="hs-card-label">Best Offers</span><strong class="hs-card-value">' . esc_html((string) ($engines['offers'] ?? 0)) . '</strong></a>';
        echo '</div>';
        echo '</div>';

        echo '<div class="hs-grid hs-grid--2">';
        $this->renderMiniTable('Pricing (Sample)', [
            'helmet' => 'Helmet',
            'country' => 'Country',
            'currency' => 'Currency',
            'price' => 'Price',
            'marketplace' => 'Marketplace',
        ], $this->getPricingRows(6));

        $this->renderMiniTable('Offers (Best Sample)', [
            'helmet' => 'Helmet',
            'country' => 'Country',
            'shop' => 'Shop',
            'currency' => 'Currency',
            'price' => 'Offer',
        ], $this->getOfferRows(6));
        echo '</div>';

        echo '<div class="hs-grid hs-grid--2">';
        $this->renderMiniTable('Marketplaces', [
            'id' => 'ID',
            'name' => 'Name',
            'countries' => 'Countries',
            'website' => 'Website',
        ], $this->getMarketplaceRows(6));

        $this->renderMiniTable('Dealers (Recent)', [
            'id' => 'ID',
            'title' => 'Name',
            'status' => 'Status',
            'modified' => 'Updated',
        ], $this->getPostTypeRows('dealer', 6));
        echo '</div>';

        echo '<div class="hs-grid hs-grid--2">';
        $this->renderMiniTable('Distributors (Recent)', [
            'id' => 'ID',
            'title' => 'Name',
            'status' => 'Status',
            'modified' => 'Updated',
        ], $this->getPostTypeRows('distributor', 6));

        $this->renderMiniTable('Recommendations (Recent)', [
            'id' => 'ID',
            'title' => 'Title',
            'status' => 'Status',
            'modified' => 'Updated',
        ], $this->getPostTypeRows('recommendation', 6));
        echo '</div>';

        echo '<div class="hs-panel">';
        echo '<h3>System Snapshot</h3>';
        echo '<pre>' . esc_html(wp_json_encode($report, JSON_PRETTY_PRINT)) . '</pre>';
        echo '</div>';
        echo '</div>';
    }

    public function catalogPage(): void
    {
        $search = isset($_GET['hs_search']) ? sanitize_text_field((string) $_GET['hs_search']) : '';
        $relationFilter = isset($_GET['relation']) ? sanitize_key((string) $_GET['relation']) : 'all';
        $allowedRelations = ['all', 'linked', 'missing_brand', 'missing_cert'];
        if (! in_array($relationFilter, $allowedRelations, true)) {
            $relationFilter = 'all';
        }

        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $perPage = 20;
        $args = [
            'post_type'      => 'helmet',
            'post_status'    => 'publish',
            'posts_per_page' => $perPage,
            'paged'          => $paged,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ];
        if ($search !== '') {
            $args['s'] = $search;
        }

        if ($relationFilter === 'linked') {
            $args['meta_query'] = [
                [
                    'key'     => 'rel_brand',
                    'compare' => 'EXISTS',
                ],
            ];
            $args['tax_query'] = [
                [
                    'taxonomy' => 'certification',
                    'operator' => 'EXISTS',
                ],
            ];
        } elseif ($relationFilter === 'missing_brand') {
            $args['meta_query'] = [
                'relation' => 'OR',
                [
                    'key'     => 'rel_brand',
                    'compare' => 'NOT EXISTS',
                ],
                [
                    'key'     => 'rel_brand',
                    'value'   => ['', '0'],
                    'compare' => 'IN',
                ],
            ];
        } elseif ($relationFilter === 'missing_cert') {
            $args['tax_query'] = [
                [
                    'taxonomy' => 'certification',
                    'operator' => 'NOT EXISTS',
                ],
            ];
        }

        $helmets = new \WP_Query($args);

        $counts = wp_count_posts('helmet');
        $totalHelmets = isset($counts->publish) ? (int) $counts->publish : 0;
        $brandLinked = (int) count(get_posts([
            'post_type'      => 'helmet',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => 'rel_brand',
                    'compare' => 'EXISTS',
                ],
            ],
        ]));
        $certLinked = (int) count(get_posts([
            'post_type'      => 'helmet',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
            'tax_query'      => [
                [
                    'taxonomy' => 'certification',
                    'operator' => 'EXISTS',
                ],
            ],
        ]));
        $engines = $this->engineSnapshot();

        echo '<div class="wrap helmetsan-wrap">';
        $this->renderAppHeader('Catalog', 'Browse helmets and entity directories.');
        $this->renderMetricCards([
            ['label' => 'Total Helmets', 'value' => (string) $totalHelmets, 'page' => 'helmetsan-catalog'],
            ['label' => 'Brand Linked', 'value' => (string) $brandLinked, 'page' => 'helmetsan-catalog'],
            ['label' => 'Certification Linked', 'value' => (string) $certLinked, 'page' => 'helmetsan-catalog'],
            ['label' => 'Brands Directory', 'value' => (string) (isset(wp_count_posts('brand')->publish) ? (int) wp_count_posts('brand')->publish : 0), 'page' => 'helmetsan-brands'],
            ['label' => 'Pricing Models', 'value' => (string) ($engines['pricing'] ?? 0), 'page' => 'helmetsan-catalog'],
            ['label' => 'Offers', 'value' => (string) ($engines['offers'] ?? 0), 'page' => 'helmetsan-catalog'],
        ]);

        echo '<div class="hs-panel">';
        echo '<h3>Commerce + Store/Distributor Index</h3>';
        echo '<div class="hs-card-grid hs-card-grid--engine">';
        echo '<a class="hs-card" href="' . esc_url(admin_url('edit.php?post_type=dealer')) . '"><span class="hs-card-label">Dealers</span><strong class="hs-card-value">' . esc_html((string) ($engines['dealers'] ?? 0)) . '</strong></a>';
        echo '<a class="hs-card" href="' . esc_url(admin_url('edit.php?post_type=distributor')) . '"><span class="hs-card-label">Distributors</span><strong class="hs-card-value">' . esc_html((string) ($engines['distributors'] ?? 0)) . '</strong></a>';
        echo '<a class="hs-card" href="' . esc_url(admin_url('edit.php?post_type=comparison')) . '"><span class="hs-card-label">Comparisons</span><strong class="hs-card-value">' . esc_html((string) ($engines['comparisons'] ?? 0)) . '</strong></a>';
        echo '<a class="hs-card" href="' . esc_url(admin_url('edit.php?post_type=recommendation')) . '"><span class="hs-card-label">Recommendations</span><strong class="hs-card-value">' . esc_html((string) ($engines['recommendations'] ?? 0)) . '</strong></a>';
        echo '<a class="hs-card" href="' . esc_url(add_query_arg(['page' => 'helmetsan-catalog'], admin_url('admin.php'))) . '"><span class="hs-card-label">Marketplaces</span><strong class="hs-card-value">' . esc_html((string) ($engines['marketplaces'] ?? 0)) . '</strong></a>';
        echo '<a class="hs-card" href="' . esc_url(add_query_arg(['page' => 'helmetsan-catalog'], admin_url('admin.php'))) . '"><span class="hs-card-label">Best Offers</span><strong class="hs-card-value">' . esc_html((string) ($engines['offers'] ?? 0)) . '</strong></a>';
        echo '</div>';
        echo '</div>';

        echo '<div class="hs-grid hs-grid--2">';
        $this->renderMiniTable('Pricing (Catalog Sample)', [
            'helmet' => 'Helmet',
            'country' => 'Country',
            'currency' => 'Currency',
            'price' => 'Price',
            'marketplace' => 'Marketplace',
        ], $this->getPricingRows(5));

        $this->renderMiniTable('Offers (Catalog Sample)', [
            'helmet' => 'Helmet',
            'country' => 'Country',
            'shop' => 'Shop',
            'currency' => 'Currency',
            'price' => 'Offer',
        ], $this->getOfferRows(5));
        echo '</div>';

        echo '<div class="hs-panel">';
        echo '<form method="get" class="hs-inline-form">';
        echo '<input type="hidden" name="page" value="helmetsan-catalog" />';
        echo '<label for="hs-search">Search</label>';
        echo '<input id="hs-search" type="search" name="hs_search" value="' . esc_attr($search) . '" placeholder="Model, slug, or title" />';
        echo '<label for="hs-relation">Relationship</label>';
        echo '<select id="hs-relation" name="relation">';
        echo '<option value="all" ' . selected($relationFilter, 'all', false) . '>All</option>';
        echo '<option value="linked" ' . selected($relationFilter, 'linked', false) . '>Linked (brand + certification)</option>';
        echo '<option value="missing_brand" ' . selected($relationFilter, 'missing_brand', false) . '>Missing brand</option>';
        echo '<option value="missing_cert" ' . selected($relationFilter, 'missing_cert', false) . '>Missing certification</option>';
        echo '</select>';
        submit_button('Apply', 'secondary', '', false);
        echo '</form>';
        echo '</div>';

        echo '<div class="hs-panel">';
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Helmet</th><th>Brand</th><th>Certifications</th><th>Specs</th><th>Completeness</th><th>Actions</th></tr></thead><tbody>';
        if (! $helmets->have_posts()) {
            echo '<tr><td colspan="7">No helmets found for this filter.</td></tr>';
        } else {
            while ($helmets->have_posts()) {
                $helmets->the_post();
                $postId = get_the_ID();
                $title = get_the_title();
                $brandId = (int) get_post_meta($postId, 'rel_brand', true);
                $brandTitle = $brandId > 0 ? get_the_title($brandId) : '';
                $certTerms = get_the_terms($postId, 'certification');
                $certNames = [];
                if (is_array($certTerms)) {
                    foreach ($certTerms as $term) {
                        if ($term instanceof \WP_Term) {
                            $certNames[] = $term->name;
                        }
                    }
                }

                $weight = (string) get_post_meta($postId, 'spec_weight_g', true);
                $shell = (string) get_post_meta($postId, 'spec_shell_material', true);
                $price = (string) get_post_meta($postId, 'price_retail_usd', true);
                $specSummary = 'W:' . ($weight !== '' ? $weight . 'g' : 'n/a') . ' | S:' . ($shell !== '' ? $shell : 'n/a') . ' | $' . ($price !== '' ? $price : 'n/a');

                $parts = [
                    $brandId > 0,
                    $certNames !== [],
                    $weight !== '',
                    $shell !== '',
                    $price !== '',
                ];
                $present = 0;
                foreach ($parts as $presentFlag) {
                    if ($presentFlag) {
                        $present++;
                    }
                }
                $score = (int) round(($present / 5) * 100);
                $scoreClass = $score >= 80 ? 'hs-pill hs-pill--ok' : 'hs-pill hs-pill--fail';

                $editUrl = get_edit_post_link($postId);
                $viewUrl = get_permalink($postId);

                echo '<tr>';
                echo '<td>' . esc_html((string) $postId) . '</td>';
                echo '<td><strong>' . esc_html($title) . '</strong><br /><code>' . esc_html((string) get_post_field('post_name', $postId)) . '</code></td>';
                echo '<td>' . ($brandTitle !== '' ? esc_html($brandTitle) : wp_kses_post($this->renderStatusPill('MISSING', false))) . '</td>';
                echo '<td>' . ($certNames !== [] ? esc_html(implode(', ', $certNames)) : wp_kses_post($this->renderStatusPill('MISSING', false))) . '</td>';
                echo '<td><code>' . esc_html($specSummary) . '</code></td>';
                echo '<td><span class="' . esc_attr($scoreClass) . '">' . esc_html((string) $score) . '%</span></td>';
                echo '<td>';
                if (is_string($viewUrl)) {
                    echo '<a class="button button-small" href="' . esc_url($viewUrl) . '" target="_blank" rel="noopener noreferrer">View</a> ';
                }
                if (is_string($editUrl)) {
                    echo '<a class="button button-small" href="' . esc_url($editUrl) . '">Edit</a>';
                }
                echo '</td>';
                echo '</tr>';
            }
            wp_reset_postdata();
        }
        echo '</tbody></table>';

        $totalPages = (int) $helmets->max_num_pages;
        if ($totalPages > 1) {
            $baseUrl = add_query_arg([
                'page' => 'helmetsan-catalog',
                'hs_search' => $search,
                'relation' => $relationFilter,
                'paged' => '%#%',
            ], admin_url('admin.php'));
            echo '<div class="tablenav"><div class="tablenav-pages" style="margin:12px 0;">';
            echo wp_kses_post(paginate_links([
                'base'      => $baseUrl,
                'format'    => '',
                'current'   => $paged,
                'total'     => $totalPages,
                'type'      => 'plain',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
            ]));
            echo '</div></div>';
        }
        echo '</div>';
        echo '</div>';
    }

    public function brandsPage(): void
    {
        $done = isset($_GET['hs_brand_cascade']) ? (int) $_GET['hs_brand_cascade'] : 0;
        $count = isset($_GET['hs_brand_count']) ? (int) $_GET['hs_brand_count'] : 0;
        $search = isset($_GET['s']) ? sanitize_text_field((string) $_GET['s']) : '';
        $country = isset($_GET['country']) ? sanitize_text_field((string) $_GET['country']) : '';
        $helmetType = isset($_GET['helmet_type']) ? sanitize_text_field((string) $_GET['helmet_type']) : '';
        $cert = isset($_GET['cert']) ? sanitize_text_field((string) $_GET['cert']) : '';
        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $perPage = 25;

        echo '<div class="wrap helmetsan-wrap">';
        $this->renderAppHeader('Brands', 'Deep brand metadata, cascade, and JSON export.');
        if ($done === 1) {
            echo '<div class="notice notice-success is-dismissible"><p>Cascade complete. Updated helmets: ' . esc_html((string) $count) . '.</p></div>';
        }

        echo '<form method="post" style="margin-bottom:12px;">';
        wp_nonce_field('helmetsan_brand_action', 'helmetsan_brand_nonce');
        echo '<input type="hidden" name="helmetsan_brand_action" value="cascade_all" />';
        submit_button('Cascade All Brands', 'secondary', 'submit', false);
        echo '</form>';

        $rows = $this->brands->listBrandOverview();
        $filtered = [];
        foreach ($rows as $row) {
            if (! is_array($row)) {
                continue;
            }
            $haystack = strtolower(implode(' ', array_map('strval', [
                $row['title'] ?? '',
                $row['origin_country'] ?? '',
                $row['helmet_types'] ?? '',
                $row['certification_coverage'] ?? '',
                $row['warranty'] ?? '',
            ])));
            if ($search !== '' && ! str_contains($haystack, strtolower($search))) {
                continue;
            }
            if ($country !== '' && stripos((string) ($row['origin_country'] ?? ''), $country) === false) {
                continue;
            }
            if ($helmetType !== '' && stripos((string) ($row['helmet_types'] ?? ''), $helmetType) === false) {
                continue;
            }
            if ($cert !== '' && stripos((string) ($row['certification_coverage'] ?? ''), $cert) === false) {
                continue;
            }
            $filtered[] = $row;
        }

        $totalRows = count($filtered);
        $totalPages = (int) max(1, ceil($totalRows / $perPage));
        $paged = min($paged, $totalPages);
        $offset = ($paged - 1) * $perPage;
        $rows = array_slice($filtered, $offset, $perPage);

        echo '<div class="hs-panel">';
        echo '<form method="get" class="hs-inline-form">';
        echo '<input type="hidden" name="page" value="helmetsan-brands" />';
        echo '<label for="hs-brand-search">Search</label>';
        echo '<input id="hs-brand-search" type="search" name="s" value="' . esc_attr($search) . '" placeholder="Brand, country, cert, type" />';
        echo '<label for="hs-brand-country">Country</label>';
        echo '<input id="hs-brand-country" type="text" name="country" value="' . esc_attr($country) . '" class="small-text" placeholder="JP, US, IN" />';
        echo '<label for="hs-brand-helmet-type">Helmet Type</label>';
        echo '<input id="hs-brand-helmet-type" type="text" name="helmet_type" value="' . esc_attr($helmetType) . '" class="regular-text" placeholder="Full Face, Modular" />';
        echo '<label for="hs-brand-cert">Certification</label>';
        echo '<input id="hs-brand-cert" type="text" name="cert" value="' . esc_attr($cert) . '" class="regular-text" placeholder="ECE, DOT, Snell" />';
        submit_button('Apply Filters', 'secondary', '', false);
        echo '</form>';
        echo '</div>';

        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Brand</th><th>Linked Helmets</th><th>Total Models</th><th>Helmet Types</th><th>Cert Coverage</th><th>Origin</th><th>Warranty</th><th>Support URL</th><th>Action</th></tr></thead><tbody>';
        if ($rows === []) {
            echo '<tr><td colspan="10">No brands found.</td></tr>';
        } else {
            foreach ($rows as $row) {
                $id = isset($row['id']) ? (int) $row['id'] : 0;
                echo '<tr>';
                echo '<td>' . esc_html((string) $id) . '</td>';
                echo '<td>' . esc_html((string) ($row['title'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($row['helmets'] ?? 0)) . '</td>';
                echo '<td>' . esc_html((string) ($row['total_models'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($row['helmet_types'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($row['certification_coverage'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($row['origin_country'] ?? '')) . '</td>';
                echo '<td>' . esc_html((string) ($row['warranty'] ?? '')) . '</td>';
                echo '<td><code>' . esc_html((string) ($row['support_url'] ?? '')) . '</code></td>';
                echo '<td>';
                echo '<form method="post">';
                wp_nonce_field('helmetsan_brand_action', 'helmetsan_brand_nonce');
                echo '<input type="hidden" name="helmetsan_brand_action" value="cascade_one" />';
                echo '<input type="hidden" name="brand_id" value="' . esc_attr((string) $id) . '" />';
                submit_button('Cascade', 'small', 'submit', false);
                echo '</form>';
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin-top:6px;">';
                wp_nonce_field('helmetsan_export_action', 'helmetsan_export_nonce');
                echo '<input type="hidden" name="action" value="helmetsan_export" />';
                echo '<input type="hidden" name="entity" value="brand" />';
                echo '<input type="hidden" name="post_id" value="' . esc_attr((string) $id) . '" />';
                submit_button('Export JSON', 'small', 'submit', false);
                echo '</form>';
                echo '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';

        if ($totalPages > 1) {
            $baseUrl = add_query_arg([
                'page' => 'helmetsan-brands',
                's' => $search,
                'country' => $country,
                'helmet_type' => $helmetType,
                'cert' => $cert,
                'paged' => '%#%',
            ], admin_url('admin.php'));
            echo '<div class="tablenav"><div class="tablenav-pages" style="margin:12px 0;">';
            echo wp_kses_post(paginate_links([
                'base'      => $baseUrl,
                'format'    => '',
                'current'   => $paged,
                'total'     => $totalPages,
                'type'      => 'plain',
                'prev_text' => '&laquo;',
                'next_text' => '&raquo;',
            ]));
            echo '</div></div>';
        }
        echo '<p><strong>Total matched:</strong> ' . esc_html((string) $totalRows) . '</p>';
        echo '</div>';
    }

    public function wooBridgePage(): void
    {
        $cfg = wp_parse_args((array) get_option(Config::OPTION_WOO_BRIDGE, []), $this->config->wooBridgeDefaults());
        $available = $this->wooBridge->available();
        $resultOk = isset($_GET['hs_woo_ok']) ? (int) $_GET['hs_woo_ok'] : -1;
        $resultMsg = isset($_GET['hs_woo_msg']) ? sanitize_text_field((string) $_GET['hs_woo_msg']) : '';
        $helmetId = isset($_GET['hs_woo_helmet']) ? (int) $_GET['hs_woo_helmet'] : 0;
        $report = [];
        if (isset($_GET['hs_woo_report'])) {
            $report = json_decode(rawurldecode((string) $_GET['hs_woo_report']), true);
            if (! is_array($report)) {
                $report = [];
            }
        }

        echo '<div class="wrap helmetsan-wrap">';
        $this->renderAppHeader('Woo Bridge', 'Sync Helmet CPT data to WooCommerce variable products and variations.');

        if ($resultOk >= 0 && $resultMsg !== '') {
            $cls = $resultOk === 1 ? 'notice-success' : 'notice-error';
            echo '<div class="notice ' . esc_attr($cls) . ' is-dismissible"><p>' . esc_html($resultMsg) . '</p></div>';
        }

        echo '<section class="hs-hero">';
        echo '<div class="hs-hero__meta">';
        echo '<p class="hs-eyebrow">WooCommerce Bridge</p>';
        echo '<h2>Helmet model to variable product sync</h2>';
        echo '<p>Uses <code>variants_json</code> for sellable options and writes product/variation links back to helmet meta.</p>';
        echo '</div>';
        echo '<div class="hs-hero__status">';
        echo '<p><strong>WooCommerce:</strong> ' . wp_kses_post($this->renderStatusPill($available ? 'AVAILABLE' : 'MISSING', $available)) . '</p>';
        echo '<p><strong>Auto Sync on Save:</strong> ' . wp_kses_post($this->renderStatusPill(! empty($cfg['auto_sync_on_save']) ? 'ON' : 'OFF', ! empty($cfg['auto_sync_on_save']))) . '</p>';
        echo '</div>';
        echo '</section>';

        echo '<div class="hs-grid hs-grid--2">';
        echo '<div class="hs-panel">';
        echo '<h3>Run Sync</h3>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="hs-inline-form">';
        wp_nonce_field('helmetsan_woo_bridge_sync_action', 'helmetsan_woo_bridge_sync_nonce');
        echo '<input type="hidden" name="action" value="helmetsan_woo_bridge_sync" />';
        echo '<label for="hs-woo-helmet-id">Helmet ID (optional)</label>';
        echo '<input id="hs-woo-helmet-id" type="number" min="1" name="helmet_id" value="' . esc_attr($helmetId > 0 ? (string) $helmetId : '') . '" class="small-text" />';
        echo '<label for="hs-woo-limit">Batch Limit</label>';
        echo '<input id="hs-woo-limit" type="number" min="1" name="limit" value="' . esc_attr((string) ((int) ($cfg['sync_limit_default'] ?? 100))) . '" class="small-text" />';
        echo '<label><input type="checkbox" name="dry_run" value="1" /> Dry Run</label>';
        submit_button('Run Woo Sync', 'secondary', '', false);
        echo '</form>';
        echo '</div>';

        echo '<div class="hs-panel">';
        echo '<h3>Bridge Settings</h3>';
        echo '<form method="post" action="options.php">';
        settings_fields('helmetsan_settings');
        echo '<table class="form-table"><tbody>';
        echo '<tr><th>Enable Bridge</th><td><label><input type="checkbox" name="' . esc_attr(Config::OPTION_WOO_BRIDGE) . '[enable_bridge]" value="1" ' . checked(! empty($cfg['enable_bridge']), true, false) . ' /> Enable Woo bridge hooks</label></td></tr>';
        echo '<tr><th>Auto Sync on Helmet Save</th><td><label><input type="checkbox" name="' . esc_attr(Config::OPTION_WOO_BRIDGE) . '[auto_sync_on_save]" value="1" ' . checked(! empty($cfg['auto_sync_on_save']), true, false) . ' /> Sync when helmet is saved</label></td></tr>';
        echo '<tr><th>Publish Woo Products</th><td><label><input type="checkbox" name="' . esc_attr(Config::OPTION_WOO_BRIDGE) . '[publish_products]" value="1" ' . checked(! empty($cfg['publish_products']), true, false) . ' /> Publish instead of draft</label></td></tr>';
        echo '<tr><th>Default Currency</th><td><input type="text" name="' . esc_attr(Config::OPTION_WOO_BRIDGE) . '[default_currency]" value="' . esc_attr((string) ($cfg['default_currency'] ?? 'USD')) . '" class="small-text" /></td></tr>';
        echo '<tr><th>Default Batch Limit</th><td><input type="number" min="1" name="' . esc_attr(Config::OPTION_WOO_BRIDGE) . '[sync_limit_default]" value="' . esc_attr((string) ((int) ($cfg['sync_limit_default'] ?? 100))) . '" class="small-text" /></td></tr>';
        echo '</tbody></table>';
        submit_button('Save Woo Bridge Settings');
        echo '</form>';
        echo '</div>';
        echo '</div>';

        echo '<div class="hs-panel">';
        echo '<h3>Linked Helmets</h3>';
        $linked = get_posts([
            'post_type' => 'helmet',
            'post_status' => ['publish', 'draft', 'private'],
            'posts_per_page' => 20,
            'meta_query' => [
                [
                    'key' => 'wc_product_id',
                    'compare' => 'EXISTS',
                ],
            ],
        ]);
        echo '<table class="widefat striped hs-table-compact"><thead><tr><th>Helmet ID</th><th>Helmet</th><th>Woo Product ID</th><th>Actions</th></tr></thead><tbody>';
        if (! is_array($linked) || $linked === []) {
            echo '<tr><td colspan="4">No linked helmets yet.</td></tr>';
        } else {
            foreach ($linked as $post) {
                if (! ($post instanceof \WP_Post)) {
                    continue;
                }
                $pid = (int) get_post_meta($post->ID, 'wc_product_id', true);
                $editHelmet = get_edit_post_link($post->ID);
                $editProduct = $pid > 0 ? get_edit_post_link($pid) : '';
                echo '<tr>';
                echo '<td>' . esc_html((string) $post->ID) . '</td>';
                echo '<td>' . esc_html((string) $post->post_title) . '</td>';
                echo '<td>' . esc_html((string) $pid) . '</td>';
                echo '<td>';
                if (is_string($editHelmet)) {
                    echo '<a class="button button-small" href="' . esc_url($editHelmet) . '">Edit Helmet</a> ';
                }
                if (is_string($editProduct) && $editProduct !== '') {
                    echo '<a class="button button-small" href="' . esc_url($editProduct) . '">Edit Product</a>';
                }
                echo '</td>';
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        echo '</div>';

        if ($report !== []) {
            echo '<div class="hs-panel">';
            echo '<h3>Last Sync Report</h3>';
            echo '<pre>' . esc_html(wp_json_encode($report, JSON_PRETTY_PRINT)) . '</pre>';
            echo '</div>';
        }

        echo '</div>';
    }

    public function commerceEnginesPage(): void
    {
        $engine = isset($_GET['engine']) ? sanitize_key((string) $_GET['engine']) : 'pricing';
        $allowedEngines = ['pricing', 'offers', 'marketplaces', 'dealers', 'distributors', 'recommendations'];
        if (! in_array($engine, $allowedEngines, true)) {
            $engine = 'pricing';
        }

        $search = isset($_GET['s']) ? sanitize_text_field((string) $_GET['s']) : '';
        $country = strtoupper(sanitize_text_field((string) ($_GET['country'] ?? '')));
        $marketplace = sanitize_title((string) ($_GET['marketplace'] ?? ''));
        $paged = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $perPage = 25;
        $engines = $this->engineSnapshot();

        echo '<div class="wrap helmetsan-wrap">';
        $this->renderAppHeader('Commerce Engines', 'Pricing, offers, marketplaces, store/distributor network, and recommendation intelligence.');

        $this->renderMetricCards([
            ['label' => 'Pricing Models', 'value' => (string) ($engines['pricing'] ?? 0), 'page' => 'helmetsan-commerce-engines'],
            ['label' => 'Offer Models', 'value' => (string) ($engines['offers'] ?? 0), 'page' => 'helmetsan-commerce-engines'],
            ['label' => 'Marketplaces', 'value' => (string) ($engines['marketplaces'] ?? 0), 'page' => 'helmetsan-commerce-engines'],
            ['label' => 'Dealers', 'value' => (string) ($engines['dealers'] ?? 0), 'page' => 'helmetsan-commerce-engines'],
            ['label' => 'Distributors', 'value' => (string) ($engines['distributors'] ?? 0), 'page' => 'helmetsan-commerce-engines'],
            ['label' => 'Recommendations', 'value' => (string) ($engines['recommendations'] ?? 0), 'page' => 'helmetsan-commerce-engines'],
        ]);

        echo '<div class="hs-panel">';
        echo '<div class="hs-shell-tabs">';
        $engineTabs = [
            'pricing' => 'Pricing',
            'offers' => 'Offers',
            'marketplaces' => 'Marketplaces',
            'dealers' => 'Dealers',
            'distributors' => 'Distributors',
            'recommendations' => 'Recommendations',
        ];
        foreach ($engineTabs as $slug => $label) {
            $url = add_query_arg([
                'page' => 'helmetsan-commerce-engines',
                'engine' => $slug,
            ], admin_url('admin.php'));
            $class = $slug === $engine ? 'hs-tab is-active' : 'hs-tab';
            echo '<a class="' . esc_attr($class) . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
        echo '</div>';
        echo '</div>';

        echo '<div class="hs-panel">';
        echo '<form method="get" class="hs-inline-form">';
        echo '<input type="hidden" name="page" value="helmetsan-commerce-engines" />';
        echo '<input type="hidden" name="engine" value="' . esc_attr($engine) . '" />';
        echo '<label for="hs-commerce-search">Search</label>';
        echo '<input id="hs-commerce-search" type="search" name="s" value="' . esc_attr($search) . '" placeholder="Helmet, shop, marketplace, id" />';
        echo '<label for="hs-commerce-country">Country</label>';
        echo '<input id="hs-commerce-country" type="text" name="country" value="' . esc_attr($country) . '" placeholder="US, IN, DE" class="small-text" />';
        echo '<label for="hs-commerce-marketplace">Marketplace</label>';
        echo '<input id="hs-commerce-marketplace" type="text" name="marketplace" value="' . esc_attr($marketplace) . '" placeholder="amazon-us" class="regular-text" />';
        submit_button('Apply', 'secondary', '', false);
        echo '</form>';
        echo '</div>';

        if ($engine === 'pricing') {
            $all = $this->collectPricingRecords($country, $marketplace, $search);
            $this->renderCommercePaginationTable(
                $engine,
                $paged,
                $perPage,
                $all,
                ['helmet' => 'Helmet', 'country' => 'Country', 'currency' => 'Currency', 'price' => 'Current', 'launch_price' => 'Launch', 'mrp' => 'MRP', 'marketplace' => 'Marketplace', 'captured_at' => 'Captured']
            );
        } elseif ($engine === 'offers') {
            $all = $this->collectOfferRecords($country, $marketplace, $search);
            $this->renderCommercePaginationTable(
                $engine,
                $paged,
                $perPage,
                $all,
                ['helmet' => 'Helmet', 'country' => 'Country', 'shop' => 'Shop', 'currency' => 'Currency', 'price' => 'Offer', 'discount' => 'Discount%', 'marketplace' => 'Marketplace', 'valid_until' => 'Valid Until']
            );
        } elseif ($engine === 'marketplaces') {
            $all = $this->collectMarketplaceRecords($country, $search);
            $this->renderCommercePaginationTable(
                $engine,
                $paged,
                $perPage,
                $all,
                ['id' => 'ID', 'name' => 'Name', 'countries' => 'Countries', 'website' => 'Website', 'online' => 'Online', 'offline' => 'Offline']
            );
        } elseif ($engine === 'dealers') {
            $this->renderCommercePostTypeTable('dealer', $engine, $paged, $perPage, $country, $search, ['dealer_country_code', 'dealer_region_code', 'dealer_city']);
        } elseif ($engine === 'distributors') {
            $this->renderCommercePostTypeTable('distributor', $engine, $paged, $perPage, $country, $search, ['distributor_country_code']);
        } else {
            $this->renderCommercePostTypeTable('recommendation', $engine, $paged, $perPage, $country, $search, ['recommendation_region', 'recommendation_use_case']);
        }

        echo '</div>';
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function collectPricingRecords(string $country, string $marketplace, string $search): array
    {
        $posts = get_posts([
            'post_type' => 'helmet',
            'post_status' => ['publish', 'draft', 'private', 'pending'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => 'pricing_records_json',
                    'compare' => 'EXISTS',
                ],
            ],
        ]);

        $rows = [];
        foreach ($posts as $postId) {
            $postId = (int) $postId;
            $title = (string) get_the_title($postId);
            $raw = (string) get_post_meta($postId, 'pricing_records_json', true);
            $records = json_decode($raw, true);
            if (! is_array($records)) {
                continue;
            }
            foreach ($records as $record) {
                if (! is_array($record)) {
                    continue;
                }
                $rowCountry = strtoupper((string) ($record['country_code'] ?? ''));
                $rowMarketplace = sanitize_title((string) ($record['marketplace_id'] ?? ''));
                if ($country !== '' && $rowCountry !== $country) {
                    continue;
                }
                if ($marketplace !== '' && $rowMarketplace !== $marketplace) {
                    continue;
                }

                $row = [
                    'helmet' => $title,
                    'country' => $rowCountry,
                    'currency' => strtoupper((string) ($record['currency'] ?? '')),
                    'price' => (string) ($record['current_price'] ?? ''),
                    'launch_price' => (string) ($record['launch_price'] ?? ''),
                    'mrp' => (string) ($record['mrp'] ?? ''),
                    'marketplace' => $rowMarketplace,
                    'captured_at' => (string) ($record['captured_at'] ?? ''),
                ];

                if ($search !== '') {
                    $haystack = strtolower(implode(' ', $row));
                    if (! str_contains($haystack, strtolower($search))) {
                        continue;
                    }
                }

                $rows[] = $row;
            }
        }

        usort($rows, static function (array $a, array $b): int {
            return strcmp((string) ($b['captured_at'] ?? ''), (string) ($a['captured_at'] ?? ''));
        });

        return $rows;
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function collectOfferRecords(string $country, string $marketplace, string $search): array
    {
        $posts = get_posts([
            'post_type' => 'helmet',
            'post_status' => ['publish', 'draft', 'private', 'pending'],
            'posts_per_page' => -1,
            'fields' => 'ids',
            'meta_query' => [
                [
                    'key' => 'offers_json',
                    'compare' => 'EXISTS',
                ],
            ],
        ]);

        $rows = [];
        foreach ($posts as $postId) {
            $postId = (int) $postId;
            $title = (string) get_the_title($postId);
            $raw = (string) get_post_meta($postId, 'offers_json', true);
            $records = json_decode($raw, true);
            if (! is_array($records)) {
                continue;
            }
            foreach ($records as $record) {
                if (! is_array($record)) {
                    continue;
                }
                $rowCountry = strtoupper((string) ($record['country_code'] ?? ''));
                $rowMarketplace = sanitize_title((string) ($record['marketplace_id'] ?? ''));
                if ($country !== '' && $rowCountry !== $country) {
                    continue;
                }
                if ($marketplace !== '' && $rowMarketplace !== $marketplace) {
                    continue;
                }

                $row = [
                    'helmet' => $title,
                    'country' => $rowCountry,
                    'shop' => (string) ($record['shop_name'] ?? ''),
                    'currency' => strtoupper((string) ($record['currency'] ?? '')),
                    'price' => (string) ($record['offer_price'] ?? ''),
                    'discount' => (string) ($record['discount_percent'] ?? ''),
                    'marketplace' => $rowMarketplace,
                    'valid_until' => (string) ($record['valid_until'] ?? ''),
                ];

                if ($search !== '') {
                    $haystack = strtolower(implode(' ', $row));
                    if (! str_contains($haystack, strtolower($search))) {
                        continue;
                    }
                }

                $rows[] = $row;
            }
        }

        usort($rows, static function (array $a, array $b): int {
            return strcmp((string) ($a['price'] ?? ''), (string) ($b['price'] ?? ''));
        });

        return $rows;
    }

    /**
     * @return array<int,array<string,string>>
     */
    private function collectMarketplaceRecords(string $country, string $search): array
    {
        $index = \Helmetsan\Core\Commerce\CommerceService::readMarketplacesIndex();
        if (! is_array($index)) {
            return [];
        }

        $rows = [];
        foreach ($index as $item) {
            if (! is_array($item)) {
                continue;
            }
            $countries = isset($item['country_codes']) && is_array($item['country_codes']) ? array_map('strtoupper', array_map('strval', $item['country_codes'])) : [];
            if ($country !== '' && ! in_array($country, $countries, true)) {
                continue;
            }
            $row = [
                'id' => (string) ($item['id'] ?? ''),
                'name' => (string) ($item['name'] ?? ''),
                'countries' => implode(', ', $countries),
                'website' => (string) ($item['website'] ?? ''),
                'online' => ! empty($item['supports_online']) ? 'yes' : 'no',
                'offline' => ! empty($item['supports_offline']) ? 'yes' : 'no',
            ];
            if ($search !== '') {
                $haystack = strtolower(implode(' ', $row));
                if (! str_contains($haystack, strtolower($search))) {
                    continue;
                }
            }
            $rows[] = $row;
        }

        usort($rows, static function (array $a, array $b): int {
            return strcmp((string) ($a['name'] ?? ''), (string) ($b['name'] ?? ''));
        });

        return $rows;
    }

    /**
     * @param array<int,array<string,string>> $rows
     * @param array<string,string> $headers
     */
    private function renderCommercePaginationTable(string $engine, int $paged, int $perPage, array $rows, array $headers): void
    {
        $totalRows = count($rows);
        $totalPages = (int) max(1, ceil($totalRows / $perPage));
        $paged = min($paged, $totalPages);
        $offset = ($paged - 1) * $perPage;
        $pageRows = array_slice($rows, $offset, $perPage);

        echo '<div class="hs-panel">';
        echo '<table class="widefat striped"><thead><tr>';
        foreach ($headers as $label) {
            echo '<th>' . esc_html($label) . '</th>';
        }
        echo '</tr></thead><tbody>';
        if ($pageRows === []) {
            echo '<tr><td colspan="' . esc_attr((string) count($headers)) . '">No records found.</td></tr>';
        } else {
            foreach ($pageRows as $row) {
                echo '<tr>';
                foreach (array_keys($headers) as $key) {
                    echo '<td>' . esc_html((string) ($row[$key] ?? '')) . '</td>';
                }
                echo '</tr>';
            }
        }
        echo '</tbody></table>';
        echo '<p><strong>Total:</strong> ' . esc_html((string) $totalRows) . '</p>';
        $this->renderCommercePagination($engine, $paged, $totalPages);
        echo '</div>';
    }

    /**
     * @param array<int,string> $metaColumns
     */
    private function renderCommercePostTypeTable(string $postType, string $engine, int $paged, int $perPage, string $country, string $search, array $metaColumns): void
    {
        $args = [
            'post_type' => $postType,
            'post_status' => ['publish', 'draft', 'private', 'pending'],
            'posts_per_page' => $perPage,
            'paged' => $paged,
            'orderby' => 'modified',
            'order' => 'DESC',
        ];
        if ($search !== '') {
            $args['s'] = $search;
        }
        if ($country !== '') {
            $countryMeta = $postType === 'dealer' ? 'dealer_country_code' : ($postType === 'distributor' ? 'distributor_country_code' : 'recommendation_region');
            $args['meta_query'] = [
                [
                    'key' => $countryMeta,
                    'value' => $country,
                    'compare' => 'LIKE',
                ],
            ];
        }

        $query = new \WP_Query($args);

        echo '<div class="hs-panel">';
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Title</th><th>Status</th>';
        foreach ($metaColumns as $metaColumn) {
            echo '<th>' . esc_html(str_replace('_', ' ', $metaColumn)) . '</th>';
        }
        echo '<th>Actions</th></tr></thead><tbody>';
        if (! $query->have_posts()) {
            echo '<tr><td colspan="' . esc_attr((string) (5 + count($metaColumns))) . '">No records found.</td></tr>';
        } else {
            while ($query->have_posts()) {
                $query->the_post();
                $postId = get_the_ID();
                echo '<tr>';
                echo '<td>' . esc_html((string) $postId) . '</td>';
                echo '<td>' . esc_html((string) get_the_title()) . '</td>';
                echo '<td>' . esc_html((string) get_post_status($postId)) . '</td>';
                foreach ($metaColumns as $metaColumn) {
                    echo '<td>' . esc_html((string) get_post_meta($postId, $metaColumn, true)) . '</td>';
                }
                $editUrl = get_edit_post_link($postId);
                $viewUrl = get_permalink($postId);
                echo '<td>';
                if (is_string($viewUrl)) {
                    echo '<a class="button button-small" href="' . esc_url($viewUrl) . '" target="_blank" rel="noopener noreferrer">View</a> ';
                }
                if (is_string($editUrl)) {
                    echo '<a class="button button-small" href="' . esc_url($editUrl) . '">Edit</a>';
                }
                echo '</td>';
                echo '</tr>';
            }
            wp_reset_postdata();
        }
        echo '</tbody></table>';

        $totalPages = (int) $query->max_num_pages;
        echo '<p><strong>Total:</strong> ' . esc_html((string) ((int) $query->found_posts)) . '</p>';
        $this->renderCommercePagination($engine, $paged, $totalPages);
        echo '</div>';
    }

    private function renderCommercePagination(string $engine, int $paged, int $totalPages): void
    {
        if ($totalPages <= 1) {
            return;
        }

        $baseArgs = [
            'page' => 'helmetsan-commerce-engines',
            'engine' => $engine,
            's' => isset($_GET['s']) ? sanitize_text_field((string) $_GET['s']) : '',
            'country' => isset($_GET['country']) ? strtoupper(sanitize_text_field((string) $_GET['country'])) : '',
            'marketplace' => isset($_GET['marketplace']) ? sanitize_title((string) $_GET['marketplace']) : '',
            'paged' => '%#%',
        ];
        $baseUrl = add_query_arg($baseArgs, admin_url('admin.php'));

        echo '<div class="tablenav"><div class="tablenav-pages" style="margin:12px 0;">';
        echo wp_kses_post(paginate_links([
            'base'      => $baseUrl,
            'format'    => '',
            'current'   => $paged,
            'total'     => $totalPages,
            'type'      => 'plain',
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
        ]));
        echo '</div></div>';
    }

    public function handleBrandActions(): void
    {
        if (! is_admin() || ! current_user_can('manage_options')) {
            return;
        }

        $action = isset($_POST['helmetsan_brand_action']) ? sanitize_text_field((string) $_POST['helmetsan_brand_action']) : '';
        if (! in_array($action, ['cascade_one', 'cascade_all'], true)) {
            return;
        }

        $nonce = isset($_POST['helmetsan_brand_nonce']) ? (string) $_POST['helmetsan_brand_nonce'] : '';
        if (! wp_verify_nonce($nonce, 'helmetsan_brand_action')) {
            return;
        }

        $updated = 0;

        if ($action === 'cascade_one') {
            $brandId = isset($_POST['brand_id']) ? (int) $_POST['brand_id'] : 0;
            if ($brandId > 0) {
                $result = $this->brands->cascadeToHelmets($brandId, 'admin-cascade-one');
                if (! empty($result['ok'])) {
                    $updated += (int) ($result['updated_helmets'] ?? 0);
                }
            }
        } else {
            $rows = $this->brands->listBrandOverview();
            foreach ($rows as $row) {
                $brandId = isset($row['id']) ? (int) $row['id'] : 0;
                if ($brandId <= 0) {
                    continue;
                }
                $result = $this->brands->cascadeToHelmets($brandId, 'admin-cascade-all');
                if (! empty($result['ok'])) {
                    $updated += (int) ($result['updated_helmets'] ?? 0);
                }
            }
        }

        $redirect = add_query_arg([
            'page' => 'helmetsan-brands',
            'hs_brand_cascade' => 1,
            'hs_brand_count' => $updated,
        ], admin_url('admin.php'));
        wp_safe_redirect($redirect);
        exit;
    }

    public function ingestionPage(): void
    {
        $status  = isset($_GET['status']) ? sanitize_text_field((string) $_GET['status']) : 'all';
        $search  = isset($_GET['s']) ? sanitize_text_field((string) $_GET['s']) : '';
        $pageNum = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $perPage = 25;

        echo '<div class="wrap helmetsan-wrap">';
        $this->renderAppHeader('Ingestion', 'Track imports, retries, and data quality outcomes.');

        if (! $this->ingestionLogs->tableExists()) {
            echo '<p>Ingestion log table not found. Re-activate plugin or run table migration.</p></div>';
            return;
        }

        $statusCounts = $this->ingestionLogs->statusCounts();
        $totalRows    = $this->ingestionLogs->count($status, $search);
        $rows         = $this->ingestionLogs->fetch($pageNum, $perPage, $status, $search);

        $retried  = isset($_GET['hs_retry']) ? (int) $_GET['hs_retry'] : 0;
        $accepted = isset($_GET['hs_accepted']) ? (int) $_GET['hs_accepted'] : 0;
        $rejected = isset($_GET['hs_rejected']) ? (int) $_GET['hs_rejected'] : 0;
        if ($retried > 0) {
            echo '<div class="notice notice-success is-dismissible"><p>';
            echo esc_html('Retry executed. Accepted: ' . (string) $accepted . ', Rejected: ' . (string) $rejected . '.');
            echo '</p></div>';
        }

        $this->renderIngestionFilters($status, $search, $statusCounts);
        $this->renderIngestionTable($rows);
        $this->renderIngestionPagination($pageNum, $perPage, $totalRows, $status, $search);

        echo '</div>';
    }

    /**
     * @param array<string,int> $statusCounts
     */
    private function renderIngestionFilters(string $status, string $search, array $statusCounts): void
    {
        $statuses = ['all', 'created', 'updated', 'skipped', 'dry-run', 'rejected', 'failed'];

        echo '<form method="get" style="margin-bottom:12px;">';
        echo '<input type="hidden" name="page" value="helmetsan-ingestion" />';
        echo '<label for="hs-status" style="margin-right:8px;">Status</label>';
        echo '<select id="hs-status" name="status" style="margin-right:8px;">';
        foreach ($statuses as $item) {
            $label = $item;
            if ($item !== 'all' && isset($statusCounts[$item])) {
                $label .= ' (' . (string) $statusCounts[$item] . ')';
            }
            if ($item === 'all') {
                $label .= ' (' . (string) array_sum($statusCounts) . ')';
            }
            echo '<option value="' . esc_attr($item) . '" ' . selected($status, $item, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';
        echo '<label for="hs-search" class="screen-reader-text">Search logs</label>';
        echo '<input id="hs-search" type="search" name="s" value="' . esc_attr($search) . '" placeholder="Search source/message/external id" />';
        submit_button('Filter', 'secondary', '', false, ['style' => 'margin-left:8px;']);
        echo '</form>';
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    private function renderIngestionTable(array $rows): void
    {
        echo '<form method="post" style="margin:12px 0;">';
        wp_nonce_field('helmetsan_retry_ingestion', 'helmetsan_retry_nonce');
        echo '<input type="hidden" name="helmetsan_ingestion_action" value="retry_selected" />';
        echo '<p><button type="submit" class="button button-secondary">Retry selected</button></p>';

        echo '<table class="widefat striped"><thead><tr>';
        echo '<th class="check-column"><input type="checkbox" id="hs-select-all" /></th><th>ID</th><th>Date</th><th>Status</th><th>External ID</th><th>Post ID</th><th>Source</th><th>Message</th>';
        echo '</tr></thead><tbody>';

        if ($rows === []) {
            echo '<tr><td colspan="8">No log entries found.</td></tr>';
        } else {
            foreach ($rows as $row) {
                $id         = isset($row['id']) ? (int) $row['id'] : 0;
                $createdAt  = isset($row['created_at']) ? (string) $row['created_at'] : '';
                $entryStatus = isset($row['status']) ? (string) $row['status'] : '';
                $externalId = isset($row['external_id']) ? (string) $row['external_id'] : '';
                $postId     = isset($row['post_id']) ? (int) $row['post_id'] : 0;
                $source     = isset($row['source_file']) ? (string) $row['source_file'] : '';
                $message    = isset($row['message']) ? (string) $row['message'] : '';

                echo '<tr>';
                echo '<th class="check-column"><input type="checkbox" name="log_ids[]" value="' . esc_attr((string) $id) . '" /></th>';
                echo '<td>' . esc_html((string) $id) . '</td>';
                echo '<td>' . esc_html($createdAt) . '</td>';
                echo '<td><code>' . esc_html($entryStatus) . '</code></td>';
                echo '<td>' . esc_html($externalId) . '</td>';
                echo '<td>' . esc_html((string) $postId) . '</td>';
                echo '<td><code>' . esc_html($source) . '</code></td>';
                echo '<td>' . esc_html($message) . '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
        echo '<p><button type="submit" class="button button-secondary">Retry selected</button></p>';
        echo '</form>';
        echo '<script>document.addEventListener("DOMContentLoaded",function(){var a=document.getElementById("hs-select-all");if(!a){return;}a.addEventListener("change",function(){document.querySelectorAll("input[name=\'log_ids[]\']").forEach(function(cb){cb.checked=a.checked;});});});</script>';
    }

    private function renderIngestionPagination(int $pageNum, int $perPage, int $totalRows, string $status, string $search): void
    {
        $totalPages = (int) ceil($totalRows / $perPage);
        if ($totalPages <= 1) {
            return;
        }

        $baseUrl = add_query_arg([
            'page'   => 'helmetsan-ingestion',
            'status' => $status,
            's'      => $search,
            'paged'  => '%#%',
        ], admin_url('admin.php'));

        echo '<div class="tablenav"><div class="tablenav-pages" style="margin:12px 0;">';
        echo wp_kses_post(paginate_links([
            'base'      => $baseUrl,
            'format'    => '',
            'current'   => $pageNum,
            'total'     => $totalPages,
            'type'      => 'plain',
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
        ]));
        echo '</div></div>';
    }

    public function syncLogsPage(): void
    {
        $status  = isset($_GET['status']) ? sanitize_text_field((string) $_GET['status']) : 'all';
        $action  = isset($_GET['action_filter']) ? sanitize_text_field((string) $_GET['action_filter']) : 'all';
        $search  = isset($_GET['s']) ? sanitize_text_field((string) $_GET['s']) : '';
        $pageNum = isset($_GET['paged']) ? max(1, (int) $_GET['paged']) : 1;
        $perPage = 25;
        $viewId  = isset($_GET['view']) ? max(0, (int) $_GET['view']) : 0;

        echo '<div class="wrap helmetsan-wrap">';
        $this->renderAppHeader('Sync Logs', 'Audit pull/push runs and payload decisions.');
        $synced = isset($_GET['hs_sync']) ? (int) $_GET['hs_sync'] : 0;
        $syncMsg = isset($_GET['hs_sync_msg']) ? sanitize_text_field((string) $_GET['hs_sync_msg']) : '';
        if ($synced === 1 && $syncMsg !== '') {
            echo '<div class="notice notice-success is-dismissible"><p>' . esc_html($syncMsg) . '</p></div>';
        }

        $this->renderSyncPullControls();

        if (! $this->syncLogs->tableExists()) {
            echo '<p>Sync log table not found. Re-activate plugin or run table migration.</p></div>';
            return;
        }

        $statusCounts = $this->syncLogs->statusCounts();
        $actionCounts = $this->syncLogs->actionCounts();
        $totalRows    = $this->syncLogs->count($status, $action, $search);
        $rows         = $this->syncLogs->fetch($pageNum, $perPage, $status, $action, $search);

        $this->renderSyncLogFilters($status, $action, $search, $statusCounts, $actionCounts);
        $this->renderSyncLogTable($rows, $status, $action, $search, $pageNum);
        $this->renderSyncLogPagination($pageNum, $perPage, $totalRows, $status, $action, $search);

        if ($viewId > 0) {
            $row = $this->syncLogs->findById($viewId);
            if (is_array($row)) {
                $payload = isset($row['payload']) ? (string) $row['payload'] : '';
                echo '<h2>Payload: Log #' . esc_html((string) $viewId) . '</h2>';
                echo '<textarea readonly rows="14" style="width:100%;font-family:monospace;">' . esc_textarea($payload) . '</textarea>';
            }
        }

        echo '</div>';
    }

    private function renderSyncPullControls(): void
    {
        $github = wp_parse_args((array) get_option(Config::OPTION_GITHUB, []), $this->config->githubDefaults());
        $savedProfile = isset($github['sync_run_profile']) ? (string) $github['sync_run_profile'] : 'pull-only';
        $profileLocked = ! empty($github['sync_profile_lock']);
        if (! in_array($savedProfile, ['pull-only', 'pull+brands', 'pull+all'], true)) {
            $savedProfile = 'pull-only';
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="margin:12px 0;padding:12px;background:#fff;border:1px solid #dcdcde;">';
        wp_nonce_field('helmetsan_sync_pull_action', 'helmetsan_sync_pull_nonce');
        echo '<input type="hidden" name="action" value="helmetsan_sync_pull" />';
        echo '<h2 style="margin-top:0;">Run Pull Now</h2>';
        echo '<p><label for="hs-pull-limit">Limit</label> ';
        echo '<input id="hs-pull-limit" type="number" min="1" name="limit" value="200" class="small-text" /></p>';
        echo '<p><label for="hs-pull-profile">Profile</label> ';
        echo '<select id="hs-pull-profile" name="profile" ' . disabled($profileLocked, true, false) . '>';
        echo '<option value="saved">Saved (' . esc_html($savedProfile) . ')</option>';
        echo '<option value="pull-only">pull-only</option>';
        echo '<option value="pull+brands">pull+brands</option>';
        echo '<option value="pull+all">pull+all</option>';
        echo '</select></p>';
        if ($profileLocked) {
            echo '<p><em>Profile lock is enabled. Saved profile will always be used.</em></p>';
        }
        submit_button('Run Pull', 'secondary', 'submit', false);
        echo '</form>';
    }

    public function handleSyncPullAction(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $nonce = isset($_POST['helmetsan_sync_pull_nonce']) ? (string) $_POST['helmetsan_sync_pull_nonce'] : '';
        if (! wp_verify_nonce($nonce, 'helmetsan_sync_pull_action')) {
            wp_die('Invalid nonce');
        }

        $limit = isset($_POST['limit']) ? max(1, (int) $_POST['limit']) : 200;
        $profileRaw = isset($_POST['profile']) ? sanitize_text_field((string) $_POST['profile']) : 'saved';
        $profile = in_array($profileRaw, ['pull-only', 'pull+brands', 'pull+all'], true) ? $profileRaw : null;

        $user = wp_get_current_user();
        $audit = [
            'source' => 'admin',
            'trigger_user_id' => get_current_user_id(),
            'trigger_user_login' => $user instanceof \WP_User ? (string) $user->user_login : '',
        ];

        $result = $this->sync->pull($limit, false, null, null, null, $profile, $audit);
        $downloaded = isset($result['downloaded']) ? (int) $result['downloaded'] : 0;
        $failed = isset($result['failed']) ? (int) $result['failed'] : 0;
        $usedProfile = isset($result['profile']) ? (string) $result['profile'] : ($profile ?? 'saved');

        $msg = 'Pull complete. profile=' . $usedProfile . ', downloaded=' . (string) $downloaded . ', failed=' . (string) $failed;

        wp_safe_redirect(add_query_arg([
            'page' => 'helmetsan-sync-logs',
            'hs_sync' => 1,
            'hs_sync_msg' => $msg,
        ], admin_url('admin.php')));
        exit;
    }

    public function handleWooBridgeSyncAction(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $nonce = isset($_POST['helmetsan_woo_bridge_sync_nonce']) ? (string) $_POST['helmetsan_woo_bridge_sync_nonce'] : '';
        if (! wp_verify_nonce($nonce, 'helmetsan_woo_bridge_sync_action')) {
            wp_die('Invalid nonce');
        }

        $helmetId = isset($_POST['helmet_id']) ? max(0, (int) $_POST['helmet_id']) : 0;
        $limit = isset($_POST['limit']) ? max(1, (int) $_POST['limit']) : 100;
        $dryRun = isset($_POST['dry_run']);

        $result = $helmetId > 0
            ? $this->wooBridge->syncHelmet($helmetId, $dryRun)
            : $this->wooBridge->syncBatch($limit, $dryRun);

        $ok = ! empty($result['ok']);
        $msg = $ok
            ? ($helmetId > 0 ? 'Woo sync completed for helmet #' . (string) $helmetId : 'Woo batch sync completed.')
            : (string) ($result['message'] ?? 'Woo sync failed');

        wp_safe_redirect(add_query_arg([
            'page' => 'helmetsan-woo-bridge',
            'hs_woo_ok' => $ok ? 1 : 0,
            'hs_woo_msg' => $msg,
            'hs_woo_helmet' => $helmetId > 0 ? $helmetId : '',
            'hs_woo_report' => rawurlencode(wp_json_encode($result)),
        ], admin_url('admin.php')));
        exit;
    }

    /**
     * @param array<string,int> $statusCounts
     * @param array<string,int> $actionCounts
     */
    private function renderSyncLogFilters(string $status, string $action, string $search, array $statusCounts, array $actionCounts): void
    {
        $statuses = ['all', 'success', 'partial', 'error', 'info'];
        $actions  = ['all', 'pull', 'push'];

        echo '<form method="get" style="margin-bottom:12px;">';
        echo '<input type="hidden" name="page" value="helmetsan-sync-logs" />';

        echo '<label for="hs-sync-status" style="margin-right:8px;">Status</label>';
        echo '<select id="hs-sync-status" name="status" style="margin-right:8px;">';
        foreach ($statuses as $item) {
            $label = $item;
            if ($item !== 'all' && isset($statusCounts[$item])) {
                $label .= ' (' . (string) $statusCounts[$item] . ')';
            }
            if ($item === 'all') {
                $label .= ' (' . (string) array_sum($statusCounts) . ')';
            }
            echo '<option value="' . esc_attr($item) . '" ' . selected($status, $item, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';

        echo '<label for="hs-sync-action" style="margin-right:8px;">Action</label>';
        echo '<select id="hs-sync-action" name="action_filter" style="margin-right:8px;">';
        foreach ($actions as $item) {
            $label = $item;
            if ($item !== 'all' && isset($actionCounts[$item])) {
                $label .= ' (' . (string) $actionCounts[$item] . ')';
            }
            if ($item === 'all') {
                $label .= ' (' . (string) array_sum($actionCounts) . ')';
            }
            echo '<option value="' . esc_attr($item) . '" ' . selected($action, $item, false) . '>' . esc_html($label) . '</option>';
        }
        echo '</select>';

        echo '<label for="hs-sync-search" class="screen-reader-text">Search sync logs</label>';
        echo '<input id="hs-sync-search" type="search" name="s" value="' . esc_attr($search) . '" placeholder="Search branch/path/message" />';
        submit_button('Filter', 'secondary', '', false, ['style' => 'margin-left:8px;']);
        echo '</form>';
    }

    /**
     * @param array<int,array<string,mixed>> $rows
     */
    private function renderSyncLogTable(array $rows, string $status, string $action, string $search, int $pageNum): void
    {
        echo '<table class="widefat striped"><thead><tr>';
        echo '<th>ID</th><th>Date</th><th>Action</th><th>Mode</th><th>Status</th><th>Branch</th><th>Target</th><th>Counts</th><th>Message</th><th>View</th>';
        echo '</tr></thead><tbody>';

        if ($rows === []) {
            echo '<tr><td colspan="10">No sync log entries found.</td></tr>';
        } else {
            foreach ($rows as $row) {
                $id           = isset($row['id']) ? (int) $row['id'] : 0;
                $createdAt    = isset($row['created_at']) ? (string) $row['created_at'] : '';
                $entryAction  = isset($row['action']) ? (string) $row['action'] : '';
                $entryMode    = isset($row['mode']) ? (string) $row['mode'] : '';
                $entryStatus  = isset($row['status']) ? (string) $row['status'] : '';
                $branch       = isset($row['branch']) ? (string) $row['branch'] : '';
                $targetBranch = isset($row['target_branch']) ? (string) $row['target_branch'] : '';
                $processed    = isset($row['processed']) ? (int) $row['processed'] : 0;
                $pushed       = isset($row['pushed']) ? (int) $row['pushed'] : 0;
                $skipped      = isset($row['skipped']) ? (int) $row['skipped'] : 0;
                $failed       = isset($row['failed']) ? (int) $row['failed'] : 0;
                $message      = isset($row['message']) ? (string) $row['message'] : '';

                $viewUrl = add_query_arg([
                    'page'          => 'helmetsan-sync-logs',
                    'status'        => $status,
                    'action_filter' => $action,
                    's'             => $search,
                    'paged'         => $pageNum,
                    'view'          => $id,
                ], admin_url('admin.php'));

                echo '<tr>';
                echo '<td>' . esc_html((string) $id) . '</td>';
                echo '<td>' . esc_html($createdAt) . '</td>';
                echo '<td><code>' . esc_html($entryAction) . '</code></td>';
                echo '<td><code>' . esc_html($entryMode) . '</code></td>';
                echo '<td><code>' . esc_html($entryStatus) . '</code></td>';
                echo '<td>' . esc_html($branch) . '</td>';
                echo '<td>' . esc_html($targetBranch) . '</td>';
                echo '<td>P:' . esc_html((string) $processed) . ' / U:' . esc_html((string) $pushed) . ' / S:' . esc_html((string) $skipped) . ' / F:' . esc_html((string) $failed) . '</td>';
                echo '<td>' . esc_html($message) . '</td>';
                echo '<td><a class="button button-small" href="' . esc_url($viewUrl) . '">Payload</a></td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table>';
    }

    private function renderSyncLogPagination(int $pageNum, int $perPage, int $totalRows, string $status, string $action, string $search): void
    {
        $totalPages = (int) ceil($totalRows / $perPage);
        if ($totalPages <= 1) {
            return;
        }

        $baseUrl = add_query_arg([
            'page'          => 'helmetsan-sync-logs',
            'status'        => $status,
            'action_filter' => $action,
            's'             => $search,
            'paged'         => '%#%',
        ], admin_url('admin.php'));

        echo '<div class="tablenav"><div class="tablenav-pages" style="margin:12px 0;">';
        echo wp_kses_post(paginate_links([
            'base'      => $baseUrl,
            'format'    => '',
            'current'   => $pageNum,
            'total'     => $totalPages,
            'type'      => 'plain',
            'prev_text' => '&laquo;',
            'next_text' => '&raquo;',
        ]));
        echo '</div></div>';
    }

    public function handleIngestionActions(): void
    {
        if (! is_admin() || ! current_user_can('manage_options')) {
            return;
        }

        $action = isset($_POST['helmetsan_ingestion_action']) ? sanitize_text_field((string) $_POST['helmetsan_ingestion_action']) : '';
        if ($action !== 'retry_selected') {
            return;
        }

        $nonce = isset($_POST['helmetsan_retry_nonce']) ? (string) $_POST['helmetsan_retry_nonce'] : '';
        if (! wp_verify_nonce($nonce, 'helmetsan_retry_ingestion')) {
            return;
        }

        $ids = isset($_POST['log_ids']) && is_array($_POST['log_ids']) ? array_map('intval', $_POST['log_ids']) : [];
        if ($ids === []) {
            $redirect = add_query_arg([
                'page'        => 'helmetsan-ingestion',
                'hs_retry'    => 1,
                'hs_accepted' => 0,
                'hs_rejected' => 0,
            ], admin_url('admin.php'));
            wp_safe_redirect($redirect);
            exit;
        }

        $rows  = $this->ingestionLogs->findByIds($ids);
        $files = [];

        foreach ($rows as $row) {
            $status = isset($row['status']) ? (string) $row['status'] : '';
            $file   = isset($row['source_file']) ? (string) $row['source_file'] : '';

            if ($status !== 'failed' && $status !== 'rejected') {
                continue;
            }
            if ($file === '' || ! file_exists($file)) {
                continue;
            }
            $files[] = $file;
        }

        $files  = array_values(array_unique($files));
        $result = $this->ingestion->ingestFiles($files, 50, null, false, 'admin-retry');

        $redirect = add_query_arg([
            'page'        => 'helmetsan-ingestion',
            'hs_retry'    => 1,
            'hs_accepted' => (int) ($result['accepted'] ?? 0),
            'hs_rejected' => (int) ($result['rejected'] ?? 0),
        ], admin_url('admin.php'));

        wp_safe_redirect($redirect);
        exit;
    }

    public function repoHealthPage(): void
    {
        echo '<div class="wrap helmetsan-wrap">';
        $this->renderAppHeader('Repo Health', 'Repository, logs, scheduler, and integration health.');
        echo '<div class="hs-panel"><pre>' . esc_html(wp_json_encode($this->health->report(), JSON_PRETTY_PRINT)) . '</pre></div></div>';
    }

    public function analyticsPage(): void
    {
        $smoke = $this->smoke->run();
        $days = isset($_GET['days']) ? max(1, (int) $_GET['days']) : 7;

        echo '<div class="wrap helmetsan-wrap">';
        $this->renderAppHeader('Analytics', 'Runtime readiness, events, and instrumentation checks.');
        echo '<h2>Runtime Smoke Test</h2>';
        echo '<pre>' . esc_html(wp_json_encode($smoke, JSON_PRETTY_PRINT)) . '</pre>';

        echo '<h2>Event Tracking (Last ' . esc_html((string) $days) . ' Days)</h2>';
        if (! $this->analyticsEvents->tableExists()) {
            echo '<p>Event table not found. Re-activate plugin to create analytics event table.</p>';
            echo '</div>';
            return;
        }

        $total = $this->analyticsEvents->total($days);
        $byEvent = $this->analyticsEvents->countByEvent($days);

        echo '<p>Total events: <strong>' . esc_html((string) $total) . '</strong></p>';
        echo '<ul>';
        foreach ($byEvent as $name => $count) {
            echo '<li>' . esc_html((string) $name) . ': ' . esc_html((string) $count) . '</li>';
        }
        echo '</ul>';
        echo '</div>';
    }

    public function revenuePage(): void
    {
        $days = isset($_GET['days']) ? max(1, (int) $_GET['days']) : 30;
        $report = $this->revenue->report($days);

        echo '<div class="wrap helmetsan-wrap">';
        $this->renderAppHeader('Revenue', 'Click-through sources, network split, and top helmets.');

        if (empty($report['ok'])) {
            echo '<p>' . esc_html((string) ($report['message'] ?? 'Revenue data unavailable')) . '</p></div>';
            return;
        }

        echo '<p>Total clicks (last ' . esc_html((string) $days) . ' days): <strong>' . esc_html((string) $report['total_clicks']) . '</strong></p>';

        echo '<h2>By Source</h2><ul>';
        $bySource = isset($report['by_source']) && is_array($report['by_source']) ? $report['by_source'] : [];
        foreach ($bySource as $source => $count) {
            echo '<li>' . esc_html((string) $source) . ': ' . esc_html((string) $count) . '</li>';
        }
        echo '</ul>';

        echo '<h2>By Network</h2><ul>';
        $byNetwork = isset($report['by_network']) && is_array($report['by_network']) ? $report['by_network'] : [];
        foreach ($byNetwork as $network => $count) {
            echo '<li>' . esc_html((string) $network) . ': ' . esc_html((string) $count) . '</li>';
        }
        echo '</ul>';

        echo '<h2>Top Helmets</h2><table class=\"widefat striped\"><thead><tr><th>Helmet</th><th>Clicks</th></tr></thead><tbody>';
        $top = isset($report['top_helmets']) && is_array($report['top_helmets']) ? $report['top_helmets'] : [];
        if ($top === []) {
            echo '<tr><td colspan=\"2\">No data</td></tr>';
        } else {
            foreach ($top as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $title = isset($row['title']) ? (string) $row['title'] : '';
                $clicks = isset($row['clicks']) ? (int) $row['clicks'] : 0;
                echo '<tr><td>' . esc_html($title) . '</td><td>' . esc_html((string) $clicks) . '</td></tr>';
            }
        }
        echo '</tbody></table>';
        echo '</div>';
    }

    public function contributionsPage(): void
    {
        echo '<div class="wrap helmetsan-wrap">';
        $this->renderAppHeader('Contributions', 'Community submissions and moderation pipeline.');
        echo '<div class="hs-panel"><p>Contribution manager scaffold ready.</p></div></div>';
    }

    public function importExportPage(): void
    {
        $imported = isset($_GET['hs_import']) ? (int) $_GET['hs_import'] : 0;
        $exported = isset($_GET['hs_export']) ? (int) $_GET['hs_export'] : 0;
        $ok       = isset($_GET['hs_ok']) ? (int) $_GET['hs_ok'] : 0;
        $message  = isset($_GET['hs_msg']) ? sanitize_text_field((string) $_GET['hs_msg']) : '';

        echo '<div class="wrap helmetsan-wrap">';
        $this->renderAppHeader('Import/Export', 'Move helmet and brand data in controlled batches.');

        if (($imported || $exported) && $message !== '') {
            $class = $ok ? 'notice-success' : 'notice-error';
            echo '<div class="notice ' . esc_attr($class) . ' is-dismissible"><p>' . esc_html($message) . '</p></div>';
        }

        echo '<h2>Import JSON</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" enctype="multipart/form-data">';
        wp_nonce_field('helmetsan_import_action', 'helmetsan_import_nonce');
        echo '<input type="hidden" name="action" value="helmetsan_import" />';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="hs-import-file">JSON File</label></th><td><input id="hs-import-file" type="file" name="import_file" accept=\".json,application/json\" /></td></tr>';
        echo '<tr><th><label for="hs-import-path">Or File Path</label></th><td><input id="hs-import-path" type="text" class="regular-text" name="import_path" placeholder="/absolute/path/file.json" /></td></tr>';
        echo '<tr><th><label for="hs-import-batch">Batch Size</label></th><td><input id="hs-import-batch" type="number" name="batch_size" min="1" value="100" /></td></tr>';
        echo '<tr><th><label for="hs-import-dry">Dry Run</label></th><td><label><input id="hs-import-dry" type="checkbox" name="dry_run" value="1" /> Validate without writing</label></td></tr>';
        echo '</tbody></table>';
        submit_button('Run Import');
        echo '</form>';

        echo '<hr />';
        echo '<h2>Export JSON</h2>';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
        wp_nonce_field('helmetsan_export_action', 'helmetsan_export_nonce');
        echo '<input type="hidden" name="action" value="helmetsan_export" />';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th><label for="hs-export-entity">Entity</label></th><td><select id="hs-export-entity" name="entity"><option value="helmet">helmet</option><option value="brand">brand</option></select></td></tr>';
        echo '<tr><th><label for="hs-export-id">Post ID</label></th><td><input id="hs-export-id" type="number" name="post_id" min="1" required /></td></tr>';
        echo '<tr><th><label for="hs-export-out">Output Path (Optional)</label></th><td><input id="hs-export-out" type="text" class="regular-text" name="out" placeholder="/absolute/path/output.json" /></td></tr>';
        echo '</tbody></table>';
        submit_button('Run Export');
        echo '</form>';

        echo '</div>';
    }

    public function handleImportAction(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $nonce = isset($_POST['helmetsan_import_nonce']) ? (string) $_POST['helmetsan_import_nonce'] : '';
        if (! wp_verify_nonce($nonce, 'helmetsan_import_action')) {
            wp_die('Invalid nonce');
        }

        $batchSize = isset($_POST['batch_size']) ? max(1, (int) $_POST['batch_size']) : 100;
        $dryRun    = isset($_POST['dry_run']);

        $filePath = '';
        $manualPath = isset($_POST['import_path']) ? trim((string) $_POST['import_path']) : '';

        if (isset($_FILES['import_file']) && is_array($_FILES['import_file']) && ! empty($_FILES['import_file']['name'])) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            $upload = wp_handle_upload($_FILES['import_file'], ['test_form' => false]);
            if (is_array($upload) && isset($upload['file']) && is_string($upload['file'])) {
                $filePath = $upload['file'];
            } else {
                $message = is_array($upload) && isset($upload['error']) ? (string) $upload['error'] : 'Upload failed';
                $this->redirectImportExport('import', false, $message);
            }
        } elseif ($manualPath !== '') {
            $filePath = $manualPath;
        } else {
            $this->redirectImportExport('import', false, 'No file provided');
        }

        $result = $this->importService->importJsonFile($filePath, $dryRun, $batchSize);
        $ok     = ! empty($result['ok']);
        $msg    = $ok
            ? 'Import finished. accepted=' . (string) ($result['accepted'] ?? 0) . ', rejected=' . (string) ($result['rejected'] ?? 0)
            : (string) ($result['message'] ?? 'Import failed');

        $this->redirectImportExport('import', $ok, $msg);
    }

    public function handleExportAction(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        $nonce = isset($_POST['helmetsan_export_nonce']) ? (string) $_POST['helmetsan_export_nonce'] : '';
        if (! wp_verify_nonce($nonce, 'helmetsan_export_action')) {
            wp_die('Invalid nonce');
        }

        $postId = isset($_POST['post_id']) ? (int) $_POST['post_id'] : 0;
        $entity = isset($_POST['entity']) ? sanitize_key((string) $_POST['entity']) : 'helmet';
        $entity = in_array($entity, ['helmet', 'brand'], true) ? $entity : 'helmet';
        $out    = isset($_POST['out']) ? trim((string) $_POST['out']) : '';

        if ($postId <= 0) {
            $this->redirectImportExport('export', false, 'Invalid post ID');
        }

        $result = $this->exportService->exportByPostId($postId, $entity, $out !== '' ? $out : null);
        $ok     = ! empty($result['ok']);
        $msg    = $ok
            ? 'Export completed: ' . (string) ($result['file'] ?? '')
            : (string) ($result['message'] ?? 'Export failed');

        $this->redirectImportExport('export', $ok, $msg);
    }

    private function redirectImportExport(string $type, bool $ok, string $message): void
    {
        $query = [
            'page'   => 'helmetsan-import-export',
            'hs_ok'  => $ok ? 1 : 0,
            'hs_msg' => $message,
        ];

        if ($type === 'import') {
            $query['hs_import'] = 1;
        } else {
            $query['hs_export'] = 1;
        }

        wp_safe_redirect(add_query_arg($query, admin_url('admin.php')));
        exit;
    }

    public function goLivePage(): void
    {
        $report = $this->checklist->report();
        $score = isset($report['score']) ? (int) $report['score'] : 0;
        $pass = ! empty($report['pass']);
        $threshold = isset($report['threshold']) ? (int) $report['threshold'] : 80;
        $totals = isset($report['totals']) && is_array($report['totals']) ? $report['totals'] : [];
        $checks = isset($report['checks']) && is_array($report['checks']) ? $report['checks'] : [];
        $criticalFailures = isset($report['critical_failures']) && is_array($report['critical_failures']) ? $report['critical_failures'] : [];
        $engines = $this->engineSnapshot();

        echo '<div class="wrap helmetsan-wrap">';
        $this->renderAppHeader('Go Live', 'Production readiness gate with objective launch criteria.');
        echo '<section class="hs-hero hs-hero--gate">';
        echo '<div class="hs-hero__meta">';
        echo '<p class="hs-eyebrow">Production Gate</p>';
        echo '<h2>Launch decision is driven by checks, not opinion</h2>';
        echo '<p>Critical blockers force fail regardless of score. Use this gate before enabling paid traffic or sales campaigns.</p>';
        echo '</div>';
        echo '<div class="hs-hero__status">';
        echo '<p><strong>Status:</strong> ' . wp_kses_post($this->renderStatusPill($pass ? 'PASS' : 'FAIL', $pass)) . '</p>';
        echo '<p><strong>Score:</strong> ' . esc_html((string) $score) . '/100</p>';
        echo '<p><strong>Threshold:</strong> ' . esc_html((string) $threshold) . '</p>';
        echo wp_kses_post($this->renderScoreBar($score));
        echo '</div>';
        echo '</section>';

        $this->renderMetricCards([
            ['label' => 'Checks Passed', 'value' => (string) ($totals['passed'] ?? 0), 'page' => 'helmetsan-go-live'],
            ['label' => 'Checks Failed', 'value' => (string) ($totals['failed'] ?? 0), 'page' => 'helmetsan-go-live'],
            ['label' => 'Critical Failures', 'value' => (string) count($criticalFailures), 'page' => 'helmetsan-go-live'],
            ['label' => 'Score', 'value' => (string) $score . '/100', 'page' => 'helmetsan-go-live'],
        ]);

        echo '<div class="hs-panel">';
        echo '<h3>Engine Readiness</h3>';
        echo '<table class="widefat striped hs-table-compact"><thead><tr><th>Engine</th><th>Volume</th><th>Status</th><th>Guidance</th></tr></thead><tbody>';
        $engineRows = [
            ['name' => 'Pricing', 'count' => (int) ($engines['pricing'] ?? 0), 'min' => 1, 'guide' => 'At least one helmet has geo pricing records.'],
            ['name' => 'Offers', 'count' => (int) ($engines['offers'] ?? 0), 'min' => 1, 'guide' => 'At least one best-offer record is resolved.'],
            ['name' => 'Marketplaces', 'count' => (int) ($engines['marketplaces'] ?? 0), 'min' => 1, 'guide' => 'Marketplace index exists for routing offers.'],
            ['name' => 'Dealers', 'count' => (int) ($engines['dealers'] ?? 0), 'min' => 1, 'guide' => 'Store network has at least one dealer.'],
            ['name' => 'Distributors', 'count' => (int) ($engines['distributors'] ?? 0), 'min' => 1, 'guide' => 'Authorized distributor list is seeded.'],
            ['name' => 'Recommendations', 'count' => (int) ($engines['recommendations'] ?? 0), 'min' => 1, 'guide' => 'Recommendation engine has published bundles.'],
            ['name' => 'Comparisons', 'count' => (int) ($engines['comparisons'] ?? 0), 'min' => 1, 'guide' => 'Comparison engine has at least one profile.'],
        ];
        foreach ($engineRows as $engineRow) {
            $ok = $engineRow['count'] >= $engineRow['min'];
            echo '<tr>';
            echo '<td>' . esc_html((string) $engineRow['name']) . '</td>';
            echo '<td>' . esc_html((string) $engineRow['count']) . '</td>';
            echo '<td>' . wp_kses_post($this->renderStatusPill($ok ? 'READY' : 'MISSING', $ok)) . '</td>';
            echo '<td>' . esc_html((string) $engineRow['guide']) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';

        if ($criticalFailures !== []) {
            echo '<div class="hs-panel">';
            echo '<h3>Critical Blockers</h3><ul class="hs-bullet-list">';
            foreach ($criticalFailures as $id) {
                echo '<li><code>' . esc_html((string) $id) . '</code></li>';
            }
            echo '</ul></div>';
        }

        echo '<div class="hs-panel">';
        echo '<h3>Gate Breakdown</h3>';
        echo '<table class="widefat striped"><thead><tr><th>ID</th><th>Check</th><th>Critical</th><th>Weight</th><th>Status</th><th>Details</th></tr></thead><tbody>';
        foreach ($checks as $check) {
            if (! is_array($check)) {
                continue;
            }
            $isPass = ! empty($check['passed']);
            $statusText = $isPass ? 'PASS' : 'FAIL';
            echo '<tr>';
            echo '<td><code>' . esc_html((string) ($check['id'] ?? '')) . '</code></td>';
            echo '<td>' . esc_html((string) ($check['label'] ?? '')) . '</td>';
            echo '<td>' . (! empty($check['critical']) ? 'yes' : 'no') . '</td>';
            echo '<td>' . esc_html((string) ($check['weight'] ?? 0)) . '</td>';
            echo '<td>' . wp_kses_post($this->renderStatusPill($statusText, $isPass)) . '</td>';
            echo '<td>' . esc_html((string) ($check['details'] ?? '')) . '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
        echo '</div>';
    }

    public function docsPage(): void
    {
        $files = $this->docs->listDocs();
        echo '<div class="wrap helmetsan-wrap">';
        $this->renderAppHeader('Documentation', 'Local references and engineering docs.');
        echo '<div class="hs-panel"><ul>';
        foreach ($files as $file) {
            echo '<li>' . esc_html(basename($file)) . '</li>';
        }
        echo '</ul></div></div>';
    }

    /**
     * @param array<int,array{key:string,option:string,label:string,desc:string,type:string,choices?:array<string,string>,prefix?:string}> $fields
     * @param array<string,mixed> $values
     */
    private function renderSettingsSection(string $heading, string $intro, array $fields, array $values): void
    {
        echo '<h2>' . esc_html($heading) . '</h2>';
        if ($intro !== '') {
            echo '<p class="description" style="margin:-8px 0 16px;">' . esc_html($intro) . '</p>';
        }
        echo '<table class="form-table"><tbody>';
        foreach ($fields as $f) {
            $key = $f['key'];
            $opt = $f['option'];
            $label = $f['label'];
            $desc = $f['desc'] ?? '';
            $type = $f['type'] ?? 'text';
            $pfx = $f['prefix'] ?? '';
            $id = $pfx . $key;
            $name = esc_attr($opt) . '[' . esc_attr($key) . ']';
            $cur = $values[$key] ?? '';

            echo '<tr><th><label for="' . esc_attr($id) . '">' . esc_html($label) . '</label></th><td>';

            if ($type === 'checkbox') {
                echo '<input type="checkbox" id="' . esc_attr($id) . '" name="' . $name . '" value="1" ' . checked(!empty($cur), true, false) . ' />';
            } elseif ($type === 'select' && isset($f['choices'])) {
                echo '<select id="' . esc_attr($id) . '" name="' . $name . '">';
                foreach ($f['choices'] as $v => $l) {
                    echo '<option value="' . esc_attr($v) . '" ' . selected((string)$cur, $v, false) . '>' . esc_html($l) . '</option>';
                }
                echo '</select>';
            } elseif ($type === 'number') {
                echo '<input type="number" class="small-text" id="' . esc_attr($id) . '" name="' . $name . '" value="' . esc_attr((string)$cur) . '" min="0" />';
            } elseif ($type === 'password') {
                $val = !empty($cur) ? '' : '';
                $ph = !empty($cur) ? 'Saved (masked). Enter new value to replace.' : '';
                echo '<input type="password" class="regular-text" id="' . esc_attr($id) . '" name="' . $name . '" value="" placeholder="' . esc_attr($ph) . '" autocomplete="new-password" />';
            } elseif ($type === 'url') {
                echo '<input type="url" class="regular-text" id="' . esc_attr($id) . '" name="' . $name . '" value="' . esc_attr((string)$cur) . '" />';
            } elseif ($type === 'textarea') {
                echo '<textarea id="' . esc_attr($id) . '" name="' . $name . '" rows="8" class="large-text code">' . esc_textarea((string)$cur) . '</textarea>';
            } else {
                echo '<input type="text" class="regular-text" id="' . esc_attr($id) . '" name="' . $name . '" value="' . esc_attr((string)$cur) . '" />';
            }

            if ($desc !== '') {
                echo '<p class="description">' . esc_html($desc) . '</p>';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';
    }

    public function settingsPage(): void
    {
        $analytics   = wp_parse_args((array) get_option(Config::OPTION_ANALYTICS, []), $this->config->analyticsDefaults());
        $github      = wp_parse_args((array) get_option(Config::OPTION_GITHUB, []), $this->config->githubDefaults());
        $marketplace = wp_parse_args((array) get_option(Config::OPTION_MARKETPLACE, []), $this->config->marketplaceDefaults());
        $geo         = wp_parse_args((array) get_option(Config::OPTION_GEO, []), $this->config->geoDefaults());
        $revenue     = wp_parse_args((array) get_option(Config::OPTION_REVENUE, []), $this->config->revenueDefaults());
        $scheduler   = wp_parse_args((array) get_option(Config::OPTION_SCHEDULER, []), $this->config->schedulerDefaults());
        $alerts      = wp_parse_args((array) get_option(Config::OPTION_ALERTS, []), $this->config->alertsDefaults());
        $media       = wp_parse_args((array) get_option(Config::OPTION_MEDIA, []), $this->config->mediaDefaults());
        $wooBridge   = wp_parse_args((array) get_option(Config::OPTION_WOO_BRIDGE, []), $this->config->wooBridgeDefaults());
        $features    = wp_parse_args((array) get_option(Config::OPTION_FEATURES, []), $this->config->featuresDefaults());

        $tabs = [
            'analytics'   => 'Analytics',
            'github'      => 'GitHub Sync',
            'marketplace' => 'Marketplace Connectors',
            'geo'         => 'Geo & Localization',
            'revenue'     => 'Revenue & Affiliates',
            'scheduler'   => 'Scheduler',
            'alerts'      => 'Alerts & Notifications',
            'media'       => 'Media Engine',
            'woobridge'   => 'WooBridge',
            'features'    => 'Features & Toggles',
        ];
        $activeTab = isset($_GET['stab']) ? sanitize_key((string) $_GET['stab']) : 'analytics';
        if (!isset($tabs[$activeTab])) {
            $activeTab = 'analytics';
        }

        echo '<div class="wrap helmetsan-wrap">';
        $this->renderAppHeader('Settings', 'Plugin configuration organized by module. Each section saves independently.');
        echo '<form method="post" action="options.php">';
        settings_fields('helmetsan_settings');

        // Tab navigation
        echo '<nav class="nav-tab-wrapper" style="margin-bottom:20px;">';
        foreach ($tabs as $slug => $label) {
            $url = add_query_arg(['page' => 'helmetsan-settings', 'stab' => $slug], admin_url('admin.php'));
            $class = $slug === $activeTab ? 'nav-tab nav-tab-active' : 'nav-tab';
            echo '<a class="' . esc_attr($class) . '" href="' . esc_url($url) . '">' . esc_html($label) . '</a>';
        }
        echo '</nav>';

        $O_A = Config::OPTION_ANALYTICS;
        $O_G = Config::OPTION_GITHUB;
        $O_M = Config::OPTION_MARKETPLACE;
        $O_GE = Config::OPTION_GEO;
        $O_R = Config::OPTION_REVENUE;
        $O_S = Config::OPTION_SCHEDULER;
        $O_AL = Config::OPTION_ALERTS;
        $O_ME = Config::OPTION_MEDIA;
        $O_W = Config::OPTION_WOO_BRIDGE;
        $O_F = Config::OPTION_FEATURES;

        // ── Analytics ────────────────────────────────────────────
        if ($activeTab === 'analytics') {
            $this->renderSettingsSection('Google Analytics & Tag Manager', 'Connect GA4 or GTM. If MonsterInsights is active, respect its tracker by default.', [
                ['key' => 'enable_analytics', 'option' => $O_A, 'label' => 'Enable Analytics', 'desc' => 'Inject analytics script on the frontend.', 'type' => 'checkbox'],
                ['key' => 'analytics_respect_monsterinsights', 'option' => $O_A, 'label' => 'Respect MonsterInsights', 'desc' => 'Skip injection if MonsterInsights is active.', 'type' => 'checkbox'],
                ['key' => 'ga4_measurement_id', 'option' => $O_A, 'label' => 'GA4 Measurement ID', 'desc' => 'e.g. G-XXXXXXXXXX', 'type' => 'text'],
                ['key' => 'gtm_container_id', 'option' => $O_A, 'label' => 'GTM Container ID', 'desc' => 'e.g. GTM-XXXXXXX. Takes precedence over GA4 if set.', 'type' => 'text'],
            ], $analytics);

            $this->renderSettingsSection('Event Tracking', 'Track user interactions like clicks, search, and form submissions.', [
                ['key' => 'enable_enhanced_event_tracking', 'option' => $O_A, 'label' => 'Enhanced Event Tracking', 'desc' => 'Push custom events to the data layer (helmet views, CTA clicks, etc.).', 'type' => 'checkbox'],
                ['key' => 'enable_internal_search_tracking', 'option' => $O_A, 'label' => 'Internal Search Tracking', 'desc' => 'Fire a view_search_results event on WP search.', 'type' => 'checkbox'],
            ], $analytics);

            $this->renderSettingsSection('Heatmaps & Session Recording', 'Optional third-party heatmap providers.', [
                ['key' => 'enable_heatmap_clarity', 'option' => $O_A, 'label' => 'Microsoft Clarity', 'desc' => 'Enable Clarity heatmaps.', 'type' => 'checkbox'],
                ['key' => 'clarity_project_id', 'option' => $O_A, 'label' => 'Clarity Project ID', 'desc' => '', 'type' => 'text', 'prefix' => 'a_'],
                ['key' => 'enable_heatmap_hotjar', 'option' => $O_A, 'label' => 'Hotjar', 'desc' => 'Enable Hotjar heatmaps.', 'type' => 'checkbox'],
                ['key' => 'hotjar_site_id', 'option' => $O_A, 'label' => 'Hotjar Site ID', 'desc' => '', 'type' => 'text', 'prefix' => 'a_'],
                ['key' => 'hotjar_version', 'option' => $O_A, 'label' => 'Hotjar Version', 'desc' => 'Usually 6.', 'type' => 'text', 'prefix' => 'a_'],
            ], $analytics);
        }

        // ── GitHub Sync ──────────────────────────────────────────
        if ($activeTab === 'github') {
            $this->renderSettingsSection('Repository Connection', 'Connect to a GitHub repo for data synchronization.', [
                ['key' => 'enabled', 'option' => $O_G, 'label' => 'Enable GitHub Sync', 'desc' => 'Turn on automatic syncing with GitHub.', 'type' => 'checkbox', 'prefix' => 'gh_'],
                ['key' => 'owner', 'option' => $O_G, 'label' => 'Repository Owner', 'desc' => 'GitHub username or organization.', 'type' => 'text', 'prefix' => 'gh_'],
                ['key' => 'repo', 'option' => $O_G, 'label' => 'Repository Name', 'desc' => '', 'type' => 'text', 'prefix' => 'gh_'],
                ['key' => 'token', 'option' => $O_G, 'label' => 'Personal Access Token', 'desc' => 'Fine-grained PAT with contents read/write permission.', 'type' => 'password', 'prefix' => 'gh_'],
                ['key' => 'branch', 'option' => $O_G, 'label' => 'Branch', 'desc' => 'Branch to sync from (default: main).', 'type' => 'text', 'prefix' => 'gh_'],
                ['key' => 'remote_path', 'option' => $O_G, 'label' => 'Remote Path', 'desc' => 'Subdirectory inside the repo (e.g. data/helmets).', 'type' => 'text', 'prefix' => 'gh_'],
            ], $github);

            $this->renderSettingsSection('Sync Behavior', 'Control how data is pulled and pushed.', [
                ['key' => 'sync_json_only', 'option' => $O_G, 'label' => 'JSON Files Only', 'desc' => 'Only sync .json files; ignore others.', 'type' => 'checkbox', 'prefix' => 'gh_'],
                ['key' => 'sync_run_profile', 'option' => $O_G, 'label' => 'Sync Profile', 'desc' => 'Which entities to process after pull.', 'type' => 'select', 'choices' => ['pull-only' => 'Pull Only', 'pull+brands' => 'Pull + Brands', 'pull+all' => 'Pull + All'], 'prefix' => 'gh_'],
                ['key' => 'sync_profile_lock', 'option' => $O_G, 'label' => 'Lock Profile', 'desc' => 'Prevent profile changes from the sync UI.', 'type' => 'checkbox', 'prefix' => 'gh_'],
            ], $github);

            $this->renderSettingsSection('Push / PR Settings', 'Configure how outbound changes are pushed to GitHub.', [
                ['key' => 'push_mode', 'option' => $O_G, 'label' => 'Push Mode', 'desc' => 'Direct commit or open a pull request.', 'type' => 'select', 'choices' => ['commit' => 'Direct Commit', 'pr' => 'Pull Request'], 'prefix' => 'gh_'],
                ['key' => 'pr_branch_prefix', 'option' => $O_G, 'label' => 'PR Branch Prefix', 'desc' => 'Prefix for auto-created branches.', 'type' => 'text', 'prefix' => 'gh_'],
                ['key' => 'pr_reuse_open', 'option' => $O_G, 'label' => 'Reuse Open PR', 'desc' => 'Push to an existing open PR branch instead of creating new ones.', 'type' => 'checkbox', 'prefix' => 'gh_'],
                ['key' => 'pr_auto_merge', 'option' => $O_G, 'label' => 'Auto-Merge PR', 'desc' => 'Automatically merge the PR after creation.', 'type' => 'checkbox', 'prefix' => 'gh_'],
            ], $github);
        }

        // ── Marketplace Connectors ───────────────────────────────
        if ($activeTab === 'marketplace') {
            $this->renderSettingsSection('Amazon SP-API', 'Connect to Amazon Product Advertising API for pricing data.', [
                ['key' => 'amazon_enabled', 'option' => $O_M, 'label' => 'Enable Amazon', 'desc' => 'Activate the Amazon connector.', 'type' => 'checkbox', 'prefix' => 'mk_'],
                ['key' => 'amazon_client_id', 'option' => $O_M, 'label' => 'Client ID', 'desc' => 'SP-API client ID from Amazon Developer Console.', 'type' => 'text', 'prefix' => 'mk_'],
                ['key' => 'amazon_client_secret', 'option' => $O_M, 'label' => 'Client Secret', 'desc' => '', 'type' => 'password', 'prefix' => 'mk_'],
                ['key' => 'amazon_refresh_token', 'option' => $O_M, 'label' => 'Refresh Token', 'desc' => '', 'type' => 'password', 'prefix' => 'mk_'],
                ['key' => 'amazon_affiliate_tag', 'option' => $O_M, 'label' => 'Affiliate Tag', 'desc' => 'e.g. helmetsan-20', 'type' => 'text', 'prefix' => 'mk_'],
            ], $marketplace);
            // Amazon countries (array → comma-separated text)
            $amzCountries = isset($marketplace['amazon_countries']) && is_array($marketplace['amazon_countries']) ? implode(', ', $marketplace['amazon_countries']) : 'US, UK, DE, IN';
            echo '<table class="form-table"><tbody>';
            echo '<tr><th><label for="mk_amazon_countries">Amazon Countries</label></th><td>';
            echo '<input type="text" class="regular-text" id="mk_amazon_countries" name="' . esc_attr($O_M) . '[amazon_countries]" value="' . esc_attr($amzCountries) . '" />';
            echo '<p class="description">Comma-separated 2-letter codes (e.g. US, UK, DE, IN).</p></td></tr>';
            echo '</tbody></table>';

            $this->renderSettingsSection('Allegro', 'Central European marketplace connector.', [
                ['key' => 'allegro_enabled', 'option' => $O_M, 'label' => 'Enable Allegro', 'desc' => '', 'type' => 'checkbox', 'prefix' => 'mk_'],
                ['key' => 'allegro_client_id', 'option' => $O_M, 'label' => 'Client ID', 'desc' => '', 'type' => 'text', 'prefix' => 'mk2_'],
                ['key' => 'allegro_client_secret', 'option' => $O_M, 'label' => 'Client Secret', 'desc' => '', 'type' => 'password', 'prefix' => 'mk2_'],
                ['key' => 'allegro_refresh_token', 'option' => $O_M, 'label' => 'Refresh Token', 'desc' => '', 'type' => 'password', 'prefix' => 'mk2_'],
                ['key' => 'allegro_affiliate_id', 'option' => $O_M, 'label' => 'Affiliate ID', 'desc' => '', 'type' => 'text', 'prefix' => 'mk2_'],
            ], $marketplace);

            $this->renderSettingsSection('Jumia', 'African marketplace connector.', [
                ['key' => 'jumia_enabled', 'option' => $O_M, 'label' => 'Enable Jumia', 'desc' => '', 'type' => 'checkbox', 'prefix' => 'mk_'],
                ['key' => 'jumia_api_key', 'option' => $O_M, 'label' => 'API Key', 'desc' => '', 'type' => 'password', 'prefix' => 'mk3_'],
                ['key' => 'jumia_affiliate_id', 'option' => $O_M, 'label' => 'Affiliate ID', 'desc' => '', 'type' => 'text', 'prefix' => 'mk3_'],
            ], $marketplace);
            // Jumia countries
            $jumCountries = isset($marketplace['jumia_countries']) && is_array($marketplace['jumia_countries']) ? implode(', ', $marketplace['jumia_countries']) : 'NG, KE, EG';
            echo '<table class="form-table"><tbody>';
            echo '<tr><th><label for="mk_jumia_countries">Jumia Countries</label></th><td>';
            echo '<input type="text" class="regular-text" id="mk_jumia_countries" name="' . esc_attr($O_M) . '[jumia_countries]" value="' . esc_attr($jumCountries) . '" />';
            echo '<p class="description">Comma-separated 2-letter codes.</p></td></tr>';
            echo '</tbody></table>';

            echo '<h2>Affiliate Feeds</h2>';
            echo '<p class="description" style="margin:-8px 0 16px;">CSV/XML product feeds from affiliate retailers. Configure feed URLs and column mappings.</p>';
            $feeds = isset($marketplace['affiliate_feeds']) && is_array($marketplace['affiliate_feeds']) ? $marketplace['affiliate_feeds'] : [];
            if (empty($feeds)) {
                echo '<p><em>No affiliate feeds configured. Use code or database to add feeds.</em></p>';
            } else {
                foreach ($feeds as $feedId => $feedCfg) {
                    if (!is_array($feedCfg)) {
                        continue;
                    }
                    $feedName = (string) ($feedCfg['name'] ?? $feedId);
                    $feedEnabled = !empty($feedCfg['enabled']);
                    $feedUrl = (string) ($feedCfg['url'] ?? '');
                    $feedCurrency = (string) ($feedCfg['currency'] ?? 'USD');
                    $feedCountries = isset($feedCfg['countries']) && is_array($feedCfg['countries']) ? implode(', ', $feedCfg['countries']) : '';
                    $statusLabel = $feedEnabled ? '✅' : '⏸️';
                    echo '<details style="margin-bottom:12px;border:1px solid #ccc;border-radius:4px;padding:8px 12px;">';
                    echo '<summary style="cursor:pointer;font-weight:600;">' . $statusLabel . ' ' . esc_html($feedName) . ' <code style="font-weight:normal;margin-left:6px;">' . esc_html($feedId) . '</code></summary>';
                    echo '<table class="form-table" style="margin-top:8px;"><tbody>';
                    $bn = esc_attr($O_M) . '[affiliate_feeds][' . esc_attr($feedId) . ']';
                    echo '<tr><th>Enabled</th><td><input type="checkbox" name="' . $bn . '[enabled]" value="1" ' . checked($feedEnabled, true, false) . ' /></td></tr>';
                    echo '<tr><th>Name</th><td><input type="text" class="regular-text" name="' . $bn . '[name]" value="' . esc_attr($feedName) . '" /></td></tr>';
                    echo '<tr><th>Countries</th><td><input type="text" class="regular-text" name="' . $bn . '[countries]" value="' . esc_attr($feedCountries) . '" /><p class="description">Comma-separated.</p></td></tr>';
                    echo '<tr><th>Currency</th><td><input type="text" class="small-text" name="' . $bn . '[currency]" value="' . esc_attr($feedCurrency) . '" /></td></tr>';
                    echo '<tr><th>Feed URL</th><td><input type="url" class="large-text" name="' . $bn . '[url]" value="' . esc_attr($feedUrl) . '" /></td></tr>';
                    echo '</tbody></table></details>';
                }
            }
        }

        // ── Geo / Localization ───────────────────────────────────
        if ($activeTab === 'geo') {
            $this->renderSettingsSection('Country Detection', 'How the visitor country is determined for localized pricing and content.', [
                ['key' => 'mode', 'option' => $O_GE, 'label' => 'Detection Mode', 'desc' => 'Auto uses CloudFlare headers and cookies. Force overrides to a fixed country (useful for debugging).', 'type' => 'select', 'choices' => ['auto' => 'Auto (CloudFlare / Cookie)', 'force' => 'Force (Debug)'], 'prefix' => 'geo_'],
                ['key' => 'force_country', 'option' => $O_GE, 'label' => 'Force Country Code', 'desc' => '2-letter ISO code (e.g. US, JP, DE). Only applies in Force mode.', 'type' => 'text', 'prefix' => 'geo_'],
            ], $geo);

            // Supported countries JSON
            $mapData = isset($geo['supported_countries']) ? $geo['supported_countries'] : [];
            $mapJson = empty($mapData) ? '' : (string) wp_json_encode($mapData, JSON_PRETTY_PRINT);
            echo '<h2>Supported Countries Map</h2>';
            echo '<p class="description" style="margin:-8px 0 16px;">Override the built-in country → region/currency map. Leave empty to use system defaults.</p>';
            echo '<table class="form-table"><tbody>';
            echo '<tr><th><label for="geo_supported_countries">JSON Map</label></th><td>';
            echo '<textarea id="geo_supported_countries" name="' . esc_attr($O_GE) . '[supported_countries]" rows="10" class="large-text code">' . esc_textarea($mapJson) . '</textarea>';
            echo '<p class="description">Format: <code>{"US": {"region": "NA", "currency": "USD"}, ...}</code></p></td></tr>';
            echo '</tbody></table>';
        }

        // ── Revenue & Affiliates ─────────────────────────────────
        if ($activeTab === 'revenue') {
            $this->renderSettingsSection('Redirect Tracking', 'Affiliate link redirection settings.', [
                ['key' => 'enable_redirect_tracking', 'option' => $O_R, 'label' => 'Enable Redirect Tracking', 'desc' => 'Track outbound clicks through /go/ redirects.', 'type' => 'checkbox', 'prefix' => 'rev_'],
                ['key' => 'default_affiliate_network', 'option' => $O_R, 'label' => 'Default Network', 'desc' => 'Fallback affiliate network for new helmets (amazon, cj, allegro, jumia).', 'type' => 'text', 'prefix' => 'rev_'],
                ['key' => 'amazon_tag', 'option' => $O_R, 'label' => 'Amazon Affiliate Tag', 'desc' => 'e.g. helmetsan-20', 'type' => 'text', 'prefix' => 'rev_'],
                ['key' => 'redirect_status_code', 'option' => $O_R, 'label' => 'Redirect HTTP Code', 'desc' => '302 (temporary) or 301/307/308.', 'type' => 'select', 'choices' => ['301' => '301 Permanent', '302' => '302 Found (default)', '307' => '307 Temp Redirect', '308' => '308 Perm Redirect'], 'prefix' => 'rev_'],
            ], $revenue);

            // Per-network CPC
            $networkCpc = isset($revenue['network_cpc']) && is_array($revenue['network_cpc']) ? $revenue['network_cpc'] : [];
            echo '<h2>Per-Network CPC Estimates</h2>';
            echo '<p class="description" style="margin:-8px 0 16px;">Average cost-per-click used for revenue estimation on the dashboard.</p>';
            echo '<table class="form-table"><tbody>';
            foreach (['amazon' => 'Amazon', 'cj' => 'CJ', 'allegro' => 'Allegro', 'jumia' => 'Jumia'] as $netKey => $netLabel) {
                $cpcVal = isset($networkCpc[$netKey]) ? (string) $networkCpc[$netKey] : '0.04';
                echo '<tr><th><label for="rev_cpc_' . esc_attr($netKey) . '">' . esc_html($netLabel) . ' CPC ($)</label></th><td>';
                echo '<input type="number" step="0.01" min="0" class="small-text" id="rev_cpc_' . esc_attr($netKey) . '" name="' . esc_attr($O_R) . '[network_cpc][' . esc_attr($netKey) . ']" value="' . esc_attr($cpcVal) . '" />';
                echo '</td></tr>';
            }
            echo '</tbody></table>';
        }

        // ── Scheduler ────────────────────────────────────────────
        if ($activeTab === 'scheduler') {
            $this->renderSettingsSection('Master Switch', 'Enable or disable all scheduled tasks.', [
                ['key' => 'enable_scheduler', 'option' => $O_S, 'label' => 'Enable Scheduler', 'desc' => 'Master switch for all WP-Cron background jobs.', 'type' => 'checkbox', 'prefix' => 'sch_'],
            ], $scheduler);

            $this->renderSettingsSection('GitHub Sync Schedule', 'Periodic pull from GitHub repository.', [
                ['key' => 'sync_pull_enabled', 'option' => $O_S, 'label' => 'Enable Sync Pull', 'desc' => 'Periodically pull data from GitHub.', 'type' => 'checkbox', 'prefix' => 'sch_'],
                ['key' => 'sync_pull_interval_hours', 'option' => $O_S, 'label' => 'Interval (hours)', 'desc' => 'How often to pull.', 'type' => 'number', 'prefix' => 'sch_'],
                ['key' => 'sync_pull_limit', 'option' => $O_S, 'label' => 'File Limit', 'desc' => 'Max files per pull.', 'type' => 'number', 'prefix' => 'sch_'],
                ['key' => 'sync_pull_apply_brands', 'option' => $O_S, 'label' => 'Apply Brands', 'desc' => 'Ingest brand entities after pull.', 'type' => 'checkbox', 'prefix' => 'sch_'],
                ['key' => 'sync_pull_apply_helmets', 'option' => $O_S, 'label' => 'Apply Helmets', 'desc' => 'Ingest helmet entities after pull.', 'type' => 'checkbox', 'prefix' => 'sch_'],
            ], $scheduler);

            $this->renderSettingsSection('Retry & Cleanup', 'Automatic retry of failed ingestions and log cleanup.', [
                ['key' => 'retry_failed_enabled', 'option' => $O_S, 'label' => 'Retry Failed Jobs', 'desc' => 'Re-attempt failed ingestion entries.', 'type' => 'checkbox', 'prefix' => 'sch_'],
                ['key' => 'retry_failed_limit', 'option' => $O_S, 'label' => 'Retry Limit', 'desc' => 'Max entries to retry per run.', 'type' => 'number', 'prefix' => 'sch_'],
                ['key' => 'retry_failed_batch_size', 'option' => $O_S, 'label' => 'Retry Batch Size', 'desc' => 'Entries processed per batch.', 'type' => 'number', 'prefix' => 'sch_'],
                ['key' => 'cleanup_logs_enabled', 'option' => $O_S, 'label' => 'Cleanup Old Logs', 'desc' => 'Delete ingestion/sync logs older than the retention period.', 'type' => 'checkbox', 'prefix' => 'sch_'],
                ['key' => 'cleanup_logs_days', 'option' => $O_S, 'label' => 'Retention (days)', 'desc' => 'Logs older than this are deleted.', 'type' => 'number', 'prefix' => 'sch_'],
                ['key' => 'health_snapshot_enabled', 'option' => $O_S, 'label' => 'Health Snapshots', 'desc' => 'Take periodic health check snapshots.', 'type' => 'checkbox', 'prefix' => 'sch_'],
                ['key' => 'ingestion_interval_hours', 'option' => $O_S, 'label' => 'Ingestion Interval (hours)', 'desc' => 'How often marketplace price ingestion runs.', 'type' => 'number', 'prefix' => 'sch_'],
            ], $scheduler);

            $status = $this->scheduler->status();
            
            echo '<h2>Scheduler Control Center</h2>';
            if (isset($_GET['task_run'])) {
                echo '<div class="notice notice-success is-dismissible" style="margin-left:0;margin-top:10px;"><p>Task <strong>' . esc_html(sanitize_text_field((string)$_GET['task_run'])) . '</strong> executed manually via Control Center.</p></div>';
            }
            
            echo '<table class="widefat striped" style="margin-bottom: 20px;">';
            echo '<thead><tr><th>Task</th><th>Hook</th><th>Next Run</th><th>Actions</th></tr></thead>';
            echo '<tbody>';
            $nextRuns = $status['next_runs'] ?? [];
            foreach ($nextRuns as $taskKey => $timestamp) {
                // Determine hook based on task key convention in SchedulerService
                $hookName = 'helmetsan_cron_' . $taskKey;
                $nextRunStr = $timestamp ? wp_date('Y-m-d H:i:s', $timestamp) : 'Not Scheduled';
                $nonceUrl = wp_nonce_url(admin_url('admin-post.php?action=helmetsan_scheduler_task&task=' . $taskKey), 'helmetsan_scheduler_task');
                
                echo '<tr>';
                echo '<td><strong>' . esc_html(ucwords(str_replace('_', ' ', (string)$taskKey))) . '</strong></td>';
                echo '<td><code>' . esc_html($hookName) . '</code></td>';
                echo '<td>' . esc_html($nextRunStr) . '</td>';
                echo '<td><a href="' . esc_url($nonceUrl) . '" class="button button-small">Run Now</a></td>';
                echo '</tr>';
            }
            echo '</tbody>';
            echo '</table>';

            echo '<h3 style="margin-top:20px;">Raw Config Snapshot</h3><pre style="background:#f5f5f5;padding:12px;border-radius:4px;max-height:300px;overflow:auto;">' . esc_html((string) wp_json_encode($status, JSON_PRETTY_PRINT)) . '</pre>';
        }

        // ── Alerts ───────────────────────────────────────────────
        if ($activeTab === 'alerts') {
            $this->renderSettingsSection('Alert System', 'Get notified when sync errors, ingestion failures, or health warnings occur.', [
                ['key' => 'enabled', 'option' => $O_AL, 'label' => 'Enable Alerts', 'desc' => 'Master switch for the alert system.', 'type' => 'checkbox', 'prefix' => 'alt_'],
            ], $alerts);

            $this->renderSettingsSection('Email Notifications', 'Send alerts via email.', [
                ['key' => 'email_enabled', 'option' => $O_AL, 'label' => 'Enable Email Alerts', 'desc' => '', 'type' => 'checkbox', 'prefix' => 'alt_'],
                ['key' => 'to_email', 'option' => $O_AL, 'label' => 'Recipient Email', 'desc' => 'Leave empty to use the admin email. Can be overridden via HELMETSAN_ALERTS_TO_EMAIL constant.', 'type' => 'text', 'prefix' => 'alt_'],
                ['key' => 'subject_prefix', 'option' => $O_AL, 'label' => 'Subject Prefix', 'desc' => 'Prepended to all alert email subjects.', 'type' => 'text', 'prefix' => 'alt_'],
            ], $alerts);

            $this->renderSettingsSection('Slack Notifications', 'Post alerts to a Slack channel.', [
                ['key' => 'slack_enabled', 'option' => $O_AL, 'label' => 'Enable Slack', 'desc' => '', 'type' => 'checkbox', 'prefix' => 'alt_'],
                ['key' => 'slack_webhook_url', 'option' => $O_AL, 'label' => 'Webhook URL', 'desc' => 'Incoming webhook URL from Slack app settings.', 'type' => 'url', 'prefix' => 'alt_'],
            ], $alerts);

            $this->renderSettingsSection('Alert Triggers', 'Choose which events generate alerts.', [
                ['key' => 'alert_on_sync_error', 'option' => $O_AL, 'label' => 'Sync Errors', 'desc' => 'Alert when GitHub sync fails.', 'type' => 'checkbox', 'prefix' => 'alt_'],
                ['key' => 'alert_on_ingest_error', 'option' => $O_AL, 'label' => 'Ingestion Errors', 'desc' => 'Alert when data ingestion fails.', 'type' => 'checkbox', 'prefix' => 'alt_'],
                ['key' => 'alert_on_health_warning', 'option' => $O_AL, 'label' => 'Health Warnings', 'desc' => 'Alert when repo health degrades.', 'type' => 'checkbox', 'prefix' => 'alt_'],
            ], $alerts);
        }

        // ── Media Engine ─────────────────────────────────────────
        if ($activeTab === 'media') {
            // Show test results if any
            $testProvider = isset($_GET['media_test_provider']) ? sanitize_key((string) $_GET['media_test_provider']) : '';
            $testStatus = isset($_GET['media_test_status']) ? sanitize_key((string) $_GET['media_test_status']) : '';
            $testDetails = isset($_GET['media_test_details']) ? sanitize_text_field(rawurldecode((string) $_GET['media_test_details'])) : '';
            if ($testProvider !== '' && $testStatus !== '') {
                $noticeClass = $testStatus === 'ok' ? 'notice-success' : 'notice-warning';
                echo '<div class="notice ' . esc_attr($noticeClass) . '"><p><strong>Media API Test (' . esc_html($testProvider) . '):</strong> ' . esc_html($testStatus);
                if ($testDetails !== '') {
                    echo ' — ' . esc_html($testDetails);
                }
                echo '</p></div>';
            }

            $this->renderSettingsSection('Media Engine', 'Automatic brand logo and image fetching from multiple providers.', [
                ['key' => 'enable_media_engine', 'option' => $O_ME, 'label' => 'Enable Media Engine', 'desc' => 'Master switch for all media providers.', 'type' => 'checkbox', 'prefix' => 'med_'],
                ['key' => 'auto_sideload_enabled', 'option' => $O_ME, 'label' => 'Auto Sideload', 'desc' => 'Automatically download remote images into the WP Media Library.', 'type' => 'checkbox', 'prefix' => 'med_'],
                ['key' => 'cache_ttl_hours', 'option' => $O_ME, 'label' => 'Cache TTL (hours)', 'desc' => 'How long fetched logos are cached before refresh.', 'type' => 'number', 'prefix' => 'med_'],
            ], $media);

            $this->renderSettingsSection('Providers', 'Toggle individual logo providers and enter API keys.', [
                ['key' => 'simpleicons_enabled', 'option' => $O_ME, 'label' => 'Simple Icons', 'desc' => 'Free SVG brand icons (no key needed).', 'type' => 'checkbox', 'prefix' => 'med_'],
                ['key' => 'wikimedia_enabled', 'option' => $O_ME, 'label' => 'Wikimedia Commons', 'desc' => 'Free logo images (no key needed).', 'type' => 'checkbox', 'prefix' => 'med_'],
                ['key' => 'brandfetch_enabled', 'option' => $O_ME, 'label' => 'Brandfetch', 'desc' => 'High-quality brand assets. Requires API token.', 'type' => 'checkbox', 'prefix' => 'med_'],
                ['key' => 'brandfetch_token', 'option' => $O_ME, 'label' => 'Brandfetch Token', 'desc' => '', 'type' => 'password', 'prefix' => 'med_'],
                ['key' => 'logodev_enabled', 'option' => $O_ME, 'label' => 'Logo.dev', 'desc' => 'Logo API. Requires publishable + secret key.', 'type' => 'checkbox', 'prefix' => 'med_'],
                ['key' => 'logodev_publishable_key', 'option' => $O_ME, 'label' => 'Logo.dev Publishable Key', 'desc' => '', 'type' => 'password', 'prefix' => 'med_'],
                ['key' => 'logodev_secret_key', 'option' => $O_ME, 'label' => 'Logo.dev Secret Key', 'desc' => '', 'type' => 'password', 'prefix' => 'med_'],
                ['key' => 'logodev_token', 'option' => $O_ME, 'label' => 'Logo.dev Legacy Token', 'desc' => 'Backward-compatible single token. Use publishable/secret keys instead.', 'type' => 'password', 'prefix' => 'med_'],
            ], $media);

            echo '<p><strong>Connectivity Tests</strong></p>';
            $testNonce = wp_create_nonce('helmetsan_media_api_test');
            $brandfetchTestUrl = add_query_arg(['action' => 'helmetsan_media_api_test', 'provider' => 'brandfetch', '_wpnonce' => $testNonce], admin_url('admin-post.php'));
            $logodevTestUrl = add_query_arg(['action' => 'helmetsan_media_api_test', 'provider' => 'logodev', '_wpnonce' => $testNonce], admin_url('admin-post.php'));
            echo '<p style="display:flex;gap:8px;flex-wrap:wrap;margin:8px 0 16px;">';
            echo '<a class="button button-secondary" href="' . esc_url($brandfetchTestUrl) . '">Test Brandfetch API</a>';
            echo '<a class="button button-secondary" href="' . esc_url($logodevTestUrl) . '">Test Logo.dev API</a>';
            echo '</p>';
        }

        // ── WooBridge ────────────────────────────────────────────
        if ($activeTab === 'woobridge') {
            $this->renderSettingsSection('WooCommerce Bridge', 'Sync helmet data to WooCommerce products.', [
                ['key' => 'enable_bridge', 'option' => $O_W, 'label' => 'Enable Bridge', 'desc' => 'Master switch. Requires WooCommerce to be active.', 'type' => 'checkbox', 'prefix' => 'wb_'],
                ['key' => 'auto_sync_on_save', 'option' => $O_W, 'label' => 'Auto-Sync on Save', 'desc' => 'Automatically push to WooCommerce when a helmet is saved.', 'type' => 'checkbox', 'prefix' => 'wb_'],
                ['key' => 'publish_products', 'option' => $O_W, 'label' => 'Publish Products', 'desc' => 'Set synced products to "published" (otherwise draft).', 'type' => 'checkbox', 'prefix' => 'wb_'],
                ['key' => 'default_currency', 'option' => $O_W, 'label' => 'Default Currency', 'desc' => '3-letter ISO code for product prices (e.g. USD, EUR).', 'type' => 'text', 'prefix' => 'wb_'],
                ['key' => 'sync_limit_default', 'option' => $O_W, 'label' => 'Sync Batch Limit', 'desc' => 'Max helmets to sync in a single batch run.', 'type' => 'number', 'prefix' => 'wb_'],
            ], $wooBridge);
        }

        // ── Features ─────────────────────────────────────────────
        if ($activeTab === 'features') {
            $this->renderSettingsSection('Beta Features & Toggles', 'Enable experimental or upcoming features.', [
                ['key' => 'enable_technical_analysis', 'option' => $O_F, 'label' => 'Technical Analysis', 'desc' => 'Show technical analysis section on single helmet pages.', 'type' => 'checkbox', 'prefix' => 'feat_'],
                ['key' => 'enable_ai_chatbot', 'option' => $O_F, 'label' => 'AI Selection Chatbot', 'desc' => 'Enable AI chatbot widget on the frontend.', 'type' => 'checkbox', 'prefix' => 'feat_'],
            ], $features);
        }

        submit_button('Save Settings');
        echo '</form></div>';

    }
}
