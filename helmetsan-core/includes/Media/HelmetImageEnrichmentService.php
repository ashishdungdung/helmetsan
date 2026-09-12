<?php

declare(strict_types=1);

namespace Helmetsan\Core\Media;

use Helmetsan\Core\AI\AiService;

/**
 * Matches catalog helmets with product images using EAN/GTIN, RevZilla product pages,
 * and optional AI, then imports images via Media Engine (sideload + set featured image).
 */
final class HelmetImageEnrichmentService
{
    private const EAN_META_KEYS = ['ean', 'gtin', 'upc'];

    public function __construct(
        private readonly MediaEngine $media,
        private readonly ?AiService $ai,
        private readonly ?RevZillaImageService $revZilla = null
    ) {
    }

    /**
     * Run enrichment: for each helmet (with optional filters), resolve image (EAN or AI) and import.
     *
     * @param int $limit Max helmets to process (0 = no limit)
     * @param bool $onlyMissingThumb Only process helmets that have no featured image
     * @param bool $useAiWhenNoEan When helmet has no EAN/RevZilla, use AI to resolve EAN or image URL
     * @param bool $dryRun If true, do not sideload or set thumbnails
     * @param callable|null $onProgress Callback (string $event, int $helmetId, string $message): void
     * @param bool $useEan Try EAN/GTIN/UPC lookup when present on helmet (default true)
     * @param bool $useRevZilla Try RevZilla product page when helmet has RevZilla link (default true)
     * @param bool $useAi Use AI when no image found from EAN or RevZilla (default false; set true for AI priority)
     * @param bool $gallery If true, also import additional images as gallery assets
     * @param bool $search If true, use AI to search for manufacturer images if other methods fail
     * @param bool $highRes If true, prioritize high-resolution image variants
     * @return array{processed: int, filled: int, skipped: int, errors: int}
     */
    public function run(
        int $limit = 0,
        bool $onlyMissingThumb = true,
        bool $useAiWhenNoEan = false,
        bool $dryRun = false,
        ?callable $onProgress = null,
        bool $useEan = true,
        bool $useRevZilla = true,
        bool $useAi = false,
        bool $gallery = false,
        bool $search = false,
        bool $highRes = true
    ): array {
        $stats = ['processed' => 0, 'filled' => 0, 'skipped' => 0, 'errors' => 0];
        $options = [
            'onlyMissingThumb' => $onlyMissingThumb,
            'useAiWhenNoEan'   => $useAiWhenNoEan,
            'dryRun'           => $dryRun,
            'useEan'           => $useEan,
            'useRevZilla'      => $useRevZilla,
            'useAi'            => $useAi,
            'gallery'          => $gallery,
            'search'           => $search,
            'highRes'          => $highRes,
        ];

        $query = new \WP_Query([
            'post_type'      => 'helmet',
            'post_status'    => 'publish',
            'posts_per_page' => $limit > 0 ? $limit : -1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ]);
        $ids = is_array($query->posts) ? array_map('intval', $query->posts) : [];
        
        if ($onlyMissingThumb && ! $gallery) {
            $ids = array_values(array_filter($ids, static function (int $id): bool {
                return (int) get_post_thumbnail_id($id) <= 0;
            }));
        }

        foreach ($ids as $helmetId) {
            $result = $this->processHelmet($helmetId, $options, $onProgress);
            $stats['processed']++;
            if ($result['status'] === 'filled') {
                $stats['filled']++;
            } elseif ($result['status'] === 'skipped') {
                $stats['skipped']++;
            } elseif ($result['status'] === 'error') {
                $stats['errors']++;
            }
        }

        return $stats;
    }

