<?php

declare(strict_types=1);

namespace Tests\Unit\Cache;

use Helmetsan\Core\Cache\ObjectCacheService;
use PHPUnit\Framework\TestCase;

final class ObjectCacheServiceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $GLOBALS['wp_object_cache'] = [];
        $GLOBALS['wp_transients'] = [];
        $GLOBALS['wp_options'] = [];
        ObjectCacheService::resetRuntimeState();
    }

    public function testRememberCachesValueOnMiss(): void
    {
        $counter = 0;
        $compute = function () use (&$counter) {
            $counter++;
            return 'computed_result';
        };

        // First call: computes value
        $val1 = ObjectCacheService::remember('test_key', ObjectCacheService::GROUP_SEARCH, $compute, 3600);
        $this->assertSame('computed_result', $val1);
        $this->assertSame(1, $counter);

        // Second call: retrieves cached value without recomputing
        $val2 = ObjectCacheService::remember('test_key', ObjectCacheService::GROUP_SEARCH, $compute, 3600);
        $this->assertSame('computed_result', $val2);
        $this->assertSame(1, $counter);
    }

    public function testSetAndGetDirectly(): void
    {
        $set = ObjectCacheService::set('my_key', ['foo' => 'bar'], ObjectCacheService::GROUP_SCHEMA);
        $this->assertTrue($set);

        $get = ObjectCacheService::get('my_key', ObjectCacheService::GROUP_SCHEMA);
        $this->assertSame(['foo' => 'bar'], $get);
    }

    public function testInvalidateGroupBumpsVersionAndInvalidates(): void
    {
        ObjectCacheService::set('item1', 'alpha', ObjectCacheService::GROUP_SEARCH);
        $this->assertSame('alpha', ObjectCacheService::get('item1', ObjectCacheService::GROUP_SEARCH));

        // Invalidate the search group
        ObjectCacheService::invalidateGroup(ObjectCacheService::GROUP_SEARCH);

        // Item should now be a cache miss
        $this->assertFalse(ObjectCacheService::get('item1', ObjectCacheService::GROUP_SEARCH));
    }

    public function testTransientFallbackWhenExternalCacheNotPresent(): void
    {
        $GLOBALS['wp_using_ext_cache'] = false;

        ObjectCacheService::set('fallback_item', 'database_cached', ObjectCacheService::GROUP_TAXONOMY);
        $cached = ObjectCacheService::get('fallback_item', ObjectCacheService::GROUP_TAXONOMY);

        $this->assertSame('database_cached', $cached);

        // Reset
        $GLOBALS['wp_using_ext_cache'] = true;
    }
}
