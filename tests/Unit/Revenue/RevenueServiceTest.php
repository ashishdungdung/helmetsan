<?php

declare(strict_types=1);

namespace Helmetsan\Core\Tests\Revenue;

use Helmetsan\Core\Revenue\RevenueService;
use Helmetsan\Core\Support\Config;
use PHPUnit\Framework\TestCase;

final class RevenueServiceTest extends TestCase
{
    private RevenueService $service;
    private Config $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = new Config();
        $this->service = new RevenueService($this->config);
        $GLOBALS['wp_post_meta'] = [];
        $GLOBALS['wp_post_fields'] = [];
        $GLOBALS['wp_post_terms'] = [];
        $GLOBALS['wp_term_meta'] = [];
    }

    public function testBuildAmazonUrlReplacesExistingTagCleanlyWithoutDuplication(): void
    {
        $helmetId = 101;
        $links = [
            'amazon-us' => [
                'url' => 'https://www.amazon.com/dp/B08XYZ1234?tag=old-affiliate-20&ref=sr_1_1',
                'network' => 'amazon'
            ]
        ];
        $GLOBALS['wp_post_meta'][$helmetId]['affiliate_links_json'] = json_encode($links);

        $settings = $this->config->revenueConfig();
        $settings['amazon_tag'] = 'vtete-20';

        $result = $this->service->buildMultiNetworkUrl($helmetId, 'amazon-us', $settings);

        $this->assertSame('amazon', $result['network']);
        $this->assertStringContainsString('tag=vtete-20', $result['url']);
        $this->assertStringNotContainsString('old-affiliate-20', $result['url']);
    }

    public function testBuildMultiNetworkUrlAutoDetectsNetworksFromDomain(): void
    {
        $helmetId = 102;
        $links = [
            'revzilla-us' => [
                'url' => 'https://www.revzilla.com/motorcycle/shoei-rf-1400-helmet',
                'network' => 'direct' // ingested as direct
            ]
        ];
        $GLOBALS['wp_post_meta'][$helmetId]['affiliate_links_json'] = json_encode($links);

        $settings = $this->config->revenueConfig();
        $settings['affiliate_networks']['cj'] = [
            'website_id' => '1234567',
        ];

        $result = $this->service->buildMultiNetworkUrl($helmetId, 'revzilla-us', $settings);

        $this->assertSame('cj', $result['network']);
        $this->assertStringContainsString('https://www.anrdoezrs.net/links/1234567/type/dlg/sid/102/', $result['url']);
        $this->assertStringContainsString('revzilla.com', $result['url']);
    }

    public function testBuildCjUrlGuardsAgainstDoubleWrapping(): void
    {
        $helmetId = 103;
        $existingCjUrl = 'https://www.anrdoezrs.net/links/1234567/type/dlg/sid/103/https://www.revzilla.com/motorcycle/shoei-rf-1400';
        $links = [
            'revzilla-us' => [
                'url' => $existingCjUrl,
                'network' => 'cj'
            ]
        ];
        $GLOBALS['wp_post_meta'][$helmetId]['affiliate_links_json'] = json_encode($links);

        $settings = $this->config->revenueConfig();
        $settings['affiliate_networks']['cj'] = [
            'website_id' => '1234567',
        ];

        $result = $this->service->buildMultiNetworkUrl($helmetId, 'revzilla-us', $settings);

        // Should return the URL without prepending another anrdoezrs.net prefix
        $this->assertSame($existingCjUrl, $result['url']);
    }

    public function testBuildLegacyUrlSupportsAccessoryPriceJson(): void
    {
        $accessoryId = 201;
        $GLOBALS['wp_post_meta'][$accessoryId]['price_json'] = json_encode([
            'current' => 29.99,
            'url' => 'https://www.partner-shop.com/visor-pinlock'
        ]);

        $settings = $this->config->revenueConfig();
        $url = $this->service->buildLegacyUrl($accessoryId, $settings);

        $this->assertSame('https://www.partner-shop.com/visor-pinlock', $url);
    }

    public function testGeoAmazonMarketplaceIdMapping(): void
    {
        $this->assertSame('amazon-in', $this->service->getGeoAmazonMarketplaceId('IN'));
        $this->assertSame('amazon-uk', $this->service->getGeoAmazonMarketplaceId('UK'));
        $this->assertSame('amazon-uk', $this->service->getGeoAmazonMarketplaceId('GB'));
        $this->assertSame('amazon-de', $this->service->getGeoAmazonMarketplaceId('DE'));
        $this->assertSame('amazon-us', $this->service->getGeoAmazonMarketplaceId('US'));
    }

    public function testBuildMultiNetworkUrlFallsBackToGenericAmazonAndAdaptsRegionalDomain(): void
    {
        $helmetId = 104;
        $links = [
            'amazon' => [
                'url' => 'https://www.amazon.com/s?k=6D+Helmets+6D+ATS-1R&tag=helmetsan-20',
                'network' => 'amazon',
            ],
        ];
        $GLOBALS['wp_post_meta'][$helmetId]['affiliate_links_json'] = json_encode($links);

        $settings = $this->config->revenueConfig();
        $settings['amazon_tag_de'] = 'helmetsan-de-21';

        $result = $this->service->buildMultiNetworkUrl($helmetId, 'amazon-cz', $settings);

        $this->assertSame('amazon', $result['network']);
        $this->assertStringContainsString('https://www.amazon.de/s?k=6D+Helmets+6D+ATS-1R', $result['url']);
        $this->assertStringContainsString('tag=helmetsan-de-21', $result['url']);
    }
}
