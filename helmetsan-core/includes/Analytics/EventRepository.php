<?php

declare(strict_types=1);

namespace Helmetsan\Core\Analytics;

final class EventRepository
{
    public function tableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'helmetsan_analytics_events';
    }

    public function ensureTable(): void
    {
        global $wpdb;

        $table = $this->tableName();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            created_at datetime NOT NULL,
            event_name varchar(100) NOT NULL,
            page_url text,
            referrer text,
            source varchar(50) DEFAULT '',
            meta_json longtext,
            PRIMARY KEY (id),
            KEY event_name (event_name),
            KEY source (source),
            KEY created_at (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * @param array<string,mixed> $payload
     */
    public function add(array $payload): void
    {
        global $wpdb;

        $meta = isset($payload['meta']) ? $payload['meta'] : [];
        $metaJson = wp_json_encode(is_array($meta) ? $meta : []);

        $wpdb->insert(
            $this->tableName(),
            [
                'created_at' => current_time('mysql'),
                'event_name' => isset($payload['event_name']) ? (string) $payload['event_name'] : '',
                'page_url'   => isset($payload['page_url']) ? esc_url_raw((string) $payload['page_url']) : '',
                'referrer'   => isset($payload['referrer']) ? esc_url_raw((string) $payload['referrer']) : '',
                'source'     => isset($payload['source']) ? sanitize_text_field((string) $payload['source']) : '',
                'meta_json'  => is_string($metaJson) ? $metaJson : '{}',
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    public function tableExists(): bool
    {
        global $wpdb;

        $table = $this->tableName();
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

        return $exists === $table;
    }

    /**
     * @return array<string,int>
     */
    public function countByEvent(int $days = 7): array
    {
        global $wpdb;

        if (! $this->tableExists()) {
            return [];
        }

        $days = max(1, $days);
        $from = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

        $rows = $wpdb->get_results(
            $wpdb->prepare('SELECT event_name, COUNT(*) as total FROM ' . $this->tableName() . ' WHERE created_at >= %s GROUP BY event_name ORDER BY total DESC', $from),
            ARRAY_A
        );

        if (! is_array($rows)) {
            return [];
        }

        $out = [];
        foreach ($rows as $row) {
            $name = isset($row['event_name']) ? (string) $row['event_name'] : '';
            if ($name !== '') {
                $out[$name] = isset($row['total']) ? (int) $row['total'] : 0;
            }
        }

        return $out;
    }

    public function total(int $days = 7): int
    {
        global $wpdb;

        if (! $this->tableExists()) {
            return 0;
        }

        $days = max(1, $days);
        $from = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

        return (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . $this->tableName() . ' WHERE created_at >= %s', $from));
    }
}
