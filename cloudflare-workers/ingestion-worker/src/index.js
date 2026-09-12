/**
 * Cloudflare Worker for Helmetsan Media Ingestion & Queue Consumer
 * Handles async media fetching, R2 bucket storage with dynamic MIME detection,
 * AbortSignal timeouts, and HMAC-signed WordPress callbacks.
 */

const MAX_IMAGE_BYTES = 15 * 1024 * 1024; // 15MB max limit

/**
 * Computes an HMAC SHA-256 hex digest for request authentication.
 */
async function generateHmacSha256(secret, message) {
    if (!secret) return '';
    try {
        const encoder = new TextEncoder();
        const key = await crypto.subtle.importKey(
            'raw',
            encoder.encode(secret),
            { name: 'HMAC', hash: 'SHA-256' },
            false,
            ['sign']
        );
        const signature = await crypto.subtle.sign('HMAC', key, encoder.encode(message));
        return Array.from(new Uint8Array(signature)).map(b => b.toString(16).padStart(2, '0')).join('');
    } catch (e) {
        console.error('HMAC generation error:', e);
        return '';
    }
}

/**
 * Maps Content-Type to standard image extension.
 */
function getExtensionFromMime(contentType) {
    const type = (contentType || '').toLowerCase().split(';')[0].trim();
    switch (type) {
        case 'image/webp': return '.webp';
        case 'image/png': return '.png';
        case 'image/avif': return '.avif';
        case 'image/gif': return '.gif';
        case 'image/svg+xml': return '.svg';
        default: return '.jpg';
    }
}

export default {
    // HTTP handler for healthcheck / manual invocation
    async fetch(request, env) {
        const url = new URL(request.url);
        if (url.pathname === '/health') {
            return new Response(JSON.stringify({
                status: 'healthy',
                worker: 'helmetsan-ingestion-worker',
                r2_bound: Boolean(env.ASSETS_BUCKET),
                timestamp: new Date().toISOString()
            }), {
                headers: { 'Content-Type': 'application/json' }
            });
        }
        return new Response('Helmetsan Ingestion Queue Worker Active', { status: 200 });
    },

    // Queue Consumer
    async queue(batch, env) {
        const messages = batch.messages;

        for (const message of messages) {
            try {
                console.log(`Processing ingestion message: ${message.id}`);
                const payload = message.body;

                // Validate payload
                if (!payload || !payload.source_url || !payload.helmet_id) {
                    console.error("Invalid payload missing source_url or helmet_id");
                    message.ack(); // Discard invalid message
                    continue;
                }

                // 1. Fetch image from source_url with a strict 15s timeout
                const imageResponse = await fetch(payload.source_url, {
                    headers: {
                        'User-Agent': 'Helmetsan-Media-Ingest/2.0 (+https://helmetsan.com)'
                    },
                    signal: AbortSignal.timeout(15000)
                });

                if (!imageResponse.ok) {
                    throw new Error(`Failed to fetch image from ${payload.source_url}: HTTP ${imageResponse.status}`);
                }

                const contentType = imageResponse.headers.get('content-type') || 'image/jpeg';
                const fileExt = getExtensionFromMime(contentType);

                // Read buffer and guard against excessive file sizes
                const imageArrayBuffer = await imageResponse.arrayBuffer();
                if (imageArrayBuffer.byteLength > MAX_IMAGE_BYTES) {
                    throw new Error(`Image exceeds size limit of ${MAX_IMAGE_BYTES} bytes: ${imageArrayBuffer.byteLength}`);
                }

                // 2. Upload directly to Cloudflare R2
                const cleanSlug = String(payload.helmet_title || 'helmet')
                    .replace(/[^a-z0-9]/gi, '-')
                    .toLowerCase()
                    .replace(/-+/g, '-')
                    .slice(0, 60);

                const now = new Date();
                const year = now.getFullYear();
                const month = String(now.getMonth() + 1).padStart(2, '0');
                const r2Key = `assets/${year}/${month}/${cleanSlug}-${message.id.slice(0, 8)}${fileExt}`;

                if (env.ASSETS_BUCKET) {
                    await env.ASSETS_BUCKET.put(r2Key, imageArrayBuffer, {
                        httpMetadata: {
                            contentType: contentType,
                            cacheControl: 'public, max-age=31536000, immutable'
                        }
                    });
                } else {
                    console.warn('ASSETS_BUCKET not bound, mock write for key:', r2Key);
                }

                const publicBase = env.R2_PUBLIC_URL || 'https://media.helmetsan.com';
                const r2Url = `${publicBase}/${r2Key}`;

                // 3. Optional photo classification / metadata
                const photoType = payload.photo_type || 'standard';

                // 4. Callback to WordPress to finalize CPT attachment
                const restBase = env.WORDPRESS_REST_URL || 'https://helmetsan.com/wp-json';
                const callbackUrl = `${restBase}/helmetsan/v1/ingestion/callback`;

                const callbackBody = JSON.stringify({
                    helmet_id: payload.helmet_id,
                    source_url: payload.source_url,
                    r2_url: r2Url,
                    photo_type: photoType,
                    file_size: imageArrayBuffer.byteLength,
                    content_type: contentType
                });

                const secret = env.WORDPRESS_WEBHOOK_SECRET || '';
                const signature = await generateHmacSha256(secret, callbackBody);

                const callbackHeaders = {
                    'Content-Type': 'application/json',
                    'Authorization': `Bearer ${secret}`
                };
                if (signature) {
                    callbackHeaders['X-Helmetsan-Signature'] = signature;
                }

                const callbackResponse = await fetch(callbackUrl, {
                    method: 'POST',
                    headers: callbackHeaders,
                    body: callbackBody,
                    signal: AbortSignal.timeout(10000)
                });

                if (!callbackResponse.ok) {
                    throw new Error(`WordPress callback failed: HTTP ${callbackResponse.status}`);
                }

                console.log(`Successfully ingested ${payload.source_url} -> ${r2Url}`);
                message.ack();

            } catch (error) {
                console.error(`Error processing message ${message.id}:`, error);
                // Re-queue message for retry with exponential backoff if Cloudflare Queue permits
                message.retry();
            }
        }
    }
};
