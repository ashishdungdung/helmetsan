/**
 * Cloudflare Worker for Helmetsan Edge-Side HTML Assembly (ESI)
 * Intercepts HTML page requests, fetches from origin, and injects mega menus and stats at the edge.
 */

const BLOCKED_PROBE_PATTERNS = [
    /^\/\.(env|git|svn|aws|ssh|htaccess|htpasswd)(\/.*)?$/i,
    /^\/wp-config\.php(\..*|\.bak|\.old|\.save)?$/i,
    /^\/wp-content\/debug\.log$/i,
    /^\/(phpmyadmin|pma|adminer|dbadmin)(\/.*)?$/i,
    /^\/(alfa|shell|wso|c99|b374k|eval-stdin)\.php$/i,
    /^\/wp-includes\/wlwmanifest\.xml$/i,
    /^\/xmlrpc\.php$/i
];

export default {
    async fetch(request, env, ctx) {
        const url = new URL(request.url);
        const pathname = url.pathname;

        // 1. Edge Security Shield: Block malicious probes instantly at Cloudflare PoP
        if (BLOCKED_PROBE_PATTERNS.some(re => re.test(pathname))) {
            return new Response('Forbidden', {
                status: 403,
                headers: {
                    'Content-Type': 'text/plain',
                    'X-Edge-Security': 'BLOCKED'
                }
            });
        }

        // 2. Handle Cache Purge & Probe API
        const isPurgePath = url.pathname === '/api/edge-cache/purge' ||
            url.pathname === '/__edge-cache/purge' ||
            url.pathname === '/__ae_purge_edge_cache__' ||
            url.pathname.endsWith('/edge/purge-cache') ||
            url.pathname === '/api/edge/purge';

        if (isPurgePath) {
            return handlePurgeRequest(request, env, url);
        }

        // Only process GET requests
        if (request.method !== 'GET') {
            return fetch(request);
        }

        // Bypass checks for static files, wp-admin, login, cron, affiliate redirects, and API requests
        const isStaticAsset = /\.(css|js|png|jpg|jpeg|gif|svg|ico|woff|woff2|json|webp|avif|xml|txt)$/i.test(pathname);
        const isWordpressAdmin = pathname.includes('/wp-admin') || pathname.includes('/wp-login.php') || pathname.includes('/wp-cron.php');
        const isWordpressApi = pathname.includes('/wp-json');
        const isGoRedirect = pathname.startsWith('/go/');

        if (isStaticAsset || isWordpressAdmin || isWordpressApi || isGoRedirect) {
            return fetch(request);
        }

        // Detect current language from URL path (Polylang directory structure)
        const supportedLangs = ['de', 'zh', 'fr', 'es', 'it', 'pl', 'pt', 'nl', 'ja'];
        let lang = 'en';
        for (const l of supportedLangs) {
            if (pathname.startsWith(`/${l}/`) || pathname === `/${l}`) {
                lang = l;
                break;
            }
        }

        // Detect visitor's country (1. Query param, 2. Cookie, 3. Header, 4. Fallback)
        let country = '';
        if (url.searchParams.has('country')) {
            country = url.searchParams.get('country');
        }
        if (!country) {
            const cookieHeader = request.headers.get('Cookie') || '';
            const match = cookieHeader.match(/helmetsan_geo=([A-Z]{2})/i);
            if (match) {
                country = match[1];
            }
        }
        if (!country) {
            country = request.headers.get('CF-IPCountry') || 'IN';
        }
        country = country.toUpperCase().slice(0, 2);

        // Check Page Edge Cache
        const bypassCheck = shouldBypassPageCache(request, url);
        const cache = caches.default;
        const cacheKeyUrl = normalizePageCacheKey(request, url, country);
        const cacheKey = new Request(cacheKeyUrl, { method: 'GET' });

        if (!bypassCheck.bypass) {
            try {
                const cachedResponse = await cache.match(cacheKey);
                if (cachedResponse) {
                    const headers = new Headers(cachedResponse.headers);
                    headers.set('CF-Edge-Cache', 'HIT');
                    headers.set('Cache-Control', 'public, max-age=0, s-maxage=7200, stale-while-revalidate=86400, must-revalidate');
                    return new Response(cachedResponse.body, {
                        status: cachedResponse.status,
                        statusText: cachedResponse.statusText,
                        headers: headers
                    });
                }
            } catch (e) {
                console.warn('Edge cache match error:', e);
            }
        }

        // Fetch the page from the origin (Nginx/WordPress)
        const response = await fetch(request);

        // Only rewrite HTML responses
        const contentType = response.headers.get('Content-Type') || '';
        if (!contentType.includes('text/html')) {
            return response;
        }

        const origin = url.origin;

        // Initiate stats fetch promise asynchronously (only for homepage)
        const isHomepage = pathname === '/' || supportedLangs.some(l => pathname === `/${l}/` || pathname === `/${l}`);
        let statsPromise = null;
        if (isHomepage) {
            statsPromise = fetchStats(origin, lang, env, ctx);
        }

        // Rewriter configuration
        const rewriter = new HTMLRewriter()
            // Replace mega menu HTML blocks dynamically
            .on('li.mega-menu--helmets .hs-mega-menu', new MenuElementHandler('helmet', lang, origin, env, ctx))
            .on('li.mega-menu--brands .hs-mega-menu', new MenuElementHandler('brands', lang, origin, env, ctx))
            .on('li.mega-menu--accessories .hs-mega-menu', new MenuElementHandler('accessories', lang, origin, env, ctx))
            .on('li.mega-menu--motorcycles .hs-mega-menu', new MenuElementHandler('motorcycles', lang, origin, env, ctx))
            .on('.hs-price', new PriceElementHandler(country, lang, origin, env, ctx))
            .on('#hsCurrentFlag, #hsCurrentCountry, #hsCurrentCode, #hsCurrentCurrency', new CountryTriggerHandler(country))
            .on('.hs-country-card', new CountryCardHandler(country));

        if (isHomepage && statsPromise) {
            // Inject correct counts inside homepage stats cards
            rewriter
                .on('a[href*="/helmets/"] strong', new StatsHandler('helmet', statsPromise))
                .on('a[href*="/brands/"] strong', new StatsHandler('brand', statsPromise))
                .on('a[href*="/accessories/"] strong', new StatsHandler('accessory', statsPromise))
                .on('a[href*="/motorcycles/"] strong', new StatsHandler('motorcycle', statsPromise))
                .on('a[href*="/dealers/"] strong', new StatsHandler('dealer', statsPromise));
        }

        // Stream the transformed response
        const transformedResponse = rewriter.transform(response);
        const finalHeaders = new Headers(transformedResponse.headers);

        // Security headers
        finalHeaders.set('X-Content-Type-Options', 'nosniff');
        finalHeaders.set('X-Frame-Options', 'SAMEORIGIN');
        finalHeaders.set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // If country was overridden via query param, set cookie at the edge
        if (url.searchParams.has('country')) {
            const countryVal = url.searchParams.get('country').toUpperCase();
            if (countryVal.length === 2 && /^[A-Z]{2}$/.test(countryVal)) {
                finalHeaders.append('Set-Cookie', `helmetsan_geo=${countryVal}; Path=/; Max-Age=86400; Secure; SameSite=Lax`);
            }
        }

        // Cache the transformed HTML page if eligible
        if (!bypassCheck.bypass && transformedResponse.status === 200) {
            finalHeaders.set('Cache-Control', 'public, max-age=7200, stale-while-revalidate=86400');
            finalHeaders.set('CF-Edge-Cache', 'HIT');

            // CWE-524 / RFC 7234: Strip Set-Cookie from shared cache storage
            const cacheHeaders = new Headers(finalHeaders);
            cacheHeaders.delete('Set-Cookie');

            const [clientStream, cacheStream] = transformedResponse.body.tee();
            const responseToCache = new Response(cacheStream, {
                status: transformedResponse.status,
                headers: cacheHeaders
            });

            if (ctx && ctx.waitUntil) {
                ctx.waitUntil(cache.put(cacheKey, responseToCache));
            }

            const clientHeaders = new Headers(finalHeaders);
            clientHeaders.set('CF-Edge-Cache', 'MISS');
            clientHeaders.set('Cache-Control', 'public, max-age=0, s-maxage=7200, stale-while-revalidate=86400, must-revalidate');

            return new Response(clientStream, {
                status: transformedResponse.status,
                statusText: transformedResponse.statusText,
                headers: clientHeaders
            });
        }

        finalHeaders.set('CF-Edge-Cache', 'BYPASS');
        if (bypassCheck.reason) {
            finalHeaders.set('CF-Edge-Cache-Reason', bypassCheck.reason);
        }

        return new Response(transformedResponse.body, {
            status: transformedResponse.status,
            statusText: transformedResponse.statusText,
            headers: finalHeaders
        });
    }
};

