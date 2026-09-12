<?php

declare(strict_types=1);

namespace Helmetsan\Core\Revenue;

use Helmetsan\Core\Geo\GeoService;
use Helmetsan\Core\Support\Config;

final class RevenueService
{
    /** Country code (e.g. IN, US) → preferred Amazon marketplace ID for geo fallback */
    private const COUNTRY_TO_AMAZON_MARKETPLACE = [
        'US' => 'amazon-us',
        'CA' => 'amazon-ca',
        'FR' => 'amazon-fr',
        'DE' => 'amazon-de',
        'IT' => 'amazon-it',
        'NL' => 'amazon-nl',
        'PL' => 'amazon-pl',
        'ES' => 'amazon-es',
        'SE' => 'amazon-se',
        'UK' => 'amazon-uk',
        'GB' => 'amazon-uk',
        'IN' => 'amazon-in',
        'JP' => 'amazon-jp',
        'AU' => 'amazon-au',
        'BR' => 'amazon-br',
        'MX' => 'amazon-mx',
        'AE' => 'amazon-ae',
        'SG' => 'amazon-sg',
        'SA' => 'amazon-sa',
        'BE' => 'amazon-be',
        'IE' => 'amazon-ie',
        'TR' => 'amazon-tr',
    ];

    public function __construct(
        private readonly Config $config,
        private readonly ?GeoService $geo = null,
    ) {}

    public function register(): void
    {
        add_action('init', [$this, 'registerRewrite']);
        add_action('init', [$this, 'captureAttributionCookies']);
        add_filter('query_vars', [$this, 'registerQueryVars']);
        add_action('template_redirect', [$this, 'handleRedirect']);
        add_filter('robots_txt', [$this, 'filterRobotsTxt'], 99999, 2);
    }

    public function filterRobotsTxt(string $output, bool $public): string
    {
        $aiCrawlers = [
            'OAI-SearchBot',       // OpenAI SearchGPT & ChatGPT Search
            'ChatGPT-User',        // On-demand ChatGPT live prompt browsing
            'GPTBot',              // OpenAI web indexing
            'PerplexityBot',       // Perplexity AI Search Engine
            'ClaudeBot',           // Anthropic Claude Citations & Search
            'Claude-Web',          // Anthropic Claude live web retrieval
            'Google-Extended',     // Google Gemini & Search Generative Overviews
            'GoogleOther',         // Google Multi-modal Knowledge Graph
            'Applebot',            // Apple Intelligence & Siri Core
            'Applebot-Extended',   // Apple Intelligence web indexing
            'Bingbot',             // Microsoft Copilot & Bing AI
            'Meta-ExternalAgent',  // Meta AI Llama web search
            'Meta-ExternalFetcher',// Meta AI real-time link preview
            'Amazonbot',           // Amazon Rufus AI Shopping Assistant
            'Diffbot',             // Diffbot AI Knowledge Graph
            'Tavily',              // LangChain / Agentic Search Stack
            'YouBot',              // You.com AI Search
            'DuckAssistBot',       // DuckDuckGo AI Answers
            'Bytespider',          // ByteDance / TikTok AI Search
        ];

        $aiDirectives = "\n# ------------------------------------------------------------\n";
        $aiDirectives .= "# Generative Engine Optimization (GEO) - Authorized AI Agents\n";
        $aiDirectives .= "# ------------------------------------------------------------\n";
        foreach ($aiCrawlers as $crawler) {
            $aiDirectives .= "User-agent: {$crawler}\n";
            $aiDirectives .= "Allow: /\n";
            $aiDirectives .= "Allow: /helmets/*/\n";
            $aiDirectives .= "Allow: /comparison/\n";
            $aiDirectives .= "Allow: /brands/*/\n";
            $aiDirectives .= "Allow: /accessories/*/\n";
            $aiDirectives .= "Allow: /llms.txt\n";
            $aiDirectives .= "Allow: /llms-full.txt\n";
            $aiDirectives .= "Disallow: /go/\n";
            $aiDirectives .= "Disallow: /wp-admin/\n";
            $aiDirectives .= "Disallow: /cart/\n";
            $aiDirectives .= "Disallow: /checkout/\n";
            $aiDirectives .= "Crawl-delay: 0\n\n";
        }

        $output .= "\nUser-agent: *\nDisallow: /go/\n" . $aiDirectives;
        $output .= "# LLMs.txt AI Manifest\n";
        $output .= "Allow: /llms.txt\n";
        $output .= "Allow: /llms-full.txt\n\n";

        $sitemaps = [
            home_url('/sitemap_index.xml'),
            home_url('/sitemap-brands.xml'),
            home_url('/sitemap-comparisons.xml'),
            home_url('/sitemap-helmets-images.xml'),
        ];
        foreach ($sitemaps as $sm) {
            if (! str_contains($output, $sm)) {
                $output .= "Sitemap: " . esc_url($sm) . "\n";
            }
        }
        return $output;
    }

    /**
     * Capture first-touch referrer and UTM parameters into cookies for cross-channel conversion attribution.
     */
    public function captureAttributionCookies(): void
    {
        if (is_admin() || wp_doing_ajax() || wp_doing_cron()) {
            return;
        }

        // 1. First-touch referrer
        if (empty($_COOKIE['hs_first_referrer']) && ! empty($_SERVER['HTTP_REFERER'])) {
            $ref = esc_url_raw((string) $_SERVER['HTTP_REFERER']);
            $refHost = (string) wp_parse_url($ref, PHP_URL_HOST);
            $siteHost = (string) wp_parse_url(home_url(), PHP_URL_HOST);

            if ($refHost !== '' && strtolower($refHost) !== strtolower($siteHost)) {
                if (! headers_sent()) {
                    setcookie(
                        'hs_first_referrer',
                        $ref,
                        [
                            'expires'  => time() + (30 * DAY_IN_SECONDS),
                            'path'     => COOKIEPATH ?: '/',
                            'domain'   => COOKIE_DOMAIN ?: '',
                            'secure'   => is_ssl(),
                            'httponly' => false,
                            'samesite' => 'Lax',
                        ]
                    );
                    $_COOKIE['hs_first_referrer'] = $ref;
                }
            }
        }

        // 2. UTM parameters
        $utmKeys = ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'];
        foreach ($utmKeys as $param) {
            if (! empty($_GET[$param])) {
                $val = sanitize_text_field((string) $_GET[$param]);
                $cookieName = 'hs_' . $param;
                if (! headers_sent()) {
                    setcookie(
                        $cookieName,
                        $val,
                        [
                            'expires'  => time() + (30 * DAY_IN_SECONDS),
                            'path'     => COOKIEPATH ?: '/',
                            'domain'   => COOKIE_DOMAIN ?: '',
                            'secure'   => is_ssl(),
                            'httponly' => false,
                            'samesite' => 'Lax',
                        ]
                    );
                    $_COOKIE[$cookieName] = $val;
                }
            }
        }
    }

