/**
 * Comprehensive Automated Audit & Test Suite for All 6 Helmetsan Cloudflare Workers
 * Tests logic, edge security, rate limiting, sanitization, hashing, and formatting.
 */

const assert = require('assert');

async function runTests() {
    console.log('======================================================');
    console.log('   HELMETSAN CLOUDFLARE WORKERS DEEP AUDIT SUITE      ');
    console.log('======================================================\n');

    let totalTests = 0;
    let passedTests = 0;

    function test(name, fn) {
        totalTests++;
        try {
            const res = fn();
            if (res && typeof res.then === 'function') {
                return res.then(() => {
                    passedTests++;
                    console.log(`  ✓ [PASS] ${name}`);
                }).catch(err => {
                    console.error(`  ✗ [FAIL] ${name}:`, err.message);
                });
            } else {
                passedTests++;
                console.log(`  ✓ [PASS] ${name}`);
            }
        } catch (err) {
            console.error(`  ✗ [FAIL] ${name}:`, err.message);
        }
    }

    // ==========================================
    // 1. ANALYTICS WORKER AUDIT
    // ==========================================
    console.log('\n--- 1. ANALYTICS WORKER ---');
    const analyticsWorker = (await import('../cloudflare-workers/analytics-worker/src/index.js')).default;

    await test('Analytics: OPTIONS preflight returns CORS headers', async () => {
        const req = new Request('https://helmetsan.com/api/analytics', { method: 'OPTIONS' });
        const res = await analyticsWorker.fetch(req, {}, {});
        assert.strictEqual(res.headers.get('Access-Control-Allow-Origin'), '*');
        assert.ok(res.headers.get('Access-Control-Allow-Methods').includes('POST'));
    });

    await test('Analytics: GET request returns 405 Method Not Allowed', async () => {
        const req = new Request('https://helmetsan.com/api/analytics', { method: 'GET' });
        const res = await analyticsWorker.fetch(req, {}, {});
        assert.strictEqual(res.status, 405);
    });

    await test('Analytics: Missing event_name rejected with 400', async () => {
        const req = new Request('https://helmetsan.com/api/analytics', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ page_url: 'https://helmetsan.com/' })
        });
        const res = await analyticsWorker.fetch(req, {}, {});
        assert.strictEqual(res.status, 400);
        const data = await res.json();
        assert.strictEqual(data.error, 'Missing event_name');
    });

    await test('Analytics: Rogue event name rejected by whitelist', async () => {
        const req = new Request('https://helmetsan.com/api/analytics', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ event_name: 'drop_tables_exploit' })
        });
        const res = await analyticsWorker.fetch(req, {}, {});
        assert.strictEqual(res.status, 400);
        const data = await res.json();
        assert.ok(data.error.includes('Invalid event_name'));
    });

    await test('Analytics: Valid event executes with D1 mock and GDPR IP hash', async () => {
        let insertedValues = null;
        const mockDb = {
            prepare: (sql) => ({
                bind: (...args) => {
                    insertedValues = args;
                    return { run: async () => ({ success: true }) };
                }
            })
        };

        const req = new Request('https://helmetsan.com/api/analytics', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'CF-Connecting-IP': '198.51.100.42'
            },
            body: JSON.stringify({
                event_name: 'click',
                page_url: 'https://helmetsan.com/helmets/',
                meta: { helmet_id: 42 }
            })
        });

        const res = await analyticsWorker.fetch(req, { DB: mockDb }, {});
        assert.strictEqual(res.status, 200);
        assert.ok(insertedValues !== null);
        assert.strictEqual(insertedValues[0], 'click');
        // Verify IP was hashed and NOT stored in raw form
        assert.notStrictEqual(insertedValues[5], '198.51.100.42');
        assert.strictEqual(insertedValues[5].length, 32); // 32-char hex hash
    });

    // ==========================================
    // 2. PRICE WORKER AUDIT
    // ==========================================
    console.log('\n--- 2. PRICE WORKER ---');
    const priceWorker = (await import('../cloudflare-workers/price-worker/src/index.js')).default;

    await test('Price: Non-price routes pass through unchanged', async () => {
        const req = new Request('https://helmetsan.com/wp-json/wp/v2/posts', { method: 'GET' });
        // In local node without origin, fetch will fail or pass, but we verify routing check
        let fetchCalled = false;
        global.fetch = async (r) => { fetchCalled = true; return new Response('mock'); };
        await priceWorker.fetch(req, {}, {});
        assert.ok(fetchCalled);
    });

    await test('Price: Purge endpoint without authorization rejected with 401', async () => {
        const req = new Request('https://helmetsan.com/wp-json/hs/v1/prices/purge', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ helmet_id: 123 })
        });
        const res = await priceWorker.fetch(req, { WORDPRESS_WEBHOOK_SECRET: 'super-secret' }, {});
        assert.strictEqual(res.status, 401);
    });

    await test('Price: Authorized purge endpoint purges keys by prefix', async () => {
        const deletedKeys = [];
        const mockKv = {
            list: async ({ prefix }) => {
                if (prefix === '/wp-json/hs/v1/prices/123') {
                    return { keys: [{ name: '/wp-json/hs/v1/prices/123?country=DE' }, { name: '/wp-json/hs/v1/prices/123/history' }] };
                }
                return { keys: [] };
            },
            delete: async (name) => { deletedKeys.push(name); }
        };

        const req = new Request('https://helmetsan.com/wp-json/hs/v1/prices/purge', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer test-secret-token'
            },
            body: JSON.stringify({ helmet_id: 123 })
        });

        const res = await priceWorker.fetch(req, {
            WORDPRESS_WEBHOOK_SECRET: 'test-secret-token',
            PRICE_CACHE: mockKv
        }, {});

        assert.strictEqual(res.status, 200);
        const data = await res.json();
        assert.strictEqual(data.purged_keys, 2);
        assert.ok(deletedKeys.includes('/wp-json/hs/v1/prices/123?country=DE'));
    });

    // ==========================================
    // 3. REVIEWS WORKER AUDIT
    // ==========================================
    console.log('\n--- 3. REVIEWS WORKER ---');
    const reviewsWorker = (await import('../cloudflare-workers/reviews-worker/src/index.js')).default;

    await test('Reviews: OPTIONS preflight returns CORS headers', async () => {
        const req = new Request('https://helmetsan.com/api/reviews', { method: 'OPTIONS' });
        const res = await reviewsWorker.fetch(req, {}, {});
        assert.strictEqual(res.headers.get('Access-Control-Allow-Origin'), '*');
        assert.ok(res.headers.get('Access-Control-Allow-Methods').includes('GET'));
    });

    await test('Reviews: XSS payloads sanitized in review submission', async () => {
        let insertedData = null;
        const mockDb = {
            prepare: (sql) => ({
                bind: (...args) => {
                    if (sql.includes('INSERT')) {
                        insertedData = args;
                    }
                    return {
                        run: async () => ({ success: true, meta: { last_row_id: 99 } }),
                        first: async () => ({ avg_rating: 5.0, review_count: 1 }),
                        all: async () => ({ results: [{ rating: 5, count: 1 }] })
                    };
                }
            })
        };

        const req = new Request('https://helmetsan.com/api/reviews', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer test-secret'
            },
            body: JSON.stringify({
                product_id: 101,
                author_name: '<script>alert("hack")</script>John',
                content: 'Great helmet! <img src=x onerror=alert(1)>',
                rating: 5,
                pros: ['<b>Lightweight</b>', 'Safe'],
                cons: ['<script>steal()</script>Pricey'],
                country_code: 'DE'
            })
        });

        // Mock caches.default for node test environment
        global.caches = {
            default: {
                match: async () => null,
                put: async () => {},
                delete: async () => {}
            }
        };

        const res = await reviewsWorker.fetch(req, {
            DB: mockDb,
            WORDPRESS_WEBHOOK_SECRET: 'test-secret'
        }, { waitUntil: () => {} });

        assert.strictEqual(res.status, 201);
        assert.ok(insertedData !== null);
        // Verify XSS sanitization
        assert.strictEqual(insertedData[2], '&lt;script&gt;alert(&quot;hack&quot;)&lt;/script&gt;John');
        assert.ok(!insertedData[4].includes('<img'));
        assert.ok(insertedData[6].includes('&lt;b&gt;Lightweight&lt;/b&gt;'));
        assert.ok(insertedData[7].includes('&lt;script&gt;steal()&lt;/script&gt;Pricey'));
    });

    await test('Reviews: POST /api/reviews/vote increments vote and uses ctx.waitUntil without ReferenceError', async () => {
        let voteInserted = null;
        let updateExecuted = false;
        let cacheDeleted = false;

        const mockDb = {
            prepare: (sql) => {
                if (sql.includes('INSERT INTO review_votes')) {
                    return {
                        bind: (...args) => {
                            voteInserted = args;
                            return { run: async () => ({ success: true }) };
                        }
                    };
                }
                if (sql.includes('UPDATE reviews SET')) {
                    return {
                        bind: (...args) => {
                            updateExecuted = true;
                            return { run: async () => ({ success: true }) };
                        }
                    };
                }
                if (sql.includes('SELECT product_id')) {
                    return {
                        bind: () => ({
                            first: async () => ({ product_id: 101, helpful_votes: 5, unhelpful_votes: 1 })
                        })
                    };
                }
                return {
                    bind: () => ({
                        first: async () => null,
                        all: async () => ({ results: [] })
                    })
                };
            }
        };

        global.caches = {
            default: {
                match: async () => null,
                put: async () => {},
                delete: async () => { cacheDeleted = true; }
            }
        };

        let waitUntilCalled = false;
        const mockCtx = {
            waitUntil: (promise) => {
                waitUntilCalled = true;
                if (promise && typeof promise.then === 'function') {
                    promise.then(() => {});
                }
            }
        };

        const req = new Request('https://helmetsan.com/api/reviews/vote', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer test-secret'
            },
            body: JSON.stringify({
                review_id: 42,
                vote_type: 'helpful',
                user_id: 12
            })
        });

        const res = await reviewsWorker.fetch(req, {
            DB: mockDb,
            WORDPRESS_WEBHOOK_SECRET: 'test-secret'
        }, mockCtx);

        assert.strictEqual(res.status, 200);
        const data = await res.json();
        assert.strictEqual(data.success, true);
        assert.strictEqual(data.helpful_votes, 5);
        assert.ok(voteInserted !== null);
        assert.strictEqual(voteInserted[0], 42);
        assert.strictEqual(voteInserted[3], 'helpful');
        assert.ok(updateExecuted, 'Review count update must be executed');
        assert.ok(waitUntilCalled, 'ctx.waitUntil must be called for cache purge');
    });

    // ==========================================
    // 4. INGESTION WORKER AUDIT
    // ==========================================
    console.log('\n--- 4. INGESTION WORKER ---');
    const ingestionWorker = (await import('../cloudflare-workers/ingestion-worker/src/index.js')).default;

    await test('Ingestion: GET /health returns healthy JSON', async () => {
        const req = new Request('https://ingestion.helmetsan.workers.dev/health', { method: 'GET' });
        const res = await ingestionWorker.fetch(req, { ASSETS_BUCKET: {} });
        assert.strictEqual(res.status, 200);
        const data = await res.json();
        assert.strictEqual(data.status, 'healthy');
        assert.strictEqual(data.r2_bound, true);
    });

    await test('Ingestion: Queue message processing fetches media, uploads to R2, and signs callback', async () => {
        let r2Uploaded = null;
        let wpCallbackHeaders = null;

        const mockR2 = {
            put: async (key, buffer, opts) => {
                r2Uploaded = { key, size: buffer.byteLength, contentType: opts.httpMetadata.contentType };
            }
        };

        // Mock global fetch for source image and WP callback
        global.fetch = async (url, opts) => {
            if (url === 'https://images.example.com/shoei.webp') {
                return new Response(new Uint8Array([1, 2, 3, 4]), {
                    headers: { 'Content-Type': 'image/webp' }
                });
            }
            if (url.includes('/ingestion/callback')) {
                wpCallbackHeaders = opts.headers;
                return new Response(JSON.stringify({ ok: true }), { status: 200 });
            }
            return new Response('Not found', { status: 404 });
        };

        let acked = false;
        const mockBatch = {
            messages: [{
                id: 'msg-abc-123',
                body: {
                    helmet_id: 55,
                    source_url: 'https://images.example.com/shoei.webp',
                    helmet_title: 'Shoei X-SPR Pro'
                },
                ack: () => { acked = true; },
                retry: () => {}
            }]
        };

        await ingestionWorker.queue(mockBatch, {
            ASSETS_BUCKET: mockR2,
            R2_PUBLIC_URL: 'https://media.helmetsan.com',
            WORDPRESS_REST_URL: 'https://helmetsan.com/wp-json',
            WORDPRESS_WEBHOOK_SECRET: 'test-webhook-secret'
        });

        assert.ok(acked, 'Message should be acknowledged on success');
        assert.ok(r2Uploaded !== null, 'Media should be uploaded to R2');
        assert.ok(r2Uploaded.key.endsWith('.webp'), 'Extension should dynamically match .webp');
        assert.ok(wpCallbackHeaders !== null, 'Callback headers should be sent');
        assert.ok(wpCallbackHeaders['X-Helmetsan-Signature'], 'HMAC signature header must be present');
    });

    // ==========================================
    // 5. IMAGE RESIZING WORKER AUDIT
    // ==========================================
    console.log('\n--- 5. IMAGE RESIZING WORKER ---');
    const imageResizingWorker = (await import('../cloudflare-workers/image-resizing-worker/src/index.js')).default;

    await test('Image Resizing: Rejects untrusted external hostname with 403 (SSRF Shield)', async () => {
        const req = new Request('https://evil-attacker.com/image.jpg?width=400', { method: 'GET' });
        const res = await imageResizingWorker.fetch(req, {}, {});
        assert.strictEqual(res.status, 403);
    });

    await test('Image Resizing: Rejects internal cloud metadata hostname with 403', async () => {
        const req = new Request('https://169.254.169.254/latest/meta-data', { method: 'GET' });
        const res = await imageResizingWorker.fetch(req, {}, {});
        assert.strictEqual(res.status, 403);
    });

    await test('Image Resizing: Allows valid helmetsan domain and negotiates format', async () => {
        let passedImageOptions = null;
        global.fetch = async (req) => {
            passedImageOptions = req.cf ? req.cf.image : null;
            return new Response(new Uint8Array([1, 2]), {
                status: 200,
                headers: { 'Content-Type': 'image/avif' }
            });
        };

        const req = new Request('https://media.helmetsan.com/assets/shoei.jpg?width=9999&quality=10', {
            method: 'GET',
            headers: { 'Accept': 'image/avif,image/webp,*/*' }
        });

        const res = await imageResizingWorker.fetch(req, {}, {});
        assert.strictEqual(res.status, 200);
        assert.ok(passedImageOptions !== null);
        // Verify width was clamped from 9999 to max 2560
        assert.strictEqual(passedImageOptions.width, 2560);
        // Verify quality was clamped from 10 to min 30
        assert.strictEqual(passedImageOptions.quality, 30);
        // Verify format was auto-negotiated to avif from Accept header
        assert.strictEqual(passedImageOptions.format, 'avif');
        assert.strictEqual(res.headers.get('Vary'), 'Accept');
        assert.strictEqual(res.headers.get('X-Content-Type-Options'), 'nosniff');
    });

    // ==========================================
    // 6. EDGE ASSEMBLY WORKER AUDIT
    // ==========================================
    console.log('\n--- 6. EDGE ASSEMBLY WORKER ---');
    const edgeWorker = (await import('../cloudflare-workers/edge-assembly-worker/src/index.js')).default;

    await test('Edge Assembly: Bypasses static assets and API requests', async () => {
        let fetched = false;
        global.fetch = async () => { fetched = true; return new Response('static'); };
        
        await edgeWorker.fetch(new Request('https://helmetsan.com/wp-content/style.css'), {}, {});
        assert.ok(fetched);

        fetched = false;
        await edgeWorker.fetch(new Request('https://helmetsan.com/wp-json/helmetsan/v1/helmets'), {}, {});
        assert.ok(fetched);
    });

    await test('Reviews: Sliding-window rate limit triggers 429 on spam burst', async () => {
        const mockDb = {
            prepare: () => ({
                bind: () => ({
                    run: async () => ({ success: true, meta: { last_row_id: 1 } }),
                    first: async () => ({ avg_rating: 5.0, review_count: 1 }),
                    all: async () => ({ results: [{ rating: 5, count: 1 }] })
                })
            })
        };

        const ip = '203.0.113.99';
        let lastStatus = 201;

        // Perform 6 rapid submissions from same IP without WP auth
        for (let i = 0; i < 6; i++) {
            const req = new Request('https://helmetsan.com/api/reviews', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'CF-Connecting-IP': ip
                },
                body: JSON.stringify({
                    product_id: 200,
                    author_name: `SpamBot${i}`,
                    content: `Test content ${i}`,
                    rating: 5,
                    turnstile_token: 'valid-test-token'
                })
            });
            const res = await reviewsWorker.fetch(req, {
                DB: mockDb,
                TURNSTILE_SECRET_KEY: '' // bypassed in dev mode
            }, { waitUntil: () => {} });
            lastStatus = res.status;
        }

        assert.strictEqual(lastStatus, 429, '6th submission must be rate limited with 429');
    });

    await test('Price: URL query normalization strips tracking params and preserves marketplace', async () => {
        let requestedCacheKey = null;
        const mockKv = {
            get: async (key) => { requestedCacheKey = key; return null; },
            put: async () => {}
        };

        const req = new Request(
            'https://helmetsan.com/wp-json/hs/v1/prices/77/history?utm_source=google&fbclid=xyz&marketplace=amazon&country=DE&gclid=123&days=60',
            { method: 'GET' }
        );

        global.fetch = async () => new Response('{}', {
            status: 200,
            headers: { 'Content-Type': 'application/json' }
        });

        await priceWorker.fetch(req, { PRICE_CACHE: mockKv }, { waitUntil: () => {} });

        assert.ok(requestedCacheKey !== null);
        // Path should be canonical
        assert.ok(requestedCacheKey.startsWith('/wp-json/hs/v1/prices/77/history'));
        // Tracking params must be stripped
        assert.ok(!requestedCacheKey.includes('utm_source'));
        assert.ok(!requestedCacheKey.includes('fbclid'));
        assert.ok(!requestedCacheKey.includes('gclid'));
        // Essential params must be preserved and sorted
        assert.ok(requestedCacheKey.includes('country=DE'));
        assert.ok(requestedCacheKey.includes('days=60'));
        assert.ok(requestedCacheKey.includes('marketplace=amazon'));
    });

    // ==========================================
    // 7. DEDICATED EDGE CACHE WORKER AUDIT
    // ==========================================
    console.log('\n--- 7. DEDICATED EDGE CACHE WORKER ---');
    const edgeCacheWorker = (await import('../cloudflare-workers/edge-cache-worker/src/index.js')).default;

    await test('Edge Cache: Bypasses for logged-in WordPress user cookie', async () => {
        const req = new Request('https://helmetsan.com/nl/helmets/', {
            method: 'GET',
            headers: { 'Cookie': 'wordpress_logged_in_hash=admin; helmetsan_geo=NL' }
        });
        global.fetch = async () => new Response('<h1>Logged in page</h1>', {
            status: 200,
            headers: { 'Content-Type': 'text/html' }
        });

        const res = await edgeCacheWorker.fetch(req, {}, {});
        assert.strictEqual(res.headers.get('CF-Edge-Cache'), 'BYPASS');
        assert.strictEqual(res.headers.get('CF-Edge-Cache-Reason'), 'LOGGED_IN_USER');
    });

    await test('Edge Cache: Bypasses for /wp-admin/ paths', async () => {
        const req = new Request('https://helmetsan.com/wp-admin/edit.php', { method: 'GET' });
        global.fetch = async () => new Response('admin content', { status: 200 });

        const res = await edgeCacheWorker.fetch(req, {}, {});
        assert.strictEqual(res.headers.get('CF-Edge-Cache'), 'BYPASS');
        assert.strictEqual(res.headers.get('CF-Edge-Cache-Reason'), 'ADMIN_PATH');
    });

    await test('Edge Cache: Canonical cache key strips tracking params and geo-partitions', async () => {
        let savedKey = null;
        let savedResponse = null;

        global.caches = {
            default: {
                match: async () => null,
                put: async (key, resp) => {
                    savedKey = key.url;
                    savedResponse = resp;
                }
            }
        };

        global.fetch = async () => new Response('<h1>Catalog Page</h1>', {
            status: 200,
            headers: { 'Content-Type': 'text/html; charset=UTF-8' }
        });

        const req = new Request(
            'https://helmetsan.com/helmets/?utm_source=meta&fbclid=999&paged=3&gclid=abc',
            {
                method: 'GET',
                headers: { 'CF-IPCountry': 'DE' }
            }
        );

        const res = await edgeCacheWorker.fetch(req, {}, { waitUntil: (p) => p });
        assert.strictEqual(res.status, 200);
        assert.strictEqual(res.headers.get('CF-Edge-Cache'), 'MISS');

        assert.ok(savedKey !== null);
        assert.ok(savedKey.includes('paged=3'));
        assert.ok(!savedKey.includes('utm_source'));
        assert.ok(!savedKey.includes('fbclid'));
        assert.ok(!savedKey.includes('gclid'));
        assert.ok(savedKey.includes('_cf_geo=DE'));
    });

    await test('Edge Cache: Returns CF-Edge-Cache: HIT on cache match', async () => {
        global.caches = {
            default: {
                match: async () => new Response('<h1>Cached HTML</h1>', {
                    status: 200,
                    headers: {
                        'Content-Type': 'text/html; charset=UTF-8',
                        'X-Edge-Cache-Time': new Date().toISOString()
                    }
                }),
                put: async () => {}
            }
        };

        const req = new Request('https://helmetsan.com/nl/helmets/', { method: 'GET' });
        const res = await edgeCacheWorker.fetch(req, {}, {});
        assert.strictEqual(res.status, 200);
        assert.strictEqual(res.headers.get('CF-Edge-Cache'), 'HIT');
        assert.ok(res.headers.has('Age'));
    });

    await test('Edge Cache: Purge API requires authorization and deletes target URL', async () => {
        const deletedUrls = [];
        global.caches = {
            default: {
                delete: async (req) => { deletedUrls.push(req.url); return true; }
            }
        };

        // Unauthorized call
        const unauthReq = new Request('https://helmetsan.com/api/edge-cache/purge', {
            method: 'POST',
            body: JSON.stringify({ url: 'https://helmetsan.com/nl/helmets/' })
        });
        const unauthRes = await edgeCacheWorker.fetch(unauthReq, { PURGE_SECRET: 'secret-123' }, {});
        assert.strictEqual(unauthRes.status, 401);

        // Authorized call
        const authReq = new Request('https://helmetsan.com/api/edge-cache/purge', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Authorization': 'Bearer secret-123'
            },
            body: JSON.stringify({ url: 'https://helmetsan.com/nl/helmets/' })
        });
        const authRes = await edgeCacheWorker.fetch(authReq, { PURGE_SECRET: 'secret-123' }, {});
        assert.strictEqual(authRes.status, 200);
        const data = await authRes.json();
        assert.strictEqual(data.purged_count, 1);
        assert.ok(deletedUrls.includes('https://helmetsan.com/nl/helmets/'));
        assert.ok(deletedUrls.some(u => u.includes('_cf_geo=FR')));
    });

    console.log('\n======================================================');
    console.log(`RESULTS: ${passedTests}/${totalTests} TESTS PASSED`);
    console.log('======================================================\n');
}

runTests().catch(err => {
    console.error('Fatal test error:', err);
    process.exit(1);
});
