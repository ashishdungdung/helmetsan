<?php

declare(strict_types=1);

namespace Helmetsan\Core\Core;

use Helmetsan\Core\Accessory\AccessoryService;
use Helmetsan\Core\Admin\Admin;
use Helmetsan\Core\Admin\AiAdmin;
use Helmetsan\Core\AI\AiService;
use Helmetsan\Core\AI\ProviderRegistry;
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
use Helmetsan\Core\Media\MediaEngine;
use Helmetsan\Core\Media\MediaService;
use Helmetsan\Core\Motorcycle\MotorcycleService;
use Helmetsan\Core\Repository\JsonRepository;
use Helmetsan\Core\Revenue\RevenueService;
use Helmetsan\Core\Recommendation\RecommendationService;
use Helmetsan\Core\SafetyStandard\SafetyStandardService;
use Helmetsan\Core\Scheduler\SchedulerService;
use Helmetsan\Core\Seed\Seeder;
use Helmetsan\Core\Seo\SchemaService;
use Helmetsan\Core\Support\AdSense;
use Helmetsan\Core\Support\AdsTxt;
use Helmetsan\Core\Support\Config;
use Helmetsan\Core\Support\DefaultImages;
use Helmetsan\Core\Support\Logger;
use Helmetsan\Core\Sync\LogRepository as SyncLogRepository;
use Helmetsan\Core\Sync\SyncService;
use Helmetsan\Core\Validation\Validator;
use Helmetsan\Core\Analytics\Tracker;
use Helmetsan\Core\WooBridge\WooBridgeService;
use Helmetsan\Core\API\BrandController;
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
use Helmetsan\Core\Marketplace\MarketplaceRouter;
use Helmetsan\Core\Geo\GeoService;
use Helmetsan\Core\Price\PriceHistory;
use Helmetsan\Core\API\PriceController;
use Helmetsan\Core\Marketplace\FeedIngestionTask;
use Helmetsan\Core\Admin\RevenueDashboard;
use Helmetsan\Core\Core\DatabaseManager;

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
    private PriceController $priceApi;
    private FeedIngestionTask $feedTask;
    private RevenueDashboard $revenueDashboard;
    private DefaultImages $defaultImages;
    private AdsTxt $adsTxt;
    private AdSense $adSense;
    private ProviderRegistry $providerRegistry;
    private AiService $aiService;
    private AiAdmin $aiAdmin;

    public function __construct()
    {
        $this->config     = new Config();
        $this->logger     = new Logger();
        $this->databaseManager = new DatabaseManager();
        $this->repository = new JsonRepository($this->config);
        $this->validator  = new Validator();
        $this->health     = new HealthService($this->validator, $this->repository);
        $this->seeder     = new Seeder($this->logger);
        $this->ingestionLogs = new LogRepository();
        $this->accessories = new AccessoryService();
        $this->ingestion  = new IngestionService($this->validator, $this->repository, $this->logger, $this->ingestionLogs, $this->accessories);
        $this->syncLogs   = new SyncLogRepository();
        $this->brands     = new BrandService();
        $this->motorcycles = new MotorcycleService();
        $this->safetyStandards = new SafetyStandardService();
        $this->dealers = new DealerService();
        $this->distributors = new DistributorService();
        $this->comparisons = new ComparisonService();
        $this->recommendations = new RecommendationService();
        $this->commerce = new CommerceService();
        $this->mediaEngine = new MediaEngine($this->config);
        $this->mediaService = new MediaService();
        $this->wooBridge = new WooBridgeService($this->config);
        $this->brandApi = new BrandController($this->brands);
        $this->search = new SearchService();
        $this->helmets = new HelmetService();
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
        $this->revenue    = new RevenueService($this->config, $this->geo);
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
        $this->defaultImages = new DefaultImages($this->config);
        $this->adsTxt = new AdsTxt();
        $this->adSense = new AdSense($this->config);
        $this->providerRegistry = new ProviderRegistry($this->config);
        $this->aiService = new AiService($this->providerRegistry);
        $this->aiAdmin = new AiAdmin($this->config, $this->aiService);
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

        $this->aiAdmin->register();
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
        $this->databaseManager->register();
        $this->helmetDataBlock->register();
        $this->tracker->register();
        $this->dataLayer->register();
        $this->analyticsEventService->register();
        $this->scheduler->register();
        $this->schema->register();
        $this->revenue->register();
        $this->geo->register();
        add_action('template_redirect', [$this, 'redirectAccessoryCategoryBaseToAccessories'], 1);
        $this->adsTxt->register();
        $this->adSense->register();
        $this->priceApi->register();
        $this->feedTask->register();
        $this->revenueDashboard->register();

        // Register custom cron interval
        add_filter('cron_schedules', [$this->feedTask, 'addInterval']);

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
                $this->wooBridge,
                $this->price,
                $this->priceHistory,
                $this->aiService
            ))->register();
        }
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

    public function geo(): GeoService
    {
        return $this->geo;
    }

    public function router(): MarketplaceRouter
    {
        return $this->router;
    }

    public function revenue(): RevenueService
    {
        return $this->revenue;
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

        // Amazon SP-API
        if (!empty($mktCfg['amazon_enabled'])) {
            $registry->register(new AmazonConnector([
                'client_id'         => $mktCfg['amazon_client_id'] ?? '',
                'client_secret'     => $mktCfg['amazon_client_secret'] ?? '',
                'refresh_token'     => $mktCfg['amazon_refresh_token'] ?? '',
                'affiliate_tag'     => $mktCfg['amazon_affiliate_tag'] ?? 'helmetsan-20',
                'enabled_countries' => $mktCfg['amazon_countries'] ?? ['US', 'UK', 'DE', 'IN'],
            ]));
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

        // Affiliate Feeds (RevZilla, Cycle Gear, FC-Moto)
        $feeds = $mktCfg['affiliate_feeds'] ?? [];
        if (is_array($feeds)) {
            foreach ($feeds as $feedId => $feed) {
                if (empty($feed['enabled'])) {
                    continue;
                }
                $registry->register(new AffiliateFeedConnector(
                    feedId:    (string) $feedId,
                    feedName:  (string) ($feed['name'] ?? $feedId),
                    countries: isset($feed['countries']) && is_array($feed['countries']) ? $feed['countries'] : ['US'],
                    feedConfig: $feed,
                ));
            }
        }

        return $registry;
    }
}
