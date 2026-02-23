<?php

declare(strict_types=1);

namespace Helmetsan\Core\AI\Tests;

use Helmetsan\Core\AI\ContextBuilder;
use PHPUnit\Framework\TestCase;

final class ContextBuilderTest extends TestCase
{
    public function testForFillFieldIncludesEntityTypeAndContext(): void
    {
        $prompt = ContextBuilder::forFillField('helmet', 'spec_shell_material', 'Shell material', ['title' => 'Test Helmet']);
        $this->assertStringContainsString('helmet', $prompt);
        $this->assertStringContainsString('Shell material', $prompt);
        $this->assertStringContainsString('Test Helmet', $prompt);
    }

    public function testForFillFieldWithMaxLengthIncludesCharacterHint(): void
    {
        $prompt = ContextBuilder::forFillField('helmet', 'x', 'Label', [], 80, null);
        $this->assertStringContainsString('80', $prompt);
    }

    public function testForFillFieldWithAllowedValuesInstructsExactChoice(): void
    {
        $allowed = ['long-oval', 'intermediate-oval', 'round-oval'];
        $prompt = ContextBuilder::forFillField('helmet', 'head_shape', 'Head shape', [], null, $allowed);
        $this->assertStringContainsString('long-oval', $prompt);
        $this->assertStringContainsString('intermediate-oval', $prompt);
        $this->assertStringContainsString('round-oval', $prompt);
        $this->assertStringContainsString('exactly one', $prompt);
    }

    public function testForFillFieldRetryIncludesPreviousInvalidAndAllowed(): void
    {
        $allowed = ['long-oval', 'round-oval'];
        $prompt = ContextBuilder::forFillFieldRetry('helmet', 'head_shape', 'Head shape', ['title' => 'X'], $allowed, 'oval');
        $this->assertStringContainsString('oval', $prompt);
        $this->assertStringContainsString('previous reply was invalid', strtolower($prompt));
        $this->assertStringContainsString('long-oval', $prompt);
    }
}
