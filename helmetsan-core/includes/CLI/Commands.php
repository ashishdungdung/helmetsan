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
use Helmetsan\Core\Media\HelmetImageEnrichmentService;
use Helmetsan\Core\Media\MediaEngine;
use Helmetsan\Core\Revenue\RevenueService;
use Helmetsan\Core\Scheduler\SchedulerService;
use Helmetsan\Core\Seed\Seeder;
use Helmetsan\Core\AI\AiService;
use Helmetsan\Core\AI\FillableFieldsConfig;
use Helmetsan\Core\AI\FillMissingService;
use Helmetsan\Core\AI\AccessoryGeneratorService;
use Helmetsan\Core\AI\SeedGeneratorService;
use Helmetsan\Core\CrossLink\CrossLinkService;
use Helmetsan\Core\Seo\AiSeoDescriptionProvider;
use Helmetsan\Core\Seo\SchemaService;
use Helmetsan\Core\Seo\YoastSeoSeeder;
use Helmetsan\Core\Data\DuplicateCheckerService;
use Helmetsan\Core\Repository\JsonRepository;
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
        private readonly ?AiService $aiService = null,
        private readonly ?JsonRepository $repository = null,
        private readonly ?SeedGeneratorService $seedGenerator = null,
        private readonly ?AccessoryGeneratorService $accessoryGenerator = null,
        private readonly ?\Helmetsan\Core\Support\TaskTracker $taskTracker = null
    ) {
    }

    public function register(): void
    {
        \WP_CLI::add_command('helmetsan health', [$this, 'health']);
        \WP_CLI::add_command('helmetsan api-check', [$this, 'apiCheck']);
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
        \WP_CLI::add_command('helmetsan seo check', [$this, 'seoCheck']);
        \WP_CLI::add_command('helmetsan seo update', [$this, 'seoUpdate']);
        \WP_CLI::add_command('helmetsan ai fill-missing', [$this, 'aiFillMissing']);
        \WP_CLI::add_command('helmetsan ai status', [$this, 'aiStatus']);
        \WP_CLI::add_command('helmetsan ai generate-seed', [$this, 'aiGenerateSeed']);
        \WP_CLI::add_command('helmetsan ai generate-accessories', [$this, 'aiGenerateAccessories']);
        \WP_CLI::add_command('helmetsan ai generate-all', [$this, 'aiGenerateAll']);
        \WP_CLI::add_command('helmetsan ai cross-link', [$this, 'aiCrossLink']);
        \WP_CLI::add_command('helmetsan revenue report', [$this, 'revenueReport']);
        \WP_CLI::add_command('helmetsan analytics report', [$this, 'analyticsReport']);
        \WP_CLI::add_command('helmetsan scheduler status', [$this, 'schedulerStatus']);
        \WP_CLI::add_command('helmetsan scheduler run', [$this, 'schedulerRun']);
        \WP_CLI::add_command('helmetsan alerts test', [$this, 'alertsTest']);
        \WP_CLI::add_command('helmetsan brand cascade', [$this, 'brandCascade']);
        \WP_CLI::add_command('helmetsan media backfill-brand-logos', [$this, 'mediaBackfillBrandLogos']);
        \WP_CLI::add_command('helmetsan helmet-images', [$this, 'helmetImages']);
        \WP_CLI::add_command('helmetsan woo-bridge sync', [$this, 'wooBridgeSync']);

        \WP_CLI::add_command('helmetsan data check-duplicates', [$this, 'dataCheckDuplicates']);
        \WP_CLI::add_command('helmetsan data fix-duplicates', [$this, 'dataFixDuplicates']);
        \WP_CLI::add_command('helmetsan ingest-seed', [$this, 'ingestSeed']);
        \WP_CLI::add_command('helmetsan ingest-brands', [$this, 'ingestBrands']);
        \WP_CLI::add_command('helmetsan seed-accessory-categories', [$this, 'seedAccessoryCategories']);
        \WP_CLI::add_command('helmetsan backfill-accessory-categories', [$this, 'backfillAccessoryCategories']);
        \WP_CLI::add_command('helmetsan list-unmapped-accessory-meta', [$this, 'listUnmappedAccessoryMeta']);
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
     * Check API connectivity: AI providers and marketplace connectors.
     *
     * ## OPTIONS
     * [--live]
     * : Run a live AI request (one short prompt) to verify provider responds.
     * [--format=<format>]
     * : table|json. Default: table.
     *
     * ## EXAMPLES
     *     wp helmetsan api-check
     *     wp helmetsan api-check --live --format=json
     */
    public function apiCheck(array $args, array $assoc): void
    {
        $report = $this->health->report();
        $api = $report['api'] ?? ['ai' => ['configured' => false], 'marketplace' => []];
        $live = isset($assoc['live']);
        $format = (string) ($assoc['format'] ?? 'table');

        if ($live && $this->aiService !== null && ($api['ai']['configured'] ?? false)) {
            $result = $this->aiService->generateWithProviderId('Reply with exactly: OK', 1, ['max_tokens' => 5]);
            $api['ai']['live_ok'] = $result !== null;
            $api['ai']['live_provider_id'] = $result['provider_id'] ?? null;
            $api['ai']['live_message'] = $result !== null ? 'Provider responded' : 'No response or error';
        }

        if ($format === 'json') {
            \WP_CLI::line(wp_json_encode($api, JSON_PRETTY_PRINT));
            return;
        }
        $providerIds = $api['ai']['provider_ids'] ?? [];
        $providerList = $providerIds !== [] ? ' (' . implode(', ', $providerIds) . ')' : '';
        \WP_CLI::log('AI: ' . (($api['ai']['configured'] ?? false) ? 'configured' . $providerList : 'not configured'));
        if (isset($api['ai']['live_ok'])) {
            $liveLine = 'AI live: ' . ($api['ai']['live_ok'] ? 'OK' : 'failed');
            if (! empty($api['ai']['live_provider_id'])) {
                $liveLine .= ' (provider: ' . $api['ai']['live_provider_id'] . ')';
            } elseif (isset($api['ai']['live_message'])) {
                $liveLine .= ' (' . $api['ai']['live_message'] . ')';
            }
            \WP_CLI::log($liveLine);
        }
        foreach ($api['marketplace'] ?? [] as $id => $ok) {
            \WP_CLI::log('Marketplace ' . $id . ': ' . ($ok ? 'OK' : 'failed'));
        }
        \WP_CLI::success('API check complete.');
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
     * : Process only first N files (after offset). Omit for no limit.
     * [--offset=<n>]
     * : Skip first N files. Used with --concurrency for parallel runs.
     * [--concurrency=<n>]
     * : Run N parallel ingest processes (chunk by file list). Ignores lock; use only for CLI chunking. Default: 1.
     * [--dry-run]
     * : Validate only, do not write.
     */
    public function ingest(array $args, array $assoc): void
    {
        $path         = (string) ($assoc['path'] ?? '');
        $batchSize    = isset($assoc['batch-size']) ? max(1, (int) $assoc['batch-size']) : 100;
        $limit        = isset($assoc['limit']) ? max(0, (int) $assoc['limit']) : null;
        $limit        = $limit === 0 ? null : $limit;
        $offset       = isset($assoc['offset']) ? max(0, (int) $assoc['offset']) : 0;
        $concurrency  = isset($assoc['concurrency']) ? max(1, min(16, (int) $assoc['concurrency'])) : 1;
        $dryRun       = isset($assoc['dry-run']);

        if ($concurrency > 1 && $path !== '') {
            $files = $this->ingestion->listJsonFiles($path);
            $total = count($files);
            if ($total === 0) {
                \WP_CLI::warning('No JSON files found at path: ' . $path);
                return;
            }
            $effectiveTotal = $limit !== null ? min($total, $limit) : $total;
            $chunkSize      = (int) ceil($effectiveTotal / $concurrency);
            $chunkSize      = max(1, $chunkSize);
            $procs          = [];
            for ($i = 0; $i < $concurrency; $i++) {
                $off = $i * $chunkSize;
                if ($off >= $effectiveTotal) {
                    break;
                }
                $chunkLimit = min($chunkSize, $effectiveTotal - $off);
                $env        = array_merge(getenv() ?: [], ['HELMETSAN_INGEST_NO_LOCK' => '1']);
                $cmd        = 'wp helmetsan ingest --path=' . escapeshellarg($path) . ' --batch-size=' . $batchSize
                    . ' --offset=' . $off . ' --limit=' . $chunkLimit
                    . ($dryRun ? ' --dry-run' : '') . ' --allow-root';
                $pipes = [];
                $proc  = proc_open($cmd, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, getcwd() ?: null, $env);
                if (is_resource($proc)) {
                    fclose($pipes[0]);
                    $procs[] = ['proc' => $proc, 'pipes' => $pipes];
                }
            }
            $accepted = 0;
            $created  = 0;
            $updated  = 0;
            $skipped  = 0;
            $rejected  = 0;
            foreach ($procs as $p) {
                $stdout = stream_get_contents($p['pipes'][1]);
                fclose($p['pipes'][1]);
                fclose($p['pipes'][2]);
                if (is_resource($p['proc'])) {
                    proc_close($p['proc']);
                }
                if ($stdout !== false && $stdout !== '') {
                    \WP_CLI::log(trim($stdout));
                    $decoded = json_decode($stdout, true);
                    if (is_array($decoded)) {
                        $accepted += (int) ($decoded['accepted'] ?? 0);
                        $created  += (int) ($decoded['created'] ?? 0);
                        $updated  += (int) ($decoded['updated'] ?? 0);
                        $skipped  += (int) ($decoded['skipped'] ?? 0);
                        $rejected += (int) ($decoded['rejected'] ?? 0);
                    }
                }
            }
            \WP_CLI::success(sprintf('Ingest (concurrency %d): accepted=%d, created=%d, updated=%d, skipped=%d, rejected=%d.', $concurrency, $accepted, $created, $updated, $skipped, $rejected));
            return;
        }

        $out = $this->ingestion->ingestPath($path, $batchSize, $limit, $dryRun, $offset);
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
     * [--concurrency=<n>]
     * : Run N parallel ingest-seed processes (chunk seed array). Uses lock bypass. Default: 1.
     * [--dry-run]
     * : Validate only, do not write.
     *
     * ## EXAMPLES
     *     wp helmetsan ingest-seed
     *     wp helmetsan ingest-seed --file=/path/to/helmets_seed.json
     *     wp helmetsan ingest-seed --dry-run
     *     wp helmetsan ingest-seed --concurrency=4
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
        if ($file === '') {
            $file = defined('WP_PLUGIN_DIR') ? WP_PLUGIN_DIR . '/helmetsan-core/seed-data/helmets_seed.json' : '';
        }

        $batchSize   = isset($assoc['batch-size']) ? max(1, (int) $assoc['batch-size']) : 25;
        $concurrency = isset($assoc['concurrency']) ? max(1, min(16, (int) $assoc['concurrency'])) : 1;
        $dryRun      = isset($assoc['dry-run']);

        \WP_CLI::log('Seed file: ' . ($file === '' ? '(default)' : $file));
        if ($dryRun) {
            \WP_CLI::log('Mode: DRY RUN (no writes)');
        }
        \WP_CLI::log('Starting ingestion...');

        if ($concurrency > 1 && $file !== '' && is_file($file)) {
            $json = file_get_contents($file);
            if ($json === false) {
                \WP_CLI::error('Cannot read seed file.');
                return;
            }
            $items = json_decode($json, true);
            if (! is_array($items) || $items === []) {
                \WP_CLI::error('Seed file is empty or not a valid JSON array.');
                return;
            }
            if (isset($items['id']) && ! isset($items[0])) {
                $items = [$items];
            }
            $total   = count($items);
            $chunkSize = (int) ceil($total / $concurrency);
            $chunkSize = max(1, $chunkSize);
            $tmpDir  = sys_get_temp_dir() . '/helmetsan_seed_chunks_' . time() . '_' . wp_rand(1000, 9999) . '/';
            if (! is_dir($tmpDir)) {
                mkdir($tmpDir, 0755, true);
            }
            $chunkFiles = [];
            for ($i = 0; $i < $concurrency; $i++) {
                $off   = $i * $chunkSize;
                if ($off >= $total) {
                    break;
                }
                $chunk = array_slice($items, $off, $chunkSize);
                $path  = $tmpDir . 'chunk_' . $i . '.json';
                file_put_contents($path, wp_json_encode($chunk, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
                $chunkFiles[] = $path;
            }
            $env  = array_merge(getenv() ?: [], ['HELMETSAN_INGEST_NO_LOCK' => '1']);
            $procs = [];
            foreach ($chunkFiles as $chunkPath) {
                $cmd  = 'wp helmetsan ingest-seed --file=' . escapeshellarg($chunkPath) . ' --batch-size=' . $batchSize . ($dryRun ? ' --dry-run' : '') . ' --allow-root';
                $pipes = [];
                $proc  = proc_open($cmd, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, getcwd() ?: null, $env);
                if (is_resource($proc)) {
                    fclose($pipes[0]);
                    $procs[] = ['proc' => $proc, 'pipes' => $pipes];
                }
            }
            $created = 0;
            $updated = 0;
            $accepted = 0;
            $rejected = 0;
            $skipped = 0;
            foreach ($procs as $p) {
                $stdout = stream_get_contents($p['pipes'][1]);
                fclose($p['pipes'][1]);
                fclose($p['pipes'][2]);
                if (is_resource($p['proc'])) {
                    proc_close($p['proc']);
                }
                if ($stdout !== false && preg_match('/HELMETSAN_SEED_RESULT\s+(\{.+\})/', $stdout, $m)) {
                    $decoded = json_decode($m[1], true);
                    if (is_array($decoded)) {
                        $created  += (int) ($decoded['created'] ?? 0);
                        $updated  += (int) ($decoded['updated'] ?? 0);
                        $accepted += (int) ($decoded['accepted'] ?? 0);
                        $rejected += (int) ($decoded['rejected'] ?? 0);
                        $skipped  += (int) ($decoded['skipped'] ?? 0);
                    }
                }
            }
            foreach ($chunkFiles as $f) {
                if (is_file($f)) {
                    @unlink($f);
                }
            }
            @rmdir($tmpDir);
            if ($rejected > 0) {
                \WP_CLI::warning("{$rejected} items were rejected across chunks. Check ingestion logs.");
            } else {
                \WP_CLI::success("Ingested {$accepted} helmets from seed (concurrency {$concurrency}): {$created} new, {$updated} updated, {$skipped} unchanged.");
            }
            return;
        }

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
        \WP_CLI::log('HELMETSAN_SEED_RESULT ' . wp_json_encode(['created' => $created, 'updated' => $updated, 'accepted' => $accepted, 'rejected' => $rejected, 'skipped' => $skipped]));
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
            $slug = sanitize_title($name);
            $existing = get_term_by('slug', $slug, 'accessory_category');
            if ($existing instanceof \WP_Term) {
                wp_update_term($existing->term_id, 'accessory_category', ['description' => $desc]);
                continue;
            }
            if (! term_exists($name, 'accessory_category')) {
                $result = wp_insert_term($name, 'accessory_category', [
                    'description' => $desc,
                    'slug'        => $slug,
                ]);
                if (! is_wp_error($result)) {
                    $created++;
                    \WP_CLI::log("Created: {$name} ({$slug})");
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
            $resolved = $accessories->resolveCategoryFromMeta($postId);
            if ($resolved !== null) {
                $term = get_term_by('name', $resolved, 'accessory_category');
                if ($term instanceof \WP_Term) {
                    wp_set_object_terms($postId, [(int) $term->term_id], 'accessory_category', false);
                    $updated++;
                    \WP_CLI::log("Assigned {$resolved} for post {$postId}");
                } else {
                    $noMap++;
                }
            } else {
                $ok = $accessories->assignCategoryFromType($postId);
                if ($ok) {
                    $updated++;
                    $type = (string) get_post_meta($postId, 'accessory_type', true);
                    \WP_CLI::log("Assigned category for post {$postId} ({$type})");
                } else {
                    $noMap++;
                }
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
     * List type/parent_category/subcategory values for accessories that have no category term and no mapping.
     * Use this to see what to add to AccessoryService::mapTypeToAccessoryCategory() or to fill via fill-missing.
     *
     * ## OPTIONS
     * [--format=<format>]
     * : table|json. Default table.
     *
     * ## EXAMPLES
     *     wp helmetsan list-unmapped-accessory-meta
     *     wp helmetsan list-unmapped-accessory-meta --format=json
     */
    public function listUnmappedAccessoryMeta(array $args, array $assoc): void
    {
        if (! function_exists('helmetsan_core')) {
            \WP_CLI::error('Plugin not loaded.');
        }
        $accessories = helmetsan_core()->accessories();
        $format = (string) ($assoc['format'] ?? 'table');

        $posts = get_posts([
            'post_type'      => 'accessory',
            'post_status'    => 'publish',
            'posts_per_page' => -1,
            'fields'         => 'ids',
        ]);

        $values = [];
        $emptyCount = 0;

        foreach ($posts as $postId) {
            $terms = wp_get_object_terms($postId, 'accessory_category');
            if (! is_wp_error($terms) && is_array($terms) && $terms !== []) {
                continue;
            }
            $type = (string) get_post_meta($postId, 'accessory_type', true);
            $parent = (string) get_post_meta($postId, 'accessory_parent_category', true);
            $sub = (string) get_post_meta($postId, 'accessory_subcategory', true);

            $resolved = $accessories->resolveCategoryFromMeta($postId);
            if ($resolved !== null) {
                continue;
            }
            if ($type === '' && $parent === '' && $sub === '') {
                $emptyCount++;
                continue;
            }

            foreach (['accessory_type' => $type, 'accessory_parent_category' => $parent, 'accessory_subcategory' => $sub] as $source => $value) {
                $v = trim($value);
                if ($v === '') {
                    continue;
                }
                $key = $source . '::' . $v;
                if (! isset($values[ $key ])) {
                    $values[ $key ] = ['source' => $source, 'value' => $v, 'count' => 0];
                }
                $values[ $key ]['count']++;
            }
        }

        uasort($values, static function ($a, $b) {
            return $b['count'] <=> $a['count'];
        });

        $rows = array_values($values);
        $withValueCount = array_sum(array_column($rows, 'count'));

        \WP_CLI::log(sprintf('Of those with no category term: %d have type/parent_category/subcategory all empty (need fill-missing or re-ingest).', $emptyCount));
        \WP_CLI::log(sprintf('Distinct unmapped meta values below: %d. Add to mapTypeToAccessoryCategory() in AccessoryService or fix source data.', count($rows)));
        \WP_CLI::log('');

        if ($rows === []) {
            if ($emptyCount > 0) {
                \WP_CLI::warning('All unmapped accessories have empty type/parent_category/subcategory. Run fill-missing for those fields, then backfill again.');
            }
            return;
        }

        if ($format === 'json') {
            \WP_CLI::line(wp_json_encode(['empty_meta_count' => $emptyCount, 'unmapped_values' => $rows], JSON_PRETTY_PRINT));
            return;
        }

        \WP_CLI\Utils\format_items('table', $rows, ['source', 'value', 'count']);
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
        $dupWarn = $out['repository_duplicates_warning'] ?? null;
        if ($dupWarn !== null && (int) $dupWarn > 0) {
            \WP_CLI::warning(sprintf('Repository has %d duplicate key(s). Run wp helmetsan data check-duplicates to list and fix.', (int) $dupWarn));
        }
        \WP_CLI::line(wp_json_encode($out, JSON_PRETTY_PRINT));
    }

    /**
     * Scan JSON under the data root for duplicate ids (and EANs for helmets) to maintain repository sanity.
     *
     * ## OPTIONS
     * [--type=<types>]
     * : Comma-separated: helmet, accessory, brand. Default: helmet,accessory,brand
     * [--no-ean]
     * : Do not check duplicate EANs for helmets (only id).
     * [--format=<format>]
     * : table|json|count. Default: table
     *
     * ## EXAMPLES
     *     wp helmetsan data check-duplicates
     *     wp helmetsan data check-duplicates --type=helmet,accessory
     *     wp helmetsan data check-duplicates --format=json
     */
    public function dataCheckDuplicates(array $args, array $assoc): void
    {
        if ($this->repository === null) {
            \WP_CLI::error('Repository not available (plugin data root not set).');
            return;
        }
        $typeOpt = isset($assoc['type']) ? trim((string) $assoc['type']) : 'helmet,accessory,brand';
        $types = array_values(array_filter(array_map('trim', explode(',', $typeOpt))));
        if ($types === []) {
            $types = ['helmet', 'accessory', 'brand'];
        }
        $includeEan = ! isset($assoc['no-ean']);
        $format = (string) ($assoc['format'] ?? 'table');

        $checker = new DuplicateCheckerService($this->repository);
        $result = $checker->check($types, $includeEan);

        if ($format === 'count') {
            $total = count($result['duplicates']);
            \WP_CLI::log((string) $total);
            return;
        }

        if ($result['duplicates'] === []) {
            \WP_CLI::success('No duplicates found. Total keys checked: ' . wp_json_encode($result['total_checked']));
            return;
        }

        \WP_CLI::warning(sprintf('Found %d duplicate key(s).', count($result['duplicates'])));
        if ($format === 'json') {
            \WP_CLI::line(wp_json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            return;
        }

        $rows = [];
        foreach ($result['duplicates'] as $d) {
            $rows[] = [
                'type'      => $d['type'],
                'key_type'  => $d['key_type'],
                'key'       => $d['key'],
                'count'     => $d['count'],
                'locations' => implode('; ', array_slice($d['locations'], 0, 5)) . (count($d['locations']) > 5 ? ' (+' . (count($d['locations']) - 5) . ' more)' : ''),
            ];
        }
        \WP_CLI\Utils\format_items('table', $rows, ['type', 'key_type', 'key', 'count', 'locations']);
    }

    /**
     * Fix duplicate ids in repository JSON: export a deduped file (keeps first occurrence per id) or report what would change.
     *
     * ## OPTIONS
     * [--dry-run]
     * : Report duplicates and suggest fix; do not write files.
     * [--strategy=<strategy>]
     * : export-deduped = write single JSON array with first occurrence of each id. Requires --output.
     * [--type=<type>]
     * : helmet|accessory. For export-deduped only helmet is supported. Default: helmet
     * [--output=<path>]
     * : Output path (relative to data root) for export-deduped. Required when using --strategy=export-deduped.
     *
     * ## EXAMPLES
     *     wp helmetsan data fix-duplicates --dry-run
     *     wp helmetsan data fix-duplicates --strategy=export-deduped --output=helmets/deduped_seed.json
     */
    public function dataFixDuplicates(array $args, array $assoc): void
    {
        if ($this->repository === null) {
            \WP_CLI::error('Repository not available (plugin data root not set).');
            return;
        }
        $dryRun = isset($assoc['dry-run']);
        $strategy = isset($assoc['strategy']) ? trim((string) $assoc['strategy']) : '';
        $outputPath = isset($assoc['output']) ? trim((string) $assoc['output']) : '';
        $type = isset($assoc['type']) ? trim((string) $assoc['type']) : 'helmet';

        $checker = new DuplicateCheckerService($this->repository);
        $result = $checker->check([$type], true);
        $dupCount = count($result['duplicates']);

        if ($dupCount === 0) {
            \WP_CLI::success('No duplicates found. Nothing to fix.');
            return;
        }

        if ($dryRun) {
            \WP_CLI::warning(sprintf('%d duplicate key(s) found.', $dupCount));
            \WP_CLI::line('To produce a deduped file (first occurrence per id): wp helmetsan data fix-duplicates --strategy=export-deduped --output=<path> [--type=helmet]');
            \WP_CLI::line('Or edit JSON manually to remove or merge duplicates, then run wp helmetsan data check-duplicates to verify.');
            return;
        }

        if ($strategy !== 'export-deduped' || $outputPath === '') {
            \WP_CLI::error('Use --strategy=export-deduped and --output=<path> to write a deduped file. Use --dry-run to report only.');
            return;
        }

        if ($type !== 'helmet') {
            \WP_CLI::error('export-deduped currently supports only --type=helmet.');
            return;
        }

        $base = $this->repository->rootPath();
        $seen = [];
        $deduped = [];
        foreach (['helmets', 'seed-data'] as $rel) {
            $files = $this->repository->listJsonFiles($rel);
            foreach ($files as $absPath) {
                $data = $this->repository->read($absPath);
                if (isset($data[0]) && is_array($data[0])) {
                    foreach ($data as $item) {
                        if (! is_array($item)) {
                            continue;
                        }
                        $id = isset($item['id']) ? trim((string) $item['id']) : '';
                        if ($id !== '' && ! isset($seen[$id])) {
                            $seen[$id] = true;
                            $deduped[] = $item;
                        }
                    }
                } elseif (isset($data['id']) && (isset($data['entity']) && $data['entity'] === 'helmet' || ! isset($data['entity']))) {
                    $id = trim((string) $data['id']);
                    if ($id !== '' && ! isset($seen[$id])) {
                        $seen[$id] = true;
                        $deduped[] = $data;
                    }
                }
            }
        }

        $fullPath = strpos($outputPath, '/') === 0 ? $outputPath : rtrim($base, '/') . '/' . ltrim($outputPath, '/');
        $dir = dirname($fullPath);
        if (! is_dir($dir)) {
            wp_mkdir_p($dir);
        }
        $json = wp_json_encode($deduped, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! is_string($json) || file_put_contents($fullPath, $json) === false) {
            \WP_CLI::error('Failed to write deduped file: ' . $fullPath);
            return;
        }
        \WP_CLI::success(sprintf('Wrote %d record(s) (first occurrence per id) to %s. Replace your seed file with this or merge manually.', count($deduped), $fullPath));
    }

    /**
     * ## OPTIONS
     * <type>
     * : schema|logic|integrity|accessory
     * [--file=<file>]
     * : Optional absolute file path for schema/logic/accessory checks. For accessory, file may be a single JSON object or array of objects (each validated).
     */
    public function validate(array $args, array $assoc): void
    {
        $type = (string) ($args[0] ?? 'integrity');

        if ($type === 'integrity') {
            \WP_CLI::line(wp_json_encode($this->validator->validateIntegrity(), JSON_PRETTY_PRINT));
            return;
        }

        if ($type === 'accessory') {
            $file = (string) ($assoc['file'] ?? '');
            if ($file === '' || ! file_exists($file)) {
                \WP_CLI::error('Provide --file for accessory validation (path to single accessory JSON or array of accessories)');
                return;
            }
            $raw  = file_get_contents($file);
            $data = json_decode((string) $raw, true);
            if (! is_array($data)) {
                \WP_CLI::error('Invalid JSON input file');
                return;
            }
            $items = isset($data[0]) ? $data : [$data];
            $allOk = true;
            $results = [];
            foreach ($items as $i => $item) {
                if (! is_array($item)) {
                    $results[] = ['index' => $i, 'schema' => ['ok' => false, 'errors' => ['Not an object']], 'logic' => ['ok' => true, 'errors' => [], 'warnings' => []]];
                    $allOk = false;
                    continue;
                }
                $schema = $this->validator->validateAccessorySchema($item);
                $logic  = $this->validator->validateAccessoryLogic($item);
                $results[] = ['index' => $i, 'schema' => $schema, 'logic' => $logic];
                if (! $schema['ok'] || ! $logic['ok']) {
                    $allOk = false;
                }
            }
            \WP_CLI::line(wp_json_encode(['ok' => $allOk, 'results' => $results], JSON_PRETTY_PRINT));
            if (! $allOk) {
                \WP_CLI::error('Accessory validation failed.', true);
            }
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
     * : helmet|brand|accessory|safety_standard|dealer|distributor|technology|motorcycle|comparison|recommendation|all. Default: all
     * [--batch-size=<n>]
     * : Number of posts per batch. Default: 300
     * [--limit=<n>]
     * : Max posts to process per type (0 = no limit). Default: 0
     * [--scope=<scope>]
     * : posts|terms|all. posts = CPTs only; terms = taxonomy term archives only; all = both. Default: posts
     * [--concurrency=<n>]
     * : Run N parallel processes for posts (single --post-type only). Ignored when --post-type=all or scope=terms. Default: 1
     * [--dry-run]
     * : Do not save; only report counts
     * [--use-ai]
     * : Use AI for meta descriptions (posts only). Requires configured providers under Helmetsan → AI.
     *
     * ## EXAMPLES
     *     wp helmetsan seo seed --post-type=helmet
     *     wp helmetsan seo seed --post-type=all --scope=all
     *     wp helmetsan seo seed --post-type=helmet --concurrency=4
     *     wp helmetsan seo seed --scope=terms
     *     wp helmetsan seo seed --dry-run --use-ai --post-type=helmet --limit=100
     */
    public function seoSeed(array $args, array $assoc): void
    {
        $postType = (string) ($assoc['post-type'] ?? 'all');
        $batchSize = isset($assoc['batch-size']) ? max(1, (int) $assoc['batch-size']) : 300;
        $limit = isset($assoc['limit']) ? max(0, (int) $assoc['limit']) : 0;
        $scope = (string) ($assoc['scope'] ?? 'posts');
        $concurrency = isset($assoc['concurrency']) ? max(1, min(16, (int) $assoc['concurrency'])) : 1;
        $dryRun = isset($assoc['dry-run']);
        $useAi = isset($assoc['use-ai']);

        if (! in_array($scope, ['posts', 'terms', 'all'], true)) {
            \WP_CLI::error('Invalid --scope. Use: posts, terms, or all.');
            return;
        }

        $allowed = ['helmet', 'brand', 'accessory', 'safety_standard', 'dealer', 'distributor', 'technology', 'motorcycle', 'comparison', 'recommendation', 'all'];
        if (! in_array($postType, $allowed, true)) {
            \WP_CLI::error('Invalid --post-type. Use: helmet, brand, accessory, safety_standard, dealer, distributor, technology, motorcycle, comparison, recommendation, or all.');
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
        $totalUpdated = 0;
        $startTime = microtime(true);
        $primaryTypes = ['helmet', 'brand', 'accessory'];
        $otherTypes = YoastSeoSeeder::getOtherCptTypesForSeo();

        if ($concurrency > 1 && $postType !== 'all' && ($scope === 'posts' || $scope === 'all')) {
            $totalQuery = new \WP_Query([
                'post_type'      => $postType,
                'post_status'    => 'publish',
                'posts_per_page' => -1,
                'fields'         => 'ids',
            ]);
            $totalCount = is_array($totalQuery->posts) ? count($totalQuery->posts) : 0;
            $effectiveTotal = $limit > 0 ? min($totalCount, $limit) : $totalCount;
            if ($effectiveTotal === 0) {
                \WP_CLI::log(sprintf('[%s] No published posts.', $postType));
            } else {
                $chunkSize = (int) ceil($effectiveTotal / $concurrency);
                $chunkSize = max(1, min($chunkSize, $batchSize));
                $procs = [];
                $baseCmd = 'wp helmetsan seo seed --post-type=' . escapeshellarg($postType)
                    . ' --batch-size=' . $chunkSize
                    . ' --scope=posts'
                    . ($dryRun ? ' --dry-run' : '')
                    . ($useAi ? ' --use-ai' : '')
                    . ' --allow-root';
                for ($i = 0; $i < $concurrency; $i++) {
                    $offset = $i * $chunkSize;
                    if ($offset >= $effectiveTotal) {
                        break;
                    }
                    $chunkLimit = min($chunkSize, $effectiveTotal - $offset);
                    $cmd = $baseCmd . ' --offset=' . $offset . ' --limit=' . $chunkLimit;
                    $pipes = [];
                    $proc = proc_open(
                        $cmd,
                        [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']],
                        $pipes,
                        getcwd() ?: null,
                        null
                    );
                    if (is_resource($proc)) {
                        fclose($pipes[0]);
                        $stdout = stream_get_contents($pipes[1]);
                        fclose($pipes[1]);
                        fclose($pipes[2]);
                        if ($stdout !== false && $stdout !== '') {
                            \WP_CLI::log(trim($stdout));
                            if (preg_match('/Total (?:would update|updated): (\d+)/', (string) $stdout, $m)) {
                                $totalUpdated += (int) $m[1];
                            }
                        }
                        $procs[] = $proc;
                    }
                }
                foreach ($procs as $p) {
                    if (is_resource($p)) {
                        proc_close($p);
                    }
                }
                \WP_CLI::log(sprintf('[%s] Concurrency %d: processed up to %d posts.', $postType, $concurrency, $effectiveTotal));
            }
            if ($scope === 'terms' || $scope === 'all') {
                \WP_CLI::log('Seeding Yoast SEO (taxonomy terms): title, meta description, focus keyword (lowercase).');
                $taxonomies = YoastSeoSeeder::getTaxonomiesForTermSeo();
                foreach ($taxonomies as $tax) {
                    $result = $seeder->seedTermsForTaxonomy($tax, 0, 0, $dryRun);
                    $updated = (int) ($result['updated'] ?? 0);
                    $total = (int) ($result['total'] ?? 0);
                    $totalUpdated += $updated;
                    if ($total > 0) {
                        \WP_CLI::log(sprintf('[term:%s] %s %d of %d', $tax, $dryRun ? 'Would update' : 'Updated', $updated, $total));
                    }
                }
            }
            $elapsed = round(microtime(true) - $startTime, 1);
            \WP_CLI::success(sprintf('SEO seed complete. Total %s: %d in %s s', $dryRun ? 'would update' : 'updated', $totalUpdated, $elapsed));
            return;
        }

        if ($concurrency > 1 && $postType === 'all' && ($scope === 'posts' || $scope === 'all')) {
            $typesToRun = array_merge($primaryTypes, $otherTypes);
            \WP_CLI::log('Seeding Yoast SEO (posts): running ' . count($typesToRun) . ' post types with concurrency ' . $concurrency . '.');
            $chunks = array_chunk($typesToRun, $concurrency);
            foreach ($chunks as $chunk) {
                $procs = [];
                foreach ($chunk as $type) {
                    $cmd = 'wp helmetsan seo seed --post-type=' . escapeshellarg($type)
                        . ' --batch-size=' . $batchSize
                        . ' --limit=' . ($limit > 0 ? $limit : 0)
                        . ' --scope=posts'
                        . ($dryRun ? ' --dry-run' : '')
                        . ($useAi ? ' --use-ai' : '')
                        . ' --allow-root';
                    $pipes = [];
                    $proc = proc_open($cmd, [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']], $pipes, getcwd() ?: null, null);
                    if (is_resource($proc)) {
                        fclose($pipes[0]);
                        $procs[] = ['type' => $type, 'proc' => $proc, 'pipes' => $pipes];
                    }
                }
                foreach ($procs as $p) {
                    $stdout = stream_get_contents($p['pipes'][1]);
                    fclose($p['pipes'][1]);
                    fclose($p['pipes'][2]);
                    if (is_resource($p['proc'])) {
                        proc_close($p['proc']);
                    }
                    if ($stdout !== false && $stdout !== '') {
                        \WP_CLI::log(trim($stdout));
                        if (preg_match('/Total (?:would update|updated): (\d+)/', (string) $stdout, $m)) {
                            $totalUpdated += (int) $m[1];
                        }
                    }
                }
            }
            if ($scope === 'all') {
                \WP_CLI::log('Seeding Yoast SEO (taxonomy terms): title, meta description, focus keyword (lowercase).');
                $taxonomies = YoastSeoSeeder::getTaxonomiesForTermSeo();
                foreach ($taxonomies as $tax) {
                    $result = $seeder->seedTermsForTaxonomy($tax, 0, 0, $dryRun);
                    $updated = (int) ($result['updated'] ?? 0);
                    $total = (int) ($result['total'] ?? 0);
                    $totalUpdated += $updated;
                    if ($total > 0) {
                        \WP_CLI::log(sprintf('[term:%s] %s %d of %d', $tax, $dryRun ? 'Would update' : 'Updated', $updated, $total));
                    }
                }
            }
            $elapsed = round(microtime(true) - $startTime, 1);
            \WP_CLI::success(sprintf('SEO seed complete. Total %s: %d in %s s', $dryRun ? 'would update' : 'updated', $totalUpdated, $elapsed));
            return;
        }

        if ($scope === 'posts' || $scope === 'all') {
            \WP_CLI::log('Seeding Yoast SEO (posts): title, meta description, focus keyword (lowercase).');
            $types = $postType === 'all' ? array_merge($primaryTypes, $otherTypes) : [$postType];
            foreach ($types as $type) {
                $offset = 0;
                $remaining = $limit > 0 ? $limit : null;
                $typeTotal = 0;

                if (in_array($type, $primaryTypes, true)) {
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
                } else {
                    $result = $seeder->seedCpt($type, $limit > 0 ? $limit : 0, 0, $dryRun);
                    $updated = (int) ($result['updated'] ?? 0);
                    $typeTotal = $updated;
                    $totalUpdated += $updated;
                    if ($updated > 0) {
                        \WP_CLI::log(sprintf('[%s] %s %d', $type, $dryRun ? 'Would update' : 'Updated', $updated));
                    }
                }

                if ($typeTotal > 0) {
                    $label = $type === 'accessory' ? 'Accessories' : ($type === 'helmet' ? 'Helmets' : ($type === 'brand' ? 'Brands' : ucfirst(str_replace('_', ' ', $type)) . 's'));
                    \WP_CLI::log(sprintf('%s: %d done.', $label, $typeTotal));
                }
            }
        }

        if ($scope === 'terms' || $scope === 'all') {
            \WP_CLI::log('Seeding Yoast SEO (taxonomy terms): title, meta description, focus keyword (lowercase).');
            $taxonomies = YoastSeoSeeder::getTaxonomiesForTermSeo();
            foreach ($taxonomies as $tax) {
                $result = $seeder->seedTermsForTaxonomy($tax, 0, 0, $dryRun);
                $updated = (int) ($result['updated'] ?? 0);
                $total = (int) ($result['total'] ?? 0);
                $totalUpdated += $updated;
                if ($total > 0) {
                    \WP_CLI::log(sprintf('[term:%s] %s %d of %d', $tax, $dryRun ? 'Would update' : 'Updated', $updated, $total));
                }
            }
        }

        $elapsed = round(microtime(true) - $startTime, 1);
        \WP_CLI::success(sprintf('SEO seed complete. Total %s: %d in %s s', $dryRun ? 'would update' : 'updated', $totalUpdated, $elapsed));
    }

    /**
     * Check Yoast SEO meta for posts and/or terms (missing, not lowercase focuskw, overlong).
     *
     * ## OPTIONS
     * [--scope=<scope>]
     * : posts|terms|all. Default: posts
     * [--post-type=<type>]
     * : For scope posts: helmet|brand|accessory|safety_standard|dealer|distributor|technology|motorcycle|comparison|recommendation|all. Default: all
     * [--format=<format>]
     * : table|count. table = one row per item with issues; count = summary only. Default: table
     *
     * ## EXAMPLES
     *     wp helmetsan seo check --scope=all
     *     wp helmetsan seo check --scope=terms --format=count
     */
    public function seoCheck(array $args, array $assoc): void
    {
        $scope = (string) ($assoc['scope'] ?? 'posts');
        $postType = (string) ($assoc['post-type'] ?? 'all');
        $format = (string) ($assoc['format'] ?? 'table');
        if (! in_array($scope, ['posts', 'terms', 'all'], true)) {
            \WP_CLI::error('Invalid --scope. Use: posts, terms, or all.');
            return;
        }
        $seeder = new YoastSeoSeeder(null);
        $rows = [];
        if ($scope === 'posts' || $scope === 'all') {
            $primary = ['helmet', 'brand', 'accessory'];
            $other = YoastSeoSeeder::getOtherCptTypesForSeo();
            $types = $postType === 'all' ? array_merge($primary, $other) : [$postType];
            foreach ($types as $type) {
                $query = new \WP_Query([
                    'post_type' => $type,
                    'post_status' => 'publish',
                    'posts_per_page' => -1,
                    'fields' => 'ids',
                ]);
                $ids = is_array($query->posts) ? array_map('intval', $query->posts) : [];
                foreach ($ids as $id) {
                    $check = $seeder->checkPostSeo($id);
                    if ($check['issues'] !== []) {
                        $rows[] = [
                            'type' => 'post',
                            'object_type' => $type,
                            'id' => $id,
                            'title' => get_the_title($id),
                            'issues' => implode(', ', $check['issues']),
                        ];
                    }
                }
            }
        }
        if ($scope === 'terms' || $scope === 'all') {
            foreach (YoastSeoSeeder::getTaxonomiesForTermSeo() as $tax) {
                $terms = get_terms(['taxonomy' => $tax, 'hide_empty' => false, 'fields' => 'ids']);
                if (! is_array($terms)) {
                    continue;
                }
                foreach ($terms as $termId) {
                    $termId = (int) $termId;
                    $check = $seeder->checkTermSeo($termId, $tax);
                    if ($check['issues'] !== []) {
                        $term = get_term($termId, $tax);
                        $rows[] = [
                            'type' => 'term',
                            'object_type' => $tax,
                            'id' => $termId,
                            'title' => $term instanceof \WP_Term ? $term->name : (string) $termId,
                            'issues' => implode(', ', $check['issues']),
                        ];
                    }
                }
            }
        }
        if ($format === 'count') {
            \WP_CLI::log(sprintf('Items with SEO issues: %d', count($rows)));
            return;
        }
        if ($rows === []) {
            \WP_CLI::success('No SEO issues found.');
            return;
        }
        \WP_CLI\Utils\format_items('table', $rows, ['type', 'object_type', 'id', 'title', 'issues']);
    }

    /**
     * Fix Yoast SEO meta: lowercase focus keyphrase, truncate overlong title/meta description.
     *
     * ## OPTIONS
     * [--scope=<scope>]
     * : posts|terms|all. Default: posts
     * [--post-type=<type>]
     * : For scope posts: helmet|brand|accessory|safety_standard|dealer|distributor|technology|motorcycle|comparison|recommendation|all. Default: all
     * [--dry-run]
     * : Report what would be fixed without saving
     *
     * ## EXAMPLES
     *     wp helmetsan seo update --scope=all
     *     wp helmetsan seo update --dry-run
     */
    public function seoUpdate(array $args, array $assoc): void
    {
        $scope = (string) ($assoc['scope'] ?? 'posts');
        $postType = (string) ($assoc['post-type'] ?? 'all');
        $dryRun = isset($assoc['dry-run']);
        if (! in_array($scope, ['posts', 'terms', 'all'], true)) {
            \WP_CLI::error('Invalid --scope. Use: posts, terms, or all.');
            return;
        }
        $seeder = new YoastSeoSeeder(null);
        $totalFixed = 0;
        if ($scope === 'posts' || $scope === 'all') {
            $primary = ['helmet', 'brand', 'accessory'];
            $other = YoastSeoSeeder::getOtherCptTypesForSeo();
            $types = $postType === 'all' ? array_merge($primary, $other) : [$postType];
            foreach ($types as $type) {
                $query = new \WP_Query([
                    'post_type' => $type,
                    'post_status' => 'publish',
                    'posts_per_page' => -1,
                    'fields' => 'ids',
                ]);
                $ids = is_array($query->posts) ? array_map('intval', $query->posts) : [];
                foreach ($ids as $id) {
                    if (! $dryRun) {
                        $result = $seeder->fixPostSeo($id, ['lowercase_focuskw' => true, 'truncate_metadesc' => true, 'truncate_title' => true]);
                        $totalFixed += count($result['fixed']);
                    } else {
                        $check = $seeder->checkPostSeo($id);
                        if ($check['issues'] !== []) {
                            $totalFixed++;
                        }
                    }
                }
            }
        }
        if ($scope === 'terms' || $scope === 'all') {
            foreach (YoastSeoSeeder::getTaxonomiesForTermSeo() as $tax) {
                $terms = get_terms(['taxonomy' => $tax, 'hide_empty' => false, 'fields' => 'ids']);
                if (! is_array($terms)) {
                    continue;
                }
                foreach ($terms as $termId) {
                    $termId = (int) $termId;
                    if (! $dryRun) {
                        $result = $seeder->fixTermSeo($termId, $tax, ['lowercase_focuskw' => true, 'truncate_metadesc' => true, 'truncate_title' => true]);
                        $totalFixed += count($result['fixed']);
                    } else {
                        $check = $seeder->checkTermSeo($termId, $tax);
                        if ($check['issues'] !== []) {
                            $totalFixed++;
                        }
                    }
                }
            }
        }
        \WP_CLI::success($dryRun
            ? sprintf('Would fix %d items. Run without --dry-run to apply.', $totalFixed)
            : sprintf('Fixed %d SEO meta fields.', $totalFixed));
    }

    /**
     * Phase 2: Fill missing entity fields using AI (context-aware).
     *
     * ## OPTIONS
     * [--post-type=<type>]
     * : helmet|brand|accessory|safety_standard|dealer|distributor|technology|motorcycle|comparison|recommendation|all. Default: all
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
     * [--refill-unmapped]
     * : (Accessory only.) Process all accessories with no accessory_category term; re-fill type/parent_category so backfill can map them (ignores --limit). Use after backfill reports "had no mapping". Fallback: HELMETSAN_REFILL_UNMAPPED=1.
     * [--verbose]
     * : Log each filled field and each failure (post ID, meta key, value or reason).
     * [--strict]
     * : On empty or invalid AI output, leave field empty and do not retry (saves API calls).
     * [--no-taxonomies]
     * : Skip filling missing taxonomy terms (helmet_type, certification, feature_tag, etc.); only fill meta.
     * [--no-cache]
     * : Disable 24h cache for identical context (more API calls).
     * [--concurrency=<n>]
     * : Run N parallel processes (split by offset/limit). Faster for large runs. Only for single --post-type. Default: 1.
     * [--rate-limit=<sec>]
     * : Seconds to sleep between API calls. Use 0 to disable (faster; may hit provider limits). Default: 1.
     * [--only-taxonomies=<tax1,tax2>]
     * : Only fill these taxonomy slugs (e.g. certification). Skips meta fill when used alone.
     * [--report]
     * : Only print coverage report (per-field set/empty and % complete); no API calls or writes.
     * [--multiplex]
     * : Process multiple fields in parallel using AI multiplexing for each post.
     *
     * ## EXAMPLES
     *     wp helmetsan ai fill-missing --report --post-type=helmet
     *     wp helmetsan ai fill-missing --post-type=helmet --limit=10
     *     wp helmetsan ai fill-missing --post-type=accessory --limit=0 --concurrency=4 --rate-limit=0
     *     wp helmetsan ai fill-missing --post-type=helmet --fields=head_shape,spec_shell_material
     *     wp helmetsan ai fill-missing --post-type=helmet --only-taxonomies=certification --limit=100
     *     wp helmetsan ai fill-missing --dry-run --verbose
     *     wp helmetsan ai fill-missing --only-incomplete --strict
     *     wp helmetsan ai fill-missing --post-type=accessory --refill-unmapped
     *     wp helmetsan ai fill-missing --post-type=helmet --multiplex
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
        $refillAccessoryIfNoCategory = isset($assoc['refill-unmapped'])
            || filter_var(getenv('HELMETSAN_REFILL_UNMAPPED'), FILTER_VALIDATE_BOOLEAN);
        $verbose = isset($assoc['verbose']);
        $strictMode = isset($assoc['strict']);
        $fillTaxonomies = ! isset($assoc['no-taxonomies']);
        $noCache = isset($assoc['no-cache']);
        $cacheTtl = $noCache ? 0 : 86400;
        $monitor = new \Helmetsan\Core\Support\ResourceMonitor();
        $defaultConcurrency = $monitor->getRecommendedConcurrency();
        $concurrency = isset($assoc['concurrency']) ? max(1, min(16, (int) $assoc['concurrency'])) : $defaultConcurrency;
        $multiplex = isset($assoc['multiplex']);
        $rateLimitSeconds = isset($assoc['rate-limit']) ? max(0, (int) $assoc['rate-limit']) : null;
        $fieldsOpt = isset($assoc['fields']) ? trim((string) $assoc['fields']) : '';
        $onlyFields = $fieldsOpt !== '' ? array_map('trim', array_filter(explode(',', $fieldsOpt))) : null;
        $onlyTaxonomiesOpt = isset($assoc['only-taxonomies']) ? trim((string) $assoc['only-taxonomies']) : '';
        $onlyTaxonomies = $onlyTaxonomiesOpt !== '' ? array_values(array_filter(array_map('trim', explode(',', $onlyTaxonomiesOpt)))) : null;
        if ($onlyTaxonomies !== null && $onlyTaxonomies !== [] && $onlyFields === null) {
            $onlyFields = [];
        }
        $reportOnly = isset($assoc['report']);
        $internalId = isset($assoc['internal-id']) ? (string) $assoc['internal-id'] : null;
        $allowed = ['helmet', 'brand', 'accessory', 'safety_standard', 'dealer', 'distributor', 'technology', 'motorcycle', 'comparison', 'recommendation', 'all'];
        if (! in_array($postType, $allowed, true)) {
            \WP_CLI::error('Invalid --post-type. Use: helmet, brand, accessory, safety_standard, dealer, distributor, technology, motorcycle, comparison, recommendation, or all.');
            return;
        }
        $allTypes = ['helmet', 'brand', 'accessory', 'safety_standard', 'dealer', 'distributor', 'technology', 'motorcycle', 'comparison', 'recommendation'];
        $types = $postType === 'all' ? $allTypes : [$postType];
        $types = array_filter($types, static fn (string $t): bool => FillableFieldsConfig::forPostType($t) !== []);
        if ($types === []) {
            \WP_CLI::warning('No fillable post types selected.');
            return;
        }
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

        if ($reportOnly) {
            $fillService = new FillMissingService($this->aiService);
            foreach ($types as $type) {
                $report = $fillService->getCoverageReport($type, $limit > 0 ? $limit : 0);
                \WP_CLI::log(sprintf("\n[%s] Total posts: %d", $type, $report['total_posts']));
                if ($report['total_posts'] === 0) {
                    continue;
                }
                $rows = [];
                foreach ($report['fields'] as $field => $counts) {
                    $rows[] = [$field, (string) $counts['set'], (string) $counts['empty'], $counts['pct'] . '%'];
                }
                \WP_CLI\Utils\format_items('table', $rows, ['field', 'set', 'empty', 'complete']);
            }
            \WP_CLI::success('Coverage report complete.');
            return;
        }

        if ($concurrency > 1 && count($types) === 1) {
            $singleType = $types[0];
            $totalQuery = new \WP_Query([
                'post_type'      => $singleType,
                'post_status'    => 'publish',
                'posts_per_page' => $limit > 0 ? $limit : -1,
                'offset'         => $offset,
                'fields'         => 'ids',
            ]);
            $totalIds = is_array($totalQuery->posts) ? array_map('intval', $totalQuery->posts) : [];
            $totalCount = count($totalIds);
            if ($totalCount === 0) {
                \WP_CLI::success('No posts to process.');
                return;
            }

            $chunkSize = (int) ceil($totalCount / $concurrency);

            if ($this->taskTracker && ! $this->taskTracker->verify()) {
                \WP_CLI::error('Tasks directory is not writable. Check permissions for wp-content/uploads/helmetsan-data/tasks/');
                return;
            }

            $phpBin = (defined('PHP_BINARY') && is_executable(PHP_BINARY)) ? PHP_BINARY : '/usr/bin/php';
            $wpBin = trim((string) @shell_exec('which wp') ?: '/usr/local/bin/wp');
            $fullWp = $phpBin . ' ' . escapeshellarg($wpBin);

            $cmdBase = $fullWp . ' helmetsan ai fill-missing --post-type=' . $singleType
                . ' --offset=%d --limit=' . $chunkSize . ' --concurrency=1'
                . ($dryRun ? ' --dry-run' : '')
                . ($onlyIncomplete ? ' --only-incomplete' : '')
                . ($refillAccessoryIfNoCategory ? ' --refill-unmapped' : '')
                . ($strictMode ? ' --strict' : '')
                . ($rateLimitSeconds !== null ? ' --rate-limit=' . (int) $rateLimitSeconds : '')
                . ($noCache ? ' --no-cache' : '')
                . ($multiplex ? ' --multiplex' : '')
                . ($fillTaxonomies ? '' : ' --no-taxonomies')
                . ($fieldsOpt !== '' ? ' --fields=' . escapeshellarg($fieldsOpt) : '')
                . (isset($assoc['allow-root']) ? ' --allow-root' : '')
                . ' --internal-id=%s';

            $debugDir = WP_CONTENT_DIR . '/uploads/helmetsan-data/debug';
            if (! is_dir($debugDir)) {
                wp_mkdir_p($debugDir);
            }

            for ($i = 0; $i < $concurrency; $i++) {
                $off = $offset + ($i * $chunkSize);
                if ($off >= $offset + $totalCount) {
                    break;
                }
                $workerId = "fm-{$singleType}-{$off}";
                if ($this->taskTracker) {
                    $this->taskTracker->start($workerId, "Fill Missing ($singleType) @$off", 'ai-enrichment');
                }

                $logFile = $debugDir . '/parallel_' . $singleType . '_' . $off . '.log';
                $fullWorkerCmd = sprintf($cmdBase, $off, escapeshellarg($workerId));
                
                // Simplified background execution: nohup COMMAND > LOG 2>&1 &
                // We rely on shell_exec inheriting the current CWD.
                $cmd = sprintf(
                    "nohup %s > %s 2>&1 &",
                    $fullWorkerCmd,
                    escapeshellarg($logFile)
                );
                
                if ($i === 0) {
                    \WP_CLI::log('Executing first worker: ' . $cmd);
                }
                
                shell_exec($cmd);
            }

            \WP_CLI::success(sprintf('Spawned %d background processes for %s. Monitoring active via: wp helmetsan ai status', $concurrency, $singleType));
            return;
        }

        if ($this->taskTracker) {
            $this->taskTracker->start($internalId ?? 'fm-' . getmypid(), "Fill Missing ($postType)", 'ai-enrichment');
        }
        $fillService = new FillMissingService($this->aiService, $this->taskTracker);
        if ($internalId) {
            $fillService->setTaskId($internalId);
        }
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
            $result = $fillService->run($type, $limit, $offset, $dryRun, $onlyFields, $onlyIncomplete, $strictMode, $fillTaxonomies, $onProgress, $onVerbose, $cacheTtl, $rateLimitSeconds, $refillAccessoryIfNoCategory, $onlyTaxonomies, false, $multiplex);
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
        if ($this->taskTracker) {
            $this->taskTracker->stop('fm-' . getmypid());
        }
        \WP_CLI::success(sprintf('Fill missing complete. Filled: %d, skipped: %d, errors: %d, API calls: %d in %s s', $totalFilled, $totalSkipped, $totalErrors, $totalApiCalls, $elapsed));
    }

    /**
     * Show status of active AI background tasks.
     *
     * ## EXAMPLES
     *     wp helmetsan ai status
     */
    public function aiStatus(array $args, array $assoc): void
    {
        if ($this->taskTracker === null) {
            \WP_CLI::error('Task tracker not available.');
            return;
        }

        $tasks = $this->taskTracker->getActiveTasks();
        if ($tasks === []) {
            \WP_CLI::success('No active AI background tasks.');
            return;
        }

        $rows = [];
        $now = time();
        foreach ($tasks as $id => $data) {
            $elapsed = $now - $data['start'];
            $lastPing = $now - $data['last_ping'];
            $rows[] = [
                'id'        => $id,
                'label'     => $data['label'],
                'type'      => $data['type'],
                'progress'  => (string) $data['progress'],
                'elapsed'   => $elapsed . 's',
                'last_ping' => $lastPing . 's ago',
            ];
        }

        \WP_CLI\Utils\format_items('table', $rows, ['id', 'label', 'type', 'progress', 'elapsed', 'last_ping']);
    }

    /**
     * Generate helmet catalog data (master format) using the AI module. Uses the same credentials as fill-missing and SEO seed.
     *
     * ## OPTIONS
     * [--count=<n>]
     * : Number of helmet models to generate. Default: 5
     * [--brand=<name>]
     * : Single brand (e.g. HJC, Shoei). Can be repeated or use --brands.
     * [--brands=<list>]
     * : Comma-separated brands (e.g. HJC,Arai,Bell).
     * [--provider=<id>]
     * : AI provider to use (e.g. groq, openai). Default: first enabled free provider.
     * [--output=<path>]
     * : Write JSON to this path. If relative, resolved under data root. Omit to print to stdout.
     * [--dry-run]
     * : Call AI and show result count only; do not write file.
     * [--existing-from=<path>]
     * : Path to master JSON (relative to data root, e.g. helmets/master.json). Existing brand/model pairs are excluded. Overrides default WP check when set.
     * [--existing-from-wp]
     * : Use WordPress helmet catalog (parent helmets) as existing list. This is the default if neither --existing-from nor --no-duplicate-check is set.
     * [--no-duplicate-check]
     * : Do not exclude any existing helmets; allow duplicates (e.g. for testing or overwrite workflows).
     *
     * ## EXAMPLES
     *     wp helmetsan ai generate-seed --count=10 --brand=HJC
     *     wp helmetsan ai generate-seed --count=5 --brands=HJC,Arai,Bell --output=helmets/generated.json
     *     wp helmetsan ai generate-seed --count=10 --existing-from=helmets/master.json
     *     wp helmetsan ai generate-seed --count=5 --existing-from-wp --output=helmets/generated.json
     *     wp helmetsan ai generate-seed --count=3 --provider=groq --dry-run
     *     wp helmetsan ai generate-seed --count=5 --no-duplicate-check
     */
    public function aiGenerateSeed(array $args, array $assoc): void
    {
        if ($this->seedGenerator === null || $this->repository === null) {
            \WP_CLI::error('Seed generator or repository not available (plugin not fully wired).');
            return;
        }
        $count = isset($assoc['count']) ? max(1, min(50, (int) $assoc['count'])) : 5;
        $brandOpt = $assoc['brand'] ?? $assoc['brands'] ?? null;
        $brands = [];
        if ($brandOpt !== null) {
            if (is_array($brandOpt)) {
                $brands = array_map('trim', $brandOpt);
            } else {
                $brands = array_map('trim', explode(',', (string) $brandOpt));
            }
            $brands = array_values(array_filter($brands));
        }
        $providerId = isset($assoc['provider']) ? trim((string) $assoc['provider']) : null;
        $providerId = $providerId !== '' ? $providerId : null;
        $outputPath = isset($assoc['output']) ? trim((string) $assoc['output']) : null;
        $outputPath = $outputPath !== '' ? $outputPath : null;
        $dryRun = isset($assoc['dry-run']);
        $noDuplicateCheck = isset($assoc['no-duplicate-check']);
        $existingFrom = isset($assoc['existing-from']) ? trim((string) $assoc['existing-from']) : null;
        $existingFromWp = isset($assoc['existing-from-wp']);
        // Default: use WordPress catalog to avoid duplicates unless --no-duplicate-check or --existing-from is set.
        if (! $noDuplicateCheck && $existingFrom === null) {
            $existingFromWp = true;
        }

        $existingBrandModels = $noDuplicateCheck ? [] : $this->aiGenerateSeedLoadExisting($existingFrom, $existingFromWp);
        if ($existingBrandModels !== []) {
            $total = 0;
            foreach ($existingBrandModels as $list) {
                $total += count($list);
            }
            \WP_CLI::log(sprintf('Duplication check: %d existing brand/model(s) will be excluded.', $total));
        }

        $result = $this->seedGenerator->generate($count, $brands, $providerId, $existingBrandModels);

        foreach ($result['errors'] as $err) {
            \WP_CLI::warning($err);
        }
        if (! $result['success']) {
            if ($result['models_generated'] === 0 && $result['errors'] !== []) {
                \WP_CLI::error(implode(' ', $result['errors']));
                return;
            }
        }
        if ($result['models_generated'] === 0) {
            \WP_CLI::warning('No valid models generated. Check prompt or try a different provider.');
            return;
        }

        $json = wp_json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! is_string($json)) {
            \WP_CLI::error('Failed to encode generated data.');
            return;
        }

        if ($dryRun) {
            \WP_CLI::success(sprintf('Generated %d model(s). Dry-run: not writing file.', $result['models_generated']));
            \WP_CLI::line($json);
            return;
        }

        if ($outputPath !== null) {
            $base = $this->repository->rootPath();
            $full = strpos($outputPath, '/') === 0 ? $outputPath : $base . '/' . ltrim($outputPath, '/');
            $dir = dirname($full);
            if (! is_dir($dir)) {
                wp_mkdir_p($dir);
            }
            if (file_put_contents($full, $json) === false) {
                \WP_CLI::error('Failed to write file: ' . $full);
                return;
            }
            \WP_CLI::success(sprintf('Generated %d model(s). Written to: %s', $result['models_generated'], $full));
            return;
        }

        \WP_CLI::line($json);
        \WP_CLI::success(sprintf('Generated %d model(s). Merge into data/helmets/master.json or use as --source-json with create_helmets_seed.php.', $result['models_generated']));
    }

    /**
     * Generate accessory catalog data using the AI module.
     *
     * ## OPTIONS
     * [--count=<n>]
     * : Number of accessories to generate. Default: 5
     * [--category=<name>]
     * : Restrict to one category (e.g. "Pinlock Inserts", "Bluetooth Headsets"). Can be repeated.
     * [--categories=<list>]
     * : Comma-separated categories.
     * [--provider=<id>]
     * : AI provider (e.g. groq). Default: first enabled.
     * [--output=<path>]
     * : Write JSON array to this path (relative to data root). Omit to print stdout.
     * [--existing-from=<path>]
     * : JSON file with existing accessory titles (one per line or array of objects with title) to avoid duplicates.
     * [--dry-run]
     * : Show result only; do not write file.
     *
     * ## EXAMPLES
     *     wp helmetsan ai generate-accessories --count=10 --category="Bluetooth Headsets"
     *     wp helmetsan ai generate-accessories --count=5 --output=accessories/generated.json
     */
    public function aiGenerateAccessories(array $args, array $assoc): void
    {
        if ($this->accessoryGenerator === null || $this->repository === null) {
            \WP_CLI::error('Accessory generator or repository not available.');
            return;
        }
        $count = isset($assoc['count']) ? max(1, min(50, (int) $assoc['count'])) : 5;
        $catOpt = $assoc['category'] ?? $assoc['categories'] ?? null;
        $categories = [];
        if ($catOpt !== null) {
            $categories = is_array($catOpt) ? array_map('trim', $catOpt) : array_map('trim', explode(',', (string) $catOpt));
            $categories = array_values(array_filter($categories));
        }
        $providerId = isset($assoc['provider']) ? trim((string) $assoc['provider']) : null;
        $providerId = $providerId !== '' ? $providerId : null;
        $outputPath = isset($assoc['output']) ? trim((string) $assoc['output']) : null;
        $outputPath = $outputPath !== '' ? $outputPath : null;
        $dryRun = isset($assoc['dry-run']);
        $existingFrom = isset($assoc['existing-from']) ? trim((string) $assoc['existing-from']) : null;
        $existingTitles = [];
        if ($existingFrom !== null && $existingFrom !== '' && $this->repository !== null) {
            $full = strpos($existingFrom, '/') === 0 ? $existingFrom : $this->repository->rootPath() . '/' . ltrim($existingFrom, '/');
            if (is_file($full)) {
                $raw = file_get_contents($full);
                if ($raw !== false) {
                    $data = json_decode($raw, true);
                    if (is_array($data)) {
                        foreach ($data as $row) {
                            $existingTitles[] = is_array($row) && isset($row['title']) ? (string) $row['title'] : (string) $row;
                        }
                    }
                }
            }
        }
        $result = $this->accessoryGenerator->generate($count, $categories, $providerId, $existingTitles);
        foreach ($result['errors'] as $err) {
            \WP_CLI::warning($err);
        }
        if ($result['generated'] === 0 && $result['errors'] !== []) {
            \WP_CLI::error(implode(' ', $result['errors']));
            return;
        }
        if ($result['generated'] === 0) {
            \WP_CLI::warning('No valid accessories generated.');
            return;
        }
        $json = wp_json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        if (! is_string($json)) {
            \WP_CLI::error('Failed to encode.');
            return;
        }
        if ($dryRun) {
            \WP_CLI::success(sprintf('Generated %d accessory(ies). Dry-run.', $result['generated']));
            \WP_CLI::line($json);
            return;
        }
        if ($outputPath !== null) {
            $base = $this->repository->rootPath();
            $full = strpos($outputPath, '/') === 0 ? $outputPath : $base . '/' . ltrim($outputPath, '/');
            $dir = dirname($full);
            if (! is_dir($dir)) {
                wp_mkdir_p($dir);
            }
            if (file_put_contents($full, $json) === false) {
                \WP_CLI::error('Failed to write: ' . $full);
                return;
            }
            \WP_CLI::success(sprintf('Generated %d accessory(ies). Written to: %s', $result['generated'], $full));
            return;
        }
        \WP_CLI::line($json);
        \WP_CLI::success(sprintf('Generated %d accessory(ies). Ingest with: wp helmetsan ingest --path=accessories', $result['generated']));
    }

    /**
     * Generate both helmets and accessories in one run (separate AI calls).
     *
     * ## OPTIONS
     * [--helmets-count=<n>]
     * : Number of helmet models to generate. Default: 5. Use 0 to skip helmets.
     * [--accessories-count=<n>]
     * : Number of accessories to generate. Default: 5. Use 0 to skip accessories.
     * [--output-dir=<path>]
     * : Directory under data root to write helmets/generated.json and accessories/generated.json. Omit to print both to stdout.
     * [--provider=<id>]
     * : AI provider for both (e.g. groq).
     * [--dry-run]
     * : Run both generators; do not write files.
     *
     * ## EXAMPLES
     *     wp helmetsan ai generate-all --helmets-count=10 --accessories-count=10 --output-dir=.
     *     wp helmetsan ai generate-all --helmets-count=5 --accessories-count=0
     */
    public function aiGenerateAll(array $args, array $assoc): void
    {
        $helmetsCount = isset($assoc['helmets-count']) ? max(0, min(50, (int) $assoc['helmets-count'])) : 5;
        $accessoriesCount = isset($assoc['accessories-count']) ? max(0, min(50, (int) $assoc['accessories-count'])) : 5;
        $outputDir = isset($assoc['output-dir']) ? trim((string) $assoc['output-dir']) : null;
        $outputDir = $outputDir !== '' ? $outputDir : null;
        $providerId = isset($assoc['provider']) ? trim((string) $assoc['provider']) : null;
        $providerId = $providerId !== '' ? $providerId : null;
        $dryRun = isset($assoc['dry-run']);
        if ($helmetsCount === 0 && $accessoriesCount === 0) {
            \WP_CLI::error('Set at least one of --helmets-count or --accessories-count.');
            return;
        }
        if ($helmetsCount > 0 && $this->seedGenerator === null) {
            \WP_CLI::error('Seed generator not available.');
            return;
        }
        if ($accessoriesCount > 0 && $this->accessoryGenerator === null) {
            \WP_CLI::error('Accessory generator not available.');
            return;
        }
        $base = $this->repository !== null ? $this->repository->rootPath() : '';
        $helmetPath = $outputDir !== null && $helmetsCount > 0 ? $outputDir . '/helmets/generated.json' : null;
        $accessoryPath = $outputDir !== null && $accessoriesCount > 0 ? $outputDir . '/accessories/generated.json' : null;
        if ($helmetsCount > 0) {
            \WP_CLI::log('--- Generating helmets ---');
            $existing = $this->aiGenerateSeedLoadExisting(null, false);
            $result = $this->seedGenerator->generate($helmetsCount, [], $providerId, $existing);
            foreach ($result['errors'] as $err) {
                \WP_CLI::warning($err);
            }
            if ($result['models_generated'] > 0) {
                $json = wp_json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if (is_string($json)) {
                    if (! $dryRun && $helmetPath !== null && $base !== '') {
                        $full = strpos($helmetPath, '/') === 0 ? $helmetPath : $base . '/' . ltrim($helmetPath, '/');
                        $dir = dirname($full);
                        if (! is_dir($dir)) {
                            wp_mkdir_p($dir);
                        }
                        file_put_contents($full, $json);
                        \WP_CLI::success(sprintf('Helmets: %d model(s) -> %s', $result['models_generated'], $full));
                    } else {
                        \WP_CLI::success(sprintf('Helmets: %d model(s) generated.', $result['models_generated']));
                    }
                }
            }
        }
        if ($accessoriesCount > 0) {
            \WP_CLI::log('--- Generating accessories ---');
            $result = $this->accessoryGenerator->generate($accessoriesCount, [], $providerId, []);
            foreach ($result['errors'] as $err) {
                \WP_CLI::warning($err);
            }
            if ($result['generated'] > 0) {
                $json = wp_json_encode($result['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                if (is_string($json)) {
                    if (! $dryRun && $accessoryPath !== null && $base !== '') {
                        $full = strpos($accessoryPath, '/') === 0 ? $accessoryPath : $base . '/' . ltrim($accessoryPath, '/');
                        $dir = dirname($full);
                        if (! is_dir($dir)) {
                            wp_mkdir_p($dir);
                        }
                        file_put_contents($full, $json);
                        \WP_CLI::success(sprintf('Accessories: %d item(s) -> %s', $result['generated'], $full));
                    } else {
                        \WP_CLI::success(sprintf('Accessories: %d item(s) generated.', $result['generated']));
                    }
                }
            }
        }
        \WP_CLI::success('generate-all complete.');
    }

    /**
     * Load existing brand/model list for duplicate checking.
     * @return array<string, list<string>> brand => [model names]
     */
    private function aiGenerateSeedLoadExisting(?string $existingFrom, bool $existingFromWp): array
    {
        $out = [];
        if ($existingFrom !== null && $existingFrom !== '' && $this->repository !== null) {
            $base = $this->repository->rootPath();
            $full = strpos($existingFrom, '/') === 0 ? $existingFrom : $base . '/' . ltrim($existingFrom, '/');
            if (is_file($full)) {
                $raw = file_get_contents($full);
                if ($raw !== false) {
                    $data = json_decode($raw, true);
                    if (is_array($data)) {
                        foreach ($data as $brand => $models) {
                            if ($brand === '_comment' || ! is_array($models)) {
                                continue;
                            }
                            $out[$brand] = array_keys($models);
                        }
                    }
                }
            }
        }
        if ($existingFromWp) {
            $parents = get_posts([
                'post_type'      => 'helmet',
                'post_status'    => 'publish',
                'post_parent'    => 0,
                'posts_per_page' => -1,
                'fields'         => 'ids',
            ]);
            $ids = is_array($parents) ? array_map('intval', $parents) : [];
            foreach ($ids as $postId) {
                $terms = wp_get_object_terms($postId, 'helmet_brand');
                $brand = '';
                if (is_array($terms) && isset($terms[0]) && $terms[0] instanceof \WP_Term) {
                    $brand = $terms[0]->name;
                }
                $post = get_post($postId);
                $title = $post instanceof \WP_Post ? $post->post_title : '';
                $model = $title;
                if ($brand !== '' && $title !== '' && stripos($title, $brand) === 0) {
                    $model = trim(substr($title, strlen($brand)));
                }
                if ($brand !== '' && $model !== '') {
                    $out[$brand] = $out[$brand] ?? [];
                    if (! in_array($model, $out[$brand], true)) {
                        $out[$brand][] = $model;
                    }
                }
            }
        }
        return $out;
    }

    /**
     * Suggest and optionally write internal links (outgoing_internal_links_json) for helmets, brands, accessories.
     *
     * ## OPTIONS
     * [--post-type=<type>]
     * : helmet|brand|accessory|all. Default: all
     * [--limit=<n>]
     * : Max posts to process per type (0 = no limit). Default: 0
     * [--offset=<n>]
     * : Offset for pagination. Default: 0
     * [--dry-run]
     * : Do not save; only report counts
     * [--report]
     * : Print analytic report after run: links by reason, total links, avg per post. Use with --dry-run for report-only.
     *
     * ## EXAMPLES
     *     wp helmetsan ai cross-link --post-type=helmet --limit=50
     *     wp helmetsan ai cross-link --dry-run --report
     */
    public function aiCrossLink(array $args, array $assoc): void
    {
        $postType = (string) ($assoc['post-type'] ?? 'all');
        $limit = isset($assoc['limit']) ? max(0, (int) $assoc['limit']) : 0;
        $offset = isset($assoc['offset']) ? max(0, (int) $assoc['offset']) : 0;
        $dryRun = isset($assoc['dry-run']);
        $report = isset($assoc['report']);
        $allowed = ['helmet', 'brand', 'accessory', 'all'];
        if (! in_array($postType, $allowed, true)) {
            \WP_CLI::error('Invalid --post-type. Use: helmet, brand, accessory, or all.');
            return;
        }
        $service = new CrossLinkService();
        $result = $service->run($postType, $limit, $offset, $dryRun);
        \WP_CLI::success(sprintf(
            'Cross-link: %s %d, skipped %d, total %d.',
            $dryRun ? 'Would update' : 'Updated',
            $result['updated'],
            $result['skipped'],
            $result['total']
        ));
        $totalLinks = (int) ($result['total_links'] ?? 0);
        $postsWithLinks = (int) ($result['posts_with_links'] ?? 0);
        $byReason = $result['by_reason'] ?? [];
        if ($report || $totalLinks > 0 || $byReason !== []) {
            \WP_CLI::log(sprintf('Total links suggested: %d (posts with links: %d, avg %.1f per post)', $totalLinks, $postsWithLinks, $postsWithLinks > 0 ? $totalLinks / $postsWithLinks : 0));
            if ($byReason !== []) {
                $rows = [];
                foreach ($byReason as $reason => $count) {
                    $rows[] = [$reason, (string) $count];
                }
                \WP_CLI\Utils\format_items('table', $rows, ['reason', 'count']);
            }
        }
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
     * : sync_pull|retry_failed|cleanup_logs|health_snapshot|ingestion|enrichment
     */
    public function schedulerRun(array $args, array $assoc): void
    {
        $task = isset($assoc['task']) ? (string) $assoc['task'] : '';
        if ($task === '') {
            \WP_CLI::error('Provide --task=<sync_pull|retry_failed|cleanup_logs|health_snapshot|ingestion|enrichment>');
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
     * Match helmets with product images (EAN, RevZilla, optional AI), then import via Media Engine.
     *
     * Tries in order: (1) EAN/GTIN/UPC from post meta → EAN-DB/eandata; (2) stored RevZilla
     * product URL (affiliate_links_json) → fetch product page and extract image; (3) when
     * --use-ai is set, AI to resolve EAN or image URL. Sideloads into Media Library and sets featured image.
     *
     * ## OPTIONS
     * [--limit=<n>]
     * : Max helmets to process. Default: 0 (no limit).
     * [--all]
     * : Process all helmets (including those that already have a featured image).
     * [--use-ai]
     * : When a helmet has no EAN/GTIN, use AI to resolve EAN or image URL.
     * [--gallery]
     * : Import additional images as gallery assets.
     * [--search]
     * : Use AI search for manufacturer images if other methods fail.
     * [--no-high-res]
     * : Disable automatic high-resolution image upgrades (900px+).
     * [--no-ean]
     * : Disable EAN/GTIN barcode lookup.
     * [--no-revzilla]
     * : Disable RevZilla product page scraping.
     * [--dry-run]
     * : Do not sideload or set thumbnails; only report what would be done.
     * [--schedule]
     * : Offload task to the background scheduler.
     * [--verbose]
     * : Log each helmet result (filled/skipped/error).
     *
     * ## EXAMPLES
     *     wp helmetsan helmet-images --limit=50
     *     wp helmetsan helmet-images --limit=20 --use-ai --dry-run
     *     wp helmetsan helmet-images --all --use-ai
     *     wp helmetsan helmet-images --no-ean --use-revzilla
     */
    public function helmetImages(array $args, array $assoc): void
    {
        $limit            = isset($assoc['limit']) ? max(0, (int) $assoc['limit']) : 0;
        $onlyMissingThumb = ! isset($assoc['all']);
        $useAi            = isset($assoc['use-ai']);
        $gallery          = isset($assoc['gallery']);
        $search           = isset($assoc['search']);
        $highRes          = ! isset($assoc['no-high-res']);
        $useEan           = ! isset($assoc['no-ean']);
        $useRevZilla      = ! isset($assoc['no-revzilla']);
        $dryRun           = isset($assoc['dry-run']);
        $schedule         = isset($assoc['schedule']);
        $verbose          = isset($assoc['verbose']);

        if (($useAi || $search) && $this->aiService === null) {
            \WP_CLI::error('AI is required for --use-ai or --search but no AI provider is configured. Run wp helmetsan api-check.');
            return;
        }

        if ($schedule) {
            wp_schedule_single_event(time(), \Helmetsan\Core\Scheduler\SchedulerService::HOOK_IMAGE_ENRICHMENT);
            \WP_CLI::success('Image enrichment task scheduled to run in the background.');
            return;
        }

        $revZilla   = new \Helmetsan\Core\Media\RevZillaImageService();
        $enrichment = new HelmetImageEnrichmentService($this->media, $this->aiService, $revZilla);
        $verbose    = isset($assoc['verbose']);
        $progress   = static function (string $event, int $helmetId, string $message) use ($verbose): void {
            if ($verbose || $event === 'error') {
                \WP_CLI::line(sprintf('[%s] Helmet %d: %s', $event, $helmetId, $message));
            }
        };

        $msg = 'Running helmet image enrichment' . ($dryRun ? ' [dry-run]' : '');
        $msg .= $useAi ? ' (+AI)' : '';
        $msg .= $gallery ? ' (+Gallery)' : '';
        $msg .= $search ? ' (+Search)' : '';
        \WP_CLI::line($msg . '...');

        $stats = $enrichment->run(
            $limit,
            $onlyMissingThumb,
            $useAi,
            $dryRun,
            $progress,
            $useEan,
            $useRevZilla,
            $useAi, // useAiForEanOrImage
            $gallery,
            $search,
            $highRes
        );
        \WP_CLI::success(sprintf(
            'Processed: %d, Filled: %d, Skipped: %d, Errors: %d',
            $stats['processed'],
            $stats['filled'],
            $stats['skipped'],
            $stats['errors']
        ));
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
                
                $this->priceHistory->record($id, 'amazon-us', 'US', 'USD', $price, null, $date);

                // Sometimes add a second marketplace
                if (rand(0, 1)) {
                    $price2 = $basePrice * (1 + (rand(-15, 5) / 100));
                    $this->priceHistory->record($id, 'revzilla', 'US', 'USD', $price2, null, $date);
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
