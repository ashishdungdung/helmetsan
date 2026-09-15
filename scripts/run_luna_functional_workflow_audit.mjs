#!/usr/bin/env node
/**
 * Deep Functional & Operational Workflow Audit of Helmetsan Mission Control
 * Auditor Engine: gpt-5.6-luna (via Experiential Labs AI Gateway)
 * Node.js ESM Runner (Zero Xcode Shim Dependency)
 */

import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __filename = fileURLToPath(import.meta.url);
const __dirname = path.dirname(__filename);

const API_KEY = process.env.EXPLABS_API_KEY || "xpl_347a690bbbf034a8340fcd0cd3ee91b5ec0147e1";
const ENDPOINT = "https://api.experientiallabs.ai/v1/chat/completions";
const MODEL = "gpt-5.6-luna";

const BASE_DIR = path.resolve(__dirname, '../..');
const MANAGER_DIR = path.join(BASE_DIR, 'HelmetsanManager');
const WEB_DIR = path.join(BASE_DIR, 'HelmetsanWeb');

function readFileSafe(filePath, maxChars = null) {
  if (fs.existsSync(filePath)) {
    try {
      const content = fs.readFileSync(filePath, 'utf-8');
      if (maxChars && content.length > maxChars) {
        return content.slice(0, maxChars) + `\n... [TRUNCATED: remaining ${content.length - maxChars} chars]`;
      }
      return content;
    } catch {
      return `[ERROR READING: ${filePath}]`;
    }
  }
  return `[FILE NOT FOUND: ${filePath}]`;
}

async function callLuna(systemPrompt, userPrompt, phaseName, maxTokens = 7500) {
  console.log(`\n${'='.repeat(75)}`);
  console.log(`🚀 Dispatching ${phaseName} (${userPrompt.length} chars) -> ${MODEL}`);
  console.log(`${'='.repeat(75)}`);

  const payload = {
    model: MODEL,
    messages: [
      { role: "system", content: systemPrompt },
      { role: "user", content: userPrompt }
    ],
    max_tokens: maxTokens
  };

  const startTime = Date.now();

  const res = await fetch(ENDPOINT, {
    method: "POST",
    headers: {
      "Authorization": `Bearer ${API_KEY}`,
      "Content-Type": "application/json"
    },
    body: JSON.stringify(payload)
  });

  if (!res.ok) {
    const errText = await res.text();
    console.error(`❌ HTTP ${res.status} in ${phaseName}: ${errText}`);
    throw new Error(`Luna API returned HTTP ${res.status}`);
  }

  const data = await res.json();
  const elapsed = (Date.now() - startTime) / 1000;
  const content = data.choices[0].message.content;
  const usage = data.usage || {};
  const finishReason = data.choices[0].finish_reason;

  console.log(`✅ ${phaseName} completed in ${elapsed.toFixed(2)}s | Tokens: ${usage.total_tokens} (Finish: ${finishReason})`);
  return { content, usage, elapsed };
}

