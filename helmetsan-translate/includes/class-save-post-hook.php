<?php
declare(strict_types=1);

namespace HelmetsanTranslate;

defined('ABSPATH') || exit;

class SavePostHook {
    private static ?SavePostHook $instance = null;
    private static bool $is_translating = false;

    public static function instance(): self {
        return self::$instance ??= new self();
    }

    private function __construct() {
        add_action('save_post', [$this, 'on_save_post'], 20, 2);
        add_action('add_meta_boxes', [$this, 'register_meta_box']);
        add_action('save_post', [$this, 'save_meta_box_data']);
        add_action('wp_trash_post', [$this, 'on_trash_post']);
        add_action('untrash_post', [$this, 'on_untrash_post']);
    }

    public function on_save_post(int $post_id, \WP_Post $post): void {
        if (self::$is_translating) return;
        if (wp_is_post_autosave($post_id) || wp_is_post_revision($post_id)) return;
        if ($post->post_status === 'auto-draft' || $post->post_status === 'trash') return;

        $options = Settings::get_options();
        if (empty($options['policies']['auto_translate_on_save'])) return;

        $allowed_types = $options['policies']['post_types'] ?? ['helmet', 'motorcycle', 'post', 'brand', 'safety_standard', 'accessory'];
        if (!in_array($post->post_type, $allowed_types, true)) return;

        // Verify that the post is in English
        if (function_exists('pll_get_post_language')) {
            $lang = pll_get_post_language($post_id, 'slug');
            if ($lang !== 'en') return;
        }

        // Check if translation is locked by an editor
        if (get_post_meta($post_id, '_hs_translation_locked', true)) return;

        // Compute content fingerprint
        $yoast_title = (string) get_post_meta($post_id, '_yoast_wpseo_title', true);
        $yoast_desc = (string) get_post_meta($post_id, '_yoast_wpseo_metadesc', true);
        $hash_payload = $post->post_title . '|' . $post->post_content . '|' . $post->post_excerpt . '|' . $yoast_title . '|' . $yoast_desc;
        $current_hash = md5($hash_payload);

        $saved_hash = get_post_meta($post_id, '_hs_trans_hash', true);
        if ($saved_hash === $current_hash) {
            return; // Content hasn't changed. Bail out in 0.002 seconds!
        }

        // Update stored hash
        update_post_meta($post_id, '_hs_trans_hash', $current_hash);

        // Enqueue translation task
        $queue_id = QueueManager::enqueue($post_id, $post->post_type, $current_hash);

        // Send non-blocking loopback ping to daemon
        self::signal_daemon($post_id, $queue_id);
    }

    private static function signal_daemon(int $post_id, int $queue_id): void {
        wp_remote_post('http://127.0.0.1:3850/queue/notify', [
            'timeout' => 0.2,
            'blocking' => false,
            'headers' => ['Content-Type' => 'application/json'],
            'body' => json_encode(['post_id' => $post_id, 'queue_id' => $queue_id])
        ]);
    }

    public function register_meta_box(): void {
        $options = Settings::get_options();
        $allowed_types = $options['policies']['post_types'] ?? ['helmet', 'motorcycle', 'post'];

        foreach ($allowed_types as $type) {
            add_meta_box(
                'hs_translate_meta_box',
                __('🌐 Helmetsan Multilingual Sync', 'helmetsan-translate'),
                [$this, 'render_meta_box'],
                $type,
                'side',
                'high'
            );
        }
    }

    public function render_meta_box(\WP_Post $post): void {
        wp_nonce_field('hs_meta_box_nonce_action', 'hs_meta_box_nonce');
        $is_locked = (bool) get_post_meta($post->ID, '_hs_translation_locked', true);
        $hash = get_post_meta($post->ID, '_hs_trans_hash', true);

        $lang = function_exists('pll_get_post_language') ? pll_get_post_language($post->ID, 'slug') : 'en';
        $translations = function_exists('pll_get_post_translations') ? pll_get_post_translations($post->ID) : [];

        echo '<div class="hs-meta-box-content">';
        echo '<p><strong>Current Lang:</strong> <code>' . esc_html(strtoupper($lang)) . '</code></p>';

        if ($lang === 'en') {
            echo '<label><input type="checkbox" name="hs_translation_locked" value="1" ' . checked($is_locked, true, false) . '> ';
            esc_html_e('Lock Translations (Prevent Auto-Sync)', 'helmetsan-translate');
            echo '</label>';

            echo '<hr style="margin:12px 0;">';
            echo '<p><strong>' . esc_html__('Target Storefronts:', 'helmetsan-translate') . '</strong></p>';
            echo '<ul style="margin:0; padding-left:18px;">';

            $targets = ['de' => 'German', 'fr' => 'French', 'es' => 'Spanish', 'it' => 'Italian', 'nl' => 'Dutch', 'pl' => 'Polish', 'pt' => 'Portuguese', 'ja' => 'Japanese', 'zh' => 'Chinese'];
            foreach ($targets as $code => $label) {
                $child_id = $translations[$code] ?? null;
                if ($child_id) {
                    $edit_url = get_edit_post_link($child_id);
                    echo "<li><code>" . strtoupper($code) . "</code>: 🟢 <a href='{$edit_url}' target='_blank'>Edit {$label}</a></li>";
                } else {
                    echo "<li><code>" . strtoupper($code) . "</code>: ⚪ <em>Pending translation</em></li>";
                }
            }
            echo '</ul>';
        } else {
            echo '<p><em>' . esc_html__('This is an auto-generated localized post. Edit the English master post to update.', 'helmetsan-translate') . '</em></p>';
        }

        echo '</div>';
    }

    public function save_meta_box_data(int $post_id): void {
        if (!isset($_POST['hs_meta_box_nonce']) || !wp_verify_nonce($_POST['hs_meta_box_nonce'], 'hs_meta_box_nonce_action')) {
            return;
        }
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
        if (!current_user_can('edit_post', $post_id)) return;

        $locked = isset($_POST['hs_translation_locked']) ? 1 : 0;
        update_post_meta($post_id, '_hs_translation_locked', $locked);
    }

    public function on_trash_post(int $post_id): void {
        if (!function_exists('pll_get_post_translations')) return;
        $translations = pll_get_post_translations($post_id);
        foreach ($translations as $lang => $child_id) {
            if ($child_id && $child_id !== $post_id) {
                wp_trash_post($child_id);
            }
        }
    }

    public function on_untrash_post(int $post_id): void {
        if (!function_exists('pll_get_post_translations')) return;
        $translations = pll_get_post_translations($post_id);
        foreach ($translations as $lang => $child_id) {
            if ($child_id && $child_id !== $post_id) {
                wp_untrash_post($child_id);
            }
        }
    }
}
