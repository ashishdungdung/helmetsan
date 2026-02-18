<?php

declare(strict_types=1);

namespace Helmetsan\Core\Core;

use Helmetsan\Core\Accessory\AccessoryService;
use Helmetsan\Core\Admin\Admin;
use Helmetsan\Core\Alerts\AlertService;
use Helmetsan\Core\Analytics\EventRepository;
use Helmetsan\Core\Analytics\EventService;
use Helmetsan\Core\Analytics\SmokeTestService;
use Helmetsan\Core\Brands\BrandService;
use Helmetsan\Core\CPT\Registrar;
use Helmetsan\Core\CLI\Commands;
use Helmetsan\Core\Commerce\CommerceService;
use Helmetsan\Core\Comparison\ComparisonService;
use Helmetsan\Core\Dealer\DealerService;
use Helmetsan\Core\Docs\DocsService;
use Helmetsan\Core\Distributor\DistributorService;
use Helmetsan\Core\Frontend\HelmetDataBlock;
use Helmetsan\Core\GoLive\ChecklistService;
use Helmetsan\Core\Health\HealthService;
use Helmetsan\Core\Ingestion\IngestionService;
use Helmetsan\Core\Ingestion\LogRepository;
use Helmetsan\Core\ImportExport\ExportService;
use Helmetsan\Core\ImportExport\ImportService;
use Helmetsan\Core\Media\MediaEngine;
use Helmetsan\Core\Motorcycle\MotorcycleService;
use Helmetsan\Core\Repository\JsonRepository;
use Helmetsan\Core\Revenue\RevenueService;
use Helmetsan\Core\Recommendation\RecommendationService;
use Helmetsan\Core\SafetyStandard\SafetyStandardService;
use Helmetsan\Core\Scheduler\SchedulerService;
use Helmetsan\Core\Seed\Seeder;
use Helmetsan\Core\Seo\SchemaService;
use Helmetsan\Core\Support\Config;
use Helmetsan\Core\Support\Logger;
use Helmetsan\Core\Sync\LogRepository as SyncLogRepository;
use Helmetsan\Core\Sync\SyncService;
use Helmetsan\Core\Validation\Validator;
use Helmetsan\Core\Analytics\Tracker;
use Helmetsan\Core\WooBridge\WooBridgeService;

final class Plugin
{
    private Config $config;
    private Logger $logger;
    private JsonRepository $repository;
    private Validator $validator;
    private HealthService $health;
    private Seeder $seeder;
    private IngestionService $ingestion;
    private LogRepository $ingestionLogs;
    private SyncService $sync;
    private SyncLogRepository $syncLogs;
    private RevenueService $revenue;
    private ImportService $importService;
    private ExportService $exportService;
    private SchemaService $schema;
    private SmokeTestService $smoke;
    private EventRepository $analyticsEvents;
    private EventService $analyticsEventService;
    private SchedulerService $scheduler;
    private AlertService $alerts;
    private ChecklistService $checklist;
    private DocsService $docs;
    private HelmetDataBlock $helmetDataBlock;
    private Tracker $tracker;
    private BrandService $brands;
    private AccessoryService $accessories;
    private MotorcycleService $motorcycles;
    private SafetyStandardService $safetyStandards;
    private DealerService $dealers;
    private DistributorService $distributors;
    private ComparisonService $comparisons;
    private RecommendationService $recommendations;
    private CommerceService $commerce;
    private MediaEngine $mediaEngine;
    private WooBridgeService $wooBridge;

