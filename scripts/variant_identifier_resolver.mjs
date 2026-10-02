import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { AMAZON_REGIONS, ONELINK_ENABLED_REGIONS, buildDefaultAmazonRegistry } from './asin_schema_migrator.mjs';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const HELMETS_DIR = path.join(__dirname, '..', 'data', 'helmets');

export function resolveVariantIdentifiers() {
  if (!fs.existsSync(HELMETS_DIR)) {
    throw new Error(`Helmets directory not found: ${HELMETS_DIR}`);
  }

  const files = fs.readdirSync(HELMETS_DIR).filter(f => f.endsWith('.json'));
  console.log(`🎨 Resolving variant-level identifiers across ${files.length} helmets...`);

  let totalVariants = 0;
  let updatedHelmets = 0;

  for (const f of files) {
    const filePath = path.join(HELMETS_DIR, f);
    const data = JSON.parse(fs.readFileSync(filePath, 'utf-8'));
    const brand = data.brand || 'Motorcycle Helmet';
    const parentTitle = data.title || data.id;

    if (Array.isArray(data.variants) && data.variants.length > 0) {
      for (const v of data.variants) {
        totalVariants++;
        const variantId = v.id || `${data.id}_${v.color || 'var'}`.toLowerCase().replace(/[^a-z0-9_]/g, '_');
        const color = v.color || '';
        const finish = v.finish || '';
        const variantTitle = v.title || `${parentTitle} ${color}`.trim();

        // Variant-level search query (high precision)
        const variantSearchQuery = `${brand} ${parentTitle} ${color} ${finish}`.replace(/\s+/g, ' ').trim();

        // Canonical internal SKU
        const variantSku = `HSN-VAR-${variantId.toUpperCase().replace(/[^A-Z0-9]/g, '-').slice(0, 36)}`;

        // If variant already had an ASIN, preserve it if clean
        const existingAsin = v.identifiers?.asin || v.asin || null;

        // Build 22-region registry for variant
        v.identifiers = {
          amazon: buildDefaultAmazonRegistry(brand, parentTitle, color, existingAsin, false, null),
          sku: variantSku,
          gtin: v.identifiers?.gtin || null,
          ean: v.identifiers?.ean || null,
          mpn: v.identifiers?.mpn || null,
          retailer_skus: {
            revzilla: null,
            fc_moto: null,
            motoin: null,
            cyclegear: null,
            louis: null,
            chromeburner: null
          }
        };

        // Also ensure top-level sku is updated
        v.sku = variantSku;
      }
      fs.writeFileSync(filePath, JSON.stringify(data, null, 2), 'utf-8');
      updatedHelmets++;
    }
  }

  console.log(`✅ Variant Resolution Complete:`);
  console.log(`   - Helmets with variants:  ${updatedHelmets}`);
  console.log(`   - Total Variants mapped:  ${totalVariants}`);
  return { updatedHelmets, totalVariants };
}

if (process.argv[1] === fileURLToPath(import.meta.url)) {
  resolveVariantIdentifiers();
}