    /**
     * Classify traffic origin into attribution channel.
     *
     * Possible channels: 'ai_assistant', 'forum', 'social', 'search', 'email', 'direct', 'referral'
     */
    public function classifyChannel(
        string $firstReferrer = '',
        string $currentReferrer = '',
        string $utmMedium = '',
        string $utmSource = ''
    ): string {
        $utmMediumLower = strtolower(trim($utmMedium));
        $utmSourceLower = strtolower(trim($utmSource));

        // 1. Email
        if (
            preg_match('/^(email|newsletter|e-mail)$/i', $utmMediumLower)
            || preg_match('/(newsletter|email)/i', $utmSourceLower)
        ) {
            return 'email';
        }

        // Check referrer domains
        $referrers = array_filter([$firstReferrer, $currentReferrer]);
        $refHost = '';
        foreach ($referrers as $r) {
            $host = (string) wp_parse_url($r, PHP_URL_HOST);
            if ($host !== '') {
                $refHost = strtolower($host);
                break;
            }
        }

        // 2. AI Assistants
        $aiDomains = [
            'chatgpt.com',
            'chat.openai.com',
            'perplexity.ai',
            'claude.ai',
            'gemini.google.com',
            'copilot.microsoft.com',
            'you.com',
            'poe.com',
            'huggingface.co',
            'tavily.com',
            'deepseek.com',
        ];
        if (preg_match('/(chatgpt|perplexity|claude|gemini|copilot|openai)/i', $utmSourceLower)) {
            return 'ai_assistant';
        }
        foreach ($aiDomains as $ai) {
            if ($refHost !== '' && (str_ends_with($refHost, $ai) || $refHost === $ai)) {
                return 'ai_assistant';
            }
        }

        // 3. Forums & Communities
        $forumDomains = [
            'reddit.com',
            'quora.com',
            'advrider.com',
            'motorcycle-usa.com',
            'motorbikewriter.com',
            'pistonheads.com',
            'bayarearidersforum.com',
            'r1-forum.com',
            'gixxer.com',
            'kawiforums.com',
            'ducatiforum.com',
            'thumpertalk.com',
            'vitalmx.com',
            'badweatherbikers.com',
            'ironbutt.org',
            'vfrdiscussion.com',
        ];
        if (preg_match('/(reddit|forum|community)/i', $utmSourceLower)) {
            return 'forum';
        }
        if ($refHost !== '') {
            if (str_contains($refHost, 'reddit') || str_contains($refHost, 'forum')) {
                return 'forum';
            }
            foreach ($forumDomains as $fd) {
                if (str_ends_with($refHost, $fd) || $refHost === $fd) {
                    return 'forum';
                }
            }
        }

        // 4. Social Networks
        $socialDomains = [
            'youtube.com',
            'youtu.be',
            'instagram.com',
            'facebook.com',
            'm.facebook.com',
            'l.facebook.com',
            'lm.facebook.com',
            'tiktok.com',
            'twitter.com',
            'x.com',
            't.co',
            'pinterest.com',
            'threads.net',
            'linkedin.com',
            'lnkd.in',
        ];
        if (
            preg_match('/(social|paidsocial|cpc_social)/i', $utmMediumLower)
            || preg_match('/(youtube|instagram|facebook|tiktok|twitter|pinterest)/i', $utmSourceLower)
        ) {
            return 'social';
        }
        foreach ($socialDomains as $sd) {
            if ($refHost !== '' && (str_ends_with($refHost, $sd) || $refHost === $sd)) {
                return 'social';
            }
        }

        // 5. Search Engines
        $searchDomains = [
            'google.',
            'bing.com',
            'duckduckgo.com',
            'yahoo.com',
            'ecosia.org',
            'baidu.com',
            'yandex.',
            'brave.com',
            'startpage.com',
        ];
        if (preg_match('/(organic|cpc|ppc|search)/i', $utmMediumLower)) {
            return 'search';
        }
        foreach ($searchDomains as $searchEngine) {
            if ($refHost !== '' && str_contains($refHost, $searchEngine)) {
                return 'search';
            }
        }

        // 6. Direct or Site Internal
        $siteHost = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
        if ($refHost === '' || $refHost === $siteHost) {
            return 'direct';
        }

        return 'referral';
    }

