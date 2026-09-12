<?php

declare(strict_types=1);

namespace Helmetsan\Core\Core;

use Helmetsan\Core\Accessory\AccessoryService;
use Helmetsan\Core\Admin\Admin;
use Helmetsan\Core\Admin\AiAdmin;
use Helmetsan\Core\Admin\HelmetImagesAdmin;
use Helmetsan\Core\AI\AccessoryGeneratorService;
use Helmetsan\Core\AI\AiService;
use Helmetsan\Core\AI\HealRepository;
use Helmetsan\Core\AI\ProviderRegistry;
use Helmetsan\Core\AI\SeedGeneratorService;
use Helmetsan\Core\Alerts\AlertService;
use Helmetsan\Core\Analytics\EventRepository;
use Helmetsan\Core\Analytics\EventService;
use Helmetsan\Core\Analytics\DataLayerService;
use Helmetsan\Core\Analytics\SmokeTestService;
use Helmetsan\Core\Brands\BrandService;
use Helmetsan\Core\CPT\MetaRegistrar;
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
use Helmetsan\Core\Media\HelmetImageEnrichmentService;
use Helmetsan\Core\Media\MediaEngine;
use Helmetsan\Core\Media\MediaService;
use Helmetsan\Core\Media\AssetManager;
use Helmetsan\Core\Ingestion\ScraperService;
use Helmetsan\Core\AI\ImageAnalysisService;
use Helmetsan\Core\Media\CloudflareR2Service;
use Helmetsan\Core\Ingestion\AssetIngestionService;
use Helmetsan\Core\Admin\AssetManagerAdmin;
use Helmetsan\Core\Cloudflare\QueueService;
use Helmetsan\Core\Cloudflare\AnalyticsInjector;
use Helmetsan\Core\API\IngestionCallbackController;
use Helmetsan\Core\Media\RevZillaImageService;
use Helmetsan\Core\Motorcycle\MotorcycleService;
use Helmetsan\Core\Repository\JsonRepository;
use Helmetsan\Core\Revenue\RevenueService;
use Helmetsan\Core\Recommendation\RecommendationService;
use Helmetsan\Core\SafetyStandard\SafetyStandardService;
use Helmetsan\Core\Scheduler\SchedulerService;
use Helmetsan\Core\Seed\Seeder;
use Helmetsan\Core\Seo\SchemaService;
use Helmetsan\Core\Seo\AutoSeoObserver;
use Helmetsan\Core\Seo\SlugRedirectService;
use Helmetsan\Core\Seo\SitemapEnhancer;
use Helmetsan\Core\Seo\YoastSeoSeeder;
use Helmetsan\Core\Admin\MediaAdmin;
use Helmetsan\Core\Admin\TranslationAdmin;
use Helmetsan\Core\Seo\AiSeoDescriptionProvider;
use Helmetsan\Core\Support\AdSense;
use Helmetsan\Core\Support\AdsTxt;
use Helmetsan\Core\Support\Config;
use Helmetsan\Core\Support\DefaultImages;
use Helmetsan\Core\Support\Logger;
use Helmetsan\Core\Sync\LogRepository as SyncLogRepository;
use Helmetsan\Core\Sync\SyncService;
use Helmetsan\Core\Validation\Validator;
use Helmetsan\Core\Analytics\Tracker;
use Helmetsan\Core\Support\TaskTracker;
use Helmetsan\Core\WooBridge\WooBridgeService;
use Helmetsan\Core\API\BrandController;
use Helmetsan\Core\API\CdnController;
use Helmetsan\Core\Price\PriceService;
use Helmetsan\Core\Price\CurrencyFormatter;
use Helmetsan\Core\Search\SearchService;
use Helmetsan\Core\Helmet\HelmetService;
use Helmetsan\Core\Marketplace\ConnectorRegistry;
use Helmetsan\Core\Marketplace\Connectors\AmazonConnector;
use Helmetsan\Core\Marketplace\Connectors\AffiliateFeedConnector;
use Helmetsan\Core\Marketplace\Connectors\AllegroConnector;
use Helmetsan\Core\Marketplace\Connectors\FlipkartConnector;
use Helmetsan\Core\Marketplace\Connectors\JumiaConnector;
use Helmetsan\Core\Marketplace\Connectors\EbayConnector;
use Helmetsan\Core\Marketplace\Connectors\AliExpressConnector;
use Helmetsan\Core\Marketplace\MarketplaceRouter;
use Helmetsan\Core\Geo\GeoService;
use Helmetsan\Core\Geo\ComplianceService;
use Helmetsan\Core\Price\PriceHistory;
use Helmetsan\Core\API\PriceController;
use Helmetsan\Core\API\ReviewController;
use Helmetsan\Core\API\ApiGateway;
use Helmetsan\Core\API\DataApiController;
use Helmetsan\Core\Cloudflare\TurnstileService;
use Helmetsan\Core\Marketplace\FeedIngestionTask;
use Helmetsan\Core\Admin\RevenueDashboard;
use Helmetsan\Core\Support\BackgroundTaskService;
use Helmetsan\Core\Core\DatabaseManager;
use Helmetsan\Core\Price\ExchangeRateService;
use Helmetsan\Core\Cache\CacheWarmingService;
use Helmetsan\Core\Cache\ObjectCacheService;
use Helmetsan\Core\Revenue\ShareableLinksService;