    /**
     * Process a single helmet for image enrichment.
     * 
     * @param array<string, mixed> $options
     * @return array{status: string, message?: string}
     */
    public function processHelmet(int $helmetId, array $options = [], ?callable $onProgress = null): array
    {
        $dryRun = ! empty($options['dryRun']);
        $gallery = ! empty($options['gallery']);
        $useEan = ! isset($options['useEan']) || ! empty($options['useEan']);
        $useRevZilla = ! isset($options['useRevZilla']) || ! empty($options['useRevZilla']);
        $useAi = ! empty($options['useAi']) || ! empty($options['useAiWhenNoEan']);
        $search = ! empty($options['search']);
        $highRes = ! isset($options['highRes']) || ! empty($options['highRes']);

        $eanService = $this->media->getProductImageByEanService();
        
        try {
            $ean = $this->getEanForHelmet($helmetId);
            $allImages = [];

            $foundMain = false;
            $productUrl = null;
            if ($useRevZilla && $this->revZilla !== null) {
                $productUrl = $this->revZilla->getRevZillaUrlForHelmet($helmetId);
            }

            // 1. Try EAN/GTIN
            if ($useEan && $ean !== '') {
                $img = $eanService->fetchImageByEan($ean);
                if ($img !== null && isset($img['url']) && $img['url'] !== '') {
                    $allImages[] = $img;
                    $foundMain = true;
                }
            }

            // 2. Try RevZilla
            if ((! $foundMain || $gallery) && $useRevZilla && $this->revZilla !== null) {
                if ($productUrl === null && $this->ai !== null) {
                    $productUrl = $this->ai->resolveRevZillaUrlForHelmet($helmetId);
                    if ($productUrl !== null && ! $dryRun) {
                        $this->saveRevZillaUrlToAffiliateLinks($helmetId, $productUrl);
                    }
                }
                if ($productUrl !== null) {
                    if ($gallery) {
                        $revImages = $this->revZilla->getAllImagesForHelmet($helmetId);
                        foreach ($revImages as $ri) {
                            $allImages[] = $ri;
                        }
                    } else {
                        $revImgUrl = $this->revZilla->fetchImageFromProductPage($productUrl);
                        if ($revImgUrl !== null) {
                            $allImages[] = ['url' => $revImgUrl, 'provider' => 'revzilla'];
                        }
                    }
                    if ($allImages !== []) {
                        $foundMain = true;
                    }
                }
            }

            // 3. Try AI Manufacturer Search Fallback
            if ((! $foundMain || $gallery) && $search && $this->ai !== null) {
                $manufacturerImages = $this->ai->resolveManufacturerImageUrls($helmetId);
                foreach ($manufacturerImages as $mi) {
                    $allImages[] = ['url' => $mi, 'provider' => 'manufacturer-search'];
                }
                if ($allImages !== []) {
                    $foundMain = true;
                }
            }

            // 4. Final resolve via resolveHelmetImageSource
            if (! $foundMain && $useAi && $this->ai !== null) {
                $resolved = $this->ai->resolveHelmetImageSource($helmetId);
                if ($resolved !== null) {
                    if (isset($resolved['ean']) && $resolved['ean'] !== '') {
                        $img = $eanService->fetchImageByEan($resolved['ean']);
                        if ($img !== null && isset($img['url']) && $img['url'] !== '') {
                            $allImages[] = $img;
                            if (! $dryRun) {
                                update_post_meta($helmetId, 'ean', $resolved['ean']);
                            }
                            $foundMain = true;
                        }
                    }
                    if (! $foundMain && isset($resolved['url']) && $resolved['url'] !== '') {
                        $allImages[] = ['url' => $resolved['url'], 'provider' => 'ai-direct'];
                        $foundMain = true;
                    }
                }
            }

            if ($allImages === []) {
                $onProgress && $onProgress('skipped', $helmetId, 'No images found from any source');
                return ['status' => 'skipped', 'message' => 'No images found'];
            }

            $allImages = $this->deduplicateImages($allImages);
            $urls = array_map(static fn ($img) => $img['url'], $allImages);

            if ($dryRun) {
                $onProgress && $onProgress('dry-run', $helmetId, 'Found ' . count($urls) . ' images');
                return ['status' => 'skipped', 'message' => 'Dry run completed'];
            }

            $downloads = $this->media->downloadMultiple($urls);
            $toDownload = [];
            foreach ($allImages as $i => $img) {
                $role = ($i === 0) ? 'main' : 'gallery';
                $toDownload[$img['url']] = [
                    'provider' => $img['provider'],
                    'role'     => $role,
                ];
            }

            $status = 'skipped';
            foreach ($downloads as $url => $result) {
                $info = $toDownload[$url];
                if ($result['error'] !== '') {
                    $onProgress && $onProgress('error', $helmetId, "Download error for $url: " . $result['error']);
                    continue;
                }

                $tmpName = $result['tmp_name'];
                $fileArray = [
                    'name'     => sanitize_file_name(basename($url)),
                    'tmp_name' => $tmpName,
                ];

                require_once ABSPATH . 'wp-admin/includes/file.php';
                require_once ABSPATH . 'wp-admin/includes/media.php';
                require_once ABSPATH . 'wp-admin/includes/image.php';

                if ($info['role'] === 'main') {
                    $attachmentId = media_handle_sideload($fileArray, $helmetId);
                    if (is_wp_error($attachmentId)) {
                        @unlink($tmpName);
                        $onProgress && $onProgress('error', $helmetId, $attachmentId->get_error_message());
                        return ['status' => 'error', 'message' => $attachmentId->get_error_message()];
                    } else {
                        set_post_thumbnail($helmetId, $attachmentId);
                        update_post_meta((int) $attachmentId, '_helmetsan_source_url', esc_url_raw($url));
                        update_post_meta((int) $attachmentId, '_helmetsan_source_provider', sanitize_text_field($info['provider']));
                        $onProgress && $onProgress('filled', $helmetId, $info['provider']);
                        $status = 'filled';
                    }
                } else {
                    $attachmentId = media_handle_sideload($fileArray, 0);
                    if (is_wp_error($attachmentId)) {
                        @unlink($tmpName);
                        continue;
                    }
                    update_post_meta((int) $attachmentId, '_helmetsan_source_url', esc_url_raw($url));
                    update_post_meta((int) $attachmentId, '_helmetsan_source_provider', sanitize_text_field($info['provider']));

                    $parent = get_post($helmetId);
                    $title = ($parent instanceof \WP_Post) ? $parent->post_title . ' Gallery' : 'Helmet Gallery';
                    $assetId = wp_insert_post([
                        'post_title'   => $title,
                        'post_type'    => 'asset',
                        'post_status'  => 'publish',
                        'post_parent'  => $helmetId,
                    ]);
                    if (! is_wp_error($assetId)) {
                        set_post_thumbnail($assetId, $attachmentId);
                        update_post_meta($assetId, 'asset_type', 'image');
                        update_post_meta($assetId, '_helmetsan_source_url', $url);
                    }
                }
            }
            return ['status' => $status];
        } catch (\Throwable $e) {
            $onProgress && $onProgress('error', $helmetId, 'Unhandled exception: ' . $e->getMessage());
            return ['status' => 'error', 'message' => $e->getMessage()];
        }
    }

