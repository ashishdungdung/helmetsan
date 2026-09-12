<?php

declare(strict_types=1);

namespace Helmetsan\Tests\Unit\Geo;

use Helmetsan\Core\Geo\ComplianceService;
use PHPUnit\Framework\TestCase;

class ComplianceServiceTest extends TestCase
{
    private ComplianceService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new ComplianceService();
    }

    public function testRoadLegalityForUsMarketWithDotCert(): void
    {
        $status = $this->service->getRoadLegalityStatus(['DOT', 'ECE 22.06'], 'US');

        $this->assertTrue($status['is_legal']);
        $this->assertSame('road_legal', $status['status']);
        $this->assertStringContainsString('DOT', $status['title']);
    }

    public function testRoadLegalityForUsMarketWithoutDotCert(): void
    {
        $status = $this->service->getRoadLegalityStatus(['ECE 22.06'], 'US');

        $this->assertFalse($status['is_legal']);
        $this->assertSame('warning', $status['status']);
        $this->assertStringContainsString('FMVSS 218', $status['title']);
        $this->assertStringContainsString('DOT', $status['regional_note']);
    }

    public function testRoadLegalityForEuMarketWithEce2206(): void
    {
        $status = $this->service->getRoadLegalityStatus(['ECE 22.06', 'DOT'], 'DE');

        $this->assertTrue($status['is_legal']);
        $this->assertSame('road_legal', $status['status']);
        $this->assertStringContainsString('ECE 22.06', $status['title']);
    }

    public function testRoadLegalityForIndiaWithIsi(): void
    {
        $status = $this->service->getRoadLegalityStatus(['ISI', 'DOT'], 'IN');

        $this->assertTrue($status['is_legal']);
        $this->assertSame('road_legal', $status['status']);
        $this->assertStringContainsString('ISI', $status['title']);
    }

    public function testRoadLegalityForIndiaWithoutIsi(): void
    {
        $status = $this->service->getRoadLegalityStatus(['ECE 22.06', 'DOT'], 'IN');

        $this->assertFalse($status['is_legal']);
        $this->assertSame('warning', $status['status']);
        $this->assertStringContainsString('Import Advisory: Missing ISI / BIS', $status['title']);
        $this->assertStringContainsString('Section 129', $status['regional_note']);
    }

    public function testTrackOnlyOrFimCertifiedStatus(): void
    {
        $status = $this->service->getRoadLegalityStatus(['FIM FRHPhe-01'], 'US');

        $this->assertSame('track_only', $status['status']);
        $this->assertStringContainsString('FIM Competition / Track Certified', $status['title']);
    }

    public function testImportDutyEstimateForUsUnderDeMinimis(): void
    {
        // Price under $800 USD threshold
        $estimate = $this->service->getImportDutyEstimate(500.0, 'USD', 'US');

        $this->assertTrue($estimate['is_duty_exempt']);
        $this->assertEquals(0.0, $estimate['estimated_duty']);
        $this->assertStringContainsString('Section 321 de minimis', $estimate['summary']);
    }

    public function testImportDutyEstimateForEuWithVat(): void
    {
        // €300 price in Germany: 19% VAT + 2.7% duty
        $estimate = $this->service->getImportDutyEstimate(300.0, 'EUR', 'DE');

        $this->assertFalse($estimate['is_duty_exempt']);
        $this->assertGreaterThan(0.0, $estimate['estimated_vat']);
        $this->assertGreaterThan(0.0, $estimate['estimated_duty']);
        $this->assertSame(19.0, $estimate['vat_rate_pct']);
        $this->assertSame(2.7, $estimate['duty_rate_pct']);
    }

    public function testImportDutyEstimateForIndiaWithCustomsAndIgst(): void
    {
        // Price in INR
        $estimate = $this->service->getImportDutyEstimate(25000.0, 'INR', 'IN');

        $this->assertFalse($estimate['is_duty_exempt']);
        $this->assertSame(10.0, $estimate['duty_rate_pct']);
        $this->assertSame(18.0, $estimate['vat_rate_pct']);
        $this->assertGreaterThan(0.0, $estimate['estimated_duty']);
        $this->assertGreaterThan(0.0, $estimate['estimated_vat']);
    }

    public function testRoadLegalityForFranceMarketWithEce(): void
    {
        $status = $this->service->getRoadLegalityStatus(['ECE 22.06'], 'FR');

        $this->assertTrue($status['is_legal']);
        $this->assertSame('road_legal', $status['status']);
        $this->assertStringContainsString('ECE 22.06', $status['title']);
        $this->assertStringContainsString('reflective stickers', $status['regional_note']);
    }

    public function testRoadLegalityForFranceMarketWithoutEce(): void
    {
        $status = $this->service->getRoadLegalityStatus(['DOT'], 'FR');

        $this->assertFalse($status['is_legal']);
        $this->assertSame('warning', $status['status']);
        $this->assertStringContainsString('reflective stickers', $status['regional_note']);
        $this->assertStringContainsString('penalty', $status['regional_note']);
    }

    public function testImportDutyEstimateForFranceWithVat(): void
    {
        // €250 price in France: 20% French TVA + 2.7% duty
        $estimate = $this->service->getImportDutyEstimate(250.0, 'EUR', 'FR');

        $this->assertFalse($estimate['is_duty_exempt']);
        $this->assertSame(20.0, $estimate['vat_rate_pct']);
        $this->assertSame(2.7, $estimate['duty_rate_pct']);
        $this->assertGreaterThan(0.0, $estimate['estimated_vat']);
        $this->assertGreaterThan(0.0, $estimate['estimated_duty']);
        $this->assertStringContainsString('20% French TVA', $estimate['summary']);
    }

    public function testGetMarketRequirements(): void
    {
        $usReqs = $this->service->getMarketRequirements('US');
        $this->assertContains('DOT', $usReqs['accepted_standards']);
        $this->assertSame(800.0, $usReqs['de_minimis_threshold']);

        $unknownReqs = $this->service->getMarketRequirements('ZZ');
        $this->assertNotEmpty($unknownReqs['accepted_standards']);
    }
}

