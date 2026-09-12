<?php

declare(strict_types=1);

namespace Helmetsan\Tests\Unit\Geo;

use PHPUnit\Framework\TestCase;

class RoadLegalityResolutionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        if (!function_exists('helmetsan_resolve_road_legality')) {
            require_once dirname(__DIR__, 3) . '/helmetsan-theme/inc/template-tags.php';
        }
    }

    public function testFranceMarketWithEce2206IncludesReflectiveStickersMandate(): void
    {
        $result = helmetsan_resolve_road_legality(['ECE 22.06'], 'FR');

        $this->assertSame('legal', $result['status']);
        $this->assertSame('🇫🇷', $result['flag']);
        $this->assertSame('FR', $result['country']);
        $this->assertSame('ECE 22.06', $result['standard']);
        $this->assertStringContainsString('Street Legal in France', $result['headline']);
        $this->assertStringContainsString('Art. R431-1', $result['subtitle']);
        $this->assertStringContainsString('4 retro-reflective stickers', $result['subtitle']);
        $this->assertStringContainsString('3-point license penalty', $result['subtitle']);
    }

    public function testFranceMarketWithEce2205IncludesReflectiveStickersMandate(): void
    {
        $result = helmetsan_resolve_road_legality(['ECE 22.05'], 'FR');

        $this->assertSame('legal', $result['status']);
        $this->assertSame('🇫🇷', $result['flag']);
        $this->assertSame('FR', $result['country']);
        $this->assertSame('ECE 22.05', $result['standard']);
        $this->assertStringContainsString('ECE 22.05 Road Legal in France', $result['headline']);
        $this->assertStringContainsString('4 retro-reflective stickers', $result['subtitle']);
    }

    public function testFranceMarketWithoutEceReturnsAdvisory(): void
    {
        $result = helmetsan_resolve_road_legality(['DOT'], 'FR');

        $this->assertSame('advisory', $result['status']);
        $this->assertSame('🇫🇷', $result['flag']);
        $this->assertSame('FR', $result['country']);
        $this->assertSame('Non-ECE', $result['standard']);
        $this->assertStringContainsString('Not Street Legal in France', $result['headline']);
    }

    public function testIndiaMarketWithIsiIsLegal(): void
    {
        $result = helmetsan_resolve_road_legality(['IS 4151'], 'IN');

        $this->assertSame('legal', $result['status']);
        $this->assertSame('🇮🇳', $result['flag']);
        $this->assertSame('IS 4151', $result['standard']);
    }

    public function testIndiaMarketWithDomesticBrandWithoutExplicitIsiIsLegal(): void
    {
        $result = helmetsan_resolve_road_legality([], 'IN', 'Vega');

        $this->assertSame('legal', $result['status']);
        $this->assertSame('🇮🇳', $result['flag']);
    }

    public function testUsMarketWithDotIsLegal(): void
    {
        $result = helmetsan_resolve_road_legality(['DOT'], 'US');

        $this->assertSame('legal', $result['status']);
        $this->assertSame('🇺🇸', $result['flag']);
        $this->assertSame('DOT', $result['standard']);
    }

    public function testGermanyMarketUsesEuFlagAndEce(): void
    {
        $result = helmetsan_resolve_road_legality(['ECE 22.06'], 'DE');

        $this->assertSame('legal', $result['status']);
        $this->assertSame('🇩🇪', $result['flag']);
        $this->assertSame('ECE 22.06', $result['standard']);
        $this->assertStringContainsString('Latest EU Safety Standard', $result['headline']);
    }

    public function testCanadaMarketWithDotOrEceIsLegal(): void
    {
        $result = helmetsan_resolve_road_legality(['DOT'], 'CA');

        $this->assertSame('legal', $result['status']);
        $this->assertSame('🇨🇦', $result['flag']);
        $this->assertStringContainsString('Street Legal in Canada (CMVSS / DOT / ECE)', $result['headline']);
    }

    public function testHelmetsanGetSupportedCountriesReturnsArray(): void
    {
        $countries = helmetsan_get_supported_countries();
        $this->assertIsArray($countries);
        $this->assertArrayHasKey('IN', $countries);
        $this->assertArrayHasKey('US', $countries);
        $this->assertArrayHasKey('CA', $countries);
    }
}