const TRACKING_PARAMS = new Set([
    'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
    'gclid', 'fbclid', '_ga', 'msclkid', 'mc_eid', 'dclid', 'ref',
    'fb_action_ids', 'fb_action_types', 'nc', '_gl'
]);

function shouldBypassPageCache(request, url) {
    if (url.pathname.startsWith('/go/')) {
        return { bypass: true, reason: 'AFFILIATE_REDIRECT' };
    }
    const cookie = request.headers.get('Cookie') || '';
    if (
        cookie.includes('wordpress_logged_in_') ||
        cookie.includes('comment_author_') ||
        cookie.includes('wp-postpass_') ||
        cookie.includes('woocommerce_items_in_cart')
    ) {
        return { bypass: true, reason: 'LOGGED_IN_USER' };
    }
    if (url.searchParams.has('preview') || url.searchParams.has('hs_preview') || url.searchParams.has('nocache')) {
        return { bypass: true, reason: 'PREVIEW_OR_NOCACHE' };
    }
    if (request.headers.get('Authorization')) {
        return { bypass: true, reason: 'AUTH_HEADER' };
    }
    return { bypass: false };
}

const CACHE_VERSION = 'v3_20260912_cs';

function normalizePageCacheKey(request, url, country) {
    const params = new URLSearchParams();
    params.set('__v', CACHE_VERSION);
    params.set('__geo', country);
    const sortedKeys = Array.from(url.searchParams.keys()).sort();
    for (const key of sortedKeys) {
        if (!TRACKING_PARAMS.has(key.toLowerCase()) && key.toLowerCase() !== 'country') {
            params.set(key, url.searchParams.get(key));
        }
    }
    return `${url.origin}${url.pathname}?${params.toString()}`;
}

