/**
 * Cloudflare Worker for Helmetsan Edge Price Caching
 * Intercepts requests to /wp-json/hs/v1/prices/* and optionally other endpoints.
 */

// Cache duration in seconds (e.g., 3600 = 1 hour)
const CACHE_TTL = 3600;

export default {
    async fetch(request, env, ctx) {
        // Only cache GET requests
        if (request.method !== 'GET') {
            return fetch(request);
        }

        const url = new URL(request.url);

        // Define which paths to cache. In this case, the prices endpoints.
        // Example: /wp-json/hs/v1/prices/123/history
        if (!url.pathname.startsWith('/wp-json/hs/v1/prices/')) {
            return fetch(request);
        }

        // Generate a cache key based on the full URL including query parameters
        const cacheKey = url.toString();

        try {
            // Check if we have the KV namespace bound
            if (!env.PRICE_CACHE) {
                console.warn('PRICE_CACHE KV namespace not bound. Bypassing cache.');
                return fetch(request);
            }

            // Attempt to fetch from KV cache
            const cachedResponse = await env.PRICE_CACHE.get(cacheKey, 'json');

            if (cachedResponse) {
                // Cache HIT
                return new Response(JSON.stringify(cachedResponse), {
                    status: 200,
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Cache-Status': 'HIT',
                        'Cache-Control': `public, max-age=${CACHE_TTL}`
                    }
                });
            }

            // Cache MISS - Fetch from origin (WordPress)
            const response = await fetch(request);

            if (response.ok) {
                // Clone the response to read the body without consuming the original
                const responseData = await response.clone().json();

                // Store in KV asynchronously without blocking the client response
                ctx.waitUntil(
                    env.PRICE_CACHE.put(cacheKey, JSON.stringify(responseData), {
                        expirationTtl: CACHE_TTL
                    })
                );

                // Add cache status header
                const newHeaders = new Headers(response.headers);
                newHeaders.set('X-Cache-Status', 'MISS');
                // Ensure browser caches it as well, or you can let WP headers pass through
                newHeaders.set('Cache-Control', `public, max-age=${CACHE_TTL}`);

                return new Response(JSON.stringify(responseData), {
                    status: response.status,
                    headers: newHeaders
                });
            }

            // If origin failed, just pass through the response
            return response;

        } catch (error) {
            console.error('Cache worker error:', error);
            // Fallback to origin on worker error
            return fetch(request);
        }
    }
};
