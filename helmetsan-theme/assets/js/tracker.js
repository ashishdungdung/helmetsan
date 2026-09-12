(function() {
    'use strict';

    function isHeadlessBot() {
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
        } catch (e) {
            return false;
        }
    }

    function getGtag() {
        if (isHeadlessBot()) {
            return function() {};
        }
        if (typeof window.gtag === 'function') {
            return window.gtag;
        }
        if (typeof window.dataLayer !== 'undefined') {
            return function(cmd, action, params) {
                if (cmd === 'event' && params && typeof params === 'object') {
                    window.dataLayer.push({ event: action, ...params });
                }
            };
        }
        return function() {};
    }

    function trackViewItem(data) {
        const gtag = getGtag();
        const params = {
            currency: data.currency || 'USD',
            value: data.price,
            items: [{
                item_id: data.id,
                item_name: data.name,
                item_brand: data.brand,
                item_category: data.category,
                price: data.price,
                quantity: 1
            }]
        };
        if (data.item_list_id) params.item_list_id = data.item_list_id;
        if (data.item_list_name) params.item_list_name = data.item_list_name;
        gtag('event', 'view_item', params);
    }

    function trackLead(data, marketplace) {
        const gtag = getGtag();
        gtag('event', 'generate_lead', {
            currency: data.currency || 'USD',
            value: data.price,
            items: [{
                item_id: data.id,
                item_name: data.name,
                item_brand: data.brand,
                item_category: data.category,
                price: data.price,
                marketplace: marketplace
            }]
        });
        
        // Also support select_item for funnel tracking
        gtag('event', 'select_item', {
            item_list_id: 'where_to_buy',
            item_list_name: 'Where to Buy',
            items: [{
                item_id: data.id,
                item_name: data.name,
                marketplace: marketplace
            }]
        });
    }

    function resolveAmazonRegion(href, marketplace) {
        const h = (href || '').toLowerCase();
        const mp = (marketplace || '').toLowerCase();
        if (h.includes('amazon.co.uk') || mp === 'amazon-uk' || mp === 'amazon-gb') return { domain: 'amazon.co.uk', region: 'UK' };
        if (h.includes('amazon.de') || mp === 'amazon-de' || mp === 'amazon-cz' || mp === 'amazon-at') return { domain: 'amazon.de', region: 'DE' };
        if (h.includes('amazon.fr') || mp === 'amazon-fr') return { domain: 'amazon.fr', region: 'FR' };
        if (h.includes('amazon.it') || mp === 'amazon-it') return { domain: 'amazon.it', region: 'IT' };
        if (h.includes('amazon.es') || mp === 'amazon-es') return { domain: 'amazon.es', region: 'ES' };
        if (h.includes('amazon.nl') || mp === 'amazon-nl') return { domain: 'amazon.nl', region: 'NL' };
        if (h.includes('amazon.pl') || mp === 'amazon-pl') return { domain: 'amazon.pl', region: 'PL' };
        if (h.includes('amazon.se') || mp === 'amazon-se') return { domain: 'amazon.se', region: 'SE' };
        if (h.includes('amazon.ca') || mp === 'amazon-ca') return { domain: 'amazon.ca', region: 'CA' };
        if (h.includes('amazon.in') || mp === 'amazon-in') return { domain: 'amazon.in', region: 'IN' };
        if (h.includes('amazon.co.jp') || h.includes('amazon.jp') || mp === 'amazon-jp') return { domain: 'amazon.co.jp', region: 'JP' };
        if (h.includes('amazon.com.au') || mp === 'amazon-au') return { domain: 'amazon.com.au', region: 'AU' };
        if (h.includes('amazon.com.be') || mp === 'amazon-be') return { domain: 'amazon.com.be', region: 'BE' };
        if (h.includes('amazon.com') || mp === 'amazon-us') return { domain: 'amazon.com', region: 'US' };
        return { domain: 'amazon.com', region: 'US' };
    }

    function getVisitorCountry() {
        const m = document.cookie.match(/(?:^|;\s*)helmetsan_geo=([A-Za-z]{2})/);
        if (m && m[1]) return m[1].toUpperCase();
        return '';
    }

    function getCatalogLanguage() {
        if (document.documentElement.lang) {
            const l = document.documentElement.lang.split('-')[0].toLowerCase();
            if (l) return l;
        }
        const m = location.pathname.match(/^\/([a-z]{2})(\/|$)/i);
        if (m && m[1]) return m[1].toLowerCase();
        return 'en';
    }

    function getClickPlacement(link) {
        if (link.closest('.hs-quick-buy-btn') || link.closest('.hs-pdp-actions') || link.closest('.hs-hero-pdp')) return 'hero_pdp_cta';
        if (link.closest('.hs-where-to-buy') || link.closest('.hs-price-table')) return 'where_to_buy_table';
        if (link.closest('.helmet-mobile-atc') || link.closest('.hs-mobile-sticky-head')) return 'mobile_sticky_bar';
        if (link.closest('.helmet-card') || link.closest('.hs-catalog-card')) return 'catalog_grid_card';
        return 'inline_content';
    }

    function trackAmazonOutbound(link, href, data, marketplace) {
        const gtag = getGtag();
        const amazonInfo = resolveAmazonRegion(href, marketplace);
        const tagMatch = href.match(/[?&]tag=([^&]+)/);
        const affiliateTag = tagMatch ? decodeURIComponent(tagMatch[1]) : '';
        const linkType = (href.includes('/dp/') || href.includes('/gp/') || href.includes('/d/')) ? 'direct_asin' : (href.includes('/s?') || href.includes('/s/')) ? 'search_query' : 'storefront';
        const catalogLang = getCatalogLanguage();
        const visitorCountry = getVisitorCountry() || catalogLang.toUpperCase();
        const placement = getClickPlacement(link);

        const eventParams = {
            amazon_store_region: amazonInfo.region,
            amazon_domain: amazonInfo.domain,
            catalog_language: catalogLang,
            visitor_country: visitorCountry,
            affiliate_tag: affiliateTag,
            link_type: linkType,
            click_placement: placement,
            outbound_url: href,
            currency: data.currency || 'USD',
            value: data.price,
            transport: 'beacon',
            items: [{
                item_id: data.id,
                item_name: data.name,
                item_brand: data.brand,
                item_category: data.category,
                price: data.price,
                marketplace: marketplace
            }]
        };

        gtag('event', 'amazon_outbound_click', eventParams);
    }

    // ── Real-User Web Vitals Tracking ──
    function trackWebVitals() {
        if (typeof window.gtag !== 'function') return;

        // 1. LCP (Largest Contentful Paint)
        try {
            const lcpObserver = new PerformanceObserver((entryList) => {
                const entries = entryList.getEntries();
                const lastEntry = entries[entries.length - 1];
                window.gtag('event', 'web_vital_measurement', {
                    vital_name: 'LCP',
                    vital_value: parseFloat(lastEntry.startTime.toFixed(2)),
                    vital_id: lastEntry.id || ''
                });
            });
            lcpObserver.observe({ type: 'largest-contentful-paint', buffered: true });
        } catch (e) {}

        // 2. CLS (Cumulative Layout Shift)
        try {
            let clsValue = 0;
            const clsObserver = new PerformanceObserver((entryList) => {
                for (const entry of entryList.getEntries()) {
                    if (!entry.hadRecentInput) {
                        clsValue += entry.value;
                    }
                }
                window.gtag('event', 'web_vital_measurement', {
                    vital_name: 'CLS',
                    vital_value: parseFloat(clsValue.toFixed(4))
                });
            });
            clsObserver.observe({ type: 'layout-shift', buffered: true });
        } catch (e) {}

        // 3. FID (First Input Delay)
        try {
            const fidObserver = new PerformanceObserver((entryList) => {
                const firstInput = entryList.getEntries()[0];
                const delay = firstInput.processingStart - firstInput.startTime;
                window.gtag('event', 'web_vital_measurement', {
                    vital_name: 'FID',
                    vital_value: parseFloat(delay.toFixed(2))
                });
            });
            fidObserver.observe({ type: 'first-input', buffered: true });
        } catch (e) {}
    }

    // ── Ad-Blocker Impact Estimation ──
    function checkAdBlocker() {
        window.addEventListener('load', function() {
            setTimeout(function() {
                // If GTM and GA4 are missing/blocked, adblock is active
                const gaLoaded = typeof window.gtag === 'function' && typeof window.google_tag_manager !== 'undefined';
                const adblockCookie = document.cookie.indexOf('hs_adblock_checked=1') !== -1;
                
                if (!gaLoaded && !adblockCookie && typeof window.helmetsanConfig !== 'undefined') {
                    const endpoint = window.helmetsanConfig.endpoint;
                    const nonce = window.helmetsanConfig.nonce;
                    const payload = {
                        event_name: 'adblock_detected',
                        page_url: window.location.href,
                        referrer: document.referrer || '',
                        source: 'frontend',
                        meta: { user_agent: navigator.userAgent },
                        _wpnonce: nonce
                    };

                    try {
                        if (navigator.sendBeacon) {
                            navigator.sendBeacon(endpoint, new Blob([JSON.stringify(payload)], { type: 'application/json' }));
                        } else {
                            fetch(endpoint, {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify(payload)
                            });
                        }
                        // Set cookie to prevent database spamming during session (expires in 30 mins)
                        document.cookie = "hs_adblock_checked=1; path=/; max-age=1800; secure; samesite=lax";
                    } catch (e) {}
                }
            }, 2000);
        });
    }

    // ── Referral Context Adaptation & Client Attribution Persistence ──
    function handleReferralContext() {
        const ref = (document.referrer || '').toLowerCase();

        // 1. YouTube Referral Context
        if (ref.includes('youtube.com') || ref.includes('youtu.be')) {
            document.body.classList.add('hs-ref-youtube');
            document.body.setAttribute('data-referral-source', 'youtube');

            // Auto-expand video review section / tab if present on product page
            const videoAccordion = document.querySelector('details.hs-video-review, #hs-video-review, details.hs-video-accordion');
            if (videoAccordion && !videoAccordion.open) {
                videoAccordion.open = true;
            }
            const videoTab = document.querySelector('[data-tab="video"], [data-tab="media"], .hs-tab-video');
            if (videoTab && typeof videoTab.click === 'function') {
                videoTab.click();
            }
        }

        // 2. Reddit / Forum Referral Context
        else if (ref.includes('reddit.com') || ref.includes('forum') || ref.includes('advrider.com') || ref.includes('motorcycle')) {
            document.body.classList.add('hs-ref-forum');
            document.body.setAttribute('data-referral-source', 'forum');

            // Auto-highlight community / user reviews section
            const reviewsTab = document.querySelector('[data-tab="reviews"], [data-tab="community"], .hs-tab-reviews');
            if (reviewsTab && typeof reviewsTab.click === 'function') {
                reviewsTab.click();
            }
            const reviewsSection = document.querySelector('#hs-community-reviews, #reviews, .hs-user-reviews');
            if (reviewsSection) {
                reviewsSection.classList.add('hs-highlight-pulse');
            }
        }

        // 3. Client-side First-Touch Referrer and UTM Preservation (works seamlessly with Edge Caching)
        try {
            const hasFirstRef = document.cookie.indexOf('hs_first_referrer=') !== -1;
            const isExternalRef = ref.indexOf(location.origin) === -1 && ref.indexOf('http') === 0;
            if (!hasFirstRef && isExternalRef) {
                document.cookie = 'hs_first_referrer=' + encodeURIComponent(document.referrer) + '; path=/; max-age=2592000; secure; samesite=lax';
            }

            // Capture UTM parameters from URL if present
            if (location.search) {
                const params = new URLSearchParams(location.search);
                ['utm_source', 'utm_medium', 'utm_campaign', 'utm_content', 'utm_term'].forEach(function(k) {
                    const val = params.get(k);
                    if (val) {
                        document.cookie = 'hs_' + k + '=' + encodeURIComponent(val) + '; path=/; max-age=2592000; secure; samesite=lax';
                    }
                });
            }
        } catch (e) {}
    }

    document.addEventListener('DOMContentLoaded', function() {
        // 1. Run global Web Vitals, Ad-Blocker & Referral Context checks
        const enabledWebVitals = typeof window.helmetsanConfig !== 'undefined' && window.helmetsanConfig.enableRealUserWebVitals;
        const enabledAdblock = typeof window.helmetsanConfig !== 'undefined' && window.helmetsanConfig.enableAdblockBeacon;

        if (enabledWebVitals) {
            trackWebVitals();
        }
        if (enabledAdblock) {
            checkAdBlocker();
        }
        handleReferralContext();

        // 2. Check for helmet product-specific data layer
        if (typeof window.helmetsanData === 'undefined') {
            return;
        }

        const data = window.helmetsanData;

        // 3. Track View Item
        trackViewItem(data);

        // 4. Track Affiliate Clicks (Event Delegation)
        document.body.addEventListener('click', function(e) {
            const link = e.target.closest('a');
            if (!link) return;

            const href = link.getAttribute('href') || '';
            const isAffiliateLink = link.classList.contains('hs-price-cta') 
                || link.classList.contains('helmet-single__retailer-link')
                || link.closest('.hs-where-to-buy') 
                || link.closest('.hs-price-table')
                || link.closest('.helmet-mobile-atc')
                || link.closest('.hs-mobile-sticky-head')
                || href.includes('/go/');

            if (isAffiliateLink) {
                let marketplace = 'unknown';
                
                // 1. Extract from marketplace= query param (internal redirect links)
                const paramMatch = href.match(/[?&]marketplace=([^&]+)/);
                if (paramMatch && paramMatch[1]) {
                    marketplace = decodeURIComponent(paramMatch[1]);
                }
                // 2. data-marketplace attribute on link or parent row
                else if (link.dataset.marketplace) {
                    marketplace = link.dataset.marketplace;
                } else if (link.closest('[data-marketplace]')) {
                    marketplace = link.closest('[data-marketplace]').dataset.marketplace;
                }
                // 3. Domain matching for direct external or redirect links
                else if (href.includes('amazon')) marketplace = 'amazon';
                else if (href.includes('revzilla')) marketplace = 'revzilla';
                else if (href.includes('flipkart')) marketplace = 'flipkart';
                else if (href.includes('fc-moto')) marketplace = 'fc-moto';
                else if (href.includes('allegro')) marketplace = 'allegro';
                else if (href.includes('jumia')) marketplace = 'jumia';
                else if (href.includes('/go/')) marketplace = 'amazon';

                if (marketplace.startsWith('amazon') || href.includes('amazon') || href.includes('/go/')) {
                    trackAmazonOutbound(link, href, data, marketplace);
                }

                trackLead(data, marketplace);
            }
        });
    });
})();
