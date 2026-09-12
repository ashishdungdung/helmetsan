<?php

declare(strict_types=1);

namespace Helmetsan\Core\Support;

final class Config
{
    public const OPTION_ANALYTICS = 'helmetsan_analytics';
    public const OPTION_ENGINE    = 'helmetsan_engine';
    public const OPTION_GITHUB    = 'helmetsan_github';
    public const OPTION_REVENUE   = 'helmetsan_revenue';
    public const OPTION_SCHEDULER = 'helmetsan_scheduler';
    public const OPTION_ALERTS    = 'helmetsan_alerts';
    public const OPTION_MEDIA     = 'helmetsan_media';
    public const OPTION_WOO_BRIDGE = 'helmetsan_woo_bridge';
    public const OPTION_MARKETPLACE = 'helmetsan_marketplace';
    public const OPTION_GEO       = 'helmetsan_geo';
    public const OPTION_FEATURES  = 'helmetsan_features';
    public const OPTION_DEFAULT_IMAGES = 'helmetsan_default_images';
    public const OPTION_ADSENSE = 'helmetsan_adsense';
    public const OPTION_AI = 'helmetsan_ai';
    public const OPTION_SECURITY = 'helmetsan_security';
    public const OPTION_PERFORMANCE = 'helmetsan_performance';
    public const OPTION_CLOUDFLARE = 'helmetsan_cloudflare';

    public function aiDefaults(): array
    {
        $localConfigPath = dirname(dirname(dirname(__DIR__))) . '/scripts/local_config.php';
        $localConfig = file_exists($localConfigPath) ? include $localConfigPath : [];
        $lmStudioUrl = $localConfig['lm_studio_base_url'] ?? 'http://192.168.2.74:1234/v1';
        $lmStudioModel = $localConfig['lm_studio_model'] ?? 'qwen/qwen3.5-9b';

        return [
            'providers' => [
                'groq' => ['enabled' => false, 'api_key' => '', 'model' => 'llama-3.1-8b-instant', 'tier' => 'free'],
                'gemini' => ['enabled' => false, 'api_key' => '', 'model' => 'gemini-1.5-flash', 'tier' => 'free'],
                'mistral' => ['enabled' => false, 'api_key' => '', 'model' => 'mistral-small-latest', 'tier' => 'free'],
                'openrouter' => ['enabled' => false, 'api_key' => '', 'model' => 'google/gemini-flash-1.5', 'tier' => 'free'],
                'huggingface' => ['enabled' => false, 'api_key' => '', 'model' => 'mistralai/Mistral-7B-Instruct-v0.2', 'tier' => 'free'],
                'together' => ['enabled' => false, 'api_key' => '', 'model' => 'meta-llama/Llama-3.2-3B-Instruct-Turbo', 'tier' => 'free'],
                'fireworks' => ['enabled' => false, 'api_key' => '', 'model' => 'accounts/fireworks/models/llama-v3p1-8b-instruct', 'tier' => 'free'],
                'cohere' => ['enabled' => false, 'api_key' => '', 'model' => 'command-r-plus', 'tier' => 'free'],
                'cloudflare' => ['enabled' => false, 'api_key' => '', 'model' => '@cf/meta/llama-3-8b-instruct', 'base_url' => '', 'tier' => 'free'],
                'lm_studio' => ['enabled' => false, 'api_key' => '', 'base_url' => $lmStudioUrl, 'model' => $lmStudioModel, 'tier' => 'free', 'concurrency' => 4],
                'openai' => ['enabled' => false, 'api_key' => '', 'model' => 'gpt-4o-mini', 'tier' => 'premium'],
                'anthropic' => ['enabled' => false, 'api_key' => '', 'model' => 'claude-sonnet-4-20250514', 'tier' => 'premium'],
                'perplexity' => ['enabled' => false, 'api_key' => '', 'model' => 'sonar', 'tier' => 'premium'],
            ],
            'default_free' => 'groq',
            'default_premium' => 'openai',
            'phase1_seo_enabled' => true,
            'phase2_fill_enabled' => false,
            'phase3_integrity_enabled' => false,
            'healing_mode' => 'local', // Options: local, server, ide
        ];
    }