    public function ensureTable(): void
    {
        global $wpdb;

        $table = $this->tableName();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            created_at datetime NOT NULL,
            helmet_id bigint(20) unsigned NOT NULL,
            marketplace_id varchar(50) NOT NULL DEFAULT '',
            click_source varchar(50) NOT NULL,
            click_intent varchar(50) NOT NULL DEFAULT 'purchase',
            affiliate_network varchar(50) NOT NULL,
            destination_url text NOT NULL,
            referer text,
            user_agent text,
            ip_hash varchar(64) DEFAULT '',
            utm_source varchar(100) NOT NULL DEFAULT '',
            utm_medium varchar(100) NOT NULL DEFAULT '',
            utm_campaign varchar(100) NOT NULL DEFAULT '',
            utm_content varchar(100) NOT NULL DEFAULT '',
            referral_channel varchar(50) NOT NULL DEFAULT 'direct',
            first_referrer text,
            PRIMARY KEY (id),
            KEY helmet_id (helmet_id),
            KEY marketplace_id (marketplace_id),
            KEY click_source (click_source),
            KEY click_intent (click_intent),
            KEY affiliate_network (affiliate_network),
            KEY referral_channel (referral_channel),
            KEY utm_source (utm_source),
            KEY created_at (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    public function tableName(): string
    {
        global $wpdb;

        return $wpdb->prefix . 'helmetsan_clicks';
    }

    public function registerRewrite(): void
    {
        add_rewrite_rule('^go/([^/]+)/?$', 'index.php?helmetsan_go=$matches[1]', 'top');
    }

    /**
     * @param array<int,string> $vars
     * @return array<int,string>
     */
    public function registerQueryVars(array $vars): array
    {
        $vars[] = 'helmetsan_go';

        return $vars;
    }

    public function handleRedirect(): void
    {
        $settings = $this->config->revenueConfig();
        $trackingEnabled = ! empty($settings['enable_redirect_tracking']);

        $slug = get_query_var('helmetsan_go');
        if (! is_string($slug) || $slug === '') {
            return;
        }

        $post = null;
        if (is_numeric($slug)) {
            $post = get_post((int) $slug);
        }
        if (! $post instanceof \WP_Post) {
            $helmets = get_posts([
                'name'           => $slug,
                'post_type'      => 'helmet',
                'posts_per_page' => 1,
                'post_status'    => 'any',
                'lang'           => '',
            ]);
            $post = !empty($helmets) ? $helmets[0] : null;
        }
        if (! $post instanceof \WP_Post) {
            $accessories = get_posts([
                'name'           => $slug,
                'post_type'      => 'accessory',
                'posts_per_page' => 1,
                'post_status'    => 'any',
                'lang'           => '',
            ]);
            $post = !empty($accessories) ? $accessories[0] : null;
        }
        if (! $post instanceof \WP_Post) {
            wp_safe_redirect(home_url('/'), 302);
            exit;
        }

        $helmetId = (int) $post->ID;
        $marketplaceId = isset($_GET['marketplace']) ? sanitize_text_field((string) $_GET['marketplace']) : '';
        $source = isset($_GET['source']) ? sanitize_text_field((string) $_GET['source']) : 'direct';
        $intent = isset($_GET['intent']) ? sanitize_text_field((string) $_GET['intent']) : 'purchase';

        // Detect user country preference (from GET, cookie, or GeoService)
        $userCountry = '';
        if (isset($_GET['country']) && is_string($_GET['country']) && strlen(trim($_GET['country'])) === 2) {
            $userCountry = strtoupper(trim($_GET['country']));
        } elseif (isset($_COOKIE['helmetsan_geo']) && is_string($_COOKIE['helmetsan_geo']) && strlen(trim($_COOKIE['helmetsan_geo'])) === 2) {
            $userCountry = strtoupper(trim($_COOKIE['helmetsan_geo']));
        } elseif (isset($_COOKIE['helmetsan_geo_country']) && is_string($_COOKIE['helmetsan_geo_country']) && strlen(trim($_COOKIE['helmetsan_geo_country'])) === 2) {
            $userCountry = strtoupper(trim($_COOKIE['helmetsan_geo_country']));
        } elseif ($this->geo !== null) {
            $userCountry = strtoupper($this->geo->getCountry());
        }
        if ($userCountry === '') {
            $userCountry = 'IN';
        }

        // If generic 'amazon' or empty was passed, map it to the user's regional Amazon marketplace!
        if ($marketplaceId === '' || $marketplaceId === 'amazon') {
            $marketplaceId = self::COUNTRY_TO_AMAZON_MARKETPLACE[$userCountry] ?? 'amazon-in';
        }

        // Try multi-network URL first (with normalized marketplace ID)
        $destination = '';
        $network = '';

        if ($marketplaceId !== '') {
            $result = $this->buildMultiNetworkUrl($helmetId, $marketplaceId, $settings);
            $destination = $result['url'];
            $network = $result['network'];
            // No stored link for this marketplace: use ASIN-based regional Amazon URL so e.g. India sees Amazon India
            if ($destination === '' && str_starts_with(strtolower($marketplaceId), 'amazon-')) {
                $destination = $this->buildLegacyAmazonUrlForRegion($helmetId, $marketplaceId, $settings);
                if ($destination !== '') {
                    $network = 'amazon';
                }
            }
            // No stored Flipkart link: redirect to Flipkart search by helmet title (India)
            if ($destination === '' && str_starts_with(strtolower($marketplaceId), 'flipkart-')) {
                $destination = $this->buildFlipkartSearchUrl($helmetId);
                if ($destination !== '') {
                    $network = 'flipkart';
                }
            }
        }

        // When no marketplace or "static": try geo-driven default from stored links
        if ($destination === '' && $this->geo !== null) {
            $preferredMp = self::COUNTRY_TO_AMAZON_MARKETPLACE[$userCountry] ?? 'amazon-in';
            
            if ($marketplaceId === '') {
                $marketplaceId = $preferredMp;
            }

            $result = $this->buildMultiNetworkUrl($helmetId, $preferredMp, $settings);
            if ($result['url'] !== '') {
                $destination = $result['url'];
                $network = $result['network'];
                $marketplaceId = $preferredMp;
            }
        }

        // Legacy fallback (ASIN / affiliate_url); for geo fallback prefer regional Amazon when possible
        if ($destination === '' && $marketplaceId !== '' && str_starts_with(strtolower($marketplaceId), 'amazon-')) {
            $destination = $this->buildLegacyAmazonUrlForRegion($helmetId, $marketplaceId, $settings);
            if ($destination !== '') {
                $network = 'amazon';
            }
        }
        if ($destination === '') {
            $destination = $this->buildLegacyUrl($helmetId, $settings);
            $network = $settings['default_affiliate_network'] ?? 'amazon';
        }

        if ($destination === '') {
            wp_safe_redirect(get_permalink($helmetId), 302);
            exit;
        }

        if ($trackingEnabled) {
            $utmSource = isset($_GET['utm_source']) ? sanitize_text_field((string) $_GET['utm_source']) : (isset($_COOKIE['hs_utm_source']) ? sanitize_text_field((string) $_COOKIE['hs_utm_source']) : '');
            $utmMedium = isset($_GET['utm_medium']) ? sanitize_text_field((string) $_GET['utm_medium']) : (isset($_COOKIE['hs_utm_medium']) ? sanitize_text_field((string) $_COOKIE['hs_utm_medium']) : '');
            $utmCampaign = isset($_GET['utm_campaign']) ? sanitize_text_field((string) $_GET['utm_campaign']) : (isset($_COOKIE['hs_utm_campaign']) ? sanitize_text_field((string) $_COOKIE['hs_utm_campaign']) : '');
            $utmContent = isset($_GET['utm_content']) ? sanitize_text_field((string) $_GET['utm_content']) : (isset($_COOKIE['hs_utm_content']) ? sanitize_text_field((string) $_COOKIE['hs_utm_content']) : '');
            $firstReferrer = isset($_COOKIE['hs_first_referrer']) ? esc_url_raw((string) $_COOKIE['hs_first_referrer']) : '';
            $currentReferrer = isset($_SERVER['HTTP_REFERER']) ? esc_url_raw((string) $_SERVER['HTTP_REFERER']) : '';

            $channel = $this->classifyChannel($firstReferrer, $currentReferrer, $utmMedium, $utmSource);

            $attribution = [
                'utm_source'       => $utmSource,
                'utm_medium'       => $utmMedium,
                'utm_campaign'     => $utmCampaign,
                'utm_content'      => $utmContent,
                'referral_channel' => $channel,
                'first_referrer'   => $firstReferrer,
            ];

            $this->logClick($helmetId, $source, $network, $destination, $marketplaceId, $intent, $attribution);
        }

        $code = isset($settings['redirect_status_code']) ? (int) $settings['redirect_status_code'] : 302;
        if (! in_array($code, [301, 302, 307, 308], true)) {
            $code = 302;
        }

        if (! headers_sent()) {
            header('X-Robots-Tag: noindex, nofollow, nosnippet, noarchive');
            header('Cache-Control: private, no-store, no-cache, must-revalidate, max-age=0');
            header('Pragma: no-cache');
            header('Expires: 0');
        }

        wp_redirect($destination, $code);
        exit;
    }

    /**
     * Build affiliate URL for a specific marketplace using affiliate_links_json.
     *
     * @param array<string,mixed> $settings
     * @return array{url: string, network: string}
     */
    public function buildMultiNetworkUrl(int $helmetId, string $marketplaceId, array $settings): array
    {
        $linksJson = (string) get_post_meta($helmetId, 'affiliate_links_json', true);
        $links = json_decode($linksJson, true);

        if (!is_array($links)) {
            return ['url' => '', 'network' => ''];
        }

        // Normalize: stored keys use hyphens (amazon-us); allow lookup by amazon_us
        $key = $marketplaceId;
        if (!isset($links[$key])) {
            $key = str_replace('_', '-', strtolower($marketplaceId));
        }
        // Fallback for regional Amazon marketplaces: if amazon-xx requested but only generic amazon exists, use it!
        if (!isset($links[$key]) && (str_starts_with($key, 'amazon-') || str_starts_with($key, 'amazon_')) && isset($links['amazon'])) {
            $key = 'amazon';
        }
        if (!isset($links[$key])) {
            return ['url' => '', 'network' => ''];
        }

        $entry = $links[$key];
        $network = $entry['network'] ?? 'direct';
        $url = $entry['url'] ?? '';

        if ($url === '') {
            return ['url' => '', 'network' => $network];
        }

        // Auto-detect network if direct or missing
        if ($network === 'direct' || $network === '') {
            if (str_starts_with($key, 'amazon-') || str_contains($url, 'amazon.')) {
                $network = 'amazon';
            } elseif (str_starts_with($key, 'flipkart-') || str_contains($url, 'flipkart.')) {
                $network = 'flipkart';
            } elseif (str_starts_with($key, 'revzilla') || str_contains($url, 'revzilla.com')) {
                $network = 'cj';
            } elseif (str_starts_with($key, 'allegro') || str_contains($url, 'allegro.')) {
                $network = 'allegro';
            } elseif (str_starts_with($key, 'jumia') || str_contains($url, 'jumia.')) {
                $network = 'jumia';
            }
        }

        $networkCfg = $settings['affiliate_networks'][$network] ?? [];

        $affiliateUrl = match ($network) {
            'amazon'   => $this->buildAmazonUrl($url, $entry, $networkCfg, $helmetId, $marketplaceId, $settings),
            'cj'       => $this->buildCjUrl($url, $entry, $networkCfg, $helmetId),
            'allegro'  => $this->buildAllegroUrl($url, $entry, $networkCfg),
            'jumia'    => $this->buildJumiaUrl($url, $entry, $networkCfg),
            'flipkart' => $this->buildFlipkartUrl($url, $entry, $networkCfg),
            default    => $url,
        };

        return ['url' => esc_url_raw($affiliateUrl), 'network' => $network];
    }

    /**
     * Get all affiliate links for a post (helmet or accessory).
     *
     * @return array<string, array{url: string, network: string, marketplace_name: string}>
     */
    public function getAffiliateLinks(int $postId): array
    {
        $linksJson = (string) get_post_meta($postId, 'affiliate_links_json', true);
        $links = json_decode($linksJson, true);

        return is_array($links) ? $links : [];
    }

    /**
     * Preferred Amazon marketplace ID for a country (for geo fallback row).
     *
     * @param string|null $country ISO country code e.g. IN, US; if null uses GeoService
     */
    public function getGeoAmazonMarketplaceId(?string $country = null): string
    {
        if ($country === null && $this->geo !== null) {
            $country = $this->geo->getCountry();
        }

        // Language-aware fallback: if country is neutral/unmatched and user is on a localized catalog page
        if (($country === null || $country === '' || $country === 'US' || $country === 'IN') && function_exists('pll_current_language')) {
            $currentLang = (string) pll_current_language();
            $langMarketplace = match ($currentLang) {
                'de' => 'amazon-de',
                'fr' => 'amazon-fr',
                'it' => 'amazon-it',
                'es' => 'amazon-es',
                'pl' => 'amazon-pl',
                'nl' => 'amazon-nl',
                'ja' => 'amazon-jp',
                default => '',
            };
            if ($langMarketplace !== '') {
                return $langMarketplace;
            }
        }

        $key = $country !== '' ? strtoupper($country) : 'US';
        if ($key === 'GB') {
            $key = 'UK';
        }

        return self::COUNTRY_TO_AMAZON_MARKETPLACE[$key] ?? 'amazon-us';
    }

    // ─── Network-specific URL builders ───────────────────────────────────

    public function getAmazonDomainForMarketplace(string $marketplaceId): string
    {
        $mp = strtolower(str_replace('_', '-', $marketplaceId));
        $domains = [
            'amazon-us' => 'https://www.amazon.com',
            'amazon'    => 'https://www.amazon.com',
            'amazon-in' => 'https://www.amazon.in',
            'amazon-uk' => 'https://www.amazon.co.uk',
            'amazon-gb' => 'https://www.amazon.co.uk',
            'amazon-de' => 'https://www.amazon.de',
            'amazon-cz' => 'https://www.amazon.de', // Amazon DE serves Czech Republic
            'amazon-at' => 'https://www.amazon.de',
            'amazon-ch' => 'https://www.amazon.de',
            'amazon-sk' => 'https://www.amazon.de',
            'amazon-hu' => 'https://www.amazon.de',
            'amazon-fr' => 'https://www.amazon.fr',
            'amazon-ca' => 'https://www.amazon.ca',
            'amazon-it' => 'https://www.amazon.it',
            'amazon-es' => 'https://www.amazon.es',
            'amazon-pt' => 'https://www.amazon.es',
            'amazon-nl' => 'https://www.amazon.nl',
            'amazon-pl' => 'https://www.amazon.pl',
            'amazon-se' => 'https://www.amazon.se',
            'amazon-be' => 'https://www.amazon.com.be',
            'amazon-jp' => 'https://www.amazon.co.jp',
            'amazon-au' => 'https://www.amazon.com.au',
            'amazon-br' => 'https://www.amazon.com.br',
            'amazon-mx' => 'https://www.amazon.com.mx',
            'amazon-ae' => 'https://www.amazon.ae',
            'amazon-sg' => 'https://www.amazon.sg',
            'amazon-sa' => 'https://www.amazon.sa',
            'amazon-ie' => 'https://www.amazon.co.uk',
            'amazon-tr' => 'https://www.amazon.com.tr',
        ];

        return $domains[$mp] ?? 'https://www.amazon.com';
    }

    private function buildAmazonUrl(string $url, array $entry, array $cfg, int $helmetId, string $marketplaceId = '', array $settings = []): string
    {
        $url = $this->normalizeAmazonSearchQuery($url, $helmetId);

        // If target marketplace specifies a region (e.g. amazon-in, amazon-de, amazon-uk, amazon-cz),
        // adjust the Amazon search URL domain so international users search on their local Amazon store!
        if ($marketplaceId !== '') {
            $targetDomain = $this->getAmazonDomainForMarketplace($marketplaceId);
            if ($targetDomain !== '' && str_contains($url, 'amazon.')) {
                $targetHost = str_replace(['https://', 'http://', '/'], '', $targetDomain);
                $parsed = parse_url($url);
                if (is_array($parsed)) {
                    $path = $parsed['path'] ?? '/s';
                    $query = $parsed['query'] ?? '';
                    $url = 'https://' . $targetHost . $path . ($query !== '' ? '?' . $query : '');
                }
            }
        }

        $tag = $entry['tag'] ?? '';

        if ($tag === '') {
            $tag = $this->getAmazonTagOverride($helmetId);
        }

        // Apply geo-specific tag from settings if available
        if ($tag === '') {
            $revConfig = ! empty($settings) ? $settings : $this->config->revenueConfig();
            $mp = strtolower(str_replace('_', '-', $marketplaceId));
            $mpCountry = str_replace('amazon-', '', $mp);
            if ($mpCountry === 'gb') {
                $mpCountry = 'uk';
            } elseif (in_array($mpCountry, ['cz', 'at', 'ch', 'sk', 'hu'], true)) {
                $mpCountry = 'de';
            } elseif ($mpCountry === 'pt') {
                $mpCountry = 'es';
            } elseif ($mp === 'amazon' || $mpCountry === 'us') {
                $mpCountry = '';
            }

            if ($mpCountry !== '' && ! empty($revConfig['amazon_tag_' . $mpCountry])) {
                $tag = (string) $revConfig['amazon_tag_' . $mpCountry];
            } else {
                $tag = (string) ($revConfig['amazon_tag'] ?? 'vtete-20');
            }
        }

        return add_query_arg('tag', $tag, $url);
    }

    /**
     * Resolve a geotargeted destination URL for a given URL and country.
     */
    public function resolveGeotargetedUrl(string $originalUrl, string $country = '', int $helmetId = 0): string
    {
        $country = strtoupper(trim($country));
        if ($country === '') {
            $country = $this->geo !== null ? strtoupper($this->geo->getCountry()) : 'IN';
        }

        // If it's a redirect /go/ URL, update or add the marketplace parameter
        if (str_contains($originalUrl, '/go/')) {
            $targetMp = self::COUNTRY_TO_AMAZON_MARKETPLACE[$country] ?? 'amazon-in';
            return add_query_arg('marketplace', $targetMp, $originalUrl);
        }

        // If it's an Amazon URL, adjust domain and associate tag
        if (str_contains($originalUrl, 'amazon.')) {
            $targetMp = self::COUNTRY_TO_AMAZON_MARKETPLACE[$country] ?? 'amazon-in';
            $targetDomain = $this->getAmazonDomainForMarketplace($targetMp);
            $parsed = wp_parse_url($originalUrl);
            if (is_array($parsed)) {
                $targetHost = str_replace(['https://', 'http://', '/'], '', $targetDomain);
                $path = $parsed['path'] ?? '/s';
                $query = $parsed['query'] ?? '';
                parse_str($query, $queryParams);

                $revConfig = $this->config->revenueConfig();
                $cCode = strtolower($country);
                if ($cCode === 'gb') {
                    $cCode = 'uk';
                }
                $tagKey = 'amazon_tag_' . $cCode;
                $tag = ! empty($revConfig[$tagKey]) ? (string) $revConfig[$tagKey] : match ($targetMp) {
                    'amazon-in' => (string) ($revConfig['amazon_tag_in'] ?? 'virginiatete-21'),
                    'amazon-uk', 'amazon-gb', 'amazon-ie' => (string) ($revConfig['amazon_tag_uk'] ?? 'vtete-21'),
                    'amazon-jp' => (string) ($revConfig['amazon_tag_jp'] ?? 'vtete-22'),
                    default     => (string) ($revConfig['amazon_tag'] ?? 'vtete-20'),
                };
                $queryParams['tag'] = $tag;

                $scheme = $parsed['scheme'] ?? 'https';
                return $scheme . '://' . $targetHost . $path . '?' . http_build_query($queryParams);
            }
        }

        return $originalUrl;
    }

    /**
     * If the URL is an Amazon search URL and the query looks like the post slug (e.g. ls2-explorer-carbon-solid-lg),
     * replace it with the post title (e.g. LS2 Explorer Carbon Solid LG) so Amazon search works properly.
     */
    private function normalizeAmazonSearchQuery(string $url, int $helmetId): string
    {
        if (! str_contains($url, '/s?') && ! str_contains($url, '/s/')) {
            return $url;
        }
        $parsed = wp_parse_url($url);
        if (! is_array($parsed) || ! isset($parsed['query'])) {
            return $url;
        }
        parse_str($parsed['query'], $params);
        $k = isset($params['k']) ? (string) $params['k'] : '';
        if ($k === '') {
            return $url;
        }
        $slug = (string) get_post_field('post_name', $helmetId);
        $kNorm = strtolower(str_replace(['-', ' '], '', $k));
        $slugNorm = strtolower(str_replace(['-', ' '], '', $slug));
        if ($slug === '' || $kNorm !== $slugNorm) {
            return $url;
        }
        $title = (string) get_post_field('post_title', $helmetId);
        if ($title === '') {
            return $url;
        }
        $params['k'] = $title;
        $newQuery = http_build_query($params);
        $path = $parsed['path'] ?? '/s';
        $host = ($parsed['scheme'] ?? 'https') . '://' . ($parsed['host'] ?? 'www.amazon.com');
        return $host . $path . '?' . $newQuery;
    }

    private function getAmazonTagOverride(int $helmetId): string
    {
        // 1. Check Brand Level
        $brands = get_the_terms($helmetId, 'helmet_brand');
        if (is_array($brands) && count($brands) > 0) {
            $brandTag = (string) get_term_meta($brands[0]->term_id, 'amazon_tag_override', true);
            if ($brandTag !== '') {
                return $brandTag;
            }
        }

        // 2. Check Category (Type) Level
        $types = get_the_terms($helmetId, 'helmet_type');
        if (is_array($types) && count($types) > 0) {
            $typeTag = (string) get_term_meta($types[0]->term_id, 'amazon_tag_override', true);
            if ($typeTag !== '') {
                return $typeTag;
            }
        }

        return '';
    }

    private function buildCjUrl(string $url, array $entry, array $cfg, int $helmetId): string
    {
        // Prevent double-wrapping if already a CJ affiliate link
        if (str_contains($url, 'anrdoezrs.net') || str_contains($url, 'cj.com') || str_contains($url, 'dpbolvw.net')) {
            return $url;
        }

        $websiteId = $cfg['website_id'] ?? '';
        $sid = $entry['sid'] ?? (string) $helmetId;
        if ($websiteId === '') {
            return $url;
        }
        return 'https://www.anrdoezrs.net/links/' . rawurlencode($websiteId)
            . '/type/dlg/sid/' . rawurlencode($sid)
            . '/' . $url;
    }

    private function buildAllegroUrl(string $url, array $entry, array $cfg): string
    {
        $affId = $entry['aff_id'] ?? $cfg['aff_id'] ?? '';
        if ($affId === '') {
            return $url;
        }
        return add_query_arg('aff_id', $affId, $url);
    }

    private function buildJumiaUrl(string $url, array $entry, array $cfg): string
    {
        $affId = $entry['aff_id'] ?? $cfg['aff_id'] ?? '';
        if ($affId === '') {
            return $url;
        }
        return add_query_arg('aff_id', $affId, $url);
    }

    private function buildFlipkartUrl(string $url, array $entry, array $cfg): string
    {
        $affId = $entry['aff_id'] ?? $cfg['aff_id'] ?? '';
        if ($affId === '') {
            $affId = $this->config->marketplaceConfig()['flipkart_affiliate_id'] ?? '';
        }
        if ($affId === '') {
            return $url;
        }
        return add_query_arg('affid', $affId, $url);
    }

    /**
     * Flipkart search URL when no product link is stored (India). Uses helmet title as search query.
     * If the title looks like a slug (e.g. shoei-x-15-marquez-7-md), converts to readable phrase for better search.
     */
    private function buildFlipkartSearchUrl(int $helmetId): string
    {
        $title = (string) get_post_field('post_title', $helmetId);
        if ($title === '') {
            return '';
        }
        $slug = (string) get_post_field('post_name', $helmetId);
        $query = $this->searchQueryFromTitleOrSlug($title, $slug);
        $settings = $this->config->revenueConfig();
        $affId = $settings['affiliate_networks']['flipkart']['aff_id'] ?? '';
        if ($affId === '') {
            $affId = $this->config->marketplaceConfig()['flipkart_affiliate_id'] ?? '';
        }
        $url = 'https://www.flipkart.com/search?q=' . rawurlencode($query);
        if ($affId !== '') {
            $url .= '&affid=' . rawurlencode($affId);
        }
        return $url;
    }

    /**
     * Prefer human-readable title for search; if title looks like a slug, convert to readable phrase.
     */
    private function searchQueryFromTitleOrSlug(string $title, string $slug): string
    {
        $trimmed = trim($title);
        if ($trimmed === '') {
            $trimmed = $slug;
        }
        if ($trimmed === '') {
            return '';
        }
        $norm = strtolower(str_replace(['-', ' '], '', $trimmed));
        $slugNorm = strtolower(str_replace(['-', ' '], '', $slug));
        if ($slug !== '' && $norm === $slugNorm) {
            return ucwords(str_replace('-', ' ', $slug));
        }
        return $trimmed;
    }

    /**
     * Whether Flipkart is enabled in Marketplace settings (so theme can show Flipkart row for IN visitors).
     */
    public function hasFlipkartEnabled(): bool
    {
        $cfg = $this->config->marketplaceConfig();
        return ! empty($cfg['flipkart_enabled']);
    }

    /**
     * Build Amazon product URL for a region when no stored marketplace_links (ASIN fallback).
     * Ensures e.g. Indian users get amazon.in with India tag.
     *
     * @param array<string,mixed> $settings
     */
    private function buildLegacyAmazonUrlForRegion(int $helmetId, string $marketplaceId, array $settings): string
    {
        $base = $this->getAmazonDomainForMarketplace($marketplaceId);

        $tag = $this->getAmazonTagOverride($helmetId);
        if ($tag === '') {
            $mp = strtolower(str_replace('_', '-', $marketplaceId));
            $mpCountry = str_replace('amazon-', '', $mp);
            if ($mpCountry === 'gb') {
                $mpCountry = 'uk';
            } elseif (in_array($mpCountry, ['cz', 'at', 'ch', 'sk', 'hu'], true)) {
                $mpCountry = 'de';
            } elseif ($mpCountry === 'pt') {
                $mpCountry = 'es';
            } elseif ($mp === 'amazon' || $mpCountry === 'us') {
                $mpCountry = '';
            }

            if ($mpCountry === 'uk') {
                $ukTag = (string) ($settings['amazon_tag_uk'] ?? 'vtete-21');
                $tag = ($ukTag === '' || $ukTag === 'vtete-20') ? 'vtete-21' : $ukTag;
            } elseif ($mpCountry !== '' && ! empty($settings['amazon_tag_' . $mpCountry])) {
                $tag = (string) $settings['amazon_tag_' . $mpCountry];
            } else {
                $tag = (string) ($settings['amazon_tag'] ?? 'vtete-20');
            }
        }

        // 1. Search by title on regional Amazon store (resilient, live catalog search that never 404s)
        $title = (string) get_post_field('post_title', $helmetId);
        if ($title !== '') {
            $slug = (string) get_post_field('post_name', $helmetId);
            $query = $this->searchQueryFromTitleOrSlug($title, $slug);
            return add_query_arg([
                'k'   => $query,
                'tag' => $tag,
            ], $base . '/s');
        }

        // 2. ASIN fallback only if title is completely absent
        $asin = (string) get_post_meta($helmetId, 'affiliate_asin', true);
        if ($asin !== '') {
            return add_query_arg('tag', $tag, $base . '/dp/' . rawurlencode($asin));
        }

        return '';
    }

    /**
     * Legacy URL builder (backward-compatible).
     *
     * @param array<string,mixed> $settings
     */
    public function buildLegacyUrl(int $helmetId, array $settings): string
    {
        $custom = (string) get_post_meta($helmetId, 'affiliate_url', true);
        if ($custom !== '') {
            return esc_url_raw($custom);
        }

        // For accessory posts: check price_json URL if available
        $priceJson = (string) get_post_meta($helmetId, 'price_json', true);
        if ($priceJson !== '') {
            $priceData = json_decode($priceJson, true);
            if (is_array($priceData) && !empty($priceData['url'])) {
                return esc_url_raw((string) $priceData['url']);
            }
        }

        $tag = $this->getAmazonTagOverride($helmetId);
        if ($tag === '') {
            $tag = $settings['amazon_tag'] ?? 'vtete-20';
        }

        // Search by title on Amazon (resilient, live catalog search that never 404s)
        $title = (string) get_post_field('post_title', $helmetId);
        if ($title !== '') {
            $slug = (string) get_post_field('post_name', $helmetId);
            $query = $this->searchQueryFromTitleOrSlug($title, $slug);
            return add_query_arg([
                'k'   => $query,
                'tag' => $tag,
            ], 'https://www.amazon.com/s');
        }

        $asin = (string) get_post_meta($helmetId, 'affiliate_asin', true);
        if ($asin !== '') {
            return add_query_arg('tag', $tag, 'https://www.amazon.com/dp/' . rawurlencode($asin));
        }

        return '';
    }

    private function logClick(
        int $helmetId,
        string $source,
        string $network,
        string $destination,
        string $marketplaceId = '',
        string $intent = 'purchase',
        array $attribution = []
    ): void {
        global $wpdb;

        if (! $this->tableExists()) {
            return;
        }

        $ip = isset($_SERVER['REMOTE_ADDR']) ? (string) $_SERVER['REMOTE_ADDR'] : '';
        $ipHash = $ip !== '' ? hash('sha256', $ip . wp_salt('auth')) : '';

        $wpdb->insert(
            $this->tableName(),
            [
                'created_at'        => current_time('mysql'),
                'helmet_id'         => $helmetId,
                'marketplace_id'    => sanitize_text_field($marketplaceId),
                'click_source'      => sanitize_text_field($source),
                'click_intent'      => sanitize_text_field($intent),
                'affiliate_network' => sanitize_text_field($network),
                'destination_url'   => esc_url_raw($destination),
                'referer'           => isset($_SERVER['HTTP_REFERER']) ? esc_url_raw((string) $_SERVER['HTTP_REFERER']) : '',
                'user_agent'        => isset($_SERVER['HTTP_USER_AGENT']) ? sanitize_text_field((string) $_SERVER['HTTP_USER_AGENT']) : '',
                'ip_hash'           => $ipHash,
                'utm_source'        => sanitize_text_field($attribution['utm_source'] ?? ''),
                'utm_medium'        => sanitize_text_field($attribution['utm_medium'] ?? ''),
                'utm_campaign'      => sanitize_text_field($attribution['utm_campaign'] ?? ''),
                'utm_content'       => sanitize_text_field($attribution['utm_content'] ?? ''),
                'referral_channel'  => sanitize_text_field($attribution['referral_channel'] ?? 'direct'),
                'first_referrer'    => esc_url_raw($attribution['first_referrer'] ?? ''),
            ],
            ['%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s']
        );
    }

    public function tableExists(): bool
    {
        global $wpdb;

        $table = $this->tableName();
        $exists = $wpdb->get_var($wpdb->prepare('SHOW TABLES LIKE %s', $table));

        return $exists === $table;
    }

    /**
     * @return array<string,mixed>
     */
    public function report(int $days = 30): array
    {
        global $wpdb;

        if (! $this->tableExists()) {
            return [
                'ok'      => false,
                'message' => 'Revenue table not found',
            ];
        }

        $days = max(1, $days);
        $from = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        $table = $this->tableName();

        $total = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE created_at >= %s', $from));

        $bySourceRows = $wpdb->get_results($wpdb->prepare(
            'SELECT click_source, COUNT(*) as total FROM ' . $table . ' WHERE created_at >= %s GROUP BY click_source ORDER BY total DESC',
            $from
        ), ARRAY_A);

        $byNetworkRows = $wpdb->get_results($wpdb->prepare(
            'SELECT affiliate_network, COUNT(*) as total FROM ' . $table . ' WHERE created_at >= %s GROUP BY affiliate_network ORDER BY total DESC',
            $from
        ), ARRAY_A);

        $topHelmetRows = $wpdb->get_results($wpdb->prepare(
            'SELECT helmet_id, COUNT(*) as total FROM ' . $table . ' WHERE created_at >= %s GROUP BY helmet_id ORDER BY total DESC LIMIT 10',
            $from
        ), ARRAY_A);

        $bySource = [];
        if (is_array($bySourceRows)) {
            foreach ($bySourceRows as $row) {
                $key = isset($row['click_source']) ? (string) $row['click_source'] : '';
                if ($key !== '') {
                    $bySource[$key] = isset($row['total']) ? (int) $row['total'] : 0;
                }
            }
        }

        $byNetwork = [];
        if (is_array($byNetworkRows)) {
            foreach ($byNetworkRows as $row) {
                $key = isset($row['affiliate_network']) ? (string) $row['affiliate_network'] : '';
                if ($key !== '') {
                    $byNetwork[$key] = isset($row['total']) ? (int) $row['total'] : 0;
                }
            }
        }

        $topHelmets = [];
        if (is_array($topHelmetRows)) {
            foreach ($topHelmetRows as $row) {
                $helmetId = isset($row['helmet_id']) ? (int) $row['helmet_id'] : 0;
                if ($helmetId <= 0) {
                    continue;
                }
                $topHelmets[] = [
                    'helmet_id' => $helmetId,
                    'title'     => get_the_title($helmetId),
                    'clicks'    => isset($row['total']) ? (int) $row['total'] : 0,
                ];
            }
        }

        return [
            'ok'          => true,
            'days'        => $days,
            'from'        => $from,
            'total_clicks' => $total,
            'by_source'   => $bySource,
            'by_network'  => $byNetwork,
            'top_helmets' => $topHelmets,
        ];
    }

    /**
     * Report clicks grouped by marketplace.
     *
     * @return array<string, int>
     */
    public function reportByMarketplace(int $days = 30): array
    {
        global $wpdb;

        if (! $this->tableExists()) {
            return [];
        }

        $from  = gmdate('Y-m-d H:i:s', time() - (max(1, $days) * DAY_IN_SECONDS));
        $table = $this->tableName();

        $rows = $wpdb->get_results($wpdb->prepare(
            'SELECT marketplace_id, COUNT(*) as total FROM ' . $table . ' WHERE created_at >= %s AND marketplace_id != "" GROUP BY marketplace_id ORDER BY total DESC',
            $from
        ), ARRAY_A);

        $result = [];
        if (is_array($rows)) {
            foreach ($rows as $row) {
                $key = (string) ($row['marketplace_id'] ?? '');
                if ($key !== '') {
                    $result[$key] = (int) ($row['total'] ?? 0);
                }
            }
        }

        return $result;
    }

    /**
     * Cross-network traffic attribution and conversion report.
     *
     * @return array<string,mixed>
     */
    public function getAttributionReport(int $days = 30): array
    {
        global $wpdb;

        if (! $this->tableExists()) {
            return [
                'ok'      => false,
                'message' => 'Revenue table not found',
            ];
        }

        $days = max(1, $days);
        $from = gmdate('Y-m-d H:i:s', time() - ($days * DAY_IN_SECONDS));
        $table = $this->tableName();

        $total = (int) $wpdb->get_var($wpdb->prepare('SELECT COUNT(*) FROM ' . $table . ' WHERE created_at >= %s', $from));

        // Group by referral channel
        $byChannelRows = $wpdb->get_results($wpdb->prepare(
            'SELECT referral_channel, COUNT(*) as total FROM ' . $table . ' WHERE created_at >= %s GROUP BY referral_channel ORDER BY total DESC',
            $from
        ), ARRAY_A);

        $byChannel = [];
        if (is_array($byChannelRows)) {
            foreach ($byChannelRows as $row) {
                $channel = (string) ($row['referral_channel'] ?? 'direct');
                $byChannel[$channel ?: 'direct'] = (int) ($row['total'] ?? 0);
            }
        }

        // Group by utm_source (where non-empty)
        $bySourceRows = $wpdb->get_results($wpdb->prepare(
            'SELECT utm_source, COUNT(*) as total FROM ' . $table . ' WHERE created_at >= %s AND utm_source != "" GROUP BY utm_source ORDER BY total DESC LIMIT 15',
            $from
        ), ARRAY_A);

        $byUtmSource = [];
        if (is_array($bySourceRows)) {
            foreach ($bySourceRows as $row) {
                $source = (string) ($row['utm_source'] ?? '');
                if ($source !== '') {
                    $byUtmSource[$source] = (int) ($row['total'] ?? 0);
                }
            }
        }

        // Group by marketplace
        $byMarketplace = $this->reportByMarketplace($days);

        // Top converting helmets
        $topHelmetRows = $wpdb->get_results($wpdb->prepare(
            'SELECT helmet_id, COUNT(*) as total FROM ' . $table . ' WHERE created_at >= %s GROUP BY helmet_id ORDER BY total DESC LIMIT 10',
            $from
        ), ARRAY_A);

        $topHelmets = [];
        if (is_array($topHelmetRows)) {
            foreach ($topHelmetRows as $row) {
                $helmetId = (int) ($row['helmet_id'] ?? 0);
                if ($helmetId > 0) {
                    $topHelmets[] = [
                        'helmet_id' => $helmetId,
                        'title'     => get_the_title($helmetId),
                        'clicks'    => (int) ($row['total'] ?? 0),
                    ];
                }
            }
        }

        // Recent conversions / clicks
        $recentRows = $wpdb->get_results($wpdb->prepare(
            'SELECT helmet_id, marketplace_id, referral_channel, utm_source, created_at FROM ' . $table . ' WHERE created_at >= %s ORDER BY created_at DESC LIMIT 10',
            $from
        ), ARRAY_A);

        $recentConversions = [];
        if (is_array($recentRows)) {
            foreach ($recentRows as $r) {
                $hId = (int) ($r['helmet_id'] ?? 0);
                $recentConversions[] = [
                    'helmet_id'        => $hId,
                    'title'            => get_the_title($hId),
                    'marketplace_id'   => (string) ($r['marketplace_id'] ?? ''),
                    'referral_channel' => (string) ($r['referral_channel'] ?? 'direct'),
                    'utm_source'       => (string) ($r['utm_source'] ?? ''),
                    'created_at'       => (string) ($r['created_at'] ?? ''),
                ];
            }
        }

        return [
            'ok'                 => true,
            'days'               => $days,
            'from'               => $from,
            'total_clicks'       => $total,
            'by_channel'         => $byChannel,
            'by_utm_source'      => $byUtmSource,
            'by_marketplace'     => $byMarketplace,
            'top_helmets'        => $topHelmets,
            'recent_conversions' => $recentConversions,
        ];
    }
}
