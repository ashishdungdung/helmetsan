<?php

declare(strict_types=1);

namespace Helmetsan\Core\Analytics\Tests;

use Helmetsan\Core\Analytics\GoogleAnalyticsService;
use Helmetsan\Core\Support\Config;
use PHPUnit\Framework\TestCase;

final class GoogleAnalyticsServiceTest extends TestCase
{
    private static string $testKeyPath;

    public static function setUpBeforeClass(): void
    {
        parent::setUpBeforeClass();
        self::$testKeyPath = __DIR__ . '/test-google-key.json';
        if (! defined('HELMETSAN_GA_KEY_PATH')) {
            define('HELMETSAN_GA_KEY_PATH', self::$testKeyPath);
        }
    }

    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['wp_options'] = [];
        if (file_exists(self::$testKeyPath)) {
            unlink(self::$testKeyPath);
        }
    }

    protected function tearDown(): void
    {
        if (file_exists(self::$testKeyPath)) {
            unlink(self::$testKeyPath);
        }
        parent::tearDown();
    }

    public function testDetectTrafficAnomaliesWithMissingConfig(): void
    {
        $config = new Config();
        $service = new GoogleAnalyticsService($config);

        $res = $service->detectTrafficAnomalies();
        $this->assertFalse($res['ok']);
        $this->assertSame('Google Analytics Property ID or Service Account Key is missing.', $res['message']);
    }

    public function testDetectTrafficAnomaliesWithInvalidKeyJson(): void
    {
        // Write invalid JSON key to our defined path constant to force failure
        file_put_contents(self::$testKeyPath, 'not-a-json-string');

        $GLOBALS['wp_options']['helmetsan_analytics'] = [
            'ga4_property_id' => '123456789',
        ];

        $config = new Config();
        $service = new GoogleAnalyticsService($config);

        $res = $service->detectTrafficAnomalies();
        $this->assertFalse($res['ok']);
        $this->assertSame('Invalid Service Account JSON key format.', $res['message']);
    }

    public function testGetAcquisitionChannelsWithoutCredentialsReturnsEmptyArray(): void
    {
        $config = new Config();
        $service = new GoogleAnalyticsService($config);

        $channels = $service->getAcquisitionChannels('30daysAgo', true);
        $this->assertIsArray($channels);
        $this->assertEmpty($channels);
    }

    public function testGetAiReferralsWithoutCredentialsReturnsEmptyStructure(): void
    {
        $config = new Config();
        $service = new GoogleAnalyticsService($config);

        $ai = $service->getAiReferrals('30daysAgo', true);
        $this->assertIsArray($ai);
        $this->assertArrayHasKey('total_sessions', $ai);
        $this->assertArrayHasKey('platforms', $ai);
        $this->assertArrayHasKey('items', $ai);
        $this->assertSame(0, $ai['total_sessions']);
    }

    public function testGetPropertyIdFallsBackGracefully(): void
    {
        $config = new Config();
        $service = new GoogleAnalyticsService($config);

        // With no option set
        $this->assertSame('', $service->getPropertyId());

        // With wp_options set
        $GLOBALS['wp_options']['helmetsan_analytics'] = [
            'ga4_property_id' => '987654321',
        ];
        $this->assertSame('987654321', $service->getPropertyId());
    }

    public function testGetRealtimeActiveUsersWithoutCredentialsReturnsGracefulStructure(): void
    {
        $config = new Config();
        $service = new GoogleAnalyticsService($config);

        $res = $service->getRealtimeActiveUsers(true);
        $this->assertIsArray($res);
        $this->assertArrayHasKey('ok', $res);
        $this->assertArrayHasKey('active_users', $res);
        $this->assertArrayHasKey('countries', $res);
        $this->assertArrayHasKey('timestamp', $res);
        $this->assertSame(0, $res['active_users']);
    }

    public function testGetDeviceBreakdownWithoutCredentialsReturnsGracefulStructure(): void
    {
        $config = new Config();
        $service = new GoogleAnalyticsService($config);

        $res = $service->getDeviceBreakdown('30daysAgo', true);
        $this->assertIsArray($res);
        $this->assertArrayHasKey('ok', $res);
        $this->assertArrayHasKey('devices', $res);
        $this->assertArrayHasKey('total_sessions', $res);
        $this->assertSame(0, $res['total_sessions']);
    }

    public function testGetTrafficAnomaliesSummaryReturnsStructure(): void
    {
        $config = new Config();
        $service = new GoogleAnalyticsService($config);

        $res = $service->getTrafficAnomaliesSummary(true);
        $this->assertIsArray($res);
        $this->assertArrayHasKey('ok', $res);
        $this->assertArrayHasKey('count', $res);
        $this->assertArrayHasKey('anomalies', $res);
    }
}
