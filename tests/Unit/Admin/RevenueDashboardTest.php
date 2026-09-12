<?php

declare(strict_types=1);

namespace Helmetsan\Core\Tests\Admin;

use Helmetsan\Core\Admin\RevenueDashboard;
use Helmetsan\Core\Price\PriceHistory;
use Helmetsan\Core\Revenue\RevenueService;
use Helmetsan\Core\Support\Config;
use PHPUnit\Framework\TestCase;

final class RevenueDashboardTest extends TestCase
{
    private RevenueDashboard $dashboard;
    private RevenueService $revenue;
    private PriceHistory $priceHistory;
    private Config $config;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = new Config();
        $this->revenue = new RevenueService($this->config);
        $this->priceHistory = (new \ReflectionClass(PriceHistory::class))->newInstanceWithoutConstructor();
        $this->dashboard = new RevenueDashboard($this->revenue, $this->priceHistory, $this->config);
    }

    public function testRenderPageRendersAttributionAndRevenueSections(): void
    {
        $wpdbMock = new class {
            public string $prefix = 'wp_';

            public function get_var(?string $query = null): mixed {
                if (str_contains($query ?? '', 'SHOW TABLES LIKE')) {
                    return 'wp_helmetsan_clicks';
                }
                return 150;
            }

            public function prepare(string $query, ...$args): string {
                return $query;
            }

            public function get_results(string $query, string $output = 'OBJECT'): array {
                if (str_contains($query, 'ORDER BY created_at DESC')) {
                    return [
                        [
                            'helmet_id'        => 202,
                            'marketplace_id'   => 'amazon-us',
                            'referral_channel' => 'ai_assistant',
                            'utm_source'       => 'perplexity',
                            'created_at'       => '2026-09-11 10:30:00',
                        ],
                    ];
                }
                if (str_contains($query, 'GROUP BY referral_channel')) {
                    return [
                        ['referral_channel' => 'ai_assistant', 'total' => 80],
                        ['referral_channel' => 'forum', 'total' => 45],
                        ['referral_channel' => 'social', 'total' => 25],
                    ];
                }
                if (str_contains($query, 'GROUP BY utm_source')) {
                    return [
                        ['utm_source' => 'chatgpt', 'total' => 50],
                        ['utm_source' => 'reddit', 'total' => 30],
                    ];
                }
                if (str_contains($query, 'GROUP BY click_source')) {
                    return [
                        ['click_source' => 'hero_pdp_cta', 'total' => 100],
                    ];
                }
                if (str_contains($query, 'GROUP BY affiliate_network')) {
                    return [
                        ['affiliate_network' => 'amazon', 'total' => 120],
                    ];
                }
                if (str_contains($query, 'GROUP BY marketplace_id')) {
                    return [
                        ['marketplace_id' => 'amazon-us', 'total' => 100],
                    ];
                }
                if (str_contains($query, 'GROUP BY helmet_id')) {
                    return [
                        ['helmet_id' => 202, 'total' => 85],
                    ];
                }
                return [];
            }
        };

        $GLOBALS['wpdb'] = $wpdbMock;

        ob_start();
        $this->dashboard->renderPage();
        $output = ob_get_clean();

        $this->assertNotEmpty($output);
        $this->assertStringContainsString('Revenue Dashboard', $output);
        $this->assertStringContainsString('Total Clicks', $output);
        $this->assertStringContainsString('Traffic Attribution by Channel', $output);
        $this->assertStringContainsString('AI Assistants', $output);
        $this->assertStringContainsString('Motorcycle Forums &amp; Reddit', $output);
        $this->assertStringContainsString('Top Inbound UTM Campaigns & Sources', $output);
        $this->assertStringContainsString('chatgpt', $output);
        $this->assertStringContainsString('Recent Attributed Conversions', $output);
    }
}