final class Plugin
{
    private Config $config;
    private Logger $logger;
    private DatabaseManager $databaseManager;
    private JsonRepository $repository;
    private Validator $validator;
    private HealthService $health;
    private Seeder $seeder;
    private IngestionService $ingestion;
    private LogRepository $ingestionLogs;
    private SyncService $sync;
    private SyncLogRepository $syncLogs;
    private RevenueService $revenue;
    private ShareableLinksService $shareableLinks;
    private ImportService $importService;
    private ExportService $exportService;
    private SchemaService $schema;
    private SmokeTestService $smoke;
    private EventRepository $analyticsEvents;
    private EventService $analyticsEventService;
    private DataLayerService $dataLayer;
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
    private MediaService $mediaService;
    private WooBridgeService $wooBridge;
    private BrandController $brandApi;
    private SearchService $search;
    private PriceService $price;
    private CurrencyFormatter $currencyFormatter;
    private HelmetService $helmets;
    private ConnectorRegistry $marketplace;
    private GeoService $geo;
    private MarketplaceRouter $router;
    private PriceHistory $priceHistory;
    private HealRepository $heals;
    private PriceController $priceApi;
    private ReviewController $reviewApi;
    private \Helmetsan\Core\API\HelmetController $helmetApi;
    private \Helmetsan\Core\API\DeltaController $deltaApi;
    private CdnController $cdnApi;
    private FeedIngestionTask $feedTask;
    private RevenueDashboard $revenueDashboard;
    private DefaultImages $defaultImages;
    private AdsTxt $adsTxt;
    private AdSense $adSense;
    private ProviderRegistry $providerRegistry;
    private AiService $aiService;
    private SeedGeneratorService $seedGenerator;
    private AccessoryGeneratorService $accessoryGenerator;
    private AiAdmin $aiAdmin;
    private RevZillaImageService $revZillaImageService;
    private HelmetImageEnrichmentService $helmetImageEnrichment;
    private HelmetImagesAdmin $helmetImagesAdmin;
    private AssetManager $assetManager;
    private ScraperService $scraperService;
    private ImageAnalysisService $imageAnalysisService;
    private CloudflareR2Service $cloudflareR2Service;
    private AssetIngestionService $assetIngestionService;
    private AssetManagerAdmin $assetManagerAdmin;
    private QueueService $queueService;
    private AnalyticsInjector $analyticsInjector;
    private IngestionCallbackController $ingestionCallbackController;
    private AutoSeoObserver $autoSeoObserver;
    private SlugRedirectService $slugRedirects;
    private SitemapEnhancer $sitemapEnhancer;
    private AiSeoDescriptionProvider $aiSeoProvider;
    private TurnstileService $turnstileService;
    private TaskTracker $taskTracker;
    private \Helmetsan\Core\Discovery\AlternativesService $discovery;
    private \Helmetsan\Core\AI\HealService $healService;
    private \Helmetsan\Core\Media\MediaHealthService $mediaHealthService;
    private MediaAdmin $mediaAdmin;
    private TranslationAdmin $translationAdmin;
    private BackgroundTaskService $backgroundTasks;
    private ExchangeRateService $exchangeRates;
    private CacheWarmingService $cacheWarming;
    private \Helmetsan\Core\Reviews\ReviewService $reviews;
    private ApiGateway $apiGateway;
    private DataApiController $dataApi;

