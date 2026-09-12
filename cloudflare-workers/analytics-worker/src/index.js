/**
 * Cloudflare Worker for Helmetsan Analytics (D1 Click & Telemetry Tracking)
 * Includes GDPR-compliant salted IP hashing, event whitelisting, and structured fallback.
 */

const ALLOWED_EVENTS = new Set([
    'click',
    'search',
    'filter',
    'view_pdp',
    'affiliate_exit',
    'pageview',
    'compare',
    'share',
    'review_submit'
]);

const CORS_HEADERS = {
    'Access-Control-Allow-Origin': '*',
    'Access-Control-Allow-Methods': 'POST, OPTIONS',
    'Access-Control-Allow-Headers': 'Content-Type, X-WP-Nonce',
    'Access-Control-Max-Age': '86400',
};

/**
 * Generates a privacy-safe salted SHA-256 hash of an IP address.
 * Never stores raw IP addresses in compliance with GDPR.
 */
async function hashIp(ip, salt = 'helmetsan-gdpr-salt-2026') {
    if (!ip || ip === '0.0.0.0') return '00000000000000000000000000000000';
    try {
        const encoder = new TextEncoder();
        const data = encoder.encode(ip + ':' + salt);
        const hashBuffer = await crypto.subtle.digest('SHA-256', data);
        const hashArray = Array.from(new Uint8Array(hashBuffer));
        return hashArray.map(b => b.toString(16).padStart(2, '0')).join('').slice(0, 32);
    } catch {
        return 'hash_err';
    }
}

export default {
    async fetch(request, env, ctx) {
        // Handle CORS Preflight
        if (request.method === 'OPTIONS') {
            return new Response(null, { headers: CORS_HEADERS });
        }

        // Only accept POST
        if (request.method !== 'POST') {
            return new Response(JSON.stringify({ error: 'Method Not Allowed' }), {
                status: 405,
                headers: { ...CORS_HEADERS, 'Content-Type': 'application/json' }
            });
        }

        try {
            let body;
            const contentType = request.headers.get('content-type') || '';
            
            if (contentType.includes('application/json')) {
                body = await request.json();
            } else {
                // Support sendBeacon or text/plain payloads
                const text = await request.text();
                try {
                    body = JSON.parse(text);
                } catch {
                    return new Response(JSON.stringify({ ok: false, error: 'Invalid JSON payload' }), {
                        status: 400,
                        headers: { ...CORS_HEADERS, 'Content-Type': 'application/json' }
                    });
                }
            }

            // Validate basic payload
            if (!body || !body.event_name) {
                return new Response(JSON.stringify({ ok: false, error: 'Missing event_name' }), {
                    status: 400,
                    headers: { ...CORS_HEADERS, 'Content-Type': 'application/json' }
                });
            }

            const rawEvent = String(body.event_name).toLowerCase().trim().slice(0, 64);
            if (!ALLOWED_EVENTS.has(rawEvent)) {
                return new Response(JSON.stringify({ ok: false, error: `Invalid event_name: ${rawEvent}` }), {
                    status: 400,
                    headers: { ...CORS_HEADERS, 'Content-Type': 'application/json' }
                });
            }

            const eventName = rawEvent;
            const pageUrl = body.page_url ? String(body.page_url).slice(0, 500) : '';
            const referrer = body.referrer ? String(body.referrer).slice(0, 500) : '';
            const source = body.source ? String(body.source).slice(0, 50) : 'frontend';

            // Cap meta_json safely to 1024 bytes
            let metaJson = '{}';
            if (body.meta && typeof body.meta === 'object') {
                metaJson = JSON.stringify(body.meta).slice(0, 1024);
            }

            // Salted GDPR IP Hash
            const rawIp = request.headers.get('CF-Connecting-IP') || '0.0.0.0';
            const ipSalt = env.ANALYTICS_SALT || 'helmetsan-analytics-gdpr-salt';
            const ipHash = await hashIp(rawIp, ipSalt);

            // Persist to D1 or fallback to structured logging
            if (env.DB) {
                await env.DB.prepare(
                    `INSERT INTO analytics_events (event_name, page_url, referrer, source, meta_json, ip_hash) 
                     VALUES (?, ?, ?, ?, ?, ?)`
                ).bind(
                    eventName,
                    pageUrl,
                    referrer,
                    source,
                    metaJson,
                    ipHash
                ).run();
            } else {
                console.log(JSON.stringify({
                    level: 'INFO',
                    type: 'analytics_telemetry_fallback',
                    timestamp: new Date().toISOString(),
                    event_name: eventName,
                    page_url: pageUrl,
                    referrer: referrer,
                    source: source,
                    meta: metaJson,
                    ip_hash: ipHash
                }));
            }

            return new Response(JSON.stringify({ ok: true }), {
                status: 200,
                headers: {
                    ...CORS_HEADERS,
                    'Content-Type': 'application/json'
                }
            });

        } catch (error) {
            console.error('Analytics worker error:', error);
            return new Response(JSON.stringify({ ok: false, error: 'Internal Error' }), {
                status: 500,
                headers: {
                    ...CORS_HEADERS,
                    'Content-Type': 'application/json'
                }
            });
        }
    }
};
