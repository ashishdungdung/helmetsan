export default {
    // The queue handler is invoked whenever messages arrive in this Worker's configured queue
    async queue(batch, env) {
        let messages = batch.messages;
        
        for (const message of messages) {
            try {
                console.log(`Processing message: ${message.id}`);
                const payload = message.body; 

                // Expected payload: { helmet_id: 123, source_url: "https://myntra...", helmet_title: "Steelbird Front" }
                if (!payload.source_url || !payload.helmet_id) {
                    console.error("Invalid payload missing source_url or helmet_id");
                    message.ack(); // Acknowledge to remove from queue
                    continue;
                }

                // 1. Fetch image from source_url
                const imageResponse = await fetch(payload.source_url);
                if (!imageResponse.ok) {
                    throw new Error(`Failed to fetch image from ${payload.source_url}: ${imageResponse.statusText}`);
                }
                const imageArrayBuffer = await imageResponse.arrayBuffer();

                // 2. Upload directly to Cloudflare R2
                const fileExt = '.jpg'; // Simplification for MVP
                const r2Key = `assets/${new Date().getFullYear()}/${String(new Date().getMonth() + 1).padStart(2, '0')}/${payload.helmet_title.replace(/[^a-z0-9]/gi, '-').toLowerCase()}-${message.id}${fileExt}`;
                
                await env.ASSETS_BUCKET.put(r2Key, imageArrayBuffer, {
                    httpMetadata: { contentType: imageResponse.headers.get('content-type') || 'image/jpeg' }
                });

                const r2Url = `${env.R2_PUBLIC_URL}/${r2Key}`;
                
                // 3. (Optional mock) AI Processing via Workers AI or fetch to OpenAI
                // For MVP, if we don't have OpenAI keys injected into the worker env, we fallback to 'standard'
                const photoType = 'standard';

                // 4. Callback to WordPress to finalize CPT creation
                const callbackUrl = `${env.WORDPRESS_REST_URL}/helmetsan/v1/ingestion/callback`;
                
                const callbackBody = {
                    helmet_id: payload.helmet_id,
                    source_url: payload.source_url,
                    r2_url: r2Url,
                    photo_type: photoType
                };

                const callbackResponse = await fetch(callbackUrl, {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Authorization': `Bearer ${env.WORDPRESS_WEBHOOK_SECRET}`
                    },
                    body: JSON.stringify(callbackBody)
                });

                if (!callbackResponse.ok) {
                    throw new Error(`WordPress callback failed: ${callbackResponse.statusText}`);
                }

                // Message processed successfully!
                console.log(`Successfully processed ${payload.source_url} into R2.`);
                message.ack();

            } catch (error) {
                console.error(`Error processing message ${message.id}:`, error);
                message.retry(); // Re-queue the message for later retry
            }
        }
    }
};
