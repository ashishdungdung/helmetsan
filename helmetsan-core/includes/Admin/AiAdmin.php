<?php

declare(strict_types=1);

namespace Helmetsan\Core\Admin;

use Helmetsan\Core\AI\AccessoryGeneratorService;
use Helmetsan\Core\AI\AiService;
use Helmetsan\Core\AI\FillableFieldsConfig;
use Helmetsan\Core\AI\FillMissingService;
use Helmetsan\Core\AI\ProviderRegistry;
use Helmetsan\Core\Repository\JsonRepository;
use Helmetsan\Core\Support\Config;
use Helmetsan\Core\Seo\YoastSeoSeeder;

/**
 * Admin UI for the AI module: providers (API keys, models), free vs premium, phase toggles,
 * accessory generator, and fill coverage report.
 */
final class AiAdmin
{
    public function __construct(
        private readonly Config $config,
        private readonly AiService $aiService,
        private readonly ?AccessoryGeneratorService $accessoryGenerator = null,
        private readonly ?JsonRepository $repository = null
    ) {
    }

    private const FILL_RESULT_TRANSIENT = 'helmetsan_ai_fill_result';
    private const ACCESSORY_GEN_RESULT_TRANSIENT = 'helmetsan_ai_accessory_gen_result';
    private const FILL_COVERAGE_RESULT_TRANSIENT = 'helmetsan_ai_fill_coverage_result';
    private const CATALOG_AI_RESULT_TRANSIENT = 'helmetsan_catalog_ai_result';
    private const BRAND_AI_RESULT_TRANSIENT = 'helmetsan_brand_ai_result';
    private const SEO_CHECK_RESULT_TRANSIENT = 'helmetsan_seo_check_result';
    private const SEO_FIX_RESULT_TRANSIENT = 'helmetsan_seo_fix_result';
    private const TEST_RESULT_TRANSIENT_PREFIX = 'helmetsan_ai_test_';
    private const TEST_RESULT_TTL = 3600;
    private const SEO_SAMPLE_LIMIT = 200;
    private const SEO_TERM_LIMIT = 50;

    public function register(): void
    {
        add_action('admin_menu', [$this, 'addMenu'], 15);
        add_action('admin_init', [$this, 'handleSave']);
        add_action('admin_post_helmetsan_ai_fill_dry', [$this, 'handleFillDry']);
        add_action('admin_post_helmetsan_ai_fill_run', [$this, 'handleFillRun']);
        // Catalog AI handlers are registered by Admin and delegate here (same context as Catalog page).
        add_action('admin_post_helmetsan_brand_ai_fill_all', [$this, 'handleBrandAiFillAll']);
        add_action('admin_post_helmetsan_brand_ai_fill_key', [$this, 'handleBrandAiFillKey']);
        add_action('admin_post_helmetsan_seo_check', [$this, 'handleSeoCheck']);
        add_action('admin_post_helmetsan_seo_fix', [$this, 'handleSeoFix']);
        add_action('admin_post_helmetsan_ai_generate_accessories', [$this, 'handleGenerateAccessories']);
        add_action('admin_post_helmetsan_ai_fill_coverage', [$this, 'handleFillCoverage']);
        add_action('wp_ajax_helmetsan_ai_test_provider', [$this, 'ajaxTestProvider']);
        add_action('admin_enqueue_scripts', [$this, 'enqueueAiScripts']);
    }

