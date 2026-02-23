<?php

declare(strict_types=1);

namespace Helmetsan\Core\Admin;

use Helmetsan\Core\AI\AiService;
use Helmetsan\Core\AI\FillMissingService;
use Helmetsan\Core\AI\ProviderRegistry;
use Helmetsan\Core\Support\Config;

/**
 * Admin UI for the AI module: providers (API keys, models), free vs premium, phase toggles.
 */
final class AiAdmin
{
    public function __construct(
        private readonly Config $config,
        private readonly AiService $aiService
    ) {
    }

    private const FILL_RESULT_TRANSIENT = 'helmetsan_ai_fill_result';

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu'], 15);
        add_action('admin_init', [$this, 'handleSave']);
        add_action('admin_post_helmetsan_ai_fill_dry', [$this, 'handleFillDry']);
        add_action('admin_post_helmetsan_ai_fill_run', [$this, 'handleFillRun']);
    }

    public function addMenu(): void
    {
        add_submenu_page(
            'helmetsan-dashboard',
            'AI',
            'AI',
            'manage_options',
            'helmetsan-ai',
            [$this, 'renderPage']
        );
    }

    public function handleSave(): void
    {
        if (! isset($_POST['helmetsan_ai_nonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['helmetsan_ai_nonce'])), 'helmetsan_ai_save')) {
            return;
        }
        if (! current_user_can('manage_options')) {
            return;
        }
        $defaults = $this->config->aiDefaults();
        $current = get_option(Config::OPTION_AI, $defaults);
        $providers = $current['providers'] ?? $defaults['providers'];
        foreach (array_keys($defaults['providers']) as $id) {
            $key = 'helmetsan_ai_' . $id;
            $providers[$id]['enabled'] = ! empty($_POST[$key . '_enabled']);
            $postedKey = isset($_POST[$key . '_key']) ? sanitize_text_field(wp_unslash($_POST[$key . '_key'])) : '';
            $providers[$id]['api_key'] = $postedKey !== '' ? $postedKey : (string) ($providers[$id]['api_key'] ?? '');
            $providers[$id]['model'] = isset($_POST[$key . '_model']) ? sanitize_text_field(wp_unslash($_POST[$key . '_model'])) : ($defaults['providers'][$id]['model'] ?? '');
        }
        $settings = get_option(Config::OPTION_AI, $defaults);
        $settings['providers'] = $providers;
        $settings['default_free'] = isset($_POST['helmetsan_ai_default_free']) ? sanitize_text_field(wp_unslash($_POST['helmetsan_ai_default_free'])) : $defaults['default_free'];
        $settings['default_premium'] = isset($_POST['helmetsan_ai_default_premium']) ? sanitize_text_field(wp_unslash($_POST['helmetsan_ai_default_premium'])) : $defaults['default_premium'];
        $settings['phase1_seo_enabled'] = ! empty($_POST['helmetsan_ai_phase1']);
        $settings['phase2_fill_enabled'] = ! empty($_POST['helmetsan_ai_phase2']);
        $settings['phase3_integrity_enabled'] = ! empty($_POST['helmetsan_ai_phase3']);
        update_option(Config::OPTION_AI, $settings, false);
        add_settings_error(
            'helmetsan_ai',
            'saved',
            __('AI settings saved.', 'helmetsan-core'),
            'success'
        );
    }

    public function renderPage(): void
    {
        $settings = get_option(Config::OPTION_AI, $this->config->aiDefaults());
        $providers = $settings['providers'] ?? $this->config->aiDefaults()['providers'];
        $freeIds = ProviderRegistry::freeProviderIds();
        $premiumIds = ProviderRegistry::premiumProviderIds();

        echo '<div class="wrap helmetsan-wrap">';
        echo '<h1>' . esc_html__('AI Module', 'helmetsan-core') . '</h1>';
        echo '<p class="description">' . esc_html__('Configure AI providers for SEO, filling missing data, and integrity checks. Use free/low-cost providers first; premium options have dedicated controls.', 'helmetsan-core') . '</p>';

        settings_errors('helmetsan_ai');

        echo '<form method="post" action="">';
        wp_nonce_field('helmetsan_ai_save', 'helmetsan_ai_nonce');

        echo '<div class="hs-panel" style="max-width: 720px; margin-top: 1.5rem;">';
        echo '<h2 class="title">' . esc_html__('Phases', 'helmetsan-core') . '</h2>';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row">Phase 1: SEO</th><td><label><input type="checkbox" name="helmetsan_ai_phase1" value="1" ' . checked(! empty($settings['phase1_seo_enabled']), true, false) . ' /> ' . esc_html__('Enable AI for meta descriptions (SEO seed)', 'helmetsan-core') . '</label></td></tr>';
        echo '<tr><th scope="row">Phase 2: Fill data</th><td><label><input type="checkbox" name="helmetsan_ai_phase2" value="1" ' . checked(! empty($settings['phase2_fill_enabled']), true, false) . ' /> ' . esc_html__('Enable AI to fill missing entity fields (helmets, brands, accessories)', 'helmetsan-core') . '</label></td></tr>';
        echo '<tr><th scope="row">Phase 3: Integrity</th><td><label><input type="checkbox" name="helmetsan_ai_phase3" value="1" ' . checked(! empty($settings['phase3_integrity_enabled']), true, false) . ' /> ' . esc_html__('Enable AI for data quality checks (coming soon)', 'helmetsan-core') . '</label></td></tr>';
        echo '</tbody></table></div>';

        echo '<div class="hs-panel" style="max-width: 720px; margin-top: 1.5rem;">';
        echo '<h2 class="title">' . esc_html__('Free / low-cost providers', 'helmetsan-core') . '</h2>';
        echo '<p class="description">' . esc_html__('Use these first to minimize cost. At least one enabled with API key is required for AI features.', 'helmetsan-core') . '</p>';
        echo '<table class="form-table widefat striped"><thead><tr><th>' . esc_html__('Provider', 'helmetsan-core') . '</th><th>' . esc_html__('Enable', 'helmetsan-core') . '</th><th>' . esc_html__('API key', 'helmetsan-core') . '</th><th>' . esc_html__('Model', 'helmetsan-core') . '</th></tr></thead><tbody>';
        foreach ($freeIds as $id) {
            $p = $providers[$id] ?? ['enabled' => false, 'api_key' => '', 'model' => ''];
            $def = $this->config->aiDefaults()['providers'][$id];
            $model = $p['model'] ?? $def['model'];
            $label = $this->providerLabel($id);
            echo '<tr>';
            echo '<td><strong>' . esc_html($label) . '</strong></td>';
            echo '<td><input type="checkbox" name="helmetsan_ai_' . esc_attr($id) . '_enabled" value="1" ' . checked(! empty($p['enabled']), true, false) . ' /></td>';
            echo '<td><input type="password" autocomplete="off" name="helmetsan_ai_' . esc_attr($id) . '_key" value="' . esc_attr((string) ($p['api_key'] ?? '')) . '" class="regular-text" placeholder="' . esc_attr__('API key', 'helmetsan-core') . '" /></td>';
            echo '<td><input type="text" name="helmetsan_ai_' . esc_attr($id) . '_model" value="' . esc_attr($model) . '" class="regular-text" placeholder="' . esc_attr($def['model']) . '" /></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<p><label>' . esc_html__('Default free provider:', 'helmetsan-core') . ' <select name="helmetsan_ai_default_free">';
        foreach ($freeIds as $id) {
            echo '<option value="' . esc_attr($id) . '" ' . selected($settings['default_free'] ?? 'groq', $id, false) . '>' . esc_html($this->providerLabel($id)) . '</option>';
        }
        echo '</select></label></p>';
        echo '</div>';

        echo '<div class="hs-panel" style="max-width: 720px; margin-top: 1.5rem;">';
        echo '<h2 class="title">' . esc_html__('Premium providers', 'helmetsan-core') . '</h2>';
        echo '<p class="description">' . esc_html__('Dedicated controls for higher-quality or paid models. Optional.', 'helmetsan-core') . '</p>';
        echo '<table class="form-table widefat striped"><thead><tr><th>' . esc_html__('Provider', 'helmetsan-core') . '</th><th>' . esc_html__('Enable', 'helmetsan-core') . '</th><th>' . esc_html__('API key', 'helmetsan-core') . '</th><th>' . esc_html__('Model', 'helmetsan-core') . '</th></tr></thead><tbody>';
        foreach ($premiumIds as $id) {
            $p = $providers[$id] ?? ['enabled' => false, 'api_key' => '', 'model' => ''];
            $def = $this->config->aiDefaults()['providers'][$id];
            $model = $p['model'] ?? $def['model'];
            $label = $this->providerLabel($id);
            echo '<tr>';
            echo '<td><strong>' . esc_html($label) . '</strong></td>';
            echo '<td><input type="checkbox" name="helmetsan_ai_' . esc_attr($id) . '_enabled" value="1" ' . checked(! empty($p['enabled']), true, false) . ' /></td>';
            echo '<td><input type="password" autocomplete="off" name="helmetsan_ai_' . esc_attr($id) . '_key" value="' . esc_attr((string) ($p['api_key'] ?? '')) . '" class="regular-text" /></td>';
            echo '<td><input type="text" name="helmetsan_ai_' . esc_attr($id) . '_model" value="' . esc_attr($model) . '" class="regular-text" placeholder="' . esc_attr($def['model']) . '" /></td>';
            echo '</tr>';
        }
        echo '</tbody></table>';
        echo '<p><label>' . esc_html__('Default premium provider:', 'helmetsan-core') . ' <select name="helmetsan_ai_default_premium">';
        foreach ($premiumIds as $id) {
            echo '<option value="' . esc_attr($id) . '" ' . selected($settings['default_premium'] ?? 'openai', $id, false) . '>' . esc_html($this->providerLabel($id)) . '</option>';
        }
        echo '</select></label></p>';
        echo '</div>';

        echo '<p class="submit" style="margin-top: 1.5rem;"><input type="submit" name="submit" class="button button-primary" value="' . esc_attr__('Save AI settings', 'helmetsan-core') . '" /></p>';
        echo '</form>';

        if ($this->aiService->hasAnyConfiguredProvider()) {
            echo '<div class="hs-panel" style="max-width: 720px; margin-top: 1.5rem;">';
            echo '<h2 class="title">' . esc_html__('Usage', 'helmetsan-core') . '</h2>';
            echo '<p><strong>' . esc_html__('Phase 1 – SEO:', 'helmetsan-core') . '</strong> <code>wp helmetsan seo seed --use-ai</code></p>';
            echo '<p><strong>' . esc_html__('Phase 2 – Fill missing fields:', 'helmetsan-core') . '</strong> <code>wp helmetsan ai fill-missing --post-type=helmet --limit=50</code> (use --dry-run to preview)</p>';
            echo '<p style="margin-top: 0.75rem;">' . esc_html__('Quick actions (last 10 helmets):', 'helmetsan-core') . '</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display: inline-block; margin-right: 8px;">';
            echo '<input type="hidden" name="action" value="helmetsan_ai_fill_dry" />';
            wp_nonce_field('helmetsan_ai_fill', '_wpnonce', true, true);
            echo '<input type="submit" class="button" value="' . esc_attr__('Dry run (last 10 helmets)', 'helmetsan-core') . '" />';
            echo '</form>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display: inline-block;">';
            echo '<input type="hidden" name="action" value="helmetsan_ai_fill_run" />';
            wp_nonce_field('helmetsan_ai_fill', '_wpnonce', true, true);
            echo '<input type="submit" class="button button-primary" value="' . esc_attr__('Fill last 10 helmets', 'helmetsan-core') . '" onclick="return confirm(\'' . esc_js(__('Fill missing fields for the last 10 helmets? This will call the AI API.', 'helmetsan-core')) . '\');" />';
            echo '</form>';
            $fillResult = get_transient(self::FILL_RESULT_TRANSIENT);
            if (is_array($fillResult)) {
                delete_transient(self::FILL_RESULT_TRANSIENT);
                $msg = sprintf(
                    __('Fill result: %d filled, %d skipped, %d errors, %d posts, %d API calls.', 'helmetsan-core'),
                    (int) ($fillResult['filled'] ?? 0),
                    (int) ($fillResult['skipped'] ?? 0),
                    (int) ($fillResult['errors'] ?? 0),
                    (int) ($fillResult['total_posts'] ?? 0),
                    (int) ($fillResult['api_calls'] ?? 0)
                );
                echo '<p class="notice notice-info" style="margin-top: 0.75rem;">' . esc_html($msg) . '</p>';
            }
            echo '</div>';
        }

        echo '</div>';
    }

    public function handleFillDry(): void
    {
        if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'helmetsan_ai_fill')) {
            wp_die(esc_html__('Security check failed.', 'helmetsan-core'));
        }
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission.', 'helmetsan-core'));
        }
        $service = new FillMissingService($this->aiService);
        $result = $service->run('helmet', 10, 0, true, null, false, false, null, null, 0);
        set_transient(self::FILL_RESULT_TRANSIENT, array_merge($result, ['dry' => true]), 60);
        wp_safe_redirect(admin_url('admin.php?page=helmetsan-ai&fill_done=1'));
        exit;
    }

    public function handleFillRun(): void
    {
        if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'helmetsan_ai_fill')) {
            wp_die(esc_html__('Security check failed.', 'helmetsan-core'));
        }
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission.', 'helmetsan-core'));
        }
        $service = new FillMissingService($this->aiService);
        $result = $service->run('helmet', 10, 0, false, null, false, false, null, null, 86400);
        set_transient(self::FILL_RESULT_TRANSIENT, array_merge($result, ['dry' => false]), 60);
        wp_safe_redirect(admin_url('admin.php?page=helmetsan-ai&fill_done=1'));
        exit;
    }

    private function providerLabel(string $id): string
    {
        return match ($id) {
            'groq' => 'Groq',
            'gemini' => 'Google Gemini',
            'mistral' => 'Mistral AI',
            'openrouter' => 'OpenRouter',
            'huggingface' => 'Hugging Face',
            'openai' => 'OpenAI (ChatGPT)',
            'perplexity' => 'Perplexity',
            default => $id,
        };
    }
}
