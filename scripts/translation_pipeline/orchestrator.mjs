import fs from 'node:fs';
import path from 'node:path';
import { execSync } from 'node:child_process';
import { fileURLToPath } from 'node:url';
import { 
  TARGET_LANGUAGES, 
  ACCESSORY_LANGUAGES, 
  SPECIALIZED_PAGE_LANGUAGES,
  RATE_LIMITS 
} from './config.mjs';
import { translateWithFleet } from './ai_clients.mjs';
import { 
  BRAND_SYSTEM_PROMPT, 
  ACCESSORY_SYSTEM_PROMPT, 
  BLOG_SYSTEM_PROMPT, 
  SAFETY_STANDARD_SYSTEM_PROMPT,
  PAGE_SYSTEM_PROMPT 
} from './prompts.mjs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

// Detect environment
const IS_PRODUCTION_SERVER = fs.existsSync('/var/www/helmetsan.com/public');
const WP_PATH = IS_PRODUCTION_SERVER ? '/var/www/helmetsan.com/public' : null;
const SSH_HOST = 'root@31.70.136.154';
const SSH_PASS = '57iYZ4PM0G0j';

// State path
const stateDir = IS_PRODUCTION_SERVER 
  ? '/var/www/helmetsan.com/data' 
  : path.resolve(__dirname, '../../data');
if (!fs.existsSync(stateDir)) fs.mkdirSync(stateDir, { recursive: true });
const stateFilePath = path.join(stateDir, 'translation_checkpoint.json');

function loadState() {
  if (fs.existsSync(stateFilePath)) {
    try {
      return JSON.parse(fs.readFileSync(stateFilePath, 'utf8'));
    } catch {
      return { completed: {} };
    }
  }
  return { completed: {} };
}

function saveState(state) {
  fs.writeFileSync(stateFilePath, JSON.stringify(state, null, 2));
}

function runWpCli(command) {
  if (IS_PRODUCTION_SERVER) {
    const fullCmd = `wp ${command} --path=${WP_PATH} --allow-root`;
    return execSync(fullCmd, { encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 });
  } else {
    const remoteCmd = `wp ${command} --path=/var/www/helmetsan.com/public --allow-root`;
    const sshCmd = `sshpass -p '${SSH_PASS}' ssh -o StrictHostKeyChecking=no ${SSH_HOST} "${remoteCmd}"`;
    return execSync(sshCmd, { encoding: 'utf8', maxBuffer: 10 * 1024 * 1024 });
  }
}

function syncToWordpress(payload) {
  const tmpFileName = `trans_${Date.now()}_${Math.random().toString(36).substring(7)}.json`;
  const localTmp = path.join('/tmp', tmpFileName);
  fs.writeFileSync(localTmp, JSON.stringify(payload));

  let remoteTmp = localTmp;
  if (!IS_PRODUCTION_SERVER) {
    remoteTmp = `/tmp/${tmpFileName}`;
    execSync(`sshpass -p '${SSH_PASS}' scp -o StrictHostKeyChecking=no ${localTmp} ${SSH_HOST}:${remoteTmp}`);
  }

  // Ensure db_sync.php is on remote server
  const dbSyncLocal = path.resolve(__dirname, 'db_sync.php');
  const dbSyncRemote = '/var/www/helmetsan.com/tmp/db_sync.php';
  if (!IS_PRODUCTION_SERVER) {
    execSync(`sshpass -p '${SSH_PASS}' scp -o StrictHostKeyChecking=no ${dbSyncLocal} ${SSH_HOST}:${dbSyncRemote}`);
  }

  const scriptPath = IS_PRODUCTION_SERVER ? dbSyncLocal : dbSyncRemote;
  const out = runWpCli(`eval-file ${scriptPath} ${remoteTmp}`);

  // Cleanup tmp files
  try { fs.unlinkSync(localTmp); } catch {}
  if (!IS_PRODUCTION_SERVER) {
    try { execSync(`sshpass -p '${SSH_PASS}' ssh -o StrictHostKeyChecking=no ${SSH_HOST} "rm -f ${remoteTmp}"`); } catch {}
  }

  return JSON.parse(out);
}

function getExistingTranslations(sourceId) {
  try {
    const out = runWpCli(`eval 'echo json_encode(pll_get_post_translations(${sourceId}));'`);
    return JSON.parse(out) || {};
  } catch {
    return {};
  }
}