async function handlePurgeRequest(request, env, url) {
    try {
        const authHeader = request.headers.get('Authorization') || '';
        const secretHeader = request.headers.get('X-Helmetsan-Secret') || '';
        const purgeTokenHeader = request.headers.get('x-purge-token') || '';
        const purgeKeyHeader = request.headers.get('X-Purge-Key') || '';
        const queryToken = url.searchParams.get('token') || '';
        const expectedSecret = env?.PURGE_SECRET || 'helmetsan-edge-purge-secret-2026';

        const isAuthorized = (
            authHeader === `Bearer ${expectedSecret}` ||
            secretHeader === expectedSecret ||
            purgeTokenHeader === expectedSecret ||
            purgeKeyHeader === expectedSecret ||
            queryToken === expectedSecret
        );

        if (!isAuthorized) {
            return new Response(JSON.stringify({ ok: false, error: 'Unauthorized' }), {
                status: 401,
                headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' }
            });
        }

        // Diagnostic health probe
        if (request.method === 'GET') {
            return new Response(JSON.stringify({
                status: 'online',
                worker: 'helmetsan-edge-assembly-worker',
                version: '2.2.0',
                colo: request.cf?.colo || 'LOCAL',
                city: request.cf?.city || 'Edge',
                country: request.cf?.country || 'Global',
                timestamp: new Date().toISOString()
            }), {
                status: 200,
                headers: { 'Content-Type': 'application/json', 'X-Edge-Cache': 'PROBE-OK', 'Access-Control-Allow-Origin': '*' }
            });
        }

        const body = await request.json().catch(() => ({}));
        const cache = caches.default;
        let purgedCount = 0;

        const supportedCountries = Object.keys(COUNTRY_METADATA);

        const purgeTarget = async (rawUrl) => {
            const u = new URL(rawUrl.startsWith('http') ? rawUrl : 'https://helmetsan.com' + rawUrl);
            // 1. Purge all regional cache keys
            for (const c of supportedCountries) {
                const geoKey = normalizePageCacheKey(new Request(u.toString()), u, c);
                await cache.delete(new Request(geoKey, { method: 'GET' }));
            }
            // 2. Purge raw URL
            await cache.delete(new Request(u.toString(), { method: 'GET' }));
        };

        if (body.purge_everything || body.all) {
            // Incrementing CACHE_VERSION in worker effectively purges all page cache
            purgedCount = 1;
        } else if (body.url) {
            await purgeTarget(body.url);
            purgedCount++;
        } else if (Array.isArray(body.urls)) {
            for (const u of body.urls) {
                await purgeTarget(u);
                purgedCount++;
            }
        } else {
            return new Response(JSON.stringify({ ok: false, error: 'Provide url, urls array, or purge_everything' }), {
                status: 400,
                headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' }
            });
        }

        return new Response(JSON.stringify({
            status: 'purged',
            ok: true,
            purged_count: purgedCount,
            colo: request.cf?.colo || 'UNKNOWN'
        }), {
            status: 200,
            headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' }
        });
    } catch (err) {
        return new Response(JSON.stringify({ ok: false, error: err.message }), {
            status: 500,
            headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' }
        });
    }
}

