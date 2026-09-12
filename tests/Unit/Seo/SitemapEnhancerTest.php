<?php

declare(strict_types=1);

namespace Tests\Unit\Seo;

use Helmetsan\Core\Seo\SitemapEnhancer;
use PHPUnit\Framework\TestCase;
use WP_Post;

final class SitemapEnhancerTest extends TestCase
{
    private SitemapEnhancer $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new SitemapEnhancer();
        $GLOBALS['wp_mock_posts'] = [];
        $GLOBALS['wp_mock_query_posts'] = [];
        $GLOBALS['wp_rewrite_rules'] = [];
        $GLOBALS['wp_query_vars'] = [];
        $GLOBALS['wp_post_meta'] = [];
        $GLOBALS['wp_object_cache'] = [];
    }

    public function testAddRewriteRulesRegistersExpectedRules(): void
    {
        $this->service->addRewriteRules();

        $rules = $GLOBALS['wp_rewrite_rules'] ?? [];
        $this->assertNotEmpty($rules);

        $regexes = array_column($rules, 0);
        $this->assertContains('^sitemap-brands\.xml$', $regexes);
        $this->assertContains('^sitemap-comparisons\.xml$', $regexes);
        $this->assertContains('^sitemap-helmets-images\.xml$', $regexes);
    }

    public function testRegisterQueryVarsAddsHelmetsanSitemap(): void
    {
        $vars = $this->service->registerQueryVars(['post_type', 'paged']);
        $this->assertContains(SitemapEnhancer::QUERY_VAR, $vars);
    }

    public function testFilterYoastSitemapIndexAppendsCustomSitemaps(): void
    {
        $initial = "<sitemapindex>\n";
        $filtered = $this->service->filterYoastSitemapIndex($initial);

        $this->assertStringContainsString('sitemap-brands.xml', $filtered);
        $this->assertStringContainsString('sitemap-comparisons.xml', $filtered);
        $this->assertStringContainsString('sitemap-helmets-images.xml', $filtered);
        $this->assertStringContainsString('<sitemap>', $filtered);
        $this->assertStringContainsString('<loc>', $filtered);
    }

    public function testBuildBrandsSitemapOutputsValidXml(): void
    {
        $brand = new WP_Post();
        $brand->ID = 50;
        $brand->post_type = 'brand';
        $brand->post_name = 'shoei';
        $brand->post_title = 'Shoei';
        $brand->post_status = 'publish';
        $brand->post_modified_gmt = '2026-09-01 12:00:00';

        $GLOBALS['wp_mock_posts'][50] = $brand;
        $GLOBALS['wp_mock_query_posts'] = [$brand];

        $xml = $this->service->buildBrandsSitemap();

        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">', $xml);
        $this->assertStringContainsString('<loc>https://helmetsan.com/?p=50</loc>', $xml);
        $this->assertStringContainsString('<changefreq>weekly</changefreq>', $xml);
        $this->assertStringContainsString('<priority>0.8</priority>', $xml);
    }

    public function testBuildComparisonsSitemapIncludesRootAndPairs(): void
    {
        $h1 = new WP_Post();
        $h1->ID = 101;
        $h1->post_type = 'helmet';
        $h1->post_name = 'shoei-x-fifteen';
        $h1->post_title = 'Shoei X-Fifteen';
        $h1->post_status = 'publish';
        $h1->post_modified_gmt = '2026-09-10 10:00:00';

        $h2 = new WP_Post();
        $h2->ID = 102;
        $h2->post_type = 'helmet';
        $h2->post_name = 'agv-pista-gp-rr';
        $h2->post_title = 'AGV Pista GP RR';
        $h2->post_status = 'publish';
        $h2->post_modified_gmt = '2026-09-09 08:00:00';

        $GLOBALS['wp_mock_posts'][101] = $h1;
        $GLOBALS['wp_mock_posts'][102] = $h2;
        $GLOBALS['wp_mock_query_posts'] = [$h1, $h2];

        $xml = $this->service->buildComparisonsSitemap();

        $this->assertStringContainsString('<?xml version="1.0" encoding="UTF-8"?>', $xml);
        $this->assertStringContainsString('https://helmetsan.com/comparison/', $xml);
        $this->assertStringContainsString('ids=agv-pista-gp-rr,shoei-x-fifteen', $xml);
    }

    public function testBuildImagesSitemapOutputsImageTags(): void
    {
        $helmet = new WP_Post();
        $helmet->ID = 201;
        $helmet->post_type = 'helmet';
        $helmet->post_name = 'arai-rx-7v-evo';
        $helmet->post_title = 'Arai RX-7V EVO';
        $helmet->post_status = 'publish';
        $helmet->post_modified_gmt = '2026-09-10 14:00:00';

        $GLOBALS['wp_mock_posts'][201] = $helmet;
        $GLOBALS['wp_mock_query_posts'] = [$helmet];
        $GLOBALS['wp_post_thumbnail_id'][201] = 999;
        $GLOBALS['wp_attachment_image_src'][999] = ['https://helmetsan.com/wp-content/uploads/arai-rx-7v.jpg', 1200, 800, false];
        $GLOBALS['wp_post_meta'][999]['_wp_attachment_image_alt'] = 'Arai RX-7V EVO Full Face Helmet';

        $xml = $this->service->buildImagesSitemap();

        $this->assertStringContainsString('xmlns:image="http://www.google.com/schemas/sitemap-image/1.1"', $xml);
        $this->assertStringContainsString('<image:image>', $xml);
        $this->assertStringContainsString('<image:loc>https://helmetsan.com/wp-content/uploads/arai-rx-7v.jpg</image:loc>', $xml);
        $this->assertStringContainsString('<image:title>Arai RX-7V EVO</image:title>', $xml);
        $this->assertStringContainsString('<image:caption>Arai RX-7V EVO Full Face Helmet</image:caption>', $xml);
    }
}
