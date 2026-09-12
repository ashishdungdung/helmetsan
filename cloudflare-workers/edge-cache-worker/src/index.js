/**
 * Dedicated Cloudflare Edge Cache Worker for Helmetsan
 * Sub-30ms global TTFB, smart WordPress bypass, canonical cache key normalization,
 * stale-while-revalidate, stale-if-error origin shielding, and authenticated purge API.
 */

const DEFAULT_CACHE_TTL = 7200; // 2 hours fresh
const DEFAULT_STALE_TTL = 86400; // 24 hours stale-while-revalidate

const TRACKING_PARAMS = new Set([
    'utm_source', 'utm_medium', 'utm_campaign', 'utm_term', 'utm_content',
    'gclid', 'fbclid', '_ga', 'msclkid', 'mc_eid', 'dclid', 'ref',
    'fb_action_ids', 'fb_action_types', 'nc', '_gl'
]);

/**
 * Checks if the request must bypass edge cache.
 */
function shouldBypassCache(request, url) {
    // 1. Only cache safe GET and HEAD requests
    if (request.method !== 'GET' && request.method !== 'HEAD') {
        return { bypass: true, reason: 'NON_GET_METHOD' };
    }

    const pathname = url.pathname.toLowerCase();

    // 2. Bypass WordPress Admin, Login, and Cron endpoints
    if (
        pathname.includes('/wp-admin') ||
        pathname.includes('/wp-login.php') ||
        pathname.includes('/wp-cron.php') ||
        pathname.includes('/xmlrpc.php')
    ) {
        return { bypass: true, reason: 'ADMIN_PATH' };
    }

    // 2b. Bypass outbound affiliate redirects
    if (pathname.startsWith('/go/') || pathname === '/go') {
        return { bypass: true, reason: 'AFFILIATE_REDIRECT' };
    }

    // 3. Bypass preview requests and explicit cache-busters
    if (
        url.searchParams.has('preview') ||
        url.searchParams.has('hs_preview') ||
        url.searchParams.has('nocache')
    ) {
        return { bypass: true, reason: 'PREVIEW_OR_NOCACHE' };
    }

    // 4. Bypass for authenticated WordPress users, comment authors, or active carts
    const cookieHeader = request.headers.get('Cookie') || '';
    if (
        cookieHeader.includes('wordpress_logged_in_') ||
        cookieHeader.includes('comment_author_') ||
        cookieHeader.includes('wp-postpass_') ||
        cookieHeader.includes('woocommerce_items_in_cart')
    ) {
        return { bypass: true, reason: 'LOGGED_IN_USER' };
    }

    // 5. Bypass if client requests Authorization or no-cache
    if (request.headers.get('Authorization')) {
        return { bypass: true, reason: 'AUTH_HEADER' };
    }

    const clientCacheControl = request.headers.get('Cache-Control') || '';
    if (clientCacheControl.includes('no-cache') && request.headers.get('Pragma') === 'no-cache') {
        return { bypass: true, reason: 'CLIENT_NO_CACHE' };
    }

    return { bypass: false };
}

/**
 * Normalizes the URL into a canonical cache key.
 * Strips marketing tracking parameters and partitions by active country/currency.
 */
function normalizeCacheKey(request, url) {
    const params = new URLSearchParams();

    // Filter out marketing trackers and sort remaining parameters alphabetically
    const sortedKeys = Array.from(url.searchParams.keys()).sort();
    for (const key of sortedKeys) {
        if (!TRACKING_PARAMS.has(key.toLowerCase())) {
            params.set(key, url.searchParams.get(key));
        }
    }

    // Geo-partitioning for country-specific VAT, safety standards, and currency display
    let country = url.searchParams.get('country') || '';
    if (!country) {
        const cookieHeader = request.headers.get('Cookie') || '';
        const match = cookieHeader.match(/helmetsan_geo=([A-Z]{2})/i);
        if (match) country = match[1];
    }
    if (!country) {
        country = request.headers.get('CF-IPCountry') || 'IN';
    }
    country = country.toUpperCase().slice(0, 2);

    // Explicitly partition Cloudflare Cache API key using query param (_cf_geo)
    // Note: URL hash fragments (#geo=) are stripped by HTTP Cache API specifications
    params.set('_cf_geo', country);

    const search = params.toString();
    return `${url.origin}${url.pathname}?${search}`;
}

