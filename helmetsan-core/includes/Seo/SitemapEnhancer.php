<?php

declare(strict_types=1);

namespace Helmetsan\Core\Seo;

use WP_Post;
use WP_Query;
use Helmetsan\Core\Cache\ObjectCacheService;
use Helmetsan\Core\Discovery\AlternativesService;
use Helmetsan\Core\Media\MediaService;

/**
 * Dedicated High-Authority XML Sitemaps for Organic Search Dominance.
 *
 * Exposes:
 * - /sitemap-brands.xml: Brand hubs with lastmod derived from latest helmet update.
 * - /sitemap-comparisons.xml: Top head-to-head comparison URLs.
 * - /sitemap-helmets-images.xml: Product gallery and CDN images sitemap.
 */
final class SitemapEnhancer
{
    public const QUERY_VAR = 'helmetsan_sitemap';

    public function register(): void
    {
        add_action('init', [$this, 'addRewriteRules']);
        add_filter('query_vars', [$this, 'registerQueryVars']);
        add_action('template_redirect', [$this, 'handleSitemapRequest']);
        add_filter('wpseo_sitemap_index', [$this, 'filterYoastSitemapIndex']);

        add_action('save_post_helmet', [$this, 'invalidateCache']);
        add_action('save_post_brand', [$this, 'invalidateCache']);
    }

    public function addRewriteRules(): void
    {
        add_rewrite_rule('^sitemap-brands\.xml$', 'index.php?' . self::QUERY_VAR . '=brands', 'top');
        add_rewrite_rule('^sitemap-comparisons\.xml$', 'index.php?' . self::QUERY_VAR . '=comparisons', 'top');
        add_rewrite_rule('^sitemap-helmets-images\.xml$', 'index.php?' . self::QUERY_VAR . '=images', 'top');
    }

    /**
     * @param list<string> $vars
     * @return list<string>
     */
    public function registerQueryVars(array $vars): array
    {
        $vars[] = self::QUERY_VAR;
        return $vars;
    }

    /**
     * Handle incoming XML sitemap requests.
     */
    public function handleSitemapRequest(): void
    {
        $type = get_query_var(self::QUERY_VAR);
        if (! is_string($type) || $type === '') {
            return;
        }

        $allowed = ['brands', 'comparisons', 'images'];
        if (! in_array($type, $allowed, true)) {
            return;
        }

        if (! headers_sent()) {
            status_header(200);
            header('Content-Type: application/xml; charset=utf-8');
            header('X-Robots-Tag: noindex, follow', true);
        }

        echo $this->getSitemapXml($type);
        exit;
    }

    /**
     * Append custom sitemaps into Yoast SEO sitemap_index.xml if active.
     */
    public function filterYoastSitemapIndex(string $sitemapIndex): string
    {
        $sitemaps = [
            home_url('/sitemap-brands.xml'),
            home_url('/sitemap-comparisons.xml'),
            home_url('/sitemap-helmets-images.xml'),
        ];

        $extra = '';
        foreach ($sitemaps as $url) {
            $extra .= "\t<sitemap>\n";
            $extra .= "\t\t<loc>" . esc_url($url) . "</loc>\n";
            $extra .= "\t\t<lastmod>" . esc_xml(gmdate('c')) . "</lastmod>\n";
            $extra .= "\t</sitemap>\n";
        }

        return $sitemapIndex . $extra;
    }

    /**
     * Invalidate all cached sitemaps.
     */
    public function invalidateCache(): void
    {
        if (class_exists(ObjectCacheService::class)) {
            ObjectCacheService::delete('sitemap_brands', ObjectCacheService::GROUP_SCHEMA);
            ObjectCacheService::delete('sitemap_comparisons', ObjectCacheService::GROUP_SCHEMA);
            ObjectCacheService::delete('sitemap_images', ObjectCacheService::GROUP_SCHEMA);
        }
    }

