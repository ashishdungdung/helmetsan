<?php

declare(strict_types=1);

namespace Tests\Unit\Core;

use Helmetsan\Core\Core\Plugin;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use WP_Query;

final class HelmetQueryParentGuardTest extends TestCase
{
    private Plugin $plugin;

    protected function setUp(): void
    {
        parent::setUp();
        $ref = new ReflectionClass(Plugin::class);
        /** @var Plugin $instance */
        $instance = $ref->newInstanceWithoutConstructor();
        $this->plugin = $instance;
    }

    public function testEnforcesPostParentZeroOnHelmetQuery(): void
    {
        $query = new WP_Query(['post_type' => 'helmet']);
        $this->assertSame('', $query->get('post_parent'));

        $this->plugin->enforceHelmetQueryParentGuard($query);

        $this->assertSame(0, $query->get('post_parent'));
    }

    public function testEnforcesPostParentZeroWhenHelmetIsInArray(): void
    {
        $query = new WP_Query(['post_type' => ['helmet', 'accessory']]);
        $this->plugin->enforceHelmetQueryParentGuard($query);

        $this->assertSame(0, $query->get('post_parent'));
    }

    public function testPreservesExplicitPostParent(): void
    {
        $query = new WP_Query([
            'post_type'   => 'helmet',
            'post_parent' => 1234,
        ]);
        $this->plugin->enforceHelmetQueryParentGuard($query);

        $this->assertSame(1234, $query->get('post_parent'));
    }

    public function testPreservesExplicitZeroPostParent(): void
    {
        $query = new WP_Query([
            'post_type'   => 'helmet',
            'post_parent' => 0,
        ]);
        $this->plugin->enforceHelmetQueryParentGuard($query);

        $this->assertSame(0, $query->get('post_parent'));
    }

    public function testPreservesPostParentInArray(): void
    {
        $query = new WP_Query([
            'post_type'      => 'helmet',
            'post_parent__in' => [10, 20, 30],
        ]);
        $this->plugin->enforceHelmetQueryParentGuard($query);

        $this->assertSame('', $query->get('post_parent'));
    }

    public function testIgnoresNonHelmetPostTypes(): void
    {
        $query = new WP_Query(['post_type' => 'post']);
        $this->plugin->enforceHelmetQueryParentGuard($query);

        $this->assertSame('', $query->get('post_parent'));
    }
}
