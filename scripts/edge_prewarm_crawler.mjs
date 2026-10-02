import { execSync } from 'child_process';

const HOST = "helmetsan.com";
const CONCURRENCY = 10;

function fetchTopTierUrls(limit = 500) {
  console.log(`📡 Fetching top ${limit} helmets from production database...`);
  const query = `
    SELECT p.ID, p.post_name, p.post_type, t.slug as lang
    FROM wp_posts p
    INNER JOIN wp_term_relationships tr ON p.ID = tr.object_id
    INNER JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'language'
    INNER JOIN wp_terms t ON tt.term_id = t.term_id
    WHERE p.post_type = 'helmet'
      AND p.post_status = 'publish'
    ORDER BY p.comment_count DESC, p.ID DESC
    LIMIT ${limit};
  `;
  const cmd = `ssh -o ControlMaster=no -o ControlPath=none -o StrictHostKeyChecking=no root@31.70.136.154 "cd /var/www/helmetsan.com/public && sudo -u www-data wp db query \\"${query.replace(/\n/g, ' ')}\\" --skip-column-names"`;
  const output = execSync(cmd, { encoding: 'utf8' }).trim();
  const lines = output.split('\n');

  const urls = [];
  const langs = ["", "de/", "zh/", "fr/", "es/", "it/", "pl/", "pt/", "nl/", "ja/"];
  for (const lang of langs) {
    urls.push(`https://${HOST}/${lang}`);
    urls.push(`https://${HOST}/${lang}helmets/`);
    urls.push(`https://${HOST}/${lang}accessories/`);
    urls.push(`https://${HOST}/${lang}motorcycles/`);
    urls.push(`https://${HOST}/${lang}brands/`);
    urls.push(`https://${HOST}/${lang}comparison/`);
  }

  for (const line of lines) {
    const parts = line.trim().split('\t');
    if (parts.length >= 4) {
      const slug = parts[1];
      const lang = parts[3];
      if (lang === "en") {
        urls.push(`https://${HOST}/helmets/${slug}/`);
      } else {
        urls.push(`https://${HOST}/${lang}/helmets/${slug}/`);
      }
    }
  }

  return [...new Set(urls)];
}

async function prewarmWorker(urls) {
  let completed = 0;
  let hits = 0;
  let primed = 0;
  let errors = 0;
  let totalMs = 0;
  const startAll = Date.now();

  async function processUrl(url) {
    const start = Date.now();
    try {
      const res = await fetch(url, {
        headers: {
          'User-Agent': 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36 (Helmetsan-Prewarm)',
          'Accept': 'text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
          'X-Helmetsan-Prewarm': '1'
        }
      });
      const ms = Date.now() - start;
      totalMs += ms;
      const cfCache = res.headers.get('cf-cache-status') || 'NONE';
      const fcgiCache = res.headers.get('x-fastcgi-cache') || 'NONE';
      if (cfCache === 'HIT' || fcgiCache === 'HIT') {
        hits++;
      } else {
        primed++;
      }
    } catch (err) {
      errors++;
    } finally {
      completed++;
      if (completed % 50 === 0 || completed === urls.length) {
        const avg = (totalMs / completed).toFixed(1);
        const pct = ((completed / urls.length) * 100).toFixed(1);
        console.log(`[${pct.padStart(5)}%] ${completed}/${urls.length} | Hits: ${hits} | Primed: ${primed} | Errors: ${errors} | Avg: ${avg}ms`);
      }
    }
  }

  // Pool with CONCURRENCY
  const queue = [...urls];
  const workers = Array.from({ length: CONCURRENCY }, async () => {
    while (queue.length > 0) {
      const url = queue.shift();
      if (url) {
        await processUrl(url);
        // Micro pause to protect origin
        await new Promise(r => setTimeout(r, 20));
      }
    }
  });

  await Promise.all(workers);
  const elapsed = ((Date.now() - startAll) / 1000).toFixed(1);
  console.log(`\n🎉 Pre-Warm Sweep Completed in ${elapsed}s!`);
  console.log(`   Total URLs: ${completed}`);
  console.log(`   Cache Hits: ${hits}`);
  console.log(`   Cache Primed: ${primed}`);
  console.log(`   Errors: ${errors}`);
}

async function main() {
  const limitArg = process.argv.find(a => a.startsWith('--limit='));
  const limit = limitArg ? parseInt(limitArg.split('=')[1], 10) : 500;
  const urls = fetchTopTierUrls(limit);
  console.log(`🚀 Commencing Edge Pre-Warm Sweep across ${urls.length} URLs with ${CONCURRENCY} workers...`);
  await prewarmWorker(urls);
}

main().catch(console.error);
