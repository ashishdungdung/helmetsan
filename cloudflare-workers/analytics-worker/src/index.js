/**
 * Cloudflare Worker for Helmetsan Analytics (D1 Click Tracking)
 */

export default {
    async fetch(request, env, ctx) {
        // Handle CORS Preflight
        if (request.method === 'OPTIONS') {
            return new Response(null, {
                headers: {
                    'Access-Control-Allow-Origin': '*',
                    'Access-Control-Allow-Methods': 'POST, OPTIONS',
                    'Access-Control-Allow-Headers': 'Content-Type, X-WP-Nonce',
                    'Access-Control-Max-Age': '86400',
                }
            });
        }

        // Only accept POST
        if (request.method !== 'POST') {
            return new Response('Method Not Allowed', { status: 405 });
        }

        try {
            const body = await request.json();
            
            // Validate basic payload
            if (!body || !body.event_name) {
                return new Response('Missing event_name', { status: 400 });
            }

            const eventName = String(body.event_name).slice(0, 100);
            const pageUrl = body.page_url ? String(body.page_url).slice(0, 500) : '';
            const referrer = body.referrer ? String(body.referrer).slice(0, 500) : '';
            const source = body.source ? String(body.source).slice(0, 50) : 'frontend';
            
            // Serialize meta as JSON safely
            let metaJson = '{}';
            if (body.meta && typeof body.meta === 'object') {
                metaJson = JSON.stringify(body.meta).slice(0, 2000);
            }

            // Simple pseudo-anonymized IP hash
            const ip = request.headers.get('CF-Connecting-IP') || '0.0.0.0';
            
            // We use D1 to store the event
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
                    ip // Note: For strictly GDPR compliance, we shouldn't store raw IPs if it can be avoided. Storing it raw here as requested, or you can hash it in the worker: await crypto.subtle.digest('SHA-256', new TextEncoder().encode(ip))
                ).run();
            } else {
                console.warn('D1 Database not bound. Event dropped.');
            }

            return new Response(JSON.stringify({ ok: true }), {
                status: 200,
                headers: {
                    'Content-Type': 'application/json',
                    'Access-Control-Allow-Origin': '*'
                }
            });

        } catch (error) {
            console.error('Error processing event:', error);
            return new Response(JSON.stringify({ ok: false, error: 'Bad Request or Internal Error' }), { 
                status: 400,
                headers: {
                    'Content-Type': 'application/json',
                    'Access-Control-Allow-Origin': '*'
                }
            });
        }
    }
};