    public function enqueueAiScripts(string $hook): void
    {
        if ($hook !== 'helmetsan_page_helmetsan-ai') {
            return;
        }
        $base = defined('HELMETSAN_CORE_FILE') ? dirname((string) HELMETSAN_CORE_FILE) : dirname(__DIR__, 2);
        $scriptPath = $base . '/assets/js/ai-admin.js';
        wp_enqueue_script(
            'helmetsan-ai-admin',
            plugins_url('assets/js/ai-admin.js', defined('HELMETSAN_CORE_FILE') ? HELMETSAN_CORE_FILE : $base . '/helmetsan-core.php'),
            ['jquery'],
            file_exists($scriptPath) ? (string) filemtime($scriptPath) : '1',
            true
        );
        wp_localize_script('helmetsan-ai-admin', 'helmetsanAi', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('helmetsan_ai_test'),
        ]);
    }

    public function ajaxTestProvider(): void
    {
        if (! check_ajax_referer('helmetsan_ai_test', 'nonce', false) || ! current_user_can('manage_options')) {
            wp_send_json_error(['message' => __('Security check failed.', 'helmetsan-core')]);
        }
        $providerId = isset($_POST['provider_id']) ? sanitize_key((string) $_POST['provider_id']) : '';
        if ($providerId === '') {
            wp_send_json_error(['message' => __('Missing provider.', 'helmetsan-core')]);
        }
        $result = $this->aiService->testProvider($providerId);
        set_transient(self::TEST_RESULT_TRANSIENT_PREFIX . $providerId, array_merge($result, ['at' => time()]), self::TEST_RESULT_TTL);
        if (! empty($result['ok'])) {
            wp_send_json_success(['message' => __('API responded. Key and model are working.', 'helmetsan-core')]);
        }
        wp_send_json_error(['message' => $result['message'] ?? __('Test failed.', 'helmetsan-core')]);
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
            if ($id === 'lm_studio' && isset($_POST[$key . '_base_url'])) {
                $providers[$id]['base_url'] = sanitize_text_field(wp_unslash($_POST[$key . '_base_url']));
            }
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
        echo '<p class="description">' . esc_html__('After entering an API key and model, use the "Test" button next to each provider to verify the connection. If the model name is wrong or the key is invalid, the test will fail.', 'helmetsan-core') . '</p>';

        settings_errors('helmetsan_ai');

        echo '<form method="post" action="">';
        wp_nonce_field('helmetsan_ai_save', 'helmetsan_ai_nonce');

        echo '<div class="hs-panel" style="max-width: 720px; margin-top: 1.5rem;">';
        echo '<h2 class="title">' . esc_html__('Phases', 'helmetsan-core') . '</h2>';
        echo '<table class="form-table"><tbody>';
        echo '<tr><th scope="row">Phase 1: SEO</th><td><label><input type="checkbox" name="helmetsan_ai_phase1" value="1" ' . checked(! empty($settings['phase1_seo_enabled']), true, false) . ' /> ' . esc_html__('Enable AI for meta descriptions (SEO seed)', 'helmetsan-core') . '</label></td></tr>';
        echo '<tr><th scope="row">Phase 2: Fill data</th><td><label><input type="checkbox" name="helmetsan_ai_phase2" value="1" ' . checked(! empty($settings['phase2_fill_enabled']), true, false) . ' /> ' . esc_html__('Enable AI to fill missing entity fields (helmets, brands, accessories, safety standards, dealers, distributors, etc.)', 'helmetsan-core') . '</label></td></tr>';
        echo '<tr><th scope="row">Phase 3: Integrity</th><td><label><input type="checkbox" name="helmetsan_ai_phase3" value="1" ' . checked(! empty($settings['phase3_integrity_enabled']), true, false) . ' /> ' . esc_html__('Enable AI for data quality checks (coming soon)', 'helmetsan-core') . '</label></td></tr>';
        echo '</tbody></table></div>';

        echo '<div class="hs-panel" style="max-width: 720px; margin-top: 1.5rem;">';
        echo '<h2 class="title">' . esc_html__('Free / low-cost providers', 'helmetsan-core') . '</h2>';
        echo '<p class="description">' . esc_html__('Use these first to minimize cost. At least one enabled with API key is required for AI features.', 'helmetsan-core') . '</p>';
        echo '<table class="form-table widefat striped"><thead><tr><th>' . esc_html__('Provider', 'helmetsan-core') . '</th><th>' . esc_html__('Best for', 'helmetsan-core') . '</th><th>' . esc_html__('Enable', 'helmetsan-core') . '</th><th>' . esc_html__('API key / Base URL', 'helmetsan-core') . '</th><th>' . esc_html__('Model', 'helmetsan-core') . '</th><th>' . esc_html__('Status', 'helmetsan-core') . '</th></tr></thead><tbody>';
        foreach ($freeIds as $id) {
            $p = $providers[$id] ?? ['enabled' => false, 'api_key' => '', 'model' => '', 'base_url' => ''];
            $def = $this->config->aiDefaults()['providers'][$id];
            $model = $p['model'] ?? $def['model'];
            $label = $this->providerLabel($id);
            $bestFor = $this->providerBestFor($id);
            $testResult = get_transient(self::TEST_RESULT_TRANSIENT_PREFIX . $id);
            echo '<tr data-provider-id="' . esc_attr($id) . '">';
            echo '<td><strong>' . esc_html($label) . '</strong></td>';
            echo '<td><span class="description">' . esc_html($bestFor) . '</span></td>';
            echo '<td><input type="checkbox" name="helmetsan_ai_' . esc_attr($id) . '_enabled" value="1" ' . checked(! empty($p['enabled']), true, false) . ' /></td>';
            if ($id === 'lm_studio') {
                $baseUrl = (string) ($p['base_url'] ?? $def['base_url'] ?? 'http://localhost:1234/v1');
                echo '<td><input type="url" name="helmetsan_ai_' . esc_attr($id) . '_base_url" value="' . esc_attr($baseUrl) . '" class="regular-text" placeholder="http://localhost:1234/v1" /></td>';
            } else {
                echo '<td><input type="password" autocomplete="off" name="helmetsan_ai_' . esc_attr($id) . '_key" value="' . esc_attr((string) ($p['api_key'] ?? '')) . '" class="regular-text" placeholder="' . esc_attr__('API key', 'helmetsan-core') . '" /></td>';
            }
            echo '<td><input type="text" name="helmetsan_ai_' . esc_attr($id) . '_model" value="' . esc_attr($model) . '" class="regular-text" placeholder="' . esc_attr($def['model'] ?? '') . '" /></td>';
            echo '<td class="helmetsan-ai-test-cell">';
            echo '<button type="button" class="button helmetsan-ai-test-btn" data-provider-id="' . esc_attr($id) . '" aria-label="' . esc_attr__('Test API connection', 'helmetsan-core') . '">' . esc_html__('Test', 'helmetsan-core') . '</button>';
            echo ' <span class="helmetsan-ai-test-result">';
            if (is_array($testResult)) {
                if (! empty($testResult['ok'])) {
                    echo '<span class="helmetsan-ai-status ok" style="color:#00a32a;">✓ ' . esc_html__('Working', 'helmetsan-core') . '</span>';
                } else {
                    echo '<span class="helmetsan-ai-status fail" style="color:#d63638;">✗ ' . esc_html($testResult['message'] ?? __('Failed', 'helmetsan-core')) . '</span>';
                }
            }
            echo '</span></td>';
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
        echo '<table class="form-table widefat striped"><thead><tr><th>' . esc_html__('Provider', 'helmetsan-core') . '</th><th>' . esc_html__('Best for', 'helmetsan-core') . '</th><th>' . esc_html__('Enable', 'helmetsan-core') . '</th><th>' . esc_html__('API key', 'helmetsan-core') . '</th><th>' . esc_html__('Model', 'helmetsan-core') . '</th><th>' . esc_html__('Status', 'helmetsan-core') . '</th></tr></thead><tbody>';
        foreach ($premiumIds as $id) {
            $p = $providers[$id] ?? ['enabled' => false, 'api_key' => '', 'model' => ''];
            $def = $this->config->aiDefaults()['providers'][$id];
            $model = $p['model'] ?? $def['model'];
            $label = $this->providerLabel($id);
            $bestFor = $this->providerBestFor($id);
            $testResult = get_transient(self::TEST_RESULT_TRANSIENT_PREFIX . $id);
            echo '<tr data-provider-id="' . esc_attr($id) . '">';
            echo '<td><strong>' . esc_html($label) . '</strong></td>';
            echo '<td><span class="description">' . esc_html($bestFor) . '</span></td>';
            echo '<td><input type="checkbox" name="helmetsan_ai_' . esc_attr($id) . '_enabled" value="1" ' . checked(! empty($p['enabled']), true, false) . ' /></td>';
            echo '<td><input type="password" autocomplete="off" name="helmetsan_ai_' . esc_attr($id) . '_key" value="' . esc_attr((string) ($p['api_key'] ?? '')) . '" class="regular-text" /></td>';
            echo '<td><input type="text" name="helmetsan_ai_' . esc_attr($id) . '_model" value="' . esc_attr($model) . '" class="regular-text" placeholder="' . esc_attr($def['model']) . '" /></td>';
            echo '<td class="helmetsan-ai-test-cell">';
            echo '<button type="button" class="button helmetsan-ai-test-btn" data-provider-id="' . esc_attr($id) . '" aria-label="' . esc_attr__('Test API connection', 'helmetsan-core') . '">' . esc_html__('Test', 'helmetsan-core') . '</button>';
            echo ' <span class="helmetsan-ai-test-result">';
            if (is_array($testResult)) {
                if (! empty($testResult['ok'])) {
                    echo '<span class="helmetsan-ai-status ok" style="color:#00a32a;">✓ ' . esc_html__('Working', 'helmetsan-core') . '</span>';
                } else {
                    echo '<span class="helmetsan-ai-status fail" style="color:#d63638;">✗ ' . esc_html($testResult['message'] ?? __('Failed', 'helmetsan-core')) . '</span>';
                }
            }
            echo '</span></td>';
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
            echo '<p><strong>' . esc_html__('Phase 1 – SEO:', 'helmetsan-core') . '</strong> <code>wp helmetsan seo seed --post-type=all --scope=all --use-ai</code></p>';
            echo '<p><strong>' . esc_html__('Phase 2 – Fill missing fields:', 'helmetsan-core') . '</strong> <code>wp helmetsan ai fill-missing --post-type=helmet|brand|accessory|safety_standard|dealer|…|all --limit=50</code> (use <code>--dry-run</code> to preview, <code>--only-incomplete</code> to process only posts with empty fields)</p>';
            echo '<p class="description" style="margin-top: 0.5rem;">' . esc_html__('Filled = new values written; Skipped = field already had a value; Errors = validation or API failure; API calls = requests sent to the provider.', 'helmetsan-core') . '</p>';
            $fillTypes = array_filter(
                ['helmet', 'brand', 'accessory', 'safety_standard', 'dealer', 'distributor', 'technology', 'motorcycle', 'comparison', 'recommendation'],
                static fn (string $t): bool => FillableFieldsConfig::forPostType($t) !== []
            );
            echo '<p style="margin-top: 0.75rem;">' . esc_html__('Quick actions (last 10 items with at least one empty field):', 'helmetsan-core') . '</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display: inline-flex; align-items: center; gap: 8px; margin-right: 8px; margin-bottom: 8px;">';
            echo '<input type="hidden" name="action" value="helmetsan_ai_fill_dry" />';
            wp_nonce_field('helmetsan_ai_fill', '_wpnonce', true, true);
            echo '<label for="fill_dry_type" class="screen-reader-text">' . esc_html__('Post type', 'helmetsan-core') . '</label><select name="fill_type" id="fill_dry_type">';
            foreach ($fillTypes as $t) {
                echo '<option value="' . esc_attr($t) . '">' . esc_html(ucfirst(str_replace('_', ' ', $t))) . '</option>';
            }
            echo '</select>';
            echo '<input type="submit" class="button" value="' . esc_attr__('Dry run (last 10)', 'helmetsan-core') . '" />';
            echo '</form>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display: inline-flex; align-items: center; gap: 8px; margin-bottom: 8px;">';
            echo '<input type="hidden" name="action" value="helmetsan_ai_fill_run" />';
            wp_nonce_field('helmetsan_ai_fill', '_wpnonce', true, true);
            echo '<label for="fill_run_type" class="screen-reader-text">' . esc_html__('Post type', 'helmetsan-core') . '</label><select name="fill_type" id="fill_run_type">';
            foreach ($fillTypes as $t) {
                echo '<option value="' . esc_attr($t) . '">' . esc_html(ucfirst(str_replace('_', ' ', $t))) . '</option>';
            }
            echo '</select>';
            echo '<input type="submit" class="button button-primary" value="' . esc_attr__('Fill last 10', 'helmetsan-core') . '" onclick="return confirm(\'' . esc_js(__('Fill missing fields for the last 10 items? This will call the AI API.', 'helmetsan-core')) . '\');" />';
            echo '</form>';
            $fillResult = get_transient(self::FILL_RESULT_TRANSIENT);
            if (is_array($fillResult)) {
                $dry = ! empty($fillResult['dry']);
                $msg = sprintf(
                    $dry
                        ? __('Last run (dry): %d would fill, %d skipped, %d errors, %d posts, %d API calls.', 'helmetsan-core')
                        : __('Last run: %d filled, %d skipped, %d errors, %d posts, %d API calls.', 'helmetsan-core'),
                    (int) ($fillResult['filled'] ?? 0),
                    (int) ($fillResult['skipped'] ?? 0),
                    (int) ($fillResult['errors'] ?? 0),
                    (int) ($fillResult['total_posts'] ?? 0),
                    (int) ($fillResult['api_calls'] ?? 0)
                );
                echo '<p class="notice notice-info" style="margin-top: 0.75rem;">' . esc_html($msg) . '</p>';
            }
            echo '<p style="margin-top: 0.75rem;"><strong>' . esc_html__('Verify API from server:', 'helmetsan-core') . '</strong> <code>wp helmetsan api-check --live</code> ' . esc_html__('to confirm which provider responds.', 'helmetsan-core') . '</p>';
            echo '</div>';

            echo '<div class="hs-panel" style="max-width: 720px; margin-top: 1.5rem;">';
            echo '<h2 class="title">' . esc_html__('SEO status &amp; fix', 'helmetsan-core') . '</h2>';
            echo '<p class="description">' . esc_html__('Check Yoast SEO meta (title, meta description, focus keyphrase). Fix: lowercase focus keyphrase, truncate overlong title/description. Sampled items to avoid timeouts.', 'helmetsan-core') . '</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display: inline-block; margin-right: 8px;">';
            echo '<input type="hidden" name="action" value="helmetsan_seo_check" />';
            wp_nonce_field('helmetsan_seo_action', '_wpnonce', true, true);
            echo '<input type="submit" class="button" value="' . esc_attr__('Check SEO (sample)', 'helmetsan-core') . '" />';
            echo '</form>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display: inline-block;">';
            echo '<input type="hidden" name="action" value="helmetsan_seo_fix" />';
            wp_nonce_field('helmetsan_seo_action', '_wpnonce', true, true);
            echo '<input type="submit" class="button button-primary" value="' . esc_attr__('Fix SEO (sample)', 'helmetsan-core') . '" onclick="return confirm(\'' . esc_js(__('Fix SEO meta (lowercase focus keyphrase, truncate) for sampled items?', 'helmetsan-core')) . '\');" />';
            echo '</form>';
            $seoCheck = get_transient(self::SEO_CHECK_RESULT_TRANSIENT);
            if (is_array($seoCheck)) {
                $class = (int) ($seoCheck['total_with_issues'] ?? 0) > 0 ? 'notice-warning' : 'notice-success';
                echo '<p class="notice ' . esc_attr($class) . '" style="margin-top: 0.75rem;">' . esc_html($seoCheck['message'] ?? '') . '</p>';
            }
            $seoFix = get_transient(self::SEO_FIX_RESULT_TRANSIENT);
            if (is_array($seoFix)) {
                echo '<p class="notice notice-info" style="margin-top: 0.5rem;">' . esc_html($seoFix['message'] ?? '') . '</p>';
            }
            echo '<p class="description" style="margin-top: 0.5rem;">' . esc_html__('Full run: wp helmetsan seo check --scope=all && wp helmetsan seo update --scope=all', 'helmetsan-core') . '</p>';
            echo '</div>';

            if ($this->accessoryGenerator !== null) {
                echo '<div class="hs-panel" style="max-width: 720px; margin-top: 1.5rem;">';
                echo '<h2 class="title">' . esc_html__('Generate accessories', 'helmetsan-core') . '</h2>';
                echo '<p class="description">' . esc_html__('Generate accessory catalog data (entity, id, title, type, parent_category, price, features) via AI. Use for preview or merge into data/accessories.', 'helmetsan-core') . '</p>';
                echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '" style="display: flex; flex-wrap: wrap; align-items: center; gap: 8px; margin-bottom: 8px;">';
                echo '<input type="hidden" name="action" value="helmetsan_ai_generate_accessories" />';
                wp_nonce_field('helmetsan_ai_gen_accessories', '_wpnonce', true, true);
                echo '<label for="acc_gen_count">' . esc_html__('Count', 'helmetsan-core') . '</label>';
                echo '<input type="number" name="acc_gen_count" id="acc_gen_count" value="5" min="1" max="20" style="width:4em;" />';
                echo '<label for="acc_gen_category">' . esc_html__('Category', 'helmetsan-core') . '</label>';
                echo '<select name="acc_gen_category" id="acc_gen_category"><option value="">' . esc_html__('Any', 'helmetsan-core') . '</option>';
                foreach (AccessoryGeneratorService::getAllowedCategories() as $cat) {
                    echo '<option value="' . esc_attr($cat) . '">' . esc_html($cat) . '</option>';
                }
                echo '</select>';
                echo '<input type="submit" class="button button-primary" value="' . esc_attr__('Generate (preview)', 'helmetsan-core') . '" />';
                echo '</form>';
                $accGenResult = get_transient(self::ACCESSORY_GEN_RESULT_TRANSIENT);
                if (is_array($accGenResult)) {
                    $n = (int) ($accGenResult['generated'] ?? 0);
                    $err = $accGenResult['errors'] ?? [];
                    if ($n > 0) {
                        echo '<p class="notice notice-success">' . esc_html(sprintf(__('Generated %d accessory(ies).', 'helmetsan-core'), $n)) . '</p>';
                        echo '<pre style="max-height:200px;overflow:auto;font-size:11px;">' . esc_html(wp_json_encode($accGenResult['data'] ?? [], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)) . '</pre>';
                    }
                    if ($err !== []) {
                        echo '<p class="notice notice-warning">' . esc_html(implode(' ', array_slice($err, 0, 3))) . '</p>';
                    }
                }
                echo '<p class="description">' . esc_html__('CLI: wp helmetsan ai generate-accessories --count=10 --output=accessories/generated.json', 'helmetsan-core') . '</p>';
                echo '</div>';
            }

            echo '<div class="hs-panel" style="max-width: 720px; margin-top: 1.5rem;">';
            echo '<h2 class="title">' . esc_html__('Fill coverage report', 'helmetsan-core') . '</h2>';
            echo '<p class="description">' . esc_html__('See per-field coverage (set vs empty, % complete) for a post type. No API calls.', 'helmetsan-core') . '</p>';
            echo '<form method="post" action="' . esc_url(admin_url('admin-post.php')) . '">';
            echo '<input type="hidden" name="action" value="helmetsan_ai_fill_coverage" />';
            wp_nonce_field('helmetsan_ai_fill_coverage', '_wpnonce', true, true);
            echo '<label for="fill_coverage_type">' . esc_html__('Post type', 'helmetsan-core') . '</label>';
            echo '<select name="fill_coverage_type" id="fill_coverage_type">';
            foreach ($fillTypes as $t) {
                echo '<option value="' . esc_attr($t) . '">' . esc_html(ucfirst(str_replace('_', ' ', $t))) . '</option>';
            }
            echo '</select>';
            echo ' <input type="submit" class="button" value="' . esc_attr__('Show coverage', 'helmetsan-core') . '" />';
            echo '</form>';
            $coverageResult = get_transient(self::FILL_COVERAGE_RESULT_TRANSIENT);
            if (is_array($coverageResult) && isset($coverageResult['total_posts'], $coverageResult['fields'])) {
                echo '<p class="description" style="margin-top:0.75rem;">' . esc_html(sprintf(__('Total posts: %d', 'helmetsan-core'), (int) $coverageResult['total_posts'])) . '</p>';
                echo '<table class="widefat striped" style="margin-top:0.5rem;"><thead><tr><th>' . esc_html__('Field', 'helmetsan-core') . '</th><th>' . esc_html__('Set', 'helmetsan-core') . '</th><th>' . esc_html__('Empty', 'helmetsan-core') . '</th><th>' . esc_html__('Complete %', 'helmetsan-core') . '</th></tr></thead><tbody>';
                foreach ($coverageResult['fields'] as $field => $counts) {
                    echo '<tr><td><code>' . esc_html($field) . '</code></td><td>' . (int) ($counts['set'] ?? 0) . '</td><td>' . (int) ($counts['empty'] ?? 0) . '</td><td>' . esc_html((string) ($counts['pct'] ?? 0) . '%') . '</td></tr>';
                }
                echo '</tbody></table>';
            }
            echo '<p class="description" style="margin-top:0.5rem;">' . esc_html__('CLI: wp helmetsan ai fill-missing --report --post-type=helmet', 'helmetsan-core') . '</p>';
            echo '</div>';
        }

        echo '<div class="hs-panel" style="max-width: 720px; margin-top: 1.5rem;">';
        echo '<h2 class="title">' . esc_html__('AI commands reference', 'helmetsan-core') . '</h2>';
        echo '<p class="description">' . esc_html__('All WP-CLI commands for the AI module. Run from the site root (e.g. /var/www/helmetsan.com/public) with <code>--allow-root</code> if needed.', 'helmetsan-core') . '</p>';
        echo '<table class="widefat striped" style="margin-top: 0.5rem;"><thead><tr><th style="width:28%;">' . esc_html__('Command', 'helmetsan-core') . '</th><th>' . esc_html__('Options &amp; description', 'helmetsan-core') . '</th></tr></thead><tbody>';

        $ref = [
            [
                'cmd' => 'wp helmetsan api-check',
                'opts' => [
                    '[--live]' => __('Run a live AI request to verify which provider responds.', 'helmetsan-core'),
                    '[--format=table|json]' => __('Output format. Default: table.', 'helmetsan-core'),
                ],
                'desc' => __('Check API connectivity: lists configured AI providers and marketplace connectors; use --live to confirm the API responds.', 'helmetsan-core'),
            ],
            [
                'cmd' => 'wp helmetsan seo seed',
                'opts' => [
                    '[--post-type=helmet|brand|…|all]' => __('Entity type. Includes safety_standard, dealer, distributor, technology, motorcycle, comparison, recommendation. Default: all.', 'helmetsan-core'),
                    '[--scope=posts|terms|all]' => __('posts = CPTs only; terms = taxonomy term archives; all = both. Default: posts.', 'helmetsan-core'),
                    '[--batch-size=&lt;n&gt;]' => __('Posts per batch. Default: 300.', 'helmetsan-core'),
                    '[--limit=&lt;n&gt;]' => __('Max posts per type (0 = no limit). Default: 0.', 'helmetsan-core'),
                    '[--dry-run]' => __('Do not save; only report counts.', 'helmetsan-core'),
                    '[--use-ai]' => __('Use AI for meta descriptions (posts only).', 'helmetsan-core'),
                ],
                'desc' => __('Seed Yoast SEO title, meta description, focus keyword (lowercase) for all CPTs and taxonomy terms. With --use-ai, meta descriptions use the AI module.', 'helmetsan-core'),
            ],
            [
                'cmd' => 'wp helmetsan seo check',
                'opts' => [
                    '[--scope=posts|terms|all]' => __('What to check. Default: posts.', 'helmetsan-core'),
                    '[--post-type=&lt;type&gt;]' => __('For posts: helmet, brand, accessory, safety_standard, dealer, … or all.', 'helmetsan-core'),
                    '[--format=table|count]' => __('table = rows with issues; count = summary only. Default: table.', 'helmetsan-core'),
                ],
                'desc' => __('Check Yoast SEO meta: missing or invalid title, meta description, focus keyphrase (e.g. not lowercase).', 'helmetsan-core'),
            ],
            [
                'cmd' => 'wp helmetsan seo update',
                'opts' => [
                    '[--scope=posts|terms|all]' => __('What to fix. Default: posts.', 'helmetsan-core'),
                    '[--post-type=&lt;type&gt;]' => __('For posts: helmet, brand, … or all.', 'helmetsan-core'),
                    '[--dry-run]' => __('Report what would be fixed without saving.', 'helmetsan-core'),
                ],
                'desc' => __('Fix SEO meta: lowercase focus keyphrase, truncate overlong title and meta description.', 'helmetsan-core'),
            ],
            [
                'cmd' => 'wp helmetsan ai fill-missing',
                'opts' => [
                    '[--post-type=helmet|brand|…|all]' => __('Entity type. Includes safety_standard, dealer, distributor, technology, motorcycle, comparison, recommendation. Default: all.', 'helmetsan-core'),
                    '[--limit=&lt;n&gt;]' => __('Max posts per type (0 = no limit). Default: 50.', 'helmetsan-core'),
                    '[--offset=&lt;n&gt;]' => __('Pagination offset. Default: 0.', 'helmetsan-core'),
                    '[--dry-run]' => __('Do not save; only report filled/skipped/errors.', 'helmetsan-core'),
                    '[--fields=&lt;keys&gt;]' => __('Comma-separated meta keys. Omit for all fillable fields.', 'helmetsan-core'),
                    '[--only-incomplete]' => __('Only process posts with at least one empty fillable field.', 'helmetsan-core'),
                    '[--refill-unmapped]' => __('(Accessory only.) Re-fill type/parent_category for accessories with no category term.', 'helmetsan-core'),
                    '[--verbose]' => __('Log each filled field and each failure.', 'helmetsan-core'),
                    '[--strict]' => __('On empty or invalid AI output, do not retry.', 'helmetsan-core'),
                    '[--no-taxonomies]' => __('Skip filling taxonomy terms.', 'helmetsan-core'),
                    '[--no-cache]' => __('Disable 24h cache.', 'helmetsan-core'),
                    '[--concurrency=&lt;n&gt;]' => __('Parallel processes (single --post-type only). Default: 1.', 'helmetsan-core'),
                    '[--rate-limit=&lt;sec&gt;]' => __('Seconds between API calls. 0 = no sleep. Default: 1.', 'helmetsan-core'),
                    '[--report]' => __('Only print coverage report (per-field set/empty and % complete); no API calls.', 'helmetsan-core'),
                ],
                'desc' => __('Fill missing entity fields (meta and taxonomies) using AI. Use --report for coverage only. Supports all CPTs with fillable config.', 'helmetsan-core'),
            ],
            [
                'cmd' => 'wp helmetsan ai generate-seed',
                'opts' => [
                    '[--count=&lt;n&gt;]' => __('Number of helmet models. Default: 5.', 'helmetsan-core'),
                    '[--brand=&lt;name&gt;]' => __('Restrict to one brand. Can be repeated.', 'helmetsan-core'),
                    '[--output=&lt;path&gt;]' => __('Write JSON to path under data root. Omit for stdout.', 'helmetsan-core'),
                    '[--existing-from=&lt;path&gt;]' => __('Exclude brand/model pairs from this JSON file.', 'helmetsan-core'),
                    '[--dry-run]' => __('Show result only; do not write file.', 'helmetsan-core'),
                ],
                'desc' => __('Generate helmet catalog data (master format) using AI. Merge output into data/helmets/master.json or use as seed source.', 'helmetsan-core'),
            ],
            [
                'cmd' => 'wp helmetsan ai generate-accessories',
                'opts' => [
                    '[--count=&lt;n&gt;]' => __('Number of accessories. Default: 5.', 'helmetsan-core'),
                    '[--category=&lt;name&gt;]' => __('Restrict to one category (e.g. Bluetooth Headsets).', 'helmetsan-core'),
                    '[--output=&lt;path&gt;]' => __('Write JSON under data root. Omit for stdout.', 'helmetsan-core'),
                    '[--dry-run]' => __('Show result only.', 'helmetsan-core'),
                ],
                'desc' => __('Generate accessory catalog data using AI. Ingest with wp helmetsan ingest --path=accessories.', 'helmetsan-core'),
            ],
            [
                'cmd' => 'wp helmetsan ai generate-all',
                'opts' => [
                    '[--helmets-count=&lt;n&gt;]' => __('Helmet models to generate. 0 = skip. Default: 5.', 'helmetsan-core'),
                    '[--accessories-count=&lt;n&gt;]' => __('Accessories to generate. 0 = skip. Default: 5.', 'helmetsan-core'),
                    '[--output-dir=&lt;path&gt;]' => __('Write helmets/generated.json and accessories/generated.json under data root.', 'helmetsan-core'),
                    '[--dry-run]' => __('Run both generators; do not write files.', 'helmetsan-core'),
                ],
                'desc' => __('Generate both helmets and accessories in one run (separate AI calls).', 'helmetsan-core'),
            ],
            [
                'cmd' => 'wp helmetsan ai cross-link',
                'opts' => [
                    '[--post-type=helmet|brand|accessory|all]' => __('Entity type. Default: all.', 'helmetsan-core'),
                    '[--limit=&lt;n&gt;]' => __('Max posts per type (0 = no limit). Default: 0.', 'helmetsan-core'),
                    '[--offset=&lt;n&gt;]' => __('Pagination offset. Default: 0.', 'helmetsan-core'),
                    '[--dry-run]' => __('Do not save; only report counts.', 'helmetsan-core'),
                    '[--report]' => __('Print analytic report: links by reason, total links, avg per post.', 'helmetsan-core'),
                ],
                'desc' => __('Suggest and write internal links (outgoing_internal_links_json) for entities. Use --report for stats.', 'helmetsan-core'),
            ],
        ];

        foreach ($ref as $row) {
            echo '<tr><td><code style="white-space:nowrap;">' . esc_html($row['cmd']) . '</code></td><td>';
            echo '<p class="description" style="margin:0 0 0.5rem 0;">' . esc_html($row['desc']) . '</p>';
            echo '<dl style="margin:0; font-size:12px;">';
            foreach ($row['opts'] as $opt => $label) {
                echo '<dt style="margin-top:4px;"><code>' . esc_html($opt) . '</code></dt><dd style="margin-left:1em; color:#50575e;">' . esc_html($label) . '</dd>';
            }
            echo '</dl></td></tr>';
        }
        echo '</tbody></table>';
        echo '<p class="description" style="margin-top:0.75rem;">' . esc_html__('Environment fallback: set HELMETSAN_REFILL_UNMAPPED=1 to enable refill-unmapped behavior for fill-missing if your deployment does not yet support the --refill-unmapped flag.', 'helmetsan-core') . '</p>';
        echo '</div>';

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
        $postType = isset($_POST['fill_type']) ? sanitize_key((string) $_POST['fill_type']) : 'helmet';
        if (FillableFieldsConfig::forPostType($postType) === []) {
            $postType = 'helmet';
        }
        $service = new FillMissingService($this->aiService);
        $result = $service->run($postType, 10, 0, true, null, true, false, true, null, null, 0, 0);
        set_transient(self::FILL_RESULT_TRANSIENT, array_merge($result, ['dry' => true, 'post_type' => $postType]), 3600);
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
        $postType = isset($_POST['fill_type']) ? sanitize_key((string) $_POST['fill_type']) : 'helmet';
        if (FillableFieldsConfig::forPostType($postType) === []) {
            $postType = 'helmet';
        }
        $service = new FillMissingService($this->aiService);
        $result = $service->run($postType, 10, 0, false, null, true, false, true, null, null, 86400, null);
        set_transient(self::FILL_RESULT_TRANSIENT, array_merge($result, ['dry' => false, 'post_type' => $postType]), 3600);
        wp_safe_redirect(admin_url('admin.php?page=helmetsan-ai&fill_done=1'));
        exit;
    }

    public function handleGenerateAccessories(): void
    {
        if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'helmetsan_ai_gen_accessories')) {
            wp_die(esc_html__('Security check failed.', 'helmetsan-core'));
        }
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission.', 'helmetsan-core'));
        }
        if ($this->accessoryGenerator === null) {
            set_transient(self::ACCESSORY_GEN_RESULT_TRANSIENT, ['generated' => 0, 'errors' => [__('Accessory generator not available.', 'helmetsan-core')], 'data' => []], 300);
            wp_safe_redirect(admin_url('admin.php?page=helmetsan-ai&acc_gen_done=1'));
            exit;
        }
        $count = isset($_POST['acc_gen_count']) ? max(1, min(20, (int) $_POST['acc_gen_count'])) : 5;
        $category = isset($_POST['acc_gen_category']) ? trim(sanitize_text_field(wp_unslash($_POST['acc_gen_category']))) : '';
        $categories = $category !== '' ? [$category] : [];
        $result = $this->accessoryGenerator->generate($count, $categories, null, []);
        set_transient(self::ACCESSORY_GEN_RESULT_TRANSIENT, $result, 300);
        wp_safe_redirect(admin_url('admin.php?page=helmetsan-ai&acc_gen_done=1'));
        exit;
    }

    public function handleFillCoverage(): void
    {
        if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'helmetsan_ai_fill_coverage')) {
            wp_die(esc_html__('Security check failed.', 'helmetsan-core'));
        }
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission.', 'helmetsan-core'));
        }
        $postType = isset($_POST['fill_coverage_type']) ? sanitize_key((string) $_POST['fill_coverage_type']) : 'helmet';
        if (FillableFieldsConfig::forPostType($postType) === []) {
            $postType = 'helmet';
        }
        $service = new FillMissingService($this->aiService);
        $report = $service->getCoverageReport($postType, 0);
        set_transient(self::FILL_COVERAGE_RESULT_TRANSIENT, $report, 600);
        wp_safe_redirect(admin_url('admin.php?page=helmetsan-ai&coverage_done=1'));
        exit;
    }

    /** Per-click limit; with 10min timeout and ~1s per API call, ~50 helmets × 3 fields is safe. Use CLI for 100+. */
    private const CATALOG_AI_LIMIT = 50;

    /** Allow long-running AI requests (PHP only; nginx may still need a higher fastcgi_read_timeout on the server). */
    private function allowLongRun(): void
    {
        if (function_exists('set_time_limit')) {
            @set_time_limit(600);
        }
    }

    public function handleCatalogAiFillAll(): void
    {
        if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'helmetsan_catalog_ai')) {
            set_transient(self::CATALOG_AI_RESULT_TRANSIENT, ['scope' => 'all', 'error' => __('Security check failed.', 'helmetsan-core')], 300);
            wp_safe_redirect(admin_url('admin.php?page=helmetsan-catalog&catalog_ai_done=1'));
            exit;
        }
        if (! current_user_can('manage_options')) {
            set_transient(self::CATALOG_AI_RESULT_TRANSIENT, ['scope' => 'all', 'error' => __('You do not have permission.', 'helmetsan-core')], 300);
            wp_safe_redirect(admin_url('admin.php?page=helmetsan-catalog&catalog_ai_done=1'));
            exit;
        }
        $this->allowLongRun();
        if (! $this->aiService->hasAnyConfiguredProvider()) {
            set_transient(self::CATALOG_AI_RESULT_TRANSIENT, ['scope' => 'all', 'error' => __('No AI provider configured. Go to Helmetsan → AI to add an API key.', 'helmetsan-core')], 300);
            wp_safe_redirect(admin_url('admin.php?page=helmetsan-catalog&catalog_ai_done=1'));
            exit;
        }
        $service = new FillMissingService($this->aiService);
        // onlyIncomplete=false so we always process up to LIMIT helmets; service skips already-filled fields
        $result = $service->run('helmet', self::CATALOG_AI_LIMIT, 0, false, null, false, false, true, null, null, 86400, null, false, null);
        set_transient(self::CATALOG_AI_RESULT_TRANSIENT, array_merge($result, ['scope' => 'all']), 300);
        wp_safe_redirect(admin_url('admin.php?page=helmetsan-catalog&catalog_ai_done=1'));
        exit;
    }

    public function handleCatalogAiFillCerts(): void
    {
        if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'helmetsan_catalog_ai')) {
            set_transient(self::CATALOG_AI_RESULT_TRANSIENT, ['scope' => 'certs', 'error' => __('Security check failed.', 'helmetsan-core')], 300);
            wp_safe_redirect(admin_url('admin.php?page=helmetsan-catalog&catalog_ai_done=1'));
            exit;
        }
        if (! current_user_can('manage_options')) {
            set_transient(self::CATALOG_AI_RESULT_TRANSIENT, ['scope' => 'certs', 'error' => __('You do not have permission.', 'helmetsan-core')], 300);
            wp_safe_redirect(admin_url('admin.php?page=helmetsan-catalog&catalog_ai_done=1'));
            exit;
        }
        $this->allowLongRun();
        if (! $this->aiService->hasAnyConfiguredProvider()) {
            set_transient(self::CATALOG_AI_RESULT_TRANSIENT, ['scope' => 'certs', 'error' => __('No AI provider configured. Go to Helmetsan → AI to add an API key.', 'helmetsan-core')], 300);
            wp_safe_redirect(admin_url('admin.php?page=helmetsan-catalog&catalog_ai_done=1'));
            exit;
        }
        $service = new FillMissingService($this->aiService);
        $result = $service->run('helmet', self::CATALOG_AI_LIMIT, 0, false, [], false, false, true, null, null, 86400, null, false, ['certification']);
        set_transient(self::CATALOG_AI_RESULT_TRANSIENT, array_merge($result, ['scope' => 'certs']), 300);
        wp_safe_redirect(admin_url('admin.php?page=helmetsan-catalog&catalog_ai_done=1'));
        exit;
    }

    public function handleCatalogAiFillSpecs(): void
    {
        if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'helmetsan_catalog_ai')) {
            set_transient(self::CATALOG_AI_RESULT_TRANSIENT, ['scope' => 'specs', 'error' => __('Security check failed.', 'helmetsan-core')], 300);
            wp_safe_redirect(admin_url('admin.php?page=helmetsan-catalog&catalog_ai_done=1'));
            exit;
        }
        if (! current_user_can('manage_options')) {
            set_transient(self::CATALOG_AI_RESULT_TRANSIENT, ['scope' => 'specs', 'error' => __('You do not have permission.', 'helmetsan-core')], 300);
            wp_safe_redirect(admin_url('admin.php?page=helmetsan-catalog&catalog_ai_done=1'));
            exit;
        }
        $this->allowLongRun();
        if (! $this->aiService->hasAnyConfiguredProvider()) {
            set_transient(self::CATALOG_AI_RESULT_TRANSIENT, ['scope' => 'specs', 'error' => __('No AI provider configured. Go to Helmetsan → AI to add an API key.', 'helmetsan-core')], 300);
            wp_safe_redirect(admin_url('admin.php?page=helmetsan-catalog&catalog_ai_done=1'));
            exit;
        }
        $service = new FillMissingService($this->aiService);
        $specFields = ['spec_weight_g', 'spec_shell_material', 'price_retail_usd'];
        // onlyIncomplete=false so we process up to LIMIT helmets; service skips fields that already have values
        $result = $service->run('helmet', self::CATALOG_AI_LIMIT, 0, false, $specFields, false, false, false, null, null, 86400, null, false, null, false);
        set_transient(self::CATALOG_AI_RESULT_TRANSIENT, array_merge($result, ['scope' => 'specs']), 300);
        wp_safe_redirect(admin_url('admin.php?page=helmetsan-catalog&catalog_ai_done=1'));
        exit;
    }

    /** Update specs (weight, shell, price) and overwrite existing values with AI. */
    public function handleCatalogAiFillSpecsOverwrite(): void
    {
        if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'helmetsan_catalog_ai')) {
            set_transient(self::CATALOG_AI_RESULT_TRANSIENT, ['scope' => 'specs_overwrite', 'error' => __('Security check failed.', 'helmetsan-core')], 300);
            wp_safe_redirect(admin_url('admin.php?page=helmetsan-catalog&catalog_ai_done=1'));
            exit;
        }
        if (! current_user_can('manage_options')) {
            set_transient(self::CATALOG_AI_RESULT_TRANSIENT, ['scope' => 'specs_overwrite', 'error' => __('You do not have permission.', 'helmetsan-core')], 300);
            wp_safe_redirect(admin_url('admin.php?page=helmetsan-catalog&catalog_ai_done=1'));
            exit;
        }
        $this->allowLongRun();
        if (! $this->aiService->hasAnyConfiguredProvider()) {
            set_transient(self::CATALOG_AI_RESULT_TRANSIENT, ['scope' => 'specs_overwrite', 'error' => __('No AI provider configured. Go to Helmetsan → AI to add an API key.', 'helmetsan-core')], 300);
            wp_safe_redirect(admin_url('admin.php?page=helmetsan-catalog&catalog_ai_done=1'));
            exit;
        }
        $service = new FillMissingService($this->aiService);
        $specFields = ['spec_weight_g', 'spec_shell_material', 'price_retail_usd'];
        $result = $service->run('helmet', self::CATALOG_AI_LIMIT, 0, false, $specFields, false, false, false, null, null, 86400, null, false, null, true);
        set_transient(self::CATALOG_AI_RESULT_TRANSIENT, array_merge($result, ['scope' => 'specs_overwrite']), 300);
        wp_safe_redirect(admin_url('admin.php?page=helmetsan-catalog&catalog_ai_done=1'));
        exit;
    }

    private const BRAND_AI_LIMIT = 100;

    /** Brand AI: fill all missing/outdated brand fields (up to BRAND_AI_LIMIT). */
    public function handleBrandAiFillAll(): void
    {
        if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'helmetsan_brand_ai')) {
            wp_die(esc_html__('Security check failed.', 'helmetsan-core'));
        }
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission.', 'helmetsan-core'));
        }
        $this->allowLongRun();
        if (! $this->aiService->hasAnyConfiguredProvider()) {
            set_transient(self::BRAND_AI_RESULT_TRANSIENT, ['scope' => 'all', 'error' => __('No AI provider configured. Go to Helmetsan → AI to add an API key.', 'helmetsan-core')], 300);
            wp_safe_redirect(admin_url('admin.php?page=helmetsan-brands&brand_ai_done=1'));
            exit;
        }
        $service = new FillMissingService($this->aiService);
        $result = $service->run('brand', self::BRAND_AI_LIMIT, 0, false, null, false, false, true, null, null, 86400, null, false, null);
        set_transient(self::BRAND_AI_RESULT_TRANSIENT, array_merge($result, ['scope' => 'all']), 300);
        wp_safe_redirect(admin_url('admin.php?page=helmetsan-brands&brand_ai_done=1'));
        exit;
    }

    /** Brand AI: fill only key fields (total models, helmet types, cert coverage, support URL, warranty, origin). */
    public function handleBrandAiFillKey(): void
    {
        if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'helmetsan_brand_ai')) {
            wp_die(esc_html__('Security check failed.', 'helmetsan-core'));
        }
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission.', 'helmetsan-core'));
        }
        $this->allowLongRun();
        if (! $this->aiService->hasAnyConfiguredProvider()) {
            set_transient(self::BRAND_AI_RESULT_TRANSIENT, ['scope' => 'key', 'error' => __('No AI provider configured. Go to Helmetsan → AI to add an API key.', 'helmetsan-core')], 300);
            wp_safe_redirect(admin_url('admin.php?page=helmetsan-brands&brand_ai_done=1'));
            exit;
        }
        $keyFields = ['brand_total_models', 'brand_helmet_types', 'brand_certification_coverage', 'brand_support_url', 'brand_warranty_terms', 'brand_origin_country'];
        $service = new FillMissingService($this->aiService);
        $result = $service->run('brand', self::BRAND_AI_LIMIT, 0, false, $keyFields, false, false, false, null, null, 86400, null, false, null);
        set_transient(self::BRAND_AI_RESULT_TRANSIENT, array_merge($result, ['scope' => 'key']), 300);
        wp_safe_redirect(admin_url('admin.php?page=helmetsan-brands&brand_ai_done=1'));
        exit;
    }

    public function handleSeoCheck(): void
    {
        if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'helmetsan_seo_action')) {
            wp_die(esc_html__('Security check failed.', 'helmetsan-core'));
        }
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission.', 'helmetsan-core'));
        }
        $seeder = new YoastSeoSeeder(null);
        $result = $seeder->runCheckSummary(self::SEO_SAMPLE_LIMIT, self::SEO_TERM_LIMIT);
        set_transient(self::SEO_CHECK_RESULT_TRANSIENT, $result, 3600);
        wp_safe_redirect(admin_url('admin.php?page=helmetsan-ai&seo_done=check'));
        exit;
    }

    public function handleSeoFix(): void
    {
        if (! isset($_POST['_wpnonce']) || ! wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['_wpnonce'])), 'helmetsan_seo_action')) {
            wp_die(esc_html__('Security check failed.', 'helmetsan-core'));
        }
        if (! current_user_can('manage_options')) {
            wp_die(esc_html__('You do not have permission.', 'helmetsan-core'));
        }
        $seeder = new YoastSeoSeeder(null);
        $result = $seeder->runFixSummary(self::SEO_SAMPLE_LIMIT, self::SEO_TERM_LIMIT);
        set_transient(self::SEO_FIX_RESULT_TRANSIENT, $result, 3600);
        wp_safe_redirect(admin_url('admin.php?page=helmetsan-ai&seo_done=fix'));
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
            'together' => 'Together AI',
            'fireworks' => 'Fireworks AI',
            'cohere' => 'Cohere',
            'lm_studio' => 'LM Studio (local)',
            'openai' => 'OpenAI (ChatGPT)',
            'anthropic' => 'Anthropic (Claude)',
            'perplexity' => 'Perplexity',
            default => $id,
        };
    }

    /** Short "best for" description for admin provider table. */
    private function providerBestFor(string $id): string
    {
        return match ($id) {
            'groq' => 'Meta descriptions, fill-missing (fast, free tier)',
            'gemini' => 'Meta descriptions, longer copy (free tier)',
            'mistral' => 'Meta descriptions, fill-missing (good balance)',
            'openrouter' => 'Many models via one key (pay-per-use)',
            'huggingface' => 'Fill-missing, classification (rate limits)',
            'together' => 'Fast inference, fill-missing (free/low-cost)',
            'fireworks' => 'Fast inference, many OSS models (free tier)',
            'cohere' => 'Classification, short text (free tier)',
            'lm_studio' => 'Local LLM (Zed, LM Studio); no API key',
            'openai' => 'Highest quality when cost justified',
            'anthropic' => 'Nuanced copy, long context',
            'perplexity' => 'Research-style queries',
            default => '—',
        };
    }
}
