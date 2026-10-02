<?php

declare(strict_types=1);

namespace Helmetsan\Core\Admin;

use Helmetsan\Core\AI\AiService;
use Helmetsan\Core\Media\HelmetImageEnrichmentService;
use Helmetsan\Core\Media\HelmetImageManager;

/**
 * Admin UI for Helmetsan 5-Shot Studio Gallery & Image Enrichment
 */
final class HelmetImagesAdmin
{
    private const RESULT_TRANSIENT = 'helmetsan_helmet_images_result';
    private const RESULT_TTL       = 3600;

    public function __construct(
        private readonly HelmetImageEnrichmentService $enrichment,
        private readonly AiService $aiService,
        private readonly ?HelmetImageManager $imageManager = null
    ) {
    }

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu'], 16);
        add_action('admin_post_helmetsan_helmet_images_run', [$this, 'handleRun']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueStyles']);
    }

    public function addMenu(): void
    {
        add_submenu_page(
            'helmetsan-dashboard',
            __('Helmet images', 'helmetsan-core'),
            __('Helmet images', 'helmetsan-core'),
            'manage_options',
            'helmetsan-helmet-images',
            [$this, 'renderPage']
        );
    }

    public function enqueueStyles(string $hook): void
    {
        if (!str_contains($hook, 'helmetsan-helmet-images')) {
            return;
        }

        $cssUrl = plugins_url('assets/css/helmet-studio-admin.css', dirname(__DIR__));
        $jsUrl = plugins_url('assets/js/helmet-studio-admin.js', dirname(__DIR__));

        wp_enqueue_style('helmetsan-studio-admin-css', $cssUrl, [], defined('HELMETSAN_CORE_VERSION') ? HELMETSAN_CORE_VERSION : '1.0.0');
        wp_enqueue_script('helmetsan-studio-admin-js', $jsUrl, [], defined('HELMETSAN_CORE_VERSION') ? HELMETSAN_CORE_VERSION : '1.0.0', true);

        wp_localize_script('helmetsan-studio-admin-js', 'HELMETSAN_STUDIO', [
            'restUrl' => esc_url_raw(rest_url('helmetsan/v1')),
            'nonce' => wp_create_nonce('wp_rest'),
            'canonicalShots' => HelmetImageManager::CANONICAL_SHOTS,
        ]);
    }

