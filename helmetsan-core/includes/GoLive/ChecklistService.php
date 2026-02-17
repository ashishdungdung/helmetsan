<?php

declare(strict_types=1);

namespace Helmetsan\Core\GoLive;

use Helmetsan\Core\Analytics\SmokeTestService;
use Helmetsan\Core\Health\HealthService;
use Helmetsan\Core\Support\Config;

final class ChecklistService
{
    public function __construct(
        private readonly HealthService $health,
        private readonly SmokeTestService $smoke
    ) {
    }

    public function items(): array
    {
        $report = $this->report();
        return $report['checks'];
    }

    /**
     * @return array<string,mixed>
     */
    public function report(): array
    {
        $health = $this->health->report();
        $smoke  = $this->smoke->run();
        $hygiene = $this->repoHygieneCheck();

        $checks = [
            [
                'id'     => 'data_minimum',
                'label'  => 'Minimum helmets published (>= 50)',
                'critical' => false,
                'weight' => 10,
                'passed' => (($health['database']['cpt_helmet_rows'] ?? 0) >= 50),
                'details' => 'Current: ' . (string) ($health['database']['cpt_helmet_rows'] ?? 0),
            ],
            [
                'id'     => 'analytics_smoke',
                'label'  => 'Analytics smoke test is ready',
                'critical' => false,
                'weight' => 8,
                'passed' => (($smoke['status'] ?? '') === 'ready'),
                'details' => 'Status: ' . (string) ($smoke['status'] ?? 'unknown'),
            ],
            [
                'id'     => 'repo_connected',
                'label'  => 'Repository root path is available',
                'critical' => true,
                'weight' => 8,
                'passed' => ! empty($health['repository']['root_exists']),
                'details' => 'JSON files: ' . (string) ($health['repository']['json_files'] ?? 0),
            ],
            [
                'id'     => 'integrity_ok',
                'label'  => 'Integrity checks have no critical failures',
                'critical' => true,
                'weight' => 15,
                'passed' => ! empty($health['integrity']['ok']),
                'details' => ! empty($health['integrity']['ok']) ? 'Integrity OK' : 'Integrity errors detected',
            ],
            [
                'id' => 'github_configured',
                'label' => 'GitHub sync is configured and enabled',
                'critical' => true,
                'weight' => 14,
                'passed' => ! empty($health['github_sync']['enabled']) && ! empty($health['github_sync']['configured']),
                'details' => 'Repo: ' . (string) ($health['github_sync']['owner'] ?? '') . '/' . (string) ($health['github_sync']['repo'] ?? ''),
            ],
            [
                'id' => 'sync_profile_locked',
                'label' => 'Sync profile lock is enabled',
                'critical' => false,
                'weight' => 7,
                'passed' => ! empty($health['github_sync']['sync_profile_lock']),
                'details' => 'Profile: ' . (string) ($health['github_sync']['sync_run_profile'] ?? 'pull-only'),
            ],
            [
                'id' => 'scheduler_enabled',
                'label' => 'Scheduler is enabled',
                'critical' => false,
                'weight' => 8,
                'passed' => ! empty($health['scheduler']['enabled']),
                'details' => ! empty($health['scheduler']['enabled']) ? 'Scheduled automation active' : 'Scheduler disabled',
            ],
            [
                'id' => 'ingestion_failures',
                'label' => 'No failed ingestion logs',
                'critical' => true,
                'weight' => 10,
                'passed' => ((int) ($health['ingestion_logs']['failed_rows'] ?? 0) === 0),
                'details' => 'Failed rows: ' . (string) ($health['ingestion_logs']['failed_rows'] ?? 0),
            ],
            [
                'id' => 'sync_errors',
                'label' => 'No sync error logs',
                'critical' => true,
                'weight' => 8,
                'passed' => ((int) ($health['sync_logs']['error_rows'] ?? 0) === 0),
                'details' => 'Sync errors: ' . (string) ($health['sync_logs']['error_rows'] ?? 0),
            ],
            [
                'id' => 'repo_hygiene',
                'label' => 'Remote data path has no temp/runtime folders',
                'critical' => true,
                'weight' => 8,
                'passed' => $hygiene['passed'],
                'details' => $hygiene['details'],
            ],
            [
                'id' => 'alerts_enabled',
                'label' => 'Alerts are enabled',
                'critical' => false,
                'weight' => 6,
                'passed' => ! empty($health['alerts']['enabled']),
                'details' => 'Email: ' . (! empty($health['alerts']['email_enabled']) ? 'on' : 'off') . ', Slack: ' . (! empty($health['alerts']['slack_enabled']) ? 'on' : 'off'),
            ],
            [
                'id' => 'seo_data_quality',
                'label' => 'SEO baseline data present (brand + price + weight)',
                'critical' => false,
                'weight' => 6,
                'passed' => $this->seoDataQualityCheck(),
                'details' => 'Sampled helmets have required SEO fields',
            ],
        ];

        $totalWeight = 0;
        $achievedWeight = 0;
        $criticalFailed = [];
        foreach ($checks as $check) {
            $weight = isset($check['weight']) ? (int) $check['weight'] : 0;
            $passed = ! empty($check['passed']);
            $totalWeight += $weight;
            if ($passed) {
                $achievedWeight += $weight;
            } elseif (! empty($check['critical'])) {
                $criticalFailed[] = (string) ($check['id'] ?? 'unknown');
            }
        }

        $score = $totalWeight > 0 ? (int) round(($achievedWeight / $totalWeight) * 100) : 0;
        $passed = $score >= 80 && $criticalFailed === [];

        return [
            'ok' => true,
            'score' => $score,
            'pass' => $passed,
            'threshold' => 80,
            'critical_failures' => $criticalFailed,
            'totals' => [
                'checks' => count($checks),
                'passed' => count(array_filter($checks, static fn (array $c): bool => ! empty($c['passed']))),
                'failed' => count(array_filter($checks, static fn (array $c): bool => empty($c['passed']))),
                'weight_total' => $totalWeight,
                'weight_achieved' => $achievedWeight,
            ],
            'checks' => $checks,
            'generated_at' => current_time('mysql'),
        ];
    }

