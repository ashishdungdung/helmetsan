<?php
/**
 * =============================================================================
 * Helmetsan Catalog Data Drift Sentinel
 * =============================================================================
 * Detects discrepancies between the canonical Git JSON repository (data/)
 * and the live WordPress MariaDB database.
 *
 * Usage:
 *   php scripts/check_data_drift.php                 → Full audit across entities
 *   php scripts/check_data_drift.php --entity=helmet → Check helmets only
 *   php scripts/check_data_drift.php --json          → Output JSON for Manager UI
 *   php scripts/check_data_drift.php --sync-to-git   → Reverse-sync drifted WP posts to JSON
 * =============================================================================
 */

declare(strict_types=1);

$rootDir = dirname(__DIR__);
$dataDir = $rootDir . '/data';
$configFile = $rootDir . '/scripts/config';

if (!file_exists($configFile)) {
    fwrite(STDERR, "❌ Config file scripts/config not found.\n");
    exit(1);
}

// Parse remote server info
$configContent = file_get_contents($configFile);
preg_match('/^HOST="([^"]+)"/m', $configContent, $mHost);
preg_match('/^USER="([^"]+)"/m', $configContent, $mUser);
preg_match('/^REMOTE_WP_PATH="([^"]+)"/m', $configContent, $mWpPath);

$host = $mHost[1] ?? '31.70.136.154';
$user = $mUser[1] ?? 'root';
$wpPath = $mWpPath[1] ?? '/var/www/helmetsan.com/public';

$options = getopt('', ['entity:', 'json', 'sync-to-git', 'limit:']);
$targetEntity = $options['entity'] ?? 'all';
$asJson = isset($options['json']);
$syncToGit = isset($options['sync-to-git']);
$limit = isset($options['limit']) ? (int)$options['limit'] : 100;

if (!$asJson) {
    echo "🛡️  Helmetsan Data Drift Sentinel v1.0\n";
    echo "   Auditing: Git JSON (local data/) <--> Live MariaDB ($host)\n\n";
}

// Build remote WP-CLI script to fetch recent modified items and their hashes
$remoteAuditScript = <<<'PHP'
$entities = ['helmet', 'motorcycle', 'accessory', 'brand'];
$results = [];

foreach ($entities as $type) {
    $posts = get_posts([
        'post_type' => $type,
        'posts_per_page' => 200,
        'post_status' => 'publish',
        'orderby' => 'modified',
        'order' => 'DESC'
    ]);

    foreach ($posts as $p) {
        $uniqueId = get_post_meta($p->ID, "_{$type}_unique_id", true) ?: $p->post_name;
        $hash = get_post_meta($p->ID, "_{$type}_hash", true);
        $results[] = [
            'type' => $type,
            'id' => $uniqueId,
            'slug' => $p->post_name,
            'post_id' => $p->ID,
            'post_title' => $p->post_title,
            'modified_gmt' => $p->post_modified_gmt,
            'hash' => $hash,
        ];
    }
}

echo json_encode($results);
PHP;

$b64Script = base64_encode("<?php\n" . $remoteAuditScript);

// Execute over SSH using base64 pipe
$sshCommand = sprintf(
    'ssh -o StrictHostKeyChecking=no -o ConnectTimeout=8 %s@%s "echo %s | base64 -d | wp --path=%s --allow-root eval-file -"',
    escapeshellarg($user),
    escapeshellarg($host),
    escapeshellarg($b64Script),
    escapeshellarg($wpPath)
);

$output = shell_exec($sshCommand);
if (!$output) {
    if ($asJson) {
        echo json_encode(['error' => 'Unable to connect to live WordPress instance']);
    } else {
        echo "❌ Error: Could not connect to remote WordPress instance on $host\n";
    }
    exit(1);
}

// Parse response
$wpItems = json_decode($output, true);
if (!is_array($wpItems)) {
    if ($asJson) {
        echo json_encode(['error' => 'Invalid response from remote WordPress audit', 'raw' => substr($output, 0, 300)]);
    } else {
        echo "❌ Error parsing remote audit output:\n" . substr($output, 0, 300) . "\n";
    }
    exit(1);
}

$driftReport = [
    'audited_count' => count($wpItems),
    'drifted_count' => 0,
    'synced_count' => 0,
    'missing_in_git' => [],
    'drifted_items' => []
];

