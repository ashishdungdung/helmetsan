<?php

declare(strict_types=1);

namespace Tests\Unit\Ingestion;

use Helmetsan\Core\Ingestion\IngestionService;
use PHPUnit\Framework\TestCase;
use ReflectionClass;

final class DeterministicIngestionSortTest extends TestCase
{
    private IngestionService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $ref = new ReflectionClass(IngestionService::class);
        /** @var IngestionService $instance */
        $instance = $ref->newInstanceWithoutConstructor();
        $this->service = $instance;
    }

    public function testSortsBaseMotorcyclesBeforeVariantTrims(): void
    {
        $input = [
            '/data/motorcycles/aprilia_rs_457_supersport_enduro_spec.json',
            '/data/motorcycles/aprilia_rs_457_supersport_dark_edition.json',
            '/data/motorcycles/aprilia_rs_457_supersport.json',
            '/data/motorcycles/bajaj_pulsar_ns200.json',
            '/data/motorcycles/bajaj_pulsar_ns200_chrome_edition.json',
        ];

        $sorted = $this->service->sortFilesDeterministically($input);

        $expected = [
            '/data/motorcycles/aprilia_rs_457_supersport.json',
            '/data/motorcycles/bajaj_pulsar_ns200.json',
            '/data/motorcycles/aprilia_rs_457_supersport_dark_edition.json',
            '/data/motorcycles/aprilia_rs_457_supersport_enduro_spec.json',
            '/data/motorcycles/bajaj_pulsar_ns200_chrome_edition.json',
        ];

        $this->assertSame($expected, $sorted);
    }

    public function testSortsHelmetParentsBeforeVariantsWithTempFiles(): void
    {
        $tmpDir = sys_get_temp_dir() . '/hs_ingest_test_' . uniqid();
        mkdir($tmpDir);

        $parentFile = $tmpDir . '/shoei_rf1400.json';
        $childFile  = $tmpDir . '/shoei_rf1400_matte_black.json';

        file_put_contents($parentFile, json_encode(['id' => 'shoei_rf1400', 'parent_id' => '']));
        file_put_contents($childFile, json_encode(['id' => 'shoei_rf1400_matte_black', 'parent_id' => 'shoei_rf1400']));

        try {
            $sorted = $this->service->sortFilesDeterministically([$childFile, $parentFile]);
            $this->assertSame([$parentFile, $childFile], $sorted);
        } finally {
            @unlink($parentFile);
            @unlink($childFile);
            @rmdir($tmpDir);
        }
    }
}