async function main() {
  console.log("📡 Gathering Mission Control functional architecture...");
  const serverJs = readFileSafe(path.join(MANAGER_DIR, 'server.js'));
  const appJs = readFileSafe(path.join(MANAGER_DIR, 'public', 'app.js'));
  const indexHtml = readFileSafe(path.join(MANAGER_DIR, 'public', 'index.html'), 18000);

  const deploySh = readFileSafe(path.join(WEB_DIR, 'deploy.sh'), 4000);
  const checkDrift = readFileSafe(path.join(WEB_DIR, 'scripts', 'check_data_drift.php'), 4000);
  const googleCli = readFileSafe(path.join(WEB_DIR, 'scripts', 'google_intelligence_cli.php'), 4000);

  // ─────────────────────────────────────────────────────────────────────────
  // PART 1: Core Operations, Catalog Pipeline & Data Workflows
  // ─────────────────────────────────────────────────────────────────────────
  const sysPromptWf1 = 
    "You are an elite Principal Operations Architect, Enterprise DevOps Lead, and Data Pipeline Specialist. " +
    "You are conducting an exhaustive, uncompromising functional and operational workflow audit of Helmetsan Mission Control. " +
    "Audit the catalog pipelines, deployment workflows, remote synchronization, and data consistency mechanisms. " +
    "Critique edge cases, failure recovery, asynchronous user feedback, state synchronization bugs, and pipeline bottlenecks.";

  const userPromptWf1 = `
## FUNCTIONAL AUDIT PART 1: CATALOG PIPELINE, DEPLOYMENT & SERVER OPS WORKFLOWS

### ARCHITECTURAL CONTEXT
Helmetsan Mission Control manages a multi-tier catalog (2,219 helmets, 3,247 motorcycles, accessories, brands, standards) across three datastores:
1. **Master JSON Repository** (\`HelmetsanWeb/data/\`)
2. **Production WordPress Database** (Polylang multilingual MySQL at \`31.70.136.154\`)
3. **Offline-First SQLite Database** (\`HelmetsanMobile/assets/database/catalog.db\`)

### ASSETS UNDER AUDIT
\`server.js\` (Core Server & Catalog/Action Routes):
\`\`\`javascript
${serverJs.slice(0, 25000)}
\`\`\`

\`deploy.sh\`:
\`\`\`bash
${deploySh}
\`\`\`

\`check_data_drift.php\`:
\`\`\`php
${checkDrift}
\`\`\`

### REQUIRED DELIVERABLES FOR PART 1:
1. **Catalog Pipeline & Cross-Linking Functional Audit**:
   - Analyze \`/api/catalog/:entity\`, filtering, pagination, and \`/api/catalog/:entity/:id\`.
   - Critique the cross-linking heuristic between helmets, accessories, and motorcycles (lines 290-330): Is slicing 80 bike files and matching on \`item.type\` efficient and accurate? What happens with memory and file descriptors?
   - Evaluate the ground-truth vs LLM validation status resolution (\`getAuditStatus\`). Are there race conditions or stale reads?
2. **Data Drift & Recompilation Workflow Audit**:
   - Evaluate the Data Drift Sentinel (\`/api/catalog/drift\` -> \`check_data_drift.php\`). Does it catch missing fields, schema mismatches, and variant regressions?
   - Evaluate the SQLite database compilation workflow (\`/api/action/recompile-db\` -> \`export-mobile-db.py\`). How does the UI track completion? What happens if it fails midway?
3. **Web & Server Operations Workflow Audit**:
   - Evaluate 1-click deployment (\`/api/action/deploy-web\` -> \`deploy.sh\`). How does it handle \`--theme-only\` vs \`--plugin-only\`? What happens on SSH failure or syntax errors?
   - Evaluate the Cloudflare edge cache purge workflow. Does it purge all multilingual edge paths (\`/\`, \`/de/\`, \`/zh/\`, \`/es/\`)?
   - Evaluate remote log streaming (\`/api/action/tail-remote-ingest\`). How does it handle broken SSH pipes, network drops, or server reboots?
`;

  const wf1 = await callLuna(sysPromptWf1, userPromptWf1, "Part 1 (Catalog & Server Ops Workflows)");

  // ─────────────────────────────────────────────────────────────────────────
  // PART 2: Distributed Compute, Translation Bot & AI Workflows
  // ─────────────────────────────────────────────────────────────────────────
  const sysPromptWf2 = 
    "You are an elite Principal AI Systems Architect and Distributed Compute Grid Engineer. " +
    "You are conducting an exhaustive functional audit of Helmetsan Mission Control's distributed AI workflows: " +
    "the Apple Silicon Metal Translation Bot, the Silicon Compute Swarm (Node A/Node B), and live telemetry pipelines.";

  const userPromptWf2 = `
## FUNCTIONAL AUDIT PART 2: AI SWARM, TRANSLATION BOT & LIVE TELEMETRY WORKFLOWS

### ASSETS UNDER AUDIT
\`server.js\` (Swarm, Translation Bot, Google Intelligence & Vault Routes):
\`\`\`javascript
${serverJs.slice(23000)}
\`\`\`

\`google_intelligence_cli.php\`:
\`\`\`php
${googleCli}
\`\`\`

### REQUIRED DELIVERABLES FOR PART 2:
1. **Multilingual Catalog & Metal Translation Bot Workflow Audit**:
   - Audit the complete lifecycle of the translation bot:
     - Starting batch (\`--count N\`) vs Daemon mode (\`--daemon\`).
     - Single helmet translation (\`--post-id\`).
     - Stopping the bot (\`--stop\` + signal escalation).
   - Evaluate the Polylang bidirectional sync audit (\`audit_bidirectionality.php\`) and cluster health reporting (\`fetchLiveTranslationStats\`). How is cache invalidation handled?
   - Live log buffer and WebSocket channel isolation (\`channel: 'translation'\`). Does the operator get real-time feedback on token savings and translation speed?
2. **Silicon Compute Swarm Grid Workflow Audit (Node A / Node B)**:
   - Audit the 3-stage hybrid swarm workflow: Vector Cluster -> Work-Stealing Queue -> Prefix KV Cache.
   - Evaluate node health probing (\`--test-nodes\` against Node A @ 127.0.0.1:1234 and Node B @ 192.168.2.223:1235). What happens if Node B is powered off or drops packets?
   - Evaluate \`/api/swarm/advanced-metrics\` and live queue monitoring (port 9090). Is there automatic failover?
3. **Google Live Intelligence & Monetization Workflows**:
   - Audit the GA4 & Search Console telemetry pipeline (\`/api/google/intelligence\`). Evaluate the dual execution strategy (Local PHP CLI runner -> remote SSH fallback) and caching (120s TTL).
   - How does the traffic anomaly sentinel detect surges or drops?
   - Evaluate the 21-country Amazon affiliate link generator and Creator API OAuth token inspection.
`;

  const wf2 = await callLuna(sysPromptWf2, userPromptWf2, "Part 2 (AI Swarm & Translation Workflows)");

  // ─────────────────────────────────────────────────────────────────────────
  // PART 3: Operator UX, State Machine, Frontend Workflow & Flight Deck Roadmap
  // ─────────────────────────────────────────────────────────────────────────
  const sysPromptWf3 = 
    "You are an elite Principal Product Architect, Flight Deck UX Specialist, and Frontend Systems Engineer. " +
    "You are conducting a thorough functional audit of the Mission Control user experience, client-side state machine, " +
    "tab switching, terminal feedback loops, and drafting the definitive Functional Enhancement Roadmap.";

  const userPromptWf3 = `
## FUNCTIONAL AUDIT PART 3: OPERATOR UX, STATE MACHINE & FLIGHT DECK ENHANCEMENT ROADMAP

### ASSETS UNDER AUDIT
\`public/index.html\`:
\`\`\`html
${indexHtml}
\`\`\`

\`public/app.js\` (Excerpts):
\`\`\`javascript
${appJs.slice(0, 25000)}
\`\`\`

### REQUIRED DELIVERABLES FOR PART 3:
1. **Operator Experience & State Machine Audit**:
   - Evaluate tab switching (\`switchTab\`) and data loading triggers. Are there redundant API requests or race conditions when switching tabs rapidly?
   - Evaluate the terminal log viewer (\`addTerminalLine\`, filter, copy, auto-scroll). Is the 300-line buffer appropriate for high-throughput batch runs?
   - Evaluate the Catalog Inspector panel: Does it provide complete situational awareness (specs, variants, pricing across currencies, linked accessories, compatible bikes)?
2. **Failure Modes & Error Visibility in the UI**:
   - When a deployment fails, does the operator see clear actionable error diagnostics or a silent hang?
   - When an SSH command times out, how does the UI gracefully inform the user?
3. **Definitive Functional Enhancement Roadmap (The Flight Deck Doctrine)**:
   - What high-impact operational tools are missing? (e.g., Live VRAM / GPU thermal telemetry for Node A, 1-Click Rollback for deployments, Database Drift Auto-Healer, Multi-Language Coverage Heatmap).
   - Priority 1: Immediate Workflow Improvements.
   - Priority 2: Automated Grid Resilience & Failover.
   - Priority 3: Advanced Business & Catalog Telemetry.
`;

  const wf3 = await callLuna(sysPromptWf3, userPromptWf3, "Part 3 (Operator UX & Flight Deck Roadmap)");

  // ─────────────────────────────────────────────────────────────────────────
  // SYNTHESIS: Compile Master Functional Workflow Report
  // ─────────────────────────────────────────────────────────────────────────
  console.log("\n📝 Compiling Master Functional Workflow Audit Document...");
  const reportPath = path.join(WEB_DIR, "docs", "HELMETSAN_MISSION_CONTROL_FUNCTIONAL_WORKFLOW_AUDIT.md");

  const totalTokens = (wf1.usage.total_tokens || 0) + (wf2.usage.total_tokens || 0) + (wf3.usage.total_tokens || 0);
  const totalCompletionTokens = (wf1.usage.completion_tokens || 0) + (wf2.usage.completion_tokens || 0) + (wf3.usage.completion_tokens || 0);
  const totalElapsed = wf1.elapsed + wf2.elapsed + wf3.elapsed;

  const masterDoc = `# Helmetsan Mission Control (HelmetsanManager) — Master Functional & Operational Workflow Audit

**Auditor Engine:** \`${MODEL}\` (Experiential Labs AI Gateway)
**Audit Execution Date:** ${new Date().toISOString().replace('T', ' ').slice(0, 19)}
**Target System:** \`HelmetsanManager\` (Unified Mission Control & Operations Dashboard)
**Total Audit Tokens:** ${totalTokens.toLocaleString()} tokens across 3 operational workflow domains
**Cumulative Inference Time:** ${totalElapsed.toFixed(2)} seconds

## Operational Workflow Telemetry

| Workflow Domain | Focus Areas | Duration | Completion Tokens | Total Tokens |
|---|---|---:|---:|---:|
| **Domain 1** | Catalog Pipeline, Drift Sentinel & Deployment Ops | ${wf1.elapsed.toFixed(1)}s | ${(wf1.usage.completion_tokens || 0).toLocaleString()} | ${(wf1.usage.total_tokens || 0).toLocaleString()} |
| **Domain 2** | Silicon Swarm Grid, Metal Bot & Live Telemetry | ${wf2.elapsed.toFixed(1)}s | ${(wf2.usage.completion_tokens || 0).toLocaleString()} | ${(wf2.usage.total_tokens || 0).toLocaleString()} |
| **Domain 3** | Operator Flight Deck UX, Error Visibility & Roadmap | ${wf3.elapsed.toFixed(1)}s | ${(wf3.usage.completion_tokens || 0).toLocaleString()} | ${(wf3.usage.total_tokens || 0).toLocaleString()} |
| **TOTAL** | **Full-Spectrum Workflow Audit** | **${totalElapsed.toFixed(1)}s** | **${totalCompletionTokens.toLocaleString()}** | **${totalTokens.toLocaleString()}** |

---

# PART I: CATALOG PIPELINE, DEPLOYMENT OPS & DATA INTEGRITY WORKFLOWS

${wf1.content}

---

# PART II: SILICON COMPUTE SWARM, METAL BOT & LIVE TELEMETRY WORKFLOWS

${wf2.content}

---

# PART III: OPERATOR FLIGHT DECK UX, RESILIENCE & FUNCTIONAL ENHANCEMENT ROADMAP

${wf3.content}
`;

  fs.writeFileSync(reportPath, masterDoc, 'utf-8');
  console.log(`🎉 Master Functional & Workflow Audit written to: ${reportPath}`);
  console.log(`📄 File size: ${fs.statSync(reportPath).size.toLocaleString()} bytes`);
}

main().catch(err => {
  console.error("❌ Fatal Error:", err);
  process.exit(1);
});
