<?php

declare(strict_types=1);

namespace Helmetsan\Core\Ingestion;

final class LogRepository
{
    public function tableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'helmetsan_ingest_logs';
    }

    public function ensureTable(): void
    {
        global $wpdb;

        $table = $this->tableName();
        $charsetCollate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            created_at datetime NOT NULL,
            source_file text NOT NULL,
            status varchar(20) NOT NULL,
            message text NOT NULL,
            external_id varchar(190) DEFAULT '',
            post_id bigint(20) unsigned DEFAULT 0,
            PRIMARY KEY (id),
            KEY status (status),
            KEY external_id (external_id(100)),
            KEY post_id (post_id)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function add(string $sourceFile, string $status, string $message, string $externalId = '', int $postId = 0): void
    {
        global $wpdb;

        $wpdb->insert(
            $this->tableName(),
            [
                'created_at'  => current_time('mysql'),
                'source_file' => $sourceFile,
                'status'      => $status,
                'message'     => $message,
                'external_id' => $externalId,
                'post_id'     => $postId,
            ],
            ['%s', '%s', '%s', '%s', '%s', '%d']
        );
    }

    public function tableExists(): bool
    {
        global $wpdb;

        $table  = $this->tableName();
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

        return $exists === $table;
    }

    /**
     * @return array<string, int>
     */
    public function statusCounts(): array
    {
        global $wpdb;

        if (! $this->tableExists()) {
            return [];
        }

        $rows = $wpdb->get_results('SELECT status, COUNT(*) as total FROM ' . $this->tableName() . ' GROUP BY status', ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }

        $counts = [];
        foreach ($rows as $row) {
            $status = isset($row['status']) ? (string) $row['status'] : '';
            $total  = isset($row['total']) ? (int) $row['total'] : 0;
            if ($status !== '') {
                $counts[$status] = $total;
            }
        }

        return $counts;
    }

    public function count(?string $status = null, string $search = ''): int
    {
        global $wpdb;

        if (! $this->tableExists()) {
            return 0;
        }

        [$whereSql, $params] = $this->buildWhere($status, $search);
        $sql = 'SELECT COUNT(*) FROM ' . $this->tableName() . $whereSql;

        if ($params === []) {
            return (int) $wpdb->get_var($sql);
        }

        return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function fetch(int $page, int $perPage, ?string $status = null, string $search = ''): array
    {
        global $wpdb;

        if (! $this->tableExists()) {
            return [];
        }

        $page    = max(1, $page);
        $perPage = max(1, $perPage);
        $offset  = ($page - 1) * $perPage;

        [$whereSql, $params] = $this->buildWhere($status, $search);
        $sql = 'SELECT id, created_at, source_file, status, message, external_id, post_id FROM ' . $this->tableName() . $whereSql . ' ORDER BY id DESC LIMIT %d OFFSET %d';

        $params[] = $perPage;
        $params[] = $offset;

        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @param array<int, int> $ids
     * @return array<int, array<string, mixed>>
     */
    public function findByIds(array $ids): array
    {
        global $wpdb;

        if (! $this->tableExists() || $ids === []) {
            return [];
        }

        $ids = array_values(array_unique(array_map('intval', $ids)));
        $ids = array_filter($ids, static fn(int $id): bool => $id > 0);

        if ($ids === []) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($ids), '%d'));
        $sql = 'SELECT id, created_at, source_file, status, message, external_id, post_id FROM ' . $this->tableName() . ' WHERE id IN (' . $placeholders . ') ORDER BY id DESC';

        $rows = $wpdb->get_results($wpdb->prepare($sql, $ids), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array{0:string,1:array<int,mixed>}
     */
    private function buildWhere(?string $status, string $search): array
    {
        $where  = [];
        $params = [];

        if ($status !== null && $status !== '' && $status !== 'all') {
            $where[]  = 'status = %s';
            $params[] = $status;
        }

        $search = trim($search);
        if ($search !== '') {
            $like     = '%' . $this->escLike($search) . '%';
            $where[]  = '(source_file LIKE %s OR message LIKE %s OR external_id LIKE %s)';
            $params[] = $like;
            $params[] = $like;
            $params[] = $like;
        }

        if ($where === []) {
            return ['', []];
        }

        return [' WHERE ' . implode(' AND ', $where), $params];
    }

    private function escLike(string $value): string
    {
        global $wpdb;
        return $wpdb->esc_like($value);
    }

    public function cleanupOlderThanDays(int $days, ?string $status = null): int
    {
        global $wpdb;

        if (! $this->tableExists()) {
            return 0;
        }

        $days = max(1, $days);
        $date = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

        if ($status !== null && $status !== '' && $status !== 'all') {
            $sql = 'DELETE FROM ' . $this->tableName() . ' WHERE created_at < %s AND status = %s';
            $deleted = $wpdb->query($wpdb->prepare($sql, $date, $status));
            return is_int($deleted) ? $deleted : 0;
        }

        $sql = 'DELETE FROM ' . $this->tableName() . ' WHERE created_at < %s';
        $deleted = $wpdb->query($wpdb->prepare($sql, $date));

        return is_int($deleted) ? $deleted : 0;
    }
}