// -------------------------------------------------------------
// STAGE 1: BRANDS TRANSLATION WORKER
// -------------------------------------------------------------
async function translateBrands(limit = null, state) {
  console.log('\n=============================================================');
  console.log('   STARTING STAGE 1A: MOTORCYCLE BRANDS TRANSLATION FLEET    ');
  console.log('=============================================================');

  // Query master brands from WP strictly in English
  const rawList = runWpCli(`post list --post_type=brand --lang=en --post_status=publish --fields=ID,post_name,post_title --format=json`);
  let brands = JSON.parse(rawList);
  if (limit) brands = brands.slice(0, limit);

  console.log(`Discovered ${brands.length} master English brands to process.`);

  for (let i = 0; i < brands.length; i++) {
    const b = brands[i];
    const sourceId = b.ID;
    console.log(`\n[Brand ${i + 1}/${brands.length}] Processing ${b.post_title} (ID: ${sourceId}, slug: ${b.post_name})`);

    const existingTrans = getExistingTranslations(sourceId);

    // Fetch existing meta
    const rawMeta = runWpCli(`post meta list ${sourceId} --format=json`);
    const metaArr = JSON.parse(rawMeta);
    const meta = {};
    for (const m of metaArr) meta[m.meta_key] = m.meta_value;

    const brandPayload = {
      brand: b.post_title,
      tagline: meta.brand_tagline || meta.brand_motto || `${b.post_title} motorcycle helmets.`,
      story: meta.brand_story || meta._yoast_wpseo_metadesc || `Official motorcycle helmet collections engineered by ${b.post_title}.`,
      manufacturing_ethos: meta.brand_manufacturing_ethos || `High performance safety standards, aerodynamic shell engineering and rider ergonomics.`
    };

    const syncPayload = {
      source_post_id: sourceId,
      translations: {}
    };

    for (const lang of TARGET_LANGUAGES) {
      if (existingTrans[lang.code]) {
        console.log(`  - [${lang.code}] Already in WordPress DB (ID: ${existingTrans[lang.code]}). Skipping.`);
        continue;
      }

      const stateKey = `brand_${sourceId}_${lang.code}`;
      if (state.completed[stateKey]) {
        console.log(`  - [${lang.code}] Already completed in state. Skipping.`);
        continue;
      }

      process.stdout.write(`  - [${lang.code}] Translating with AI Fleet... `);
      try {
        const transRes = await translateWithFleet(
          BRAND_SYSTEM_PROMPT,
          brandPayload,
          lang.name,
          ['groq', 'cerebras', 'mistral', 'cloudflare']
        );

        const d = transRes.data;
        syncPayload.translations[lang.code] = {
          post_title: b.post_title, // Brand names remain canonical
          post_content: d.brand_story || '',
          post_excerpt: d.brand_tagline || '',
          meta: {
            brand_tagline: d.brand_tagline || '',
            brand_story: d.brand_story || '',
            brand_manufacturing_ethos: d.brand_manufacturing_ethos || '',
            _yoast_wpseo_title: d.seo_title || `${b.post_title} Helmets - ${lang.native}`,
            _yoast_wpseo_metadesc: d.seo_description || d.brand_tagline || ''
          }
        };

        console.log(`SUCCESS via ${transRes.provider} (${transRes.latency_ms}ms)`);
        state.completed[stateKey] = {
          time: new Date().toISOString(),
          provider: transRes.provider,
          latency: transRes.latency_ms
        };
      } catch (err) {
        console.log(`FAILED: ${err.message}`);
      }
    }

    if (Object.keys(syncPayload.translations).length > 0) {
      process.stdout.write(`  -> Syncing ${Object.keys(syncPayload.translations).length} translations into WordPress DB... `);
      try {
        syncToWordpress(syncPayload);
        console.log(`SUCCESS (Polylang cluster linked!)`);
        saveState(state);
      } catch (err) {
        console.log(`DB SYNC ERROR: ${err.message}`);
      }
    }
  }
}