    /**
     * Get sitemap XML content with caching.
     */
    public function getSitemapXml(string $type): string
    {
        $cacheKey = 'sitemap_' . $type;
        if (class_exists(ObjectCacheService::class)) {
            return ObjectCacheService::remember($cacheKey, ObjectCacheService::GROUP_SCHEMA, function () use ($type): string {
                return $this->generateSitemapXml($type);
            }, 43200);
        }

        return $this->generateSitemapXml($type);
    }

    /**
     * Generate raw XML for the specified sitemap type.
     */
    public function generateSitemapXml(string $type): string
    {
        return match ($type) {
            'brands'      => $this->buildBrandsSitemap(),
            'comparisons' => $this->buildComparisonsSitemap(),
            'images'      => $this->buildImagesSitemap(),
            default       => '',
        };
    }

    /**
     * Build sitemap-brands.xml
     * All brand hubs with lastmod derived from the latest modified helmet update.
     */
    public function buildBrandsSitemap(): string
    {
        $brandQuery = new WP_Query([
            'post_type'      => 'brand',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'title',
            'order'          => 'ASC',
        ]);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        if (! empty($brandQuery->posts)) {
            foreach ($brandQuery->posts as $brandPost) {
                if (! $brandPost instanceof WP_Post) {
                    continue;
                }

                $brandUrl = (string) get_permalink($brandPost->ID);
                if ($brandUrl === '') {
                    continue;
                }

                $lastmod = $this->getBrandLastModified($brandPost);

                $xml .= "\t<url>\n";
                $xml .= "\t\t<loc>" . esc_url($brandUrl) . "</loc>\n";
                $xml .= "\t\t<lastmod>" . esc_xml($lastmod) . "</lastmod>\n";
                $xml .= "\t\t<changefreq>weekly</changefreq>\n";
                $xml .= "\t\t<priority>0.8</priority>\n";
                $xml .= "\t</url>\n";
            }
        }
        wp_reset_postdata();

        $xml .= '</urlset>';
        return $xml;
    }

    /**
     * Build sitemap-comparisons.xml
     * High-authority comparison matrix URLs pairing popular helmets.
     */
    public function buildComparisonsSitemap(): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">' . "\n";

        // 1. Root Comparison Hub
        $comparisonBase = home_url('/comparison/');
        $xml .= "\t<url>\n";
        $xml .= "\t\t<loc>" . esc_url($comparisonBase) . "</loc>\n";
        $xml .= "\t\t<lastmod>" . esc_xml(gmdate('c')) . "</lastmod>\n";
        $xml .= "\t\t<changefreq>daily</changefreq>\n";
        $xml .= "\t\t<priority>0.9</priority>\n";
        $xml .= "\t</url>\n";

        // 2. Head-to-head comparison pairings
        $pairs = $this->generateComparisonPairs(60);
        foreach ($pairs as $pair) {
            $url = home_url('/comparison/?ids=' . urlencode($pair['slug_a']) . ',' . urlencode($pair['slug_b']));
            $xml .= "\t<url>\n";
            $xml .= "\t\t<loc>" . esc_url($url) . "</loc>\n";
            $xml .= "\t\t<lastmod>" . esc_xml($pair['lastmod']) . "</lastmod>\n";
            $xml .= "\t\t<changefreq>weekly</changefreq>\n";
            $xml .= "\t\t<priority>0.7</priority>\n";
            $xml .= "\t</url>\n";
        }

