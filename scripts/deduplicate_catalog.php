<?php
/**
 * P0 Fix: Catalog Slug Deduplication & Collision Resolver
 *
 * Finds all helmet/accessory/motorcycle JSON files where two slug variants
 * refer to the same real product (e.g. "foo-bar.json" vs "foo_bar.json").
 * For each collision: merges into the canonical hyphen-form slug, deletes the stale file.
 *
 * Usage:
 *   php scripts/deduplicate_catalog.php [--dry-run] [--catalog=helmets]
 *
 * Options:
 *   --dry-run      List collisions without modifying any files.
 *   --catalog=X    Target catalog: helmets (default), accessories, motorcycles.
 */

declare(strict_types=1);

define('PROJECT_ROOT', dirname(__DIR__));

$opts     = getopt('', ['dry-run', 'catalog:']);
$isDryRun = isset($opts['dry-run']);
$catalog  = $opts['catalog'] ?? 'helmets';

$allowedCatalogs = ['helmets', 'accessories', 'motorcycles'];
if (!in_array($catalog, $allowedCatalogs, true)) {
    fwrite(STDERR, "Error: --catalog must be one of: " . implode(', ', $allowedCatalogs) . "\n");
    exit(1);
}

$dataDir = PROJECT_ROOT . '/data/' . $catalog;
if (!is_dir($dataDir)) {
    fwrite(STDERR, "Error: Data directory not found: $dataDir\n");
    exit(1);
}

echo "========================================================\n";
echo "🔍 Helmetsan Catalog Slug Deduplication\n";
echo "========================================================\n";
echo "  Catalog : $catalog\n";
echo "  Mode    : " . ($isDryRun ? "DRY RUN (no changes)" : "LIVE (will merge & delete)") . "\n";
echo "  Path    : $dataDir\n";
echo "========================================================\n\n";

// -----------------------------------------------------------------------
// Step 1: Inventory all files and normalize slugs for comparison
// -----------------------------------------------------------------------

/** @var array<string, list<array{file: string, slug: string, mtime: int, size: int, fields: int}>> */
$normalized = [];

$files = glob($dataDir . '/*.json');
if ($files === false || empty($files)) {
    echo "No JSON files found in $dataDir\n";
    exit(0);
}

foreach ($files as $file) {
    $slug     = basename($file, '.json');
    // Canonical form: replace all underscores with hyphens for comparison key
    $normKey  = str_replace('_', '-', $slug);
    $stat     = stat($file);
    $data     = json_decode((string)file_get_contents($file), true);
    $fields   = is_array($data) ? count($data, COUNT_RECURSIVE) : 0;

    $normalized[$normKey][] = [
        'file'   => $file,
        'slug'   => $slug,
        'mtime'  => $stat ? (int)$stat['mtime'] : 0,
        'size'   => $stat ? (int)$stat['size']   : 0,
        'fields' => $fields,
        'data'   => $data,
    ];
}

// -----------------------------------------------------------------------
// Step 2: Find collision groups (same normalized key, 2+ actual files)
// -----------------------------------------------------------------------

$collisions = array_filter($normalized, fn(array $group): bool => count($group) > 1);
$totalCollisions = count($collisions);

if ($totalCollisions === 0) {
    echo "✅ No slug collisions found. Catalog is clean.\n";
    exit(0);
}

echo "⚠️  Found $totalCollisions slug collision group(s):\n\n";

$mergedCount = 0;
$deletedCount = 0;
$conflictCount = 0;
$conflicts = [];

