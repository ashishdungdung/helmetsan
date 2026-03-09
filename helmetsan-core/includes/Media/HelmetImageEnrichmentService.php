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
        bool $useAi = false
    ): array {
        $useAi = $useAi || $useAiWhenNoEan;
        $stats = ['processed' => 0, 'filled' => 0, 'skipped' => 0, 'errors' => 0];
        $eanService = $this->media->getProductImageByEanService();

        $query = new \WP_Query([
            'post_type'      => 'helmet',
            'post_status'    => 'publish',
            'posts_per_page' => $limit > 0 ? $limit : -1,
            'orderby'        => 'ID',
            'order'          => 'ASC',
            'fields'         => 'ids',
        ]);
        $ids = is_array($query->posts) ? array_map('intval', $query->posts) : [];
        if ($onlyMissingThumb) {
            $ids = array_values(array_filter($ids, static function (int $id): bool {
                return (int) get_post_thumbnail_id($id) <= 0;
            }));
        }

        foreach ($ids as $helmetId) {
            $stats['processed']++;
            $ean = $this->getEanForHelmet($helmetId);

            if ($useEan && $ean !== '') {
                $img = $eanService->fetchImageByEan($ean);
                if ($img !== null && isset($img['url']) && $img['url'] !== '') {
                    if (! $dryRun) {
                        $result = $this->media->sideloadAndSetFeaturedImage(
                            $img['url'],
                            $helmetId,
                            $img['provider'] ?? 'ean'
                        );
                        if ($result['error'] !== '') {
                            $stats['errors']++;
                            $onProgress && $onProgress('error', $helmetId, $result['error']);
                        } else {
                            $stats['filled']++;
                            $onProgress && $onProgress('filled', $helmetId, 'EAN image');
                        }
                    } else {
                        $stats['filled']++;
                        $onProgress && $onProgress('filled', $helmetId, '[dry-run] EAN image');
                    }
                } else {
                    $stats['skipped']++;
                    $onProgress && $onProgress('skipped', $helmetId, 'No image for EAN');
                }
                continue;
            }

            // RevZilla: stored URL first; if none and AI available, use AI to find RevZilla link then fetch image
            if ($useRevZilla && $this->revZilla !== null) {
                $productUrl = $this->revZilla->getRevZillaUrlForHelmet($helmetId);
                if ($productUrl === null && $this->ai !== null) {
                    $productUrl = $this->ai->resolveRevZillaUrlForHelmet($helmetId);
                    if ($productUrl !== null && ! $dryRun) {
                        $this->saveRevZillaUrlToAffiliateLinks($helmetId, $productUrl);
                    }
                }
                if ($productUrl !== null) {
                    $revImgUrl = $this->revZilla->fetchImageFromProductPage($productUrl);
                    if ($revImgUrl !== null && $revImgUrl !== '') {
                        if (! $dryRun) {
                            $result = $this->media->sideloadAndSetFeaturedImage(
                                $revImgUrl,
                                $helmetId,
                                'revzilla'
                            );
                            if ($result['error'] !== '') {
                                $stats['errors']++;
                                $onProgress && $onProgress('error', $helmetId, $result['error']);
                            } else {
                                $stats['filled']++;
                                $onProgress && $onProgress('filled', $helmetId, 'RevZilla');
                            }
                        } else {
                            $stats['filled']++;
                            $onProgress && $onProgress('filled', $helmetId, '[dry-run] RevZilla');
                        }
                        continue;
                    }
                }
            }

            if (! $useAi || $this->ai === null) {
                $stats['skipped']++;
                $onProgress && $onProgress('skipped', $helmetId, 'No EAN');
                continue;
            }

            $resolved = $this->ai->resolveHelmetImageSource($helmetId);
            if ($resolved === null) {
                $stats['errors']++;
                $onProgress && $onProgress('error', $helmetId, 'AI resolve failed');
                continue;
            }

            $imageUrl = null;
            $provider = 'ai-resolved';

            if (isset($resolved['ean']) && $resolved['ean'] !== '') {
                $img = $eanService->fetchImageByEan($resolved['ean']);
                if ($img !== null && isset($img['url']) && $img['url'] !== '') {
                    $imageUrl = $img['url'];
                    $provider = $img['provider'] ?? 'ean';
                    if (! $dryRun) {
                        update_post_meta($helmetId, 'ean', $resolved['ean']);
                    }
                }
            }
            if ($imageUrl === null && isset($resolved['image_url']) && $resolved['image_url'] !== '') {
                $u = trim($resolved['image_url']);
                if (filter_var($u, FILTER_VALIDATE_URL) && (str_starts_with(strtolower($u), 'http://') || str_starts_with(strtolower($u), 'https://'))) {
                    $imageUrl = $u;
                }
            }

            if ($imageUrl !== null) {
                if (! $dryRun) {
                    $result = $this->media->sideloadAndSetFeaturedImage($imageUrl, $helmetId, $provider);
                    if ($result['error'] !== '') {
                        $stats['errors']++;
                        $onProgress && $onProgress('error', $helmetId, $result['error']);
                    } else {
                        $stats['filled']++;
                        $onProgress && $onProgress('filled', $helmetId, $provider);
                    }
                } else {
                    $stats['filled']++;
                    $onProgress && $onProgress('filled', $helmetId, '[dry-run] ' . $provider);
                }
            } else {
                $stats['skipped']++;
                $onProgress && $onProgress('skipped', $helmetId, 'AI returned no image');
            }
        }

        return $stats;
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