    /**
     * Deduplicate image list by URL.
     *
     * @param array<array{url: string, provider: string}> $images
     * @return array<array{url: string, provider: string}>
     */
    private function deduplicateImages(array $images): array
    {
        $unique = [];
        $urls   = [];
        foreach ($images as $img) {
            $url = trim($img['url']);
            if ($url === '' || in_array($url, $urls, true)) {
                continue;
            }
            $urls[]   = $url;
            $unique[] = $img;
        }
        return $unique;
    }

    private function getEanForHelmet(int $helmetId): string
    {
        foreach (self::EAN_META_KEYS as $key) {
            $v = get_post_meta($helmetId, $key, true);
            if (is_string($v) && $v !== '') {
                $cleaned = preg_replace('/\D/', '', $v);
                if ($cleaned !== '' && strlen($cleaned) >= 8) {
                    return $cleaned;
                }
            }
        }
        return '';
    }

    /**
     * Merge a RevZilla product URL into affiliate_links_json so future runs use the stored link.
     */
    private function saveRevZillaUrlToAffiliateLinks(int $helmetId, string $revZillaUrl): void
    {
        $json = (string) get_post_meta($helmetId, 'affiliate_links_json', true);
        $links = $json !== '' ? json_decode($json, true) : [];
        if (! is_array($links)) {
            $links = [];
        }
        $links['revzilla-us'] = [
            'url'     => esc_url_raw($revZillaUrl),
            'network' => 'direct',
        ];
        update_post_meta($helmetId, 'affiliate_links_json', wp_json_encode($links, JSON_UNESCAPED_SLASHES));
    }
}