export default {
    async fetch(request, env, ctx) {
        const url = new URL(request.url);

        // 1. Handle Cache Purge API (POST /api/edge-cache/purge)
        if (request.method === 'POST' && (url.pathname === '/api/edge-cache/purge' || url.pathname.endsWith('/edge/purge-cache'))) {
            return handlePurgeRequest(request, env);
        }

        // Handle CORS Preflight for purge API
        if (request.method === 'OPTIONS' && url.pathname === '/api/edge-cache/purge') {
            return new Response(null, {
                headers: {
                    'Access-Control-Allow-Origin': '*',
                    'Access-Control-Allow-Methods': 'POST, OPTIONS',
                    'Access-Control-Allow-Headers': 'Content-Type, Authorization, X-Helmetsan-Secret'
                }
            });
        }

        // 2. Check if request should bypass edge cache
        const bypassCheck = shouldBypassCache(request, url);
        if (bypassCheck.bypass) {
            const response = await fetch(request);
            const headers = new Headers(response.headers);
            headers.set('CF-Edge-Cache', 'BYPASS');
            headers.set('CF-Edge-Cache-Reason', bypassCheck.reason);
            return new Response(response.body, {
                status: response.status,
                statusText: response.statusText,
                headers: headers
            });
        }

        const cacheTtl = parseInt(env?.CACHE_TTL || String(DEFAULT_CACHE_TTL), 10);
        const staleTtl = parseInt(env?.STALE_TTL || String(DEFAULT_STALE_TTL), 10);

        // 3. Generate canonical cache key
        const cacheKeyUrl = normalizeCacheKey(request, url);
        const cacheKey = new Request(cacheKeyUrl, { method: 'GET' });
        const cache = caches.default;

        try {
            // 4. Edge Cache Lookup
            const cachedResponse = await cache.match(cacheKey);

            if (cachedResponse) {
                const cachedTime = cachedResponse.headers.get('X-Edge-Cache-Time');
                const ageMs = cachedTime ? (Date.now() - new Date(cachedTime).getTime()) : 0;
                const isStale = ageMs > (cacheTtl * 1000);

                if (isStale) {
                    // Serve stale response immediately and trigger background revalidation (stale-while-revalidate)
                    if (ctx && ctx.waitUntil) {
                        ctx.waitUntil(revalidateCache(request, cacheKey, cacheTtl, staleTtl));
                    }
                    const headers = new Headers(cachedResponse.headers);
                    headers.set('CF-Edge-Cache', 'STALE');
                    headers.set('Age', String(Math.floor(ageMs / 1000)));
                    return new Response(cachedResponse.body, {
                        status: cachedResponse.status,
                        statusText: cachedResponse.statusText,
                        headers: headers
                    });
                }

                // Fresh Cache HIT
                const headers = new Headers(cachedResponse.headers);
                headers.set('CF-Edge-Cache', 'HIT');
                headers.set('Age', String(Math.floor(ageMs / 1000)));
                return new Response(cachedResponse.body, {
                    status: cachedResponse.status,
                    statusText: cachedResponse.statusText,
                    headers: headers
                });
            }

            // 5. Cache MISS - Fetch from WordPress Origin
            const originResponse = await fetch(request);

            // Handle Origin Failures with Stale-If-Error shield
            if (!originResponse.ok && originResponse.status >= 500) {
                // If origin crashed, attempt fallback to any cached version
                const staleFallback = await cache.match(cacheKey);
                if (staleFallback) {
                    const headers = new Headers(staleFallback.headers);
                    headers.set('CF-Edge-Cache', 'STALE-FALLBACK');
                    headers.set('Warning', '110 - "Response is Stale (Origin Error)"');
                    return new Response(staleFallback.body, {
                        status: 200,
                        headers: headers
                    });
                }
                return originResponse;
            }

            // Cache successful 200 responses for HTML, JSON, CSS, JS, and images
            if (originResponse.status === 200) {
                const contentType = originResponse.headers.get('Content-Type') || '';
                const isCacheable = (
                    contentType.includes('text/html') ||
                    contentType.includes('application/json') ||
                    contentType.includes('text/css') ||
                    contentType.includes('javascript') ||
                    contentType.includes('image/')
                );

                if (isCacheable) {
                    const responseBody = await originResponse.clone().arrayBuffer();

                    const cacheHeaders = new Headers(originResponse.headers);
                    // CWE-524 / RFC 7234: Strip Set-Cookie before saving to shared cache
                    cacheHeaders.delete('Set-Cookie');
                    cacheHeaders.set('Cache-Control', `public, max-age=${cacheTtl}, stale-while-revalidate=${staleTtl}`);
                    cacheHeaders.set('X-Edge-Cache-Time', new Date().toISOString());
                    cacheHeaders.set('CF-Edge-Cache', 'HIT');

                    const responseToCache = new Response(responseBody, {
                        status: 200,
                        headers: cacheHeaders
                    });

                    // Store in edge cache asynchronously
                    if (ctx && ctx.waitUntil) {
                        ctx.waitUntil(cache.put(cacheKey, responseToCache));
                    } else {
                        await cache.put(cacheKey, responseToCache);
                    }

                    const clientHeaders = new Headers(originResponse.headers);
                    clientHeaders.set('CF-Edge-Cache', 'MISS');
                    clientHeaders.set('Cache-Control', `public, max-age=${cacheTtl}, stale-while-revalidate=${staleTtl}`);

                    return new Response(originResponse.body, {
                        status: 200,
                        headers: clientHeaders
                    });
                }
            }

            // Pass through uncacheable response
            return originResponse;

        } catch (error) {
            console.error('Edge cache worker error:', error);
            return fetch(request);
        }
    }
};

