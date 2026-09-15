<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/tests/bootstrap.php';
require_once dirname(__DIR__) . '/helmetsan-core/includes/AI/ProviderInterface.php';
require_once dirname(__DIR__) . '/helmetsan-core/includes/AI/BaseProvider.php';
require_once dirname(__DIR__) . '/helmetsan-core/includes/AI/Providers/OpenAIProvider.php';
require_once dirname(__DIR__) . '/helmetsan-core/includes/AI/Providers/ExperientialProvider.php';

use Helmetsan\Core\AI\Providers\OpenAIProvider;
use Helmetsan\Core\AI\Providers\ExperientialProvider;

$opts = getopt("", ["model:", "key:", "list-models"]);
$targetModel = $opts['model'] ?? getenv('EXPLABS_MODEL') ?: ($argv[1] ?? 'gpt-5.6-luna');

// Handle if first arg was a key starting with xpl_
$explicitKey = $opts['key'] ?? '';
if (str_starts_with($targetModel, 'xpl_')) {
    $explicitKey = $targetModel;
    $targetModel = $argv[2] ?? 'gpt-5.6-luna';
}

echo "========================================================\n";
echo "🔬 EXPERIENTIAL GATEWAY DYNAMIC MODEL CLIENT\n";
echo "========================================================\n";
echo "Target Model: {$targetModel}\n\n";

// 1. Check without key to test enforcement
$currentKey = getenv('EXPLABS_API_KEY');

if (empty($currentKey) && empty($explicitKey)) {
    echo "⚠️  TEST 1: Verifying guard when EXPLABS_API_KEY is not set...\n";
    try {
        new ExperientialProvider('', $targetModel);
        echo "❌ FAILED: Expected exception was not thrown!\n";
    } catch (\RuntimeException $e) {
        echo "✅ PASSED: Guard triggered correctly:\n";
        echo "   -> " . $e->getMessage() . "\n\n";
    }
    echo "ℹ️  To execute the live test call, export EXPLABS_API_KEY or use --key=xpl_...\n";
    echo "   Example: EXPLABS_API_KEY=xpl_... php scripts/test_experiential_gateway.php --model={$targetModel}\n";
    exit(0);
}

$apiKey = $currentKey ?: $explicitKey;
putenv("EXPLABS_API_KEY={$apiKey}");
$_ENV['EXPLABS_API_KEY'] = $apiKey;

$provider = new ExperientialProvider('', $targetModel);

echo "🚀 Initialized ExperientialProvider:\n";
echo "   - Model ID   : " . $provider->getModel() . "\n";
echo "   - Base URL   : " . $provider->getBaseUrl() . "\n";
echo "   - Provider ID: " . $provider->getId() . "\n";
echo "   - Configured : " . ($provider->isConfigured() ? 'YES' : 'NO') . "\n\n";

if (isset($opts['list-models'])) {
    echo "📋 Fetching dynamic catalog from Experiential gateway (GET /v1/models)...\n";
    $models = $provider->listModels();
    echo "   -> Retrieved " . count($models) . " available models!\n";
    $sample = array_slice(array_column($models, 'id'), 0, 15);
    echo "   -> Sample: " . implode(', ', $sample) . "...\n\n";
}

echo "📡 Dispatching completion call for '{$targetModel}'...\n";
$prompt = "Hello! Confirm gateway routing for model {$targetModel} in one concise sentence.";
$result = $provider->generateDetailed($prompt, [
    'max_tokens' => 100,
    'temperature' => 0.2,
]);

if ($result === null) {
    echo "❌ Call failed: received null response from gateway.\n";
    exit(1);
}

echo "✅ Gateway response received successfully!\n\n";
echo "--------------------------------------------------------\n";
echo "💬 REPLY:\n" . ($result['content'] ?? '(empty)') . "\n";
echo "--------------------------------------------------------\n";
echo "📊 TOKEN USAGE & COST METRICS:\n";
if (!empty($result['usage'])) {
    foreach ($result['usage'] as $k => $v) {
        if (is_array($v)) {
            echo "   - $k: " . json_encode($v) . "\n";
        } else {
            echo "   - $k: $v\n";
        }
    }
}
echo "--------------------------------------------------------\n";
