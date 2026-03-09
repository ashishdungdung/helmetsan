<?php

declare(strict_types=1);

namespace Helmetsan\Core\Ingestion;

use Helmetsan\Core\AI\ImageAnalysisService;
use Helmetsan\Core\Media\AssetManager;
use Helmetsan\Core\Media\CloudflareR2Service;
use Helmetsan\Core\Media\MediaEngine;
use WP_Error;

/**
 * Orchestrates the ingestion of helmet media from an external URL.
 */
class AssetIngestionService
{
    public function __construct(
        private readonly ScraperService $scraper,
        private readonly ImageAnalysisService $aiAnalysis,
        private readonly AssetManager $assetManager,
        private readonly MediaEngine $mediaEngine,
        private readonly CloudflareR2Service $cloudflareR2Service
    ) {}

    /**
     * Ingest assets from a URL and link them to a Helmet ID.
     *
     * @param int $helmetId The target helmet post ID.
     * @param string $sourceUrl The URL to scrape (e.g., Myntra product page).
     * @return array{success: int, failures: int, errors: array<string>}
     */
    public function ingestFromUrl(int $helmetId, string $sourceUrl): array
    {
        $result = [
            'success'  => 0,
            'failures' => 0,
            'errors'   => [],
        ];

        // 1. Scrape image URLs
        $urls = $this->scraper->scrapeImagesFromUrl($sourceUrl);
        if (is_wp_error($urls)) {
            $result['errors'][] = "Scraping failed: " . $urls->get_error_message();
            return $result;
        }

        if (empty($urls)) {
            $result['errors'][] = "No images found at the provided URL.";
            return $result;
        }

        $helmetTitle = get_the_title($helmetId);

        // 2. Process each URL
        foreach ($urls as $index => $imgUrl) {
            // A. Analyze with AI (to get suggested filename and photo type)
            // Passing the helmet title as known context
            $analysis = $this->aiAnalysis->analyzeImage($imgUrl, $helmetTitle);

            if ($analysis !== null) {
                // Check if AI deemed it irrelevant
                if (isset($analysis['is_relevant']) && $analysis['is_relevant'] === false) {
                    $result['failures']++;
                    $result['errors'][] = "Skipped $imgUrl: AI determined this image is not a relevant product photo.";
                    continue;
                }

                $photoType = $analysis['photo_type'];
                $slug = $analysis['suggested_filename'] . '-' . wp_generate_password(4, false, false);
            } else {
                $photoType = 'standard';
                $slug = sanitize_title($helmetTitle) . '-' . wp_generate_password(6, false, false) . '-' . 'asset';
            }

            // B. Prepare file
            $attachmentId = 0;
            $r2Url = '';

            if ($this->cloudflareR2Service->isEnabled()) {
                // Download to temp file
                require_once ABSPATH . 'wp-admin/includes/file.php';
                $tmpFile = download_url($imgUrl);

                if (is_wp_error($tmpFile)) {
                    $result['failures']++;
                    $result['errors'][] = "Failed to download $imgUrl for R2: " . $tmpFile->get_error_message();
                    continue;
                }

                $r2Key = 'assets/' . date('Y/m') . '/' . sanitize_title($helmetTitle) . '-' . uniqid() . '.jpg';
                $uploadResult = $this->cloudflareR2Service->uploadFile($tmpFile, $r2Key);

                @unlink($tmpFile);

                if (is_wp_error($uploadResult)) {
                    $result['failures']++;
                    $result['errors'][] = "Failed to upload to R2 ($imgUrl): " . $uploadResult->get_error_message();
                    continue;
                }

                $r2Url = $uploadResult;
            } else {
                // Sideload into WP Media Library
                $sideload = $this->mediaEngine->sideloadToMediaLibrary($imgUrl, $helmetId, 'url_ingestion');

                if (!empty($sideload['error'])) {
                    $result['failures']++;
                    $result['errors'][] = "Failed to download $imgUrl: " . $sideload['error'];
                    continue;
                }

                $attachmentId = $sideload['attachment_id'] ?? 0;
                if ($attachmentId <= 0) {
                    $result['failures']++;
                    continue;
                }

                // Rename the attachment post title for SEO based on AI
                wp_update_post([
                    'ID' => $attachmentId,
                    'post_title' => sanitize_text_field(str_replace('-', ' ', $slug))
                ]);
            }

            // C. Create Asset CPT & Link
            $assetId = $this->assetManager->createAsset($attachmentId, $imgUrl, $r2Url, $photoType, [$helmetId]);
            if (is_wp_error($assetId)) {
                $result['failures']++;
                $result['errors'][] = "Failed to create Asset for $imgUrl.";
            } else {
                $result['success']++;
            }
        }

        return $result;
    }
}