/**
 * Handler for fetching and replacing mega menu blocks using Cloudflare Cache API.
 */
class MenuElementHandler {
    constructor(type, lang, origin, env, ctx) {
        this.type = type;
        this.lang = lang;
        this.origin = origin;
        this.env = env;
        this.ctx = ctx;
    }

    async element(element) {
        // Fast-Path 1: Check Cloudflare KV (0ms latency, zero origin hit)
        const kv = this.env?.HELMETSAN_KV || this.env?.EDGE_KV;
        if (kv) {
            try {
                const kvHtml = await kv.get(`menu_${this.type}_${this.lang}`, 'text');
                if (kvHtml) {
                    element.replace(kvHtml, { html: true });
                    return;
                }
            } catch (e) {}
        }

        const cache = caches.default;
        const menuUrl = `${this.origin}/wp-json/helmetsan/v1/edge/mega-menu?type=${this.type}&lang=${this.lang}&format=html`;
        
        // Cache Key must be a unique Request object
        const cacheKey = new Request(menuUrl, {
            method: 'GET',
            headers: { 'Accept': 'text/html' }
        });

        try {
            let cachedResponse = await cache.match(cacheKey);
            let menuHtml = '';

            if (cachedResponse) {
                menuHtml = await cachedResponse.text();
            } else {
                // Cache miss, fetch from the origin endpoint
                const response = await fetch(menuUrl);
                if (response.ok) {
                    menuHtml = await response.text();
                    
                    // Cache the raw HTML block at the edge for 1 hour
                    const cacheResponse = new Response(menuHtml, {
                        headers: {
                            'Content-Type': 'text/html; charset=UTF-8',
                            'Cache-Control': 'public, max-age=3600'
                        }
                    });
                    if (this.ctx && this.ctx.waitUntil) {
                        this.ctx.waitUntil(cache.put(cacheKey, cacheResponse));
                    }
                }
            }

            if (menuHtml) {
                element.replace(menuHtml, { html: true });
            }
        } catch (e) {
            console.error(`Edge assembly error for menu ${this.type}:`, e);
            // In case of error, we do nothing and let the origin's original HTML output pass through
        }
    }
}

/**
 * Helper to fetch stats count JSON with Edge Cache.
 */
async function fetchStats(origin, lang, env, ctx) {
    // Fast-Path 1: Check Cloudflare KV
    const kv = env?.HELMETSAN_KV || env?.EDGE_KV;
    if (kv) {
        try {
            const kvStats = await kv.get(`stats_${lang}`, 'json');
            if (kvStats) return kvStats;
        } catch (e) {}
    }

    const cache = caches.default;
    const statsUrl = `${origin}/wp-json/helmetsan/v1/edge/stats?lang=${lang}`;
    const cacheKey = new Request(statsUrl, {
        method: 'GET',
        headers: { 'Accept': 'application/json' }
    });

    try {
        let cachedResponse = await cache.match(cacheKey);
        if (cachedResponse) {
            return await cachedResponse.json();
        }

        const response = await fetch(statsUrl);
        if (response.ok) {
            const data = await response.json();
            const cacheResponse = new Response(JSON.stringify(data), {
                headers: {
                    'Content-Type': 'application/json',
                    'Cache-Control': 'public, max-age=1800' // Cache stats for 30 mins
                }
            });
            if (ctx && ctx.waitUntil) {
                ctx.waitUntil(cache.put(cacheKey, cacheResponse));
            }
            return data;
        }
    } catch (e) {
        console.error('Edge stats fetch error:', e);
    }
    return null;
}

/**
 * Handler for replacing the inner text of stats numbers.
 */
class StatsHandler {
    constructor(type, statsPromise) {
        this.type = type;
        this.statsPromise = statsPromise;
    }

    async element(element) {
        try {
            const stats = await this.statsPromise;
            if (stats && stats[this.type] !== undefined) {
                element.setInnerContent(String(stats[this.type]));
            }
        } catch (e) {
            console.error(`Edge stats injection error for ${this.type}:`, e);
        }
    }
}