        $xml .= '</urlset>';
        return $xml;
    }

    /**
     * Build sitemap-helmets-images.xml
     * Comprehensive image sitemap for all published helmets including product galleries and R2 CDN URLs.
     */
    public function buildImagesSitemap(): string
    {
        $helmetQuery = new WP_Query([
            'post_type'      => 'helmet',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        $xml = '<?xml version="1.0" encoding="UTF-8"?>' . "\n";
        $xml .= '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"' . "\n";
        $xml .= '        xmlns:image="http://www.google.com/schemas/sitemap-image/1.1">' . "\n";

        if (! empty($helmetQuery->posts)) {
            $mediaService = class_exists(MediaService::class) ? new MediaService() : null;

            foreach ($helmetQuery->posts as $helmet) {
                if (! $helmet instanceof WP_Post) {
                    continue;
                }

                $loc = (string) get_permalink($helmet->ID);
                if ($loc === '') {
                    continue;
                }

                $images = $this->getHelmetImages($helmet->ID, $mediaService);
                if ($images === []) {
                    continue;
                }

                $lastmod = $this->formatW3cDate($helmet->post_modified_gmt ?: $helmet->post_modified);

                $xml .= "\t<url>\n";
                $xml .= "\t\t<loc>" . esc_url($loc) . "</loc>\n";
                $xml .= "\t\t<lastmod>" . esc_xml($lastmod) . "</lastmod>\n";

                foreach ($images as $img) {
                    $imgUrl = $img['url'] ?? '';
                    if ($imgUrl === '') {
                        continue;
                    }

                    $title = $img['title'] ?? $helmet->post_title;
                    $caption = $img['caption'] ?? $helmet->post_title;

                    $xml .= "\t\t<image:image>\n";
                    $xml .= "\t\t\t<image:loc>" . esc_url($imgUrl) . "</image:loc>\n";
                    if ($title !== '') {
                        $xml .= "\t\t\t<image:title>" . esc_xml($title) . "</image:title>\n";
                    }
                    if ($caption !== '') {
                        $xml .= "\t\t\t<image:caption>" . esc_xml($caption) . "</image:caption>\n";
                    }
                    $xml .= "\t\t</image:image>\n";
                }

                $xml .= "\t</url>\n";
            }
        }
        wp_reset_postdata();

        $xml .= '</urlset>';
        return $xml;
    }

    /**
     * Compute brand lastmod date: newest date between brand post and its child helmets.
     */
    private function getBrandLastModified(WP_Post $brandPost): string
    {
        global $wpdb;

        $brandDate = $this->formatW3cDate($brandPost->post_modified_gmt ?: $brandPost->post_modified);
        $brandId = $brandPost->ID;

        if (isset($wpdb) && is_object($wpdb) && ! empty($wpdb->posts) && ! empty($wpdb->postmeta) && method_exists($wpdb, 'prepare') && method_exists($wpdb, 'get_var')) {
            $latestHelmetDate = $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT p.post_modified_gmt 
                     FROM {$wpdb->posts} p
                     INNER JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id
                     WHERE p.post_type = 'helmet'
                       AND p.post_status = 'publish'
                       AND pm.meta_key IN ('brand', 'rel_brand')
                       AND pm.meta_value = %d
                     ORDER BY p.post_modified_gmt DESC
                     LIMIT 1",
                    $brandId
                )
            );

            if (is_string($latestHelmetDate) && $latestHelmetDate !== '' && $latestHelmetDate !== '0000-00-00 00:00:00') {
                $helmetFormatted = $this->formatW3cDate($latestHelmetDate);
                if ($helmetFormatted > $brandDate) {
                    return $helmetFormatted;
                }
            }
        }

        return $brandDate;
    }

    /**
     * Generate high-value comparison pairs.
     *
     * @return list<array{slug_a: string, slug_b: string, lastmod: string}>
     */
    private function generateComparisonPairs(int $limit = 60): array
    {
        $query = new WP_Query([
            'post_type'      => 'helmet',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'orderby'        => 'date',
            'order'          => 'DESC',
        ]);

        $posts = $query->posts;
        wp_reset_postdata();

        if (empty($posts) || count($posts) < 2) {
            return [];
        }

        $alternativesService = class_exists(AlternativesService::class) ? new AlternativesService() : null;
        $seenPairs = [];
        $pairs = [];

        foreach ($posts as $p) {
            if (! $p instanceof WP_Post) {
                continue;
            }

            $candIds = [];
            if ($alternativesService !== null) {
                $candIds = $alternativesService->findAlternatives($p->ID, 3);
            }

            if (empty($candIds)) {
                // Fallback: pair with neighboring helmets in query
                foreach ($posts as $other) {
                    if ($other instanceof WP_Post && $other->ID !== $p->ID) {
                        $candIds[] = $other->ID;
                        if (count($candIds) >= 2) {
                            break;
                        }
                    }
                }
            }

            foreach ($candIds as $candId) {
                $cand = get_post($candId);
                if (! $cand instanceof WP_Post || $cand->post_status !== 'publish') {
                    continue;
                }

                $slugA = $p->post_name;
                $slugB = $cand->post_name;

                if ($slugA === '' || $slugB === '' || $slugA === $slugB) {
                    continue;
                }

                // Canonical sort key to eliminate A vs B and B vs A duplication
                $pairKey = strcmp($slugA, $slugB) < 0 ? $slugA . '__' . $slugB : $slugB . '__' . $slugA;
                if (isset($seenPairs[$pairKey])) {
                    continue;
                }
                $seenPairs[$pairKey] = true;

                $modA = $this->formatW3cDate($p->post_modified_gmt ?: $p->post_modified);
                $modB = $this->formatW3cDate($cand->post_modified_gmt ?: $cand->post_modified);
                $newest = $modA > $modB ? $modA : $modB;

                $pairs[] = [
                    'slug_a'  => min($slugA, $slugB),
                    'slug_b'  => max($slugA, $slugB),
                    'lastmod' => $newest,
                ];

                if (count($pairs) >= $limit) {
                    return $pairs;
                }
            }
        }

        return $pairs;
    }

    /**
     * Retrieve all images for a helmet.
     *
     * @return list<array{url: string, title?: string, caption?: string}>
     */
    private function getHelmetImages(int $helmetId, ?MediaService $mediaService): array
    {
        $images = [];
        $seen = [];

        // 1. Featured image
        $thumbId = (int) get_post_thumbnail_id($helmetId);
        if ($thumbId > 0) {
            $src = wp_get_attachment_image_src($thumbId, 'full');
            if (is_array($src) && ! empty($src[0])) {
                $imgUrl = (string) $src[0];
                $seen[$imgUrl] = true;
                $alt = (string) get_post_meta($thumbId, '_wp_attachment_image_alt', true);
                $images[] = [
                    'url'     => $imgUrl,
                    'title'   => get_the_title($helmetId),
                    'caption' => $alt !== '' ? $alt : get_the_title($helmetId),
                ];
            }
        }

        // 2. MediaService product gallery & geo_media_json
        if ($mediaService !== null) {
            $gallery = $mediaService->getProductGallery($helmetId);
            foreach ($gallery as $item) {
                if (($item['type'] ?? '') !== 'image') {
                    continue;
                }
                $url = (string) ($item['url'] ?? '');
                if ($url !== '' && ! isset($seen[$url])) {
                    $seen[$url] = true;
                    $images[] = [
                        'url'     => $url,
                        'title'   => (string) ($item['alt'] ?? get_the_title($helmetId)),
                        'caption' => (string) ($item['alt'] ?? get_the_title($helmetId)),
                    ];
                }
            }
        }

        // 3. Cloudflare R2 images meta check
        $r2Images = get_post_meta($helmetId, '_asset_r2_url', false);
        if (is_array($r2Images)) {
            foreach ($r2Images as $r2Url) {
                if (is_string($r2Url) && $r2Url !== '' && ! isset($seen[$r2Url])) {
                    $seen[$r2Url] = true;
                    $images[] = [
                        'url'     => $r2Url,
                        'title'   => get_the_title($helmetId),
                        'caption' => get_the_title($helmetId),
                    ];
                }
            }
        }

        return $images;
    }

    /**
     * Format timestamp or SQL date string to W3C ISO 8601 string.
     */
    private function formatW3cDate(string $dateString): string
    {
        if ($dateString === '' || $dateString === '0000-00-00 00:00:00') {
            return gmdate('c');
        }

        $time = strtotime($dateString);
        if ($time === false || $time <= 0) {
            return gmdate('c');
        }

        return gmdate('c', $time);
    }
}
