/**
 * Serverless Reviews API Worker for Helmetsan
 * Backed by Cloudflare D1 and secured with Cloudflare Turnstile,
 * HTML-entity sanitization (XSS defense), Edge Cache, and rate limiting.
 */

// In-memory rate limiting map for edge burst protection (5 submissions / 10 min)
const submissionRateMap = new Map();
const RATE_LIMIT_WINDOW_MS = 10 * 60 * 1000;
const MAX_SUBMISSIONS_PER_WINDOW = 5;

/**
 * Escapes HTML entities to prevent XSS payloads in user reviews.
 */
function sanitizeText(str) {
    if (typeof str !== 'string') return '';
    return str
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#039;')
        .trim();
}

/**
 * Checks in-memory sliding window rate limit for review submissions.
 */
function checkRateLimit(ip) {
    const now = Date.now();
    const records = submissionRateMap.get(ip) || [];
    const recent = records.filter(timestamp => now - timestamp < RATE_LIMIT_WINDOW_MS);

    if (recent.length >= MAX_SUBMISSIONS_PER_WINDOW) {
        return false;
    }

    recent.push(now);
    submissionRateMap.set(ip, recent);

    // Garbage collect rate map occasionally
    if (submissionRateMap.size > 2000) {
        for (const [key, timestamps] of submissionRateMap.entries()) {
            if (timestamps.every(t => now - t >= RATE_LIMIT_WINDOW_MS)) {
                submissionRateMap.delete(key);
            }
        }
    }
    return true;
}

export default {
    async fetch(request, env, ctx) {
        // Handle CORS preflight requests
        if (request.method === "OPTIONS") {
            return handleCORS(request);
        }

        const url = new URL(request.url);

        // GET /api/reviews?product_id=123
        if (request.method === "GET" && url.pathname === "/api/reviews") {
            return getReviews(request, env, ctx);
        }

        // POST /api/reviews
        if (request.method === "POST" && url.pathname === "/api/reviews") {
            return submitReview(request, env, ctx);
        }

        // POST /api/reviews/vote
        if (request.method === "POST" && url.pathname === "/api/reviews/vote") {
            return voteReview(request, env, ctx);
        }

        return new Response("Not Found", { status: 404 });
    }
};

async function getReviews(request, env, ctx) {
    const url = new URL(request.url);
    const productId = url.searchParams.get('product_id');
    const limit = Math.min(Math.max(parseInt(url.searchParams.get('limit') || '10', 10), 1), 50);
    const offset = Math.max(parseInt(url.searchParams.get('offset') || '0', 10), 0);
    const sort = url.searchParams.get('sort') || 'newest';

    if (!productId) {
        return createJSONResponse({ error: "Missing product_id parameter" }, 400);
    }

    if (!env.DB) {
        return createJSONResponse({ error: "D1 database not configured" }, 503);
    }

    // 1. Edge Cache lookup using caches.default
    const cache = caches.default;
    const cacheKey = new Request(url.toString(), { method: 'GET' });
    const cachedResponse = await cache.match(cacheKey);
    if (cachedResponse) {
        const responseWithHeader = new Response(cachedResponse.body, cachedResponse);
        responseWithHeader.headers.set('X-Edge-Cache', 'HIT');
        return responseWithHeader;
    }

    try {
        let orderField = "created_at";
        if (sort === "helpful") {
            orderField = "helpful_votes";
        }

        // Fetch reviews
        const { results } = await env.DB.prepare(
            `SELECT id, product_id, user_id, author_name, content, rating, pros, cons, country_code, helpful_votes, unhelpful_votes, created_at
             FROM reviews 
             WHERE product_id = ? AND status = 'approved' 
             ORDER BY ${orderField} DESC 
             LIMIT ? OFFSET ?`
        ).bind(productId, limit, offset).all();

        // Fetch aggregates
        const stats = await env.DB.prepare(
            "SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews WHERE product_id = ? AND status = 'approved'"
        ).bind(productId).first();

        const distribution = await env.DB.prepare(
            "SELECT rating, COUNT(*) as count FROM reviews WHERE product_id = ? AND status = 'approved' GROUP BY rating"
        ).bind(productId).all();

        const ratingCounts = { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 };
        if (distribution.results) {
            for (const row of distribution.results) {
                if (row.rating >= 1 && row.rating <= 5) {
                    ratingCounts[row.rating] = row.count;
                }
            }
        }

        const avgRating = stats ? parseFloat(stats.avg_rating || '0') : 0;
        const totalCount = stats ? parseInt(stats.review_count || '0', 10) : 0;

        const payload = {
            success: true,
            reviews: results || [],
            total_count: totalCount,
            total_pages: Math.ceil(totalCount / limit),
            aggregates: {
                avg_rating: Math.round(avgRating * 10) / 10,
                review_count: totalCount,
                rating_counts: ratingCounts
            }
        };

        const response = new Response(JSON.stringify(payload), {
            status: 200,
            headers: {
                "Content-Type": "application/json",
                "Access-Control-Allow-Origin": "*",
                "Cache-Control": "public, max-age=300, stale-while-revalidate=600",
                "X-Edge-Cache": "MISS"
            }
        });

        // Store in edge cache asynchronously
        if (ctx && ctx.waitUntil) {
            ctx.waitUntil(cache.put(cacheKey, response.clone()));
        }

        return response;
    } catch (e) {
        return createJSONResponse({ error: e.message }, 500);
    }
}

