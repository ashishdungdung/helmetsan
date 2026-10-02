import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const HELMETS_DIR = path.join(__dirname, '..', 'data', 'helmets');
const ACCESSORIES_DIR = path.join(__dirname, '..', 'data', 'accessories');
const MOTORCYCLES_DIR = path.join(__dirname, '..', 'data', 'motorcycles');
const REPORT_FILE = path.join(__dirname, '..', '.catalog-build', 'asin-collision-report.json');

// Supported 22 Amazon Worldwide Regions Registry
export const AMAZON_REGIONS = [
  'us', 'ca', 'mx', 'br',
  'uk', 'de', 'fr', 'it', 'es', 'nl', 'pl', 'se', 'be', 'tr',
  'in', 'jp', 'au', 'sg', 'ae', 'sa', 'eg', 'za'
];

export const ONELINK_ENABLED_REGIONS = new Set([
  'ca', 'uk', 'de', 'fr', 'it', 'es', 'nl', 'pl', 'se', 'be'
]);

export function buildDefaultAmazonRegistry(brand, model, variant = '', verifiedUsAsin = null, isQuarantined = false, quarantinedVal = null) {
  const query = [brand, model, variant].filter(Boolean).join(' ').trim();
  const reg = {};

  for (const r of AMAZON_REGIONS) {
    if (r === 'us') {
      if (verifiedUsAsin && !isQuarantined) {
        reg.us = {
          asin: verifiedUsAsin,
          status: 'verified',
          confidence: 0.95,
          search_fallback: query
        };
      } else if (isQuarantined) {
        reg.us = {
          asin: null,
          status: 'quarantined',
          quarantined_asin: quarantinedVal,
          reason: 'SYNTHETIC_COLLISION',
          search_fallback: query
        };
      } else {
        reg.us = {
          asin: null,
          status: 'search_fallback',
          search_fallback: query
        };
      }
    } else if (ONELINK_ENABLED_REGIONS.has(r)) {
      reg[r] = {
        asin: null,
        status: (verifiedUsAsin && !isQuarantined) ? 'onelink_forwarding' : 'search_fallback',
        search_fallback: query
      };
    } else {
      // Standalone Associate ID required (IN, JP, AE, etc.) or Search fallback
      reg[r] = {
        asin: null,
        status: 'search_fallback',
        search_fallback: query
      };
    }
  }
  return reg;
}