// -------------------------------------------------------------
// STAGE 2: ACCESSORIES TRANSLATION WORKER
// -------------------------------------------------------------
async function translateAccessories(limit = null, state) {
  console.log('\n=============================================================');
  console.log('   STARTING STAGE 1B: ACCESSORIES TRANSLATION FLEET         ');
  console.log('=============================================================');

  // Query master accessories strictly in English
  const rawList = runWpCli(`post list --post_type=accessory --lang=en --post_status=publish --fields=ID,post_name,post_title --format=json`);
  let accs = JSON.parse(rawList);
  if (limit) accs = accs.slice(0, limit);

  console.log(`Discovered ${accs.length} curated English accessories to process.`);

  for (let i = 0; i < accs.length; i++) {
    const a = accs[i];
    const sourceId = a.ID;
    console.log(`\n[Accessory ${i + 1}/${accs.length}] Processing ${a.post_title} (ID: ${sourceId})`);

    const existingTrans = getExistingTranslations(sourceId);

    const rawPost = runWpCli(`post get ${sourceId} --format=json`);
    const postData = JSON.parse(rawPost);

    const payload = {
      title: a.post_title,
      content: postData.post_content || '',
      excerpt: postData.post_excerpt || ''
    };

    const syncPayload = {
      source_post_id: sourceId,
      translations: {}
    };

    for (const lang of ACCESSORY_LANGUAGES) {
      if (existingTrans[lang.code]) {
        console.log(`  - [${lang.code}] Already in WordPress DB (ID: ${existingTrans[lang.code]}). Skipping.`);
        continue;
      }

      const stateKey = `accessory_${sourceId}_${lang.code}`;
      if (state.completed[stateKey]) {
        console.log(`  - [${lang.code}] Already completed in state. Skipping.`);
        continue;
      }

      process.stdout.write(`  - [${lang.code}] Translating with AI Fleet... `);
      try {
        const transRes = await translateWithFleet(
          ACCESSORY_SYSTEM_PROMPT,
          payload,
          lang.name,
          ['groq', 'cerebras', 'mistral', 'cloudflare']
        );

        const d = transRes.data;
        syncPayload.translations[lang.code] = {
          post_title: d.post_title || a.post_title,
          post_content: d.post_content || payload.content,
          post_excerpt: d.post_excerpt || payload.excerpt,
          meta: {
            _yoast_wpseo_title: d.seo_title || `${d.post_title || a.post_title} - ${lang.native}`,
            _yoast_wpseo_metadesc: d.seo_description || d.post_excerpt || ''
          }
        };

        console.log(`SUCCESS via ${transRes.provider} (${transRes.latency_ms}ms)`);
        state.completed[stateKey] = {
          time: new Date().toISOString(),
          provider: transRes.provider,
          latency: transRes.latency_ms
        };
      } catch (err) {
        console.log(`FAILED: ${err.message}`);
      }
    }

    if (Object.keys(syncPayload.translations).length > 0) {
      process.stdout.write(`  -> Syncing ${Object.keys(syncPayload.translations).length} translations into WordPress DB... `);
      try {
        syncToWordpress(syncPayload);
        console.log(`SUCCESS (Polylang cluster linked!)`);
        saveState(state);
      } catch (err) {
        console.log(`DB SYNC ERROR: ${err.message}`);
      }
    }
  }
}

// -------------------------------------------------------------
// STAGE 3: BLOG POSTS TRANSLATION WORKER
// -------------------------------------------------------------
async function translateBlogPosts(limit = null, state) {
  console.log('\n=============================================================');
  console.log('   STARTING STAGE 1C: BLOG POSTS TRANSLATION FLEET          ');
  console.log('=============================================================');

  const rawList = runWpCli(`post list --post_type=post --lang=en --post_status=publish --fields=ID,post_name,post_title --format=json`);
  let posts = JSON.parse(rawList);
  if (limit) posts = posts.slice(0, limit);

  console.log(`Discovered ${posts.length} cornerstone English blog posts to process.`);

  for (let i = 0; i < posts.length; i++) {
    const p = posts[i];
    const sourceId = p.ID;
    console.log(`\n[Blog Post ${i + 1}/${posts.length}] Processing ${p.post_title} (ID: ${sourceId})`);

    const existingTrans = getExistingTranslations(sourceId);

    const rawPost = runWpCli(`post get ${sourceId} --format=json`);
    const postData = JSON.parse(rawPost);

    const payload = {
      title: p.post_title,
      content: postData.post_content || '',
      excerpt: postData.post_excerpt || ''
    };

    const syncPayload = {
      source_post_id: sourceId,
      translations: {}
    };

    for (const lang of TARGET_LANGUAGES) {
      if (existingTrans[lang.code]) {
        console.log(`  - [${lang.code}] Already in WordPress DB (ID: ${existingTrans[lang.code]}). Skipping.`);
        continue;
      }

      const stateKey = `blog_${sourceId}_${lang.code}`;
      if (state.completed[stateKey]) {
        console.log(`  - [${lang.code}] Already completed in state. Skipping.`);
        continue;
      }

      process.stdout.write(`  - [${lang.code}] Translating with AI Fleet... `);
      try {
        const transRes = await translateWithFleet(
          BLOG_SYSTEM_PROMPT,
          payload,
          lang.name,
          ['cerebras', 'gemini', 'groq', 'mistral']
        );

        const d = transRes.data;
        syncPayload.translations[lang.code] = {
          post_title: d.post_title || p.post_title,
          post_content: d.post_content || payload.content,
          post_excerpt: d.post_excerpt || payload.excerpt,
          meta: {
            _yoast_wpseo_title: d.seo_title || `${d.post_title || p.post_title} - ${lang.native}`,
            _yoast_wpseo_metadesc: d.seo_description || d.post_excerpt || ''
          }
        };

        console.log(`SUCCESS via ${transRes.provider} (${transRes.latency_ms}ms)`);
        state.completed[stateKey] = {
          time: new Date().toISOString(),
          provider: transRes.provider,
          latency: transRes.latency_ms
        };
      } catch (err) {
        console.log(`FAILED: ${err.message}`);
      }
    }

    if (Object.keys(syncPayload.translations).length > 0) {
      process.stdout.write(`  -> Syncing ${Object.keys(syncPayload.translations).length} translations into WordPress DB... `);
      try {
        syncToWordpress(syncPayload);
        console.log(`SUCCESS (Polylang cluster linked!)`);
        saveState(state);
      } catch (err) {
        console.log(`DB SYNC ERROR: ${err.message}`);
      }
    }
  }
}

