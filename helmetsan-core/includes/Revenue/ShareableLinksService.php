<?php

declare(strict_types=1);

namespace Helmetsan\Core\Revenue;

use Helmetsan\Core\Support\Config;

final class ShareableLinksService
{
    public function __construct(
        private readonly Config $config,
        private readonly ?RevenueService $revenue = null,
    ) {}

    public function register(): void
    {
        add_action('init', [$this, 'registerRewriteRules']);
        add_filter('query_vars', [$this, 'registerQueryVars']);
        add_action('template_redirect', [$this, 'handleTemplateRedirect'], 5);
    }

    public function registerRewriteRules(): void
    {
        add_rewrite_rule(
            '^vs/([a-zA-Z0-9_-]+)-vs-([a-zA-Z0-9_-]+)/?$',
            'index.php?helmetsan_vs_a=$matches[1]&helmetsan_vs_b=$matches[2]',
            'top'
        );

        add_rewrite_rule(
            '^embed/([a-zA-Z0-9_-]+)/?$',
            'index.php?helmetsan_embed=$matches[1]',
            'top'
        );
    }

    /**
     * @param array<int,string> $vars
     * @return array<int,string>
     */
    public function registerQueryVars(array $vars): array
    {
        $vars[] = 'helmetsan_vs_a';
        $vars[] = 'helmetsan_vs_b';
        $vars[] = 'helmetsan_embed';

        return $vars;
    }

    public function handleTemplateRedirect(): void
    {
        // 1. Check Embed Widget Request: /embed/{slug}/
        $embedSlug = get_query_var('helmetsan_embed');
        if (is_string($embedSlug) && $embedSlug !== '') {
            $this->handleEmbedRequest($embedSlug);
            return;
        }

        // 2. Check Comparison Short Link: /vs/{slugA}-vs-{slugB}/
        $slugA = get_query_var('helmetsan_vs_a');
        $slugB = get_query_var('helmetsan_vs_b');
        if (is_string($slugA) && $slugA !== '' && is_string($slugB) && $slugB !== '') {
            $this->handleComparisonRequest($slugA, $slugB);
            return;
        }
    }

    private function handleComparisonRequest(string $slugA, string $slugB): void
    {
        $helmets = get_posts([
            'post_type'     => 'helmet',
            'post_name__in' => [$slugA, $slugB],
            'numberposts'   => 2,
            'post_status'   => 'publish',
        ]);

        if (count($helmets) < 2) {
            // Fallback: search accessories or redirect to main comparison
            wp_safe_redirect(home_url('/comparison/'), 302);
            exit;
        }

        // Set query parameter so page-comparison.php picks them up
        $_GET['ids'] = "{$slugA},{$slugB}";

        // Inject OpenGraph and Twitter Meta Tags for social sharing
        $titleA = get_the_title($helmets[0]);
        $titleB = get_the_title($helmets[1]);
        $ogTitle = "{$titleA} vs {$titleB} — Head-to-Head Comparison | Helmetsan";
        $w1 = get_post_meta($helmets[0]->ID, 'spec_weight_g', true) ?: get_post_meta($helmets[0]->ID, 'weight_g', true);
        $w2 = get_post_meta($helmets[1]->ID, 'spec_weight_g', true) ?: get_post_meta($helmets[1]->ID, 'weight_g', true);
        $n1 = get_post_meta($helmets[0]->ID, 'noise_db_at_100kph', true) ?: get_post_meta($helmets[0]->ID, 'spec_noise_db', true);
        $n2 = get_post_meta($helmets[1]->ID, 'noise_db_at_100kph', true) ?: get_post_meta($helmets[1]->ID, 'spec_noise_db', true);

        $specHighlights = [];
        if ($w1 && $w2) {
            $specHighlights[] = "weight ({$w1}g vs {$w2}g)";
        }
        if ($n1 && $n2) {
            $specHighlights[] = "noise level ({$n1}dB vs {$n2}dB)";
        }
        $specText = $specHighlights !== [] ? ' Compare verified ' . implode(' and ', $specHighlights) . '.' : '';
        $ogDesc = "Which helmet is safer, lighter, and quieter? Direct technical comparison between {$titleA} and {$titleB}.{$specText} Verified data by Helmetsan.";

        $img1 = get_the_post_thumbnail_url($helmets[0]->ID, 'large') ?: '';
        $img2 = get_the_post_thumbnail_url($helmets[1]->ID, 'large') ?: '';
        $ogImage = $img1 ?: $img2;
        $canonicalUrl = $this->getShareableComparisonUrl($slugA, $slugB);

        add_action('wp_head', static function () use ($ogTitle, $ogDesc, $ogImage, $canonicalUrl): void {
            echo '<meta property="og:title" content="' . esc_attr($ogTitle) . '">' . "\n";
            echo '<meta property="og:description" content="' . esc_attr($ogDesc) . '">' . "\n";
            echo '<meta property="og:type" content="article">' . "\n";
            echo '<meta property="og:url" content="' . esc_url($canonicalUrl) . '">' . "\n";
            if ($ogImage !== '') {
                echo '<meta property="og:image" content="' . esc_url($ogImage) . '">' . "\n";
            }
            echo '<meta name="twitter:card" content="summary_large_image">' . "\n";
            echo '<meta name="twitter:title" content="' . esc_attr($ogTitle) . '">' . "\n";
            echo '<meta name="twitter:description" content="' . esc_attr($ogDesc) . '">' . "\n";
            if ($ogImage !== '') {
                echo '<meta name="twitter:image" content="' . esc_url($ogImage) . '">' . "\n";
            }
        }, 2);

        $template = locate_template(['page-comparison.php', 'template-comparison.php']);
        if ($template !== '') {
            status_header(200);
            include $template;
            exit;
        }

        // Fallback if template file not directly resolvable
        wp_safe_redirect(home_url("/comparison/?ids={$slugA},{$slugB}"), 301);
        exit;
    }

