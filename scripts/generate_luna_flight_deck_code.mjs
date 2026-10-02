#!/usr/bin/env node
/**
 * Generate Complete Production Code for the 3 Flight Deck Domains via gpt-5.6-luna
 */

import fs from 'fs';
import os from 'os';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

function loadVaultKey(keyName) {
  if (process.env[keyName]) return process.env[keyName].trim();
  const vaultPath = path.join(os.homedir(), '.config', 'antigravity', 'ai_mesh.env');
  if (fs.existsSync(vaultPath)) {
    const lines = fs.readFileSync(vaultPath, 'utf8').split('\n');
    for (const line of lines) {
      const trimmed = line.trim();
      if (trimmed.startsWith(`${keyName}=`)) {
        return trimmed.slice(`${keyName}=`.length).trim();
      }
    }
  }
  return '';
}

const API_KEY = loadVaultKey("EXPLABS_API_KEY");
const ENDPOINT = "https://api.experientiallabs.ai/v1/chat/completions";
const MODEL = "gpt-6-luna";

const BASE_DIR = path.resolve(__dirname, '../..');
const MANAGER_DIR = path.join(BASE_DIR, 'HelmetsanManager');
const WEB_DIR = path.join(BASE_DIR, 'HelmetsanWeb');

async function callLuna(prompt) {
  console.log(`🚀 Requesting implementation specifications from ${MODEL}...`);
  const res = await fetch(ENDPOINT, {
    method: "POST",
    headers: {
      "Authorization": `Bearer ${API_KEY}`,
      "Content-Type": "application/json"
    },
    body: JSON.stringify({
      model: MODEL,
      messages: [
        {
          role: "system",
          content: "You are an elite Principal Software Architect and Lead Systems Engineer. Provide clean, robust, production-grade JavaScript code for Helmetsan Mission Control V2 addressing all 3 domains: Catalog & Deployment Pipeline, AI Swarm & Translation Bot Resilience, and Flight Deck Operator UX & Job Engine."
        },
        { role: "user", content: prompt }
      ],
      max_tokens: 7500
    })
  });

  if (!res.ok) {
    throw new Error(`HTTP ${res.status}: ${await res.text()}`);
  }

  const data = await res.json();
  return data.choices[0].message.content;
}

async function main() {
  const prompt = `
Please author the exact, production-grade architectural patterns and code modules for Helmetsan Mission Control V2:

### DOMAIN 1: Catalog Pipeline & Deployment Ops
1. **In-Memory Cross-Link Index**: Pre-index accessories and motorcycles in RAM on startup and cache with a 5-minute TTL. Eliminate the synchronous 80-file disk scan in \`/api/catalog/:entity/:id\`.
2. **Deterministic Catalog Pagination**: Deterministically sort catalog files (\`localeCompare\`) before slicing.
3. **Structured Job State Engine**: Implement a global \`JobManager\` in \`server.js\` tracking jobs (\`id\`, \`type\`, \`name\`, \`status\`: 'running'|'succeeded'|'failed', \`startedAt\`, \`endedAt\`, \`exitCode\`, \`error\`, \`pid\`). Expose \`/api/jobs\` and \`/api/jobs/:id/cancel\`.

### DOMAIN 2: Compute Grid & Translation Bot Resilience
1. **Heartbeat & Bounds Validation**: In \`/api/translation/bot/start\`, strictly validate \`count\` (1-5415), \`batch_size\` (1-50), \`workers\` (1-8), and allowlist models. Report heartbeat-based health.
2. **Node B Circuit Breaker**: Ping Node B with a 2-second timeout before dispatching swarm tasks; if Node B fails, automatically route 100% of workers to Node A without failing the batch.
3. **Google Intelligence HTML Sanitization**: Ensure all query strings, URLs, and anomaly texts are escaped before rendering.

### DOMAIN 3: Flight Deck UX & Terminal Console
1. **Channel-Isolated Terminal**: Refactor \`clearTerminal(channel)\` so clearing Web Ops logs does not wipe Translation logs.
2. **WebSocket Sequence Numbers**: Add \`seq\` counter and timestamp to every broadcast message.
3. **Smart Auto-Scroll**: Detect operator scroll up (if \`scrollTop + clientHeight < scrollHeight - 30\`) and pause auto-scrolling until scrolled back to bottom.
4. **Action Confirmation Modals**: Client-side confirmation before triggering Deploy, Cloudflare purge, or Recompile DB.

Provide structured, modular code snippets ready for integration.
`;

  const response = await callLuna(prompt);
  const outPath = path.join(WEB_DIR, "docs", "LUNA_FLIGHT_DECK_IMPLEMENTATION_SPEC.md");
  fs.writeFileSync(outPath, response, 'utf-8');
  console.log(`✅ Saved Luna flight deck implementation specs to: ${outPath}`);
}

main().catch(console.error);
