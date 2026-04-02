<?php
/**
 * Autonomous Continuous Data Sweep & Heal Script — HIGH PERFORMANCE EDITION.
 * Designed to run 24/7 on the local Mac (M4 Pro).
 * 
 * HYBRID POLICY:
 * - Perfect Fixes (0 errors, 0 warnings): Auto-Commit directly to file.
 * - Suspicious Fixes (Any errors/warnings remains): Stage in data/corrections/.
 * 
 * SWITCHABLE AI:
 * - Supports Local LM Studio (Parallel) or Server AI (via WP Plugin).
 */

declare(strict_types=1);

define('HS_DATA_DIR', dirname(__DIR__) . '/data');
define('HS_CORRECTIONS_DIR', HS_DATA_DIR . '/corrections');
define('VALIDATE_BRIDGE', __DIR__ . '/id-ai-validate.php');

// CONFIGURATION: Fetch current mode from WP-CLI (with environment variable override)
$envMode = getenv('HELMETSAN_AI_MODE');
if ($envMode && in_array($envMode, ['local', 'server', 'ide'], true)) {
    $aiMode = $envMode;
} else {
    $cliMode = trim((string)shell_exec("wp helmetsan ai config --get-mode --allow-root 2>/dev/null"));
    $aiMode = in_array($cliMode, ['local', 'server', 'ide'], true) ? $cliMode : 'local';
}

define('PILOT_LIMIT', 10);       // Max total heals per run
define('CONCURRENCY', 4);        // Batch size for local parallel healing
define('HS_LM_STUDIO_URL', 'http://127.0.0.1:1234/v1/chat/completions');

if (!is_dir(HS_CORRECTIONS_DIR)) {
    mkdir(HS_CORRECTIONS_DIR, 0755, true);
}

// Fallback logic: Check if local AI is actually reachable
if ($aiMode === 'local') {
    $ch = curl_init(HS_LM_STUDIO_URL);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_TIMEOUT, 2);
    curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);
    
    if ($httpCode === 0) {
        echo "  ! LOCAL AI (LM Studio) unreachable. Falling back to SERVER mode...\n";
        $aiMode = 'server';
    }
}

echo "--- Helmetsan Autonomous Hybrid Sweep (HP Mode) Started ---\n";
echo "AI MODE:      " . strtoupper($aiMode) . "\n";
echo "PILOT LIMIT:  " . PILOT_LIMIT . "\n";
echo "CONCURRENCY:  " . ($aiMode === 'local' ? CONCURRENCY . 'x Parallel' : ($aiMode === 'server' ? 'Sequential (Plugin)' : 'IDE Assisted')) . "\n\n";

/**
 * Main Execution
 */
$anomalies = find_anomalies(HS_DATA_DIR);

if (empty($anomalies)) {
    echo "Catalog is healthy. No anomalies found.\n";
    exit(0);
}

$healedCount = 0;
while ($healedCount < PILOT_LIMIT && !empty($anomalies)) {
    $batchSize = ($aiMode === 'local') ? CONCURRENCY : 1;
    $batch = array_splice($anomalies, 0, $batchSize);
    
    if ($aiMode === 'local') {
        $healedCount += process_batch_local($batch);
    } elseif ($aiMode === 'server') {
        $healedCount += process_batch_server($batch);
    } else {
        $healedCount += process_batch_ide($batch);
    }
}

echo "\n--- Sweep Completed. Total Healed: $healedCount ---\n";

/**
 * IDE Assisted Healing (Console Prompts)
 */
function process_batch_ide(array $batch): int {
    $item = $batch[0];
    echo "  [IDE MODE] Detected anomaly in " . basename($item['file']) . "\n";
    
    $prompt = build_patch_prompt($item['entity'], file_get_contents($item['file']), $item['issues']);
    
    echo "\n--- HEALING PROMPT FOR IDE AI ---\n";
    echo $prompt . "\n";
    echo "---------------------------------\n\n";
    
    $promptPath = HS_CORRECTIONS_DIR . '/' . basename($item['file']) . '.prompt';
    file_put_contents($promptPath, $prompt);
    
    echo "  ✓ Prompt staged at: " . basename($promptPath) . "\n";
    echo "  ! Action: Use your IDE AI to apply this fix and save to the original file.\n";
    
    return 1; // Increment count as "handled" by IDE
}

/**
 * Find all anomalies in the data directory using the validation bridge.
 */
function find_anomalies(string $dir): array {
    $anomalies = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
    
    foreach ($it as $file) {
        if (!$file->isFile() || $file->getExtension() !== 'json') continue;
        $path = $file->getRealPath();
        
        if (str_contains($path, 'corrections/') || str_contains($path, 'schemas/')) continue;
        
        $output = shell_exec(PHP_BINARY . " " . escapeshellarg(VALIDATE_BRIDGE) . " " . escapeshellarg($path));
        $res = json_decode((string)$output, true);
        
        if ($res && (!$res['ok'] || !empty($res['warnings']))) {
            $issues = array_merge($res['errors'] ?? [], $res['warnings'] ?? []);
            echo "  [ANOMALY] " . basename($path) . ": " . implode('; ', $issues) . "\n";
            
            $anomalies[] = [
                'file'   => $path,
                'entity' => determine_entity($path),
                'issues' => $issues
            ];
        }
    }
    return $anomalies;
}

/**
 * Determine entity type based on path.
 */
function determine_entity(string $path): string {
    if (str_contains($path, '/helmets/')) return 'helmet';
    if (str_contains($path, '/brands/')) return 'brand';
    if (str_contains($path, '/accessories/')) return 'accessory';
    if (str_contains($path, '/distributors/')) return 'distributor';
    
    // Fallback: check JSON content
    $data = json_decode((string)file_get_contents($path), true);
    return $data['entity'] ?? 'helmet';
}

