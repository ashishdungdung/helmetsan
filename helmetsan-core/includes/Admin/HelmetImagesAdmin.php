<?php

declare(strict_types=1);

namespace Helmetsan\Core\Admin;

use Helmetsan\Core\AI\AiService;
use Helmetsan\Core\Media\HelmetImageEnrichmentService;

/**
 * Admin UI for matching catalog helmets to product images (AI, RevZilla, EAN) and importing via Media Engine.
 */
final class HelmetImagesAdmin
{
    private const RESULT_TRANSIENT = 'helmetsan_helmet_images_result';
    private const RESULT_TTL       = 3600;

    public function __construct(
        private readonly HelmetImageEnrichmentService $enrichment,
        private readonly AiService $aiService
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
        if ($hook !== 'helmetsan_page_helmetsan-helmet-images') {
            return;
        }
        $slug = 'helmetsan-helmet-images-admin';
        wp_register_style($slug, '', [], HELMETSAN_CORE_VERSION);
        wp_add_inline_style($slug, $this->inlineStyles());
        wp_enqueue_style($slug);
    }

    private function inlineStyles(): string
    {
        return '
            .helmetsan-helmet-images-wrap { max-width: 720px; }
            .helmetsan-helmet-images-wrap .hs-panel { margin-bottom: 1.25rem; }
            .helmetsan-helmet-images-priority { background: linear-gradient(135deg, #f0f9ff 0%, #e0f2fe 100%); border-left: 4px solid #0284c7; padding: 1rem 1.25rem; margin-bottom: 1.25rem; border-radius: 0 8px 8px 0; }
            .helmetsan-helmet-images-priority strong { color: #0369a1; }
            .helmetsan-helmet-images-options { display: grid; gap: 0.75rem; }
            .helmetsan-helmet-images-options label { display: flex; align-items: center; gap: 0.5rem; }
            .helmetsan-helmet-images-options label input[type="checkbox"] { margin: 0; }
            .helmetsan-helmet-images-result { margin-top: 1rem; padding: 1rem; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 8px; }
            .helmetsan-helmet-images-result.success { border-color: #86efac; background: #f0fdf4; }
            .helmetsan-helmet-images-result .result-row { display: flex; gap: 1.5rem; margin: 0.25rem 0; }
            .helmetsan-helmet-images-result .result-label { font-weight: 600; color: #475569; min-width: 5rem; }
        ';
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

        $aiRequired = $useAi && ! $this->aiService->hasAnyConfiguredProvider();
        if ($aiRequired) {
            set_transient(self::RESULT_TRANSIENT, [
                'error' => __('AI is enabled but no provider is configured. Go to Helmetsan → AI to add an API key and model.', 'helmetsan-core'),
            ], self::RESULT_TTL);
            wp_safe_redirect(admin_url('admin.php?page=helmetsan-helmet-images&done=1'));
            exit;
        }

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
        wp_safe_redirect(admin_url('admin.php?page=helmetsan-helmet-images&done=1'));
        exit;
    }

    public function renderPage(): void
    {
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission.', 'helmetsan-core'));
        }

        $result = get_transient(self::RESULT_TRANSIENT);
        $done   = isset($_GET['done']) && (int) $_GET['done'] === 1;
        if ($done && is_array($result)) {
            delete_transient(self::RESULT_TRANSIENT);
        }

        echo '<div class="wrap helmetsan-wrap helmetsan-helmet-images-wrap">';
        echo '<h1>' . esc_html__('Helmet images', 'helmetsan-core') . '</h1>';
        echo '<p class="description">' . esc_html__('Match your catalog helmets to product images and import them as featured images. Images are fetched from AI (recommended), RevZilla product pages, or EAN/GTIN lookup, then saved to the Media Library.', 'helmetsan-core') . '</p>';

        echo '<div class="helmetsan-helmet-images-priority">';
        echo '<strong>' . esc_html__('Priority: AI', 'helmetsan-core') . '</strong> ';
        echo esc_html__('When a helmet has no barcode or RevZilla link, enable "Use AI" so the model can suggest an EAN, a RevZilla product URL, or a direct image URL. AI-resolved RevZilla links are stored for future runs. Configure at least one provider under Helmetsan → AI.', 'helmetsan-core');
        echo '</div>';

        echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" class="hs-panel">';
        echo '<input type="hidden" name="action" value="helmetsan_helmet_images_run" />';
        wp_nonce_field('helmetsan_helmet_images_run', '_wpnonce', true, true);

        echo '<h2 class="title" style="margin-top:0;">' . esc_html__('Image sources', 'helmetsan-core') . '</h2>';
        echo '<div class="helmetsan-helmet-images-options">';
        $aiConfigured = $this->aiService->hasAnyConfiguredProvider();
        echo '<label><input type="checkbox" name="use_ai" value="1" ' . checked(true, true, false) . ' /> ' . esc_html__('Use AI', 'helmetsan-core') . ' <span class="description">— ' . esc_html__('Resolve EAN or image URL when helmet has no barcode or link (recommended).', 'helmetsan-core') . '</span></label>';
        if (! $aiConfigured) {
            echo '<p class="description" style="margin-left: 1.5rem; color: #b45309;">' . esc_html__('No AI provider configured. Go to Helmetsan → AI to add an API key.', 'helmetsan-core') . '</p>';
        }
        echo '<label><input type="checkbox" name="use_revzilla" value="1" ' . checked(true, true, false) . ' /> ' . esc_html__('Use RevZilla', 'helmetsan-core') . ' <span class="description">— ' . esc_html__('Fetch image from RevZilla product page. Uses stored link when present; when "Use AI" is also on, AI can find the RevZilla URL for the helmet and save it for next time.', 'helmetsan-core') . '</span></label>';
        echo '<label><input type="checkbox" name="use_ean" value="1" ' . checked(true, true, false) . ' /> ' . esc_html__('Use EAN / GTIN lookup', 'helmetsan-core') . ' <span class="description">— ' . esc_html__('Fetch image from EAN-DB or eandata when helmet has barcode in meta.', 'helmetsan-core') . '</span></label>';
        echo '</div>';

        echo '<h2 class="title" style="margin-top: 1.25rem;">' . esc_html__('Scope', 'helmetsan-core') . '</h2>';
        echo '<div class="helmetsan-helmet-images-options">';
        echo '<label><input type="checkbox" name="all_helmets" value="1" ' . checked(false, true, false) . ' /> ' . esc_html__('Process all helmets', 'helmetsan-core') . ' <span class="description">— ' . esc_html__('If unchecked, only helmets without a featured image are processed.', 'helmetsan-core') . '</span></label>';
        echo '<p><label>' . esc_html__('Limit', 'helmetsan-core') . ' <input type="number" name="limit" value="50" min="1" max="500" step="1" class="small-text" /> ' . esc_html__('helmets per run (0 = no limit).', 'helmetsan-core') . '</label></p>';
        echo '</div>';

        echo '<h2 class="title" style="margin-top: 1.25rem;">' . esc_html__('Run', 'helmetsan-core') . '</h2>';
        echo '<div class="helmetsan-helmet-images-options">';
        echo '<label><input type="checkbox" name="dry_run" value="1" /> ' . esc_html__('Dry run', 'helmetsan-core') . ' <span class="description">— ' . esc_html__('Report what would be done without importing or setting thumbnails.', 'helmetsan-core') . '</span></label>';
        echo '</div>';
        echo '<p class="submit" style="margin-top: 1rem; margin-bottom: 0;">';
        echo '<input type="submit" class="button button-primary button-hero" value="' . esc_attr__('Run enrichment', 'helmetsan-core') . '" />';
        echo '</p>';
        echo '</form>';

        if ($done && is_array($result)) {
            if (! empty($result['error'])) {
                echo '<div class="helmetsan-helmet-images-result notice notice-error" style="margin-top:1rem;"><p>' . esc_html($result['error']) . '</p></div>';
            } else {
                $dryRun = ! empty($result['dry_run']);
                echo '<div class="helmetsan-helmet-images-result success" style="margin-top:1rem;">';
                echo '<p><strong>' . ($dryRun ? esc_html__('Dry run result', 'helmetsan-core') : esc_html__('Last run result', 'helmetsan-core')) . '</strong></p>';
                echo '<div class="result-row"><span class="result-label">' . esc_html__('Processed', 'helmetsan-core') . '</span> ' . (int) ($result['processed'] ?? 0) . '</div>';
                echo '<div class="result-row"><span class="result-label">' . esc_html__('Filled', 'helmetsan-core') . '</span> ' . (int) ($result['filled'] ?? 0) . '</div>';
                echo '<div class="result-row"><span class="result-label">' . esc_html__('Skipped', 'helmetsan-core') . '</span> ' . (int) ($result['skipped'] ?? 0) . '</div>';
                echo '<div class="result-row"><span class="result-label">' . esc_html__('Errors', 'helmetsan-core') . '</span> ' . (int) ($result['errors'] ?? 0) . '</div>';
                echo '</div>';
            }
        }

        echo '<div class="hs-panel" style="margin-top: 1.5rem;">';
        echo '<h2 class="title" style="margin-top:0;">' . esc_html__('CLI', 'helmetsan-core') . '</h2>';
        echo '<p class="description">' . esc_html__('Same enrichment from the command line:', 'helmetsan-core') . '</p>';
        echo '<pre style="background:#1e293b;color:#e2e8f0;padding:1rem;border-radius:8px;overflow:auto;"><code>wp helmetsan helmet-images --limit=50
wp helmetsan helmet-images --limit=100 --use-ai --verbose
wp helmetsan helmet-images --all --use-ai --dry-run</code></pre>';
        echo '</div>';

        echo '</div>';
    }
}
