<?php

declare(strict_types=1);

namespace Helmetsan\Core\CLI;

use Helmetsan\Core\Alerts\AlertService;
use Helmetsan\Core\Analytics\SmokeTestService;
use Helmetsan\Core\Brands\BrandService;
use Helmetsan\Core\Docs\DocsService;
use Helmetsan\Core\GoLive\ChecklistService;
use Helmetsan\Core\Health\HealthService;
use Helmetsan\Core\Analytics\EventRepository;
use Helmetsan\Core\Ingestion\IngestionService;
use Helmetsan\Core\Ingestion\LogRepository;
use Helmetsan\Core\ImportExport\ExportService;
use Helmetsan\Core\ImportExport\ImportService;
use Helmetsan\Core\Media\MediaEngine;
use Helmetsan\Core\Revenue\RevenueService;
use Helmetsan\Core\Scheduler\SchedulerService;
use Helmetsan\Core\Seed\Seeder;
use Helmetsan\Core\AI\AiService;
use Helmetsan\Core\AI\FillMissingService;
use Helmetsan\Core\Seo\AiSeoDescriptionProvider;
use Helmetsan\Core\Seo\SchemaService;
use Helmetsan\Core\Seo\YoastSeoSeeder;
use Helmetsan\Core\Sync\LogRepository as SyncLogRepository;
use Helmetsan\Core\Sync\SyncService;
use Helmetsan\Core\Validation\Validator;
use Helmetsan\Core\WooBridge\WooBridgeService;
use Helmetsan\Core\Price\PriceService;
use Helmetsan\Core\Price\PriceHistory;


final class Commands
{
    public function __construct(
        private readonly HealthService $health,
        private readonly Seeder $seeder,
        private readonly IngestionService $ingestion,
        private readonly SyncService $sync,
        private readonly Validator $validator,
        private readonly SmokeTestService $smoke,
        private readonly ChecklistService $checklist,
        private readonly DocsService $docs,
        private readonly LogRepository $ingestionLogs,
        private readonly ImportService $importService,
        private readonly ExportService $exportService,
        private readonly SyncLogRepository $syncLogs,
        private readonly SchemaService $schema,
        private readonly RevenueService $revenue,
        private readonly EventRepository $analyticsEvents,
        private readonly SchedulerService $scheduler,
        private readonly AlertService $alerts,
        private readonly BrandService $brands,
        private readonly MediaEngine $media,
        private readonly WooBridgeService $wooBridge,
        private readonly PriceService $price,
        private readonly PriceHistory $priceHistory,
        private readonly ?AiService $aiService = null
    ) {
    }

    public function register(): void
    {
        \WP_CLI::add_command('helmetsan health', [$this, 'health']);
        \WP_CLI::add_command('helmetsan seed', [$this, 'seed']);
        \WP_CLI::add_command('helmetsan ingest', [$this, 'ingest']);
        \WP_CLI::add_command('helmetsan sync', [$this, 'sync']);
        \WP_CLI::add_command('helmetsan validate', [$this, 'validate']);
        \WP_CLI::add_command('helmetsan analytics smoke-test', [$this, 'analyticsSmokeTest']);
        \WP_CLI::add_command('helmetsan go-live checklist', [$this, 'goLiveChecklist']);
        \WP_CLI::add_command('helmetsan docs build-index', [$this, 'docsBuildIndex']);
        \WP_CLI::add_command('helmetsan retry-failed', [$this, 'retryFailed']);
        \WP_CLI::add_command('helmetsan import', [$this, 'importData']);
        \WP_CLI::add_command('helmetsan export', [$this, 'exportData']);
        \WP_CLI::add_command('helmetsan unlock-ingestion', [$this, 'unlockIngestion']);
        \WP_CLI::add_command('helmetsan sync-logs', [$this, 'syncLogs']);
        \WP_CLI::add_command('helmetsan sync-logs-cleanup', [$this, 'cleanupSyncLogs']);
        \WP_CLI::add_command('helmetsan ingest-logs-cleanup', [$this, 'cleanupIngestionLogs']);
        \WP_CLI::add_command('helmetsan seo schema-check', [$this, 'schemaCheck']);
        \WP_CLI::add_command('helmetsan seo seed', [$this, 'seoSeed']);
        \WP_CLI::add_command('helmetsan ai fill-missing', [$this, 'aiFillMissing']);
        \WP_CLI::add_command('helmetsan revenue report', [$this, 'revenueReport']);
        \WP_CLI::add_command('helmetsan analytics report', [$this, 'analyticsReport']);
        \WP_CLI::add_command('helmetsan scheduler status', [$this, 'schedulerStatus']);
        \WP_CLI::add_command('helmetsan scheduler run', [$this, 'schedulerRun']);
        \WP_CLI::add_command('helmetsan alerts test', [$this, 'alertsTest']);
        \WP_CLI::add_command('helmetsan brand cascade', [$this, 'brandCascade']);
        \WP_CLI::add_command('helmetsan media backfill-brand-logos', [$this, 'mediaBackfillBrandLogos']);
        \WP_CLI::add_command('helmetsan woo-bridge sync', [$this, 'wooBridgeSync']);

        \WP_CLI::add_command('helmetsan ingest-seed', [$this, 'ingestSeed']);
        \WP_CLI::add_command('helmetsan ingest-brands', [$this, 'ingestBrands']);
        \WP_CLI::add_command('helmetsan seed-accessory-categories', [$this, 'seedAccessoryCategories']);
        \WP_CLI::add_command('helmetsan backfill-accessory-categories', [$this, 'backfillAccessoryCategories']);
        \WP_CLI::add_command('helmetsan revenue import-links', [$this, 'revenueImportLinks']);
        \WP_CLI::add_command('helmetsan price seed-history', [$this, 'priceSeedHistory']);
    }

    /**
     * ## OPTIONS
     * [--format=<format>]
     * : table|json. Default json.
     */
    public function health(array $args, array $assoc): void
    {
        $format = (string) ($assoc['format'] ?? 'json');
        $report = $this->health->report();
        $this->renderAssoc($report, $format);
    }

    /**
     * ## OPTIONS
     * --set=<set>
     * : Seeder set name.
     * [--force]
     * : Force seed even if data exists.
     */
    public function seed(array $args, array $assoc): void
    {
        $set   = (string) ($assoc['set'] ?? 'start-pack-v1');
        $force = isset($assoc['force']);
        $out   = $this->seeder->seed($set, $force);
        \WP_CLI::line(wp_json_encode($out, JSON_PRETTY_PRINT));
    }

