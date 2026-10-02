<?php

declare(strict_types=1);

namespace Helmetsan\Core\Media;

/**
 * Dedicated Helmet Image Manager
 *
 * Manages the canonical 5-shot photography lifecycle for every helmet:
 * - front_hero: 3/4 Front Isometric Hero
 * - side_profile: Lateral Side Profile
 * - rear_exhaust: Rear Exhaust & Diffuser
 * - interior_macro: Macro Interior & Retention System
 * - cockpit_context: Ergonomic Motorcycle Cockpit Pairing
 *
 * Handles database persistence (wp_helmetsan_images), coverage tracking,
 * responsive WebP derivatives, pHash deduplication, and R2/WordPress media linkage.
 */
final class HelmetImageManager
{
    public const TABLE_NAME = 'helmetsan_images';

    public const SHOT_FRONT_HERO = 'front_hero';
    public const SHOT_SIDE_PROFILE = 'side_profile';
    public const SHOT_REAR_EXHAUST = 'rear_exhaust';
    public const SHOT_INTERIOR_MACRO = 'interior_macro';
    public const SHOT_COCKPIT_CONTEXT = 'cockpit_context';

    public const CANONICAL_SHOTS = [
        self::SHOT_FRONT_HERO => [
            'label' => '3/4 Front Isometric Hero',
            'desc' => 'Visor cracked, Pinlock pins visible, intake vents open, softbox lighting',
            'required' => true,
        ],
        self::SHOT_SIDE_PROFILE => [
            'label' => 'Lateral Side Profile',
            'desc' => 'Perpendicular to shell, spoiler contour, visor pivot baseplate',
            'required' => true,
        ],
        self::SHOT_REAR_EXHAUST => [
            'label' => 'Rear Exhaust & Diffuser',
            'desc' => 'Exhaust ports, neck-roll taper, ECE 22.06 / DOT certification badge',
            'required' => true,
        ],
        self::SHOT_INTERIOR_MACRO => [
            'label' => 'Macro Interior & Retention',
            'desc' => 'Multi-density EPS channels, emergency red tabs, titanium D-ring / ratchet',
            'required' => true,
        ],
        self::SHOT_COCKPIT_CONTEXT => [
            'label' => 'Motorcycle Cockpit Pairing',
            'desc' => 'Mounted on fuel tank or rider in realistic lighting and scale',
            'required' => true,
        ],
    ];

    public function __construct()
    {
        $this->ensureTable();
    }

    /**
     * Ensure the wp_helmetsan_images table exists.
     */
    public function ensureTable(): void
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        if ($wpdb->get_var("SHOW TABLES LIKE '{$table}'") === $table) {
            return;
        }

        $charsetCollate = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE {$table} (
            id VARCHAR(64) NOT NULL,
            helmet_id VARCHAR(64) NOT NULL,
            wordpress_attachment_id BIGINT UNSIGNED NULL,
            shot_type VARCHAR(32) NOT NULL,
            source_type VARCHAR(32) DEFAULT 'ai_generated',
            url VARCHAR(512) NOT NULL,
            thumbnail_url VARCHAR(512) NOT NULL,
            alt_text TEXT NOT NULL,
            caption TEXT NULL,
            dimensions_json TEXT NOT NULL,
            format VARCHAR(16) DEFAULT 'webp',
            file_size_bytes INT UNSIGNED NOT NULL DEFAULT 0,
            phash VARCHAR(64) NOT NULL DEFAULT '',
            sha256 VARCHAR(64) NOT NULL DEFAULT '',
            is_primary TINYINT(1) DEFAULT 0,
            sort_order INT DEFAULT 0,
            validation_status VARCHAR(32) DEFAULT 'queued',
            audit_metadata_json LONGTEXT NULL,
            generation_metadata_json LONGTEXT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            PRIMARY KEY (id),
            KEY idx_helmet_shot (helmet_id, shot_type),
            KEY idx_phash (phash),
            KEY idx_status (validation_status)
        ) {$charsetCollate};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /**
     * Get all images associated with a helmet.
     *
     * @return list<array<string,mixed>>
     */
    public function getImagesForHelmet(string $helmetId): array
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        $results = $wpdb->get_results(
            $wpdb->prepare("SELECT * FROM {$table} WHERE helmet_id = %s ORDER BY sort_order ASC, created_at ASC", $helmetId),
            ARRAY_A
        );

        if (!is_array($results)) {
            return [];
        }

        foreach ($results as &$row) {
            $row['dimensions'] = json_decode((string) ($row['dimensions_json'] ?? '{}'), true);
            $row['audit_metadata'] = json_decode((string) ($row['audit_metadata_json'] ?? '{}'), true);
            $row['generation_metadata'] = json_decode((string) ($row['generation_metadata_json'] ?? '{}'), true);
        }

