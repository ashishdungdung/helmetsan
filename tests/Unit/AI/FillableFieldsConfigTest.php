<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI\Tests;

use Helmetsan\Core\AI\FillableFieldsConfig;
use PHPUnit\Framework\TestCase;

final class FillableFieldsConfigTest extends TestCase
{
    public function testForPostTypeReturnsEmptyForUnknown(): void
    {
        $result = FillableFieldsConfig::forPostType('unknown');
        $this->assertSame([], $result);
    }

    public function testForHelmetReturnsExpectedKeys(): void
    {
        $result = FillableFieldsConfig::forHelmet();
        $this->assertArrayHasKey('spec_shell_material', $result);
        $this->assertArrayHasKey('head_shape', $result);
        $this->assertArrayHasKey('warranty_years', $result);
        $this->assertArrayHasKey('technical_analysis', $result);
    }

    public function testForBrandReturnsExpectedKeys(): void
    {
        $result = FillableFieldsConfig::forBrand();
        $this->assertArrayHasKey('brand_story', $result);
        $this->assertArrayHasKey('brand_founded_year', $result);
    }

    public function testGetLabelFromArrayConfig(): void
    {
        $config = ['label' => 'Shell material (e.g. Polycarbonate)', 'max_length' => 80];
        $this->assertSame('Shell material (e.g. Polycarbonate)', FillableFieldsConfig::getLabel('spec_shell_material', 'helmet', $config));
    }

    public function testGetLabelFallsBackToMetaKey(): void
    {
        $config = [];
        $this->assertSame('some_key', FillableFieldsConfig::getLabel('some_key', 'helmet', $config));
    }

    public function testGetMaxLengthReturnsNullForStringConfig(): void
    {
        $this->assertNull(FillableFieldsConfig::getMaxLength('just a string'));
    }

    public function testGetMaxLengthReturnsValue(): void
    {
        $config = ['label' => 'X', 'max_length' => 120];
        $this->assertSame(120, FillableFieldsConfig::getMaxLength($config));
    }

    public function testGetAllowedValuesFromConfig(): void
    {
        $config = ['label' => 'Head shape', 'allowed_values' => ['long-oval', 'intermediate-oval', 'round-oval']];
        $this->assertSame(['long-oval', 'intermediate-oval', 'round-oval'], FillableFieldsConfig::getAllowedValues($config));
    }

    public function testGetAllowedValuesReturnsNullForStringConfig(): void
    {
        $this->assertNull(FillableFieldsConfig::getAllowedValues('string'));
    }

    public function testWarrantyYearsHasAllowedValues(): void
    {
        $helmet = FillableFieldsConfig::forHelmet();
        $this->assertIsArray($helmet['warranty_years']);
        $this->assertArrayHasKey('allowed_values', $helmet['warranty_years']);
        $allowed = $helmet['warranty_years']['allowed_values'];
        $this->assertContains('5', $allowed);
        $this->assertContains('0', $allowed);
        $this->assertContains('10', $allowed);
    }
}
