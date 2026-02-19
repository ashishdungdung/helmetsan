<?php

declare(strict_types=1);

namespace Helmetsan\Core\Price;

/**
 * Manages the wp_helmetsan_price_history table.
 *
 * Records a snapshot each time a price is fetched from a marketplace
 * connector, enabling historical price charts on the PDP.
 */
final class PriceHistory
{
    /**
     * Create the table on plugin activation.
     */
    public function ensureTable(): void
    {
        global $wpdb;

        $table   = $this->tableName();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            helmet_id bigint(20) unsigned NOT NULL,
            marketplace_id varchar(50) NOT NULL DEFAULT 'global',
            country_code char(2) NOT NULL DEFAULT 'US',
            currency char(3) NOT NULL DEFAULT 'USD',
            price decimal(10,2) NOT NULL,
            captured_at datetime NOT NULL,
            PRIMARY KEY (id),
            KEY helmet_id (helmet_id),
            KEY marketplace_id (marketplace_id),
            KEY country_code (country_code),
            KEY captured_at (captured_at),
            UNIQUE KEY unique_snapshot (helmet_id, marketplace_id, country_code, captured_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function tableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'helmetsan_price_history';
    }

    /**
     * Record a price snapshot.
     *
     * Silently skips if a record for the same helmet/marketplace/country/date exists
     * (the UNIQUE KEY prevents duplicates within the same timestamp).
     */
    public function record(
        int    $helmetId,
        string $marketplaceId,
        string $countryCode,
        string $currency,
        float  $price,
        ?string $capturedAt = null
    ): bool {
        global $wpdb;

        if (!$this->tableExists()) {
            return false;
        }

        $result = $wpdb->insert(
            $this->tableName(),
            [
                'helmet_id'      => $helmetId,
                'marketplace_id' => sanitize_text_field($marketplaceId),
                'country_code'   => strtoupper(substr(sanitize_text_field($countryCode), 0, 2)),
                'currency'       => strtoupper(substr(sanitize_text_field($currency), 0, 3)),
                'price'          => $price,
                'captured_at'    => $capturedAt ?? current_time('mysql'),
            ],
            ['%d', '%s', '%s', '%s', '%f', '%s']
        );

        return $result !== false;
    }

    /**
     * Get price history for a helmet, optionally filtered by marketplace and country.
     *
     * @return array<int, array{marketplace_id: string, country_code: string, currency: string, price: float, captured_at: string}>
     */
    public function getHistory(
        int     $helmetId,
        int     $days = 30,
        ?string $marketplaceId = null,
        ?string $countryCode = null
    ): array {
        global $wpdb;

        if (!$this->tableExists()) {
            return [];
        }

        $table = $this->tableName();
        $from  = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));

        $where  = $wpdb->prepare('helmet_id = %d AND captured_at >= %s', $helmetId, $from);

        if ($marketplaceId !== null) {
            $where .= $wpdb->prepare(' AND marketplace_id = %s', $marketplaceId);
        }
        if ($countryCode !== null) {
            $where .= $wpdb->prepare(' AND country_code = %s', strtoupper($countryCode));
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            "SELECT marketplace_id, country_code, currency, price, captured_at FROM {$table} WHERE {$where} ORDER BY captured_at ASC",
            ARRAY_A
        );

        if (!is_array($rows)) {
            return [];
        }

        return array_map(static fn(array $row) => [
            'marketplace_id' => (string) $row['marketplace_id'],
            'country_code'   => (string) $row['country_code'],
            'currency'       => (string) $row['currency'],
            'price'          => (float) $row['price'],
            'captured_at'    => (string) $row['captured_at'],
        ], $rows);
    }

    /**
     * Get the latest price for each marketplace for a helmet.
     *
     * @return array<string, array{price: float, currency: string, captured_at: string}>
     */
    public function getLatestByMarketplace(int $helmetId, ?string $countryCode = null): array
    {
        global $wpdb;

        if (!$this->tableExists()) {
            return [];
        }

        $table = $this->tableName();
        $where = $wpdb->prepare('h.helmet_id = %d', $helmetId);
        if ($countryCode !== null) {
            $where .= $wpdb->prepare(' AND h.country_code = %s', strtoupper($countryCode));
        }

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $subquery = $wpdb->prepare(
            "SELECT marketplace_id, MAX(captured_at) as max_date FROM {$table} WHERE helmet_id = %d GROUP BY marketplace_id",
            $helmetId
        );

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            "SELECT h.marketplace_id, h.currency, h.price, h.captured_at
             FROM {$table} h
             INNER JOIN ({$subquery}) latest ON h.marketplace_id = latest.marketplace_id AND h.captured_at = latest.max_date
             WHERE {$where}
             ORDER BY h.price ASC",
            ARRAY_A
        );

        if (!is_array($rows)) {
            return [];
        }

        $result = [];
        foreach ($rows as $row) {
            $result[(string) $row['marketplace_id']] = [
                'price'       => (float) $row['price'],
                'currency'    => (string) $row['currency'],
                'captured_at' => (string) $row['captured_at'],
            ];
        }

        return $result;
    }

    /**
     * Aggregate snapshot statistics for the Price Coverage dashboard block.
     *
     * @return array{total_snapshots: int, by_marketplace: array<string, int>, last_captured_at: string|null}
     */
    public function getSnapshotStats(): array
    {
        global $wpdb;

        $empty = ['total_snapshots' => 0, 'by_marketplace' => [], 'last_captured_at' => null];

        if (!$this->tableExists()) {
            return $empty;
        }

        $table = $this->tableName();

        $total = (int) $wpdb->get_var("SELECT COUNT(*) FROM {$table}");

        // phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
        $rows = $wpdb->get_results(
            "SELECT marketplace_id, COUNT(*) as cnt FROM {$table} GROUP BY marketplace_id ORDER BY cnt DESC",
            ARRAY_A
        );

        $byMp = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $byMp[(string) $row['marketplace_id']] = (int) $row['cnt'];
            }
        }

        $lastCaptured = $wpdb->get_var("SELECT MAX(captured_at) FROM {$table}");

        return [
            'total_snapshots'  => $total,
            'by_marketplace'   => $byMp,
            'last_captured_at' => $lastCaptured ? (string) $lastCaptured : null,
        ];
    }

    public function tableExists(): bool
    {
        global $wpdb;

        $table  = $this->tableName();
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

        return $exists === $table;
    }
}
