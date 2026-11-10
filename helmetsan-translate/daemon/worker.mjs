import http from 'http';
import mysql from 'mysql2/promise';
import fs from 'fs';
import path from 'path';
import { translateWithFleet } from './ai_clients.mjs';
import { exec } from 'child_process';
import util from 'util';

const execPromise = util.promisify(exec);

// Database configuration dynamically falling back to production socket
const dbConfig = {
  socketPath: fs.existsSync('/run/mysqld/mysqld.sock') ? '/run/mysqld/mysqld.sock' : undefined,
  host: !fs.existsSync('/run/mysqld/mysqld.sock') ? (process.env.DB_HOST || '127.0.0.1') : undefined,
  user: process.env.DB_USER || 'wp_helmetsan_com',
  password: process.env.DB_PASSWORD || 'qvoYz3sFrmaHDvhEGR6CIJtD2yKrwQJW',
  database: process.env.DB_NAME || 'wp_helmetsan_com',
  charset: 'utf8mb4'
};

const TARGET_LANGS = [
  { code: 'de', name: 'German' },
  { code: 'fr', name: 'French' },
  { code: 'es', name: 'Spanish' },
  { code: 'it', name: 'Italian' },
  { code: 'nl', name: 'Dutch' },
  { code: 'pl', name: 'Polish' },
  { code: 'pt', name: 'Portuguese' },
  { code: 'ja', name: 'Japanese' },
  { code: 'zh', name: 'Chinese' }
];

let isProcessing = false;

async function getPool() {
  return mysql.createPool(dbConfig);
}

async function processQueue() {
  if (isProcessing) return;
  isProcessing = true;

  let pool = null;
  try {
    pool = await getPool();
    const [rows] = await pool.query(
      `SELECT * FROM wp_helmetsan_translate_queue WHERE status = 'pending' ORDER BY id ASC LIMIT 5`
    );

    if (rows.length === 0) {
      isProcessing = false;
      return;
    }

    for (const job of rows) {
      console.log(`[Worker] Starting Job ${job.id} for Post ID: ${job.post_id} (${job.post_type})`);
      await pool.query(
        `UPDATE wp_helmetsan_translate_queue SET status = 'processing', attempts = attempts + 1 WHERE id = ?`,
        [job.id]
      );

      try {
        const [posts] = await pool.query(`SELECT * FROM wp_posts WHERE ID = ?`, [job.post_id]);
        if (posts.length === 0) {
          await pool.query(
            `UPDATE wp_helmetsan_translate_queue SET status = 'failed', last_error = 'Post not found' WHERE id = ?`,
            [job.id]
          );
          continue;
        }

        const enPost = posts[0];
        const state = JSON.parse(job.languages_state || '{}');
        const translationsPayload = {};

        const payloadToTranslate = {
          title: enPost.post_title,
          content: enPost.post_content,
          excerpt: enPost.post_excerpt
        };

        const systemPrompt = `You are an elite motorcycle gear and technical engineering localization engine for Helmetsan.com. 
Preserve all technical codes (ECE 22.06, DOT, Snell, JIS), model names, measurements, and HTML formatting.
Preserve Gutenberg block comments <!-- wp:... --> untouched. Return valid JSON matching schema.`;

        for (const lang of TARGET_LANGS) {
          if (state[lang.code] === 'done') continue;

          console.log(`  -> [${lang.code}] Translating into ${lang.name}...`);
          try {
            const result = await translateWithFleet(
              systemPrompt,
              payloadToTranslate,
              lang.name,
              ['mercury', 'cerebras', 'groq', 'gemini', 'mistral', 'luna']
            );

            if (result.success && result.data) {
              translationsPayload[lang.code] = {
                title: result.data.title || enPost.post_title,
                content: result.data.content || enPost.post_content,
                excerpt: result.data.excerpt || enPost.post_excerpt
              };
              state[lang.code] = 'done';
              console.log(`     ✓ Success via ${result.provider} (${result.latency_ms}ms)`);

              // Record into log table
              await pool.query(
                `INSERT INTO wp_helmetsan_translate_log (queue_id, post_id, target_lang, provider, latency_ms, status) VALUES (?, ?, ?, ?, ?, 'success')`,
                [job.id, job.post_id, lang.code, result.provider, result.latency_ms]
              );
            }
          } catch (langErr) {
            console.error(`     ✗ Failed for [${lang.code}]:`, langErr.message);
            state[lang.code] = 'failed';
          }
        }

        // Write payload to temporary file and execute db_sync.php via WP-CLI
        if (Object.keys(translationsPayload).length > 0) {
          const tmpFile = `/tmp/hs_trans_${job.id}_${Date.now()}.json`;
          fs.writeFileSync(tmpFile, JSON.stringify({
            source_post_id: job.post_id,
            translations: translationsPayload
          }));

          const dbSyncScript = path.resolve(path.dirname(new URL(import.meta.url).pathname), 'db_sync.php');
          const wpCmd = `wp --path=/var/www/helmetsan.com/public eval-file ${dbSyncScript} ${tmpFile} --allow-root`;
          
          console.log(`  -> Syncing translations into WordPress DB via Polylang...`);
          const { stdout, stderr } = await execPromise(wpCmd);
          if (fs.existsSync(tmpFile)) fs.unlinkSync(tmpFile);
          console.log(`     ✓ WP Sync Result: ${stdout.trim()}`);
        }

        await pool.query(
          `UPDATE wp_helmetsan_translate_queue SET status = 'completed', languages_state = ? WHERE id = ?`,
          [JSON.stringify(state), job.id]
        );
        console.log(`[Worker] Job ${job.id} completed successfully.`);
      } catch (err) {
        console.error(`[Worker] Job ${job.id} failed:`, err.message);
        await pool.query(
          `UPDATE wp_helmetsan_translate_queue SET status = 'failed', last_error = ? WHERE id = ?`,
          [err.message, job.id]
        );
      }
    }
  } catch (err) {
    console.error('[Worker] Queue error:', err.message);
  } finally {
    isProcessing = false;
  }
}

// Loopback HTTP server for instant WordPress save_post notifications
const server = http.createServer((req, res) => {
  if (req.method === 'POST' && req.url === '/queue/notify') {
    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ status: 'acknowledged' }));
    setImmediate(processQueue);
  } else {
    res.writeHead(404);
    res.end();
  }
});

const PORT = 3850;
server.listen(PORT, '127.0.0.1', () => {
  console.log(`[Helmetsan Translate Daemon] Listening on http://127.0.0.1:${PORT}`);
  setInterval(processQueue, 15000); // 15s fallback poll
  setImmediate(processQueue); // run immediately on startup!
});
