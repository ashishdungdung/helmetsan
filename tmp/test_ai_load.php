<?php
function get_option($name, $default = false) { return $default; }
function __($text, $domain) { return $text; }
function is_wp_error($thing) { return false; }
function wp_parse_args($args, $defaults = []) { return array_merge($defaults, $args); }
function sanitize_text_field($s) { return $s; }
function wp_unslash($s) { return $s; }

if (!defined('WP_CONTENT_DIR')) define('WP_CONTENT_DIR', __DIR__ . '/../wp-content');

require_once __DIR__ . '/../helmetsan-core/includes/AI/ProviderInterface.php';
require_once __DIR__ . '/../helmetsan-core/includes/AI/BaseProvider.php';
require_once __DIR__ . '/../helmetsan-core/includes/AI/Providers/LMStudioProvider.php';
require_once __DIR__ . '/../helmetsan-core/includes/AI/Providers/OpenAIProvider.php';
require_once __DIR__ . '/../helmetsan-core/includes/AI/Providers/CloudflareProvider.php';
require_once __DIR__ . '/../helmetsan-core/includes/AI/ProviderRegistry.php';
require_once __DIR__ . '/../helmetsan-core/includes/AI/HealRepository.php';
require_once __DIR__ . '/../helmetsan-core/includes/AI/AiServiceInterface.php';
require_once __DIR__ . '/../helmetsan-core/includes/AI/AiService.php';
require_once __DIR__ . '/../helmetsan-core/includes/AI/ParallelAiClient.php';
require_once __DIR__ . '/../helmetsan-core/includes/Support/Config.php';

use Helmetsan\Core\AI\AiService;
use Helmetsan\Core\AI\ProviderRegistry;
use Helmetsan\Core\AI\HealRepository;
use Helmetsan\Core\Support\Config;

$config = new Config();
$registry = new ProviderRegistry($config);
$heals = new HealRepository();
$service = new AiService($registry, $heals);

echo "AiService loaded successfully\n";
