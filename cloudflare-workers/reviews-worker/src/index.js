/**
 * Serverless Reviews API Worker for Helmetsan
 * Backed by Cloudflare D1 and secured with Cloudflare Turnstile.
 */

export default {
    async fetch(request, env, ctx) {
        // Handle CORS preflight requests
        if (request.method === "OPTIONS") {
            return handleCORS(request);
        }

        const url = new URL(request.url);

        // GET /api/reviews?product_id=123
        if (request.method === "GET" && url.pathname === "/api/reviews") {
            return getReviews(request, env);
        }

        // POST /api/reviews
        if (request.method === "POST" && url.pathname === "/api/reviews") {
            return submitReview(request, env);
        }

        return new Response("Not Found", { status: 404 });
    }
};

async function getReviews(request, env) {
    const url = new URL(request.url);
    const productId = url.searchParams.get('product_id');

    if (!productId) {
        return createJSONResponse({ error: "Missing product_id parameter" }, 400);
    }

    try {
        const { results } = await env.DB.prepare(
            "SELECT id, author_name, content, rating, created_at FROM reviews WHERE product_id = ? AND status = 'approved' ORDER BY created_at DESC"
        ).bind(productId).all();

        return createJSONResponse({ success: true, reviews: results });
    } catch (e) {
        return createJSONResponse({ error: e.message }, 500);
    }
}

async function submitReview(request, env) {
    try {
        const data = await request.json();
        
        // 1. Verify Cloudflare Turnstile token to stop spam bots
        const ip = request.headers.get("CF-Connecting-IP");
        const token = data.turnstile_token;
        
        if (!token) {
            return createJSONResponse({ error: "Missing Turnstile verification token" }, 400);
        }
        
        const isValid = await verifyTurnstile(token, ip, env.TURNSTILE_SECRET_KEY);
        if (!isValid) {
            return createJSONResponse({ error: "Failed spam verification. Are you a bot?" }, 403);
        }

        // 2. Validate review data
        const { product_id, author_name, content, rating } = data;
        
        if (!product_id || !author_name || !content || !rating) {
            return createJSONResponse({ error: "Missing required review fields" }, 400);
        }

        if (rating < 1 || rating > 5) {
            return createJSONResponse({ error: "Rating must be between 1 and 5" }, 400);
        }

        // 3. Insert into D1
        // (Status defaults to 'approved' per schema, but could easily be flagged as 'pending' for manual review)
        const result = await env.DB.prepare(
            "INSERT INTO reviews (product_id, author_name, content, rating) VALUES (?, ?, ?, ?)"
        ).bind(product_id, author_name, content, rating).run();

        if (result.success) {
            return createJSONResponse({ success: true, message: "Review submitted successfully!" }, 201);
        } else {
            throw new Error("Database insertion failed");
        }
        
    } catch (e) {
        return createJSONResponse({ error: e.message }, 500);
    }
}

/**
 * Validates the token against the Cloudflare Turnstile API.
 */
async function verifyTurnstile(token, ip, secretKey) {
    // Secret Key is injected via `npx wrangler secret put TURNSTILE_SECRET_KEY`
    if (!secretKey) {
        console.warn("Turnstile Secret Key not configured in Worker environment. Bypassing check for dev.");
        return true; 
    }

    const formData = new FormData();
    formData.append("secret", secretKey);
    formData.append("response", token);
    formData.append("remoteip", ip);

    const result = await fetch("https://challenges.cloudflare.com/turnstile/v0/siteverify", {
        body: formData,
        method: "POST",
    });

    const outcome = await result.json();
    return outcome.success;
}

function handleCORS(request) {
    return new Response(null, {
        headers: {
            "Access-Control-Allow-Origin": "*", // Or lock down to your specific WP domain
            "Access-Control-Allow-Methods": "GET, POST, OPTIONS",
            "Access-Control-Allow-Headers": "Content-Type",
        }
    });
}

function createJSONResponse(data, status = 200) {
    return new Response(JSON.stringify(data), {
        status: status,
        headers: {
            "Content-Type": "application/json",
            "Access-Control-Allow-Origin": "*" // CORS header
        }
    });
}
