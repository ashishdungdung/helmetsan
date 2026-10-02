# Helmetsan AI Mesh Caching & Cost Minimization Architecture

## Executive Overview
This document details the production implementation of the **Antigravity AI Mesh** inside **Helmetsan**, designed to eliminate wasted API spend on external AI models (specifically ChatGPT 6 Luna and DeepSeek V4.1 via Experiential Labs Gateway) and maintain an **85%–93% Prompt Cache Hit Rate**.

---

## 1. Root Cause Analysis: Why Historical Requests Showed 0% Cache Hits
In an audit of 57,000+ historical requests to Experiential Labs:
1. **Sub-Floor Contexts (< 1,024 Tokens):** Experiential Labs and upstream model clusters enforce a strict 1,024-token minimum floor for KV prompt caching. Prompts averaging 600–700 tokens were processed without caching.
2. **Dynamic Prefix Invalidation:** Any dynamic tokens placed at character 0 (e.g., timestamps, session IDs, changing queries) completely break prefix hashing.
3. **GPU VRAM Eviction (5-Minute TTL):** Sequential calls spaced 10–15 minutes apart always hit cold GPU VRAM.
4. **Cluster Flipping:** Lack of `prompt_cache_key` affinity routed sequential requests to different physical GPU nodes.
5. **Legacy Model Pricing:** Using `gpt-5.6-luna` instead of `gpt-6-luna` resulted in 3.3x higher base costs with slower generation.

---

## 2. The 3-Tier Production Defense Strategy

```
┌────────────────────────────────────────────────────────────────────────┐
│                        HELMETSAN COST REDUCTION                        │
├───────────────────────────────────┬────────────────────────────────────┤
│   LAYER 1: DETERMINISTIC CODE     │ • Normalize ASINs, units, hashes   │
│   (Zero External AI Cost)         │ • Translation Memory (Disk/RAM)    │
│                                   │ • Source hash deduplication        │
├───────────────────────────────────┼────────────────────────────────────┤
│   LAYER 2: PROMPT CACHING (KV)    │ • Canonical static prefix ≥ 1,024  │
│   (80–93% Discount on Inputs)     │ • prompt_cache_key routing         │
│                                   │ • SwarmPool concurrent calls       │
├───────────────────────────────────┼────────────────────────────────────┤
│   LAYER 3: SPECIALIST ROUTING     │ • DeepSeek V4.1 for bulk jobs      │
│   (Lowest Unit Pricing)           │ • GPT-6-Luna for arbitration only  │
└───────────────────────────────────┴────────────────────────────────────┘
```

1. **Layer 1: Deterministic Deduplication ($0.00 Cost):**
   * Before sending UI strings or product specs to any model, local translation memory (`translation_memory.json` / `/tmp/luna_translated_missing.json`) checks for exact matches. Already-translated strings are returned at 0 tokens and $0.00 cost.
2. **Layer 2: Canonical Prefix Caching (85–93% Input Discount):**
   * For novel items, `CanonicalPromptBuilder` constructs an immutable static prefix containing full motorcycle technical taxonomy, safety certifications (ECE 22.06, DOT FMVSS 218, Snell, FIM), and output schemas.
   * Prefix size is guaranteed **>= 1,150 tokens** (meeting the 1,024-token floor).
   * Requests pass `prompt_cache_key` and `X-Prompt-Cache-Key`.
   * `SwarmPool` executes workers concurrently within seconds, ensuring warm KV cache hits in VRAM.
3. **Layer 3: Model Downgrade & Tier Specialization:**
   * Replaced legacy `gpt-5.6-luna` with `gpt-6-luna` across all scripts (3.3x cheaper, native CoT reasoning, transparent telemetry).
   * High-throughput batch translations route to `deepseek-v4.1-flash` with `gpt-6-luna` as the fallback/arbiter.

---

## 3. Production Code Implementations

### A. Declarative Project Manifest (`ai-mesh.json`)
Located at project root `/Users/anumac/Documents/Projects/Helmetsan/ai-mesh.json`:
* Enforces `gpt-6-luna` as default model.
* Configures `deepseek-v4.1-flash` as high-throughput secondary.
* Enforces `minimumPrefixTokens: 1024` and `defaultTTLSeconds: 300`.
* Sets network timeout to 60,000ms with jittered exponential backoff.

### B. WordPress Plugin Core Provider (`ExperientialProvider.php`)
File: `helmetsan-core/includes/AI/Providers/ExperientialProvider.php`
* Injects `prompt_cache_key` into request payloads and `X-Prompt-Cache-Key` HTTP headers.
* Extracts `usage.prompt_tokens_details.cached_tokens` and computes `cache_hit_rate` (%).
* Implements `postWithTimeout()` with extended 60-second timeouts and automatic retry backoff on 429 / 5xx errors.

### C. Swarm Batch Theme Localization (`batch_translate_with_luna.mjs`)
File: `HelmetsanWeb/scripts/batch_translate_with_luna.mjs`
* Canonical static prefix: **1,248 tokens** (`DETAILED_HELMET_STANDARDS_AND_I18N`).
* Cache key: `helmetsan:theme_i18n:v1.0`.
* Concurrency: `SwarmPool` with concurrency 2–3.
* **Live Telemetry Results:**
  * Cold prefill (Batch 1): 1,276 tokens in, 0 cached (KV cache write).
  * Warm read (Batch 2): 1,271 tokens in, **1,178 cached tokens read (92.7% hit rate)**.
  * Cost reduced from $0.000166 down to $0.000111 per batch.
  * Wall-clock latency dropped from 14.7s down to 9.4s.

### D. Zero Hardcoded Credentials (Universal Vault)
All scripts resolved credentials dynamically from `~/.config/antigravity/ai_mesh.env`:
* `batch_translate_with_luna.mjs`
* `consult_kimi_token_discipline.mjs`
* `test_luna_i18n.mjs`
* `generate_luna_flight_deck_code.mjs`
* `run_full_editorial_pipeline.mjs`
* `xpl_task_runner.py`
* `swarm_translation_bot.py`

---

## 4. Verification & Live Benchmarks

```bash
# Verify central telemetry across all projects
ai-mesh stats
```

**Observed Telemetry:**
* **Prompt Tokens Sent:** 1,271
* **Cached Tokens Read:** 1,178
* **Cache Read Hit Rate:** **92.7% 🟢**
* **Translation Memory Hit Rate:** 100% on repeat strings ($0.00 cost)