/**
 * Local Healing (Parallel)
 */
function process_batch_local(array $batch): int {
    echo "  [HEALING] Processing batch of " . count($batch) . " via Local AI...\n";
    $mh = curl_multi_init();
    $handles = [];

    foreach ($batch as $idx => $item) {
        $prompt = build_patch_prompt($item['entity'], file_get_contents($item['file']), $item['issues']);
        $ch = curl_init(HS_LM_STUDIO_URL);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode([
            'model' => 'qwen3.5-9b',
            'messages' => [['role' => 'user', 'content' => $prompt]],
            'temperature' => 0.1,
            'max_tokens' => 500
        ]));
        curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json']);
        
        $handles[$idx] = $ch;
        curl_multi_add_handle($mh, $ch);
    }

    $running = null;
    do {
        curl_multi_exec($mh, $running);
    } while ($running);

    $success = 0;
    foreach ($handles as $idx => $ch) {
        $response = curl_multi_getcontent($ch);
        $item = $batch[$idx];
        curl_multi_remove_handle($mh, $ch);
        curl_close($ch);

        $decoded = json_decode((string)$response, true);
        $patchRaw = $decoded['choices'][0]['message']['content'] ?? null;
        
        if (apply_patch($item, $patchRaw)) {
            $success++;
        }
    }
    curl_multi_close($mh);
    return $success;
}

/**
 * Server Healing (Sequential via WP-CLI)
 */
function process_batch_server(array $batch): int {
    $item = $batch[0]; 
    echo "  [HEALING] Processing " . basename($item['file']) . " via Server AI...\n";
    
    $json   = file_get_contents($item['file']);
    $issues = implode('; ', $item['issues']);
    
    $cmd = "wp helmetsan ai heal --json=" . escapeshellarg($json) . " --entity=" . escapeshellarg($item['entity']) . " --issues=" . escapeshellarg($issues);
    $patchRaw = shell_exec($cmd);
    
    return apply_patch($item, $patchRaw) ? 1 : 0;
}

/**
 * Shared Patch Application Logic
 */
function apply_patch(array $item, ?string $patchRaw): bool {
    if (!$patchRaw) return false;
    
    $patchRaw = preg_replace('/^```json\s*|\s*```$/i', '', trim($patchRaw));
    $patch = json_decode($patchRaw, true);
    
    if (!is_array($patch)) return false;
    
    $original = json_decode((string)file_get_contents($item['file']), true);
    $healed = array_replace_recursive($original, $patch);
    $healedRaw = json_encode($healed, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    
    // Re-verify
    $tmp = sys_get_temp_dir() . '/heal_test_' . uniqid() . '.json';
    file_put_contents($tmp, $healedRaw);
    $check = shell_exec(PHP_BINARY . " " . escapeshellarg(VALIDATE_BRIDGE) . " " . escapeshellarg($tmp));
    $res = json_decode((string)$check, true);
    @unlink($tmp);
    
    if ($res && $res['ok'] && empty($res['warnings'])) {
        file_put_contents($item['file'], $healedRaw);
        echo "    [FIXED] " . basename($item['file']) . " (Auto-Committed)\n";
        
        // Log to database
        log_to_db($item, $patchRaw, $original, true);
        return true;
    } else {
        file_put_contents(HS_CORRECTIONS_DIR . '/' . basename($item['file']), $healedRaw);
        echo "    [STAGED] " . basename($item['file']) . " (Imperfect fix, manually review corrections/)\n";
        
        // Log as staged
        log_to_db($item, $patchRaw, $original, false);
        return true;
    }
}

/**
 * Log the heal event to the WordPress database via WP-CLI.
 */
function log_to_db(array $item, string $patch, array $original, bool $applied): void {
    global $aiMode;
    $issues = implode('; ', $item['issues']);
    $origJson = json_encode($original, JSON_UNESCAPED_SLASHES);
    $cmd = "wp helmetsan ai log-heal " . 
           "--entity=" . escapeshellarg($item['entity']) . " " .
           "--item_id=" . escapeshellarg(basename($item['file'], '.json')) . " " .
           "--file=" . escapeshellarg($item['file']) . " " .
           "--issues=" . escapeshellarg($issues) . " " .
           "--patch=" . escapeshellarg($patch) . " " .
           "--original=" . escapeshellarg((string) $origJson) . " " .
           "--mode=" . escapeshellarg($aiMode) . " " .
           ($applied ? "--applied" : "");
    shell_exec($cmd);
}

function build_patch_prompt(string $entity, string $json, array $issues): string {
    $prompt = "You are a JSON repair and data enrichment expert specialized in premium motorcycle gear.\n"
            . "Entity: $entity\n"
            . "Data: $json\n"
            . "Issues Detected: " . implode('; ', $issues) . "\n\n"
            . "Specific Instructions for Descriptions:\n"
            . "- If a 'Missing marketing description' or 'Fallback description' is flagged, generate a high-quality, SEO-optimized 'product_details.description'.\n"
            . "- Length: 100-150 words. Focus on styling, safety engineering, aerodynamics, and ventilation.\n"
            . "- Tone: Premium, authoritative, and evocative.\n\n"
            . "General Task:\n"
            . "- Return ONLY the corrected or missing fields as a JSON patch (no full object).\n"
            . "- Maintain the existing structure. If adding to 'product_details', nest it correctly.\n"
            . "- Rules: Valid JSON only. No markdown formatting. No preamble.";
    
    return $prompt;
}