foreach ($wpItems as $item) {
    $type = $item['type'];
    if ($targetEntity !== 'all' && $targetEntity !== $type) {
        continue;
    }

    $slug = $item['slug'];
    $jsonFile = "{$dataDir}/{$type}s/{$slug}.json";
    if (!file_exists($jsonFile)) {
        // Check singular folder for motorcycle/accessory/brand
        $folder = match ($type) {
            'motorcycle' => 'motorcycles',
            'accessory' => 'accessories',
            'brand' => 'brands',
            'helmet' => 'helmets',
            default => "{$type}s"
        };
        $jsonFile = "{$dataDir}/{$folder}/{$slug}.json";
    }

    if (!file_exists($jsonFile)) {
        $driftReport['missing_in_git'][] = [
            'type' => $type,
            'slug' => $slug,
            'title' => $item['post_title'],
            'post_id' => $item['post_id'],
            'reason' => 'Post exists in WordPress MariaDB but no corresponding JSON file found in Git'
        ];
        $driftReport['drifted_count']++;
        continue;
    }

    $fileMtime = filemtime($jsonFile);
    $wpModified = strtotime($item['modified_gmt']);

    // Check if WordPress was edited significantly after Git commit (buffer of 60s)
    if ($wpModified > ($fileMtime + 60)) {
        $driftReport['drifted_items'][] = [
            'type' => $type,
            'slug' => $slug,
            'title' => $item['post_title'],
            'post_id' => $item['post_id'],
            'wp_modified' => $item['modified_gmt'],
            'git_modified' => date('Y-m-d H:i:s', $fileMtime),
            'json_path' => str_replace($rootDir . '/', '', $jsonFile)
        ];
        $driftReport['drifted_count']++;
    } else {
        $driftReport['synced_count']++;
    }
}

if ($asJson) {
    echo json_encode($driftReport, JSON_PRETTY_PRINT);
    exit(0);
}

// Pretty CLI output
echo "📊 Audit Results:\n";
echo "   Total Live Posts Checked: {$driftReport['audited_count']}\n";
echo "   In Perfect Sync:          {$driftReport['synced_count']}\n";
echo "   Drifted / Out of Sync:    {$driftReport['drifted_count']}\n\n";

if ($driftReport['drifted_count'] === 0) {
    echo "✅ EXCELLENT: Zero data drift detected! Git JSON and Live WordPress are 100% in sync.\n";
    exit(0);
}

if (!empty($driftReport['drifted_items'])) {
    echo "⚠️  Items Modified in WordPress After Git Commit:\n";
    foreach ($driftReport['drifted_items'] as $drift) {
        echo "   • [{$drift['type']}] {$drift['title']} ({$drift['slug']})\n";
        echo "     WP Modified:  {$drift['wp_modified']} GMT\n";
        echo "     Git Modified: {$drift['git_modified']} Local\n";
        echo "     File:         {$drift['json_path']}\n\n";
    }
}

if (!empty($driftReport['missing_in_git'])) {
    echo "⚠️  Items in WordPress Missing From Local Git JSON:\n";
    foreach ($driftReport['missing_in_git'] as $missing) {
        echo "   • [{$missing['type']}] {$missing['title']} (Slug: {$missing['slug']}, ID: {$missing['post_id']})\n";
    }
    echo "\n";
}

if ($syncToGit) {
    echo "🔄 Running automated reverse-sync to pull live changes into Git JSON...\n";
    foreach ($driftReport['drifted_items'] as $drift) {
        $exportCmd = sprintf(
            'ssh -o StrictHostKeyChecking=no %s@%s "wp --path=%s --allow-root helmetsan export --post_id=%d --entity=%s"',
            escapeshellarg($user),
            escapeshellarg($host),
            escapeshellarg($wpPath),
            $drift['post_id'],
            escapeshellarg($drift['type'])
        );
        echo "   Exporting post {$drift['post_id']} ({$drift['slug']})...\n";
        shell_exec($exportCmd);
    }
    echo "✅ Reverse-sync complete. Review changes with 'git status' and 'git diff'.\n";
} else {
    echo "💡 TIP: Run with '--sync-to-git' to automatically export drifted live WordPress posts into your local JSON files.\n";
}