/**
 * Handler for dynamic Geo-IP price conversion on .hs-price elements.
 */
class PriceElementHandler {
    constructor(country, lang, origin, env, ctx) {
        this.country = country;
        this.lang = lang || 'en';
        this.origin = origin;
        this.env = env;
        this.ctx = ctx;
        this.ratesPromise = null;
    }

    async element(element) {
        const basePriceAttr = element.getAttribute('data-base-price');
        const baseCurrencyAttr = element.getAttribute('data-base-currency') || 'USD';
        const manualPricingAttr = element.getAttribute('data-manual-pricing');

        if (!basePriceAttr) {
            return;
        }

        const basePrice = parseFloat(basePriceAttr);
        if (isNaN(basePrice) || basePrice <= 0) {
            return;
        }

        // Country config mapping (identical to COUNTRY_MAP in PHP)
        const countryMap = {
            'US': { region: 'NA',   currency: 'USD' },
            'CA': { region: 'NA',   currency: 'CAD' },
            'MX': { region: 'NA',   currency: 'MXN' },
            'UK': { region: 'EU',   currency: 'GBP' },
            'GB': { region: 'EU',   currency: 'GBP' },
            'DE': { region: 'EU',   currency: 'EUR' },
            'FR': { region: 'EU',   currency: 'EUR' },
            'IT': { region: 'EU',   currency: 'EUR' },
            'ES': { region: 'EU',   currency: 'EUR' },
            'PL': { region: 'EU',   currency: 'PLN' },
            'CH': { region: 'EU',   currency: 'CHF' },
            'SE': { region: 'EU',   currency: 'SEK' },
            'NO': { region: 'EU',   currency: 'NOK' },
            'IN': { region: 'APAC', currency: 'INR' },
            'JP': { region: 'APAC', currency: 'JPY' },
            'AU': { region: 'APAC', currency: 'AUD' },
            'NZ': { region: 'APAC', currency: 'NZD' },
            'SG': { region: 'APAC', currency: 'SGD' },
            'KR': { region: 'APAC', currency: 'KRW' },
            'BR': { region: 'SA',   currency: 'BRL' },
            'AE': { region: 'ME',   currency: 'AED' },
            'NG': { region: 'AF',   currency: 'NGN' },
            'KE': { region: 'AF',   currency: 'KES' },
            'EG': { region: 'AF',   currency: 'EGP' },
            'MA': { region: 'AF',   currency: 'MAD' },
            'GH': { region: 'AF',   currency: 'GHS' },
            'UG': { region: 'AF',   currency: 'UGX' },
            'TZ': { region: 'AF',   currency: 'TZS' }
        };

        const geo = countryMap[this.country] || { region: 'NA', currency: 'USD' };
        const targetCurrency = geo.currency;

        // 1. Check manual pricing first
        if (manualPricingAttr) {
            try {
                // Decode HTML entities (just in case HTMLRewriter doesn't decode them)
                const decodedJson = manualPricingAttr
                    .replace(/&quot;/g, '"')
                    .replace(/&amp;/g, '&')
                    .replace(/&lt;/g, '<')
                    .replace(/&gt;/g, '>');
                const manualPricing = JSON.parse(decodedJson);
                if (manualPricing && manualPricing[this.country]) {
                    const entry = manualPricing[this.country];
                    const price = parseFloat(entry.current_price || entry.price);
                    const curr = entry.currency || targetCurrency;
                    if (!isNaN(price) && price > 0) {
                        let formattedPrice = formatCurrency(price, curr);
                        if (isVatCountry(this.country)) {
                            const vatLabel = getVatLabel(this.country, this.lang);
                            formattedPrice += ` <small class="hs-tax-label">${vatLabel}</small>`;
                            element.setInnerContent(formattedPrice, { html: true });
                        } else {
                            element.setInnerContent(formattedPrice);
                        }
                        return;
                    }
                }
            } catch (e) {
                // Not a fatal error, just fall back
            }
        }

        // 2. Dynamic conversion using exchange rates
        try {
            if (!this.ratesPromise) {
                this.ratesPromise = fetchExchangeRates(this.origin, this.env, this.ctx);
            }
            const rates = await this.ratesPromise;
            if (rates && rates[targetCurrency]) {
                const fromRate = rates[baseCurrencyAttr] || 1.0;
                const toRate = rates[targetCurrency];
                let converted = (basePrice / fromRate) * toRate;

                // Apply VAT and Charm Rounding at the Edge
                converted = applyVat(converted, this.country);
                converted = charmRound(converted, targetCurrency);

                let formattedPrice = formatCurrency(converted, targetCurrency);
                if (isVatCountry(this.country)) {
                    const vatLabel = getVatLabel(this.country, this.lang);
                    formattedPrice += ` <small class="hs-tax-label">${vatLabel}</small>`;
                    element.setInnerContent(formattedPrice, { html: true });
                } else {
                    element.setInnerContent(formattedPrice);
                }
            }
        } catch (e) {
            console.error('Error converting price:', e);
        }
    }
}

