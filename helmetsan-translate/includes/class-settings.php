<?php
declare(strict_types=1);

namespace HelmetsanTranslate;

defined('ABSPATH') || exit;

class Settings {
    private static ?Settings $instance = null;
    public const OPTION_NAME = 'helmetsan_translate_options';

    public static function instance(): self {
        return self::$instance ??= new self();
    }

    private function __construct() {
        add_action('admin_menu', [$this, 'register_admin_menu']);
        add_action('admin_init', [$this, 'handle_save_settings']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_admin_assets']);
        add_action('wp_ajax_hs_fetch_provider_models', [$this, 'ajax_fetch_provider_models']);
        add_action('wp_ajax_hs_test_provider_connection', [$this, 'ajax_test_provider_connection']);
    }

    public static function init_defaults(): void {
        if (get_option(self::OPTION_NAME) !== false) return;

        $defaults = [
            'providers' => [
                'mercury' => [
                    'name' => 'Inception Labs (Mercury-3 dLLM)',
                    'enabled' => 1,
                    'api_key' => '',
                    'base_url' => 'https://api.inceptionlabs.ai/v1/chat/completions',
                    'models_url' => 'https://api.inceptionlabs.ai/v1/models',
                    'model' => 'mercury-3',
                    'priority' => 1,
                    'limits' => 'Sub-second (698ms), 1M Context Window, $0.10/M tokens',
                    'best_for' => 'Primary high-speed volume batching',
                ],
                'gemini' => [
                    'name' => 'Google Gemini Flash Lite',
                    'enabled' => 1,
                    'api_key' => '',
                    'base_url' => 'https://generativelanguage.googleapis.com/v1beta/models',
                    'models_url' => 'https://generativelanguage.googleapis.com/v1beta/models',
                    'model' => 'gemini-3.1-flash-lite',
                    'priority' => 2,
                    'limits' => '1,500 RPM, 1M TPM, 1,500 req/day Free Tier',
                    'best_for' => 'Large batches (25-50 items) & long-form blog guides',
                ],
                'luna' => [
                    'name' => 'Experiential Labs (GPT-6-Luna)',
                    'enabled' => 1,
                    'api_key' => '',
                    'base_url' => 'https://api.experientiallabs.ai/v1/chat/completions',
                    'models_url' => 'https://api.experientiallabs.ai/v1/models',
                    'model' => 'gpt-6-luna',
                    'priority' => 5,
                    'limits' => 'SolarQnA Pro Plan (Dedicated Arbiter), 1.05M Context Window',
                    'best_for' => 'Frontier editorial arbitration & deep contextual review',
                ],
                'cerebras' => [
                    'name' => 'Cerebras Cloud',
                    'enabled' => 1,
                    'api_key' => '',
                    'base_url' => 'https://api.cerebras.ai/v1/chat/completions',
                    'models_url' => 'https://api.cerebras.ai/v1/models',
                    'model' => 'gpt-oss-120b',
                    'priority' => 3,
                    'limits' => '30 RPM, 60,000 TPM, Free Allowance',
                    'best_for' => 'Sub-second (350ms) structured JSON & CJK translation',
                ],
                'groq' => [
                    'name' => 'Groq Fast Inference',
                    'enabled' => 1,
                    'api_key' => '',
                    'base_url' => 'https://api.groq.com/openai/v1/chat/completions',
                    'models_url' => 'https://api.groq.com/openai/v1/models',
                    'model' => 'qwen/qwen3.8-27b',
                    'priority' => 4,
                    'limits' => '30 RPM, 6,000 TPM, 14,400 RPD Free',
                    'best_for' => 'Low-latency JSON failover',
                ],
                'sambanova' => [
                    'name' => 'SambaNova Cloud',
                    'enabled' => 1,
                    'api_key' => '',
                    'base_url' => 'https://api.sambanova.ai/v1/chat/completions',
                    'models_url' => 'https://api.sambanova.ai/v1/models',
                    'model' => 'Meta-Llama-3.3-70B-Instruct',
                    'priority' => 3,
                    'limits' => '20 RPM, 50,000 TPM, Free Tier (220 tokens/sec)',
                    'best_for' => 'Nuanced 70B parameter multilingual reasoning',
                ],
                'mistral' => [
                    'name' => 'Mistral AI',
                    'enabled' => 1,
                    'api_key' => '',
                    'base_url' => 'https://api.mistral.ai/v1/chat/completions',
                    'models_url' => 'https://api.mistral.ai/v1/models',
                    'model' => 'ministral-8b-latest',
                    'priority' => 3,
                    'limits' => '60 RPM, 500,000 TPM',
                    'best_for' => 'European romance languages (FR, ES, IT, PT, DE)',
                ],
                'openrouter' => [
                    'name' => 'OpenRouter (Free & Low Cost)',
                    'enabled' => 0,
                    'api_key' => '',
                    'base_url' => 'https://openrouter.ai/api/v1/chat/completions',
                    'models_url' => 'https://openrouter.ai/api/v1/models',
                    'model' => 'deepseek/deepseek-r1:free',
                    'priority' => 5,
                    'limits' => 'Free tier models (:free) available',
                    'best_for' => 'Multi-vendor backup routing',
                ],
                'deepseek' => [
                    'name' => 'DeepSeek Direct API',
                    'enabled' => 0,
                    'api_key' => '',
                    'base_url' => 'https://api.deepseek.com/v1/chat/completions',
                    'models_url' => 'https://api.deepseek.com/v1/models',
                    'model' => 'deepseek-chat',
                    'priority' => 4,
                    'limits' => '60 RPM, 200,000 TPM, $0.14/M tokens',
                    'best_for' => 'Cost-effective whole-spec translation',
                ],
                'cloudflare' => [
                    'name' => 'Cloudflare Workers AI',
                    'enabled' => 0,
                    'api_key' => '',
                    'account_id' => 'a06a4ae5652491dfb8376dc23488f6a0',
                    'base_url' => 'https://api.cloudflare.com/client/v4/accounts/{account_id}/ai/run',
                    'models_url' => 'https://api.cloudflare.com/client/v4/accounts/{account_id}/ai/models/search',
                    'model' => '@cf/meta/llama-3.1-8b-instruct',
                    'priority' => 6,
                    'limits' => '10,000 daily neurons free',
                    'best_for' => 'Edge execution & m2m100 fallback',
                ],
            ],
            'redis' => [
                'enabled' => 1,
                'host' => '127.0.0.1',
                'port' => 6379,
                'db' => 0,
                'prefix' => 'hs:tm:',
            ],
            'cloudflare' => [
                'purge_enabled' => 1,
                'zone_id' => '',
                'api_token' => '',
                'd1_enabled' => 0,
                'd1_database_id' => '',
            ],
            'policies' => [
                'auto_translate_on_save' => 1,
                'debounce_seconds' => 30,
                'concurrency' => 5,
                'circuit_breaker_limit' => 1000,
                'post_types' => ['helmet', 'motorcycle', 'post', 'brand', 'safety_standard', 'accessory'],
                'slug_policy' => 'smart', // smart: canonical for specs, translated for blog/pages
            ],
            'seo' => [
                'sync_yoast' => 1,
                'translate_image_alts' => 1,
                'indexnow_enabled' => 1,
                'indexnow_key' => 'c9a72e8140db4e5fb3d6812975ef83a0',
                'meta_whitelist' => '_hs_editorial_summary, _hs_fit_notes, _yoast_wpseo_title, _yoast_wpseo_metadesc',
                'meta_copy_as_is' => '_hs_helmet_price, _hs_weight_grams, _hs_noise_db, _thumbnail_id',
            ],
        ];

        update_option(self::OPTION_NAME, $defaults);
    }

    public static function get_options(): array {
        return get_option(self::OPTION_NAME, []);
    }

    public function register_admin_menu(): void {
        add_submenu_page(
            'edit.php?post_type=helmet',
            __('Translation Settings', 'helmetsan-translate'),
            __('Translations', 'helmetsan-translate'),
            'manage_options',
            'helmetsan-translate-settings',
            [$this, 'render_settings_page']
        );
    }

    public function enqueue_admin_assets(string $hook): void {
        if (!str_contains($hook, 'helmetsan-translate-settings')) return;

        wp_enqueue_style(
            'hs-translate-admin-css',
            HELMETSAN_TRANSLATE_URL . 'admin/assets/settings.css',
            [],
            HELMETSAN_TRANSLATE_VERSION
        );

        wp_enqueue_script(
            'hs-translate-admin-js',
            HELMETSAN_TRANSLATE_URL . 'admin/assets/settings.js',
            ['jquery'],
            HELMETSAN_TRANSLATE_VERSION,
            true
        );

        wp_localize_script('hs-translate-admin-js', 'hsTranslateData', [
            'ajax_url' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('hs_translate_settings_nonce'),
        ]);
    }

    public function handle_save_settings(): void {
        if (!isset($_POST['hs_save_settings_nonce']) || !wp_verify_nonce($_POST['hs_save_settings_nonce'], 'hs_save_settings_action')) {
            return;
        }

        if (!current_user_can('manage_options')) {
            wp_die(__('Unauthorized user', 'helmetsan-translate'));
        }

        $current = self::get_options();
        $input = $_POST['hs_options'] ?? [];

        // 1. Process Providers
        if (isset($input['providers']) && is_array($input['providers'])) {
            foreach ($input['providers'] as $key => $prov) {
                if (!isset($current['providers'][$key])) continue;

                $current['providers'][$key]['enabled'] = isset($prov['enabled']) ? 1 : 0;
                $current['providers'][$key]['model'] = sanitize_text_field($prov['model'] ?? '');
                $current['providers'][$key]['priority'] = (int) ($prov['priority'] ?? 3);
                $current['providers'][$key]['base_url'] = esc_url_raw($prov['base_url'] ?? '');

                // Only update API key if a non-masked value was submitted
                if (!empty($prov['api_key']) && !str_contains($prov['api_key'], '••••••••')) {
                    $current['providers'][$key]['api_key'] = Encryption::encrypt(trim($prov['api_key']));
                }
            }
        }

        // 2. Process Redis
        if (isset($input['redis'])) {
            $current['redis']['enabled'] = isset($input['redis']['enabled']) ? 1 : 0;
            $current['redis']['host'] = sanitize_text_field($input['redis']['host'] ?? '127.0.0.1');
            $current['redis']['port'] = (int) ($input['redis']['port'] ?? 6379);
        }

        // 3. Process Cloudflare
        if (isset($input['cloudflare'])) {
            $current['cloudflare']['purge_enabled'] = isset($input['cloudflare']['purge_enabled']) ? 1 : 0;
            $current['cloudflare']['zone_id'] = sanitize_text_field($input['cloudflare']['zone_id'] ?? '');
            if (!empty($input['cloudflare']['api_token']) && !str_contains($input['cloudflare']['api_token'], '••••••••')) {
                $current['cloudflare']['api_token'] = Encryption::encrypt(trim($input['cloudflare']['api_token']));
            }
        }

        // 4. Process Policies
        if (isset($input['policies'])) {
            $current['policies']['auto_translate_on_save'] = isset($input['policies']['auto_translate_on_save']) ? 1 : 0;
            $current['policies']['debounce_seconds'] = max(5, (int) ($input['policies']['debounce_seconds'] ?? 30));
            $current['policies']['concurrency'] = max(1, min(20, (int) ($input['policies']['concurrency'] ?? 5)));
            $current['policies']['post_types'] = array_map('sanitize_key', $input['policies']['post_types'] ?? []);
        }

        // 5. Process SEO
        if (isset($input['seo'])) {
            $current['seo']['sync_yoast'] = isset($input['seo']['sync_yoast']) ? 1 : 0;
            $current['seo']['translate_image_alts'] = isset($input['seo']['translate_image_alts']) ? 1 : 0;
            $current['seo']['indexnow_enabled'] = isset($input['seo']['indexnow_enabled']) ? 1 : 0;
            $current['seo']['indexnow_key'] = sanitize_text_field($input['seo']['indexnow_key'] ?? '');
            $current['seo']['meta_whitelist'] = sanitize_text_field($input['seo']['meta_whitelist'] ?? '');
            $current['seo']['meta_copy_as_is'] = sanitize_text_field($input['seo']['meta_copy_as_is'] ?? '');
        }

        update_option(self::OPTION_NAME, $current);

        // Sync configuration snapshot to daemon config file
        self::sync_daemon_config($current);

        add_settings_error('hs_translate_messages', 'hs_translate_saved', __('Settings saved successfully and synced with translation daemon.', 'helmetsan-translate'), 'updated');
    }

    public static function sync_daemon_config(array $options): void {
        $daemon_config_path = '/var/www/helmetsan.com/secrets/ai_translation_config.json';
        if (!file_exists(dirname($daemon_config_path))) {
            $daemon_config_path = HELMETSAN_TRANSLATE_DIR . 'secrets/ai_translation_config.json';
        }

        $export = ['providers' => []];
        foreach ($options['providers'] as $id => $p) {
            $export['providers'][$id] = [
                'name' => $p['name'],
                'enabled' => $p['enabled'],
                'api_key' => Encryption::decrypt($p['api_key'] ?? ''),
                'base_url' => $p['base_url'],
                'default_model' => $p['model'],
                'priority' => $p['priority']
            ];
        }

        @file_put_contents($daemon_config_path, json_encode($export, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function ajax_fetch_provider_models(): void {
        check_ajax_referer('hs_translate_settings_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Forbidden']);
        }

        $provider_key = sanitize_key($_POST['provider'] ?? '');
        $api_key_input = trim($_POST['api_key'] ?? '');
        $options = self::get_options();

        $provider = $options['providers'][$provider_key] ?? null;
        if (!$provider) {
            wp_send_json_error(['message' => 'Provider not recognized']);
        }

        $api_key = (!empty($api_key_input) && !str_contains($api_key_input, '••••••••'))
            ? $api_key_input
            : Encryption::decrypt($provider['api_key'] ?? '');

        if (empty($api_key)) {
            wp_send_json_error(['message' => 'No API key provided']);
        }

        $models_url = $provider['models_url'] ?? '';
        if (empty($models_url)) {
            wp_send_json_error(['message' => 'No models discovery endpoint configured for this provider']);
        }

        // Fetch models
        $args = [
            'timeout' => 10,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ]
        ];

        // Specific handling for Gemini REST
        if ($provider_key === 'gemini') {
            $models_url = "{$models_url}?key={$api_key}";
            unset($args['headers']['Authorization']);
        }

        $response = wp_remote_get($models_url, $args);
        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message()]);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);
        $data = json_decode($body, true);

        if ($code !== 200) {
            wp_send_json_error(['message' => "HTTP {$code}: " . ($data['error']['message'] ?? $body)]);
        }

        $models = [];
        if (isset($data['data']) && is_array($data['data'])) {
            foreach ($data['data'] as $item) {
                if (isset($item['id'])) $models[] = $item['id'];
            }
        } elseif (isset($data['models']) && is_array($data['models'])) { // Gemini format
            foreach ($data['models'] as $item) {
                if (isset($item['name'])) {
                    $models[] = str_replace('models/', '', $item['name']);
                }
            }
        }

        sort($models);
        wp_send_json_success(['models' => $models, 'count' => count($models)]);
    }

    public function ajax_test_provider_connection(): void {
        check_ajax_referer('hs_translate_settings_nonce', 'nonce');
        if (!current_user_can('manage_options')) {
            wp_send_json_error(['message' => 'Forbidden']);
        }

        $provider_key = sanitize_key($_POST['provider'] ?? '');
        $options = self::get_options();
        $provider = $options['providers'][$provider_key] ?? null;

        if (!$provider) {
            wp_send_json_error(['message' => 'Provider not recognized']);
        }

        $api_key = Encryption::decrypt($provider['api_key'] ?? '');
        if (empty($api_key)) {
            wp_send_json_error(['message' => 'API Key is empty']);
        }

        $start = microtime(true);
        $payload = [
            'model' => $provider['model'],
            'messages' => [
                ['role' => 'user', 'content' => 'Translate to German: Helmet. Return JSON: {"test": "Helm"}']
            ],
            'temperature' => 0.1,
            'max_tokens' => 50
        ];

        $args = [
            'timeout' => 12,
            'headers' => [
                'Authorization' => 'Bearer ' . $api_key,
                'Content-Type' => 'application/json'
            ],
            'body' => json_encode($payload)
        ];

        if ($provider_key === 'gemini') {
            $url = "{$provider['base_url']}/{$provider['model']}:generateContent?key={$api_key}";
            unset($args['headers']['Authorization']);
            $args['body'] = json_encode([
                'contents' => [['role' => 'user', 'parts' => [['text' => 'Translate to German: Helmet. Return JSON: {"test": "Helm"}']]]]
            ]);
        } else {
            $url = $provider['base_url'];
        }

        $response = wp_remote_post($url, $args);
        $latency = round((microtime(true) - $start) * 1000);

        if (is_wp_error($response)) {
            wp_send_json_error(['message' => $response->get_error_message(), 'latency' => $latency]);
        }

        $code = wp_remote_retrieve_response_code($response);
        $body = wp_remote_retrieve_body($response);

        if ($code === 200) {
            wp_send_json_success([
                'status' => 'online',
                'latency' => $latency,
                'message' => "Connected successfully ({$latency}ms)"
            ]);
        } else {
            $err = json_decode($body, true);
            $msg = $err['error']['message'] ?? "HTTP {$code}";
            wp_send_json_error([
                'status' => 'error',
                'latency' => $latency,
                'message' => "Error ({$code}): {$msg}"
            ]);
        }
    }

    public function render_settings_page(): void {
        require_once HELMETSAN_TRANSLATE_DIR . 'admin/views/settings-view.php';
    }
}
