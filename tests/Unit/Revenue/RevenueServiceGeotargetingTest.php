<?php

declare(strict_types=1);

namespace Helmetsan\Core\Tests\Revenue;

use Helmetsan\Core\Geo\GeoService;
use Helmetsan\Core\Revenue\RevenueService;
use Helmetsan\Core\Support\Config;
use PHPUnit\Framework\TestCase;

final class RevenueServiceGeotargetingTest extends TestCase
{
    private RevenueService $service;
    private Config $config;
    private GeoService $geo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->config = new Config();
        $this->geo = new GeoService();
        $this->service = new RevenueService($this->config, null, null, $this->geo);
        $GLOBALS['wp_post_meta'] = [];
        $GLOBALS['wp_post_fields'] = [];
    }

    public function testResolveGeotargetedUrlForIndia(): void
    {
        $originalUrl = 'https://www.amazon.com/dp/B07QKZV8YJ?tag=vtete-20';
        $resolved = $this->service->resolveGeotargetedUrl($originalUrl, 'IN');

        $this->assertStringContainsString('amazon.in', $resolved);
        $this->assertStringContainsString('tag=virginiatete-21', $resolved);
        $this->assertStringContainsString('/dp/B07QKZV8YJ', $resolved);
    }

    public function testResolveGeotargetedUrlForUnitedStates(): void
    {
        $originalUrl = 'https://www.amazon.in/dp/B07QKZV8YJ?tag=virginiatete-21';
        $resolved = $this->service->resolveGeotargetedUrl($originalUrl, 'US');

        $this->assertStringContainsString('amazon.com', $resolved);
        $this->assertStringContainsString('tag=vtete-20', $resolved);
        $this->assertStringContainsString('/dp/B07QKZV8YJ', $resolved);
    }

    public function testResolveGeotargetedUrlForUnitedKingdom(): void
    {
        $originalUrl = 'https://www.amazon.com/dp/B07QKZV8YJ?tag=vtete-20';
        $resolved = $this->service->resolveGeotargetedUrl($originalUrl, 'GB');

        $this->assertStringContainsString('amazon.co.uk', $resolved);
        $this->assertStringContainsString('tag=vtete-21', $resolved);
    }

    public function testResolveGeotargetedUrlForGermany(): void
    {
        $originalUrl = 'https://www.amazon.com/dp/B07QKZV8YJ?tag=vtete-20';
        $resolved = $this->service->resolveGeotargetedUrl($originalUrl, 'DE');

        $this->assertStringContainsString('amazon.de', $resolved);
        $this->assertStringContainsString('tag=vtete-20', $resolved);
    }

    public function testResolveGeotargetedUrlForFrance(): void
    {
        $originalUrl = 'https://www.amazon.com/dp/B07QKZV8YJ?tag=vtete-20';
        $resolved = $this->service->resolveGeotargetedUrl($originalUrl, 'FR');

        $this->assertStringContainsString('amazon.fr', $resolved);
        $this->assertStringContainsString('tag=vtete-20', $resolved);
    }

    public function testResolveGeotargetedUrlForJapan(): void
    {
        $originalUrl = 'https://www.amazon.com/dp/B07QKZV8YJ?tag=vtete-20';
        $resolved = $this->service->resolveGeotargetedUrl($originalUrl, 'JP');

        $this->assertStringContainsString('amazon.co.jp', $resolved);
        $this->assertStringContainsString('tag=vtete-22', $resolved);
    }

    public function testResolveGeotargetedUrlForGoRedirectUrl(): void
    {
        $goUrl = 'https://helmetsan.com/go/shoei-rf-1400/?source=pdp';
        $resolvedIn = $this->service->resolveGeotargetedUrl($goUrl, 'IN');
        $this->assertStringContainsString('marketplace=amazon-in', $resolvedIn);

        $resolvedDe = $this->service->resolveGeotargetedUrl($goUrl, 'DE');
        $this->assertStringContainsString('marketplace=amazon-de', $resolvedDe);
    }

    public function testResolveGeotargetedUrlForIrelandAndTurkey(): void
    {
        $originalUrl = 'https://www.amazon.com/dp/B07QKZV8YJ?tag=vtete-20';
        $resolvedIe = $this->service->resolveGeotargetedUrl($originalUrl, 'IE');
        $this->assertStringContainsString('amazon.co.uk', $resolvedIe);
        $this->assertStringContainsString('tag=vtete-21', $resolvedIe);

        $resolvedTr = $this->service->resolveGeotargetedUrl($originalUrl, 'TR');
        $this->assertStringContainsString('amazon.com.tr', $resolvedTr);
        $this->assertStringContainsString('tag=vtete-20', $resolvedTr);
    }

    public function testResolveGeotargetedUrlForCanadaAndAustralia(): void
    {
        $originalUrl = 'https://www.amazon.com/dp/B07QKZV8YJ?tag=vtete-20';
        $resolvedCa = $this->service->resolveGeotargetedUrl($originalUrl, 'CA');
        $this->assertStringContainsString('amazon.ca', $resolvedCa);
        $this->assertStringContainsString('tag=vtete-20', $resolvedCa);

        $resolvedAu = $this->service->resolveGeotargetedUrl($originalUrl, 'AU');
        $this->assertStringContainsString('amazon.com.au', $resolvedAu);
        $this->assertStringContainsString('tag=vtete-20', $resolvedAu);

        $resolvedAe = $this->service->resolveGeotargetedUrl($originalUrl, 'AE');
        $this->assertStringContainsString('amazon.ae', $resolvedAe);
        $this->assertStringContainsString('tag=vtete08-21', $resolvedAe);
    }

    public function testNonAmazonUrlRemainsUnchanged(): void
    {
        $url = 'https://www.revzilla.com/motorcycle/shoei-rf-1400-helmet';
        $resolved = $this->service->resolveGeotargetedUrl($url, 'IN');
        $this->assertSame($url, $resolved);
    }
}
