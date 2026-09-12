<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI\Tests;

use Helmetsan\Core\AI\FillMissingService;
use Helmetsan\Core\AI\AiService;
use PHPUnit\Framework\TestCase;

final class FillMissingServiceSanitizeTest extends TestCase
{
    private FillMissingService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $aiServiceMock = $this->createMock(AiService::class);
        $this->service = new FillMissingService($aiServiceMock, null, null);
    }

    public function testAllowedValuesExactMatch(): void
    {
        $config = ['label' => 'Head shape', 'allowed_values' => ['long-oval', 'intermediate-oval', 'round-oval']];
        $this->assertSame('long-oval', $this->service->sanitizeAndValidate('head_shape', 'long-oval', $config));
        $this->assertSame('intermediate-oval', $this->service->sanitizeAndValidate('head_shape', 'INTERMEDIATE-OVAL', $config));
    }

    public function testAllowedValuesNoMatchReturnsEmpty(): void
    {
        $config = ['label' => 'Head shape', 'allowed_values' => ['long-oval', 'round-oval']];
        $this->assertSame('', $this->service->sanitizeAndValidate('head_shape', 'oval', $config));
    }

    public function testBrandFoundedYearValidRange(): void
    {
        $config = ['label' => 'Year', 'max_length' => 4];
        $this->assertSame('1959', $this->service->sanitizeAndValidate('brand_founded_year', '1959', $config));
        $this->assertSame('2020', $this->service->sanitizeAndValidate('brand_founded_year', 'Founded in 2020', $config));
    }

    public function testBrandFoundedYearOutOfRangeReturnsEmpty(): void
    {
        $config = ['label' => 'Year', 'max_length' => 4];
        $this->assertSame('', $this->service->sanitizeAndValidate('brand_founded_year', '1899', $config));
        $maxYear = (int) date('Y');
        $this->assertSame('', $this->service->sanitizeAndValidate('brand_founded_year', (string) ($maxYear + 1), $config));
    }

    public function testMaxLengthTruncation(): void
    {
        $config = ['label' => 'Short', 'max_length' => 10];
        $long = 'hello world and more';
        $result = $this->service->sanitizeAndValidate('x', $long, $config);
        $this->assertLessThanOrEqual(10, strlen($result));
    }

    public function testWarrantyYearsAllowedValues(): void
    {
        $config = ['label' => 'Warranty', 'allowed_values' => ['0', '1', '2', '3', '4', '5', '6', '7', '8', '9', '10']];
        $this->assertSame('5', $this->service->sanitizeAndValidate('warranty_years', '5', $config));
        $this->assertSame('10', $this->service->sanitizeAndValidate('warranty_years', '10', $config));
    }

    public function testStripsQuotes(): void
    {
        $config = ['label' => 'X', 'max_length' => 100];
        $this->assertSame('value', $this->service->sanitizeAndValidate('other', '"value"', $config));
    }
}
