<?php

declare(strict_types=1);

namespace Helmetsan\Core\Analytics;

use Helmetsan\Core\Support\Config;

final class Tracker
{
    private Config $config;

    public function __construct()
    {
        $this->config = new Config();
    }

    public function register(): void
    {
        add_action('wp_enqueue_scripts', [$this, 'enqueueScripts']);
        add_action('wp_head', [$this, 'printHeadScripts'], 20);
        add_action('wp_footer', [$this, 'printFooterScripts'], 20);
    }

    public function enqueueScripts(): void
    {
        if (is_singular('helmet')) {
            wp_enqueue_script(
                'helmetsan-tracker',
                get_template_directory_uri() . '/assets/js/tracker.js',
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
            return;
        }

        if (! empty($settings['gtm_container_id'])) {
            $id = esc_js((string) $settings['gtm_container_id']);
            echo "<script>(function(w,d,s,l,i){w[l]=w[l]||[];w[l].push({'gtm.start':new Date().getTime(),event:'gtm.js'});var f=d.getElementsByTagName(s)[0],j=d.createElement(s),dl=l!='dataLayer'?'&l='+l:'';j.async=true;j.src='https://www.googletagmanager.com/gtm.js?id='+i+dl;f.parentNode.insertBefore(j,f);})(window,document,'script','dataLayer','{$id}');</script>\n";
            return;
        }

        if (! empty($settings['ga4_measurement_id'])) {
            $ga4 = esc_js((string) $settings['ga4_measurement_id']);
            echo "<script async src='https://www.googletagmanager.com/gtag/js?id={$ga4}'></script>\n";
            echo "<script>window.dataLayer=window.dataLayer||[];function gtag(){dataLayer.push(arguments);}gtag('js', new Date());gtag('config','{$ga4}');</script>\n";
        }
    }

    public function printFooterScripts(): void
    {
        $settings = $this->getSettings();

        if (! $this->shouldRun($settings)) {
            return;
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

        if (! $trackOutbound && ! $trackSearch) {
            return;
        }

        $endpoint = esc_js((string) rest_url('helmetsan/v1/event'));
        $outbound = $trackOutbound ? 'true' : 'false';
        $search   = $trackSearch ? 'true' : 'false';
        $nonce    = esc_js(wp_create_nonce('helmetsan_event'));

        echo "<script>(function(){var cfg={endpoint:'{$endpoint}',trackOutbound:{$outbound},trackSearch:{$search},nonce:'{$nonce}'};function sendEvent(name,meta){var payload={event_name:name,page_url:window.location.href,referrer:document.referrer||'',source:'frontend',meta:meta||{},_wpnonce:cfg.nonce};try{if(window.dataLayer&&Array.isArray(window.dataLayer)){window.dataLayer.push({event:name,helmetsan:payload});}if(typeof window.gtag==='function'){window.gtag('event',name,meta||{});}if(navigator.sendBeacon){var b=new Blob([JSON.stringify(payload)],{type:'application/json'});navigator.sendBeacon(cfg.endpoint,b);}else{fetch(cfg.endpoint,{method:'POST',headers:{'Content-Type':'application/json','X-WP-Nonce':cfg.nonce},body:JSON.stringify(payload),keepalive:true});}}catch(e){}}if(cfg.trackOutbound){document.addEventListener('click',function(ev){var a=ev.target&&ev.target.closest?ev.target.closest('a[href]'):null;if(!a){return;}var href=a.getAttribute('href')||'';if(!href){return;}if(href.indexOf('http')!==0){return;}if(href.indexOf(window.location.origin)===0){return;}sendEvent('outbound_click',{href:href,text:(a.textContent||'').trim().slice(0,120)});},true);}if(cfg.trackSearch){document.addEventListener('submit',function(ev){var f=ev.target;if(!f||!f.querySelector){return;}var i=f.querySelector('input[name=\"s\"]');if(!i){return;}var q=(i.value||'').trim();if(!q){return;}sendEvent('internal_search',{query:q.slice(0,120)});},true);}})();</script>\n";
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

        $respectMonsterInsights = ! empty($settings['analytics_respect_monsterinsights']);
        if ($respectMonsterInsights && class_exists('MonsterInsights')) {
            return false;
        }

        return true;
    }
}
