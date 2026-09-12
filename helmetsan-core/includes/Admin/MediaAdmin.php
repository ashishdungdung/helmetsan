<?php

declare(strict_types=1);

namespace Helmetsan\Core\Admin;

use Helmetsan\Core\Support\Config;
use Helmetsan\Core\Media\MediaEngine;
use Helmetsan\Core\Media\HelmetImageEnrichmentService;

/**
 * Admin UI for Media Management & Health.
 * Provides a dashboard to see coverage of high-fidelity assets vs placeholders.
 */
final class MediaAdmin
{
    private const SCAN_RESULT_TRANSIENT = 'helmetsan_media_scan_result';

    public function __construct(
        private readonly Config $config,
        private readonly MediaEngine $mediaEngine,
        private readonly HelmetImageEnrichmentService $enrichmentService,
        private readonly \Helmetsan\Core\Media\MediaHealthService $healthService
    ) {}

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu'], 16);
        add_action('admin_post_helmetsan_media_scan', [$this, 'handleScan']);
        add_action('admin_post_helmetsan_media_generate_pollinations', [$this, 'handleGeneratePollinations']);
        add_action('admin_post_helmetsan_media_generate_huggingface', [$this, 'handleGenerateHuggingFace']);
        add_action('admin_post_helmetsan_media_export_draw_things', [$this, 'handleExportDrawThings']);
    }

    public function addMenu(): void
    {
        add_submenu_page(
            'helmetsan-dashboard',
            'Media Health',
            'Media Health',
            'manage_options',
            'helmetsan-media-health',
            [$this, 'renderPage']
        );
    }

    public function handleScan(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }

        check_admin_referer('helmetsan_media_scan');

        $stats = $this->healthService->scan();
        set_transient(self::SCAN_RESULT_TRANSIENT, $stats, HOUR_IN_SECONDS);

        wp_safe_redirect(admin_url('admin.php?page=helmetsan-media-health&scanned=1'));
        exit;
    }

    public function handleGeneratePollinations(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'helmetsan_media_generate')) {
            wp_die('Security check failed.');
        }
        
        $tracker = new \Helmetsan\Core\Support\TaskTracker();
        $id = 'pollinations_' . time();
        $tracker->queueLaunch('enrich_images_pollinations', $id);

        wp_safe_redirect(admin_url('admin.php?page=helmetsan-media-health&msg=pollinations_queued'));
        exit;
    }

    public function handleGenerateHuggingFace(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'helmetsan_media_generate')) {
            wp_die('Security check failed.');
        }
        
        $tracker = new \Helmetsan\Core\Support\TaskTracker();
        $id = 'huggingface_' . time();
        $tracker->queueLaunch('enrich_images_huggingface', $id);

        wp_safe_redirect(admin_url('admin.php?page=helmetsan-media-health&msg=huggingface_queued'));
        exit;
    }

    public function handleExportDrawThings(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die('Unauthorized');
        }
        if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'helmetsan_media_generate')) {
            wp_die('Security check failed.');
        }

        // Query helmets missing a featured image (the actual use case)
        $query = new \WP_Query([
            'post_type'      => 'helmet',
            'post_status'    => 'publish',
            'posts_per_page' => 200,
            'meta_query'     => [
                [
                    'key'     => '_thumbnail_id',
                    'compare' => 'NOT EXISTS',
                ],
            ],
            'fields' => 'ids',
        ]);

        $ids = is_array($query->posts) ? array_map('intval', $query->posts) : [];
        if (empty($ids)) {
            wp_safe_redirect(admin_url('admin.php?page=helmetsan-media-health&msg=no_missing'));
            exit;
        }

        require_once dirname(__DIR__, 2) . '/scripts/media_pipeline_prompts.php';

        $manifest = [];
        foreach ($ids as $postId) {
            $helmet = get_post($postId);
            if (! $helmet) continue;
            
            $helmetData = [
                'id'    => $postId,
                'title' => $helmet->post_title,
                'color' => get_post_meta($postId, 'color', true) ?: 'Black',
                'finish' => get_post_meta($postId, 'finish', true) ?: 'Gloss',
                'type'  => get_post_meta($postId, 'type', true) ?: 'Full Face',
                'specs' => [
                    'material' => get_post_meta($postId, 'material', true) ?: 'Polycarbonate'
                ]
            ];

            $manifest[] = [
                'id'              => $postId,
                'title'           => $helmet->post_title,
                'prompt'          => generate_helmet_prompt($helmetData),
                'negative_prompt' => 'deformed, messy, text, watermark, blurry, low quality',
                'width'           => 1024,
                'height'          => 1024,
                'steps'           => 30,
                'cfg_scale'       => 7.5
            ];
        }

        $json = wp_json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
        
        header('Content-Type: application/json');
        header('Content-Disposition: attachment; filename="helmetsan_draw_things_manifest.json"');
        echo $json;
        exit;
    }

    public function renderPage(): void
    {
        $stats = get_transient(self::SCAN_RESULT_TRANSIENT);
        $scanned = isset($_GET['scanned']);
        $msg = isset($_GET['msg']) ? sanitize_key($_GET['msg']) : '';

        echo '<div class="wrap helmetsan-wrap">';
        echo '<h1>' . esc_html__('Media Health Dashboard', 'helmetsan-core') . '</h1>';
        echo '<p class="description">Monitor the visual quality of your catalog. Track high-fidelity assets vs. placeholders.</p>';

        if ($scanned) {
            echo '<div class="notice notice-success is-dismissible"><p>Scan completed successfully.</p></div>';
        }
        if ($msg === 'pollinations_queued') {
            echo '<div class="notice notice-info is-dismissible"><p>✅ Pollinations batch has been queued for background processing.</p></div>';
        } elseif ($msg === 'huggingface_queued') {
            echo '<div class="notice notice-info is-dismissible"><p>✅ Hugging Face Flux batch has been queued for background processing.</p></div>';
        } elseif ($msg === 'no_missing') {
            echo '<div class="notice notice-warning is-dismissible"><p>No helmets with missing images found.</p></div>';
        }

        if (!$stats) {
            echo '<div class="hs-panel">';
            echo '<h3>No scan data found</h3>';
            echo '<p>Start a new scan to analyze your catalog media health.</p>';
            $this->renderScanButton();
            echo '</div>';
            echo '</div>';
            return;
        }

        $hfPct = $stats['total'] > 0 ? round(($stats['high_fidelity'] / $stats['total']) * 100) : 0;
        $plPct = $stats['total'] > 0 ? round(($stats['placeholders'] / $stats['total']) * 100) : 0;
        $msPct = $stats['total'] > 0 ? round(($stats['missing'] / $stats['total']) * 100) : 0;

        echo '<div class="hs-hero">';
        echo '<div class="hs-hero__meta">';
        echo '<div class="hs-eyebrow">Catalog Media Coverage</div>';
        echo '<h2>' . (int)$hfPct . '% High-Fidelity</h2>';
        echo '<p>Last Scanned: ' . esc_html(date('Y-m-d H:i:s', $stats['scanned_at'])) . '</p>';
        echo '</div>';
        echo '<div class="hs-hero__status">';
        echo '<div class="hs-eyebrow">Asset Distribution</div>';
        echo '<div style="display:flex; height:24px; border-radius:12px; overflow:hidden; margin: 10px 0;">';
        echo '<div style="width:' . (int)$hfPct . '%; background:var(--hs-ok, #00a32a);" title="High-Fidelity"></div>';
        echo '<div style="width:' . (int)$plPct . '%; background:var(--hs-warn, #dba617);" title="Placeholders"></div>';
        echo '<div style="width:' . (int)$msPct . '%; background:var(--hs-fail, #d63638);" title="Missing"></div>';
        echo '</div>';
        echo '<div style="display:flex; gap:15px; font-size:11px;">';
        echo '<span><span style="display:inline-block; width:10px; height:10px; background:#00a32a; border-radius:50%;"></span> ' . (int)$stats['high_fidelity'] . ' OK</span>';
        echo '<span><span style="display:inline-block; width:10px; height:10px; background:#dba617; border-radius:50%;"></span> ' . (int)$stats['placeholders'] . ' Placeholders</span>';
        echo '<span><span style="display:inline-block; width:10px; height:10px; background:#d63638; border-radius:50%;"></span> ' . (int)$stats['missing'] . ' Missing</span>';
        echo '</div>';
        echo '</div>';
        echo '</div>';

        echo '<div class="hs-grid hs-grid--2">';
        
        echo '<div class="hs-panel">';
        echo '<h3>Brand Health Breakdown</h3>';
        echo '<div style="max-height: 400px; overflow-y: auto;">';
        echo '<table class="widefat striped hs-table-compact">';
        echo '<thead><tr><th>Brand</th><th>Total</th><th>Status</th></tr></thead>';
        echo '<tbody>';
        foreach ($stats['by_brand'] as $brand => $bStats) {
            $pct = round(($bStats['ok'] / $bStats['total']) * 100);
            $color = $pct >= 80 ? '#00a32a' : ($pct >= 40 ? '#dba617' : '#d63638');
            echo '<tr>';
            echo '<td><strong>' . esc_html($brand) . '</strong></td>';
            echo '<td>' . (int)$bStats['total'] . '</td>';
            echo '<td>';
            echo '<div style="display:flex; align-items:center; gap:8px;">';
            echo '<div style="flex:1; height:6px; background:#eee; border-radius:3px;"><div style="width:' . (int)$pct . '%; height:100%; background:' . esc_attr($color) . '; border-radius:3px;"></div></div>';
            echo '<span style="font-size:10px; font-weight:600; width:30px;">' . (int)$pct . '%</span>';
            echo '</div>';
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '</div>';
        echo '</div>';

        echo '<div class="hs-panel">';
        echo '<h3>Actions</h3>';
        echo '<p class="description">Use these tools to fill gaps in your media catalog.</p>';
        echo '<div class="hs-action-row" style="margin-top:20px;">';
        $this->renderScanButton();
        echo '<a href="' . esc_url(admin_url('admin.php?page=helmetsan-catalog&relation=missing_brand')) . '" class="button">View Problems in Catalog</a>';
        echo '</div>';
        
        echo '<h4 style="margin-top:2rem;">Batch Generators</h4>';
        echo '<p class="description">Run automated generation for missing assets.</p>';
        echo '<div class="hs-action-row" style="margin-top:10px; display:flex; gap:10px;">';
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline;">';
        echo '<input type="hidden" name="action" value="helmetsan_media_generate_pollinations" />';
        wp_nonce_field('helmetsan_media_generate', '_wpnonce', true, true);
        echo '<button type="submit" class="button button-primary">Generate missing (Pollinations)</button>';
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline; margin-left:10px;">';
        echo '<input type="hidden" name="action" value="helmetsan_media_generate_huggingface" />';
        wp_nonce_field('helmetsan_media_generate', '_wpnonce', true, true);
        echo '<button type="submit" class="button">Generate (Hugging Face Flux)</button>';
        echo '</form>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline; margin-left:10px;">';
        echo '<input type="hidden" name="action" value="helmetsan_media_export_draw_things" />';
        wp_nonce_field('helmetsan_media_generate', '_wpnonce', true, true);
        echo '<button type="submit" class="button">Export Prompt Manifest (Draw Things)</button>';
        echo '</form>';
        echo '</div>';
        echo '<p class="description"><em>Hugging Face uses FLUX.1-schnell for ultra-high fidelity. Pollinations is faster for initial coverage.</em></p>';
        echo '</div>';

        echo '</div>'; // .hs-grid
        echo '</div>'; // .wrap
    }

    private function renderScanButton(): void
    {
        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display:inline-block; margin-right:10px;">';
        echo '<input type="hidden" name="action" value="helmetsan_media_scan" />';
        wp_nonce_field('helmetsan_media_scan');
        echo '<input type="submit" class="button button-primary" value="Run Media Health Scan" />';
        echo '</form>';
    }
}
