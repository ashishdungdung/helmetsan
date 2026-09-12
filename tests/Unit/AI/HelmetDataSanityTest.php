<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use PHPUnit\Framework\TestCase;

final class HelmetDataSanityTest extends TestCase
{
    public function testCatalogDataCompliesWithInMemorySanityLinter(): void
    {
        $webDir = dirname(__DIR__, 3);
        $script = $webDir . '/scripts/lint_helmet_data.py';

        $this->assertFileExists($script, 'In-memory linter script must exist.');

        $output = [];
        $exitCode = 0;
        exec(sprintf('python3 %s 2>&1', escapeshellarg($script)), $output, $exitCode);

        $outputText = implode("\n", $output);
        $this->assertSame(0, $exitCode, 'Linter failed with errors: ' . $outputText);
        $this->assertStringContainsString('PASSED: 100% of catalog records comply', $outputText);
    }
}