        return $results;
    }

    /**
     * Calculate the 5-shot coverage for a helmet.
     *
     * @return array{
     *     required: int,
     *     approved: int,
     *     missing: list<string>,
     *     status: 'complete'|'incomplete'|'failed',
     *     shots: array<string, array<string, mixed>|null>
     * }
     */
    public function getCoverageForHelmet(string $helmetId): array
    {
        $images = $this->getImagesForHelmet($helmetId);
        $shots = [
            self::SHOT_FRONT_HERO => null,
            self::SHOT_SIDE_PROFILE => null,
            self::SHOT_REAR_EXHAUST => null,
            self::SHOT_INTERIOR_MACRO => null,
            self::SHOT_COCKPIT_CONTEXT => null,
        ];

        $approvedCount = 0;
        $missing = [];

        foreach ($images as $img) {
            $type = $img['shot_type'] ?? '';
            if (isset($shots[$type]) && ($shots[$type] === null || !empty($img['is_primary']))) {
                $shots[$type] = $img;
            }
        }

        foreach (array_keys(self::CANONICAL_SHOTS) as $shotType) {
            $shot = $shots[$shotType];
            if ($shot !== null && in_array($shot['validation_status'] ?? '', ['approved', 'published', 'verified', 'audit_passed'], true)) {
                $approvedCount++;
            } else {
                $missing[] = $shotType;
            }
        }

        $status = $approvedCount === count(self::CANONICAL_SHOTS) ? 'complete' : 'incomplete';

        return [
            'required' => count(self::CANONICAL_SHOTS),
            'approved' => $approvedCount,
            'missing' => $missing,
            'status' => $status,
            'shots' => $shots,
        ];
    }

    /**
     * Register or update an image record.
     */
    public function registerImage(array $data): string
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        $id = $data['id'] ?? ('img_' . substr(md5(uniqid((string) mt_rand(), true)), 0, 16));
        $helmetId = (string) ($data['helmet_id'] ?? '');
        $shotType = (string) ($data['shot_type'] ?? self::SHOT_FRONT_HERO);

        $isPrimary = !empty($data['is_primary']) ? 1 : 0;
        if ($isPrimary === 1) {
            // Demote other primary images for this helmet
            $wpdb->update($table, ['is_primary' => 0], ['helmet_id' => $helmetId]);
        }

        $row = [
            'id' => $id,
            'helmet_id' => $helmetId,
            'wordpress_attachment_id' => !empty($data['wordpress_attachment_id']) ? (int) $data['wordpress_attachment_id'] : null,
            'shot_type' => $shotType,
            'source_type' => $data['source_type'] ?? 'ai_generated',
            'url' => (string) ($data['url'] ?? ''),
            'thumbnail_url' => (string) ($data['thumbnail_url'] ?? $data['url'] ?? ''),
            'alt_text' => (string) ($data['alt_text'] ?? ''),
            'caption' => (string) ($data['caption'] ?? ''),
            'dimensions_json' => wp_json_encode($data['dimensions'] ?? ['hero' => 1920, 'gallery' => 1024, 'thumb' => 480]),
            'format' => (string) ($data['format'] ?? 'webp'),
            'file_size_bytes' => (int) ($data['file_size_bytes'] ?? 0),
            'phash' => (string) ($data['phash'] ?? ''),
            'sha256' => (string) ($data['sha256'] ?? ''),
            'is_primary' => $isPrimary,
            'sort_order' => (int) ($data['sort_order'] ?? 0),
            'validation_status' => (string) ($data['validation_status'] ?? 'queued'),
            'audit_metadata_json' => !empty($data['audit_metadata']) ? wp_json_encode($data['audit_metadata']) : null,
            'generation_metadata_json' => !empty($data['generation_metadata']) ? wp_json_encode($data['generation_metadata']) : null,
        ];

        $existing = $wpdb->get_var($wpdb->prepare("SELECT id FROM {$table} WHERE id = %s", $id));
        if ($existing) {
            $wpdb->update($table, $row, ['id' => $id]);
        } else {
            $wpdb->insert($table, $row);
        }

        return $id;
    }

    /**
     * Designate an image as the primary hero image for a helmet.
     */
    public function setPrimaryImage(string $helmetId, string $imageId): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        $wpdb->update($table, ['is_primary' => 0], ['helmet_id' => $helmetId]);
        $updated = $wpdb->update($table, ['is_primary' => 1], ['id' => $imageId, 'helmet_id' => $helmetId]);

        return $updated !== false;
    }

    /**
     * Delete an image record.
     */
    public function deleteImage(string $imageId): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        return (bool) $wpdb->delete($table, ['id' => $imageId]);
    }

    /**
     * Update validation status and audit results.
     */
    public function updateValidationStatus(string $imageId, string $status, ?array $auditData = null): bool
    {
        global $wpdb;
        $table = $wpdb->prefix . self::TABLE_NAME;

        $update = ['validation_status' => $status];
        if ($auditData !== null) {
            $update['audit_metadata_json'] = wp_json_encode($auditData);
        }

        $res = $wpdb->update($table, $update, ['id' => $imageId]);
        return $res !== false;
    }
}