    public function __construct()
    {
        $this->config     = new Config();
        $this->logger     = new Logger();
        $this->databaseManager = new DatabaseManager();
        $this->taskTracker = new TaskTracker();
        $this->repository = new JsonRepository($this->config);
        $this->validator  = new Validator();
        $this->seeder     = new Seeder($this->logger);
        $this->ingestionLogs = new LogRepository();
        $this->accessories = new AccessoryService();
        $this->brands     = new BrandService();
        $this->reviews    = new \Helmetsan\Core\Reviews\ReviewService();
        $this->motorcycles = new MotorcycleService();
        $this->safetyStandards = new SafetyStandardService();
        $this->dealers = new DealerService();
        $this->distributors = new DistributorService();
        $this->comparisons = new ComparisonService();
        $this->recommendations = new RecommendationService();
        $this->ingestion  = new IngestionService(
            $this->validator,
            $this->repository,
            $this->logger,
            $this->ingestionLogs,
            $this->accessories,
            $this->brands,
            $this->motorcycles,
            $this->safetyStandards,
            $this->dealers,
            $this->distributors,
            $this->comparisons,
            $this->recommendations
        );
        $this->syncLogs   = new SyncLogRepository();
        $this->commerce = new CommerceService();
        $this->mediaEngine = new MediaEngine($this->config);
        $this->mediaHealthService = new \Helmetsan\Core\Media\MediaHealthService($this->config);
        $this->mediaService = new MediaService();
        $this->wooBridge = new WooBridgeService($this->config);
        $this->brandApi = new BrandController($this->brands);
        $this->search = new SearchService();
        $this->helmets = new HelmetService($this->config);
        $this->marketplace = $this->buildMarketplace();
        $this->geo = new GeoService();
        $this->router = new MarketplaceRouter($this->geo, $this->marketplace);
        $this->priceHistory = new PriceHistory();
        $this->currencyFormatter = new CurrencyFormatter();
        $this->price = new PriceService(
            $this->geo,
            $this->router,
            $this->priceHistory,
            $this->currencyFormatter
        );
        $this->dataLayer = new DataLayerService($this->price);
        $this->priceApi = new PriceController($this->price, $this->priceHistory);
        $this->turnstileService = new TurnstileService($this->config, $this->ingestionLogs);
        $this->reviewApi = new ReviewController($this->turnstileService, $this->reviews);
        $this->helmetApi = new \Helmetsan\Core\API\HelmetController();
        $this->deltaApi = new \Helmetsan\Core\API\DeltaController($this->repository);
        $this->cdnApi = new CdnController();
        $this->apiGateway = new ApiGateway();
        $this->dataApi = new DataApiController($this->price, $this->reviews, $this->apiGateway);
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
        $this->revenue        = new RevenueService($this->config, $this->geo);
        $this->shareableLinks = new ShareableLinksService($this->config, $this->revenue);
        $this->feedTask = new FeedIngestionTask(
            $this->config,
            $this->marketplace,
            $this->priceHistory
        );
        $this->revenueDashboard = new RevenueDashboard(
            $this->revenue,
            $this->priceHistory,
            $this->config
        );
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
        $this->schema    = new SchemaService($this->reviews);
        $this->smoke      = new SmokeTestService();
        $this->analyticsEvents = new EventRepository();
        $this->analyticsEventService = new EventService($this->analyticsEvents);
        $this->alerts     = new AlertService($this->config);
        $this->providerRegistry = new ProviderRegistry($this->config);
        $this->heals      = new HealRepository();
        $this->healService = new \Helmetsan\Core\AI\HealService($this->repository, $this->ingestion, $this->heals, $this->logger);
        $this->aiService = new AiService($this->providerRegistry, $this->heals);
        $this->aiSeoProvider = new AiSeoDescriptionProvider($this->aiService);
        $this->seedGenerator = new SeedGeneratorService($this->aiService);
        $this->accessoryGenerator = new AccessoryGeneratorService($this->aiService, $this->validator);
        $this->health = new HealthService(
            $this->validator,
            $this->repository,
            $this->aiService,
            $this->marketplace,
            $this->mediaEngine->getProductImageByEanService()
        );
        $this->backgroundTasks = new BackgroundTaskService();
        $this->exchangeRates = new ExchangeRateService();
        $this->cacheWarming = new CacheWarmingService($this->backgroundTasks);
        $this->scheduler = new SchedulerService(
            $this->config,
            $this->sync,
            $this->ingestion,
            $this->ingestionLogs,
            $this->syncLogs,
            $this->health,
            $this->alerts,
            $this->backgroundTasks,
            $this->aiService
        );
        $this->checklist  = new ChecklistService($this->health, $this->smoke);
        $this->docs       = new DocsService();
        $this->helmetDataBlock = new HelmetDataBlock();
        $this->tracker = new Tracker($this->geo);
        $this->defaultImages = new DefaultImages($this->config);
        $this->adsTxt = new AdsTxt();
        $this->adSense = new AdSense($this->config);
        $this->discovery = new \Helmetsan\Core\Discovery\AlternativesService();
        $this->revZillaImageService = new RevZillaImageService();
        $this->helmetImageEnrichment = new HelmetImageEnrichmentService(
            $this->mediaEngine,
            $this->aiService,
            $this->revZillaImageService
        );
        $this->mediaAdmin = new MediaAdmin(
            $this->config,
            $this->mediaEngine,
            $this->helmetImageEnrichment,
            $this->mediaHealthService
        );
        $this->translationAdmin = new TranslationAdmin(
            $this->config,
            $this->taskTracker
        );

        $this->aiAdmin = new AiAdmin(
            $this->config, 
            $this->aiService, 
            $this->accessoryGenerator, 
            $this->repository, 
            $this->heals,
            $this->healService,
            $this->health,
            new \Helmetsan\Core\AI\CertificationAutomatorService($this->aiService),
            $this->discovery,
            $this->mediaAdmin
        );
        $this->helmetImagesAdmin = new HelmetImagesAdmin($this->helmetImageEnrichment, $this->aiService);

        // Asset Manager / Scraper Services
        $this->assetManager = new AssetManager();
        $this->scraperService = new ScraperService();
        $this->imageAnalysisService = new ImageAnalysisService($this->providerRegistry);
        $this->cloudflareR2Service = new CloudflareR2Service($this->config);
        $this->queueService = new QueueService($this->config);
        $this->analyticsInjector = new AnalyticsInjector($this->config, $this->geo);
        $this->autoSeoObserver = new AutoSeoObserver(new YoastSeoSeeder($this->aiSeoProvider));
        $this->slugRedirects = new SlugRedirectService();
        $this->sitemapEnhancer = new SitemapEnhancer();
        $this->assetIngestionService = new AssetIngestionService(
            $this->scraperService,
            $this->imageAnalysisService,
            $this->assetManager,
            $this->mediaEngine,
            $this->cloudflareR2Service,
            $this->queueService
        );
        $this->assetManagerAdmin = new AssetManagerAdmin($this->assetIngestionService);
        $this->ingestionCallbackController = new IngestionCallbackController($this->assetManager);

        $this->analyticsInjector->bootstrap();
        (new \Helmetsan\Core\Seo\IndexNowService())->register();
    }