    private function handleEmbedRequest(string $slug): void
    {
        $posts = get_posts([
            'name'           => $slug,
            'post_type'      => 'helmet',
            'posts_per_page' => 1,
            'post_status'    => 'publish',
            'lang'           => '',
        ]);

        if (empty($posts)) {
            $posts = get_posts([
                'name'           => $slug,
                'post_type'      => 'accessory',
                'posts_per_page' => 1,
                'post_status'    => 'publish',
                'lang'           => '',
            ]);
        }

        if (empty($posts)) {
            status_header(404);
            $this->render404Embed($slug);
            exit;
        }

        $helmet = $posts[0];
        $theme = isset($_GET['theme']) && strtolower((string) $_GET['theme']) === 'light' ? 'light' : 'dark';

        do_action('helmetsan_referral_embed_view', (int) $helmet->ID);

        $this->renderEmbedCard($helmet, $theme);
        exit;
    }

    private function renderEmbedCard(\WP_Post $helmet, string $theme = 'dark'): void
    {
        $helmetId = (int) $helmet->ID;
        $title = get_the_title($helmet);
        $permalink = get_permalink($helmet);
        $ctaUrl = add_query_arg([
            'utm_source'   => 'embed_widget',
            'utm_medium'   => 'referral',
            'utm_campaign' => 'spec_card',
        ], $permalink);

        $brandTerms = get_the_terms($helmetId, 'helmet_brand');
        $brand = (is_array($brandTerms) && ! empty($brandTerms)) ? $brandTerms[0]->name : (get_post_meta($helmetId, 'brand_name', true) ?: '');

        $weight = get_post_meta($helmetId, 'spec_weight_g', true) ?: get_post_meta($helmetId, 'weight_g', true);
        $noise = get_post_meta($helmetId, 'noise_db_at_100kph', true) ?: get_post_meta($helmetId, 'spec_noise_db', true);
        $sharp = (int) (get_post_meta($helmetId, 'sharp_rating', true) ?: 0);

        $certTerms = get_the_terms($helmetId, 'certification');
        $certs = is_array($certTerms) ? wp_list_pluck($certTerms, 'name') : [];
        if (empty($certs)) {
            $rawCert = (string) get_post_meta($helmetId, 'certifications', true);
            if ($rawCert !== '') {
                $certs = array_filter(array_map('trim', explode(',', $rawCert)));
            }
        }

        $priceUsd = get_post_meta($helmetId, 'price_usd', true) ?: get_post_meta($helmetId, 'price_retail_usd', true);
        $priceInr = get_post_meta($helmetId, 'price_inr', true);
        $priceDisplay = '';
        if (! empty($priceUsd)) {
            $priceDisplay = '$' . number_format((float) $priceUsd, 0);
        } elseif (! empty($priceInr)) {
            $priceDisplay = '₹' . number_format((float) $priceInr, 0);
        }

        $image = get_the_post_thumbnail_url($helmetId, 'medium') ?: '';

        // Headers for secure cross-origin embedding
        if (! headers_sent()) {
            header('Content-Type: text/html; charset=utf-8');
            header('X-Robots-Tag: noindex, follow');
            header('X-Frame-Options: ALLOWALL');
            header('Content-Security-Policy: frame-ancestors *');
            header('Cache-Control: public, max-age=86400, s-maxage=86400');
        }

        $isLight = ($theme === 'light');
        $bg = $isLight ? '#ffffff' : '#0f172a';
        $border = $isLight ? '#e2e8f0' : '#1e293b';
        $textMain = $isLight ? '#0f172a' : '#f8fafc';
        $textMuted = $isLight ? '#64748b' : '#94a3b8';
        $cardSurface = $isLight ? '#f8fafc' : '#1e293b';
        $accent = '#3b82f6';
        $accentHover = '#2563eb';
        $gold = '#f59e0b';
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo esc_html($title); ?> — Specs Widget</title>
    <style>
        * { box-sizing: border-box; margin: 0; padding: 0; }
        body {
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
            background-color: <?php echo $bg; ?>;
            color: <?php echo $textMain; ?>;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 12px;
            font-size: 14px;
            line-height: 1.4;
        }
        .hs-embed-card {
            width: 100%;
            max-width: 380px;
            background: <?php echo $bg; ?>;
            border: 1px solid <?php echo $border; ?>;
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0,0,0,0.15);
            display: flex;
            flex-direction: column;
            transition: transform 0.2s ease;
        }
        .hs-embed-header {
            padding: 14px 16px 8px;
            display: flex;
            align-items: flex-start;
            justify-content: space-between;
            gap: 12px;
        }
        .hs-brand-badge {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            color: <?php echo $accent; ?>;
        }
        .hs-title {
            font-size: 16px;
            font-weight: 800;
            line-height: 1.25;
            color: <?php echo $textMain; ?>;
            margin-top: 2px;
        }
        .hs-verified-badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            background: rgba(59, 130, 246, 0.12);
            color: <?php echo $accent; ?>;
            font-size: 10px;
            font-weight: 700;
            padding: 2px 8px;
            border-radius: 999px;
            white-space: nowrap;
        }
        .hs-embed-body {
            padding: 8px 16px 14px;
            display: flex;
            gap: 16px;
            align-items: center;
        }
        .hs-image-wrap {
            width: 90px;
            height: 90px;
            flex-shrink: 0;
            background: <?php echo $cardSurface; ?>;
            border-radius: 12px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        .hs-image-wrap img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .hs-specs-grid {
            flex: 1;
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 6px 12px;
        }
        .hs-spec-item {
            display: flex;
            flex-direction: column;
        }
        .hs-spec-label {
            font-size: 10px;
            color: <?php echo $textMuted; ?>;
            text-transform: uppercase;
            font-weight: 600;
        }
        .hs-spec-value {
            font-size: 13px;
            font-weight: 700;
            color: <?php echo $textMain; ?>;
        }
        .hs-stars {
            color: <?php echo $gold; ?>;
            font-size: 12px;
        }
        .hs-embed-footer {
            padding: 10px 16px;
            background: <?php echo $cardSurface; ?>;
            border-top: 1px solid <?php echo $border; ?>;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .hs-price {
            font-size: 15px;
            font-weight: 800;
            color: <?php echo $textMain; ?>;
        }
        .hs-btn {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 6px;
            background: <?php echo $accent; ?>;
            color: #ffffff;
            text-decoration: none;
            font-size: 12px;
            font-weight: 700;
            padding: 7px 14px;
            border-radius: 8px;
            transition: background 0.15s ease;
        }
        .hs-btn:hover {
            background: <?php echo $accentHover; ?>;
        }
    </style>
</head>
<body>
    <div class="hs-embed-card">
        <div class="hs-embed-header">
            <div>
                <?php if ($brand !== ''): ?>
                    <div class="hs-brand-badge"><?php echo esc_html($brand); ?></div>
                <?php endif; ?>
                <div class="hs-title"><?php echo esc_html($title); ?></div>
            </div>
            <div class="hs-verified-badge">✓ Verified Specs</div>
        </div>

        <div class="hs-embed-body">
            <?php if ($image !== ''): ?>
                <div class="hs-image-wrap">
                    <img src="<?php echo esc_url($image); ?>" alt="<?php echo esc_attr($title); ?>" loading="lazy">
                </div>
            <?php endif; ?>

            <div class="hs-specs-grid">
                <?php if (! empty($weight)): ?>
                    <div class="hs-spec-item">
                        <span class="hs-spec-label">Weight</span>
                        <span class="hs-spec-value"><?php echo esc_html((string) $weight); ?> g</span>
                    </div>
                <?php endif; ?>

                <?php if (! empty($noise)): ?>
                    <div class="hs-spec-item">
                        <span class="hs-spec-label">Noise</span>
                        <span class="hs-spec-value"><?php echo esc_html((string) $noise); ?> dB</span>
                    </div>
                <?php endif; ?>

                <?php if ($sharp > 0): ?>
                    <div class="hs-spec-item">
                        <span class="hs-spec-label">SHARP</span>
                        <span class="hs-spec-value hs-stars"><?php echo esc_html(str_repeat('★', $sharp) . str_repeat('☆', 5 - $sharp)); ?></span>
                    </div>
                <?php endif; ?>

                <?php if (! empty($certs)): ?>
                    <div class="hs-spec-item">
                        <span class="hs-spec-label">Standard</span>
                        <span class="hs-spec-value"><?php echo esc_html(implode(', ', array_slice($certs, 0, 2))); ?></span>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <div class="hs-embed-footer">
            <div>
                <?php if ($priceDisplay !== ''): ?>
                    <div class="hs-price"><?php echo esc_html($priceDisplay); ?></div>
                <?php else: ?>
                    <span style="font-size:11px;color:<?php echo $textMuted; ?>;">Helmetsan Data</span>
                <?php endif; ?>
            </div>
            <a href="<?php echo esc_url($ctaUrl); ?>" target="_blank" rel="noopener" class="hs-btn">
                View on Helmetsan &rarr;
            </a>
        </div>
    </div>
</body>
</html>
        <?php
    }

    private function render404Embed(string $slug): void
    {
        ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Helmet Not Found</title>
    <style>
        body { font-family: sans-serif; background: #0f172a; color: #94a3b8; display: flex; align-items: center; justify-content: center; height: 100vh; margin: 0; font-size: 13px; text-align: center; }
        .hs-box { border: 1px solid #1e293b; padding: 20px; border-radius: 12px; background: #1e293b; }
        a { color: #3b82f6; text-decoration: none; font-weight: bold; }
    </style>
</head>
<body>
    <div class="hs-box">
        <p>Helmet "<?php echo esc_html($slug); ?>" not found.</p>
        <p><a href="<?php echo esc_url(home_url('/helmets/')); ?>" target="_blank">Search Helmetsan Catalog &rarr;</a></p>
    </div>
</body>
</html>
        <?php
    }

    /**
     * Generate canonical comparison URL: /vs/slugA-vs-slugB/
     */
    public function getShareableComparisonUrl(string $slugA, string $slugB): string
    {
        $slugs = [sanitize_title($slugA), sanitize_title($slugB)];
        sort($slugs); // Alphabetical normalization for canonical consistency

        return home_url("/vs/{$slugs[0]}-vs-{$slugs[1]}/");
    }

    /**
     * Generate embed widget URL: /embed/slug/?theme=dark
     */
    public function getEmbedUrl(string $slug, string $theme = 'dark'): string
    {
        $cleanSlug = sanitize_title($slug);
        $url = home_url("/embed/{$cleanSlug}/");

        if ($theme === 'light') {
            $url = add_query_arg('theme', 'light', $url);
        }

        return $url;
    }

    /**
     * Generate HTML iframe embed snippet.
     */
    public function getEmbedSnippet(string $slug, string $theme = 'dark', int $width = 340, int $height = 240): string
    {
        $url = esc_url($this->getEmbedUrl($slug, $theme));
        $w = max(280, min(600, $width));
        $h = max(200, min(500, $height));

        return sprintf(
            '<iframe src="%s" width="%d" height="%d" style="border:none;border-radius:16px;overflow:hidden;max-width:100%%;" loading="lazy" title="Helmetsan Spec Widget"></iframe>',
            $url,
            $w,
            $h
        );
    }
}
