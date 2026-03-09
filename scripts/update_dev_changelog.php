<?php

declare(strict_types=1);

/**
 * Update DEV_CHANGELOG.md from recent git commits.
 *
 * Usage (from repo root):
 *   php scripts/update_dev_changelog.php
 *
 * Behaviour:
 * - On first run, creates/updates a state file and does NOT modify the dev changelog.
 * - On subsequent runs, appends new dated sections for commits since the last run,
 *   grouping commits by commit date and using the commit subject as the bullet text.
 */

// Resolve repo root from scripts/ directory.
$scriptDir = __DIR__;
$rootDir   = dirname($scriptDir);

chdir($rootDir);

$stateFile      = $rootDir . '/.dev_changelog_state.json';
$devChangelog   = $rootDir . '/DEV_CHANGELOG.md';

// Helper to run a git command safely and capture output.
$runGit = function (string $args): string {
    $cmd = sprintf('git %s 2>&1', $args);
    $out = [];
    $code = 0;
    exec($cmd, $out, $code);
    if ($code !== 0) {
        throw new RuntimeException("Git command failed: {$cmd}\n" . implode("\n", $out));
    }
    return implode("\n", $out);
};

// Load last processed commit hash, if any.
$lastProcessed = null;
if (is_file($stateFile)) {
    $json = file_get_contents($stateFile);
    if (is_string($json) && $json !== '') {
        $data = json_decode($json, true);
        if (is_array($data) && isset($data['last_processed'])) {
            $lastProcessed = (string) $data['last_processed'];
        }
    }
}

// If no state yet, initialise with HEAD and exit without touching DEV_CHANGELOG.md.
if ($lastProcessed === null) {
    $head = trim($runGit('rev-parse HEAD'));
    file_put_contents($stateFile, json_encode(['last_processed' => $head], JSON_PRETTY_PRINT) . "\n");
    fwrite(STDOUT, "Initialized dev changelog state at HEAD ({$head}). No changes written.\n");
    exit(0);
}

// Get new commits since lastProcessed (exclusive), newest first.
$logFormat = '%H%x1f%ad%x1f%s%x1e'; // SHA, date, subject, record separator.
$range     = escapeshellarg($lastProcessed . '..HEAD');
$logOutput = $runGit("log {$range} --date=short --pretty=format:'{$logFormat}'");

if (trim($logOutput) === '') {
    fwrite(STDOUT, "No new commits since {$lastProcessed}. Nothing to do.\n");
    exit(0);
}

// Parse log into [date => [subjects]] and track newest SHA.
$entriesByDate = [];
$newestSha     = $lastProcessed;

foreach (explode("\x1e", $logOutput) as $record) {
    $record = trim($record);
    if ($record === '') {
        continue;
    }

    [$sha, $date, $subject] = array_pad(explode("\x1f", $record), 3, '');
    $sha     = trim($sha);
    $date    = trim($date);
    $subject = trim($subject);

    if ($sha === '' || $date === '' || $subject === '') {
        continue;
    }

    if (! isset($entriesByDate[$date])) {
        $entriesByDate[$date] = [];
    }
    $entriesByDate[$date][] = $subject;

    if ($newestSha === $lastProcessed) {
        $newestSha = $sha;
    }
}

if ($entriesByDate === []) {
    fwrite(STDOUT, "Parsed no valid commits. Nothing to do.\n");
    exit(0);
}

// Sort dates descending (newest first).
$dates = array_keys($entriesByDate);
rsort($dates);

// Build new sections markdown.
$newSections = '';
foreach ($dates as $date) {
    $newSections .= "## {$date}\n\n";
    foreach ($entriesByDate[$date] as $subject) {
        $newSections .= '- ' . $subject . "\n";
    }
    $newSections .= "\n";
}

// Read existing DEV_CHANGELOG.md.
if (! is_file($devChangelog)) {
    throw new RuntimeException("DEV_CHANGELOG.md not found at {$devChangelog}");
}

$existing = file_get_contents($devChangelog);
if (! is_string($existing) || $existing === '') {
    throw new RuntimeException("DEV_CHANGELOG.md is empty or unreadable.");
}

// Insert new sections after the intro paragraph (before the first "## " heading).
$pos = strpos($existing, "\n## ");
if ($pos === false) {
    // No existing sections; append after header.
    $updated = rtrim($existing) . "\n\n" . $newSections;
} else {
    $before  = substr($existing, 0, $pos);
    $after   = substr($existing, $pos);
    $updated = rtrim($before) . "\n\n" . $newSections . $after;
}

file_put_contents($devChangelog, $updated);

// Update state file with newest processed SHA.
file_put_contents($stateFile, json_encode(['last_processed' => $newestSha], JSON_PRETTY_PRINT) . "\n");

fwrite(STDOUT, "DEV_CHANGELOG.md updated with commits since {$lastProcessed}.\n");