async function submitReview(request, env, ctx) {
    if (!env.DB) {
        return createJSONResponse({ error: "D1 database not configured" }, 503);
    }

    try {
        const url = new URL(request.url);
        const data = await request.json();
        const ip = request.headers.get("CF-Connecting-IP") || "0.0.0.0";
        
        // 1. Authenticate or verify Turnstile & rate limit
        const authHeader = request.headers.get("Authorization");
        const webhookSecret = env.WORDPRESS_WEBHOOK_SECRET || "";
        const isWPAuthorized = webhookSecret !== "" && authHeader === `Bearer ${webhookSecret}`;

        if (!isWPAuthorized) {
            // Apply rate limit on public submissions
            if (!checkRateLimit(ip)) {
                return createJSONResponse({ error: "Too many review submissions. Please wait a few minutes." }, 429);
            }

            const token = data.turnstile_token;
            if (!token) {
                return createJSONResponse({ error: "Missing Turnstile verification token" }, 400);
            }
            const isValid = await verifyTurnstile(token, ip, env.TURNSTILE_SECRET_KEY);
            if (!isValid) {
                return createJSONResponse({ error: "Failed spam verification. Are you a bot?" }, 403);
            }
        }

        // 2. Validate and sanitize review data (XSS defense)
        const productId = parseInt(data.product_id, 10);
        const rating = parseInt(data.rating, 10);
        const authorName = sanitizeText(data.author_name).slice(0, 100);
        const authorEmail = data.author_email ? String(data.author_email).trim().slice(0, 150) : null;
        const content = sanitizeText(data.content).slice(0, 5000);
        const countryCode = data.country_code ? sanitizeText(data.country_code).toUpperCase().slice(0, 3) : null;
        
        if (!productId || !authorName || !content || !rating) {
            return createJSONResponse({ error: "Missing required review fields" }, 400);
        }

        if (rating < 1 || rating > 5) {
            return createJSONResponse({ error: "Rating must be between 1 and 5" }, 400);
        }

        // Sanitize pros and cons arrays
        let prosJson = null;
        if (Array.isArray(data.pros)) {
            const cleanPros = data.pros.map(p => sanitizeText(p).slice(0, 200)).filter(Boolean);
            prosJson = cleanPros.length ? JSON.stringify(cleanPros) : null;
        }

        let consJson = null;
        if (Array.isArray(data.cons)) {
            const cleanCons = data.cons.map(c => sanitizeText(c).slice(0, 200)).filter(Boolean);
            consJson = cleanCons.length ? JSON.stringify(cleanCons) : null;
        }

        // 3. Insert into D1
        const result = await env.DB.prepare(
            `INSERT INTO reviews (product_id, user_id, author_name, author_email, content, rating, pros, cons, country_code, status) 
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'approved')`
        ).bind(
            productId, 
            data.user_id || 0, 
            authorName, 
            authorEmail, 
            content, 
            rating, 
            prosJson, 
            consJson, 
            countryCode
        ).run();

        if (result.success) {
            const reviewId = result.meta.last_row_id;

            // Fetch updated aggregates
            const stats = await env.DB.prepare(
                "SELECT AVG(rating) as avg_rating, COUNT(*) as review_count FROM reviews WHERE product_id = ? AND status = 'approved'"
            ).bind(productId).first();

            const distribution = await env.DB.prepare(
                "SELECT rating, COUNT(*) as count FROM reviews WHERE product_id = ? AND status = 'approved' GROUP BY rating"
            ).bind(productId).all();

            const ratingCounts = { 1: 0, 2: 0, 3: 0, 4: 0, 5: 0 };
            if (distribution.results) {
                for (const row of distribution.results) {
                    if (row.rating >= 1 && row.rating <= 5) {
                        ratingCounts[row.rating] = row.count;
                    }
                }
            }

            const avgRating = stats ? parseFloat(stats.avg_rating || '0') : 0;
            const totalCount = stats ? parseInt(stats.review_count || '0', 10) : 0;

            // Invalidate cached reviews for this product
            const cache = caches.default;
            const purgeKeys = [
                new Request(`${url.origin}/api/reviews?product_id=${productId}`, { method: 'GET' }),
                new Request(`${url.origin}/api/reviews?product_id=${productId}&sort=newest`, { method: 'GET' }),
                new Request(`${url.origin}/api/reviews?product_id=${productId}&sort=helpful`, { method: 'GET' })
            ];
            for (const key of purgeKeys) {
                if (ctx && ctx.waitUntil) {
                    ctx.waitUntil(cache.delete(key));
                } else {
                    cache.delete(key).catch(() => {});
                }
            }

            return createJSONResponse({ 
                success: true, 
                message: "Review submitted successfully!", 
                review_id: reviewId,
                aggregates: {
                    avg_rating: Math.round(avgRating * 10) / 10,
                    review_count: totalCount,
                    rating_counts: ratingCounts
                }
            }, 201);
        } else {
            throw new Error("Database insertion failed");
        }
        
    } catch (e) {
        return createJSONResponse({ error: e.message }, 500);
    }
}