    public function handleRun(): void
    {
        if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'helmetsan_helmet_images_run')) {
            wp_die(esc_html__('Security check failed.', 'helmetsan-core'));
        }
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission.', 'helmetsan-core'));
        }

        $limit          = isset($_POST['limit']) ? max(0, (int) $_POST['limit']) : 50;
        $onlyMissing    = ! isset($_POST['all_helmets']);
        $useAi          = isset($_POST['use_ai']);
        $useRevZilla    = isset($_POST['use_revzilla']);
        $useEan         = isset($_POST['use_ean']);
        $dryRun         = isset($_POST['dry_run']);

        $stats = $this->enrichment->run(
            $limit,
            $onlyMissing,
            $useAi,
            $dryRun,
            null,
            $useEan,
            $useRevZilla,
            $useAi
        );

        set_transient(self::RESULT_TRANSIENT, array_merge($stats, [
            'dry_run' => $dryRun,
            'limit'   => $limit,
            'use_ai'  => $useAi,
            'use_revzilla' => $useRevZilla,
            'use_ean' => $useEan,
        ]), self::RESULT_TTL);

        wp_safe_redirect(admin_url('admin.php?page=helmetsan-helmet-images&tab=importer&done=1'));
        exit;
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission.', 'helmetsan-core'));
        }

        $currentTab = sanitize_key($_GET['tab'] ?? 'studio');

        echo '<div class="wrap helmetsan-wrap helmetsan-helmet-images-wrap">';
        
        // Studio Header
        echo '<div class="hs-studio-header">';
        echo '<div>';
        echo '<h1>' . esc_html__('Helmetsan Helmet Image Studio & Gallery', 'helmetsan-core') . '</h1>';
        echo '<p>' . esc_html__('Multi-model photography suite powered by NVIDIA NIM FLUX.1 (Photoreal generation) and Moonshot AI Kimi-K3 (Visual quality and safety inspection) with 64-bit pHash deduplication.', 'helmetsan-core') . '</p>';
        echo '</div>';
        echo '</div>';

        // Tab Navigation
        echo '<div class="hs-tabs-nav">';
        $studioClass = $currentTab === 'studio' ? 'hs-tab-link active' : 'hs-tab-link';
        $importerClass = $currentTab === 'importer' ? 'hs-tab-link active' : 'hs-tab-link';
        echo '<a href="' . esc_url(admin_url('admin.php?page=helmetsan-helmet-images&tab=studio')) . '" class="' . esc_attr($studioClass) . '">' . esc_html__('📸 5-Shot Studio Cockpit', 'helmetsan-core') . '</a>';
        echo '<a href="' . esc_url(admin_url('admin.php?page=helmetsan-helmet-images&tab=importer')) . '" class="' . esc_attr($importerClass) . '">' . esc_html__('🌐 External Importer (RevZilla / EAN)', 'helmetsan-core') . '</a>';
        echo '</div>';

        if ($currentTab === 'studio') {
            $this->renderStudioTab();
        } else {
            $this->renderImporterTab();
        }

        echo '</div>';
    }

    private function renderStudioTab(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . HelmetImageManager::TABLE_NAME;

        $totalHelmets = (int) wp_count_posts('helmet')->publish;
        $totalImages = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");
        $verifiedCount = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE validation_status IN ('approved', 'published', 'verified', 'audit_passed')");

        echo '<div class="hs-stat-grid">';
        echo '<div class="hs-stat-card"><div class="desc">' . esc_html__('Total Helmets', 'helmetsan-core') . '</div><div class="val">' . esc_html((string) $totalHelmets) . '</div></div>';
        echo '<div class="hs-stat-card"><div class="desc">' . esc_html__('Target 5-Shots', 'helmetsan-core') . '</div><div class="val">' . esc_html((string) ($totalHelmets * 5)) . '</div></div>';
        echo '<div class="hs-stat-card"><div class="desc">' . esc_html__('Generated Images', 'helmetsan-core') . '</div><div class="val">' . esc_html((string) $totalImages) . '</div></div>';
        echo '<div class="hs-stat-card"><div class="desc">' . esc_html__('Kimi-K3 Verified', 'helmetsan-core') . '</div><div class="val" style="color:#16a34a;">' . esc_html((string) $verifiedCount) . '</div></div>';
        echo '</div>';

        echo '<div class="hs-priority-banner">';
        echo '<strong>' . esc_html__('Canonical 5-Shot Photography Protocol (FLUX.1 + Moonshot AI Kimi-K3)', 'helmetsan-core') . '</strong><br />';
        echo esc_html__('Every catalog helmet requires 5 standardized technical angles: 1) 3/4 Front Isometric Hero, 2) Lateral Side Profile, 3) Rear Exhaust & Diffuser, 4) Macro Interior & Retention System, 5) Motorcycle Cockpit Pairing. All images are rendered at 1024px, optimized into responsive WebP (1920 hero, 1024 gallery, 480 thumb), deduplicated via 64-bit difference pHash, and visually audited by Kimi-K3.', 'helmetsan-core');
        echo '</div>';

        // Table Controls: Filter buttons & search box
        echo '<div class="hs-table-controls">';
        echo '<div class="hs-filter-group">';
        echo '<button type="button" class="hs-filter-btn active" data-filter="all">' . esc_html__('All Helmets', 'helmetsan-core') . '</button>';
        echo '<button type="button" class="hs-filter-btn" data-filter="incomplete">' . esc_html__('⚠️ Missing Shots (< 5)', 'helmetsan-core') . '</button>';
        echo '<button type="button" class="hs-filter-btn" data-filter="complete">' . esc_html__('✓ Complete 5-Shot', 'helmetsan-core') . '</button>';
        echo '<button type="button" class="hs-filter-btn" data-filter="audited">' . esc_html__('🔍 Kimi Audited', 'helmetsan-core') . '</button>';
        echo '</div>';
        echo '<div>';
        echo '<input type="text" id="hs-search-helmets" class="hs-search-box" placeholder="' . esc_attr__('Search helmet model or ID...', 'helmetsan-core') . '" />';
        echo '</div>';
        echo '</div>';

        // Query catalog helmets
        $helmets = get_posts([
            'post_type' => 'helmet',
            'posts_per_page' => 25,
            'post_status' => 'publish',
        ]);

        echo '<div class="hs-studio-table">';
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead><tr>';
        echo '<th style="width:260px;">' . esc_html__('Helmet Model', 'helmetsan-core') . '</th>';
        echo '<th>' . esc_html__('5-Shot Studio Coverage', 'helmetsan-core') . '</th>';
        echo '<th style="width:90px;text-align:center;">' . esc_html__('Primary', 'helmetsan-core') . '</th>';
        echo '<th style="width:130px;">' . esc_html__('Audit Status', 'helmetsan-core') . '</th>';
        echo '<th style="width:170px;">' . esc_html__('Actions', 'helmetsan-core') . '</th>';
        echo '</tr></thead><tbody>';

        if (empty($helmets)) {
            echo '<tr><td colspan="5">' . esc_html__('No helmets found in catalog.', 'helmetsan-core') . '</td></tr>';
        } else {
            foreach ($helmets as $h) {
                $helmetId = (string) $h->ID;
                $coverage = $this->imageManager ? $this->imageManager->getCoverageForHelmet($helmetId) : ['approved' => 0, 'missing' => array_keys(HelmetImageManager::CANONICAL_SHOTS)];
                $heroThumb = get_the_post_thumbnail_url($h->ID, 'thumbnail') ?: '';
                $approvedCount = $coverage['approved'] ?? 0;
                $isAudited = $approvedCount > 0;

                // Extract brand
                $brandTerms = get_the_terms($h->ID, 'helmet_brand');
                $brandName = (!empty($brandTerms) && is_array($brandTerms)) ? $brandTerms[0]->name : 'Helmetsan';
                $shellMaterial = (string) get_post_meta($h->ID, 'spec_shell_material', true);

                echo '<tr class="hs-helmet-row" data-id="' . esc_attr($helmetId) . '" data-title="' . esc_attr($h->post_title) . '" data-approved="' . esc_attr((string) $approvedCount) . '" data-audited="' . ($isAudited ? 'true' : 'false') . '">';
                echo '<td><strong><a href="' . esc_url(get_edit_post_link($h->ID)) . '">' . esc_html($h->post_title) . '</a></strong><br /><span class="description">' . esc_html($brandName) . ' • ID: ' . esc_html($helmetId) . '</span></td>';
                
                // 5 Shots display
                echo '<td>';
                $shotTypes = [
                    HelmetImageManager::SHOT_FRONT_HERO => 'Front Hero',
                    HelmetImageManager::SHOT_SIDE_PROFILE => 'Side Profile',
                    HelmetImageManager::SHOT_REAR_EXHAUST => 'Rear Exhaust',
                    HelmetImageManager::SHOT_INTERIOR_MACRO => 'Interior Macro',
                    HelmetImageManager::SHOT_COCKPIT_CONTEXT => 'Cockpit Context',
                ];
                foreach ($shotTypes as $st => $label) {
                    $isDone = !in_array($st, $coverage['missing'] ?? [], true);
                    $pillClass = $isDone ? 'hs-shot-pill hs-pill-green' : 'hs-shot-pill hs-pill-gray';
                    echo '<span class="' . esc_attr($pillClass) . '">' . ($isDone ? '✓ ' : '') . esc_html($label) . '</span>';
                }
                echo '</td>';

                // Primary Thumbnail
                echo '<td style="text-align:center;">';
                if ($heroThumb) {
                    echo '<img src="' . esc_url($heroThumb) . '" style="width:42px;height:42px;object-fit:cover;border-radius:6px;border:1px solid #cbd5e1;" />';
                } else {
                    echo '<span class="description" style="font-size:0.75rem;">None</span>';
                }
                echo '</td>';

                // Audit Status
                echo '<td>';
                if ($approvedCount === 5) {
                    echo '<span style="color:#16a34a;font-weight:700;">✓ 5/5 Verified</span>';
                } else {
                    echo '<span style="color:#d97706;font-weight:600;">' . esc_html((string) $approvedCount) . '/5 Shots</span>';
                }
                echo '</td>';

                // Actions
                echo '<td>';
                echo '<button type="button" class="button button-small button-primary hs-open-studio-btn" data-helmet-id="' . esc_attr($helmetId) . '" data-title="' . esc_attr($h->post_title) . '" data-brand="' . esc_attr($brandName) . '" data-shell="' . esc_attr($shellMaterial) . '">';
                echo '📸 ' . esc_html__('Open Studio', 'helmetsan-core');
                echo '</button> ';
                echo '<a href="' . esc_url(get_edit_post_link($h->ID)) . '" class="button button-small">' . esc_html__('Edit Post', 'helmetsan-core') . '</a>';
                echo '</td>';
                echo '</tr>';
            }
        }

        echo '</tbody></table></div>';

        // -------------------------------------------------------------
        // Studio Cockpit Modal Markup
        // -------------------------------------------------------------
        echo '<div id="hs-studio-modal" class="hs-modal-backdrop hidden">';
        echo '<div class="hs-modal-window">';
        
        echo '<div class="hs-modal-header">';
        echo '<div>';
        echo '<h2 id="hs-modal-helmet-title">Helmet Title</h2>';
        echo '<span id="hs-modal-helmet-subtitle" style="font-size:0.85rem;color:#94a3b8;">Brand • ID: 123</span>';
        echo '</div>';
        echo '<button type="button" class="hs-modal-close" aria-label="Close">&times;</button>';
        echo '</div>';

        echo '<div class="hs-modal-body">';
        
        // Progress & Batch Swarm Bar
        echo '<div class="hs-modal-coverage-bar">';
        echo '<div><strong>' . esc_html__('5-Shot Studio Coverage:', 'helmetsan-core') . '</strong> <span id="hs-coverage-progress-label">0/5 Shots</span></div>';
        echo '<div class="hs-progress-track"><div id="hs-coverage-progress-fill" class="hs-progress-fill"></div></div>';
        echo '<button type="button" id="hs-btn-generate-all-missing" class="button button-primary button-hero" style="font-size:0.9rem;">⚡ ' . esc_html__('Generate All Missing 5 Shots', 'helmetsan-core') . '</button>';
        echo '</div>';

        // 5-Shot Grid
        echo '<div id="hs-shots-container" class="hs-shots-grid">';
        echo '</div>';

        // Live Console Logger
        echo '<div style="margin-top:1rem;">';
        echo '<strong style="font-size:0.85rem;color:#334155;">' . esc_html__('Live Swarm Activity Console (NVIDIA NIM & Moonshot AI Kimi-K3):', 'helmetsan-core') . '</strong>';
        echo '<div id="hs-studio-console" class="hs-activity-console">';
        echo '<div class="log-line"><span class="log-time">[Init]</span> Studio ready.</div>';
        echo '</div>';
        echo '</div>';

        echo '</div>'; // .hs-modal-body
        echo '</div>'; // .hs-modal-window
        echo '</div>'; // #hs-studio-modal

        // -------------------------------------------------------------
        // Kimi-K3 Quality Audit Modal Markup
        // -------------------------------------------------------------
        echo '<div id="hs-audit-modal" class="hs-modal-backdrop hidden">';
        echo '<div class="hs-modal-window hs-audit-modal-window">';
        
        echo '<div class="hs-modal-header">';
        echo '<h2>🔍 ' . esc_html__('Moonshot AI Kimi-K3 Visual Quality Audit', 'helmetsan-core') . '</h2>';
        echo '<button type="button" class="hs-modal-close" aria-label="Close">&times;</button>';
        echo '</div>';

        echo '<div class="hs-modal-body">';
        
        echo '<div class="hs-audit-score-card">';
        echo '<div id="hs-audit-gauge" class="hs-audit-gauge hs-gauge-pass">95</div>';
        echo '<div>';
        echo '<h3 id="hs-audit-decision-text" style="margin:0 0 0.25rem;font-size:1.1rem;color:#0f172a;">APPROVED</h3>';
        echo '<p style="margin:0;color:#64748b;font-size:0.85rem;">Inspected via Moonshot AI Kimi-K3 Multimodal Vision on NVIDIA NIM.</p>';
        echo '</div>';
        echo '</div>';

        echo '<h4 style="margin:0 0 0.5rem;font-size:0.9rem;">' . esc_html__('Photorealism & Safety Geometry Checklist:', 'helmetsan-core') . '</h4>';
        echo '<ul id="hs-audit-checks-list" class="hs-audit-checks-list"></ul>';

        echo '<div style="background:#fff;padding:1rem;border-radius:8px;border:1px solid #e2e8f0;">';
        echo '<strong>' . esc_html__('Auditor Recommendations:', 'helmetsan-core') . '</strong>';
        echo '<p id="hs-audit-recommendations" style="margin:0.35rem 0 0;font-size:0.85rem;color:#475569;"></p>';
        echo '</div>';

        echo '<p style="text-align:right;margin-top:1.25rem;margin-bottom:0;">';
        echo '<button type="button" class="button hs-close-modal-trigger">' . esc_html__('Close Audit Report', 'helmetsan-core') . '</button>';
        echo '</p>';

        echo '</div>'; // .hs-modal-body
        echo '</div>'; // .hs-modal-window
        echo '</div>'; // #hs-audit-modal
    }

    private function renderImporterTab(): void
    {
        $result = get_transient(self::RESULT_TRANSIENT);
        $done   = isset($_GET['done']) && (int) $_GET['done'] === 1;
        if ($done && is_array($result)) {
            delete_transient(self::RESULT_TRANSIENT);
        }

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="hs-panel" style="background:#fff;padding:1.25rem;border-radius:8px;border:1px solid #e2e8f0;">';
        echo '<input type="hidden" name="action" value="helmetsan_helmet_images_run" />';
        wp_nonce_field('helmetsan_helmet_images_run', '_wpnonce', true, true);

        echo '<h2 class="title" style="margin-top:0;">' . esc_html__('External Image Sources', 'helmetsan-core') . '</h2>';
        echo '<div class="helmetsan-helmet-images-options">';
        echo '<label><input type="checkbox" name="use_ai" value="1" ' . checked(true, true, false) . ' /> ' . esc_html__('Use AI', 'helmetsan-core') . ' <span class="description">— ' . esc_html__('Resolve EAN or image URL when helmet has no barcode or link.', 'helmetsan-core') . '</span></label>';
        echo '<label><input type="checkbox" name="use_revzilla" value="1" ' . checked(true, true, false) . ' /> ' . esc_html__('Use RevZilla', 'helmetsan-core') . ' <span class="description">— ' . esc_html__('Fetch image from RevZilla product page.', 'helmetsan-core') . '</span></label>';
        echo '<label><input type="checkbox" name="use_ean" value="1" ' . checked(true, true, false) . ' /> ' . esc_html__('Use EAN / GTIN lookup', 'helmetsan-core') . ' <span class="description">— ' . esc_html__('Fetch image from EAN-DB or eandata.', 'helmetsan-core') . '</span></label>';
        echo '</div>';

        echo '<h2 class="title" style="margin-top: 1.25rem;">' . esc_html__('Scope & Limits', 'helmetsan-core') . '</h2>';
        echo '<div class="helmetsan-helmet-images-options">';
        echo '<label><input type="checkbox" name="all_helmets" value="1" /> ' . esc_html__('Process all helmets', 'helmetsan-core') . ' <span class="description">— ' . esc_html__('If unchecked, only helmets without a featured image are processed.', 'helmetsan-core') . '</span></label>';
        echo '<p><label>' . esc_html__('Limit', 'helmetsan-core') . ' <input type="number" name="limit" value="50" min="1" max="500" class="small-text" /> ' . esc_html__('helmets per run.', 'helmetsan-core') . '</label></p>';
        echo '<label><input type="checkbox" name="dry_run" value="1" /> ' . esc_html__('Dry run', 'helmetsan-core') . '</label>';
        echo '</div>';

        echo '<p class="submit" style="margin-top: 1rem; margin-bottom: 0;">';
        echo '<input type="submit" class="button button-primary button-hero" value="' . esc_attr__('Run Enrichment', 'helmetsan-core') . '" />';
        echo '</p>';
        echo '</form>';

        if ($done && is_array($result)) {
            echo '<div class="notice notice-success" style="margin-top:1rem;padding:1rem;">';
            echo '<p><strong>' . esc_html__('Enrichment Complete', 'helmetsan-core') . '</strong></p>';
            echo '<div>Processed: ' . (int) ($result['processed'] ?? 0) . ' | Filled: ' . (int) ($result['filled'] ?? 0) . '</div>';
            echo '</div>';
        }
    }
}
