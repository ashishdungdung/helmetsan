<?php

declare(strict_types=1);

namespace Helmetsan\Core\Media;

use Helmetsan\Core\Support\Config;

/**
 * Service for analyzing the health of catalog media assets.
 * Tracks coverage of high-fidelity images vs placeholders.
 */
final class MediaHealthService
{
    public function __construct(private readonly Config $config) {}

    /**
     * Perform a full scan of all helmets and their featured images.
     * 
     * @return array{
     *   total: int,
     *   high_fidelity: int,
     *   placeholders: int,
     *   missing: int,
     *   by_brand: array<string, array{total: int, ok: int, placeholder: int, missing: int}>,
     *   scanned_at: int
     * }
     */
    public function scan(): array
    {
        $query = new \WP_Query([
            'post_type'      => 'helmet',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        $ids = is_array($query->posts) ? array_map('intval', $query->posts) : [];
        
        $stats = [
            'total' => count($ids),
            'high_fidelity' => 0,
            'placeholders' => 0,
            'missing' => 0,
            'by_brand' => [],
            'scanned_at' => time(),
        ];

        foreach ($ids as $id) {
            $thumbId = (int) get_post_thumbnail_id($id);
            $brandId = (int) get_post_meta($id, 'rel_brand', true);
            $brandName = $brandId > 0 ? get_the_title($brandId) : 'Unknown';

            if (!isset($stats['by_brand'][$brandName])) {
                $stats['by_brand'][$brandName] = ['total' => 0, 'ok' => 0, 'placeholder' => 0, 'missing' => 0];
            }
            $stats['by_brand'][$brandName]['total']++;

            if ($thumbId <= 0) {
                $stats['missing']++;
                $stats['by_brand'][$brandName]['missing']++;
                continue;
            }

            $url = (string) wp_get_attachment_url($thumbId);
            if ($this->isPlaceholder($url)) {
                $stats['placeholders']++;
                $stats['by_brand'][$brandName]['placeholder']++;
            } else {
                $stats['high_fidelity']++;
                $stats['by_brand'][$brandName]['ok']++;
            }
        }

        // Sort brands by total helmets
        uasort($stats['by_brand'], fn($a, $b) => $b['total'] <=> $a['total']);

        return $stats;
    }

    /**
     * Check if a URL points to a placeholder or low-fidelity service.
     */
    public function isPlaceholder(string $url): bool
    {
        $placeholders = [
            'placehold.co',
            'via.placeholder',
            'dummyimage.com',
            'pollinations.ai', // AI generated are treated as placeholders until finalized/ingested locally
        ];

        foreach ($placeholders as $p) {
            if (strpos($url, $p) !== false) {
                return true;
            }
        }

        return false;
    }

    /**
     * Validate the quality of an attachment.
     * 
     * @return array{ok: bool, issues: string[]}
     */
    public function validateQuality(int $attachmentId): array
    {
        $issues = [];
        $file = get_attached_file($attachmentId);
        
        if (!$file || !file_exists($file)) {
            return ['ok' => false, 'issues' => ['File not found']];
        }

        $meta = wp_get_attachment_metadata($attachmentId) ?: [];
        $width = (int)($meta['width'] ?? 0);
        $height = (int)($meta['height'] ?? 0);

        if ($width < 800 || $height < 800) {
            $issues[] = "Low resolution: {$width}x{$height}";
        }

        $filesize = filesize($file);
        if ($filesize < 50000) { // < 50KB is suspicious for a product image
            $issues[] = "Small file size: " . round($filesize / 1024) . "KB";
        }

        return [
            'ok' => empty($issues),
            'issues' => $issues
        ];
    }
}