async function voteReview(request, env, ctx) {
    if (!env.DB) {
        return createJSONResponse({ error: "D1 database not configured" }, 503);
    }

    try {
        const data = await request.json();
        const reviewId = parseInt(data.review_id, 10);
        const voteType = String(data.vote_type || '').toLowerCase();
        const userId = parseInt(data.user_id || '0', 10);
        const ip = request.headers.get("CF-Connecting-IP") || "0.0.0.0";

        if (!reviewId || !['helpful', 'unhelpful'].includes(voteType)) {
            return createJSONResponse({ error: "Invalid review ID or vote type" }, 400);
        }

        // Check WP authorization or enforce unique IP vote
        const authHeader = request.headers.get("Authorization");
        const webhookSecret = env.WORDPRESS_WEBHOOK_SECRET || "";
        const isWPAuthorized = webhookSecret !== "" && authHeader === `Bearer ${webhookSecret}`;

        if (!isWPAuthorized) {
            const existing = await env.DB.prepare(
                "SELECT id FROM review_votes WHERE review_id = ? AND ip_address = ?"
            ).bind(reviewId, ip).first();

            if (existing) {
                return createJSONResponse({ error: "Already voted" }, 400);
            }
        }

        // Insert vote
        await env.DB.prepare(
            "INSERT INTO review_votes (review_id, ip_address, user_id, vote_type) VALUES (?, ?, ?, ?)"
        ).bind(reviewId, ip, userId, voteType).run();

        // Update vote counts in reviews table
        const column = voteType === 'helpful' ? 'helpful_votes' : 'unhelpful_votes';
        await env.DB.prepare(
            `UPDATE reviews SET ${column} = ${column} + 1 WHERE id = ?`
        ).bind(reviewId).run();

        // Fetch updated counts and review for product_id
        const review = await env.DB.prepare(
            "SELECT product_id, helpful_votes, unhelpful_votes FROM reviews WHERE id = ?"
        ).bind(reviewId).first();

        // Invalidate edge cache for this product
        if (review && review.product_id) {
            const urlObj = new URL(request.url);
            const cache = caches.default;
            const purgeKeys = [
                new Request(`${urlObj.origin}/api/reviews?product_id=${review.product_id}`, { method: 'GET' }),
                new Request(`${urlObj.origin}/api/reviews?product_id=${review.product_id}&sort=helpful`, { method: 'GET' })
            ];
            for (const key of purgeKeys) {
                if (ctx && ctx.waitUntil) {
                    ctx.waitUntil(cache.delete(key));
                } else {
                    cache.delete(key).catch(() => {});
                }
            }
        }

        return createJSONResponse({
            success: true,
            helpful_votes: review ? parseInt(review.helpful_votes || '0', 10) : 0,
            unhelpful_votes: review ? parseInt(review.unhelpful_votes || '0', 10) : 0
        });
    } catch (e) {
        return createJSONResponse({ error: e.message }, 500);
    }
}

/**
 * Validates the token against the Cloudflare Turnstile API.
 */
async function verifyTurnstile(token, ip, secretKey) {
    if (!secretKey) {
        console.warn("Turnstile Secret Key not configured in Worker environment. Bypassing check for dev.");
        return true; 
    }

    try {
        const formData = new FormData();
        formData.append("secret", secretKey);
        formData.append("response", token);
        formData.append("remoteip", ip);

        const result = await fetch("https://challenges.cloudflare.com/turnstile/v0/siteverify", {
            body: formData,
            method: "POST",
        });

        const outcome = await result.json();
        return outcome.success === true;
    } catch (err) {
        console.error("Turnstile verification exception:", err);
        return false;
    }
}

function handleCORS(request) {
    return new Response(null, {
        headers: {
            "Access-Control-Allow-Origin": "*",
            "Access-Control-Allow-Methods": "GET, POST, OPTIONS",
            "Access-Control-Allow-Headers": "Content-Type, Authorization, X-WP-Nonce",
            "Access-Control-Max-Age": "86400",
        }
    });
}

function createJSONResponse(data, status = 200) {
    return new Response(JSON.stringify(data), {
        status: status,
        headers: {
            "Content-Type": "application/json",
            "Access-Control-Allow-Origin": "*",
            "Access-Control-Allow-Headers": "Content-Type, Authorization, X-WP-Nonce",
        }
    });
}
