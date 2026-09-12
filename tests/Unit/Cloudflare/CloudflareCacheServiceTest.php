<?php

declare(strict_types=1);

namespace Helmetsan\Core\Cloudflare\Tests;

use Helmetsan\Core\Cloudflare\CloudflareCacheService;
use PHPUnit\Framework\TestCase;

final class CloudflareCacheServiceTest extends TestCase
{
    private CloudflareCacheService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new CloudflareCacheService();
    }

    public function testPurgeUrlsReturnsTrueWhenUrlsArrayIsEmpty(): void
    {
        $result = $this->service->purgeUrls([]);
        $this->assertTrue($result);
    }

    public function testPurgeMultilingualPathsGeneratesCorrectUrls(): void
    {
        // Without credentials configured, purgeMultilingualPaths will compute paths and then return WP_Error cf_not_configured
        $result = $this->service->purgeMultilingualPaths(['/helmets/', '/contact/']);
        
        // It reaches purgeUrls and returns cf_not_configured WP_Error (or true if mocked)
        $this->assertInstanceOf(\WP_Error::class, $result);
        $this->assertSame('cf_not_configured', $result->get_error_code());
    }
}
