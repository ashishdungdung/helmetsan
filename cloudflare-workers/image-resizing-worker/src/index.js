export default {
    async fetch(request, env, ctx) {
        // Only accept GET requests
        if (request.method !== 'GET') {
            return new Response('Method Not Allowed', { status: 405 });
        }

        const url = new URL(request.url);

        // Get resizing options from query parameters
        // Example: ?width=800&quality=75&format=webp
        const width = url.searchParams.get('width');
        const height = url.searchParams.get('height');
        const quality = url.searchParams.get('quality');
        const format = url.searchParams.get('format');
        const fit = url.searchParams.get('fit');

        // Construct Cloudflare Image Resizing options
        const options = {};
        if (width) options.width = parseInt(width, 10);
        if (height) options.height = parseInt(height, 10);
        if (quality) options.quality = parseInt(quality, 10);
        if (format) options.format = format;
        if (fit) options.fit = fit; // e.g., 'scale-down', 'contain', 'cover', 'crop', 'pad'

        // If no resizing options are provided, just serve the original request
        if (Object.keys(options).length === 0) {
            // Modify headers to allow caching
            request = new Request(request);
            request.headers.set("Cache-Control", "public, max-age=31536000"); // Cache for 1 year
            return fetch(request);
        }

        // Must extract the path from the request URL to fetch from R2 bucket public URL
        // or directly from the bound bucket if configured
        
        // Option 1: Fetching from a public R2 domain or Origin
        // Assuming the worker is mapped to a route like media.helmetsan.com/*
        // and the R2 bucket or origin is also accessible (or we rewrite the hostname)
        
        let targetUrl = request.url;
        
        // If the worker is on a different domain than the actual R2 bucket's public domain,
        // we'd rewrite the hostname here, e.g.:
        // if (env.R2_PUBLIC_DOMAIN) {
        //     const target = new URL(request.url);
        //     target.hostname = env.R2_PUBLIC_DOMAIN;
        //     targetUrl = target.toString();
        // }

        // Fetch the optimized image using Cloudflare's built-in Image Resizing service.
        // This requires Image Resizing to be enabled in the Cloudflare Dashboard for the zone.
        const imageRequest = new Request(targetUrl, {
            headers: request.headers,
            cf: {
                image: options,
                // Cache the resized image
                cacheEverything: true,
                cacheTtl: 31536000 // 1 year
            }
        });

        const response = await fetch(imageRequest);

        if (response.ok || response.status === 304) {
             // Create a new response to modify headers if necessary (e.g., adding CORS)
             let newResponse = new Response(response.body, response);
             newResponse.headers.set('Cache-Control', 'public, max-age=31536000');
             newResponse.headers.set('Access-Control-Allow-Origin', '*');
             return newResponse;
        }
        
        // If resizing fails (e.g., origin error), return the error response from the origin
        return response;
    }
};