// -------------------------------------------------------------
// STAGE 4: SAFETY STANDARDS TRANSLATION WORKER
// -------------------------------------------------------------
async function translateSafetyStandards(limit = null, state) {
  console.log('\n=============================================================');
  console.log('   STARTING STAGE 1D: SAFETY STANDARDS TRANSLATION FLEET     ');
  console.log('=============================================================');

  const rawList = runWpCli(`post list --post_type=safety_standard --lang=en --post_status=publish --fields=ID,post_name,post_title --format=json`);
  let items = JSON.parse(rawList);
  if (limit) items = items.slice(0, limit);

  console.log(`Discovered ${items.length} English safety standards to process.`);

  for (let i = 0; i < items.length; i++) {
    const s = items[i];
    const sourceId = s.ID;
    console.log(`\n[Standard ${i + 1}/${items.length}] Processing ${s.post_title} (ID: ${sourceId})`);

    const existingTrans = getExistingTranslations(sourceId);

    const rawPost = runWpCli(`post get ${sourceId} --format=json`);
    const postData = JSON.parse(rawPost);

    const payload = {
      title: s.post_title,
      content: postData.post_content || '',
      excerpt: postData.post_excerpt || ''
    };

    const syncPayload = {
      source_post_id: sourceId,
      translations: {}
    };

    for (const lang of TARGET_LANGUAGES) {
      if (existingTrans[lang.code]) {
        console.log(`  - [${lang.code}] Already in WordPress DB (ID: ${existingTrans[lang.code]}). Skipping.`);
        continue;
      }

      const stateKey = `safety_${sourceId}_${lang.code}`;
      if (state.completed[stateKey]) {
        console.log(`  - [${lang.code}] Already completed in state. Skipping.`);
        continue;
      }

      process.stdout.write(`  - [${lang.code}] Translating with AI Fleet... `);
      try {
        const transRes = await translateWithFleet(
          SAFETY_STANDARD_SYSTEM_PROMPT,
          payload,
          lang.name,
          ['groq', 'cerebras', 'gemini', 'mistral', 'cloudflare']
        );

        const d = transRes.data;
        syncPayload.translations[lang.code] = {
          post_title: d.post_title || s.post_title,
          post_content: d.post_content || payload.content,
          post_excerpt: d.post_excerpt || payload.excerpt,
          meta: {
            _yoast_wpseo_title: d.seo_title || `${d.post_title || s.post_title} - ${lang.native}`,
            _yoast_wpseo_metadesc: d.seo_description || d.post_excerpt || ''
          }
        };

        console.log(`SUCCESS via ${transRes.provider} (${transRes.latency_ms}ms)`);
        state.completed[stateKey] = {
          time: new Date().toISOString(),
          provider: transRes.provider,
          latency: transRes.latency_ms
        };
      } catch (err) {
        console.log(`FAILED: ${err.message}`);
      }
    }

    if (Object.keys(syncPayload.translations).length > 0) {
      process.stdout.write(`  -> Syncing ${Object.keys(syncPayload.translations).length} translations into WordPress DB... `);
      try {
        syncToWordpress(syncPayload);
        console.log(`SUCCESS (Polylang cluster linked!)`);
        saveState(state);
      } catch (err) {
        console.log(`DB SYNC ERROR: ${err.message}`);
      }
    }
  }
}