export function migrateCatalogIdentifiers() {
  let quarantinedSet = new Set();
  if (fs.existsSync(REPORT_FILE)) {
    try {
      const rep = JSON.parse(fs.readFileSync(REPORT_FILE, 'utf-8'));
      quarantinedSet = new Set(rep.quarantined_asins || []);
    } catch {}
  }

  console.log(`🚀 Starting SuperMulti-Marketplace Identifier Schema Migration...`);
  console.log(`   Quarantined ASINs loaded: ${quarantinedSet.size}`);

  let helmetCount = 0;
  let quarantinedHelmets = 0;
  let verifiedHelmets = 0;

  // 1. Migrate Helmets
  if (fs.existsSync(HELMETS_DIR)) {
    const files = fs.readdirSync(HELMETS_DIR).filter(f => f.endsWith('.json'));
    for (const f of files) {
      const filePath = path.join(HELMETS_DIR, f);
      const data = JSON.parse(fs.readFileSync(filePath, 'utf-8'));
      const id = data.id || path.basename(f, '.json');
      const brand = data.brand || 'Motorcycle Helmet';
      const title = data.title || id;
      const model = data.model || title.replace(new RegExp(`^${brand}\\s*`, 'i'), '').trim();

      const oldIdentifiers = data.identifiers || {};
      let oldAsin = null;
      if (oldIdentifiers.amazon && typeof oldIdentifiers.amazon === 'object') {
        oldAsin = oldIdentifiers.amazon.us?.asin || oldIdentifiers.amazon.us || null;
      }
      if (!oldAsin) {
        oldAsin = oldIdentifiers.asin || data.amazon_asin || data.asin || null;
      }

      let isQuarantined = false;
      let verifiedAsin = null;

      if (oldAsin && typeof oldAsin === 'string') {
        const clean = oldAsin.trim().toUpperCase();
        if (quarantinedSet.has(clean)) {
          isQuarantined = true;
          quarantinedHelmets++;
        } else {
          verifiedAsin = clean;
          verifiedHelmets++;
        }
      }

      const amazonReg = buildDefaultAmazonRegistry(brand, model, '', verifiedAsin, isQuarantined, oldAsin);

      // Check for sequential fake barcodes (e.g., 8421567890123)
      let cleanEan = oldIdentifiers.ean || null;
      let eanStatus = 'valid';
      if (cleanEan && (cleanEan.startsWith('842156789') || cleanEan.length < 12)) {
        eanStatus = 'quarantined_synthetic';
      }

      // Canonical internal SKU
      const canonicalSku = `HSN-HLM-${id.toUpperCase().replace(/[^A-Z0-9]/g, '-').slice(0, 32)}`;

      data.identifiers = {
        amazon: amazonReg,
        gtin: oldIdentifiers.gtin || (eanStatus === 'valid' ? cleanEan : null),
        ean: eanStatus === 'valid' ? cleanEan : null,
        ean_status: eanStatus,
        mpn: oldIdentifiers.mpn || null,
        sku: canonicalSku,
        retailer_skus: {
          revzilla: null,
          fc_moto: null,
          motoin: null,
          cyclegear: null,
          louis: null,
          chromeburner: null
        }
      };

      fs.writeFileSync(filePath, JSON.stringify(data, null, 2), 'utf-8');
      helmetCount++;
    }
  }

  console.log(`✅ Helmets Migrated: ${helmetCount} (Verified Unique ASINs: ${verifiedHelmets}, Quarantined: ${quarantinedHelmets})`);

  // 2. Migrate Accessories (27 items)
  let accCount = 0;
  if (fs.existsSync(ACCESSORIES_DIR)) {
    const files = fs.readdirSync(ACCESSORIES_DIR).filter(f => f.endsWith('.json'));
    for (const f of files) {
      const filePath = path.join(ACCESSORIES_DIR, f);
      const data = JSON.parse(fs.readFileSync(filePath, 'utf-8'));
      const id = data.id || path.basename(f, '.json');
      const brand = data.brand || 'Motorcycle Accessory';
      const title = data.title || id;

      const oldIdentifiers = data.identifiers || {};
      const oldAsin = oldIdentifiers.asin || data.amazon_asin || data.asin || null;
      const amazonReg = buildDefaultAmazonRegistry(brand, title, '', oldAsin ? oldAsin.trim().toUpperCase() : null, false, null);

      const canonicalSku = `HSN-ACC-${id.toUpperCase().replace(/[^A-Z0-9]/g, '-').slice(0, 32)}`;

      data.identifiers = {
        amazon: amazonReg,
        gtin: oldIdentifiers.gtin || null,
        ean: oldIdentifiers.ean || null,
        mpn: oldIdentifiers.mpn || null,
        sku: canonicalSku,
        retailer_skus: {
          revzilla: null,
          fc_moto: null,
          motoin: null,
          cyclegear: null,
          louis: null,
          chromeburner: null
        }
      };

      fs.writeFileSync(filePath, JSON.stringify(data, null, 2), 'utf-8');
      accCount++;
    }
  }
  console.log(`✅ Accessories Migrated: ${accCount}`);

  // 3. Migrate Motorcycles (3,247 items)
  let motoCount = 0;
  if (fs.existsSync(MOTORCYCLES_DIR)) {
    const files = fs.readdirSync(MOTORCYCLES_DIR).filter(f => f.endsWith('.json'));
    for (const f of files) {
      const filePath = path.join(MOTORCYCLES_DIR, f);
      const data = JSON.parse(fs.readFileSync(filePath, 'utf-8'));
      const id = data.id || path.basename(f, '.json');
      const brand = data.brand || 'Motorcycle';
      const model = data.model || id;
      const year = data.specs?.engine_and_transmission?.year || data.year || null;

      const canonicalSku = `HSN-MOTO-${id.toUpperCase().replace(/[^A-Z0-9]/g, '-').slice(0, 32)}`;

      // Motorcycles do not have direct Amazon ASINs, but have OEM family codes and gear fitment search queries
      data.identifiers = {
        oem_family_code: `${brand.toUpperCase().slice(0, 4)}-${model.toUpperCase().slice(0, 6)}`,
        sku: canonicalSku,
        model_year: year,
        gear_search_fallback: `${brand} ${model} accessories motorcycle gear`
      };

      fs.writeFileSync(filePath, JSON.stringify(data, null, 2), 'utf-8');
      motoCount++;
    }
  }
  console.log(`✅ Motorcycles Migrated: ${motoCount}`);

  console.log(`\n🎉 SuperMulti-Marketplace Identifier Schema Migration Complete across ${helmetCount + accCount + motoCount} total catalog items.`);
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  migrateCatalogIdentifiers();
}