    public function adsenseDefaults(): array
    {
        return [
            'enable_adsense'   => false,
            'publisher_id'    => 'ca-pub-5006746847998381',
            'enable_auto_ads' => false,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function adsenseConfig(): array
    {
        $saved = get_option(self::OPTION_ADSENSE, []);
        return wp_parse_args(is_array($saved) ? $saved : [], $this->adsenseDefaults());
    }

    public function analyticsDefaults(): array
    {
        return [
            'enable_analytics'                         => false,
            'analytics_respect_monsterinsights'       => true,
            'cf_analytics_token'                      => '',
            'ga4_measurement_id'                      => '',
            'gtm_container_id'                        => '',
            'enable_enhanced_event_tracking'          => false,
            'enable_internal_search_tracking'         => false,
            'enable_scroll_depth_tracking'            => false,
            'enable_file_download_tracking'           => false,
            'enable_email_phone_tracking'             => false,
            'enable_user_id_tracking'                 => true,
            'exclude_admins'                          => true,
            'enable_consent_gate'                     => false,
            'consent_cookie_name'                     => 'helmetsan_consent_analytics',
            'enable_heatmap_clarity'                  => false,
            'clarity_project_id'                      => '',
            'enable_heatmap_hotjar'                   => false,
            'hotjar_site_id'                          => '',
            'hotjar_version'                          => '6',
            'd1_analytics_worker_url'                 => '',
            'ga4_property_id'                         => '',
            'google_service_account_key'              => '',
            'analytics_anomaly_threshold'             => '3.0',
            'analytics_anomaly_min_sessions'          => '500',
            'analytics_anomaly_alert_email'           => '',
            'analytics_anomaly_detection_enabled'     => false,
        ];
    }

    public function dataRoot(): string
    {
        return WP_CONTENT_DIR . '/uploads/helmetsan-data';
    }

    public function engineDefaults(): array
    {
        return [
            'default_batch_size'      => 100,
            'max_batch_size'          => 500,
            'default_ai_enabled'      => false,
            'dedupe_processing'       => true,
            'skip_unchanged_entities' => true,
        ];
    }

    public function githubDefaults(): array
    {
        return [
            'enabled'      => false,
            'owner'        => '',
            'repo'         => '',
            'token'        => '',
            'branch'       => 'main',
            'remote_path'  => '',
            'sync_json_only' => true,
            'sync_run_profile' => 'pull-only',
            'sync_profile_lock' => false,
            'push_mode'    => 'commit',
            'pr_branch_prefix' => 'helmetsan-sync',
            'pr_reuse_open' => true,
            'pr_auto_merge' => false,
        ];
    }

    public function revenueDefaults(): array
    {
        return [
            'enable_redirect_tracking' => true,
            'default_affiliate_network' => 'amazon',
            'amazon_tag'               => 'vtete-20',
            'amazon_tag_uk'            => 'vtete-21',
            'amazon_tag_in'            => 'virginiatete-21',
            'amazon_tag_jp'            => 'vtete-22',
            'amazon_tag_ca'            => 'vtete-20',
            'amazon_tag_de'            => 'vtete-20',
            'amazon_tag_fr'            => 'vtete-20',
            'amazon_tag_it'            => 'vtete-20',
            'amazon_tag_es'            => 'vtete-20',
            'amazon_tag_nl'            => 'vtete-20',
            'amazon_tag_pl'            => 'vtete-20',
            'amazon_tag_se'            => 'vtete-20',
            'amazon_tag_be'            => 'vtete-20',
            'amazon_tag_au'            => 'vtete-20',
            'amazon_tag_br'            => 'vtete-20',
            'amazon_tag_mx'            => 'vtete-20',
            'amazon_tag_ae'            => 'vtete08-21',
            'amazon_tag_sa'            => 'vtete-20',
            'amazon_tag_sg'            => 'vtete-20',
            'amazon_tag_ie'            => 'vtete-21',
            'amazon_tag_tr'            => 'vtete-20',
            'amazon_onelink_enabled'   => false,
            'amazon_onelink_id'        => '',
            'amazon_onelink_parent_tag'=> 'vtete-20',
            'redirect_status_code'     => 302,
            'affiliate_networks'       => [
                'amazon'  => ['enabled' => true,  'tag' => 'vtete-20'],
                'cj'      => ['enabled' => false, 'website_id' => '', 'advertiser_id' => ''],
                'allegro' => ['enabled' => false, 'aff_id' => ''],
                'jumia'   => ['enabled' => false, 'aff_id' => ''],
                'flipkart' => ['enabled' => false, 'aff_id' => ''],
            ],
            'network_cpc' => [
                'amazon'  => 0.06,
                'cj'      => 0.04,
                'allegro' => 0.03,
                'jumia'   => 0.02,
                'flipkart' => 0.04,
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function revenueConfig(): array
    {
        $saved = get_option(self::OPTION_REVENUE, []);
        $cfg   = wp_parse_args(is_array($saved) ? $saved : [], $this->revenueDefaults());

        // Self-heal UK tag: vtete-20 was US tag mistakenly stored in older DB options
        if (empty($cfg['amazon_tag_uk']) || $cfg['amazon_tag_uk'] === 'vtete-20') {
            $cfg['amazon_tag_uk'] = 'vtete-21';
        }

        // Env-var overrides for affiliate IDs
        if (defined('HELMETSAN_CJ_WEBSITE_ID') && \HELMETSAN_CJ_WEBSITE_ID !== '') {
            $cfg['affiliate_networks']['cj']['website_id'] = (string) \HELMETSAN_CJ_WEBSITE_ID;
        }
        if (defined('HELMETSAN_ALLEGRO_AFF_ID') && \HELMETSAN_ALLEGRO_AFF_ID !== '') {
            $cfg['affiliate_networks']['allegro']['aff_id'] = (string) \HELMETSAN_ALLEGRO_AFF_ID;
        }
        if (defined('HELMETSAN_JUMIA_AFF_ID') && \HELMETSAN_JUMIA_AFF_ID !== '') {
            $cfg['affiliate_networks']['jumia']['aff_id'] = (string) \HELMETSAN_JUMIA_AFF_ID;
        }

        return $cfg;
    }

    public function schedulerDefaults(): array
    {
        return [
            'enable_scheduler'           => false,
            'sync_pull_enabled'          => false,
            'sync_pull_interval_hours'   => 6,
            'sync_pull_limit'            => 200,
            'sync_pull_apply_brands'     => true,
            'sync_pull_apply_helmets'    => false,
            'retry_failed_enabled'       => false,
            'retry_failed_limit'         => 100,
            'retry_failed_batch_size'    => 50,
            'cleanup_logs_enabled'       => true,
            'cleanup_logs_days'          => 30,
            'health_snapshot_enabled'    => true,
            'ingestion_interval_hours'   => 6,
            'r2_backups_enabled'         => false,
            'r2_backups_interval_hours'  => 24,
            // AI enrichment (scheduler)
            'enrichment_enabled'         => false, // master switch
            'enrichment_interval_hours'  => 24,
            // Per-type toggles and limits
            'enrichment_helmets_enabled'     => true,
            'enrichment_helmets_fill_limit'  => 50,
            'enrichment_helmets_seo_limit'   => 100,
            'enrichment_brands_enabled'      => false,
            'enrichment_brands_fill_limit'   => 50,
            'enrichment_brands_seo_limit'    => 100,
            'enrichment_accessories_enabled' => false,
            'enrichment_accessories_fill_limit' => 50,
            'enrichment_accessories_seo_limit'  => 100,
            // SEO for taxonomy term archives and other CPTs (from time to time)
            'enrichment_seo_terms_enabled'      => false,
            'enrichment_seo_other_cpts_enabled' => false,
            'enrichment_seo_other_cpts_limit'   => 100,
        ];
    }

    public function geoDefaults(): array
    {
        return [
            'mode'                => 'auto', // auto, force
            'force_country'       => 'US',
            'supported_countries' => [],     // Empty means use hardcoded map
        ];
    }

    public function alertsDefaults(): array
    {
        return [
            'enabled'                => false,
            'email_enabled'          => true,
            'to_email'               => '',
            'subject_prefix'         => '[Helmetsan]',
            'slack_enabled'          => false,
            'slack_webhook_url'      => '',
            'alert_on_sync_error'    => true,
            'alert_on_ingest_error'  => true,
            'alert_on_health_warning' => false,
        ];
    }

    public function mediaDefaults(): array
    {
        return [
            'enable_media_engine'   => true,
            'simpleicons_enabled'   => true,
            'brandfetch_enabled'    => true,
            'brandfetch_token'      => '',
            'logodev_enabled'       => true,
            'logodev_publishable_key' => '',
            'logodev_secret_key'    => '',
            // Backward compatibility with previous single-key setup.
            'logodev_token'         => '',
            'wikimedia_enabled'     => true,
            'cache_ttl_hours'       => 12,
            'auto_sideload_enabled' => false,
            // Product image by EAN/GTIN (EAN-DB, eandata)
            'ean_db_enabled'        => false,
            'ean_db_token'          => '',
            'eandata_enabled'       => false,
            'eandata_keycode'       => '',
            'enable_cloudflare_queues' => false,
            'r2_enabled'      => false,
            'r2_account_id'   => '',
            'r2_access_key'   => '',
            'r2_secret_key'   => '',
            'r2_bucket'       => '',
            'r2_public_url'   => '',
            'r2_image_resizing_enabled' => false,
            'r2_image_resizer_url'      => '',
        ];
    }

    public function wooBridgeDefaults(): array
    {
        return [
            'enable_bridge' => false,
            'auto_sync_on_save' => false,
            'publish_products' => false,
            'default_currency' => 'USD',
            'sync_limit_default' => 100,
        ];
    }

    public function securityDefaults(): array
    {
        return [
            'enable_turnstile' => false,
            'turnstile_site_key' => '',
            'turnstile_secret_key' => '',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function schedulerConfig(): array
    {
        $saved = get_option(self::OPTION_SCHEDULER, []);
        return wp_parse_args(is_array($saved) ? $saved : [], $this->schedulerDefaults());
    }

    /**
     * @return array<string,mixed>
     */
    public function alertsConfig(): array
    {
        $saved = get_option(self::OPTION_ALERTS, []);
        $cfg   = wp_parse_args(is_array($saved) ? $saved : [], $this->alertsDefaults());

        if (defined('HELMETSAN_ALERTS_TO_EMAIL') && \HELMETSAN_ALERTS_TO_EMAIL !== '') {
            $cfg['to_email'] = (string) \HELMETSAN_ALERTS_TO_EMAIL;
        }
        if (defined('HELMETSAN_ALERTS_SLACK_WEBHOOK') && \HELMETSAN_ALERTS_SLACK_WEBHOOK !== '') {
            $cfg['slack_webhook_url'] = (string) \HELMETSAN_ALERTS_SLACK_WEBHOOK;
        }

        return $cfg;
    }

    /**
     * @return array<string,mixed>
     */
    public function githubConfig(): array
    {
        $saved = get_option(self::OPTION_GITHUB, []);
        $cfg   = wp_parse_args(is_array($saved) ? $saved : [], $this->githubDefaults());

        if (defined('HELMETSAN_GITHUB_OWNER') && \HELMETSAN_GITHUB_OWNER !== '') {
            $cfg['owner'] = (string) \HELMETSAN_GITHUB_OWNER;
        }
        if (defined('HELMETSAN_GITHUB_REPO') && \HELMETSAN_GITHUB_REPO !== '') {
            $cfg['repo'] = (string) \HELMETSAN_GITHUB_REPO;
        }
        if (defined('HELMETSAN_GITHUB_TOKEN') && \HELMETSAN_GITHUB_TOKEN !== '') {
            $cfg['token'] = (string) \HELMETSAN_GITHUB_TOKEN;
        }
        if (defined('HELMETSAN_GITHUB_BRANCH') && \HELMETSAN_GITHUB_BRANCH !== '') {
            $cfg['branch'] = (string) \HELMETSAN_GITHUB_BRANCH;
        }
        if (defined('HELMETSAN_GITHUB_REMOTE_PATH') && \HELMETSAN_GITHUB_REMOTE_PATH !== '') {
            $cfg['remote_path'] = (string) \HELMETSAN_GITHUB_REMOTE_PATH;
        }

        return $cfg;
    }

    /**
     * @return array<string,mixed>
     */
    public function securityConfig(): array
    {
        $saved = get_option(self::OPTION_SECURITY, []);
        $cfg   = wp_parse_args(is_array($saved) ? $saved : [], $this->securityDefaults());

        if (defined('HELMETSAN_TURNSTILE_SITE_KEY') && \HELMETSAN_TURNSTILE_SITE_KEY !== '') {
            $cfg['turnstile_site_key'] = (string) \HELMETSAN_TURNSTILE_SITE_KEY;
        }
        if (defined('HELMETSAN_TURNSTILE_SECRET_KEY') && \HELMETSAN_TURNSTILE_SECRET_KEY !== '') {
            $cfg['turnstile_secret_key'] = (string) \HELMETSAN_TURNSTILE_SECRET_KEY;
        }

        return $cfg;
    }

    /**
     * @return array<string,mixed>
     */
    public function mediaConfig(): array
    {
        $saved = get_option(self::OPTION_MEDIA, []);
        $cfg   = wp_parse_args(is_array($saved) ? $saved : [], $this->mediaDefaults());

        if (defined('HELMETSAN_BRANDFETCH_TOKEN') && \HELMETSAN_BRANDFETCH_TOKEN !== '') {
            $cfg['brandfetch_token'] = (string) \HELMETSAN_BRANDFETCH_TOKEN;
        }
        if (defined('HELMETSAN_LOGODEV_TOKEN') && \HELMETSAN_LOGODEV_TOKEN !== '') {
            $cfg['logodev_token'] = (string) \HELMETSAN_LOGODEV_TOKEN;
        }
        if (defined('HELMETSAN_LOGODEV_PUBLISHABLE_KEY') && \HELMETSAN_LOGODEV_PUBLISHABLE_KEY !== '') {
            $cfg['logodev_publishable_key'] = (string) \HELMETSAN_LOGODEV_PUBLISHABLE_KEY;
        }
        if (defined('HELMETSAN_LOGODEV_SECRET_KEY') && \HELMETSAN_LOGODEV_SECRET_KEY !== '') {
            $cfg['logodev_secret_key'] = (string) \HELMETSAN_LOGODEV_SECRET_KEY;
        }

        if ((string) ($cfg['logodev_publishable_key'] ?? '') === '' && (string) ($cfg['logodev_token'] ?? '') !== '') {
            $cfg['logodev_publishable_key'] = (string) $cfg['logodev_token'];
        }
        if ((string) ($cfg['logodev_secret_key'] ?? '') === '' && (string) ($cfg['logodev_token'] ?? '') !== '') {
            $cfg['logodev_secret_key'] = (string) $cfg['logodev_token'];
        }
        if (defined('HELMETSAN_EAN_DB_TOKEN') && \HELMETSAN_EAN_DB_TOKEN !== '') {
            $cfg['ean_db_token'] = (string) \HELMETSAN_EAN_DB_TOKEN;
        }
        if (defined('HELMETSAN_EANDATA_KEYCODE') && \HELMETSAN_EANDATA_KEYCODE !== '') {
            $cfg['eandata_keycode'] = (string) \HELMETSAN_EANDATA_KEYCODE;
        }

        return $cfg;
    }

    public function defaultImagesDefaults(): array
    {
        return [
            'helmet_attachment_id'   => 0,
            'helmet_svg_slug'        => '',
            'brand_attachment_id'    => 0,
            'brand_svg_slug'         => '',
            'accessory_attachment_id' => 0,
            'accessory_svg_slug'     => '',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function defaultImagesConfig(): array
    {
        $saved = get_option(self::OPTION_DEFAULT_IMAGES, []);
        return wp_parse_args(is_array($saved) ? $saved : [], $this->defaultImagesDefaults());
    }

    public function marketplaceDefaults(): array
    {
        return [
            // Amazon SP-API (Legacy)
            'amazon_enabled'         => false,
            'amazon_client_id'       => '',
            'amazon_client_secret'   => '',
            'amazon_refresh_token'   => '',
            'amazon_affiliate_tag'   => 'vtete-20',
            'amazon_countries'       => ['US', 'CA', 'MX', 'BR', 'UK', 'DE', 'FR', 'IT', 'ES', 'NL', 'PL', 'SE', 'BE', 'IN', 'AE', 'SA', 'SG', 'AU', 'JP'],

            // Amazon Creator API (v3.1 OAuth2)
            'amazon_creator_enabled'       => true,
            'amazon_creator_client_id'     => '',
            'amazon_creator_client_secret' => '',
            'amazon_creator_version'       => 'v3.1',
            'amazon_creator_partner_tag'   => 'vtete-20',
            'amazon_creator_india_tag'     => 'virginiatete-21',
            'amazon_creator_countries'     => ['US', 'CA', 'UK', 'GB', 'DE', 'FR', 'IT', 'ES', 'NL', 'PL', 'SE', 'BE', 'IE', 'IN', 'JP', 'AU', 'BR', 'MX', 'AE', 'SA', 'SG', 'TR'],

            // Allegro
            'allegro_enabled'        => false,
            'allegro_client_id'      => '',
            'allegro_client_secret'  => '',
            'allegro_refresh_token'  => '',
            'allegro_affiliate_id'   => '',

            // Jumia
            'jumia_enabled'          => false,
            'jumia_api_key'          => '',
            'jumia_affiliate_id'     => '',
            'jumia_countries'        => ['NG', 'KE', 'EG'],

            // Flipkart (India)
            'flipkart_enabled'       => false,
            'flipkart_affiliate_id'  => '',

            // eBay Partner Network
            'ebay_enabled'           => false,
            'ebay_client_id'         => '',
            'ebay_client_secret'     => '',
            'ebay_campaign_id'       => '',
            'ebay_countries'         => ['US', 'GB', 'DE', 'FR', 'IT', 'ES', 'CA', 'AU'],

            // AliExpress Portals
            'aliexpress_enabled'     => false,
            'aliexpress_app_key'     => '',
            'aliexpress_app_secret'  => '',
            'aliexpress_tracking_id' => '',

            // Affiliate feeds keyed by feed ID
            'affiliate_feeds'        => [
                'revzilla-us' => [
                    'enabled'    => false,
                    'name'       => 'RevZilla',
                    'countries'  => ['US'],
                    'currency'   => 'USD',
                    'url'        => '',
                    'column_map' => ['price' => 'price', 'name' => 'product_name', 'url' => 'product_url', 'ean' => 'gtin'],
                    'affiliate_params' => [],
                ],
                'cyclegear-us' => [
                    'enabled'    => false,
                    'name'       => 'Cycle Gear',
                    'countries'  => ['US'],
                    'currency'   => 'USD',
                    'url'        => '',
                    'column_map' => ['price' => 'price', 'name' => 'product_name', 'url' => 'product_url', 'ean' => 'gtin'],
                    'affiliate_params' => [],
                ],
                'fc-moto-eu' => [
                    'enabled'    => false,
                    'name'       => 'FC-Moto',
                    'countries'  => ['DE', 'FR', 'IT', 'ES', 'UK'],
                    'currency'   => 'EUR',
                    'url'        => '',
                    'column_map' => ['price' => 'price', 'name' => 'product_name', 'url' => 'product_url', 'ean' => 'ean'],
                    'affiliate_params' => [],
                ],
            ],
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function marketplaceConfig(): array
    {
        $saved = get_option(self::OPTION_MARKETPLACE, []);
        $cfg   = wp_parse_args(is_array($saved) ? $saved : [], $this->marketplaceDefaults());

        // Environment variable overrides for sensitive keys
        if (defined('HELMETSAN_AMZ_CLIENT_ID') && \HELMETSAN_AMZ_CLIENT_ID !== '') {
            $cfg['amazon_client_id'] = (string) \HELMETSAN_AMZ_CLIENT_ID;
        }
        if (defined('HELMETSAN_AMZ_CLIENT_SECRET') && \HELMETSAN_AMZ_CLIENT_SECRET !== '') {
            $cfg['amazon_client_secret'] = (string) \HELMETSAN_AMZ_CLIENT_SECRET;
        }
        if (defined('HELMETSAN_AMZ_REFRESH_TOKEN') && \HELMETSAN_AMZ_REFRESH_TOKEN !== '') {
            $cfg['amazon_refresh_token'] = (string) \HELMETSAN_AMZ_REFRESH_TOKEN;
        }
        if (defined('HELMETSAN_AMZ_AFFILIATE_TAG') && \HELMETSAN_AMZ_AFFILIATE_TAG !== '') {
            $cfg['amazon_affiliate_tag'] = (string) \HELMETSAN_AMZ_AFFILIATE_TAG;
        }

        // Amazon Creator API overrides
        if (defined('HELMETSAN_AMZ_CREATOR_CLIENT_ID') && \HELMETSAN_AMZ_CREATOR_CLIENT_ID !== '') {
            $cfg['amazon_creator_client_id'] = (string) \HELMETSAN_AMZ_CREATOR_CLIENT_ID;
        }
        if (defined('HELMETSAN_AMZ_CREATOR_CLIENT_SECRET') && \HELMETSAN_AMZ_CREATOR_CLIENT_SECRET !== '') {
            $cfg['amazon_creator_client_secret'] = (string) \HELMETSAN_AMZ_CREATOR_CLIENT_SECRET;
        }
        if (defined('HELMETSAN_AMZ_CREATOR_TAG') && \HELMETSAN_AMZ_CREATOR_TAG !== '') {
            $cfg['amazon_creator_partner_tag'] = (string) \HELMETSAN_AMZ_CREATOR_TAG;
        }
        if (defined('HELMETSAN_AMZ_CREATOR_VERSION') && \HELMETSAN_AMZ_CREATOR_VERSION !== '') {
            $cfg['amazon_creator_version'] = (string) \HELMETSAN_AMZ_CREATOR_VERSION;
        }
        if (defined('HELMETSAN_ALLEGRO_CLIENT_ID') && \HELMETSAN_ALLEGRO_CLIENT_ID !== '') {
            $cfg['allegro_client_id'] = (string) \HELMETSAN_ALLEGRO_CLIENT_ID;
        }
        if (defined('HELMETSAN_ALLEGRO_CLIENT_SECRET') && \HELMETSAN_ALLEGRO_CLIENT_SECRET !== '') {
            $cfg['allegro_client_secret'] = (string) \HELMETSAN_ALLEGRO_CLIENT_SECRET;
        }
        if (defined('HELMETSAN_JUMIA_API_KEY') && \HELMETSAN_JUMIA_API_KEY !== '') {
            $cfg['jumia_api_key'] = (string) \HELMETSAN_JUMIA_API_KEY;
        }
        if (defined('HELMETSAN_FLIPKART_AFFILIATE_ID') && \HELMETSAN_FLIPKART_AFFILIATE_ID !== '') {
            $cfg['flipkart_affiliate_id'] = (string) \HELMETSAN_FLIPKART_AFFILIATE_ID;
        }
        if (defined('HELMETSAN_EBAY_CLIENT_ID') && \HELMETSAN_EBAY_CLIENT_ID !== '') {
            $cfg['ebay_client_id'] = (string) \HELMETSAN_EBAY_CLIENT_ID;
        }
        if (defined('HELMETSAN_EBAY_CLIENT_SECRET') && \HELMETSAN_EBAY_CLIENT_SECRET !== '') {
            $cfg['ebay_client_secret'] = (string) \HELMETSAN_EBAY_CLIENT_SECRET;
        }
        if (defined('HELMETSAN_EBAY_CAMPAIGN_ID') && \HELMETSAN_EBAY_CAMPAIGN_ID !== '') {
            $cfg['ebay_campaign_id'] = (string) \HELMETSAN_EBAY_CAMPAIGN_ID;
        }
        if (defined('HELMETSAN_ALIEXPRESS_APP_KEY') && \HELMETSAN_ALIEXPRESS_APP_KEY !== '') {
            $cfg['aliexpress_app_key'] = (string) \HELMETSAN_ALIEXPRESS_APP_KEY;
        }
        if (defined('HELMETSAN_ALIEXPRESS_APP_SECRET') && \HELMETSAN_ALIEXPRESS_APP_SECRET !== '') {
            $cfg['aliexpress_app_secret'] = (string) \HELMETSAN_ALIEXPRESS_APP_SECRET;
        }
        if (defined('HELMETSAN_ALIEXPRESS_TRACKING_ID') && \HELMETSAN_ALIEXPRESS_TRACKING_ID !== '') {
            $cfg['aliexpress_tracking_id'] = (string) \HELMETSAN_ALIEXPRESS_TRACKING_ID;
        }

        return $cfg;
    }
    /**
     * @return array<string,mixed>
     */
    public function geoConfig(): array
    {
        $saved = get_option(self::OPTION_GEO, []);
        return wp_parse_args(is_array($saved) ? $saved : [], $this->geoDefaults());
    }
    public function featuresDefaults(): array
    {
        return [
            'enable_technical_analysis'   => false,
            'enable_ai_chatbot'           => false,
            'enable_ajax_catalog_filters' => true,
            'enable_comparison_engine'    => true,
            'enable_geo_pricing_fallback' => true,
            'enable_real_user_web_vitals' => true,
            'enable_adblock_beacon'       => true,
            'enable_ga4_trending_badges'  => true,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function featuresConfig(): array
    {
        $saved = get_option(self::OPTION_FEATURES, []);
        return wp_parse_args(is_array($saved) ? $saved : [], $this->featuresDefaults());
    }

    public function performanceDefaults(): array
    {
        return [
            'enable_metadata_caching' => false,
            'cache_expiration_hours'  => 24,
            'enable_geoip_pricing'    => false,
            'enable_active_cache_push'=> false,
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function performanceConfig(): array
    {
        $saved = get_option(self::OPTION_PERFORMANCE, []);
        $defaults = $this->performanceDefaults();
        $config = wp_parse_args(is_array($saved) ? $saved : [], $defaults);

        // Check for Customizer override (theme-level toggle)
        $customizerToggle = get_option('helmetsan_performance_metadata_cache', null);
        if ($customizerToggle !== null) {
            $config['enable_metadata_caching'] = (bool) $customizerToggle;
        }

        // Environment overrides
        if (defined('HELMETSAN_GEO_IP_PRICING')) {
            $config['enable_geoip_pricing'] = (bool) \HELMETSAN_GEO_IP_PRICING;
        }
        if (defined('HELMETSAN_ACTIVE_CACHE_PUSH')) {
            $config['enable_active_cache_push'] = (bool) \HELMETSAN_ACTIVE_CACHE_PUSH;
        }

        return $config;
    }

    public function cloudflareDefaults(): array
    {
        return [
            'enable_edge_assembly'     => false,
            'enable_d1_reviews'        => false,
            'd1_reviews_worker_url'    => '',
            'enable_cloudflare_queues' => false,
            'enable_r2_backups'        => false,
            'enable_workers_ai'        => false,
            'workers_ai_model'         => '@cf/meta/llama-3-8b-instruct',
            'cf_zone_id'               => '',
            'cf_api_token'             => '',
            'cf_account_id'            => '',
            'cf_webhook_secret'        => '',
            'queue_name'               => 'helmetsan-ingest-queue',
            'r2_bucket'                => '',
            'r2_public_url'            => '',
        ];
    }

    /**
     * @return array<string,mixed>
     */
    public function cloudflareConfig(): array
    {
        $saved = get_option(self::OPTION_CLOUDFLARE, []);
        $cfg = wp_parse_args(is_array($saved) ? $saved : [], $this->cloudflareDefaults());

        // Environment overrides
        if (defined('HELMETSAN_CLOUDFLARE_ZONE_ID')) {
            $cfg['cf_zone_id'] = constant('HELMETSAN_CLOUDFLARE_ZONE_ID');
        }
        if (defined('HELMETSAN_CLOUDFLARE_API_TOKEN')) {
            $cfg['cf_api_token'] = constant('HELMETSAN_CLOUDFLARE_API_TOKEN');
        }
        if (defined('HELMETSAN_CLOUDFLARE_ACCOUNT_ID')) {
            $cfg['cf_account_id'] = constant('HELMETSAN_CLOUDFLARE_ACCOUNT_ID');
        }
        if (defined('HELMETSAN_WEBHOOK_SECRET')) {
            $cfg['cf_webhook_secret'] = constant('HELMETSAN_WEBHOOK_SECRET');
        }
        if (defined('HELMETSAN_CF_INGEST_QUEUE')) {
            $cfg['queue_name'] = constant('HELMETSAN_CF_INGEST_QUEUE');
        }
        if (defined('HELMETSAN_R2_BUCKET')) {
            $cfg['r2_bucket'] = constant('HELMETSAN_R2_BUCKET');
        }
        if (defined('HELMETSAN_R2_PUBLIC_URL')) {
            $cfg['r2_public_url'] = constant('HELMETSAN_R2_PUBLIC_URL');
        }

        return $cfg;
    }
}
