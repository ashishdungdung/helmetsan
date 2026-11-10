<?php
declare(strict_types=1);

namespace HelmetsanTranslate;

defined('ABSPATH') || exit;

class QueueManager {
    private static ?QueueManager $instance = null;

    public static function instance(): self {
        return self::$instance ??= new self();
    }

    private function __construct() {
        // Daily maintenance: prune logs older than 7 days
        add_action('helmetsan_translate_daily_cleanup', [$this, 'prune_old_logs']);
        if (!wp_next_scheduled('helmetsan_translate_daily_cleanup')) {
            wp_schedule_event(time(), 'daily', 'helmetsan_translate_daily_cleanup');
        }
    }

    public static function enqueue(int $post_id, string $post_type, string $hash): int {
        global $wpdb;
        $table = $wpdb->prefix . HELMETSAN_TRANSLATE_QUEUE_TABLE;

        $target_langs = ['de', 'fr', 'es', 'it', 'nl', 'pl', 'pt', 'ja', 'zh'];
        $initial_state = [];
        foreach ($target_langs as $lang) {
            $initial_state[$lang] = 'pending';
        }

        // Circuit breaker check
        if (self::is_circuit_breaker_tripped()) {
            error_log('[HelmetsanTranslate] Circuit breaker tripped! Halting automatic enqueue.');
            return 0;
        }

        $existing = $wpdb->get_row($wpdb->prepare(
            "SELECT id, status FROM {$table} WHERE post_id = %d AND content_hash = %s",
            $post_id, $hash
        ));

        if ($existing) {
            if ($existing->status === 'failed') {
                $wpdb->update($table, ['status' => 'pending', 'attempts' => 0], ['id' => $existing->id]);
            }
            return (int) $existing->id;
        }

        $wpdb->insert($table, [
            'post_id' => $post_id,
            'post_type' => $post_type,
            'content_hash' => $hash,
            'status' => 'pending',
            'attempts' => 0,
            'languages_state' => json_encode($initial_state),
            'created_at' => current_time('mysql'),
        ]);

        return (int) $wpdb->insert_id;
    }

    public static function is_circuit_breaker_tripped(): bool {
        global $wpdb;
        $table = $wpdb->prefix . HELMETSAN_TRANSLATE_QUEUE_TABLE;
        $options = Settings::get_options();
        $limit = (int) ($options['policies']['circuit_breaker_limit'] ?? 1000);

        $recent_count = (int) $wpdb->get_var(
            "SELECT COUNT(*) FROM {$table} WHERE created_at >= NOW() - INTERVAL 1 HOUR"
        );

        return $recent_count >= $limit;
    }

    public static function log(int $post_id, string $lang, string $provider, int $latency, string $status, string $message = ''): void {
        global $wpdb;
        $table = $wpdb->prefix . HELMETSAN_TRANSLATE_LOG_TABLE;
        $wpdb->insert($table, [
            'post_id' => $post_id,
            'target_lang' => $lang,
            'provider' => $provider,
            'latency_ms' => $latency,
            'status' => $status,
            'message' => $message,
            'created_at' => current_time('mysql')
        ]);
    }

    public function prune_old_logs(): void {
        global $wpdb;
        $table = $wpdb->prefix . HELMETSAN_TRANSLATE_LOG_TABLE;
        $wpdb->query("DELETE FROM {$table} WHERE created_at < NOW() - INTERVAL 7 DAY");
    }
}
