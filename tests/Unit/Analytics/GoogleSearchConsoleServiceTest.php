<?php

declare(strict_types=1);

namespace Helmetsan\Core\Analytics\Tests;

use Helmetsan\Core\Analytics\GoogleSearchConsoleService;
use Helmetsan\Core\Support\Config;
use PHPUnit\Framework\TestCase;

final class GoogleSearchConsoleServiceTest extends TestCase
{
    public function testGetOverviewMetricsWithoutCredentialsReturnsGracefulFailure(): void
    {
        $config = new Config();
        $service = new GoogleSearchConsoleService($config);

        $res = $service->getOverviewMetrics(30, true);
        $this->assertIsArray($res);
        $this->assertArrayHasKey('clicks', $res);
        $this->assertArrayHasKey('impressions', $res);
        $this->assertArrayHasKey('ctr', $res);
        $this->assertArrayHasKey('position', $res);
    }

    public function testGetTopQueriesWithoutCredentialsReturnsEmptyArray(): void
    {
        $config = new Config();
        $service = new GoogleSearchConsoleService($config);

        $queries = $service->getTopQueries(5, 30, true);
        $this->assertIsArray($queries);
    }

    public function testGetSiteStatusReturnsArray(): void
    {
        $config = new Config();
        $service = new GoogleSearchConsoleService($config);

        $status = $service->getSiteStatus(true);
        $this->assertIsArray($status);
        $this->assertArrayHasKey('connected', $status);
        $this->assertArrayHasKey('permission', $status);
    }

    public function testGetStrikingDistanceQueriesWithoutCredentialsReturnsEmptyArray(): void
    {
        $config = new Config();
        $service = new GoogleSearchConsoleService($config);

        $queries = $service->getStrikingDistanceQueries(5, 30, true);
        $this->assertIsArray($queries);
    }

    public function testGetSitemapsListReturnsArray(): void
    {
        $config = new Config();
        $service = new GoogleSearchConsoleService($config);

        $sitemaps = $service->getSitemapsList(true);
        $this->assertIsArray($sitemaps);
        if (!empty($sitemaps)) {
            $this->assertArrayHasKey('path', $sitemaps[0]);
            $this->assertArrayHasKey('warnings', $sitemaps[0]);
            $this->assertArrayHasKey('errors', $sitemaps[0]);
        }
    }

    public function testGetSearchAppearanceReturnsArray(): void
    {
        $config = new Config();
        $service = new GoogleSearchConsoleService($config);

        $appearance = $service->getSearchAppearance(30, true);
        $this->assertIsArray($appearance);
        if (!empty($appearance)) {
            $this->assertArrayHasKey('appearance', $appearance[0]);
            $this->assertArrayHasKey('impressions', $appearance[0]);
        }
    }

    public function testGetOverviewMetricsSupportsCustomTimeframe(): void
    {
        $config = new Config();
        $service = new GoogleSearchConsoleService($config);

        $res7 = $service->getOverviewMetrics(7, true);
        $this->assertIsArray($res7);
        $this->assertArrayHasKey('clicks', $res7);
        $this->assertArrayHasKey('impressions', $res7);

        $res90 = $service->getOverviewMetrics(90, true);
        $this->assertIsArray($res90);
        $this->assertArrayHasKey('clicks', $res90);
    }
}