foreach ($collisions as $normKey => $group) {
    // Canonical slug: prefer the hyphen-form file if it exists, else pick by most fields
    usort($group, function (array $a, array $b): int {
        // Prefer hyphen-form slug (canonical standard)
        $aIsHyphen = !str_contains($a['slug'], '_') || preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $a['slug']);
        $bIsHyphen = !str_contains($b['slug'], '_') || preg_match('/^[a-z0-9]+(-[a-z0-9]+)*$/', $b['slug']);
        if ($aIsHyphen !== $bIsHyphen) {
            return $bIsHyphen <=> $aIsHyphen; // hyphen-form first
        }
        // Then prefer richer data (more recursive fields)
        if ($b['fields'] !== $a['fields']) {
            return $b['fields'] <=> $a['fields'];
        }
        // Then prefer newer file
        return $b['mtime'] <=> $a['mtime'];
    });

    $canonical  = $group[0]; // Winner
    $stalePairs = array_slice($group, 1);

    // Detect factual conflicts (type, title, price divergence)
    $hasConflict = false;
    $canonicalData = $canonical['data'];

    foreach ($stalePairs as $stale) {
        $staleData = $stale['data'];
        $fieldConflicts = [];

        // Check critical fields for conflicts
        foreach (['type', 'title', 'brand'] as $field) {
            if (
                isset($canonicalData[$field], $staleData[$field]) &&
                strtolower((string)$canonicalData[$field]) !== strtolower((string)$staleData[$field])
            ) {
                $fieldConflicts[] = "$field: \"{$canonicalData[$field]}\" vs \"{$staleData[$field]}\"";
            }
        }

        if (!empty($fieldConflicts)) {
            $hasConflict = true;
            $conflictCount++;
            $conflicts[] = [
                'canonical'      => basename($canonical['file']),
                'stale'          => basename($stale['file']),
                'conflicts'      => $fieldConflicts,
            ];

            echo "  ❌ CONFLICT — requires manual review:\n";
            echo "     Canonical : " . basename($canonical['file']) . " ({$canonical['fields']} fields)\n";
            echo "     Stale     : " . basename($stale['file']) . " ({$stale['fields']} fields)\n";
            foreach ($fieldConflicts as $fc) {
                echo "       ⚡ $fc\n";
            }
            echo "\n";
        }
    }

    if ($hasConflict) {
        continue; // Skip auto-merge — flag for human review
    }

    // Safe to auto-merge: canonical wins, stale gets deleted
    echo "  ✅ MERGE: " . basename($canonical['file']) . " (wins, {$canonical['fields']} fields)\n";
    foreach ($stalePairs as $stale) {
        // Merge any fields the stale has that canonical is missing
        if (is_array($canonicalData) && is_array($stale['data'])) {
            $merged = array_replace_recursive($stale['data'], $canonicalData); // canonical values win
            $canonicalData = $merged;
        }

        echo "     → Delete: " . basename($stale['file']) . "\n";

        if (!$isDryRun) {
            @unlink($stale['file']);
            $deletedCount++;
        }
    }

    if (!$isDryRun && is_array($canonicalData)) {
        // Ensure the canonical file has the hyphen-form slug as its ID
        $canonicalSlug = str_replace('_', '-', $canonical['slug']);
        $canonicalData['id'] = $canonicalSlug;

        // Atomic write back
        $atomicTmp = $canonical['file'] . '.dedup_tmp';
        file_put_contents($atomicTmp, json_encode($canonicalData, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX);
        rename($atomicTmp, $canonical['file']);
        $mergedCount++;
    }

    echo "\n";
}

// -----------------------------------------------------------------------
// Step 3: Write conflict report for human review
// -----------------------------------------------------------------------

if (!empty($conflicts)) {
    $reportPath = PROJECT_ROOT . '/data/corrections/slug_conflicts_' . date('Ymd_His') . '.json';
    if (!is_dir(dirname($reportPath))) {
        mkdir(dirname($reportPath), 0755, true);
    }

    if (!$isDryRun) {
        file_put_contents(
            $reportPath,
            json_encode([
                'generated_at' => date('c'),
                'catalog'      => $catalog,
                'total_groups' => $totalCollisions,
                'conflicts'    => $conflicts,
                'instruction'  => 'Review each conflict. Determine the correct value from manufacturer spec sheet. Delete the stale file after resolving.',
            ], JSON_PRETTY_PRINT)
        );
        echo "📋 Conflict report saved: " . basename($reportPath) . "\n";
    }
}

// -----------------------------------------------------------------------
// Summary
// -----------------------------------------------------------------------

echo "========================================================\n";
echo "SUMMARY\n";
echo "========================================================\n";
echo "  Total collision groups : $totalCollisions\n";
echo "  Auto-merged (clean)    : $mergedCount\n";
echo "  Stale files deleted    : $deletedCount\n";
echo "  Conflicts (manual req) : $conflictCount\n";

if ($isDryRun) {
    echo "\n  [DRY RUN] No files were changed.\n";
} elseif ($mergedCount > 0) {
    echo "\n⚡ Rebuilding RAM index after deduplication...\n";
    $indexScript = PROJECT_ROOT . '/scripts/build_in_memory_catalog_index.py';
    if (file_exists($indexScript)) {
        passthru('python3 ' . escapeshellarg($indexScript));
        echo "✅ RAM index rebuilt.\n";
    }
}

echo "========================================================\n";
exit($conflictCount > 0 ? 1 : 0); // Non-zero exit if manual review needed