    public function boot(): void
    {
        (new Registrar())->register();
        (new MetaRegistrar())->register();
        $this->brands->register();
        $this->safetyStandards->register();
        $this->motorcycles->register();
        $this->dealers->register();
        $this->distributors->register();
        $this->comparisons->register();
        $this->recommendations->register();
        $this->mediaEngine->register();
        $this->mediaService->register();
        $this->wooBridge->register();
        $this->brandApi->register();
        $this->search->register();
        $this->helmets->register();

        $this->assetManagerAdmin->register();

        $this->autoSeoObserver->init();
        $this->slugRedirects->register();
        $this->sitemapEnhancer->register();

        $this->aiAdmin->register();
        $this->helmetImagesAdmin->register();
        $this->mediaAdmin->register();
        $this->translationAdmin->register();
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
            $this->wooBridge,
            $this->taskTracker,
            $this->aiAdmin
        ))->register();
        $this->databaseManager->register();
        $this->helmetDataBlock->register();
        $this->tracker->register();
        $this->dataLayer->register();
        $this->analyticsEventService->register();
        $this->scheduler->register();
        $this->schema->register();
        $this->revenue->register();
        $this->shareableLinks->register();
        $this->geo->register();
        $this->cacheWarming->register();
        ObjectCacheService::register();
        (new \Helmetsan\Core\Cloudflare\CloudflareCacheService())->registerAjaxHooks();
        add_action('template_redirect', [$this, 'redirectAccessoryCategoryBaseToAccessories'], 1);
        add_action('template_redirect', [$this, 'redirectCorruptedHelmetSlugs'], 1);
        $this->adsTxt->register();
        $this->adSense->register();
        $this->priceApi->register();
        $this->reviewApi->register();
        $this->helmetApi->register();
        $this->deltaApi->register();
        $this->cdnApi->register();
        $this->dataApi->register();
        $this->feedTask->register();
        $this->revenueDashboard->register();
        add_action('pre_get_posts', [$this->search, 'interceptMainQuery']);

        // Register custom cron interval
        add_filter('cron_schedules', [$this->feedTask, 'addInterval']);

        if (defined('WP_CLI') && WP_CLI) {
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
                $this->wooBridge,
                $this->price,
                $this->priceHistory,
                $this->taskTracker,
                $this->mediaHealthService,
                $this->config,
                $this->heals,
                $this->databaseManager,
                $this->aiService,
                $this->repository,
                $this->seedGenerator,
                $this->accessoryGenerator
            ))->register();
        }
    }

    /** For theme/archive use: faceted helmet search (parse params, build query). */
    public function getSearchService(): SearchService
    {
        return $this->search;
    }

    /** Get Config service. */
    public function config(): Config
    {
        return $this->config;
    }

    /**
     * Redirect /accessory-category/ (base URL with no term) to /accessories/.
     * WordPress has no taxonomy index at the base slug, so this avoids a 404.
     */
    public function redirectAccessoryCategoryBaseToAccessories(): void
    {
        $path = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        $path = strtok($path, '?');
        $path = $path === false ? '' : trim($path, '/');
        if ($path !== 'accessory-category') {
            return;
        }
        wp_safe_redirect(home_url('/accessories/'), 302);
        exit;
    }

    /**
     * Permanent 301 redirect for legacy URLs that contained percent-encoded or non-ASCII
     * characters in their slugs to ensure historical backlinks and bookmarks seamlessly resolve.
     */
    public function redirectCorruptedHelmetSlugs(): void
    {
        $uri = isset($_SERVER['REQUEST_URI']) ? (string) $_SERVER['REQUEST_URI'] : '';
        if (! str_contains($uri, '/helmets/')) {
            return;
        }
        $path = trim((string) strtok($uri, '?'), '/');
        $parts = explode('/', $path);
        if (count($parts) < 2 || $parts[0] !== 'helmets') {
            return;
        }
        $slug = $parts[1];
        if (! str_contains($slug, '%') && preg_match('/^[a-z0-9\-]+$/', $slug)) {
            return;
        }

        $decoded = urldecode($slug);
        $latinParts = array_values(array_filter(explode('-', (string) preg_replace('/[^a-zA-Z0-9\-]/', '-', $decoded))));
        if (! empty($latinParts)) {
            $candidatePrefix = implode('-', array_slice($latinParts, 0, min(3, count($latinParts))));
            global $wpdb;
            $match = $wpdb->get_var($wpdb->prepare(
                "SELECT post_name FROM {$wpdb->posts} WHERE post_type = 'helmet' AND post_status = 'publish' AND post_name LIKE %s ORDER BY ID DESC LIMIT 1",
                $candidatePrefix . '%'
            ));
            if ($match) {
                wp_safe_redirect(home_url('/helmets/' . $match . '/'), 301);
                exit;
            }
        }
    }

    public function activate(): void
    {
        (new Registrar())->register();
        $this->databaseManager->ensureTables();
        $this->ingestionLogs->ensureTable();
        $this->syncLogs->ensureTable();
        $this->revenue->ensureTable();
        $this->analyticsEvents->ensureTable();
        $this->priceHistory->ensureTable();
        $this->feedTask->schedule();
        $this->scheduler->activate();
        $this->ensureOptionsPreserveOnUpgrade();
        flush_rewrite_rules();
    }

    /**
     * On upgrade/activation: merge current defaults into existing options so new keys get defaults
     * but saved credentials and settings are never overwritten. Prevents settings vanishing on deploy.
     */
    private function ensureOptionsPreserveOnUpgrade(): void
    {
        $options = [
            Config::OPTION_ANALYTICS  => [$this->config, 'analyticsDefaults'],
            Config::OPTION_GITHUB     => [$this->config, 'githubDefaults'],
            Config::OPTION_REVENUE    => [$this->config, 'revenueDefaults'],
            Config::OPTION_SCHEDULER  => [$this->config, 'schedulerDefaults'],
            Config::OPTION_ALERTS     => [$this->config, 'alertsDefaults'],
            Config::OPTION_MEDIA      => [$this->config, 'mediaDefaults'],
            Config::OPTION_WOO_BRIDGE => [$this->config, 'wooBridgeDefaults'],
            Config::OPTION_MARKETPLACE => [$this->config, 'marketplaceDefaults'],
            Config::OPTION_GEO        => [$this->config, 'geoDefaults'],
            Config::OPTION_FEATURES   => [$this->config, 'featuresDefaults'],
            Config::OPTION_DEFAULT_IMAGES => [$this->config, 'defaultImagesDefaults'],
            Config::OPTION_ADSENSE => [$this->config, 'adsenseDefaults'],
            Config::OPTION_AI => [$this->config, 'aiDefaults'],
        ];
        foreach ($options as $optionKey => $defaultsCallable) {
            $existing = get_option($optionKey, null);
            if ($existing === null || ! is_array($existing)) {
                continue;
            }
            $defaults = $defaultsCallable();
            $merged = array_merge($defaults, $existing);
            if ($optionKey === Config::OPTION_AI && isset($defaults['providers'], $merged['providers']) && is_array($merged['providers'])) {
                $merged['providers'] = array_merge($defaults['providers'], $merged['providers']);
            }
            if ($merged !== $existing) {
                update_option($optionKey, $merged, false);
            }
        }
    }

    public function deactivate(): void
    {
        $this->scheduler->deactivate();
        flush_rewrite_rules();
    }

    public function price(): PriceService
    {
        return $this->price;
    }

    public function exchangeRates(): ExchangeRateService
    {
        return $this->exchangeRates;
    }

    public function cacheWarming(): CacheWarmingService
    {
        return $this->cacheWarming;
    }

    public function helmets(): HelmetService
    {
        return $this->helmets;
    }

    public function mediaService(): MediaService
    {
        return $this->mediaService;
    }

    public function marketplace(): ConnectorRegistry
    {
        return $this->marketplace;
    }

    public function priceHistory(): PriceHistory
    {
        return $this->priceHistory;
    }

    public function apiGateway(): ApiGateway
    {
        return $this->apiGateway;
    }

    public function geo(): GeoService
    {
        return $this->geo;
    }

    public function compliance(): ComplianceService
    {
        return $this->geo->compliance();
    }

    public function router(): MarketplaceRouter
    {
        return $this->router;
    }

    public function revenue(): RevenueService
    {
        return $this->revenue;
    }

    public function shareableLinks(): ShareableLinksService
    {
        return $this->shareableLinks;
    }

    public function ingestion(): IngestionService
    {
        return $this->ingestion;
    }

    public function sync(): SyncService
    {
        return $this->sync;
    }

    public function brands(): BrandService
    {
        return $this->brands;
    }

    public function reviews(): \Helmetsan\Core\Reviews\ReviewService
    {
        return $this->reviews;
    }

    public function accessories(): AccessoryService
    {
        return $this->accessories;
    }

    public function defaultImages(): DefaultImages
    {
        return $this->defaultImages;
    }

    public function motorcycles(): MotorcycleService
    {
        return $this->motorcycles;
    }

    /**
     * Build and populate the marketplace connector registry.
     */
    private function buildMarketplace(): ConnectorRegistry
    {
        $registry = new ConnectorRegistry();
        $mktCfg   = $this->config->marketplaceConfig();

        // Amazon SP-API (Legacy)
        if (!empty($mktCfg['amazon_enabled'])) {
            $registry->register(new AmazonConnector([
                'client_id'         => $mktCfg['amazon_client_id'] ?? '',
                'client_secret'     => $mktCfg['amazon_client_secret'] ?? '',
                'refresh_token'     => $mktCfg['amazon_refresh_token'] ?? '',
                'affiliate_tag'     => $mktCfg['amazon_affiliate_tag'] ?? 'vtete-20',
                'enabled_countries' => $mktCfg['amazon_countries'] ?? ['US', 'CA', 'FR', 'DE', 'IT', 'NL', 'PL', 'ES', 'SE', 'UK', 'IN'],
            ]));
        }

        // Amazon Creator API (v3.1 OAuth2)
        if (!empty($mktCfg['amazon_creator_enabled'])) {
            $revConfig = $this->config->revenueConfig();
            $allCreatorCountries = ['US', 'CA', 'UK', 'GB', 'DE', 'FR', 'IT', 'ES', 'NL', 'PL', 'SE', 'BE', 'IE', 'IN', 'JP', 'AU', 'BR', 'MX', 'AE', 'SA', 'SG', 'TR'];
            $registry->register(new \Helmetsan\Core\Marketplace\Connectors\AmazonCreatorConnector(array_merge($revConfig, [
                'client_id'         => $mktCfg['amazon_creator_client_id'] ?? '',
                'client_secret'     => $mktCfg['amazon_creator_client_secret'] ?? '',
                'version'           => $mktCfg['amazon_creator_version'] ?? 'v3.1',
                'partner_tag'       => $revConfig['amazon_tag'] ?? $mktCfg['amazon_creator_partner_tag'] ?? 'vtete-20',
                'uk_tag'            => $revConfig['amazon_tag_uk'] ?? 'vtete-21',
                'india_tag'         => $revConfig['amazon_tag_in'] ?? $mktCfg['amazon_creator_india_tag'] ?? 'virginiatete-21',
                'enabled_countries' => $mktCfg['amazon_creator_countries'] ?? $allCreatorCountries,
            ])));
        }

        // Allegro
        if (!empty($mktCfg['allegro_enabled'])) {
            $registry->register(new AllegroConnector([
                'client_id'     => $mktCfg['allegro_client_id'] ?? '',
                'client_secret' => $mktCfg['allegro_client_secret'] ?? '',
                'refresh_token' => $mktCfg['allegro_refresh_token'] ?? '',
                'affiliate_id'  => $mktCfg['allegro_affiliate_id'] ?? '',
            ]));
        }

        // Jumia
        if (!empty($mktCfg['jumia_enabled'])) {
            $registry->register(new JumiaConnector([
                'api_key'           => $mktCfg['jumia_api_key'] ?? '',
                'affiliate_id'      => $mktCfg['jumia_affiliate_id'] ?? '',
                'enabled_countries' => $mktCfg['jumia_countries'] ?? ['NG', 'KE', 'EG'],
            ]));
        }

        // Flipkart (India)
        if (!empty($mktCfg['flipkart_enabled'])) {
            $registry->register(new FlipkartConnector([
                'affiliate_id' => $mktCfg['flipkart_affiliate_id'] ?? '',
            ]));
        }

        // eBay Partner Network
        if (!empty($mktCfg['ebay_enabled'])) {
            $registry->register(new EbayConnector([
                'client_id'       => $mktCfg['ebay_client_id'] ?? '',
                'client_secret'   => $mktCfg['ebay_client_secret'] ?? '',
                'campaign_id'     => $mktCfg['ebay_campaign_id'] ?? '',
                'ebay_countries'  => $mktCfg['ebay_countries'] ?? ['US', 'GB', 'DE', 'FR', 'IT', 'ES', 'CA', 'AU'],
            ]));
        }

        // AliExpress Portals
        if (!empty($mktCfg['aliexpress_enabled'])) {
            $registry->register(new AliExpressConnector([
                'app_key'     => $mktCfg['aliexpress_app_key'] ?? '',
                'app_secret'  => $mktCfg['aliexpress_app_secret'] ?? '',
                'tracking_id' => $mktCfg['aliexpress_tracking_id'] ?? '',
            ]));
        }

        // Affiliate Feeds (RevZilla, Cycle Gear, FC-Moto)
        $feeds = $mktCfg['affiliate_feeds'] ?? [];
        if (is_array($feeds)) {
            foreach ($feeds as $feedId => $feed) {
                if (empty($feed['enabled'])) {
                    continue;
                }
                $registry->register(new AffiliateFeedConnector(
                    feedId: (string) $feedId,
                    feedName: (string) ($feed['name'] ?? $feedId),
                    countries: isset($feed['countries']) && is_array($feed['countries']) ? $feed['countries'] : ['US'],
                    feedConfig: $feed,
                ));
            }
        }

        return $registry;
    }
}
