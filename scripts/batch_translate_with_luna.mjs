#!/usr/bin/env node
/**
 * Helmetsan High-Efficiency Swarm Theme Translator
 * Powered by Antigravity AI Mesh (GPT-6-Luna), Canonical Prompt Prefixing, and SwarmPool.
 * 
 * Features:
 * 1. Zero hardcoded keys (resolves via Universal Vault ~/.config/antigravity/ai_mesh.env)
 * 2. Canonical static prefix (>1,150 tokens) meeting the upstream KV cache floor (>=1,024 tokens)
 * 3. Stable cache routing affinity via prompt_cache_key
 * 4. Local Translation Memory & cache deduplication (0 tokens for existing strings)
 * 5. High-throughput parallel execution via SwarmPool with jittered backoff
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';
import { aiMesh, CanonicalPromptBuilder, SwarmPool } from 'antigravity-ai-mesh';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);
const WEB_DIR = path.resolve(__dirname, '..');
const THEME_LANG_DIR = path.join(WEB_DIR, 'helmetsan-theme', 'languages');
const MISSING_STRINGS_PATH = '/tmp/missing_theme_strings.json';
const OUTPUT_TRANSLATIONS_PATH = '/tmp/luna_translated_missing.json';

const TARGET_LANGS = ['de', 'es', 'fr', 'it', 'ja', 'nl', 'pl', 'pt', 'zh'];

// 1. Rich Canonical Static Prefix (Guaranteed >= 1,150 tokens to surpass the 1,024-token KV cache floor)
const DETAILED_HELMET_STANDARDS_AND_I18N = `
# HELMETSAN UNIVERSAL MOTORCYCLE & PROTECTIVE GEAR LOCALIZATION STANDARDS
You are the Chief Technical Localization Engineer and Moto-Journalism Specialist for Helmetsan, the premier motorcycle helmet and riding gear intelligence platform.
Translate technical English UI strings and catalog labels into 9 target languages:
- de: German (Technical motorcycle standard German)
- es: Spanish (Neutral international motorcycle terminology)
- fr: French (Standard European motorcycle terminology)
- it: Italian (Standard Italian moto-journalism terminology)
- ja: Japanese (Natural Japanese riding terminology, katakana for established foreign tech names)
- nl: Dutch (Standard Dutch motorcycle terminology)
- pl: Polish (Accurate motorcycle riding terminology)
- pt: Portuguese (Neutral Brazilian / European Portuguese motorcycle terminology)
- zh: Simplified Chinese (Standard mainland Chinese motorcycle terminology)

## SECTION 1: SAFETY CERTIFICATION & HOMOLOGATION DICTIONARY
Never translate or alter these legal and regulatory safety standards:
- ECE 22.06 (Economic Commission for Europe): Oblique impact rotational acceleration testing (BrIC), 18 impact points, steel ball visor penetration test at 60 m/s (216 km/h).
- DOT FMVSS 218 (US Federal Motor Vehicle Safety Standard 218): 400G peak acceleration limit, dwell time thresholds (200G < 2.0ms, 150G < 4.0ms), pointed striker drop from 3.0m.
- FIM Racing Homologation Programme (FRHPhe-01 and FRHPhe-02): Mandatory for MotoGP and WorldSBK, oblique impact testing at 8.0 m/s on abrasive anvil.
- Snell Memorial Foundation (Snell M2020D / M2020R): Extreme impact attenuation, double-drop tests on flat and hemispherical anvils.
- P/J Homologation: Dual homologation for modular flip-up helmets (Protective full-face and Jet open-face).

## SECTION 2: PROPRIETARY MATERIALS & HARDWARE GLOSSARY
Retain exact brand engineering names:
- Visor & Anti-Fog: Pinlock, Pinlock 70, Pinlock 120, MaxVision, Tear-Off Posts, Photochromic Transitions Visor, Optically Correct Class 1 Shield.
- Impact Management: MIPS (Multi-directional Impact Protection System), Multi-Density EPS Liner, Dual-Density EPS, Rotational Inertia Mitigation.
- Shell Engineering: AIM+ (Advanced Integrated Matrix Plus), Pre-Preg Carbon Fiber, Basalt-Reinforced Fiberglass, LG Chem ABS Thermoplastic.
- Retention & Fitment: Double-D Ring (track mandatory), Micrometric Metal-on-Metal Ratchet Buckle, Emergency Quick Release System (EQRS) cheek pads.
- Aerodynamics & Comfort: Aero-acoustic wind noise reduction (dB rating), chin curtain, breath deflector, eyewear channel (glasses-friendly pads).

## SECTION 2.5: STRUCTURAL HELMET CATEGORIES & SHELL ARCHITECTURE
- Full Face Helmets: Fixed one-piece chin bar offering highest torsional rigidity and maximum facial crash protection. Preferred for track racing, sport riding, and high-speed highway touring.
- Modular / Flip-Up Helmets: Movable chin bar with dual P/J homologation (Protective/Jet). Must specify if lockable in raised position. Popular among adventure tourers and urban commuters.
- Adventure / Dual-Sport Helmets: Hybrid construction featuring sun peak with aerodynamic pass-through vents, wide eye-port accommodating MX goggles, and optional shield removal for off-road dust protection.
- Open Face / 3/4 Helmets: Without chin bar, retro cruiser ergonomics, maximum peripheral visibility and direct ambient airflow.
- Motocross / Off-Road Helmets: Elongated roost guard chin bar, no face shield (goggle required), large brow intake ports, lightweight composite shell for extreme physical heat dissipation.

## SECTION 2.6: AERODYNAMICS, VENTILATION & SENSORY SPECIFICATIONS
- Aero-Acoustic Tuning: Boundary layer airflow separation management, wind tunnel engineered spoiler lips reducing neck turbulence and buffeting at speeds exceeding 120 km/h.
- Ventilation Architecture: Multi-channel EPS internal channelling, negative pressure Venturi exhaust ports, multi-position glove-friendly chin intakes, brow vent air scoops.
- Internal Comfort Liners: Moisture-wicking, anti-bacterial, hypoallergenic sanitized fabric, 3D laser-cut cheek pad foam, washable and replaceable crown liners.

## SECTION 3: STRICT FORMATTING & SYNTAX CONSTRAINTS
1. Exact Placeholder Preservation:
   - Preserve all printf placeholders identically: %s, %d, %1$s, %2$s, %1$d, %2$d. Never drop or reorder placeholders.
2. HTML & Character Entity Integrity:
   - Retain all HTML entities and markup: &larr;, &rarr;, &amp;, &bull;, <span>, <strong>, etc.
3. UI Layout Brevity:
   - Motorcycle UI buttons, filter chips, and table headers must remain concise to prevent mobile layout wrapping.

## SECTION 4: OUTPUT SPECIFICATION
Return ONLY a valid, complete JSON object matching this schema:
{
  "de": { "English String": "German Translation", ... },
  "es": { "English String": "Spanish Translation", ... },
  "fr": { ... },
  "it": { ... },
  "ja": { ... },
  "nl": { ... },
  "pl": { ... },
  "pt": { ... },
  "zh": { ... }
}
`;

// Initialize CanonicalPromptBuilder
const builder = new CanonicalPromptBuilder({
  projectId: 'helmetsan',
  taskFamily: 'theme_i18n',
  version: 'v1.0',
  staticPrefixes: [DETAILED_HELMET_STANDARDS_AND_I18N]
});

console.log(`================================================================`);
console.log(`🌐 HELMETSAN MULTI-LANGUAGE SWARM LOCALIZATION ENGINE`);
console.log(`   Prefix Token Estimate: ${builder.estimatePrefixTokens()} (Floor >= 1024: ${builder.estimatePrefixTokens() >= 1024})`);
console.log(`   Cache Affinity Key:    ${builder.getCacheKey()}`);
console.log(`   Target Locales (9):    ${TARGET_LANGS.join(', ')}`);
console.log(`================================================================\n`);

// 2. Load strings and existing memory
if (!fs.existsSync(MISSING_STRINGS_PATH)) {
  console.log(`Creating sample missing strings batch...`);
  const sampleMissing = [
    "Accessories Tracked",
    "Add to Comparison",
    "Airflow performance rating",
    "Anti-fog visor insert included",
    "Emergency quick-release cheek pads",
    "Homologated P/J modular chin bar",
    "Multi-density EPS impact absorbing liner",
    "Aerodynamic spoiler with high-speed stability",
    "Eyewear friendly interior cheek padding",
    "Integrated sun shield with UV400 protection"
  ];
  fs.writeFileSync(MISSING_STRINGS_PATH, JSON.stringify(sampleMissing, null, 2));
}

const rawMissing = JSON.parse(fs.readFileSync(MISSING_STRINGS_PATH, 'utf8'));
const uniqueStrings = [...new Set(rawMissing.filter(s => typeof s === 'string' && s.trim().length > 0))];
console.log(`Total unique strings to process: ${uniqueStrings.length}`);

// Load existing translations if file exists (Persistent Memory)
const cumulativeTranslations = {};
for (const lang of TARGET_LANGS) {
  cumulativeTranslations[lang] = {};
}

if (fs.existsSync(OUTPUT_TRANSLATIONS_PATH)) {
  try {
    const existing = JSON.parse(fs.readFileSync(OUTPUT_TRANSLATIONS_PATH, 'utf8'));
    for (const lang of TARGET_LANGS) {
      if (existing[lang]) {
        Object.assign(cumulativeTranslations[lang], existing[lang]);
      }
    }
    console.log(`Loaded existing translation memory from ${OUTPUT_TRANSLATIONS_PATH}`);
  } catch (err) {
    console.warn(`Could not parse existing memory: ${err.message}`);
  }
}

// Filter out strings already completely translated across all 9 languages ($0 cost!)
const stringsNeedingTranslation = uniqueStrings.filter(str => {
  return TARGET_LANGS.some(lang => !cumulativeTranslations[lang][str]);
});

console.log(`Strings already cached in Translation Memory: ${uniqueStrings.length - stringsNeedingTranslation.length} ($0 cost)`);
console.log(`Strings requiring Swarm Translation:        ${stringsNeedingTranslation.length}`);

if (stringsNeedingTranslation.length === 0) {
  console.log(`\n🎉 All strings are 100% translated in memory! No external AI calls needed.`);
  process.exit(0);
}

// 3. Batch into groups of 5 strings for reliable output token bounds
const BATCH_SIZE = 5;
const batches = [];
for (let i = 0; i < stringsNeedingTranslation.length; i += BATCH_SIZE) {
  batches.push({
    batchIndex: Math.floor(i / BATCH_SIZE) + 1,
    strings: stringsNeedingTranslation.slice(i, i + BATCH_SIZE)
  });
}
console.log(`Grouped into ${batches.length} parallel batches of up to ${BATCH_SIZE} strings each.\n`);

async function executeTranslation() {
  const pool = new SwarmPool({
    concurrency: 2,
    maxRetries: 3,
    baseDelayMs: 2000
  });

  const startTime = Date.now();
  console.log(`🚀 Dispatching parallel swarm to GPT-6-Luna with warm cache affinity...`);

  let totalCachedTokensRead = 0;
  let totalPromptTokensSent = 0;
  let totalCostUSD = 0;

  const results = await pool.processAll(batches, async (batchItem) => {
    const promptData = builder.buildMessages({
      agentRole: 'Lead Technical Moto-Journalism Localization Engineer',
      dynamicPayload: {
        batch_id: batchItem.batchIndex,
        total_in_batch: batchItem.strings.length,
        strings: batchItem.strings
      },
      userPrompt: 'Translate all strings into the 9 target languages conforming strictly to the JSON schema.'
    });

    const res = await aiMesh.consultLuna(promptData.messages, {
      model: 'gpt-6-luna',
      temperature: 0.1,
      maxTokens: 4096,
      prompt_cache_key: promptData.promptCacheKey
    });

    let parsed = null;
    try {
      const content = res.content.trim();
      const jsonStart = content.indexOf('{');
      const jsonEnd = content.lastIndexOf('}');
      if (jsonStart !== -1 && jsonEnd !== -1) {
        parsed = JSON.parse(content.substring(jsonStart, jsonEnd + 1));
      } else {
        parsed = JSON.parse(content);
      }
    } catch (parseErr) {
      throw new Error(`Failed to parse JSON response from Luna: ${parseErr.message}`);
    }

    return {
      batchIndex: batchItem.batchIndex,
      stringCount: batchItem.strings.length,
      translations: parsed,
      usage: res.usage,
      latencyMs: res.latencyMs,
      cost: res.cost
    };
  });

  console.log(`\n⏱️ Swarm Execution Completed in ${((Date.now() - startTime) / 1000).toFixed(2)}s\n`);

  results.forEach(r => {
    if (r.success) {
      const data = r.result;
      const cached = data.usage?.cachedTokens || 0;
      const promptToks = data.usage?.promptTokens || 0;
      const hitRate = data.usage?.cacheHitRate || '0%';
      totalCachedTokensRead += cached;
      totalPromptTokensSent += promptToks;
      totalCostUSD += (data.cost || 0);

      console.log(`✅ Batch ${data.batchIndex}/${batches.length} (${data.stringCount} strings) | Latency: ${(data.latencyMs / 1000).toFixed(2)}s | Tokens: In=${promptToks}, Cached=${cached} (${hitRate}) | Cost: $${data.cost?.toFixed(6) || '0.000000'}`);

      // Merge translations
      for (const lang of TARGET_LANGS) {
        if (data.translations[lang]) {
          Object.assign(cumulativeTranslations[lang], data.translations[lang]);
        }
      }
    } else {
      console.error(`❌ Batch ${r.item.batchIndex} failed:`, r.error);
    }
  });

  // Save cumulative translations to disk
  fs.writeFileSync(OUTPUT_TRANSLATIONS_PATH, JSON.stringify(cumulativeTranslations, null, 2), 'utf8');
  console.log(`\n💾 Saved all translations to: ${OUTPUT_TRANSLATIONS_PATH}`);

  const overallHitRate = totalPromptTokensSent > 0
    ? ((totalCachedTokensRead / totalPromptTokensSent) * 100).toFixed(1) + '%'
    : '0%';

  console.log(`\n================================================================`);
  console.log(`📊 BATCH TRANSLATION TELEMETRY SUMMARY:`);
  console.log(`   Total Prompt Tokens Sent: ${totalPromptTokensSent}`);
  console.log(`   Cached Tokens Read:       ${totalCachedTokensRead}`);
  console.log(`   Overall Cache Hit Rate:   ${overallHitRate} 🟢 (KV Cache Warm)`);
  console.log(`   Total AI Mesh Spend:      $${totalCostUSD.toFixed(6)}`);
  console.log(`================================================================\n`);
}

executeTranslation().catch(err => {
  console.error("Fatal error in swarm translator:", err);
  process.exit(1);
});
