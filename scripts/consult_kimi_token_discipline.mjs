import fs from 'fs';
import os from 'os';
import path from 'path';

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

const NVIDIA_URL = "https://integrate.api.nvidia.com/v1/chat/completions";
const NVIDIA_KEY = loadVaultKey("NVIDIA_API_KEY");

const EXPERIENTIAL_URL = "https://api.experientiallabs.ai/v1/chat/completions";
const EXPERIENTIAL_KEY = loadVaultKey("EXPLABS_API_KEY");

async function callKimi(prompt) {
  console.log("📡 Calling Moonshot AI Kimi / Nemotron-Rigor (NVIDIA NIM)...");
  const start = Date.now();
  const systemPrompt = "You are Moonshot AI Kimi / Nemotron-Rigor, Chief Adversarial Systems Auditor and Token Economics Specialist. Provide ruthless, mathematically rigorous analysis of token discipline, context bloat elimination, and LLM cost optimization.";

  try {
    const controller = new AbortController();
    const timeout = setTimeout(() => controller.abort(), 25000);
    const res = await fetch(NVIDIA_URL, {
      signal: controller.signal,
      method: 'POST',
      headers: {
        'Content-Type': 'application/json',
        'Authorization': `Bearer ${NVIDIA_KEY}`
      },
      body: JSON.stringify({
        model: 'nvidia/nemotron-3-nano-omni-30b-a3b-reasoning',
        messages: [
          { role: 'system', content: systemPrompt },
          { role: 'user', content: prompt }
        ],
        temperature: 0.2,
        max_tokens: 3800
      })
    });
    clearTimeout(timeout);
    const data = await res.json();
    if (data.choices?.[0]?.message?.content) {
      const content = data.choices[0].message.content;
      const elapsed = ((Date.now() - start) / 1000).toFixed(1);
      console.log(`✅ Kimi (NVIDIA NIM) responded in ${elapsed}s (${content.length} chars)`);
      return content;
    }
  } catch (err) {
    console.warn("⚠️ NIM timed out or errored, falling back to Experiential Labs Kimi Persona:", err.message);
  }

  // Fallback: Experiential Labs with Kimi Persona
  console.log("🔄 Calling Experiential Labs (Kimi Persona Fallback)...");
  const res = await fetch(EXPERIENTIAL_URL, {
    method: 'POST',
    headers: {
      'Content-Type': 'application/json',
      'Authorization': `Bearer ${EXPERIENTIAL_KEY}`
    },
    body: JSON.stringify({
      model: 'gpt-6-luna',
      messages: [
        { role: 'system', content: systemPrompt },
        { role: 'user', content: prompt }
      ],
      temperature: 0.2,
      max_tokens: 4200
    })
  });

  const data = await res.json();
  const content = data.choices[0].message.content;
  const elapsed = ((Date.now() - start) / 1000).toFixed(1);
  console.log(`✅ Fallback responded in ${elapsed}s (${content.length} chars)`);
  return content;
}

async function main() {
  console.log("🚀 Launching Deep Token Discipline & Context Bloat Audit with Kimi...");

  const prompt = `
We need a deep adversarial audit of Helmetsan's token discipline, context footprint, and content elimination policy.

### Empirical Repository Token Footprint:
- **Total Physical Repository on Disk:** 52,816 files (926.9 MB) -> **255,761,273 raw tokens (~255.8M tokens)**
- **Ignored by .geminiignore:** 52,470 files (923.8 MB | 254,901,225 tokens)
- **Active Indexable Context:** 346 files (3.1 MB | **860,047 tokens**)
- **Current Token Shield Efficiency:** 99.7% of disk tokens blocked from context bloat.

### Category Breakdown:
1. 'HelmetsanWeb/data' (JSON catalogs, memory indices): 46,200 files | 702 MB | 193.7M tokens (Shielded)
2. 'HelmetsanWeb/vendor' (PHP dependencies): 4,850 files | 106 MB | 29.2M tokens (Shielded)
3. 'HelmetsanMobile' (SQLite catalog.db): 91.14 MB | 25.1M tokens (Shielded)
4. 'HelmetsanWeb/docs' (80+ markdown documents): 131 files | 4.79 MB | 1.32M tokens (74% Shielded, some active)
5. 'HelmetsanWeb/helmetsan-core' (PHP plugin): 204 files | 8.45 MB | 2.33M tokens (Core PHP active, seed-data shielded)
6. 'HelmetsanWeb/helmetsan-theme' (Templates, CSS, translations): 218 files | 6.92 MB | 1.91M tokens (Templates active, minified CSS shielded)
7. 'HelmetsanWeb/scripts': 188 files | 1.85 MB | 511K tokens (39% Shielded)
8. 'HelmetsanManager': 835 files | 4.68 MB | 1.29M tokens (95% Shielded)

### Top Individual Context Sinks & Monolithic Files:
- 'helmets_seed.json': 4.82 MB | 1,329,756 tokens
- 'helmet-default.png': 1.10 MB | 302,568 tokens
- 'create_helmets_seed.php': 487.7 KB | 131,413 tokens
- 'Admin.php': 337.9 KB | 91,065 tokens
- 'helmetsan-bundle.min.css': 257.7 KB | 69,453 tokens
- 'single-helmet.php': 104.3 KB | 27,440 tokens
- 'archive-helmet.php': 51.2 KB | 13,485 tokens
- 'archive-accessory.php': 49.7 KB | 13,082 tokens

### Pricing Model Matrix (1M tokens):
- 'gpt-6-luna' / 'gemini-2.0-flash': $0.10 input / $0.60 output / $0.025 prompt cached
- 'gpt-5.6-sol': $2.00 input / $10.00 output / $0.50 prompt cached
- 'claude-3.7-sonnet': $3.00 input / $15.00 output / $0.75 prompt cached

### Your Audit Directive:
1. **Content Not Required in Context**:
   - Exactly what content should NEVER be loaded into an LLM context during day-to-day coding, debugging, or feature development?
   - Identify stealth token sinks that developers often accidentally pull in (e.g. minified bundles, translation JSONs, seed data, historical audit logs).
2. **Mathematical Token Cost Modeling**:
   - Model the cost disparity between:
     - Unshielded raw load (255M tokens) vs Current shielded active context (860K tokens) vs Focused slice interaction (10K–25K tokens).
     - Standard real-time API vs Batch API (-50%) vs Prompt Caching (-75%).
3. **Execution Protocols for Zero Token Burn**:
   - Enforce slice notation rules (exact line ranges).
   - In-memory CLI tools vs reading files.
   - Subagent sandboxing to prevent transcript pollution.
4. **Hardened .geminiignore Recommendations**:
   - Review current ignore patterns and identify any remaining blind spots.
5. **Final Verdict & Actionable Policy**:
   - Provide an authoritative Token Discipline Manifesto for Helmetsan.
`;

  const kimiAnalysis = await callKimi(prompt);
  fs.writeFileSync('HelmetsanWeb/docs/KIMI_TOKEN_DISCIPLINE_AND_BLOAT_AUDIT.md', kimiAnalysis, 'utf8');
  console.log("💾 Saved Kimi Analysis to HelmetsanWeb/docs/KIMI_TOKEN_DISCIPLINE_AND_BLOAT_AUDIT.md");
  console.log("🎉 Deep Kimi Token Discipline Audit Complete!");
}

main().catch(err => {
  console.error("❌ Fatal Error:", err);
  process.exit(1);
});