    /**
     * ## OPTIONS
     * [--path=<path>]
     * : Relative path under data root.
     * [--batch-size=<n>]
     * : Batch size. Default 100.
     * [--limit=<n>]
     * : Process only first N files.
     * [--dry-run]
     * : Validate only, do not write.
     */
    public function ingest(array $args, array $assoc): void
    {
        $path      = (string) ($assoc['path'] ?? '');
        $batchSize = isset($assoc['batch-size']) ? max(1, (int) $assoc['batch-size']) : 100;
        $limit     = isset($assoc['limit']) ? max(1, (int) $assoc['limit']) : null;
        $dryRun    = isset($assoc['dry-run']);

        $out = $this->ingestion->ingestPath($path, $batchSize, $limit, $dryRun);
        \WP_CLI::line(wp_json_encode($out, JSON_PRETTY_PRINT));
    }

    /**
     * Ingest a seed array JSON file (single file containing array of helmets).
     *
     * ## OPTIONS
     * [--file=<file>]
     * : Absolute or plugin-relative path to the seed JSON file.
     *   Default: seed-data/helmets_seed.json
     * [--batch-size=<n>]
     * : Batch size. Default 25 (smaller batches reduce lock timeouts).
     * [--dry-run]
     * : Validate only, do not write.
     *
     * ## EXAMPLES
     *     wp helmetsan ingest-seed
     *     wp helmetsan ingest-seed --file=/path/to/helmets_seed.json
     *     wp helmetsan ingest-seed --dry-run
     */
    public function ingestSeed(array $args, array $assoc): void
    {
        $file = (string) ($assoc['file'] ?? '');
        if ($file !== '' && ! file_exists($file) && defined('WP_PLUGIN_DIR')) {
            $try = WP_PLUGIN_DIR . '/helmetsan-core/' . ltrim($file, '/');
            if (file_exists($try)) {
                $file = $try;
            }
        }

        $batchSize = isset($assoc['batch-size']) ? max(1, (int) $assoc['batch-size']) : 25;
        $dryRun    = isset($assoc['dry-run']);

        \WP_CLI::log('Seed file: ' . ($file === '' ? '(default)' : $file));
        if ($dryRun) {
            \WP_CLI::log('Mode: DRY RUN (no writes)');
        }
        \WP_CLI::log('Starting ingestion...');

        $result = $this->ingestion->ingestSeedFile($file, $batchSize, $dryRun);

        if (! empty($result['locked'])) {
            \WP_CLI::warning($result['message'] ?? 'Ingestion is already running.');
            return;
        }
        if (empty($result['ok'])) {
            \WP_CLI::error($result['message'] ?? 'Ingestion failed.');
            return;
        }

        $created  = (int) ($result['created'] ?? 0);
        $updated  = (int) ($result['updated'] ?? 0);
        $accepted = (int) ($result['accepted'] ?? 0);
        $rejected = (int) ($result['rejected'] ?? 0);
        $skipped  = (int) ($result['skipped'] ?? 0);

        \WP_CLI::log('');
        \WP_CLI::log('Results:');
        \WP_CLI::log("  Created:  {$created}");
        \WP_CLI::log("  Updated:  {$updated}");
        \WP_CLI::log("  Skipped:  {$skipped}");
        \WP_CLI::log("  Rejected: {$rejected}");

        if ($rejected > 0) {
            \WP_CLI::warning("{$rejected} items were rejected. Check ingestion logs.");
        } else {
            \WP_CLI::success("Ingested {$accepted} helmets from seed ({$created} new, {$updated} updated, {$skipped} unchanged).");
        }
    }

    /**
     * Ingest brand JSON files from data root / brands.
     *
     * ## OPTIONS
     * [--path=<path>]
     * : Relative path under data root. Default: brands
     * [--dry-run]
     * : Validate only, do not write.
     *
     * ## EXAMPLES
     *     wp helmetsan ingest-brands
     *     wp helmetsan ingest-brands --path=brands --dry-run
     */
    public function ingestBrands(array $args, array $assoc): void
    {
        $path   = (string) ($assoc['path'] ?? 'brands');
        $dryRun = isset($assoc['dry-run']);
        $result = $this->sync->ingestBrandsFromPath($path, $dryRun);
        \WP_CLI::line(wp_json_encode($result, JSON_PRETTY_PRINT));
        if (($result['failed'] ?? 0) > 0) {
            \WP_CLI::warning('Some files failed. Check payload (entity: "brand", profile).');
        } else {
            \WP_CLI::success('Brands ingestion finished. Accepted: ' . (string) ($result['accepted'] ?? 0) . ', Skipped: ' . (string) ($result['skipped'] ?? 0) . '.');
        }
    }

    /**
     * Seed accessory_category taxonomy terms so URLs like /accessory-category/bluetooth-headsets/ work.
     *
     * ## EXAMPLES
     *     wp helmetsan seed-accessory-categories
     */
    public function seedAccessoryCategories(array $args, array $assoc): void
    {
        $categories = [
            'Visors & Shields' => 'Premium optical-grade shields, anti-fog inserts, and adaptive tinting solutions.',
            'Communications' => 'Integrated and universal Bluetooth systems, mesh intercoms, and high-fidelity audio.',
            'Bluetooth Headsets' => 'Bluetooth helmet communication systems, headsets, and intercoms.',
            'Mesh Intercoms' => 'Mesh network intercom systems for rider-to-rider and group communication.',
            'Helmet Cameras' => 'Action cameras, dashcams, and mounts for helmet recording.',
            'Audio Kits' => 'Helmet speakers, microphones, and audio upgrade kits.',
            'GPS Navigation' => 'GPS units and mounts for motorcycle navigation.',
            'Smart Helmet Add-ons' => 'Connectivity and smart features for helmets.',
            'Maintenance & Care' => 'Specialized cleaners, anti-microbial treatments, and protective wax for shell longevity.',
            'Electronics' => 'Integrated lighting, backup batteries, and smart dashcam integrations.',
            'Inner Liners' => 'Replacement comfort liners, cheek pads, and moisture-wicking headliners.',
            'Face Shields' => 'Full-face and modular helmet face shields and visors.',
            'Pinlock Inserts' => 'Anti-fog Pinlock lens inserts for visors.',
            'Tear-Offs' => 'Visor tear-off strips for dirt and racing.',
            'Goggles' => 'MX and open-face goggles.',
            'Replacement Lenses' => 'Replacement lenses for visors and goggles.',
            'Anti-Fog Solutions' => 'Anti-fog treatments and inserts.',
            'Sun Visors' => 'Internal sun visors and tinted options.',
            'Cheek Pads' => 'Replacement cheek pads for fit and comfort.',
            'Liners' => 'Comfort liners and headliners.',
            'Helmet Cleaners' => 'Cleaning products for helmet shells and interiors.',
            'Visor Cleaners' => 'Cleaning solutions for visors and lenses.',
            'Helmet Bags' => 'Carry bags and storage for helmets.',
            'Balaclavas' => 'Helmet liners and balaclavas.',
            'Breath Guards' => 'Breath deflectors and guards.',
            'Breath Boxes' => 'Breath box replacements for modular helmets.',
            'Peak Visors' => 'Peak and peak visor replacements.',
            'Replacement Vents' => 'Vent parts and replacements.',
            'Pivot Kits' => 'Visor pivot and mechanism kits.',
            'Chin Curtains' => 'Chin curtain replacements.',
            'Reflective Stickers' => 'Reflective decals and safety stickers.',
        ];

        $created = 0;
        foreach ($categories as $name => $desc) {
            if (! term_exists($name, 'accessory_category')) {
                $result = wp_insert_term($name, 'accessory_category', ['description' => $desc]);
                if (! is_wp_error($result)) {
                    $created++;
                    \WP_CLI::log("Created: {$name}");
                }
            }
        }

        $pageAcc = get_page_by_path('accessories');
        if ($pageAcc instanceof \WP_Post) {
            update_post_meta($pageAcc->ID, '_wp_page_template', 'page-accessories.php');
            \WP_CLI::log('Accessories page template set to page-accessories.php.');
        }

        if ($created > 0) {
            \WP_CLI::success("Created {$created} accessory category terms. Flushing rewrite rules.");
            flush_rewrite_rules(false);
        } else {
            \WP_CLI::success('All accessory categories already exist.');
        }
    }

