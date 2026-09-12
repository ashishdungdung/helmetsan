<?php

declare(strict_types=1);

namespace Helmetsan\Core\Analytics;

use Helmetsan\Core\Support\Config;

final class Tracker
{
    private Config $config;
    private ?\Helmetsan\Core\Geo\GeoService $geo;

    public function __construct(?\Helmetsan\Core\Geo\GeoService $geo = null)
    {
        $this->config = new Config();
        $this->geo = $geo;
    }

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('wp_head', [$this, 'printHeadScripts'], 20);
        add_action('wp_body_open', [$this, 'printGtmNoscript'], 1);
        add_action('wp_footer', [$this, 'printFooterScripts'], 20);
    }

    /** GTM noscript iframe (recommended by Google; works when JS is disabled). */
    public function printGtmNoscript(): void
    {
        $settings = $this->getSettings();
        if (! $this->shouldRun($settings)) {
            return;
        }
        $gtm = isset($settings['gtm_container_id']) ? trim((string) $settings['gtm_container_id']) : '';
        if ($gtm === '') {
            return;
        }
        if (! empty($settings['enable_consent_gate'])) {
            $cookieName = (string) ($settings['consent_cookie_name'] ?? 'helmetsan_consent_analytics');
            $cookieName = preg_replace('/[^a-zA-Z0-9_-]/', '', $cookieName) ?: 'helmetsan_consent_analytics';
            if (empty($_COOKIE[$cookieName])) {
                return;
            }
        }
        $id = esc_attr($gtm);
        echo "<noscript><iframe src=\"https://www.googletagmanager.com/ns.html?id={$id}\" height=\"0\" width=\"0\" style=\"display:none;visibility:hidden\"></iframe></noscript>\n";
    }

    public function enqueueScripts(): void
    {
        $theme_uri = get_stylesheet_directory_uri();
        wp_enqueue_script(
            'helmetsan-tracker',
            $theme_uri . '/assets/js/tracker.js',
            [],
            '1.0.0',
            true
        );

        $features = wp_parse_args((array) get_option(Config::OPTION_FEATURES, []), $this->config->featuresDefaults());

        wp_localize_script('helmetsan-tracker', 'helmetsanConfig', [
            'endpoint' => esc_url_raw(rest_url('helmetsan/v1/event')),
            'nonce'    => wp_create_nonce('helmetsan_event'),
            'enableRealUserWebVitals' => !empty($features['enable_real_user_web_vitals']),
            'enableAdblockBeacon'     => !empty($features['enable_adblock_beacon']),
        ]);

        if (is_singular('helmet')) {
            wp_enqueue_script(
                'helmetsan-analytics-events',
                $theme_uri . '/assets/js/analytics-events.js',
                ['helmetsan-tracker'],
                '1.0.0',
                true
            );
        }
        if (is_post_type_archive('helmet')) {
            wp_enqueue_script(
                'helmetsan-list-tracking',
                $theme_uri . '/assets/js/list-tracking.js',
                [],
                '1.0.0',
                true
            );
        }
    }

    public function printHeadScripts(): void
    {
        $settings = $this->getSettings();

        if (! $this->shouldRun($settings)) {
            echo "<!-- Helmetsan Analytics: off. Enable in Helmetsan → Settings → Analytics and set GA4 (G-...) or GTM (GTM-...) ID -->\n";
            return;
        }

        if (! empty($settings['enable_consent_gate'])) {
            $cookieName = (string) ($settings['consent_cookie_name'] ?? 'helmetsan_consent_analytics');
            $cookieName = preg_replace('/[^a-zA-Z0-9_-]/', '', $cookieName) ?: 'helmetsan_consent_analytics';
            $consent = isset($_COOKIE[$cookieName]) && $_COOKIE[$cookieName] !== '';
            if (! $consent) {
                echo "<!-- Helmetsan Analytics: consent gate active; load analytics only after user consent (cookie: {$cookieName}) -->\n";
                return;
            }
        }

        $ga4 = isset($settings['ga4_measurement_id']) ? trim((string) $settings['ga4_measurement_id']) : '';
        $gtm = isset($settings['gtm_container_id']) ? trim((string) $settings['gtm_container_id']) : '';
        $userId = ! empty($settings['enable_user_id_tracking']) && is_user_logged_in() ? (string) get_current_user_id() : '';

        if ($gtm !== '') {
            $id = esc_js($gtm);
            echo "<!-- Helmetsan Analytics: GTM -->\n";
            echo "<script>(function(){
                function isAutomatedClient(){
                    try {
                        if (navigator.webdriver) return true;
                        var ua = navigator.userAgent || '';
                        if (/bot|crawl|spider|slurp|headless|chrome-lighthouse|preview/i.test(ua)) return true;
                        if (!navigator.languages || navigator.languages.length === 0) return true;
                        if (window.outerWidth === 0 && window.outerHeight === 0) return true;
                        var sw = window.screen ? window.screen.width : 0;
                        var sh = window.screen ? window.screen.height : 0;
                        var isMobile = Boolean(navigator.userAgentData && navigator.userAgentData.mobile) || /Android|iPhone|iPad|iPod/i.test(ua);
                        if (sh >= 5000) return true;
                        if (navigator.userAgentData && Array.isArray(navigator.userAgentData.brands)) {
                            for (var b = 0; b < navigator.userAgentData.brands.length; b++) {
                                if (/HeadlessChrome/i.test(navigator.userAgentData.brands[b].brand)) return true;
                            }
                        }
                        if (!isMobile) {
                            if (sw === 1280 && sh === 1200) return true;
                            if (sw === 1800 && sh === 1125) return true;
                            if (sw === 800 && sh === 600) return true;
                            if (/Linux/i.test(navigator.platform || '') && (sw === 1440 && sh === 900 || sw === 1024 && sh === 768)) return true;
                            if (navigator.plugins && navigator.plugins.length === 0 && !navigator.pdfViewerEnabled) return true;
                        }
                        try {
                            var canvas = document.createElement('canvas');
                            var gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
                            if (gl) {
                                var debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
                                if (debugInfo) {
                                    var renderer = gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL) || '';
                                    if (/SwiftShader|llvmpipe|Mesa Offscreen|VirtualBox/i.test(renderer)) return true;
                                }
                            }
                        } catch(glErr) {}
                        return false;
                    } catch(e) { return false; }
                }
                if (isAutomatedClient()) { return; }
                (function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{$id}');
            })();</script>\n";
            if ($ga4 === '' && $userId !== '') {
                echo '<script>window.dataLayer=window.dataLayer||[];window.dataLayer.push({event:"helmetsan_user_id",user_id:"' . esc_js($userId) . '"});</script>' . "\n";
            }
            return;
        }

        $ga4Valid = $ga4 !== '' && preg_match('/^G-[A-Za-z0-9_-]+$/i', $ga4);
        if ($ga4Valid) {
            $ga4e = esc_js($ga4);
            echo "<!-- Helmetsan Analytics: GA4 -->\n";
            $config = $userId !== '' ? ",{'user_id':'" . esc_js($userId) . "'}" : '';
            echo "<script>(function(){
                function isAutomatedClient(){
                    try {
                        if (navigator.webdriver) return true;
                        var ua = navigator.userAgent || '';
                        if (/bot|crawl|spider|slurp|headless|chrome-lighthouse|preview/i.test(ua)) return true;
                        if (!navigator.languages || navigator.languages.length === 0) return true;
                        if (window.outerWidth === 0 && window.outerHeight === 0) return true;
                        var sw = window.screen ? window.screen.width : 0;
                        var sh = window.screen ? window.screen.height : 0;
                        var isMobile = Boolean(navigator.userAgentData && navigator.userAgentData.mobile) || /Android|iPhone|iPad|iPod/i.test(ua);
                        if (sh >= 5000) return true;
                        if (navigator.userAgentData && Array.isArray(navigator.userAgentData.brands)) {
                            for (var b = 0; b < navigator.userAgentData.brands.length; b++) {
                                if (/HeadlessChrome/i.test(navigator.userAgentData.brands[b].brand)) return true;
                            }
                        }
                        if (!isMobile) {
                            if (sw === 1280 && sh === 1200) return true;
                            if (sw === 1800 && sh === 1125) return true;
                            if (sw === 800 && sh === 600) return true;
                            if (/Linux/i.test(navigator.platform || '') && (sw === 1440 && sh === 900 || sw === 1024 && sh === 768)) return true;
                            if (navigator.plugins && navigator.plugins.length === 0 && !navigator.pdfViewerEnabled) return true;
                        }
                        try {
                            var canvas = document.createElement('canvas');
                            var gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
                            if (gl) {
                                var debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
                                if (debugInfo) {
                                    var renderer = gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL) || '';
                                    if (/SwiftShader|llvmpipe|Mesa Offscreen|VirtualBox/i.test(renderer)) return true;
                                }
                            }
                        } catch(glErr) {}
                        return false;
                    } catch(e) { return false; }
                }
                if (isAutomatedClient()) { return; }
                var s = document.createElement('script');
                s.async = true;
                s.src = 'https://www.googletagmanager.com/gtag/js?id={$ga4e}';
                document.head.appendChild(s);
                window.dataLayer=window.dataLayer||[];
                function gtag(){dataLayer.push(arguments);}
                gtag('js', new Date());
                gtag('config','{$ga4e}'{$config});
            })();</script>\n";
        } elseif ($ga4 !== '') {
            echo "<!-- Helmetsan Analytics: GA4 ID invalid (use format G-XXXXXXXXXX) -->\n";
        } else {
            echo "<!-- Helmetsan Analytics: enabled but no GA4 or GTM ID in Settings → Analytics -->\n";
        }
    }

    public function printFooterScripts(): void
    {
        $settings = $this->getSettings();

        if (! $this->shouldRun($settings)) {
            return;
        }

        if (! empty($settings['enable_consent_gate'])) {
            $cookieName = (string) ($settings['consent_cookie_name'] ?? 'helmetsan_consent_analytics');
            $cookieName = preg_replace('/[^a-zA-Z0-9_-]/', '', $cookieName) ?: 'helmetsan_consent_analytics';
            if (empty($_COOKIE[$cookieName])) {
                return;
            }
        }

        if (! empty($settings['enable_heatmap_clarity']) && ! empty($settings['clarity_project_id'])) {
            $clarity = esc_js((string) $settings['clarity_project_id']);
            echo "<script>(function(c,l,a,r,i,t,y){c[a]=c[a]||function(){(c[a].q=c[a].q||[]).push(arguments)};t=l.createElement(r);t.async=1;t.src='https://www.clarity.ms/tag/'+i;y=l.getElementsByTagName(r)[0];y.parentNode.insertBefore(t,y);})(window, document, 'clarity', 'script', '{$clarity}');</script>\n";
        }

        if (! empty($settings['enable_heatmap_hotjar']) && ! empty($settings['hotjar_site_id'])) {
            $siteId  = esc_js((string) $settings['hotjar_site_id']);
            $version = esc_js((string) ($settings['hotjar_version'] ?? '6'));
            echo "<script>(function(h,o,t,j,a,r){h.hj=h.hj||function(){(h.hj.q=h.hj.q||[]).push(arguments)};h._hjSettings={hjid:{$siteId},hjsv:{$version}};a=o.getElementsByTagName('head')[0];r=o.createElement('script');r.async=1;r.src=t+h._hjSettings.hjid+j+h._hjSettings.hjsv;a.appendChild(r);})(window,document,'https://static.hotjar.com/c/hotjar-','.js?sv=');</script>\n";
        }

        $trackOutbound = ! empty($settings['enable_enhanced_event_tracking']);
        $trackSearch   = ! empty($settings['enable_internal_search_tracking']);
        $trackScroll   = ! empty($settings['enable_scroll_depth_tracking']);
        $trackFile     = ! empty($settings['enable_file_download_tracking']);
        $trackEmailPhone = ! empty($settings['enable_email_phone_tracking']);

        if (! $trackOutbound && ! $trackSearch && ! $trackScroll && ! $trackFile && ! $trackEmailPhone) {
            return;
        }

        $workerUrl = !empty($settings['d1_analytics_worker_url']) ? rtrim((string)$settings['d1_analytics_worker_url'], '/') : '';
        $endpoint = $workerUrl !== '' ? esc_js((string)$workerUrl) : esc_js((string) rest_url('helmetsan/v1/event'));
        $nonce    = esc_js(wp_create_nonce('helmetsan_event'));
        $o = $trackOutbound ? '1' : '0';
        $s = $trackSearch ? '1' : '0';
        $sc = $trackScroll ? '1' : '0';
        $f = $trackFile ? '1' : '0';
        $ep = $trackEmailPhone ? '1' : '0';

        echo "<script>(function(){
            function isAutomatedClient(){
                try {
                    if (navigator.webdriver) return true;
                    var ua = navigator.userAgent || '';
                    if (/bot|crawl|spider|slurp|headless|chrome-lighthouse|preview/i.test(ua)) return true;
                    if (!navigator.languages || navigator.languages.length === 0) return true;
                    if (window.outerWidth === 0 && window.outerHeight === 0) return true;
                    var sw = window.screen ? window.screen.width : 0;
                    var sh = window.screen ? window.screen.height : 0;
                    var isMobile = Boolean(navigator.userAgentData && navigator.userAgentData.mobile) || /Android|iPhone|iPad|iPod/i.test(ua);
                    if (sh >= 5000) return true;
                    if (navigator.userAgentData && Array.isArray(navigator.userAgentData.brands)) {
                        for (var b = 0; b < navigator.userAgentData.brands.length; b++) {
                            if (/HeadlessChrome/i.test(navigator.userAgentData.brands[b].brand)) return true;
                        }
                    }
                    if (!isMobile) {
                        if (sw === 1280 && sh === 1200) return true;
                        if (sw === 1800 && sh === 1125) return true;
                        if (sw === 800 && sh === 600) return true;
                        if (/Linux/i.test(navigator.platform || '') && (sw === 1440 && sh === 900 || sw === 1024 && sh === 768)) return true;
                        if (navigator.plugins && navigator.plugins.length === 0 && !navigator.pdfViewerEnabled) return true;
                    }
                    try {
                        var canvas = document.createElement('canvas');
                        var gl = canvas.getContext('webgl') || canvas.getContext('experimental-webgl');
                        if (gl) {
                            var debugInfo = gl.getExtension('WEBGL_debug_renderer_info');
                            if (debugInfo) {
                                var renderer = gl.getParameter(debugInfo.UNMASKED_RENDERER_WEBGL) || '';
                                if (/SwiftShader|llvmpipe|Mesa Offscreen|VirtualBox/i.test(renderer)) return true;
                            }
                        }
                    } catch(glErr) {}
                    return false;
                } catch(e) { return false; }
            }
            if (isAutomatedClient()) { return; }
            var cfg={endpoint:'{$endpoint}',nonce:'{$nonce}',o:{$o},s:{$s},sc:{$sc},f:{$f},ep:{$ep}};function send(n,m){var p={event_name:n,page_url:location.href,referrer:document.referrer||'',source:'frontend',meta:m||{},_wpnonce:cfg.nonce};try{if(window.dataLayer){window.dataLayer.push({event:n,helmetsan:p});}if(typeof window.gtag==='function')window.gtag('event',n,m||{});if(navigator.sendBeacon)navigator.sendBeacon(cfg.endpoint,new Blob([JSON.stringify(p)],{type:'application/json'}));}catch(e){}}document.addEventListener('click',function(ev){var a=ev.target&&ev.target.closest?ev.target.closest('a[href]'):null;if(!a)return;var href=(a.getAttribute('href')||'').trim();if(!href)return;if(cfg.o&&href.indexOf('http')===0&&href.indexOf(location.origin)!==0)send('outbound_click',{href:href,text:(a.textContent||'').trim().slice(0,120)});if(cfg.f&&/\\.(pdf|zip|doc|docx|xls|xlsx)(\\?|$)/i.test(href))send('file_download',{href:href});if(cfg.ep){if(href.indexOf('mailto:')===0)send('email_click',{href:href});if(href.indexOf('tel:')===0)send('phone_click',{href:href});}},true);if(cfg.s)document.addEventListener('submit',function(ev){var i=ev.target&&ev.target.querySelector('input[name=\"s\"]');if(!i)return;var q=(i.value||'').trim();if(q)send('internal_search',{query:q.slice(0,120)});},true);if(cfg.sc){var sd=[25,50,75,90],done={};window.addEventListener('scroll',function(){var h=Math.max(document.documentElement.scrollHeight-document.documentElement.clientHeight,1),y=window.scrollY||window.pageYOffset;sd.forEach(function(d){if(done[d])return;if((y/h)*100>=d){done[d]=1;send('scroll_depth',{depth:d});}});},{passive:true});}})();</script>\n";
    }

    /**
     * @return array<string, mixed>
     */
    private function getSettings(): array
    {
        $saved = get_option(Config::OPTION_ANALYTICS, []);

        return wp_parse_args(is_array($saved) ? $saved : [], $this->config->analyticsDefaults());
    }

    /**
     * @param array<string, mixed> $settings
     */
    private function shouldRun(array $settings): bool
    {
        if (empty($settings['enable_analytics'])) {
            return false;
        }

        if (! empty($settings['exclude_admins']) && current_user_can('manage_options')) {
            return false;
        }

        $respectMonsterInsights = ! empty($settings['analytics_respect_monsterinsights']);
        if ($respectMonsterInsights && class_exists('MonsterInsights')) {
            return false;
        }

        // Disable analytics for users from China to avoid GFW load blocks
        if (function_exists('helmetsan_is_china_visitor') && helmetsan_is_china_visitor()) {
            return false;
        }

        return true;
    }
}
