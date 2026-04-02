<?php
require_once dirname(__DIR__) . '/wp-load.php';

use Helmetsan\Core\AI\AiService;
use Helmetsan\Core\Support\Config;

$service = \Helmetsan\Core\Container::get(AiService::class);
$registry = \Helmetsan\Core\Container::get(\Helmetsan\Core\AI\ProviderRegistry::class);

echo "Checking Concurrency Config...\n";

// Mock providers to check generateMultiplexed logic
$prompts = [
    'p1' => 'test 1',
    'p2' => 'test 2',
    'p3' => 'test 3',
    'p4' => 'test 4',
    'p5' => 'test 5',
];

// We can't easily run the full multiplexed without actual API keys being enabled, 
// but we can check if the ProviderRegistry returns the correct concurrency value.

$lm = $registry->get('lm_studio');
if ($lm) {
    echo "LM Studio Concurrency: " . $lm->getConcurrency() . "\n";
} else {
    echo "LM Studio not enabled/configured in DB (using defaults).\n";
    $defaults = (new Config())->aiDefaults();
    echo "Default LM Studio Concurrency: " . ($defaults['providers']['lm_studio']['concurrency'] ?? 'MISSING') . "\n";
}

echo "Done.\n";
