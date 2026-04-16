# Blueprint: High-Performance AI Data Enrichment (Turbo Edition)

This blueprint codifies the architectural patterns and operational guardrails required for performing massive data enrichment (>1,000 records) using local or cloud-based Large Language Models (LLMs).

## 🌍 Core Philosophy: Stability-First
When processing bulk catalogs, **Stability is equal to Speed**. A slow, uninterrupted process is infinitely faster than a high-speed process that crashes the AI cluster or results in data gaps.

---

## ⚡ 1. The "Turbo" Architecture (Concurrency)
To maximize throughput without overwhelming infrastructure, utilize a **Managed Multi-Handle** approach (e.g., PHP `curl_multi`).

### Best Practices:
- **Optimal Concurrency**: Start at 4-way parallel and scale to 10 only after verifying server RAM and response latency.
- **Pacing**: Implement a mandatory `usleep()` (0.5s to 1s) between batches to prevent LLM server saturation.
- **Connection Keep-Alive**: Reuse cURL handles where possible to minimize handshake overhead.

---

## 🔄 2. The "Smart Retry" Pattern (Self-Healing)
AI responses are probabilistic and can fail due to token limits, malformed JSON, or connection timeouts.

### Implementation:
- **Retry Budget**: Implement a maximum of **2 retries** per record.
- **Trigger**: Retry on:
    - HTTP Non-200 codes.
    - JSON parsing failures.
    - Empty response content.
- **Backoff**: Wait 1-2 seconds between retries to allow the LLM server to clear its queue.

---

## 🏗️ 3. State Sovereignty (Flags vs. Content)
Never rely on "Content Completeness" (e.g., checking if `asin` is empty) to determine if a record needs processing.

### The "Tombstone" Flag:
Implement a boolean flag (e.g., `"deep_enriched": true`) once a record has been successfully scanned by the AI.
- **Why?** Some records naturally lack data (e.g., niche helmets without ASINs). Without a flag, the system will attempt to process these "incomplete" records infinitely, wasting tokens and CPU.
- **Safety**: Only set the flag if the AI returns a valid (even if partially empty) schema.

---

## 🔍 4. Audit-Driven Development (The North Star)
Bulk enrichment is invisible until audited. Maintain a dedicated audit tool that calculates **coverage percentages** across the entire catalog.

### Audit Checklist:
- Calculate `%` coverage for every high-value field group (Safety, Identifiers, Tech).
- Log records that failed after all retries for manual inspection.
- Use audits as the "Checkmate" step before production synchronization.

---

## 📦 5. Schema Rigidity
Always validate AI output against a **Strict Schema**.
- Use nested objects (`identifiers`, `safety_intelligence`) to group technical data.
- Ensure the AI understands the **Exact Keys** requested to minimize post-processing re-alignment.

---

> [!NOTE]
> This blueprint was derived from the successful **2,148-helmet enrichment pass** completed in April 2026, which achieved 100% specification coverage and 90%+ fitment coverage using a local Qwen 3.5-9b model.