    public function __construct()
    {
        $this->config     = new Config();
        $this->logger     = new Logger();
        $this->repository = new JsonRepository($this->config);
        $this->validator  = new Validator();
        $this->health     = new HealthService($this->validator, $this->repository);
        $this->seeder     = new Seeder($this->logger);
        $this->ingestionLogs = new LogRepository();
        $this->ingestion  = new IngestionService($this->validator, $this->repository, $this->logger, $this->ingestionLogs);
        $this->syncLogs   = new SyncLogRepository();
        $this->brands     = new BrandService();
        $this->accessories = new AccessoryService();
        $this->motorcycles = new MotorcycleService();
        $this->safetyStandards = new SafetyStandardService();
        $this->dealers = new DealerService();
        $this->distributors = new DistributorService();
        $this->comparisons = new ComparisonService();
        $this->recommendations = new RecommendationService();
        $this->commerce = new CommerceService();
        $this->mediaEngine = new MediaEngine($this->config);
        $this->wooBridge = new WooBridgeService($this->config);
        $this->sync       = new SyncService(
            $this->repository,
            $this->logger,
            $this->config,
            $this->syncLogs,
            $this->brands,
            $this->ingestion,
            $this->accessories,
            $this->motorcycles,
            $this->safetyStandards,
            $this->dealers,
            $this->distributors,
            $this->comparisons,
            $this->recommendations,
            $this->commerce
        );
        $this->revenue    = new RevenueService($this->config);
        $this->importService = new ImportService(
            $this->ingestion,
            $this->config,
            $this->brands,
            $this->accessories,
            $this->motorcycles,
            $this->safetyStandards,
            $this->dealers,
            $this->distributors,
            $this->comparisons,
            $this->recommendations,
            $this->commerce
        );
        $this->exportService = new ExportService($this->config, $this->brands);
        $this->schema     = new SchemaService();
        $this->smoke      = new SmokeTestService();
        $this->analyticsEvents = new EventRepository();
        $this->analyticsEventService = new EventService($this->analyticsEvents);
        $this->alerts     = new AlertService($this->config);
        $this->scheduler = new SchedulerService(
            $this->config,
            $this->sync,
            $this->ingestion,
            $this->ingestionLogs,
            $this->syncLogs,
            $this->health,
            $this->alerts
        );
        $this->checklist  = new ChecklistService($this->health, $this->smoke);
        $this->docs       = new DocsService();
        $this->helmetDataBlock = new HelmetDataBlock();
        $this->tracker = new Tracker();
    }

    public function boot(): void
    {
        (new Registrar())->register();
        $this->brands->register();
        $this->mediaEngine->register();
        $this->wooBridge->register();

        (new Admin(
            $this->health,
            $this->smoke,
            $this->checklist,
            $this->docs,
            $this->config,
            $this->ingestionLogs,
            $this->ingestion,
            $this->sync,
            $this->syncLogs,
            $this->revenue,
            $this->importService,
            $this->exportService,
            $this->analyticsEvents,
            $this->scheduler,
            $this->alerts,
            $this->brands,
            $this->wooBridge
        ))->register();
        $this->helmetDataBlock->register();
        $this->tracker->register();
        $this->analyticsEventService->register();
        $this->scheduler->register();
        $this->schema->register();
        $this->revenue->register();

        if (defined('WP_CLI') && \WP_CLI) {
            (new Commands(
                $this->health,
                $this->seeder,
                $this->ingestion,
                $this->sync,
                $this->validator,
                $this->smoke,
                $this->checklist,
                $this->docs,
                $this->ingestionLogs,
                $this->importService,
                $this->exportService,
                $this->syncLogs,
                $this->schema,
                $this->revenue,
                $this->analyticsEvents,
                $this->scheduler,
                $this->alerts,
                $this->brands,
                $this->mediaEngine,
                $this->wooBridge
            ))->register();
        }
    }

    public function activate(): void
    {
        (new Registrar())->register();
        $this->ingestionLogs->ensureTable();
        $this->syncLogs->ensureTable();
        $this->revenue->ensureTable();
        $this->analyticsEvents->ensureTable();
        $this->scheduler->activate();
        flush_rewrite_rules();
    }

    public function deactivate(): void
    {
        $this->scheduler->deactivate();
        flush_rewrite_rules();
    }
}
