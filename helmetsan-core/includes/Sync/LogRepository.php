<?php

declare(strict_types=1);

namespace Helmetsan\Core\Sync;

final class LogRepository
{
    public function tableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'helmetsan_sync_logs';
    }

    public function ensureTable(): void
    {
        global $wpdb;

        $table = $this->tableName();
        $charsetCollate = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            created_at datetime NOT NULL,
            action varchar(20) NOT NULL,
            mode varchar(20) DEFAULT '',
            status varchar(20) NOT NULL,
            branch varchar(190) DEFAULT '',
            target_branch varchar(190) DEFAULT '',
            remote_path text,
            processed int(11) DEFAULT 0,
            pushed int(11) DEFAULT 0,
            skipped int(11) DEFAULT 0,
            failed int(11) DEFAULT 0,
            message text,
            payload longtext,
            PRIMARY KEY (id),
            KEY action (action),
            KEY status (status),
            KEY branch (branch(100)),
            KEY target_branch (target_branch(100))
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * @param array<string,mixed> $row
     */
    public function add(array $row): void
    {
        global $wpdb;

        $defaults = [
            'created_at'    => current_time('mysql'),
            'action'        => '',
            'mode'          => '',
            'status'        => 'info',
            'branch'        => '',
            'target_branch' => '',
            'remote_path'   => '',
            'processed'     => 0,
            'pushed'        => 0,
            'skipped'       => 0,
            'failed'        => 0,
            'message'       => '',
            'payload'       => '',
        ];

        $data = wp_parse_args($row, $defaults);

        if (! is_string($data['payload'])) {
            $json = wp_json_encode($data['payload']);
            $data['payload'] = is_string($json) ? $json : '';
        }

        $wpdb->insert(
            $this->tableName(),
            [
                'created_at'    => (string) $data['created_at'],
                'action'        => (string) $data['action'],
                'mode'          => (string) $data['mode'],
                'status'        => (string) $data['status'],
                'branch'        => (string) $data['branch'],
                'target_branch' => (string) $data['target_branch'],
                'remote_path'   => (string) $data['remote_path'],
                'processed'     => (int) $data['processed'],
                'pushed'        => (int) $data['pushed'],
                'skipped'       => (int) $data['skipped'],
                'failed'        => (int) $data['failed'],
                'message'       => (string) $data['message'],
                'payload'       => (string) $data['payload'],
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%d', '%d', '%s', '%s']
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
            $total = isset($row['total']) ? (int) $row['total'] : 0;
            if ($status !== '') {
                $counts[$status] = $total;
            }
        }

        return $counts;
    }

    /**
     * @return array<string,int>
     */
    public function actionCounts(): array
    {
        global $wpdb;

        if (! $this->tableExists()) {
            return [];
        }

        $rows = $wpdb->get_results('SELECT action, COUNT(*) as total FROM ' . $this->tableName() . ' GROUP BY action', ARRAY_A);
        if (! is_array($rows)) {
            return [];
        }

        $counts = [];
        foreach ($rows as $row) {
            $action = isset($row['action']) ? (string) $row['action'] : '';
            $total  = isset($row['total']) ? (int) $row['total'] : 0;
            if ($action !== '') {
                $counts[$action] = $total;
            }
        }

        return $counts;
    }

    public function count(?string $status = null, ?string $action = null, string $search = ''): int
    {
        global $wpdb;

        if (! $this->tableExists()) {
            return 0;
        }

        [$whereSql, $params] = $this->buildWhere($status, $action, $search);
        $sql = 'SELECT COUNT(*) FROM ' . $this->tableName() . $whereSql;

        if ($params === []) {
            return (int) $wpdb->get_var($sql);
        }

        return (int) $wpdb->get_var($wpdb->prepare($sql, $params));
    }

    /**
     * @return array<int,array<string,mixed>>
     */
    public function fetch(int $page, int $perPage, ?string $status = null, ?string $action = null, string $search = ''): array
    {
        global $wpdb;

        if (! $this->tableExists()) {
            return [];
        }

        $page    = max(1, $page);
        $perPage = max(1, $perPage);
        $offset  = ($page - 1) * $perPage;

        [$whereSql, $params] = $this->buildWhere($status, $action, $search);
        $sql = 'SELECT id, created_at, action, mode, status, branch, target_branch, remote_path, processed, pushed, skipped, failed, message FROM '
            . $this->tableName() . $whereSql . ' ORDER BY id DESC LIMIT %d OFFSET %d';

        $params[] = $perPage;
        $params[] = $offset;

        $rows = $wpdb->get_results($wpdb->prepare($sql, $params), ARRAY_A);

        return is_array($rows) ? $rows : [];
    }

    /**
     * @return array<string,mixed>|null
     */
    public function findById(int $id): ?array
    {
        global $wpdb;

        if (! $this->tableExists() || $id <= 0) {
            return null;
        }

        $sql = 'SELECT * FROM ' . $this->tableName() . ' WHERE id = %d LIMIT 1';
        $row = $wpdb->get_row($wpdb->prepare($sql, $id), ARRAY_A);

        return is_array($row) ? $row : null;
    }

    /**
     * @return array{0:string,1:array<int,mixed>}
     */
    private function buildWhere(?string $status, ?string $action, string $search): array
    {
        $where  = [];
        $params = [];

        if ($status !== null && $status !== '' && $status !== 'all') {
            $where[] = 'status = %s';
            $params[] = $status;
        }

        if ($action !== null && $action !== '' && $action !== 'all') {
            $where[] = 'action = %s';
            $params[] = $action;
        }

        $search = trim($search);
        if ($search !== '') {
            $like = '%' . $this->escLike($search) . '%';
            $where[] = '(branch LIKE %s OR target_branch LIKE %s OR remote_path LIKE %s OR message LIKE %s)';
            $params[] = $like;
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
