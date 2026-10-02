<?php

declare(strict_types=1);

namespace Helmetsan\Core\Frontend;

use Helmetsan\Core\Media\HelmetImageManager;

/**
 * Dedicated 5-Shot Product Gallery Frontend Block
 *
 * Renders the interactive 5-angle product gallery on single helmet listings
 * with high-res WebP derivatives, Kimi-K3 trust badges, zoom lightbox, and Schema.org markup.
 */
final class HelmetGalleryBlock
{
    private bool $assetsEnqueued = false;

    public function __construct(
        private readonly HelmetImageManager $imageManager
    ) {}

    public function register(): void
    {
        add_shortcode('helmetsan_gallery', [$this, 'renderShortcode']);
        add_shortcode('helmetsan_helmet_gallery', [$this, 'renderShortcode']);
        add_filter('the_content', [$this, 'prependGalleryToHelmetContent'], 5);
        add_action('wp_enqueue_scripts', [$this, 'registerAssets']);
    }

    public function registerAssets(): void
    {
        $cssUrl = plugins_url('assets/css/helmet-product-gallery.css', dirname(__DIR__));
        $jsUrl = plugins_url('assets/js/helmet-product-gallery.js', dirname(__DIR__));

        wp_register_style('helmetsan-product-gallery-css', $cssUrl, [], defined('HELMETSAN_CORE_VERSION') ? HELMETSAN_CORE_VERSION : '1.0.0');
        wp_register_script('helmetsan-product-gallery-js', $jsUrl, [], defined('HELMETSAN_CORE_VERSION') ? HELMETSAN_CORE_VERSION : '1.0.0', true);
    }

    public function enqueueAssets(): void
    {
        if ($this->assetsEnqueued) {
            return;
        }
        wp_enqueue_style('helmetsan-product-gallery-css');
        wp_enqueue_script('helmetsan-product-gallery-js');
        $this->assetsEnqueued = true;
    }

    public function prependGalleryToHelmetContent(string $content): string
    {
        if (! is_singular('helmet') || ! in_the_loop() || ! is_main_query()) {
            return $content;
        }

        $galleryHtml = $this->renderGallery((int) get_the_ID());
        return $galleryHtml . $content;
    }

    /**
     * @param array<string, mixed> $atts
     */
    public function renderShortcode(array $atts = []): string
    {
        $postId = isset($atts['id']) || isset($atts['helmet_id']) 
            ? (int) ($atts['id'] ?? $atts['helmet_id']) 
            : (int) get_the_ID();

        if ($postId <= 0 || get_post_type($postId) !== 'helmet') {
            return '';
        }

        return $this->renderGallery($postId);
    }

