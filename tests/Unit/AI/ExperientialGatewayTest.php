<?php

declare(strict_types=1);

namespace Tests\Unit\AI;

use Helmetsan\Core\AI\Providers\OpenAIProvider;
use Helmetsan\Core\AI\Providers\ExperientialProvider;
use Helmetsan\Core\AI\ProviderRegistry;
use Helmetsan\Core\Support\Config;
use PHPUnit\Framework\TestCase;

final class ExperientialGatewayTest extends TestCase
{
    private ?string $originalExplabsKey = null;

    protected function setUp(): void
    {
        parent::setUp();
        $this->originalExplabsKey = getenv('EXPLABS_API_KEY') ?: null;
    }

    protected function tearDown(): void
    {
        if ($this->originalExplabsKey !== null) {
            putenv("EXPLABS_API_KEY={$this->originalExplabsKey}");
        } else {
            putenv('EXPLABS_API_KEY');
        }
        parent::tearDown();
    }

    public function testStandardOpenAiProviderDefaults(): void
    {
        $provider = new OpenAIProvider('sk-test123', 'gpt-4o-mini');
        $this->assertSame('openai', $provider->getId());
        $this->assertSame('gpt-4o-mini', $provider->getModel());
        $this->assertSame('https://api.openai.com/v1', $provider->getBaseUrl());
        $this->assertSame('OpenAI (gpt-4o-mini)', $provider->getLabel());
        $this->assertTrue($provider->isConfigured());

        $req = $provider->prepareRequest('Hello');
        $this->assertNotNull($req);
        $this->assertSame('https://api.openai.com/v1/chat/completions', $req['url']);
        $this->assertSame('Bearer sk-test123', $req['headers']['Authorization']);

        // Assert public getApiKey does not exist (least privilege encapsulation)
        $this->assertFalse(method_exists($provider, 'getApiKey'));
    }

    public function testExperientialProviderThrowsWhenExplabsKeyMissing(): void
    {
        putenv('EXPLABS_API_KEY'); // Unset
        unset($_ENV['EXPLABS_API_KEY']);

        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('EXPLABS_API_KEY environment variable is not set. Please create one under Settings -> API Keys and export it.');

        new ExperientialProvider('', 'gpt-5.6-luna');
    }

    public function testSsrfProtectionRejectsInsecureOrUntrustedUrls(): void
    {
        putenv('EXPLABS_API_KEY=xpl_test_123');

        // Insecure scheme
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Insecure gateway scheme 'http'");
        new ExperientialProvider('', 'gpt-5.6-luna', 'http://api.experientiallabs.ai/v1');
    }

    public function testSsrfProtectionRejectsUntrustedHosts(): void
    {
        putenv('EXPLABS_API_KEY=xpl_test_123');

        // Untrusted host
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage("Untrusted gateway host 'attacker.example.com'");
        new ExperientialProvider('', 'gpt-5.6-luna', 'https://attacker.example.com/v1');
    }

    public function testExperientialProviderDynamicModelsAndTokenExclusivity(): void
    {
        putenv('EXPLABS_API_KEY=xpl_test_secret_key_123');
        $_ENV['EXPLABS_API_KEY'] = 'xpl_test_secret_key_123';

        $models = ['gpt-5.6-luna', 'qwen3.8-27b', 'deepseek-v4-flash', 'claude-sonnet-4.5'];
        foreach ($models as $m) {
            $provider = new ExperientialProvider('', $m);
            $this->assertSame('experiential', $provider->getId());
            $this->assertSame($m, $provider->getModel());
            $this->assertSame('https://api.experientiallabs.ai/v1', $provider->getBaseUrl());
            $this->assertSame("Experiential Labs ({$m})", $provider->getLabel());
            $this->assertTrue($provider->isConfigured());

            // Assert getApiKey is encapsulated
            $this->assertFalse(method_exists($provider, 'getApiKey'));

            // Test token exclusivity: max_completion_tokens replaces max_tokens
            $req = $provider->prepareRequest('Prompt', [
                'stream' => true,
                'max_completion_tokens' => 1500,
            ]);
            $this->assertNotNull($req);
            $this->assertSame('https://api.experientiallabs.ai/v1/chat/completions', $req['url']);
            $body = json_decode($req['body'], true);
            $this->assertSame($m, $body['model']);
            $this->assertTrue($body['stream']);
            $this->assertSame(1500, $body['max_completion_tokens']);
            $this->assertArrayNotHasKey('max_tokens', $body, 'max_tokens must be omitted when max_completion_tokens is passed');
        }
    }

    public function testProviderRegistryDynamicExperiential(): void
    {
        putenv('EXPLABS_API_KEY=xpl_registry_test');
        $_ENV['EXPLABS_API_KEY'] = 'xpl_registry_test';

        $config = new Config();
        $registry = new ProviderRegistry($config);

        // Model gpt-5.6-luna
        $p1 = $registry->getForModel('gpt-5.6-luna');
        $this->assertInstanceOf(ExperientialProvider::class, $p1);
        $this->assertSame('https://api.experientiallabs.ai/v1', $p1->getBaseUrl());
        $this->assertSame('gpt-5.6-luna', $p1->getModel());

        // Dynamic model switching via getExperiential()
        $p2 = $registry->getExperiential('qwen3.8-27b');
        $this->assertInstanceOf(ExperientialProvider::class, $p2);
        $this->assertSame('qwen3.8-27b', $p2->getModel());

        $p3 = $registry->getExperiential('deepseek-v4-flash');
        $this->assertSame('deepseek-v4-flash', $p3->getModel());
    }
}