// -------------------------------------------------------------
// STAGE 5: SPECIALIZED LEGAL PAGES TRANSLATION WORKER
// -------------------------------------------------------------
async function translateSpecializedPages(limit = null, state) {
  console.log('\n=============================================================');
  console.log('   STARTING STAGE 1E: SPECIALIZED LEGAL PAGES FLEET         ');
  console.log('=============================================================');

  const targetSlugs = [
    'ai-policy',
    'certification-documents',
    'geo-pricing',
    'helmet-comparison',
    'helmet-types',
    'intellectual-property',
    'dmca-policy'
  ];

  for (const slug of targetSlugs) {
    const rawPost = runWpCli(`post list --post_type=page --name=${slug} --fields=ID,post_name,post_title --format=json`);
    const pages = JSON.parse(rawPost);
    if (!pages || pages.length === 0) continue;

    const sourceId = pages[0].ID;
    console.log(`\n[Specialized Page] Processing ${pages[0].post_title} (${slug}, ID: ${sourceId})`);

    const fullPost = JSON.parse(runWpCli(`post get ${sourceId} --format=json`));
    const payload = {
      title: fullPost.post_title,
      content: fullPost.post_content || ''
    };

    const syncPayload = {
      source_post_id: sourceId,
      translations: {}
    };

    for (const lang of SPECIALIZED_PAGE_LANGUAGES) {
      const stateKey = `page_${slug}_${lang.code}`;
      if (state.completed[stateKey]) {
        console.log(`  - [${lang.code}] Already translated. Skipping.`);
        continue;
      }

      process.stdout.write(`  - [${lang.code}] Translating with AI Fleet... `);
      try {
        const transRes = await translateWithFleet(
          PAGE_SYSTEM_PROMPT,
          payload,
          lang.name,
          ['groq', 'cerebras', 'gemini', 'mistral', 'cloudflare']
        );

        const d = transRes.data;
        syncPayload.translations[lang.code] = {
          post_title: d.post_title || fullPost.post_title,
          post_content: d.post_content || payload.content,
          meta: {
            _yoast_wpseo_title: d.seo_title || `${d.post_title || fullPost.post_title} - ${lang.native}`,
            _yoast_wpseo_metadesc: d.seo_description || ''
          }
        };

        console.log(`SUCCESS via ${transRes.provider} (${transRes.latency_ms}ms)`);
        state.completed[stateKey] = {
          time: new Date().toISOString(),
          provider: transRes.provider,
          latency: transRes.latency_ms
        };
      } catch (err) {
        console.log(`FAILED: ${err.message}`);
      }
    }

    if (Object.keys(syncPayload.translations).length > 0) {
      process.stdout.write(`  -> Syncing ${Object.keys(syncPayload.translations).length} translations into WordPress DB... `);
      try {
        syncToWordpress(syncPayload);
        console.log(`SUCCESS (Polylang cluster linked!)`);
        saveState(state);
      } catch (err) {
        console.log(`DB SYNC ERROR: ${err.message}`);
      }
    }
  }
}

// -------------------------------------------------------------
// MAIN ENTRY POINT
// -------------------------------------------------------------
async function main() {
  const args = process.argv.slice(2);
  const stageArg = args.find(a => a.startsWith('--stage='))?.split('=')[1] || 'stage1';
  const limitArg = args.find(a => a.startsWith('--limit='))?.split('=')[1];
  const limit = limitArg ? parseInt(limitArg, 10) : null;

  console.log(`\n>>> HELMETSAN MULTILINGUAL TRANSLATION DAEMON INITIALIZED <<<`);
  console.log(`Execution Mode: ${IS_PRODUCTION_SERVER ? 'Direct Server (Local MySQL)' : 'Remote SSH to Production'}`);
  console.log(`Target Stage:   ${stageArg}`);
  if (limit) console.log(`Batch Limit:    ${limit} items`);

  const state = loadState();

  if (stageArg === 'brands' || stageArg === 'stage1' || stageArg === 'all') {
    await translateBrands(limit, state);
  }
  if (stageArg === 'accessories' || stageArg === 'stage1' || stageArg === 'all') {
    await translateAccessories(limit, state);
  }
  if (stageArg === 'blog' || stageArg === 'stage1' || stageArg === 'all') {
    await translateBlogPosts(limit, state);
  }
  if (stageArg === 'safety' || stageArg === 'stage1' || stageArg === 'all') {
    await translateSafetyStandards(limit, state);
  }
  if (stageArg === 'pages' || stageArg === 'stage1' || stageArg === 'all') {
    await translateSpecializedPages(limit, state);
  }

  console.log(`\n=============================================================`);
  console.log(`   EXECUTION RUN COMPLETED SUCCESSFULLY                      `);
  console.log(`=============================================================`);
}

main().catch(err => {
  console.error('\nFATAL ERROR in Translation Orchestrator:', err);
  process.exit(1);
});
