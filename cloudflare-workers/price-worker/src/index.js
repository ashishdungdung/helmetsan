/**
 * Cloudflare Worker for Helmetsan Edge Price Caching
 * Intercepts requests to /wp-json/hs/v1/prices/* with KV edge caching,
 * tracking-parameter stripped cache keys, stale-while-revalidate,
 * and authenticated purge endpoints.
 */

const CACHE_TTL = 3600; // 1 hour fresh
const STALE_TTL = 86400; // 24 hours stale-while-revalidate

// Parameters to preserve when normalizing cache keys
const ALLOWED_QUERY_PARAMS = new Set(['currency', 'country', 'days', 'marketplace', 'affiliate', 'lang']);

/**
 * Normalizes a URL to create a canonical cache key.
 * Strips tracking parameters (utm_*, gclid, fbclid, etc.) and sorts query params.
 * Uses pathname + search to enable efficient prefix-based KV purging.
 */
function normalizeCacheKey(rawUrl, request) {
    const url = new URL(rawUrl);
    const params = new URLSearchParams();

    // Preserve country from query param, or derive from CF-IPCountry / cookie
    let country = url.searchParams.get('country') || '';
    if (!country && request) {
        const cookieHeader = request.headers?.get('Cookie') || '';
        const match = cookieHeader.match(/helmetsan_geo=([A-Z]{2})/i);
        if (match) {
            country = match[1];
        } else {
            country = request.headers?.get('CF-IPCountry') || 'IN';
        }
    }
    if (country) {
        params.set('country', country.toUpperCase().slice(0, 2));
    }

    // Only copy remaining allowed parameters and sort them
    const sortedKeys = Array.from(url.searchParams.keys()).sort();
    for (const key of sortedKeys) {
        if (key.toLowerCase() !== 'country' && ALLOWED_QUERY_PARAMS.has(key.toLowerCase())) {
            params.set(key.toLowerCase(), url.searchParams.get(key));
        }
    }

    const search = params.toString();
    return `${url.pathname}${search ? '?' + search : ''}`;
}

export default {
    async fetch(request, env, ctx) {
        const url = new URL(request.url);

        // 1. Handle Purge Endpoint (POST /wp-json/hs/v1/prices/purge)
        if (request.method === 'POST' && url.pathname.endsWith('/prices/purge')) {
            return handlePurgeRequest(request, env);
        }

        // Only cache GET requests
        if (request.method !== 'GET') {
            return fetch(request);
        }

        // Define which paths to cache (prices endpoints)
        if (!url.pathname.startsWith('/wp-json/hs/v1/prices/')) {
            return fetch(request);
        }

        const cacheKey = normalizeCacheKey(request.url, request);

        try {
            // Check if KV namespace is bound
            if (!env.PRICE_CACHE) {
                console.warn('PRICE_CACHE KV namespace not bound. Bypassing cache.');
                return fetch(request);
            }

            // Attempt to fetch from KV cache
            const cachedResponse = await env.PRICE_CACHE.get(cacheKey, 'json');

            if (cachedResponse) {
                return new Response(JSON.stringify(cachedResponse), {
                    status: 200,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Cache-Status': 'HIT',
                        'Cache-Control': `public, max-age=${CACHE_TTL}, stale-while-revalidate=${STALE_TTL}`,
                        'Access-Control-Allow-Origin': '*'
                    }
                });
            }

            // Cache MISS - Fetch from WordPress origin
            const response = await fetch(request);

            if (response.ok) {
                const contentType = response.headers.get('content-type') || '';
                
                // Only cache valid JSON responses
                if (contentType.includes('application/json')) {
                    const responseData = await response.clone().json();

                    // Store in KV asynchronously without blocking client
                    const putPromise = env.PRICE_CACHE.put(cacheKey, JSON.stringify(responseData), {
                        expirationTtl: CACHE_TTL + STALE_TTL
                    });

                    if (ctx && ctx.waitUntil) {
                        ctx.waitUntil(putPromise);
                    } else {
                        await putPromise;
                    }

                    const newHeaders = new Headers(response.headers);
                    newHeaders.set('X-Cache-Status', 'MISS');
                    newHeaders.set('Cache-Control', `public, max-age=${CACHE_TTL}, stale-while-revalidate=${STALE_TTL}`);
                    newHeaders.set('Access-Control-Allow-Origin', '*');

                    return new Response(JSON.stringify(responseData), {
                        status: response.status,
                        headers: newHeaders
                    });
                }
            }

            // If origin failed or returned non-JSON, pass through original response
            return response;

        } catch (error) {
            console.error('Price cache worker error:', error);
            // Graceful fallback to origin
            return fetch(request);
        }
    }
};

/**
 * Handles authenticated cache purging from WordPress webhooks or admin actions.
 */
async function handlePurgeRequest(request, env) {
    try {
        const authHeader = request.headers.get('Authorization') || '';
        const secretHeader = request.headers.get('X-Helmetsan-Secret') || '';
        const webhookSecret = env.WORDPRESS_WEBHOOK_SECRET || '';

        const isAuthorized = (
            webhookSecret && (
                authHeader === `Bearer ${webhookSecret}` ||
                secretHeader === webhookSecret
            )
        );

        if (!isAuthorized) {
            return new Response(JSON.stringify({ ok: false, error: 'Unauthorized' }), {
                status: 401,
                headers: { 'Content-Type': 'application/json' }
            });
        }

        if (!env.PRICE_CACHE) {
            return new Response(JSON.stringify({ ok: false, error: 'PRICE_CACHE not bound' }), {
                status: 503,
                headers: { 'Content-Type': 'application/json' }
            });
        }

        const body = await request.json().catch(() => ({}));
        let deletedCount = 0;

        if (body.helmet_id) {
            // Match canonical pathname prefix
            const shortPrefix = `/wp-json/hs/v1/prices/${body.helmet_id}`;
            const list1 = await env.PRICE_CACHE.list({ prefix: shortPrefix });
            for (const key of list1.keys) {
                await env.PRICE_CACHE.delete(key.name);
                deletedCount++;
            }
            // Also clean up any legacy keys containing origin
            const urlObj = new URL(request.url);
            const fullPrefix = `${urlObj.origin}${shortPrefix}`;
            const list2 = await env.PRICE_CACHE.list({ prefix: fullPrefix });
            for (const key of list2.keys) {
                await env.PRICE_CACHE.delete(key.name);
                deletedCount++;
            }
        } else if (body.purge_all === true) {
            let list = await env.PRICE_CACHE.list();
            for (const key of list.keys) {
                await env.PRICE_CACHE.delete(key.name);
                deletedCount++;
            }
        } else if (body.key) {
            await env.PRICE_CACHE.delete(body.key);
            deletedCount = 1;
        } else {
            return new Response(JSON.stringify({ ok: false, error: 'Provide helmet_id, key, or purge_all: true' }), {
                status: 400,
                headers: { 'Content-Type': 'application/json' }
            });
        }

        return new Response(JSON.stringify({ ok: true, purged_keys: deletedCount }), {
            status: 200,
            headers: { 'Content-Type': 'application/json' }
        });

    } catch (err) {
        return new Response(JSON.stringify({ ok: false, error: err.message }), {
            status: 500,
            headers: { 'Content-Type': 'application/json' }
        });
    }
}