/**
 * Fetch exchange rates with caching at the Edge (Cache API).
 */
async function fetchExchangeRates(origin, env, ctx) {
    // Fast-Path 1: Check Cloudflare KV (0ms latency)
    const kv = env?.HELMETSAN_KV || env?.EDGE_KV;
    if (kv) {
        try {
            const kvRates = await kv.get('exchange_rates', 'json');
            if (kvRates) return kvRates;
        } catch (e) {}
    }

    const cache = caches.default;
    const ratesUrl = `${origin}/wp-json/helmetsan/v1/edge/rates`;
    const cacheKey = new Request(ratesUrl, {
        method: 'GET',
        headers: { 'Accept': 'application/json' }
    });

    try {
        let cachedResponse = await cache.match(cacheKey);
        if (cachedResponse) {
            const data = await cachedResponse.json();
            return data.rates || null;
        }

        const response = await fetch(ratesUrl);
        if (response.ok) {
            const data = await response.json();
            const cacheResponse = new Response(JSON.stringify(data), {
                headers: {
                    'Content-Type': 'application/json',
                    'Cache-Control': 'public, max-age=43200' // Cache rates for 12 hours
                }
            });
            if (ctx && ctx.waitUntil) {
                ctx.waitUntil(cache.put(cacheKey, cacheResponse));
            }
            return data.rates || null;
        }
    } catch (e) {
        console.error('Edge exchange rates fetch error:', e);
    }
    return null;
}

/**
 * Localized VAT / Tax label based on active language.
 */
function getVatLabel(country, lang) {
    const labelsByLang = {
        'de': 'inkl. MwSt.',
        'fr': 'TTC',
        'es': 'IVA incl.',
        'it': 'IVA incl.',
        'pl': 'z VAT',
        'pt': 'IVA incl.',
        'nl': 'incl. btw',
        'ja': '税込',
        'zh': '含税',
        'en': 'incl. VAT'
    };
    return labelsByLang[lang] || 'incl. VAT';
}

/**
 * Simple multi-currency formatter matching CurrencyFormatter.php.
 */
function formatCurrency(amount, currency) {
    const CURRENCIES = {
        'USD': { symbol: '$',    position: 'before', decimals: 2, thousands: ',', decimal: '.' },
        'EUR': { symbol: '€',    position: 'before', decimals: 2, thousands: '.', decimal: ',' },
        'GBP': { symbol: '£',    position: 'before', decimals: 2, thousands: ',', decimal: '.' },
        'CHF': { symbol: 'CHF ', position: 'before', decimals: 2, thousands: "'", decimal: '.' },
        'SEK': { symbol: ' kr',  position: 'after',  decimals: 2, thousands: ' ', decimal: ',' },
        'NOK': { symbol: ' kr',  position: 'after',  decimals: 2, thousands: ' ', decimal: ',' },
        'INR': { symbol: '₹',    position: 'before', decimals: 0, thousands: ',', decimal: '.' },
        'JPY': { symbol: '¥',    position: 'before', decimals: 0, thousands: ',', decimal: '.' },
        'AUD': { symbol: 'A$',   position: 'before', decimals: 2, thousands: ',', decimal: '.' },
        'NZD': { symbol: 'NZ$',  position: 'before', decimals: 2, thousands: ',', decimal: '.' },
        'CAD': { symbol: 'CA$',  position: 'before', decimals: 2, thousands: ',', decimal: '.' },
        'SGD': { symbol: 'S$',   position: 'before', decimals: 2, thousands: ',', decimal: '.' },
        'MXN': { symbol: 'MX$',  position: 'before', decimals: 2, thousands: ',', decimal: '.' },
        'BRL': { symbol: 'R$',   position: 'before', decimals: 2, thousands: '.', decimal: ',' },
        'PLN': { symbol: 'zł',   position: 'after',  decimals: 2, thousands: ' ', decimal: ',' },
        'AED': { symbol: 'AED ', position: 'before', decimals: 2, thousands: ',', decimal: '.' },
        'NGN': { symbol: '₦',    position: 'before', decimals: 0, thousands: ',', decimal: '.' },
        'KES': { symbol: 'KSh ', position: 'before', decimals: 0, thousands: ',', decimal: '.' },
        'EGP': { symbol: 'E£',   position: 'before', decimals: 2, thousands: ',', decimal: '.' },
        'MAD': { symbol: ' MAD', position: 'after',  decimals: 2, thousands: ' ', decimal: ',' },
        'GHS': { symbol: 'GH₵',  position: 'before', decimals: 2, thousands: ',', decimal: '.' },
        'UGX': { symbol: 'USh ', position: 'before', decimals: 0, thousands: ',', decimal: '.' },
        'TZS': { symbol: 'TSh ', position: 'before', decimals: 0, thousands: ',', decimal: '.' },
        'KRW': { symbol: '₩',    position: 'before', decimals: 0, thousands: ',', decimal: '.' }
    };

    const cfg = CURRENCIES[currency] || { symbol: ' ' + currency, position: 'after', decimals: 2, thousands: ',', decimal: '.' };
    
    // Format numeric value
    let parts = amount.toFixed(cfg.decimals).split('.');
    parts[0] = parts[0].replace(/\B(?=(\d{3})+(?!\d))/g, cfg.thousands);
    const formatted = parts.join(cfg.decimal);

    return cfg.position === 'before'
        ? cfg.symbol + formatted
        : formatted + cfg.symbol;
}

