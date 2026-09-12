<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use PHPUnit\Framework\TestCase;

final class HelmetDataQualityTest extends TestCase
{
    private string $dataDir;

    protected function setUp(): void
    {
        $this->dataDir = dirname(__DIR__, 3) . '/data/helmets';
    }

    public function testHelmetDataFilesExist(): void
    {
        $this->assertDirectoryExists($this->dataDir);
        $files = glob($this->dataDir . '/*.json');
        $this->assertNotEmpty($files);
        $this->assertGreaterThanOrEqual(2000, count($files));
    }

    public function testCatalogContentDiversityAndPlausibilitySample(): void
    {
        $files = glob($this->dataDir . '/*.json');
        $this->assertNotEmpty($files);

        $sample = array_slice($files, 0, 100);
        $uniqueCons = [];
        $uniquePros = [];
        $uniqueTakeaways = [];

        foreach ($sample as $file) {
            $raw = file_get_contents($file);
            $this->assertIsString($raw);
            $data = json_decode($raw, true);
            $this->assertIsArray($data);

            $hName = $data['name'] ?? $data['model'] ?? 'Helmet';

            // Specs checks
            $specs = $data['specs'] ?? [];
            if (isset($specs['noise_db']) && $specs['noise_db'] !== null) {
                $noise = (float) $specs['noise_db'];
                $this->assertGreaterThanOrEqual(84.0, $noise, "Helmet {$hName} has impossibly quiet noise level ($noise dB)");
                $this->assertLessThanOrEqual(102.0, $noise, "Helmet {$hName} has unrealistically loud noise level ($noise dB)");
            }

            // Pros and Cons checks
            $prosAndCons = $data['pros_and_cons'] ?? [];
            $this->assertArrayHasKey('pros', $prosAndCons);
            $this->assertArrayHasKey('cons', $prosAndCons);
            $this->assertNotEmpty($prosAndCons['pros'], "Helmet {$hName} must have pros");
            $this->assertNotEmpty($prosAndCons['cons'], "Helmet {$hName} must have cons");

            foreach ($prosAndCons['pros'] as $p) {
                $uniquePros[$p] = true;
            }
            foreach ($prosAndCons['cons'] as $c) {
                $uniqueCons[$c] = true;
            }

            // Rider takeaway
            $takeaway = $data['rider_takeaway'] ?? '';
            $this->assertNotEmpty($takeaway, "Helmet {$hName} must have a rider takeaway");
            $uniqueTakeaways[$takeaway] = true;
        }

        // Ensure we don't have uniform 1-2 boilerplate cons/pros across 100 helmets
        $this->assertGreaterThan(10, count($uniqueCons), 'Catalog must have diverse, spec-driven cons');
        $this->assertGreaterThan(10, count($uniquePros), 'Catalog must have diverse, spec-driven pros');
        $this->assertGreaterThan(10, count($uniqueTakeaways), 'Catalog must have diverse rider takeaways');
    }
}