    public function renderGallery(int $postId): string
    {
        $this->enqueueAssets();

        $helmetId = (string) $postId;
        $coverage = $this->imageManager->getCoverageForHelmet($helmetId);
        $shotsMap = $coverage['shots'] ?? [];

        // Build list of displayable shots
        $availableShots = [];
        foreach (HelmetImageManager::CANONICAL_SHOTS as $shotType => $spec) {
            $shot = $shotsMap[$shotType] ?? null;
            if ($shot !== null && !empty($shot['url'])) {
                $auditData = $shot['audit_metadata'] ?? [];
                $score = $auditData['quality_score'] ?? ($shot['validation_status'] === 'audit_passed' ? 95 : 0);
                
                $availableShots[] = [
                    'shot_type' => $shotType,
                    'title' => $spec['label'],
                    'desc' => $spec['desc'],
                    'hero_url' => $shot['url'],
                    'thumb_url' => $shot['thumbnail_url'] ?: $shot['url'],
                    'score' => (int) $score,
                    'is_primary' => !empty($shot['is_primary']),
                ];
            }
        }

        // If no 5-shot images registered yet, fallback to featured image if available
        if (empty($availableShots)) {
            $featuredUrl = get_the_post_thumbnail_url($postId, 'large');
            if (! $featuredUrl) {
                return ''; // No images to display
            }
            $availableShots[] = [
                'shot_type' => 'front_hero',
                'title' => 'Product Photograph',
                'desc' => get_the_title($postId),
                'hero_url' => $featuredUrl,
                'thumb_url' => get_the_post_thumbnail_url($postId, 'thumbnail') ?: $featuredUrl,
                'score' => 0,
                'is_primary' => true,
            ];
        }

        $activeShot = $availableShots[0];
        $brandTerms = get_the_terms($postId, 'helmet_brand');
        $brandName = (!empty($brandTerms) && is_array($brandTerms)) ? $brandTerms[0]->name : 'Helmetsan';
        $helmetTitle = get_the_title($postId);

        ob_start();
        ?>
        <div class="helmetsan-gallery-wrap" aria-label="<?php echo esc_attr__('Helmet 5-Shot Studio Gallery', 'helmetsan-core'); ?>">
            <!-- Viewport -->
            <div class="hs-gallery-viewport">
                <span class="hs-gallery-badge-top">
                    <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="12" cy="12" r="10"></circle><polyline points="12 6 12 12 16 14"></polyline></svg>
                    <?php echo esc_html__('5-SHOT STUDIO', 'helmetsan-core'); ?>
                </span>

                <?php if ($activeShot['score'] > 0) : ?>
                    <span class="hs-gallery-badge-kimi">
                        ✓ Kimi-K3 Inspected • <strong><?php echo esc_html((string) $activeShot['score']); ?>/100</strong>
                    </span>
                <?php else : ?>
                    <span class="hs-gallery-badge-kimi" style="display:none;"></span>
                <?php endif; ?>

                <img 
                    class="hs-gallery-main-img" 
                    src="<?php echo esc_url($activeShot['hero_url']); ?>" 
                    alt="<?php echo esc_attr("{$brandName} {$helmetTitle} - {$activeShot['title']}"); ?>" 
                    loading="eager" 
                />

                <div class="hs-gallery-caption-bar">
                    <div>
                        <h3 class="hs-gallery-angle-title"><?php echo esc_html($activeShot['title']); ?></h3>
                        <p class="hs-gallery-angle-desc"><?php echo esc_html($activeShot['desc']); ?></p>
                    </div>
                    <div class="hs-gallery-zoom-hint">
                        <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="11" cy="11" r="8"></circle><line x1="21" y1="21" x2="16.65" y2="16.65"></line><line x1="11" y1="8" x2="11" y2="14"></line><line x1="8" y1="11" x2="14" y2="11"></line></svg>
                        <?php echo esc_html__('Click to zoom', 'helmetsan-core'); ?>
                    </div>
                </div>
            </div>

            <!-- Thumbnail Selector Strip -->
            <?php if (count($availableShots) > 1) : ?>
                <div class="hs-gallery-thumbs-strip" role="tablist">
                    <?php foreach ($availableShots as $idx => $shot) : ?>
                        <button 
                            type="button" 
                            class="hs-gallery-thumb-btn <?php echo $idx === 0 ? 'active' : ''; ?>"
                            data-hero-url="<?php echo esc_url($shot['hero_url']); ?>"
                            data-title="<?php echo esc_attr($shot['title']); ?>"
                            data-desc="<?php echo esc_attr($shot['desc']); ?>"
                            data-score="<?php echo esc_attr((string) $shot['score']); ?>"
                            aria-label="<?php echo esc_attr($shot['title']); ?>"
                        >
                            <img class="hs-gallery-thumb-img" src="<?php echo esc_url($shot['thumb_url']); ?>" alt="" loading="lazy" />
                            <span class="hs-gallery-thumb-label"><?php echo esc_html($shot['title']); ?></span>
                        </button>
                    <?php endforeach; ?>
                </div>
            <?php endif; ?>

            <!-- Lightbox Modal -->
            <div class="hs-gallery-lightbox hidden">
                <img class="hs-lightbox-img" src="<?php echo esc_url($activeShot['hero_url']); ?>" alt="<?php echo esc_attr($helmetTitle); ?>" />
            </div>

            <!-- Schema.org JSON-LD Gallery Metadata -->
            <script type="application/ld+json">
            {
              "@context": "https://schema.org",
              "@type": "ImageGallery",
              "name": <?php echo wp_json_encode("{$brandName} {$helmetTitle} 5-Shot Studio Gallery"); ?>,
              "associatedMedia": [
                <?php
                $jsonMedia = [];
                foreach ($availableShots as $s) {
                    $jsonMedia[] = json_encode([
                        '@type' => 'ImageObject',
                        'contentUrl' => $s['hero_url'],
                        'thumbnailUrl' => $s['thumb_url'],
                        'name' => $s['title'],
                        'caption' => $s['desc'],
                    ], JSON_UNESCAPED_SLASHES);
                }
                echo implode(",\n", $jsonMedia);
                ?>
              ]
            }
            </script>
        </div>
        <?php
        return (string) ob_get_clean();
    }
}
