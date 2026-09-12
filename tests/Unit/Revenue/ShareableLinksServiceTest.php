<?php

declare(strict_types=1);

namespace Helmetsan\Core\Tests\Revenue;

use Helmetsan\Core\Revenue\ShareableLinksService;
use Helmetsan\Core\Revenue\RevenueService;
use Helmetsan\Core\Support\Config;
use PHPUnit\Framework\TestCase;

final class ShareableLinksServiceTest extends TestCase
{
    private ShareableLinksService $service;
    private Config $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = new Config();
        $revenue = (new \ReflectionClass(RevenueService::class))->newInstanceWithoutConstructor();
        $this->service = new ShareableLinksService($this->config, $revenue);

        $GLOBALS['wp_rewrite_rules'] = [];
        $GLOBALS['wp_query_vars'] = [];
        $GLOBALS['wp_post_meta'] = [];
        $GLOBALS['wp_post_fields'] = [];
        $GLOBALS['wp_post_terms'] = [];
    }

    public function testRegisterRewriteRulesAddsComparisonAndEmbedRoutes(): void
    {
        $this->service->registerRewriteRules();

        $rules = $GLOBALS['wp_rewrite_rules'] ?? [];
        $regexes = array_column($rules, 0);

        $this->assertContains('^vs/([a-zA-Z0-9_-]+)-vs-([a-zA-Z0-9_-]+)/?$', $regexes);
        $this->assertContains('^embed/([a-zA-Z0-9_-]+)/?$', $regexes);
    }

    public function testRegisterQueryVarsRegistersExpectedVariables(): void
    {
        $vars = $this->service->registerQueryVars(['test_var']);

        $this->assertContains('helmetsan_vs_a', $vars);
        $this->assertContains('helmetsan_vs_b', $vars);
        $this->assertContains('helmetsan_embed', $vars);
        $this->assertContains('test_var', $vars);
    }

    public function testGetShareableComparisonUrlSortsSlugsAlphabeticallyForCanonicalConsistency(): void
    {
        $url1 = $this->service->getShareableComparisonUrl('shoei-x-fifteen', 'agv-pista-gp-rr');
        $url2 = $this->service->getShareableComparisonUrl('agv-pista-gp-rr', 'shoei-x-fifteen');

        $this->assertSame($url1, $url2);
        $this->assertSame('https://helmetsan.com/vs/agv-pista-gp-rr-vs-shoei-x-fifteen/', $url1);
    }

    public function testGetEmbedUrlSupportsDarkAndLightTheme(): void
    {
        $darkUrl = $this->service->getEmbedUrl('shoei-rf-1400', 'dark');
        $lightUrl = $this->service->getEmbedUrl('shoei-rf-1400', 'light');

        $this->assertSame('https://helmetsan.com/embed/shoei-rf-1400/', $darkUrl);
        $this->assertSame('https://helmetsan.com/embed/shoei-rf-1400/?theme=light', $lightUrl);
    }

    public function testGetEmbedSnippetOutputsIframeWithCleanSecurityParameters(): void
    {
        $snippet = $this->service->getEmbedSnippet('arai-corsair-x', 'dark', 350, 420);

        $this->assertStringContainsString('<iframe src="https://helmetsan.com/embed/arai-corsair-x/"', $snippet);
        $this->assertStringContainsString('width="350"', $snippet);
        $this->assertStringContainsString('height="420"', $snippet);
        $this->assertStringContainsString('loading="lazy"', $snippet);
        $this->assertStringContainsString('border:none', $snippet);
        $this->assertStringContainsString('max-width:100%', $snippet);
    }

    public function testHandleTemplateRedirectSkipsWhenNoQueryVarsPresent(): void
    {
        $GLOBALS['wp_query_vars'] = [];

        // Calling without query vars should return normally without exiting or redirecting
        $this->service->handleTemplateRedirect();
        $this->assertTrue(true);
    }
}
