import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const HELMETS_DIR = path.join(__dirname, '..', 'data', 'helmets');
const REPORT_DIR = path.join(__dirname, '..', '.catalog-build');
const REPORT_FILE = path.join(REPORT_DIR, 'asin-collision-report.json');

export function auditIdentifierCollisions() {
  if (!fs.existsSync(HELMETS_DIR)) {
    throw new Error(`Helmets directory not found: ${HELMETS_DIR}`);
  }

  const files = fs.readdirSync(HELMETS_DIR).filter(f => f.endsWith('.json'));
  console.log(`🔍 Auditing identifiers across ${files.length} helmets...`);

  const asinMap = new Map(); // asin -> [ { id, title, brand, file } ]
  const eanMap = new Map();  // ean -> [ { id, title, brand, file } ]

  for (const f of files) {
    const filePath = path.join(HELMETS_DIR, f);
    try {
      const data = JSON.parse(fs.readFileSync(filePath, 'utf-8'));
      const id = data.id || path.basename(f, '.json');
      const title = data.title || id;
      const brand = data.brand || 'Unknown';
      const identifiers = data.identifiers || {};

      // Extract ASIN (check both flat and nested)
      let asin = null;
      if (identifiers.amazon && typeof identifiers.amazon === 'object') {
        asin = identifiers.amazon.us?.asin || identifiers.amazon.us || null;
      }
      if (!asin) {
        asin = identifiers.asin || data.amazon_asin || data.asin || null;
      }

      if (asin && typeof asin === 'string' && asin.trim()) {
        const cleanAsin = asin.trim().toUpperCase();
        if (!asinMap.has(cleanAsin)) asinMap.set(cleanAsin, []);
        asinMap.get(cleanAsin).push({ id, title, brand, file: f });
      }

      // Extract EAN / GTIN
      const ean = identifiers.ean || identifiers.gtin || null;
      if (ean && typeof ean === 'string' && ean.trim()) {
        const cleanEan = ean.trim();
        if (!eanMap.has(cleanEan)) eanMap.set(cleanEan, []);
        eanMap.get(cleanEan).push({ id, title, brand, file: f });
      }
    } catch (err) {
      console.warn(`⚠️ Failed to parse ${f}: ${err.message}`);
    }
  }

  // Identify collisions
  const collidingAsins = [];
  let totalCollidingHelmetInstances = 0;

  for (const [asin, items] of asinMap.entries()) {
    // Check if ASIN spans multiple distinct helmet models
    const distinctIds = new Set(items.map(i => i.id));
    if (distinctIds.size > 1) {
      totalCollidingHelmetInstances += items.length;
      collidingAsins.push({
        asin,
        count: items.length,
        severity: items.length > 50 ? 'CRITICAL' : items.length > 10 ? 'HIGH' : 'MEDIUM',
        brands: [...new Set(items.map(i => i.brand))],
        sample_helmets: items.slice(0, 5).map(i => ({ id: i.id, title: i.title })),
        all_ids: items.map(i => i.id)
      });
    }
  }

  // Sort by count descending
  collidingAsins.sort((a, b) => b.count - a.count);

  // Identify EAN collisions
  const collidingEans = [];
  for (const [ean, items] of eanMap.entries()) {
    const distinctIds = new Set(items.map(i => i.id));
    if (distinctIds.size > 1) {
      collidingEans.push({
        ean,
        count: items.length,
        brands: [...new Set(items.map(i => i.brand))],
        all_ids: items.map(i => i.id)
      });
    }
  }
  collidingEans.sort((a, b) => b.count - a.count);

  const report = {
    generated_at: new Date().toISOString(),
    total_helmets_scanned: files.length,
    total_unique_asins: asinMap.size,
    total_unique_eans: eanMap.size,
    colliding_asin_keys: collidingAsins.length,
    colliding_helmet_instances: totalCollidingHelmetInstances,
    colliding_ean_keys: collidingEans.length,
    quarantined_asins: collidingAsins.map(c => c.asin),
    top_collisions: collidingAsins.slice(0, 15),
    top_ean_collisions: collidingEans.slice(0, 10)
  };

  if (!fs.existsSync(REPORT_DIR)) {
    fs.mkdirSync(REPORT_DIR, { recursive: true });
  }
  fs.writeFileSync(REPORT_FILE, JSON.stringify(report, null, 2), 'utf-8');

  console.log(`\n📊 IDENTIFIER COLLISION AUDIT REPORT:`);
  console.log(`   - Total Helmets:          ${files.length}`);
  console.log(`   - Total Unique ASINs:     ${asinMap.size}`);
  console.log(`   - Colliding ASIN Keys:    ${collidingAsins.length}`);
  console.log(`   - Quarantined Instances:  ${totalCollidingHelmetInstances}`);
  console.log(`   - Colliding EAN Keys:     ${collidingEans.length}`);
  console.log(`   - Report written to:      ${REPORT_FILE}`);

  if (collidingAsins.length > 0) {
    console.log(`\n🚨 TOP CRITICAL ASIN COLLISIONS QUARANTINED:`);
    for (const c of collidingAsins.slice(0, 5)) {
      console.log(`   → ASIN ${c.asin}: shared by ${c.count} helmets across ${c.brands.join(', ')}`);
    }
  }

  return report;
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  auditIdentifierCollisions();
}