    private function seoDataQualityCheck(): bool
    {
        $posts = get_posts([
            'post_type'      => 'helmet',
            'post_status'    => 'publish',
            'posts_per_page' => 20,
            'fields'         => 'ids',
        ]);

        if (! is_array($posts) || $posts === []) {
            return false;
        }

        foreach ($posts as $postId) {
            $postId = (int) $postId;
            $brand = (int) get_post_meta($postId, 'rel_brand', true);
            $weight = (string) get_post_meta($postId, 'spec_weight_g', true);
            $price = (string) get_post_meta($postId, 'price_retail_usd', true);
            if ($brand <= 0 || $weight === '' || $price === '') {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array{passed:bool,details:string}
     */
    private function repoHygieneCheck(): array
    {
        $cfg = (new Config())->githubConfig();
        if (empty($cfg['enabled']) || empty($cfg['owner']) || empty($cfg['repo']) || empty($cfg['token'])) {
            return [
                'passed' => true,
                'details' => 'Skipped: GitHub sync not fully configured',
            ];
        }

        $owner = (string) $cfg['owner'];
        $repo = (string) $cfg['repo'];
        $branch = (string) ($cfg['branch'] ?? 'main');
        $remoteBase = trim((string) ($cfg['remote_path'] ?? 'data'), '/');
        if ($remoteBase === '') {
            $remoteBase = 'data';
        }

        $cacheKey = 'helmetsan_repo_hygiene_' . md5($owner . '/' . $repo . ':' . $branch . ':' . $remoteBase);
        $cached = get_transient($cacheKey);
        if (is_array($cached) && isset($cached['passed']) && isset($cached['details'])) {
            return [
                'passed' => (bool) $cached['passed'],
                'details' => (string) $cached['details'],
            ];
        }

        $headers = [
            'Authorization' => 'Bearer ' . (string) $cfg['token'],
            'Accept' => 'application/vnd.github+json',
            'X-GitHub-Api-Version' => '2022-11-28',
            'User-Agent' => 'Helmetsan-Core/' . HELMETSAN_CORE_VERSION,
        ];

        $refUrl = 'https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo) . '/git/ref/heads/' . rawurlencode($branch);
        $refRes = wp_remote_get($refUrl, ['timeout' => 20, 'headers' => $headers]);
        if (is_wp_error($refRes)) {
            return [
                'passed' => false,
                'details' => 'GitHub ref lookup failed: ' . $refRes->get_error_message(),
            ];
        }
        $refCode = (int) wp_remote_retrieve_response_code($refRes);
        $refBody = json_decode((string) wp_remote_retrieve_body($refRes), true);
        if ($refCode < 200 || $refCode >= 300 || ! is_array($refBody)) {
            return [
                'passed' => false,
                'details' => 'GitHub ref lookup HTTP ' . (string) $refCode,
            ];
        }
        $sha = isset($refBody['object']['sha']) ? (string) $refBody['object']['sha'] : '';
        if ($sha === '') {
            return [
                'passed' => false,
                'details' => 'GitHub ref SHA missing',
            ];
        }

        $treeUrl = 'https://api.github.com/repos/' . rawurlencode($owner) . '/' . rawurlencode($repo) . '/git/trees/' . rawurlencode($sha) . '?recursive=1';
        $treeRes = wp_remote_get($treeUrl, ['timeout' => 25, 'headers' => $headers]);
        if (is_wp_error($treeRes)) {
            return [
                'passed' => false,
                'details' => 'GitHub tree lookup failed: ' . $treeRes->get_error_message(),
            ];
        }
        $treeCode = (int) wp_remote_retrieve_response_code($treeRes);
        $treeBody = json_decode((string) wp_remote_retrieve_body($treeRes), true);
        if ($treeCode < 200 || $treeCode >= 300 || ! is_array($treeBody)) {
            return [
                'passed' => false,
                'details' => 'GitHub tree lookup HTTP ' . (string) $treeCode,
            ];
        }

        $tree = isset($treeBody['tree']) && is_array($treeBody['tree']) ? $treeBody['tree'] : [];
        $violations = [];
        $needles = ['/_imports/', '/_exports/', '/_bootstrap/', '/helmetsan-runtime/'];
        $prefix = $remoteBase . '/';

        foreach ($tree as $item) {
            if (! is_array($item)) {
                continue;
            }
            $path = isset($item['path']) ? (string) $item['path'] : '';
            if ($path === '' || strpos($path, $prefix) !== 0) {
                continue;
            }
            $normalized = '/' . strtolower(ltrim(substr($path, strlen($remoteBase)), '/'));
            foreach ($needles as $needle) {
                if (strpos($normalized . '/', $needle) !== false) {
                    $violations[] = $path;
                    break;
                }
            }
        }

        $violations = array_values(array_unique($violations));
        if ($violations === []) {
            $result = [
                'passed' => true,
                'details' => 'No temp/runtime folders detected in remote data path',
            ];
            set_transient($cacheKey, $result, 5 * MINUTE_IN_SECONDS);
            return $result;
        }

        $result = [
            'passed' => false,
            'details' => 'Temp/runtime paths found: ' . implode(', ', array_slice($violations, 0, 5)),
        ];
        set_transient($cacheKey, $result, 5 * MINUTE_IN_SECONDS);

        return $result;
    }
}
