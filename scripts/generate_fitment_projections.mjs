#!/usr/bin/env node
/**
 * Helmetsan Materialized Fitment Projection Generator
 * Strategy formulated with GPT-5.6-Luna in LUNA_GAP_ANALYSIS_AND_ENHANCEMENTS.md.
 * 
 * Extracts 38,196 compatibility records from catalog.db and pre-materializes
 * high-performance, bounded, directional fitment projections for runtime 0ms overhead:
 * - motorcycle_helmet_fitments.json: Motorcycle ID -> Top 5 Recommended Helmets
 * - helmet_motorcycle_fitments.json: Helmet ID -> Top 5 Recommended Motorcycles
 */

import fs from 'fs';
import path from 'path';
import Database from 'better-sqlite3';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const WEB_DIR = path.resolve(__dirname, '..');
const DB_PATH = path.join(WEB_DIR, 'data', 'catalog.db');
const PROJECTIONS_DIR = path.join(WEB_DIR, 'data', 'projections');

if (!fs.existsSync(PROJECTIONS_DIR)) {
  fs.mkdirSync(PROJECTIONS_DIR, { recursive: true });
}

console.log("🚀 Initializing Fitment Projection Generator...");
console.log(`   Database: ${DB_PATH}`);

if (!fs.existsSync(DB_PATH)) {
  console.error(`❌ catalog.db not found at ${DB_PATH}`);
  process.exit(1);
}

const db = new Database(DB_PATH, { readonly: true });

// 1. Fetch helmets and motorcycles mapping for title/brand resolution
const helmets = new Map();
for (const h of db.prepare('SELECT id, slug, title, brand, helmet_type FROM helmets').all()) {
  helmets.set(h.id, h);
  if (h.slug) helmets.set(h.slug, h);
}

const motorcycles = new Map();
for (const m of db.prepare('SELECT id, slug, title, brand, category, riding_position FROM motorcycles').all()) {
  motorcycles.set(m.id, m);
  if (m.slug) motorcycles.set(m.slug, m);
}

console.log(`📦 Loaded ${helmets.size / 2 | 0} helmets and ${motorcycles.size / 2 | 0} motorcycles.`);

// 2. Query all motorcycle -> helmet compatibility records
console.log("🔍 Extracting compatibility matrix...");
const compatRows = db.prepare(`
  SELECT entity_a_id, entity_b_id, entity_a_type, entity_b_type, score, reason
  FROM compatibility
  ORDER BY score DESC
`).all();

console.log(`   Total compatibility rows: ${compatRows.length.toLocaleString()}`);

const bikeToHelmets = {};
const helmetToBikes = {};

for (const row of compatRows) {
  let bikeId = null;
  let helmetId = null;

  if (row.entity_a_type === 'motorcycle' && row.entity_b_type === 'helmet') {
    bikeId = row.entity_a_id;
    helmetId = row.entity_b_id;
  } else if (row.entity_a_type === 'helmet' && row.entity_b_type === 'motorcycle') {
    helmetId = row.entity_a_id;
    bikeId = row.entity_b_id;
  } else {
    continue;
  }

  // Normalize helmet ID (could be variant id like axor_street_red or model id like agv_k3)
  const helmetObj = helmets.get(helmetId) || helmets.get(helmetId.replace(/-/g, '_'));
  const bikeObj = motorcycles.get(bikeId) || motorcycles.get(bikeId.replace(/-/g, '_'));

  const hId = helmetObj ? helmetObj.id : helmetId;
  const bId = bikeObj ? bikeObj.id : bikeId;

  // Aggregate for Motorcycle -> Helmets (max 5)
  if (!bikeToHelmets[bId]) bikeToHelmets[bId] = [];
  if (bikeToHelmets[bId].length < 5 && !bikeToHelmets[bId].some(item => item.helmet_id === hId)) {
    bikeToHelmets[bId].push({
      helmet_id: hId,
      helmet_title: helmetObj ? helmetObj.title : hId.replace(/_/g, ' '),
      helmet_brand: helmetObj ? helmetObj.brand : '',
      helmet_type: helmetObj ? helmetObj.helmet_type : '',
      score: row.score,
      reason: row.reason,
      reason_code: 'motorcycle_fitment'
    });
  }

  // Aggregate for Helmet -> Motorcycles (max 5)
  if (!helmetToBikes[hId]) helmetToBikes[hId] = [];
  if (helmetToBikes[hId].length < 5 && !helmetToBikes[hId].some(item => item.motorcycle_id === bId)) {
    helmetToBikes[hId].push({
      motorcycle_id: bId,
      motorcycle_title: bikeObj ? bikeObj.title : bId.replace(/_/g, ' '),
      motorcycle_brand: bikeObj ? bikeObj.brand : '',
      motorcycle_category: bikeObj ? bikeObj.category : '',
      riding_position: bikeObj ? bikeObj.riding_position : '',
      score: row.score,
      reason: row.reason,
      reason_code: 'helmet_fitment'
    });
  }
}

// 3. Write projections
const bikeOutFile = path.join(PROJECTIONS_DIR, 'motorcycle_helmet_fitments.json');
const helmetOutFile = path.join(PROJECTIONS_DIR, 'helmet_motorcycle_fitments.json');

fs.writeFileSync(bikeOutFile, JSON.stringify(bikeToHelmets, null, 2), 'utf-8');
fs.writeFileSync(helmetOutFile, JSON.stringify(helmetToBikes, null, 2), 'utf-8');

const bikeKeys = Object.keys(bikeToHelmets).length;
const helmetKeys = Object.keys(helmetToBikes).length;

console.log(`✅ Materialized Projections Generated!`);
console.log(`   🏍️  Motorcycle -> Helmets: ${bikeKeys.toLocaleString()} bikes projected -> ${bikeOutFile}`);
console.log(`   🪖 Helmet -> Motorcycles: ${helmetKeys.toLocaleString()} helmets projected -> ${helmetOutFile}`);

db.close();
