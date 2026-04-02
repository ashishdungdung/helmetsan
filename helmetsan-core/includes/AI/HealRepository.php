<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI;

/**
 * Manages the persistence of AI healing events for the autonomous engine.
 */
final class HealRepository
{
    /**
     * Log a successful or staged heal event.
     */
    public function logHeal(array $params): int
    {
        global $wpdb;
        $table = $wpdb->prefix . 'helmetsan_heals';

        $wpdb->insert(
            $table,
            [
                'entity_type' => $params['entity_type'] ?? 'helmet',
                'item_id'     => $params['item_id'] ?? 'unknown',
                'file_path'   => $params['file_path'] ?? '',
                'issues'      => is_array($params['issues']) ? implode(', ', $params['issues']) : (string) $params['issues'],
                'fix_patch'       => is_array($params['fix_patch']) ? wp_json_encode($params['fix_patch']) : (string) $params['fix_patch'],
                'original_values' => is_array($params['original_values'] ?? null) ? wp_json_encode($params['original_values']) : (string) ($params['original_values'] ?? ''),
                'ai_mode'         => $params['ai_mode'] ?? 'local',
                'applied'         => ! empty($params['applied']) ? 1 : 0,
                'reverted'        => 0,
                'created_at'      => current_time('mysql'),
            ],
            ['%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%d', '%s']
        );

        return (int) $wpdb->insert_id;
    }

    /**
     * Get recent heals for the dashboard.
     */
    public function getRecentHeals(int $limit = 50): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'helmetsan_heals';
        return $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} ORDER BY created_at DESC LIMIT %d", $limit),
            ARRAY_A
        );
    }

    /**
     * Get stats for the "Morning Report".
     */
    public function getStatsForPeriod(string $period = '24 hours'): array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'helmetsan_heals';
        
        $total = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE created_at >= %s",
            date('Y-m-d H:i:s', strtotime('-' . $period))
        ));

        $byMode = $wpdb->get_results($wpdb->prepare(
            "SELECT ai_mode, COUNT(*) as count FROM {$table} WHERE created_at >= %s GROUP BY ai_mode",
            date('Y-m-d H:i:s', strtotime('-' . $period))
        ), ARRAY_A);

        return [
            'total' => (int) $total,
            'modes' => $byMode,
        ];
    }

    /**
     * Get a single heal record.
     */
    public function getHeal(int $id): ?array
    {
        global $wpdb;
        $table = $wpdb->prefix . 'helmetsan_heals';
        return $wpdb->get_row($wpdb->prepare("SELECT * FROM {$table} WHERE id = %d", $id), ARRAY_A);
    }

    /**
     * Attempt to surgically revert a heal by restoring original values.
     */
    public function revertHeal(int $id): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . 'helmetsan_heals';
        $heal = $this->getHeal($id);

        if (! $heal || empty($heal['original_values']) || (int) $heal['reverted'] === 1) {
            return false;
        }

        $filePath = (string) $heal['file_path'];
        if (! file_exists($filePath)) {
            return false;
        }

        $currentData = json_decode((string) file_get_contents($filePath), true);
        $originalPatch = json_decode((string) $heal['original_values'], true);

        if (! is_array($currentData) || ! is_array($originalPatch)) {
            return false;
        }

        // Surgical reversal: put original values back into the JSON structure
        $revertedData = array_replace_recursive($currentData, $originalPatch);
        
        $success = file_put_contents($filePath, wp_json_encode($revertedData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)) !== false;

        if ($success) {
            $wpdb->update($table, ['reverted' => 1], ['id' => $id]);
        }

        return $success;
    }
}
