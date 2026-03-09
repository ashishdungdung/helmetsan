<?php

declare(strict_types=1);

namespace Helmetsan\Core\Media;

use WP_Post;
use WP_Error;

/**
 * Manages operations related to the 'asset' Custom Post Type.
 */
class AssetManager
{
    /**
     * Create a new Asset post and link it to an attachment and optionally helmets.
     *
     * @param int $attachmentId The WordPress media attachment ID.
     * @param string $sourceUrl The original source URL of the image.
     * @param string $r2Url The R2 bucket public URL (if offloaded).
     * @param string $assetType The semantic type (e.g. 'front-view').
     * @param array<int> $linkedHelmetIds Array of helmet post IDs to link.
     * @return int|WP_Error The created asset post ID, or WP_Error on failure.
     */
    public function createAsset(int $attachmentId, string $sourceUrl, string $r2Url, string $assetType, array $linkedHelmetIds = [])
    {
        $attachment = $attachmentId > 0 ? get_post($attachmentId) : null;
        if ($attachmentId > 0 && (!$attachment || $attachment->post_type !== 'attachment')) {
            return new WP_Error('invalid_attachment', 'Invalid attachment ID provided.');
        }

        $title = $attachmentId > 0 ? wp_strip_all_tags(get_the_title($attachmentId)) : 'Asset - ' . basename($r2Url);

        $assetData = [
            'post_title'   => $title,
            'post_status'  => 'publish',
            'post_type'    => 'asset',
            'meta_input'   => [
                '_asset_source_url' => $sourceUrl,
                '_asset_type'       => $assetType,
                '_asset_r2_url'     => $r2Url,
                'rel_helmets'       => $linkedHelmetIds,
            ],
        ];

        $assetId = wp_insert_post($assetData, true);

        if (!is_wp_error($assetId) && $attachmentId > 0) {
            // Set the attachment as the featured image for the asset CPT
            set_post_thumbnail($assetId, $attachmentId);
        }

        return $assetId;
    }

    /**
     * Link an existing asset to a helmet.
     *
     * @param int $assetId
     * @param int $helmetId
     * @return bool
     */
    public function linkAssetToHelmet(int $assetId, int $helmetId): bool
    {
        $existingLinks = get_post_meta($assetId, 'rel_helmets', true);
        if (!is_array($existingLinks)) {
            $existingLinks = [];
        }

        if (!in_array($helmetId, $existingLinks, true)) {
            $existingLinks[] = $helmetId;
            update_post_meta($assetId, 'rel_helmets', $existingLinks);
            return true;
        }

        return false;
    }

    /**
     * Get all assets linked to a specific helmet.
     *
     * @param int $helmetId
     * @return array<WP_Post>
     */
    public function getAssetsForHelmet(int $helmetId): array
    {
        $args = [
            'post_type'      => 'asset',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'meta_query'     => [
                [
                    'key'     => 'rel_helmets',
                    'value'   => serialize($helmetId), // Note: querying serialized arrays can be slow, but works for basic relations. We might use specific meta query depending on serialization.
                    'compare' => 'LIKE',
                ],
            ],
        ];

        return get_posts($args);
    }
}
