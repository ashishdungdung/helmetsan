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

    public function testBuildMultiNetworkUrlUsesUkStoreIdForUkMarketplace(): void
    {
        $helmetId = 105;
        $links = [
            'amazon' => [
                'url' => 'https://www.amazon.com/s?k=Shoei+RF-1400&tag=vtete-20',
                'network' => 'amazon',
            ],
            'revzilla' => [
                'url' => 'https://www.revzilla.com/search?query=Shoei+RF-1400',
                'network' => 'direct',
            ],
        ];
        $GLOBALS['wp_post_meta'][$helmetId]['affiliate_links_json'] = json_encode($links);

        $settings = $this->config->revenueConfig();

        $this->assertSame('vtete-21', $settings['amazon_tag_uk']);

        $result = $this->service->buildMultiNetworkUrl($helmetId, 'amazon-uk', $settings);

        $this->assertSame('amazon', $result['network']);
        $this->assertStringContainsString('https://www.amazon.co.uk/s?k=Shoei+RF-1400', $result['url']);
        $this->assertStringContainsString('tag=vtete-21', $result['url']);
        $this->assertStringNotContainsString('tag=vtete-20', $result['url']);
    }

    public function testFilterRobotsTxtIncludesGeoDirectivesAndSitemaps(): void
    {
        $robots = $this->service->filterRobotsTxt('', true);

        $this->assertStringContainsString('User-agent: GPTBot', $robots);
        $this->assertStringContainsString('User-agent: ClaudeBot', $robots);
        $this->assertStringContainsString('User-agent: PerplexityBot', $robots);
        $this->assertStringContainsString('Allow: /helmets/*/', $robots);
        $this->assertStringContainsString('Allow: /comparison/', $robots);
        $this->assertStringContainsString('Allow: /brands/*/', $robots);
        $this->assertStringContainsString('Crawl-delay: 0', $robots);
        $this->assertStringContainsString('sitemap-brands.xml', $robots);
        $this->assertStringContainsString('sitemap-comparisons.xml', $robots);
        $this->assertStringContainsString('sitemap-helmets-images.xml', $robots);
    }

    public function testClassifyChannelCategorizesTrafficOriginsCorrectly(): void
    {
        // 1. AI Assistant
        $this->assertSame('ai_assistant', $this->service->classifyChannel('https://chatgpt.com/c/12345'));
        $this->assertSame('ai_assistant', $this->service->classifyChannel('https://www.perplexity.ai/search?q=shoei'));
        $this->assertSame('ai_assistant', $this->service->classifyChannel('https://claude.ai/chat/abc'));
        $this->assertSame('ai_assistant', $this->service->classifyChannel('', '', '', 'chatgpt'));

        // 2. Forum & Community
        $this->assertSame('forum', $this->service->classifyChannel('https://www.reddit.com/r/motorcycles/comments/xyz'));
        $this->assertSame('forum', $this->service->classifyChannel('https://www.advrider.com/f/threads/best-helmet.123/'));
        $this->assertSame('forum', $this->service->classifyChannel('https://www.bayarearidersforum.com/forums/showthread.php?t=1'));
        $this->assertSame('forum', $this->service->classifyChannel('', '', '', 'reddit'));

        // 3. Social Media
        $this->assertSame('social', $this->service->classifyChannel('https://www.youtube.com/watch?v=dQw4w9WgXcQ'));
        $this->assertSame('social', $this->service->classifyChannel('https://youtu.be/xyz123'));
        $this->assertSame('social', $this->service->classifyChannel('https://www.instagram.com/p/abc123/'));
        $this->assertSame('social', $this->service->classifyChannel('', '', 'social', ''));

        // 4. Search Engines
        $this->assertSame('search', $this->service->classifyChannel('https://www.google.com/url?sa=t&rct=j'));
        $this->assertSame('search', $this->service->classifyChannel('https://www.bing.com/search?q=helmets'));
        $this->assertSame('search', $this->service->classifyChannel('', '', 'organic', ''));

        // 5. Email
        $this->assertSame('email', $this->service->classifyChannel('', '', 'email', ''));
        $this->assertSame('email', $this->service->classifyChannel('', '', '', 'newsletter'));

        // 6. Direct
        $this->assertSame('direct', $this->service->classifyChannel(''));
    }

    public function testGetAttributionReportAggregatesClicksProperly(): void
    {
        $wpdbMock = new class {
            public string $prefix = 'wp_';
            public array $queries = [];

            public function get_var(?string $query = null): mixed {
                if (str_contains($query ?? '', 'SHOW TABLES LIKE')) {
                    return 'wp_helmetsan_clicks';
                }
                return 42;
            }

            public function prepare(string $query, ...$args): string {
                return $query;
            }

            public function get_results(string $query, string $output = 'OBJECT'): array {
                if (str_contains($query, 'ORDER BY created_at DESC')) {
                    return [
                        [
                            'helmet_id'        => 101,
                            'marketplace_id'   => 'amazon-us',
                            'referral_channel' => 'ai_assistant',
                            'utm_source'       => 'chatgpt',
                            'created_at'       => '2026-09-11 10:00:00',
                        ],
                    ];
                }
                if (str_contains($query, 'GROUP BY referral_channel')) {
                    return [
                        ['referral_channel' => 'ai_assistant', 'total' => 20],
                        ['referral_channel' => 'forum', 'total' => 15],
                        ['referral_channel' => 'social', 'total' => 7],
                    ];
                }
                if (str_contains($query, 'utm_source')) {
                    if (str_contains($query, 'GROUP BY utm_source')) {
                        return [
                            ['utm_source' => 'chatgpt', 'total' => 12],
                            ['utm_source' => 'reddit', 'total' => 10],
                        ];
                    }
                    // Recent conversions
                    return [
                        [
                            'helmet_id'        => 101,
                            'marketplace_id'   => 'amazon-us',
                            'referral_channel' => 'ai_assistant',
                            'utm_source'       => 'chatgpt',
                            'created_at'       => '2026-09-11 10:00:00',
                        ],
                    ];
                }
                if (str_contains($query, 'marketplace_id')) {
                    return [
                        ['marketplace_id' => 'amazon-us', 'total' => 30],
                        ['marketplace_id' => 'revzilla-us', 'total' => 12],
                    ];
                }
                if (str_contains($query, 'helmet_id')) {
                    return [
                        ['helmet_id' => 101, 'total' => 25],
                    ];
                }
                return [];
            }
        };

        $GLOBALS['wpdb'] = $wpdbMock;

        $report = $this->service->getAttributionReport(30);

        $this->assertTrue($report['ok']);
        $this->assertSame(42, $report['total_clicks']);
        $this->assertSame(20, $report['by_channel']['ai_assistant']);
        $this->assertSame(15, $report['by_channel']['forum']);
        $this->assertSame(12, $report['by_utm_source']['chatgpt']);
        $this->assertSame(30, $report['by_marketplace']['amazon-us']);
        $this->assertCount(1, $report['top_helmets']);
        $this->assertSame(101, $report['top_helmets'][0]['helmet_id']);
        $this->assertCount(1, $report['recent_conversions']);
    }
}


