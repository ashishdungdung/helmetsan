/**
 * Cloudflare Worker for Helmetsan Image Resizing & SSRF Shield
 * Uses Cloudflare Image Resizing with strict SSRF host filtering,
 * parameter clamping, and automatic AVIF/WebP negotiation.
 */

const ALLOWED_HOSTS = new Set([
    'helmetsan.com',
    'media.helmetsan.com',
    'assets.helmetsan.com',
    'cdn.helmetsan.com'
]);

const ALLOWED_FITS = new Set(['scale-down', 'contain', 'cover', 'crop', 'pad']);
const ALLOWED_FORMATS = new Set(['avif', 'webp', 'jpeg', 'png']);

/**
 * Checks if a hostname is authorized to prevent SSRF vulnerabilities.
 */
function isAllowedHost(hostname, extraHosts) {
    if (!hostname) return false;
    const host = hostname.toLowerCase();
    if (ALLOWED_HOSTS.has(host)) return true;
    if (host.endsWith('.helmetsan.com')) return true;

    if (extraHosts) {
        const list = extraHosts.split(',').map(h => h.trim().toLowerCase());
        if (list.includes(host)) return true;
    }

    return false;
}

/**
 * Clamps an integer between min and max.
 */
function clamp(val, min, max, defaultVal) {
    const num = parseInt(val, 10);
    if (isNaN(num)) return defaultVal;
    return Math.min(Math.max(num, min), max);
}

export default {
    async fetch(request, env, ctx) {
        // Only accept GET and HEAD requests
        if (request.method !== 'GET' && request.method !== 'HEAD') {
            return new Response('Method Not Allowed', { status: 405 });
        }

        const url = new URL(request.url);

        // Verify request host against whitelist
        if (!isAllowedHost(url.hostname, env.ALLOWED_HOSTS)) {
            return new Response('Forbidden: Hostname not allowed for image proxy', { status: 403 });
        }

        // Parse query parameters
        const rawWidth = url.searchParams.get('width') || url.searchParams.get('w');
        const rawHeight = url.searchParams.get('height') || url.searchParams.get('h');
        const rawQuality = url.searchParams.get('quality') || url.searchParams.get('q');
        const rawFormat = url.searchParams.get('format') || url.searchParams.get('f');
        const rawFit = url.searchParams.get('fit');

        const options = {};

        // Clamp width & height (16 to 2560 px)
        if (rawWidth) options.width = clamp(rawWidth, 16, 2560, null);
        if (rawHeight) options.height = clamp(rawHeight, 16, 2560, null);

        // Clamp quality (30 to 95)
        if (rawQuality) {
            options.quality = clamp(rawQuality, 30, 95, 80);
        }

        // Fit validation
        if (rawFit && ALLOWED_FITS.has(rawFit.toLowerCase())) {
            options.fit = rawFit.toLowerCase();
        } else if (options.width || options.height) {
            options.fit = 'scale-down';
        }

        // Format negotiation
        if (rawFormat && ALLOWED_FORMATS.has(rawFormat.toLowerCase())) {
            options.format = rawFormat.toLowerCase();
        } else {
            // Automatic modern format negotiation via Accept header
            const acceptHeader = request.headers.get('Accept') || '';
            if (acceptHeader.includes('image/avif')) {
                options.format = 'avif';
            } else if (acceptHeader.includes('image/webp')) {
                options.format = 'webp';
            }
        }

        // If no image transformations were requested, pass through with aggressive caching
        if (!options.width && !options.height && !options.quality && !options.format) {
            const directReq = new Request(request);
            directReq.headers.set('Cache-Control', 'public, max-age=31536000, immutable');
            return fetch(directReq);
        }

        // Resolve target URL (allow rewriting to R2 public domain if configured)
        let targetUrl = request.url;
        if (env.R2_PUBLIC_DOMAIN) {
            const target = new URL(request.url);
            target.hostname = env.R2_PUBLIC_DOMAIN;
            targetUrl = target.toString();
        }

        try {
            // Fetch resized image through Cloudflare's Image Resizing engine
            const cfOptions = {
                image: options,
                cacheEverything: true,
                cacheTtl: 31536000 // 1 year edge cache
            };
            const imageRequest = new Request(targetUrl, {
                headers: request.headers,
                cf: cfOptions
            });
            imageRequest.cf = cfOptions;

            const response = await fetch(imageRequest);

            if (response.ok || response.status === 304) {
                const newResponse = new Response(response.body, response);
                newResponse.headers.set('Cache-Control', 'public, max-age=31536000, immutable');
                newResponse.headers.set('Access-Control-Allow-Origin', '*');
                newResponse.headers.set('X-Content-Type-Options', 'nosniff');
                newResponse.headers.set('Vary', 'Accept');
                return newResponse;
            }

            // If Cloudflare image resizing isn't enabled or origin returned error, pass through
            return response;

        } catch (err) {
            console.error('Image resizing worker error:', err);
            return fetch(request);
        }
    }
};