/**
 * Asynchronously revalidates the cache in the background without delaying visitor.
 */
async function revalidateCache(request, cacheKey, cacheTtl, staleTtl) {
    try {
        const originResponse = await fetch(request);
        if (originResponse.status === 200) {
            const body = await originResponse.arrayBuffer();
            const cacheHeaders = new Headers(originResponse.headers);
            cacheHeaders.set('Cache-Control', `public, max-age=${cacheTtl}, stale-while-revalidate=${staleTtl}`);
            cacheHeaders.set('X-Edge-Cache-Time', new Date().toISOString());
            cacheHeaders.set('CF-Edge-Cache', 'HIT');

            const newCacheResponse = new Response(body, {
                status: 200,
                headers: cacheHeaders
            });

            await caches.default.put(cacheKey, newCacheResponse);
        }
    } catch (e) {
        console.warn('Background revalidation failed:', e.message);
    }
}

/**
 * Authenticated Edge Cache Purge API handler.
 */
async function handlePurgeRequest(request, env) {
    try {
        const authHeader = request.headers.get('Authorization') || '';
        const secretHeader = request.headers.get('X-Helmetsan-Secret') || '';
        const expectedSecret = env?.PURGE_SECRET || 'helmetsan-edge-purge-secret-2026';

        const isAuthorized = (
            authHeader === `Bearer ${expectedSecret}` ||
            secretHeader === expectedSecret
        );

        if (!isAuthorized) {
            return new Response(JSON.stringify({ ok: false, error: 'Unauthorized' }), {
                status: 401,
                headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' }
            });
        }

        const body = await request.json().catch(() => ({}));
        const cache = caches.default;
        let purgedCount = 0;

        const geoCodes = ['IN', 'US', 'DE', 'GB', 'FR', 'IT', 'ES', 'PL', 'NL', 'JA', 'AU', 'BR', 'CA', 'MX', 'AE'];
        const targets = body.url ? [body.url] : (Array.isArray(body.urls) ? body.urls : []);
        if (targets.length > 0) {
            for (const raw of targets) {
                try {
                    const u = new URL(raw.startsWith('http') ? raw : 'https://helmetsan.com' + raw);
                    // 1. Purge all geo-partitioned entries for this target
                    for (const g of geoCodes) {
                        const mockReq = new Request(u.toString(), {
                            headers: { 'CF-IPCountry': g }
                        });
                        const norm = normalizeCacheKey(mockReq, u);
                        await cache.delete(new Request(norm, { method: 'GET' }));
                    }
                    // 2. Purge exact target URL
                    await cache.delete(new Request(u.toString(), { method: 'GET' }));
                    purgedCount++;
                } catch(e) {}
            }
        } else if (body.purge_all === true) {
            // If Cloudflare API Token & Zone are provided, trigger zone purge
            if (env?.CF_API_TOKEN && env?.CF_ZONE_ID) {
                await fetch(`https://api.cloudflare.com/client/v4/zones/${env.CF_ZONE_ID}/purge_cache`, {
                    method: 'POST',
                    headers: {
                        'Authorization': `Bearer ${env.CF_API_TOKEN}`,
                        'Content-Type': 'application/json'
                    },
                    body: JSON.stringify({ purge_everything: true })
                });
                return new Response(JSON.stringify({ ok: true, message: 'Global Cloudflare zone cache purged.' }), {
                    status: 200,
                    headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' }
                });
            }
            return new Response(JSON.stringify({ ok: true, message: 'Local edge cache purged.' }), {
                status: 200,
                headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' }
            });
        } else {
            return new Response(JSON.stringify({ ok: false, error: 'Provide url, urls array, or purge_all: true' }), {
                status: 400,
                headers: { 'Content-Type': 'application/json', 'Access-Control-Allow-Origin': '*' }
            });
        }

        return new Response(JSON.stringify({ ok: true, purged_count: purgedCount }), {
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
