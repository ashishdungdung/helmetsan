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
        private readonly BrandService $brands
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
    }

    public function registerMenu(): void
    {
        add_menu_page('Helmetsan', 'Helmetsan', 'manage_options', 'helmetsan-dashboard', [$this, 'dashboardPage'], 'dashicons-admin-site', 2);

        add_submenu_page('helmetsan-dashboard', 'Catalog', 'Catalog', 'manage_options', 'helmetsan-catalog', [$this, 'catalogPage']);
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
            'helmetsan-brands' => 'Brands',
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

    public function dashboardPage(): void
    {
        $report = $this->health->report();
        $goLive = $this->checklist->report();
        $syncRows = $this->syncLogs->tableExists() ? $this->syncLogs->fetch(1, 5, 'all', 'all', '') : [];
        $repoStatus = (string) ($report['status'] ?? 'unknown');

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

        echo '<div class="wrap helmetsan-wrap">';
        $this->renderAppHeader('Catalog', 'Browse helmets and entity directories.');
        $this->renderMetricCards([
            ['label' => 'Total Helmets', 'value' => (string) $totalHelmets, 'page' => 'helmetsan-catalog'],
            ['label' => 'Brand Linked', 'value' => (string) $brandLinked, 'page' => 'helmetsan-catalog'],
            ['label' => 'Certification Linked', 'value' => (string) $certLinked, 'page' => 'helmetsan-catalog'],
            ['label' => 'Brands Directory', 'value' => (string) (isset(wp_count_posts('brand')->publish) ? (int) wp_count_posts('brand')->publish : 0), 'page' => 'helmetsan-brands'],
        ]);

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
        echo '</tbody></table></div>';
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

    public function settingsPage(): void
    {
        $values = wp_parse_args((array) get_option(Config::OPTION_ANALYTICS, []), $this->config->analyticsDefaults());
        $github = wp_parse_args((array) get_option(Config::OPTION_GITHUB, []), $this->config->githubDefaults());
        $revenue = wp_parse_args((array) get_option(Config::OPTION_REVENUE, []), $this->config->revenueDefaults());
        $scheduler = wp_parse_args((array) get_option(Config::OPTION_SCHEDULER, []), $this->config->schedulerDefaults());
        $alerts = wp_parse_args((array) get_option(Config::OPTION_ALERTS, []), $this->config->alertsDefaults());

        echo '<div class="wrap helmetsan-wrap">';
        $this->renderAppHeader('Settings', 'Runtime configuration for sync, analytics, scheduler, and alerts.');
        echo '<form method="post" action="options.php">';
        settings_fields('helmetsan_settings');

        echo '<h2>Analytics</h2>';
        echo '<table class="form-table"><tbody>';

        foreach ($values as $key => $current) {
            echo '<tr><th><label for="' . esc_attr($key) . '">' . esc_html($key) . '</label></th><td>';

            if (is_bool($current)) {
                echo '<input type="checkbox" id="' . esc_attr($key) . '" name="' . esc_attr(Config::OPTION_ANALYTICS) . '[' . esc_attr($key) . ']" value="1" ' . checked((bool) $current, true, false) . ' />';
            } else {
                echo '<input type="text" class="regular-text" id="' . esc_attr($key) . '" name="' . esc_attr(Config::OPTION_ANALYTICS) . '[' . esc_attr($key) . ']" value="' . esc_attr((string) $current) . '" />';
            }

            echo '</td></tr>';
        }

        echo '</tbody></table>';
        echo '<h2>GitHub Sync</h2>';
        echo '<table class="form-table"><tbody>';

        foreach ($github as $key => $current) {
            echo '<tr><th><label for="gh_' . esc_attr($key) . '">' . esc_html($key) . '</label></th><td>';
            if (is_bool($current)) {
                echo '<input type="checkbox" id="gh_' . esc_attr($key) . '" name="' . esc_attr(Config::OPTION_GITHUB) . '[' . esc_attr($key) . ']" value="1" ' . checked((bool) $current, true, false) . ' />';
            } elseif ($key === 'sync_run_profile') {
                $selected = (string) $current;
                echo '<select id="gh_' . esc_attr($key) . '" name="' . esc_attr(Config::OPTION_GITHUB) . '[' . esc_attr($key) . ']">';
                foreach (['pull-only', 'pull+brands', 'pull+all'] as $profile) {
                    echo '<option value="' . esc_attr($profile) . '" ' . selected($selected, $profile, false) . '>' . esc_html($profile) . '</option>';
                }
                echo '</select>';
            } else {
                $type = $key === 'token' ? 'password' : 'text';
                echo '<input type="' . esc_attr($type) . '" class="regular-text" id="gh_' . esc_attr($key) . '" name="' . esc_attr(Config::OPTION_GITHUB) . '[' . esc_attr($key) . ']" value="' . esc_attr((string) $current) . '" />';
            }
            echo '</td></tr>';
        }

        echo '</tbody></table>';
        echo '<h2>Revenue</h2>';
        echo '<table class="form-table"><tbody>';
        foreach ($revenue as $key => $current) {
            echo '<tr><th><label for="rev_' . esc_attr($key) . '">' . esc_html($key) . '</label></th><td>';
            if (is_bool($current)) {
                echo '<input type="checkbox" id="rev_' . esc_attr($key) . '" name="' . esc_attr(Config::OPTION_REVENUE) . '[' . esc_attr($key) . ']" value="1" ' . checked((bool) $current, true, false) . ' />';
            } else {
                echo '<input type="text" class="regular-text" id="rev_' . esc_attr($key) . '" name="' . esc_attr(Config::OPTION_REVENUE) . '[' . esc_attr($key) . ']" value="' . esc_attr((string) $current) . '" />';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';
        echo '<h2>Scheduler</h2>';
        echo '<table class="form-table"><tbody>';
        foreach ($scheduler as $key => $current) {
            echo '<tr><th><label for="sch_' . esc_attr($key) . '">' . esc_html($key) . '</label></th><td>';
            if (is_bool($current)) {
                echo '<input type="checkbox" id="sch_' . esc_attr($key) . '" name="' . esc_attr(Config::OPTION_SCHEDULER) . '[' . esc_attr($key) . ']" value="1" ' . checked((bool) $current, true, false) . ' />';
            } else {
                echo '<input type="number" class="small-text" id="sch_' . esc_attr($key) . '" name="' . esc_attr(Config::OPTION_SCHEDULER) . '[' . esc_attr($key) . ']" value="' . esc_attr((string) $current) . '" min="1" />';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';

        $status = $this->scheduler->status();
        echo '<h3>Scheduler Status</h3><pre>' . esc_html(wp_json_encode($status, JSON_PRETTY_PRINT)) . '</pre>';
        echo '<h2>Alerts</h2>';
        echo '<table class="form-table"><tbody>';
        foreach ($alerts as $key => $current) {
            echo '<tr><th><label for="alt_' . esc_attr($key) . '">' . esc_html($key) . '</label></th><td>';
            if (is_bool($current)) {
                echo '<input type="checkbox" id="alt_' . esc_attr($key) . '" name="' . esc_attr(Config::OPTION_ALERTS) . '[' . esc_attr($key) . ']" value="1" ' . checked((bool) $current, true, false) . ' />';
            } else {
                $type = ($key === 'slack_webhook_url') ? 'url' : 'text';
                echo '<input type="' . esc_attr($type) . '" class="regular-text" id="alt_' . esc_attr($key) . '" name="' . esc_attr(Config::OPTION_ALERTS) . '[' . esc_attr($key) . ']" value="' . esc_attr((string) $current) . '" />';
            }
            echo '</td></tr>';
        }
        echo '</tbody></table>';
        submit_button('Save Settings');
        echo '</form></div>';
    }
}