    /**
     * Backfill accessory_category for all published accessories from their accessory_type meta.
     * Fixes category counts showing 0 when accessories were ingested before type→category mapping.
     *
     * ## OPTIONS
     * [--force]
     * : Re-apply category from type even when the accessory already has category terms.
     *
     * ## EXAMPLES
     *     wp helmetsan backfill-accessory-categories
     *     wp helmetsan backfill-accessory-categories --force
     */
    public function backfillAccessoryCategories(array $args, array $assoc): void
    {
        if (! function_exists('helmetsan_core')) {
            \WP_CLI::error('Plugin not loaded.');
        }
        $accessories = helmetsan_core()->accessories();
        $force = isset($assoc['force']);

        $posts = get_posts([
            'post_type'      => 'accessory',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);
        $updated = 0;
        $skipped = 0;
        $noMap   = 0;

        foreach ($posts as $postId) {
            if (! $force) {
                $terms = wp_get_object_terms($postId, 'accessory_category');
                if (! is_wp_error($terms) && is_array($terms) && $terms !== []) {
                    $skipped++;
                    continue;
                }
            } else {
                wp_set_object_terms($postId, [], 'accessory_category', false);
            }
            $type = (string) get_post_meta($postId, 'accessory_type', true);
            if ($type === '') {
                $noMap++;
                continue;
            }
            $ok = $accessories->assignCategoryFromType($postId);
            if ($ok) {
                $updated++;
                \WP_CLI::log("Assigned category for post {$postId} ({$type})");
            } else {
                $noMap++;
            }
        }

        \WP_CLI::success(sprintf(
            'Backfill complete: %d updated, %d skipped (already had category), %d had no mapping.',
            $updated,
            $skipped,
            $noMap
        ));
    }

    /**
     * ## OPTIONS
     * <action>
     * : push|pull
     * [--limit=<n>]
     * : Max files to process. Default 500.
     * [--dry-run]
     * : Evaluate only, do not write.
     * [--path=<remotePath>]
     * : Override configured GitHub remote path.
     * [--apply-brands]
     * : For pull: auto-apply downloaded brand JSON into WordPress brand records.
     * [--apply-helmets]
     * : For pull: auto-apply downloaded helmet JSON into WordPress helmets.
     * [--profile=<profile>]
     * : pull-only|pull+brands|pull+all. Overrides saved sync profile.
     * [--mode=<mode>]
     * : push mode: commit|pr (only for push).
     * [--pr-title=<title>]
     * : Pull request title when --mode=pr.
     * [--auto-merge]
     * : Auto-merge PR when mode=pr.
     */
    public function sync(array $args, array $assoc): void
    {
        $action = (string) ($args[0] ?? 'pull');
        $limit  = isset($assoc['limit']) ? max(1, (int) $assoc['limit']) : 500;
        $dryRun = isset($assoc['dry-run']);
        $path   = isset($assoc['path']) ? (string) $assoc['path'] : null;
        $applyBrands = array_key_exists('apply-brands', $assoc) ? true : null;
        $applyHelmets = array_key_exists('apply-helmets', $assoc) ? true : null;
        $profile = isset($assoc['profile']) ? (string) $assoc['profile'] : null;
        $mode   = isset($assoc['mode']) ? (string) $assoc['mode'] : null;
        $prTitle= isset($assoc['pr-title']) ? (string) $assoc['pr-title'] : null;
        $autoMerge = isset($assoc['auto-merge']) ? true : null;

        $out = ($action === 'push')
            ? $this->sync->push($limit, $dryRun, $path, $mode, $prTitle, $autoMerge)
            : $this->sync->pull($limit, $dryRun, $path, $applyBrands, $applyHelmets, $profile, [
                'source' => 'wp-cli',
                'trigger_user' => 'wp-cli',
            ]);
        \WP_CLI::line(wp_json_encode($out, JSON_PRETTY_PRINT));
    }

    /**
     * ## OPTIONS
     * <type>
     * : schema|logic|integrity
     * [--file=<file>]
     * : Optional absolute file path for schema/logic checks.
     */
    public function validate(array $args, array $assoc): void
    {
        $type = (string) ($args[0] ?? 'integrity');

        if ($type === 'integrity') {
            \WP_CLI::line(wp_json_encode($this->validator->validateIntegrity(), JSON_PRETTY_PRINT));
            return;
        }

        $file = (string) ($assoc['file'] ?? '');
        if ($file === '' || ! file_exists($file)) {
            \WP_CLI::error('Provide --file for schema/logic validation');
            return;
        }

        $raw  = file_get_contents($file);
        $data = json_decode((string) $raw, true);

        if (! is_array($data)) {
            \WP_CLI::error('Invalid JSON input file');
            return;
        }

        $result = ($type === 'schema') ? $this->validator->validateSchema($data) : $this->validator->validateLogic($data);
        \WP_CLI::line(wp_json_encode($result, JSON_PRETTY_PRINT));
    }

    public function analyticsSmokeTest(array $args, array $assoc): void
    {
        \WP_CLI::line(wp_json_encode($this->smoke->run(), JSON_PRETTY_PRINT));
    }

    public function goLiveChecklist(array $args, array $assoc): void
    {
        $report = $this->checklist->report();
        \WP_CLI::line(wp_json_encode($report, JSON_PRETTY_PRINT));
    }

    public function docsBuildIndex(array $args, array $assoc): void
    {
        $files = $this->docs->listDocs();
        \WP_CLI::line(wp_json_encode(['docs' => $files, 'count' => count($files)], JSON_PRETTY_PRINT));
    }

    /**
     * Retry failed/rejected ingestion logs.
     *
     * ## OPTIONS
     * [--limit=<n>]
     * : Max log rows to evaluate. Default 100.
     * [--batch-size=<n>]
     * : Ingestion batch size. Default 50.
     * [--dry-run]
     * : Validate retry candidates without writing.
     */
    public function retryFailed(array $args, array $assoc): void
    {
        if (! $this->ingestionLogs->tableExists()) {
            \WP_CLI::error('Ingestion log table not found.');
            return;
        }

        $limit     = isset($assoc['limit']) ? max(1, (int) $assoc['limit']) : 100;
        $batchSize = isset($assoc['batch-size']) ? max(1, (int) $assoc['batch-size']) : 50;
        $dryRun    = isset($assoc['dry-run']);

        $failedRows   = $this->ingestionLogs->fetch(1, $limit, 'failed', '');
        $rejectedRows = $this->ingestionLogs->fetch(1, $limit, 'rejected', '');
        $rows         = array_merge($failedRows, $rejectedRows);

        if ($rows === []) {
            \WP_CLI::line(wp_json_encode([
                'ok'      => true,
                'message' => 'No failed/rejected log rows found.',
            ], JSON_PRETTY_PRINT));
            return;
        }

        usort($rows, static function (array $a, array $b): int {
            $idA = isset($a['id']) ? (int) $a['id'] : 0;
            $idB = isset($b['id']) ? (int) $b['id'] : 0;
            return $idB <=> $idA;
        });

        $rows  = array_slice($rows, 0, $limit);
        $files = [];

        foreach ($rows as $row) {
            $file = isset($row['source_file']) ? (string) $row['source_file'] : '';
            if ($file === '' || ! file_exists($file)) {
                continue;
            }
            $files[] = $file;
        }

        $files = array_values(array_unique($files));
        if ($files === []) {
            \WP_CLI::line(wp_json_encode([
                'ok'      => true,
                'message' => 'No retryable source files found for selected logs.',
            ], JSON_PRETTY_PRINT));
            return;
        }

        $result = $this->ingestion->ingestFiles($files, $batchSize, null, $dryRun, 'cli-retry-failed');
        $result['candidate_logs'] = count($rows);
        $result['retry_files']    = count($files);

        \WP_CLI::line(wp_json_encode($result, JSON_PRETTY_PRINT));
    }

    /**
     * Import JSON data into helmets.
     *
     * ## OPTIONS
     * --file=<file>
     * : Absolute path to JSON file.
     * [--batch-size=<n>]
     * : Batch size for ingestion.
     * [--dry-run]
     * : Validate only.
     */
    public function importData(array $args, array $assoc): void
    {
        $file = (string) ($assoc['file'] ?? '');
        if ($file === '') {
            \WP_CLI::error('Provide --file=<absolute-path-to-json>');
            return;
        }

        $batchSize = isset($assoc['batch-size']) ? max(1, (int) $assoc['batch-size']) : 100;
        $dryRun    = isset($assoc['dry-run']);

        $result = $this->importService->importJsonFile($file, $dryRun, $batchSize);
        \WP_CLI::line(wp_json_encode($result, JSON_PRETTY_PRINT));
    }

    /**
     * Export helmet data to JSON.
     *
     * ## OPTIONS
     * --post-id=<id>
     * : Post ID.
     * [--entity=<entity>]
     * : helmet|brand. Default helmet.
     * [--out=<file>]
     * : Optional output file path.
     */
    public function exportData(array $args, array $assoc): void
    {
        $postId = isset($assoc['post-id']) ? (int) $assoc['post-id'] : 0;
        if ($postId <= 0) {
            \WP_CLI::error('Provide --post-id=<helmet-post-id>');
            return;
        }

        $entity = isset($assoc['entity']) ? sanitize_key((string) $assoc['entity']) : 'helmet';
        $entity = in_array($entity, ['helmet', 'brand'], true) ? $entity : 'helmet';
        $out = isset($assoc['out']) ? (string) $assoc['out'] : null;
        $result = $this->exportService->exportByPostId($postId, $entity, $out);
        \WP_CLI::line(wp_json_encode($result, JSON_PRETTY_PRINT));
    }

    public function unlockIngestion(array $args, array $assoc): void
    {
        $wasActive = $this->ingestion->lockActive();
        $this->ingestion->forceUnlock();

        \WP_CLI::line(wp_json_encode([
            'ok'         => true,
            'was_locked' => $wasActive,
            'message'    => 'Ingestion lock cleared.',
        ], JSON_PRETTY_PRINT));
    }

    /**
     * Inspect sync logs from CLI.
     *
     * ## OPTIONS
     * [--status=<status>]
     * : Filter by status (success|partial|error|info|all).
     * [--action=<action>]
     * : Filter by action (pull|push|all).
     * [--search=<text>]
     * : Search in branch/target/path/message.
     * [--limit=<n>]
     * : Rows per page (default 20).
     * [--page=<n>]
     * : Page number (default 1).
     * [--tail]
     * : Shorthand for latest rows (page=1).
     * [--id=<id>]
     * : Show full log row and payload for one log id.
     * [--format=<format>]
     * : json|table (default json).
     */
    public function syncLogs(array $args, array $assoc): void
    {
        if (! $this->syncLogs->tableExists()) {
            \WP_CLI::error('Sync log table not found.');
            return;
        }

        $format = (string) ($assoc['format'] ?? 'json');
        $id     = isset($assoc['id']) ? (int) $assoc['id'] : 0;

        if ($id > 0) {
            $row = $this->syncLogs->findById($id);
            if (! is_array($row)) {
                \WP_CLI::error('Sync log entry not found for id=' . (string) $id);
                return;
            }
            \WP_CLI::line(wp_json_encode($row, JSON_PRETTY_PRINT));
            return;
        }

        $status = isset($assoc['status']) ? (string) $assoc['status'] : 'all';
        $action = isset($assoc['action']) ? (string) $assoc['action'] : 'all';
        $search = isset($assoc['search']) ? (string) $assoc['search'] : '';
        $limit  = isset($assoc['limit']) ? max(1, (int) $assoc['limit']) : 20;
        $page   = isset($assoc['page']) ? max(1, (int) $assoc['page']) : 1;

        if (isset($assoc['tail'])) {
            $page = 1;
        }

        $total = $this->syncLogs->count($status, $action, $search);
        $rows  = $this->syncLogs->fetch($page, $limit, $status, $action, $search);

        if ($format === 'table') {
            $tableRows = [];
            foreach ($rows as $row) {
                $tableRows[] = [
                    'id'            => isset($row['id']) ? (string) $row['id'] : '',
                    'created_at'    => isset($row['created_at']) ? (string) $row['created_at'] : '',
                    'action'        => isset($row['action']) ? (string) $row['action'] : '',
                    'mode'          => isset($row['mode']) ? (string) $row['mode'] : '',
                    'status'        => isset($row['status']) ? (string) $row['status'] : '',
                    'branch'        => isset($row['branch']) ? (string) $row['branch'] : '',
                    'target_branch' => isset($row['target_branch']) ? (string) $row['target_branch'] : '',
                    'processed'     => isset($row['processed']) ? (string) $row['processed'] : '0',
                    'pushed'        => isset($row['pushed']) ? (string) $row['pushed'] : '0',
                    'skipped'       => isset($row['skipped']) ? (string) $row['skipped'] : '0',
                    'failed'        => isset($row['failed']) ? (string) $row['failed'] : '0',
                ];
            }
            \WP_CLI\Utils\format_items('table', $tableRows, [
                'id',
                'created_at',
                'action',
                'mode',
                'status',
                'branch',
                'target_branch',
                'processed',
                'pushed',
                'skipped',
                'failed',
            ]);
            \WP_CLI::line('total=' . (string) $total . ' page=' . (string) $page . ' limit=' . (string) $limit);
            return;
        }

        \WP_CLI::line(wp_json_encode([
            'ok'      => true,
            'total'   => $total,
            'page'    => $page,
            'limit'   => $limit,
            'status'  => $status,
            'action'  => $action,
            'search'  => $search,
            'rows'    => $rows,
        ], JSON_PRETTY_PRINT));
    }

    /**
     * Cleanup old sync logs.
     *
     * ## OPTIONS
     * [--days=<n>]
     * : Delete logs older than N days. Default 30.
     * [--status=<status>]
     * : Optional status filter.
     */
    public function cleanupSyncLogs(array $args, array $assoc): void
    {
        if (! $this->syncLogs->tableExists()) {
            \WP_CLI::error('Sync log table not found.');
            return;
        }

        $days   = isset($assoc['days']) ? max(1, (int) $assoc['days']) : 30;
        $status = isset($assoc['status']) ? (string) $assoc['status'] : null;

        $deleted = $this->syncLogs->cleanupOlderThanDays($days, $status);
        \WP_CLI::line(wp_json_encode([
            'ok'      => true,
            'table'   => 'sync_logs',
            'days'    => $days,
            'status'  => $status ?? 'all',
            'deleted' => $deleted,
        ], JSON_PRETTY_PRINT));
    }

    /**
     * Cleanup old ingestion logs.
     *
     * ## OPTIONS
     * [--days=<n>]
     * : Delete logs older than N days. Default 30.
     * [--status=<status>]
     * : Optional status filter.
     */
    public function cleanupIngestionLogs(array $args, array $assoc): void
    {
        if (! $this->ingestionLogs->tableExists()) {
            \WP_CLI::error('Ingestion log table not found.');
            return;
        }

        $days   = isset($assoc['days']) ? max(1, (int) $assoc['days']) : 30;
        $status = isset($assoc['status']) ? (string) $assoc['status'] : null;

        $deleted = $this->ingestionLogs->cleanupOlderThanDays($days, $status);
        \WP_CLI::line(wp_json_encode([
            'ok'      => true,
            'table'   => 'ingestion_logs',
            'days'    => $days,
            'status'  => $status ?? 'all',
            'deleted' => $deleted,
        ], JSON_PRETTY_PRINT));
    }

    /**
     * Check helmet schema completeness.
     *
     * ## OPTIONS
     * [--limit=<n>]
     * : Number of posts to inspect. Default 200.
     * [--offset=<n>]
     * : Offset for pagination. Default 0.
     * [--format=<format>]
     * : json|table (default json).
     */
    public function schemaCheck(array $args, array $assoc): void
    {
        $limit  = isset($assoc['limit']) ? max(1, (int) $assoc['limit']) : 200;
        $offset = isset($assoc['offset']) ? max(0, (int) $assoc['offset']) : 0;
        $format = (string) ($assoc['format'] ?? 'json');

        $report = $this->schema->audit($limit, $offset);

        if ($format === 'table') {
            $rows = [];
            foreach ($report['issues'] as $issue) {
                if (! is_array($issue)) {
                    continue;
                }
                $rows[] = [
                    'post_id' => isset($issue['post_id']) ? (string) $issue['post_id'] : '',
                    'title'   => isset($issue['title']) ? (string) $issue['title'] : '',
                    'missing' => isset($issue['missing']) && is_array($issue['missing']) ? implode(',', $issue['missing']) : '',
                ];
            }

            if ($rows === []) {
                \WP_CLI::line('No schema issues found.');
            } else {
                \WP_CLI\Utils\format_items('table', $rows, ['post_id', 'title', 'missing']);
            }
            \WP_CLI::line('checked=' . (string) $report['checked'] . ' valid=' . (string) $report['valid'] . ' invalid=' . (string) $report['invalid']);
            return;
        }

        \WP_CLI::line(wp_json_encode($report, JSON_PRETTY_PRINT));
    }

    /**
     * Seed Yoast SEO title, meta description and focus keyword for helmets, brands, accessories.
     *
     * ## OPTIONS
     * [--post-type=<type>]
     * : helmet|brand|accessory|all. Default: all
     * [--batch-size=<n>]
     * : Number of posts per batch. Default: 300
     * [--limit=<n>]
     * : Max posts to process per type (0 = no limit). Default: 0
     * [--dry-run]
     * : Do not save; only report counts
     * [--use-ai]
     * : Use Groq + Gemini (load-balanced) for meta descriptions. Set HELMETSAN_GROQ_API_KEY and HELMETSAN_GEMINI_API_KEY in wp-config.php. Free tier only.
     *
     * ## EXAMPLES
     *     wp helmetsan seo seed --post-type=helmet
     *     wp helmetsan seo seed --post-type=all --batch-size=500
     *     wp helmetsan seo seed --dry-run
     *     wp helmetsan seo seed --use-ai --post-type=helmet --limit=100
     */
    public function seoSeed(array $args, array $assoc): void
    {
        $postType = (string) ($assoc['post-type'] ?? 'all');
        $batchSize = isset($assoc['batch-size']) ? max(1, (int) $assoc['batch-size']) : 300;
        $limit = isset($assoc['limit']) ? max(0, (int) $assoc['limit']) : 0;
        $dryRun = isset($assoc['dry-run']);
        $useAi = isset($assoc['use-ai']);

        $allowed = ['helmet', 'brand', 'accessory', 'all'];
        if (! in_array($postType, $allowed, true)) {
            \WP_CLI::error('Invalid --post-type. Use: helmet, brand, accessory, or all.');
            return;
        }

        $aiProvider = null;
        if ($useAi) {
            $aiProvider = new AiSeoDescriptionProvider($this->aiService);
            if (! $aiProvider->hasAnyKey()) {
                \WP_CLI::error('--use-ai requires API keys. Set HELMETSAN_GROQ_API_KEY and/or HELMETSAN_GEMINI_API_KEY in wp-config.php (or env).');
                return;
            }
            \WP_CLI::log('Using AI (Groq + Gemini, load-balanced) for meta descriptions. Free tier only.');
        }

        $seeder = new YoastSeoSeeder($aiProvider);
        $types = $postType === 'all' ? ['helmet', 'brand', 'accessory'] : [$postType];
        $totalUpdated = 0;
        $startTime = microtime(true);

        foreach ($types as $type) {
            $offset = 0;
            $remaining = $limit > 0 ? $limit : null;
            $typeTotal = 0;

            while (true) {
                $batchLimit = $remaining !== null ? min($batchSize, $remaining) : $batchSize;
                if ($batchLimit < 1) {
                    break;
                }

                $result = $type === 'helmet'
                    ? $seeder->seedHelmets($batchLimit, $offset, $dryRun)
                    : ($type === 'brand'
                        ? $seeder->seedBrands($batchLimit, $offset, $dryRun)
                        : $seeder->seedAccessories($batchLimit, $offset, $dryRun));

                $updated = (int) ($result['updated'] ?? 0);
                $typeTotal += $updated;
                $totalUpdated += $updated;

                if ($updated > 0) {
                    \WP_CLI::log(sprintf('[%s] %s %d (offset %d, total %d)', $type, $dryRun ? 'Would update' : 'Updated', $updated, $offset, $typeTotal));
                }

                if ($updated < $batchLimit) {
                    break;
                }
                $offset += $updated;
                if ($remaining !== null) {
                    $remaining -= $updated;
                    if ($remaining <= 0) {
                        break;
                    }
                }
            }

            if ($typeTotal > 0) {
                $label = $type === 'accessory' ? 'Accessories' : (ucfirst($type) . 's');
                \WP_CLI::log(sprintf('%s: %d done.', $label, $typeTotal));
            }
        }

        $elapsed = round(microtime(true) - $startTime, 1);
        \WP_CLI::success(sprintf('SEO seed complete. Total %s: %d in %s s', $dryRun ? 'would update' : 'updated', $totalUpdated, $elapsed));
    }

    /**
     * Phase 2: Fill missing entity fields using AI (context-aware).
     *
     * ## OPTIONS
     * [--post-type=<type>]
     * : helmet|brand|accessory|all. Default: all
     * [--limit=<n>]
     * : Max posts to process per type (0 = no limit). Default: 50
     * [--offset=<n>]
     * : Offset for pagination. Default: 0
     * [--dry-run]
     * : Do not save; only report counts
     * [--fields=<keys>]
     * : Comma-separated meta keys to fill (e.g. head_shape,technical_analysis). If omitted, all fillable fields are used.
     * [--only-incomplete]
     * : Only process posts that have at least one empty fillable field.
     * [--verbose]
     * : Log each filled field and each failure (post ID, meta key, value or reason).
     * [--strict]
     * : On empty or invalid AI output, leave field empty and do not retry (saves API calls).
     * [--no-cache]
     * : Disable 24h cache for identical context (more API calls).
     *
     * ## EXAMPLES
     *     wp helmetsan ai fill-missing --post-type=helmet --limit=10
     *     wp helmetsan ai fill-missing --post-type=helmet --fields=head_shape,spec_shell_material
     *     wp helmetsan ai fill-missing --dry-run --verbose
     *     wp helmetsan ai fill-missing --only-incomplete --strict
     */
    public function aiFillMissing(array $args, array $assoc): void
    {
        if ($this->aiService === null || ! $this->aiService->hasAnyConfiguredProvider()) {
            \WP_CLI::error('AI module has no configured providers. Configure at least one under Helmetsan → AI.');
            return;
        }
        $postType = (string) ($assoc['post-type'] ?? 'all');
        $limit = isset($assoc['limit']) ? max(0, (int) $assoc['limit']) : 50;
        $offset = isset($assoc['offset']) ? max(0, (int) $assoc['offset']) : 0;
        $dryRun = isset($assoc['dry-run']);
        $onlyIncomplete = isset($assoc['only-incomplete']);
        $verbose = isset($assoc['verbose']);
        $strictMode = isset($assoc['strict']);
        $noCache = isset($assoc['no-cache']);
        $cacheTtl = $noCache ? 0 : 86400;
        $fieldsOpt = isset($assoc['fields']) ? trim((string) $assoc['fields']) : '';
        $onlyFields = $fieldsOpt !== '' ? array_map('trim', array_filter(explode(',', $fieldsOpt))) : null;
        $allowed = ['helmet', 'brand', 'accessory', 'all'];
        if (! in_array($postType, $allowed, true)) {
            \WP_CLI::error('Invalid --post-type. Use: helmet, brand, accessory, or all.');
            return;
        }
        $types = $postType === 'all' ? ['helmet', 'brand', 'accessory'] : [$postType];
        if ($onlyFields !== null) {
            foreach ($types as $type) {
                $fillableKeys = FillMissingService::getFillableKeys($type);
                $unknown = array_diff($onlyFields, $fillableKeys);
                if ($unknown !== []) {
                    \WP_CLI::error(sprintf('Invalid --fields for %s: %s. Allowed: %s', $type, implode(', ', $unknown), implode(', ', $fillableKeys)));
                    return;
                }
            }
        }
        $fillService = new FillMissingService($this->aiService);
        $totalFilled = 0;
        $totalSkipped = 0;
        $totalErrors = 0;
        $totalApiCalls = 0;
        $startTime = microtime(true);
        foreach ($types as $type) {
            $onProgress = static function (int $processed, int $total, int $postId) use ($type): void {
                if ($total > 5 && $processed % max(1, (int) ($total / 10)) === 0) {
                    \WP_CLI::log(sprintf('[%s] Progress: %d / %d posts (post ID %d)', $type, $processed, $total, $postId));
                }
            };
            $onVerbose = $verbose ? static function (string $verbType, int $postId, string $metaKey, ?string $detail) use ($type): void {
                if ($verbType === 'filled') {
                    \WP_CLI::log(sprintf('[%s] Filled post %d %s = %s', $type, $postId, $metaKey, $detail !== null ? substr($detail, 0, 60) . (strlen($detail) > 60 ? '…' : '') : ''));
                } elseif ($verbType === 'error') {
                    \WP_CLI::log(sprintf('[%s] Error post %d %s: %s', $type, $postId, $metaKey, $detail ?? ''));
                }
            } : null;
            $result = $fillService->run($type, $limit, $offset, $dryRun, $onlyFields, $onlyIncomplete, $strictMode, $onProgress, $onVerbose, $cacheTtl);
            $filled = (int) ($result['filled'] ?? 0);
            $skipped = (int) ($result['skipped'] ?? 0);
            $errors = (int) ($result['errors'] ?? 0);
            $apiCalls = (int) ($result['api_calls'] ?? 0);
            $totalFilled += $filled;
            $totalSkipped += $skipped;
            $totalErrors += $errors;
            $totalApiCalls += $apiCalls;
            if ($result['total_posts'] > 0 || $filled > 0 || $skipped > 0 || $errors > 0) {
                \WP_CLI::log(sprintf('[%s] posts=%d filled=%d skipped=%d errors=%d api_calls=%d', $type, (int) ($result['total_posts'] ?? 0), $filled, $skipped, $errors, $apiCalls));
            }
        }
        $elapsed = round(microtime(true) - $startTime, 1);
        \WP_CLI::success(sprintf('Fill missing complete. Filled: %d, skipped: %d, errors: %d, API calls: %d in %s s', $totalFilled, $totalSkipped, $totalErrors, $totalApiCalls, $elapsed));
    }

    /**
     * Revenue click report.
     *
     * ## OPTIONS
     * [--days=<n>]
     * : Report window in days. Default 30.
     * [--format=<format>]
     * : json|table (default json).
     */
    public function revenueReport(array $args, array $assoc): void
    {
        $days = isset($assoc['days']) ? max(1, (int) $assoc['days']) : 30;
        $format = (string) ($assoc['format'] ?? 'json');
        $report = $this->revenue->report($days);

        if (! ($report['ok'] ?? false)) {
            \WP_CLI::error((string) ($report['message'] ?? 'Revenue report failed'));
            return;
        }

        if ($format === 'table') {
            $top = isset($report['top_helmets']) && is_array($report['top_helmets']) ? $report['top_helmets'] : [];
            $rows = [];
            foreach ($top as $row) {
                if (! is_array($row)) {
                    continue;
                }
                $rows[] = [
                    'helmet_id' => isset($row['helmet_id']) ? (string) $row['helmet_id'] : '',
                    'title'     => isset($row['title']) ? (string) $row['title'] : '',
                    'clicks'    => isset($row['clicks']) ? (string) $row['clicks'] : '0',
                ];
            }
            if ($rows !== []) {
                \WP_CLI\Utils\format_items('table', $rows, ['helmet_id', 'title', 'clicks']);
            }
            \WP_CLI::line('days=' . (string) $days . ' total_clicks=' . (string) ($report['total_clicks'] ?? 0));
            return;
        }

        \WP_CLI::line(wp_json_encode($report, JSON_PRETTY_PRINT));
    }

    /**
     * Analytics event report.
     *
     * ## OPTIONS
     * [--days=<n>]
     * : Report window in days. Default 7.
     * [--format=<format>]
     * : json|table (default json).
     */
    public function analyticsReport(array $args, array $assoc): void
    {
        if (! $this->analyticsEvents->tableExists()) {
            \WP_CLI::error('Analytics events table not found.');
            return;
        }

        $days = isset($assoc['days']) ? max(1, (int) $assoc['days']) : 7;
        $format = (string) ($assoc['format'] ?? 'json');
        $total = $this->analyticsEvents->total($days);
        $byEvent = $this->analyticsEvents->countByEvent($days);

        if ($format === 'table') {
            $rows = [];
            foreach ($byEvent as $name => $count) {
                $rows[] = ['event' => (string) $name, 'count' => (string) $count];
            }
            if ($rows !== []) {
                \WP_CLI\Utils\format_items('table', $rows, ['event', 'count']);
            }
            \WP_CLI::line('days=' . (string) $days . ' total_events=' . (string) $total);
            return;
        }

        \WP_CLI::line(wp_json_encode([
            'ok'           => true,
            'days'         => $days,
            'total_events' => $total,
            'by_event'     => $byEvent,
        ], JSON_PRETTY_PRINT));
    }

    /**
     * Sync helmets to WooCommerce product/variations.
     *
     * ## OPTIONS
     * [--helmet-id=<id>]
     * : Sync a single helmet post id.
     * [--limit=<n>]
     * : Batch size when helmet-id is not provided. Default 100.
     * [--dry-run]
     * : Validate/match only without persisting.
     */
    public function wooBridgeSync(array $args, array $assoc): void
    {
        $helmetId = isset($assoc['helmet-id']) ? max(0, (int) $assoc['helmet-id']) : 0;
        $limit = isset($assoc['limit']) ? max(1, (int) $assoc['limit']) : 100;
        $dryRun = isset($assoc['dry-run']);

        $result = $helmetId > 0
            ? $this->wooBridge->syncHelmet($helmetId, $dryRun)
            : $this->wooBridge->syncBatch($limit, $dryRun);

        \WP_CLI::line(wp_json_encode($result, JSON_PRETTY_PRINT));
    }

    public function schedulerStatus(array $args, array $assoc): void
    {
        \WP_CLI::line(wp_json_encode($this->scheduler->status(), JSON_PRETTY_PRINT));
    }

    /**
     * ## OPTIONS
     * --task=<task>
     * : sync_pull|retry_failed|cleanup_logs|health_snapshot
     */
    public function schedulerRun(array $args, array $assoc): void
    {
        $task = isset($assoc['task']) ? (string) $assoc['task'] : '';
        if ($task === '') {
            \WP_CLI::error('Provide --task=<sync_pull|retry_failed|cleanup_logs|health_snapshot>');
            return;
        }

        $result = $this->scheduler->runTask($task);
        \WP_CLI::line(wp_json_encode($result, JSON_PRETTY_PRINT));
    }

    /**
     * ## OPTIONS
     * [--title=<title>]
     * : Optional alert title.
     * [--message=<message>]
     * : Optional alert message.
     */
    public function alertsTest(array $args, array $assoc): void
    {
        $title = isset($assoc['title']) ? (string) $assoc['title'] : 'Helmetsan test alert';
        $message = isset($assoc['message']) ? (string) $assoc['message'] : 'Manual test alert from CLI';
        $result = $this->alerts->send('info', $title, $message, [
            'source' => 'wp-cli',
            'timestamp' => current_time('mysql'),
        ]);

        \WP_CLI::line(wp_json_encode($result, JSON_PRETTY_PRINT));
    }

    /**
     * ## OPTIONS
     * [--brand-id=<id>]
     * : Single brand post ID to cascade.
     * [--all]
     * : Cascade all brands.
     */
    public function brandCascade(array $args, array $assoc): void
    {
        $runAll = isset($assoc['all']);
        $brandId = isset($assoc['brand-id']) ? (int) $assoc['brand-id'] : 0;

        if (! $runAll && $brandId <= 0) {
            \WP_CLI::error('Provide --brand-id=<id> or --all');
            return;
        }

        $totalUpdated = 0;
        $results = [];

        if ($runAll) {
            $rows = $this->brands->listBrandOverview();
            foreach ($rows as $row) {
                $id = isset($row['id']) ? (int) $row['id'] : 0;
                if ($id <= 0) {
                    continue;
                }
                $result = $this->brands->cascadeToHelmets($id, 'cli-cascade-all');
                $results[] = $result;
                if (! empty($result['ok'])) {
                    $totalUpdated += (int) ($result['updated_helmets'] ?? 0);
                }
            }
        } else {
            $result = $this->brands->cascadeToHelmets($brandId, 'cli-cascade-one');
            $results[] = $result;
            if (! empty($result['ok'])) {
                $totalUpdated += (int) ($result['updated_helmets'] ?? 0);
            }
        }

        \WP_CLI::line(wp_json_encode([
            'ok' => true,
            'total_updated_helmets' => $totalUpdated,
            'runs' => $results,
        ], JSON_PRETTY_PRINT));
    }

    /**
     * Backfill brand logos into Media Library and bind them on brand posts.
     *
     * ## OPTIONS
     * [--limit=<n>]
     * : Process first N brand posts.
     * [--force]
     * : Re-import and overwrite even if logo meta already exists.
     * [--dry-run]
     * : Simulate only.
     */
    public function mediaBackfillBrandLogos(array $args, array $assoc): void
    {
        $limit = isset($assoc['limit']) ? max(1, (int) $assoc['limit']) : 0;
        $force = isset($assoc['force']);
        $dryRun = isset($assoc['dry-run']);

        $result = $this->media->backfillBrandLogos($limit, $force, $dryRun);
        \WP_CLI::line(wp_json_encode($result, JSON_PRETTY_PRINT));
    }

    /**
     * Import affiliate links from JSON.
     *
     * JSON format:
     * {
     *   "helmet_id_or_sku": {
     *     "network_key": { "network": "...", "url": "...", "tag": "..." }
     *   }
     * }
     *
     * ## OPTIONS
     * --file=<file>
     * : JSON file path.
     */
    public function revenueImportLinks(array $args, array $assoc): void
    {
        $file = (string) ($assoc['file'] ?? '');
        if (!file_exists($file)) {
            \WP_CLI::error("File not found: $file");
            return;
        }

        $data = json_decode(file_get_contents($file), true);
        if (!is_array($data)) {
            \WP_CLI::error("Invalid JSON.");
            return;
        }

        $updated = 0;
        foreach ($data as $id => $links) {
            $postId = 0;
            if (is_numeric($id)) {
                $postId = (int)$id;
            } else {
                // Try SKU lookup
                $posts = get_posts([
                    'post_type' => 'helmet',
                    'meta_key' => 'sku',
                    'meta_value' => $id,
                    'posts_per_page' => 1,
                    'fields' => 'ids'
                ]);
                if (!empty($posts)) {
                    $postId = $posts[0];
                }
            }

            if ($postId > 0) {
                update_post_meta($postId, 'affiliate_links_json', wp_json_encode($links));
                $updated++;
            }
        }

        \WP_CLI::success("Updated links for $updated helmets.");
    }

    /**
     * Seed random price history for testing.
     *
     * ## OPTIONS
     * [--days=<n>]
     * : Days of history. Default 90.
     * [--helmet-id=<id>]
     * : Specific helmet ID.
     */
    public function priceSeedHistory(array $args, array $assoc): void
    {
        $days = isset($assoc['days']) ? (int)$assoc['days'] : 90;
        $helmetId = isset($assoc['helmet-id']) ? (int)$assoc['helmet-id'] : 0;

        $helmets = $helmetId > 0 ? [$helmetId] : get_posts(['post_type' => 'helmet', 'posts_per_page' => -1, 'fields' => 'ids']);

        $count = 0;
        foreach ($helmets as $id) {
            $basePrice = (float)get_post_meta($id, 'price_retail_usd', true);
            if ($basePrice <= 0) $basePrice = 200.0;

            for ($d = $days; $d >= 0; $d--) {
                $date = date('Y-m-d H:i:s', strtotime("-$d days"));
                
                // Simulate fluctuation
                $price = $basePrice * (1 + (rand(-10, 10) / 100));
                
                $this->priceHistory->record($id, 'amazon-us', 'US', 'USD', $price, $date);
                
                // Sometimes add a second marketplace
                if (rand(0, 1)) {
                     $price2 = $basePrice * (1 + (rand(-15, 5) / 100));
                     $this->priceHistory->record($id, 'revzilla', 'US', 'USD', $price2, $date);
                }
            }
            $count++;
            \WP_CLI::line("Seeded history for helmet $id");
        }
        
        \WP_CLI::success("Seeded history for $count helmets.");
    }

    private function renderAssoc(array $assoc, string $format): void
    {
        if ($format === 'table') {
            $rows = [];
            foreach ($assoc as $key => $value) {
                $rows[] = [
                    'key'   => (string) $key,
                    'value' => is_scalar($value) ? (string) $value : wp_json_encode($value),
                ];
            }
            \WP_CLI\Utils\format_items('table', $rows, ['key', 'value']);
            return;
        }

        \WP_CLI::line(wp_json_encode($assoc, JSON_PRETTY_PRINT));
    }
}