/**
 * Check if the country requires VAT display on the edge.
 */
function isVatCountry(country) {
    const vatCountries = [
        'DE', 'FR', 'IT', 'ES', 'GB', 'UK', 'PL', 'AT', 'BE', 'BG', 'CY', 'CZ', 'DK', 'EE', 'FI',
        'GR', 'HR', 'HU', 'IE', 'LT', 'LU', 'LV', 'MT', 'NL', 'PT', 'RO', 'SE', 'SI', 'SK',
        'CH', 'NO'
    ];
    return vatCountries.includes(country);
}

/**
 * Apply country-specific VAT at the edge.
 */
function applyVat(amount, country) {
    const vatRates = {
        'DE': 1.19, // 19%
        'FR': 1.20, // 20%
        'IT': 1.22, // 22%
        'ES': 1.21, // 21%
        'GB': 1.20, // 20%
        'UK': 1.20, // 20%
        'PL': 1.23, // 23%
        'CH': 1.081, // 8.1%
        'SE': 1.25, // 25%
        'NO': 1.25, // 25%
        'AT': 1.20, 'BE': 1.21, 'BG': 1.20, 'CY': 1.19, 'CZ': 1.21,
        'DK': 1.25, 'EE': 1.22, 'FI': 1.24, 'GR': 1.24, 'HR': 1.25,
        'HU': 1.27, 'IE': 1.23, 'LT': 1.21, 'LU': 1.17, 'LV': 1.21,
        'MT': 1.18, 'NL': 1.21, 'PT': 1.23, 'RO': 1.19,
        'SI': 1.22, 'SK': 1.20
    };

    const rate = vatRates[country] || 1.0;
    return amount * rate;
}

/**
 * Apply psychological rounding (charm pricing) at the edge.
 */
function charmRound(amount, currency) {
    if (amount <= 0.0) {
        return 0.0;
    }
    if (amount < 5.0) {
        return amount;
    }

    const decimalCurrencies = ['USD', 'EUR', 'GBP', 'CAD', 'AUD', 'MXN', 'BRL', 'PLN', 'CHF', 'SEK', 'NOK', 'NZD', 'SGD'];

    if (decimalCurrencies.includes(currency)) {
        const rounded = Math.round(amount);
        if (currency === 'EUR' || currency === 'GBP' || currency === 'CHF') {
            return rounded - 0.01;
        }
        return rounded - 0.05;
    }

    // Zero-decimal currencies
    if (amount < 1000.0) {
        return Math.round(amount / 10.0) * 10.0 - 1.0;
    } else {
        return Math.round(amount / 100.0) * 100.0 - 10.0;
    }
}

const COUNTRY_METADATA = {
    'IN': { name: 'India', currency: 'INR', symbol: '₹', flag: '🇮🇳' },
    'US': { name: 'United States', currency: 'USD', symbol: '$', flag: '🇺🇸' },
    'GB': { name: 'United Kingdom', currency: 'GBP', symbol: '£', flag: '🇬🇧' },
    'UK': { name: 'United Kingdom', currency: 'GBP', symbol: '£', flag: '🇬🇧' },
    'DE': { name: 'Germany', currency: 'EUR', symbol: '€', flag: '🇩🇪' },
    'FR': { name: 'France', currency: 'EUR', symbol: '€', flag: '🇫🇷' },
    'IT': { name: 'Italy', currency: 'EUR', symbol: '€', flag: '🇮🇹' },
    'ES': { name: 'Spain', currency: 'EUR', symbol: '€', flag: '🇪🇸' },
    'NL': { name: 'Netherlands', currency: 'EUR', symbol: '€', flag: '🇳🇱' },
    'PL': { name: 'Poland', currency: 'PLN', symbol: 'zł', flag: '🇵🇱' },
    'BE': { name: 'Belgium', currency: 'EUR', symbol: '€', flag: '🇧🇪' },
    'CH': { name: 'Switzerland', currency: 'CHF', symbol: 'CHF ', flag: '🇨🇭' },
    'SE': { name: 'Sweden', currency: 'SEK', symbol: ' kr', flag: '🇸🇪' },
    'NO': { name: 'Norway', currency: 'NOK', symbol: ' kr', flag: '🇳🇴' },
    'CA': { name: 'Canada', currency: 'CAD', symbol: 'CA$', flag: '🇨🇦' },
    'MX': { name: 'Mexico', currency: 'MXN', symbol: 'MX$', flag: '🇲🇽' },
    'JP': { name: 'Japan', currency: 'JPY', symbol: '¥', flag: '🇯🇵' },
    'AU': { name: 'Australia', currency: 'AUD', symbol: 'A$', flag: '🇦🇺' },
    'NZ': { name: 'New Zealand', currency: 'NZD', symbol: 'NZ$', flag: '🇳🇿' },
    'SG': { name: 'Singapore', currency: 'SGD', symbol: 'S$', flag: '🇸🇬' },
    'KR': { name: 'South Korea', currency: 'KRW', symbol: '₩', flag: '🇰🇷' },
    'AE': { name: 'United Arab Emirates', currency: 'AED', symbol: 'AED ', flag: '🇦🇪' },
    'SA': { name: 'Saudi Arabia', currency: 'SAR', symbol: 'SAR ', flag: '🇸🇦' },
    'BR': { name: 'Brazil', currency: 'BRL', symbol: 'R$', flag: '🇧🇷' },
    'NG': { name: 'Nigeria', currency: 'NGN', symbol: '₦', flag: '🇳🇬' },
    'KE': { name: 'Kenya', currency: 'KES', symbol: 'KSh ', flag: '🇰🇪' },
    'EG': { name: 'Egypt', currency: 'EGP', symbol: 'E£', flag: '🇪🇬' },
    'MA': { name: 'Morocco', currency: 'MAD', symbol: 'MAD', flag: '🇲🇦' },
    'GH': { name: 'Ghana', currency: 'GHS', symbol: 'GH₵', flag: '🇬🇭' },
    'UG': { name: 'Uganda', currency: 'UGX', symbol: 'USh ', flag: '🇺🇬' },
    'TZ': { name: 'Tanzania', currency: 'TZS', symbol: 'TSh ', flag: '🇹🇿' }
};

class CountryTriggerHandler {
    constructor(country) {
        this.info = COUNTRY_METADATA[country] || COUNTRY_METADATA['IN'];
        this.country = country;
    }

    element(element) {
        const id = element.getAttribute('id');
        if (id === 'hsCurrentFlag') {
            element.setInnerContent(this.info.flag);
        } else if (id === 'hsCurrentCountry') {
            element.setInnerContent(this.info.name);
        } else if (id === 'hsCurrentCode') {
            element.setInnerContent(this.country);
        } else if (id === 'hsCurrentCurrency') {
            element.setInnerContent(`(${this.info.symbol})`);
        }
    }
}

class CountryCardHandler {
    constructor(activeCountry) {
        this.activeCountry = activeCountry;
    }

    element(element) {
        const code = element.getAttribute('data-country-code');
        const currentClass = element.getAttribute('class') || '';
        if (code === this.activeCountry) {
            if (!currentClass.includes('is-active')) {
                element.setAttribute('class', (currentClass + ' is-active').trim());
            }
            element.setAttribute('aria-selected', 'true');
        } else {
            element.setAttribute('class', currentClass.replace(/\bis-active\b/g, '').trim());
            element.setAttribute('aria-selected', 'false');
        }
    }
}
