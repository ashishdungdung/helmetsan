#!/usr/bin/env python3
"""
Deep Functional & Operational Workflow Audit of Helmetsan Mission Control
Auditor Engine: gpt-5.6-luna (via Experiential Labs AI Gateway)
Dispatches full server, client, and operational integration scripts to evaluate
workflow fidelity, edge cases, pipeline integrity, cluster orchestration, and operator UX.
"""

import os
import sys
import json
import urllib.request
import urllib.error
import time

API_KEY = os.environ.get("EXPLABS_API_KEY", "xpl_347a690bbbf034a8340fcd0cd3ee91b5ec0147e1")
ENDPOINT = "https://api.experientiallabs.ai/v1/chat/completions"
MODEL = "gpt-5.6-luna"

BASE_DIR = "/Users/anumac/Documents/Projects/Helmetsan"
MANAGER_DIR = os.path.join(BASE_DIR, "HelmetsanManager")
WEB_DIR = os.path.join(BASE_DIR, "HelmetsanWeb")

def read_file(path, max_chars=None):
    if os.path.exists(path):
        with open(path, "r", encoding="utf-8", errors="ignore") as f:
            content = f.read()
            if max_chars and len(content) > max_chars:
                return content[:max_chars] + f"\n... [TRUNCATED: remaining {len(content) - max_chars} chars]"
            return content
    return f"[FILE NOT FOUND: {path}]"

def call_luna(system_prompt, user_prompt, phase_name, max_tokens=7500):
    print(f"\n{'='*75}")
    print(f"🚀 Dispatching {phase_name} ({len(user_prompt)} chars) -> {MODEL}")
    print(f"{'='*75}")

    payload = {
        "model": MODEL,
        "messages": [
            {"role": "system", "content": system_prompt},
            {"role": "user", "content": user_prompt}
        ],
        "max_tokens": max_tokens
    }

    start_time = time.time()
    req = urllib.request.Request(
        ENDPOINT,
        data=json.dumps(payload).encode("utf-8"),
        headers={
            "Authorization": f"Bearer {API_KEY}",
            "Content-Type": "application/json"
        },
        method="POST"
    )

    try:
        with urllib.request.urlopen(req, timeout=360) as resp:
            elapsed = time.time() - start_time
            data = json.loads(resp.read().decode("utf-8"))
            content = data["choices"][0]["message"]["content"]
            usage = data.get("usage", {})
            finish_reason = data["choices"][0].get("finish_reason")
            print(f"✅ {phase_name} completed in {elapsed:.2f}s | Tokens: {usage.get('total_tokens')} (Finish: {finish_reason})")
            return content, usage, elapsed
    except urllib.error.HTTPError as e:
        err_body = e.read().decode("utf-8")
        print(f"❌ HTTP Error {e.code} in {phase_name}: {err_body}")
        raise
    except Exception as e:
        print(f"❌ Error in {phase_name}: {e}")
        raise

def main():
    print("📡 Gathering Mission Control functional architecture...")
    server_js = read_file(os.path.join(MANAGER_DIR, "server.js"))
    app_js = read_file(os.path.join(MANAGER_DIR, "public", "app.js"))
    index_html = read_file(os.path.join(MANAGER_DIR, "public", "index.html"), max_chars=18000)

    # Key integration scripts
    deploy_sh = read_file(os.path.join(WEB_DIR, "deploy.sh"), max_chars=4000)
    check_drift = read_file(os.path.join(WEB_DIR, "scripts", "check_data_drift.php"), max_chars=4000)
    google_cli = read_file(os.path.join(WEB_DIR, "scripts", "google_intelligence_cli.php"), max_chars=4000)

    # ─────────────────────────────────────────────────────────────────────────
    # PART 1: Core Operations, Catalog Pipeline & Data Workflows
    # ─────────────────────────────────────────────────────────────────────────
    sys_prompt_wf1 = (
        "You are an elite Principal Operations Architect, Enterprise DevOps Lead, and Data Pipeline Specialist. "
        "You are conducting an exhaustive, uncompromising functional and operational workflow audit of Helmetsan Mission Control. "
        "Audit the catalog pipelines, deployment workflows, remote synchronization, and data consistency mechanisms. "
        "Critique edge cases, failure recovery, asynchronous user feedback, state synchronization bugs, and pipeline bottlenecks."
    )

    user_prompt_wf1 = f"""
## FUNCTIONAL AUDIT PART 1: CATALOG PIPELINE, DEPLOYMENT & SERVER OPS WORKFLOWS

### ARCHITECTURAL CONTEXT
Helmetsan Mission Control manages a multi-tier catalog (2,219 helmets, 3,247 motorcycles, accessories, brands, standards) across three datastores:
1. **Master JSON Repository** (`HelmetsanWeb/data/`)
2. **Production WordPress Database** (Polylang multilingual MySQL at `31.70.136.154`)
3. **Offline-First SQLite Database** (`HelmetsanMobile/assets/database/catalog.db`)

### ASSETS UNDER AUDIT
`server.js` (Core Server & Catalog/Action Routes):
```javascript
{server_js[:25000]}
```

`deploy.sh`:
```bash
{deploy_sh}
```

`check_data_drift.php`:
```php
{check_drift}
```

### REQUIRED DELIVERABLES FOR PART 1:
1. **Catalog Pipeline & Cross-Linking Functional Audit**:
   - Analyze `/api/catalog/:entity`, filtering, pagination, and `/api/catalog/:entity/:id`.
   - Critique the cross-linking heuristic between helmets, accessories, and motorcycles (lines 290-330): Is slicing 80 bike files and matching on `item.type` efficient and accurate? What happens with memory and file descriptors?
   - Evaluate the ground-truth vs LLM validation status resolution (`getAuditStatus`). Are there race conditions or stale reads?
2. **Data Drift & Recompilation Workflow Audit**:
   - Evaluate the Data Drift Sentinel (`/api/catalog/drift` -> `check_data_drift.php`). Does it catch missing fields, schema mismatches, and variant regressions?
   - Evaluate the SQLite database compilation workflow (`/api/action/recompile-db` -> `export-mobile-db.py`). How does the UI track completion? What happens if it fails midway?
3. **Web & Server Operations Workflow Audit**:
   - Evaluate 1-click deployment (`/api/action/deploy-web` -> `deploy.sh`). How does it handle `--theme-only` vs `--plugin-only`? What happens on SSH failure or syntax errors?
   - Evaluate the Cloudflare edge cache purge workflow. Does it purge all multilingual edge paths (`/`, `/de/`, `/zh/`, `/es/`)?
   - Evaluate remote log streaming (`/api/action/tail-remote-ingest`). How does it handle broken SSH pipes, network drops, or server reboots?
"""

    wf1_content, wf1_usage, wf1_time = call_luna(sys_prompt_wf1, user_prompt_wf1, "Part 1 (Catalog & Server Ops Workflows)")

    # ─────────────────────────────────────────────────────────────────────────
    # PART 2: Distributed Compute, Translation Bot & AI Workflows
    # ─────────────────────────────────────────────────────────────────────────
    sys_prompt_wf2 = (
        "You are an elite Principal AI Systems Architect and Distributed Compute Grid Engineer. "
        "You are conducting an exhaustive functional audit of Helmetsan Mission Control's distributed AI workflows: "
        "the Apple Silicon Metal Translation Bot, the Silicon Compute Swarm (Node A/Node B), and live telemetry pipelines."
    )

    user_prompt_wf2 = f"""
## FUNCTIONAL AUDIT PART 2: AI SWARM, TRANSLATION BOT & LIVE TELEMETRY WORKFLOWS

### ASSETS UNDER AUDIT
`server.js` (Swarm, Translation Bot, Google Intelligence & Vault Routes):
```javascript
{server_js[23000:]}
```

`google_intelligence_cli.php`:
```php
{google_cli}
```

### REQUIRED DELIVERABLES FOR PART 2:
1. **Multilingual Catalog & Metal Translation Bot Workflow Audit**:
   - Audit the complete lifecycle of the translation bot:
     - Starting batch (`--count N`) vs Daemon mode (`--daemon`).
     - Single helmet translation (`--post-id`).
     - Stopping the bot (`--stop` + signal escalation).
   - Evaluate the Polylang bidirectional sync audit (`audit_bidirectionality.php`) and cluster health reporting (`fetchLiveTranslationStats`). How is cache invalidation handled?
   - Live log buffer and WebSocket channel isolation (`channel: 'translation'`). Does the operator get real-time feedback on token savings and translation speed?
2. **Silicon Compute Swarm Grid Workflow Audit (Node A / Node B)**:
   - Audit the 3-stage hybrid swarm workflow: Vector Cluster -> Work-Stealing Queue -> Prefix KV Cache.
   - Evaluate node health probing (`--test-nodes` against Node A @ 127.0.0.1:1234 and Node B @ 192.168.2.223:1235). What happens if Node B is powered off or drops packets?
   - Evaluate `/api/swarm/advanced-metrics` and live queue monitoring (port 9090). Is there automatic failover?
3. **Google Live Intelligence & Monetization Workflows**:
   - Audit the GA4 & Search Console telemetry pipeline (`/api/google/intelligence`). Evaluate the dual execution strategy (Local PHP CLI runner -> remote SSH fallback) and caching (120s TTL).
   - How does the traffic anomaly sentinel detect surges or drops?
   - Evaluate the 21-country Amazon affiliate link generator and Creator API OAuth token inspection.
"""

    wf2_content, wf2_usage, wf2_time = call_luna(sys_prompt_wf2, user_prompt_wf2, "Part 2 (AI Swarm & Translation Workflows)")

    # ─────────────────────────────────────────────────────────────────────────
    # PART 3: Operator UX, State Machine, Frontend Workflow & Flight Deck Roadmap
    # ─────────────────────────────────────────────────────────────────────────
    sys_prompt_wf3 = (
        "You are an elite Principal Product Architect, Flight Deck UX Specialist, and Frontend Systems Engineer. "
        "You are conducting a thorough functional audit of the Mission Control user experience, client-side state machine, "
        "tab switching, terminal feedback loops, and drafting the definitive Functional Enhancement Roadmap."
    )

    user_prompt_wf3 = f"""
## FUNCTIONAL AUDIT PART 3: OPERATOR UX, STATE MACHINE & FLIGHT DECK ENHANCEMENT ROADMAP

### ASSETS UNDER AUDIT
`public/index.html`:
```html
{index_html}
```

`public/app.js` (Excerpts):
```javascript
{app_js[:25000]}
```

### REQUIRED DELIVERABLES FOR PART 3:
1. **Operator Experience & State Machine Audit**:
   - Evaluate tab switching (`switchTab`) and data loading triggers. Are there redundant API requests or race conditions when switching tabs rapidly?
   - Evaluate the terminal log viewer (`addTerminalLine`, filter, copy, auto-scroll). Is the 300-line buffer appropriate for high-throughput batch runs?
   - Evaluate the Catalog Inspector panel: Does it provide complete situational awareness (specs, variants, pricing across currencies, linked accessories, compatible bikes)?
2. **Failure Modes & Error Visibility in the UI**:
   - When a deployment fails, does the operator see clear actionable error diagnostics or a silent hang?
   - When an SSH command times out, how does the UI gracefully inform the user?
3. **Definitive Functional Enhancement Roadmap (The Flight Deck Doctrine)**:
   - What high-impact operational tools are missing? (e.g., Live VRAM / GPU thermal telemetry for Node A, 1-Click Rollback for deployments, Database Drift Auto-Healer, Multi-Language Coverage Heatmap).
   - Priority 1: Immediate Workflow Improvements.
   - Priority 2: Automated Grid Resilience & Failover.
   - Priority 3: Advanced Business & Catalog Telemetry.
"""

    wf3_content, wf3_usage, wf3_time = call_luna(sys_prompt_wf3, user_prompt_wf3, "Part 3 (Operator UX & Flight Deck Roadmap)")

    # ─────────────────────────────────────────────────────────────────────────
    # SYNTHESIS: Compile Master Functional Workflow Report
    # ─────────────────────────────────────────────────────────────────────────
    print("\n📝 Compiling Master Functional Workflow Audit Document...")
    report_path = os.path.join(WEB_DIR, "docs", "HELMETSAN_MISSION_CONTROL_FUNCTIONAL_WORKFLOW_AUDIT.md")

    total_tokens = wf1_usage.get("total_tokens", 0) + wf2_usage.get("total_tokens", 0) + wf3_usage.get("total_tokens", 0)
    total_elapsed = wf1_time + wf2_time + wf3_time

    with open(report_path, "w", encoding="utf-8") as rf:
        rf.write(f"# Helmetsan Mission Control (HelmetsanManager) — Master Functional & Operational Workflow Audit\n\n")
        rf.write(f"**Auditor Engine:** `{MODEL}` (Experiential Labs AI Gateway)\n")
        rf.write(f"**Audit Execution Date:** {time.strftime('%Y-%m-%d %H:%M:%S')}\n")
        rf.write(f"**Target System:** `HelmetsanManager` (Unified Mission Control & Operations Dashboard)\n")
        rf.write(f"**Total Audit Tokens:** {total_tokens:,} tokens across 3 exhaustive operational workflow domains\n")
        rf.write(f"**Cumulative Inference Time:** {total_elapsed:.2f} seconds\n\n")

        rf.write("## Operational Workflow Telemetry\n\n")
        rf.write(f"| Workflow Domain | Focus Areas | Duration | Completion Tokens | Total Tokens |\n")
        rf.write(f"|---|---|---:|---:|---:|\n")
        rf.write(f"| **Domain 1** | Catalog Pipeline, Drift Sentinel & Deployment Ops | {wf1_time:.1f}s | {wf1_usage.get('completion_tokens'):,} | {wf1_usage.get('total_tokens'):,} |\n")
        rf.write(f"| **Domain 2** | Silicon Swarm Grid, Metal Bot & Live Telemetry | {wf2_time:.1f}s | {wf2_usage.get('completion_tokens'):,} | {wf2_usage.get('total_tokens'):,} |\n")
        rf.write(f"| **Domain 3** | Operator Flight Deck UX, Error Visibility & Roadmap | {wf3_time:.1f}s | {wf3_usage.get('completion_tokens'):,} | {wf3_usage.get('total_tokens'):,} |\n")
        rf.write(f"| **TOTAL** | **Full-Spectrum Workflow Audit** | **{total_elapsed:.1f}s** | **{wf1_usage.get('completion_tokens',0)+wf2_usage.get('completion_tokens',0)+wf3_usage.get('completion_tokens',0):,}** | **{total_tokens:,}** |\n\n")
        rf.write("---\n\n")

        rf.write("# PART I: CATALOG PIPELINE, DEPLOYMENT OPS & DATA INTEGRITY WORKFLOWS\n\n")
        rf.write(wf1_content + "\n\n")
        rf.write("---\n\n")

        rf.write("# PART II: SILICON COMPUTE SWARM, METAL BOT & LIVE TELEMETRY WORKFLOWS\n\n")
        rf.write(wf2_content + "\n\n")
        rf.write("---\n\n")

        rf.write("# PART III: OPERATOR FLIGHT DECK UX, RESILIENCE & FUNCTIONAL ENHANCEMENT ROADMAP\n\n")
        rf.write(wf3_content + "\n\n")

    print(f"🎉 Master Functional & Workflow Audit written to: {report_path}")
    print(f"📄 File size: {os.path.getsize(report_path):,} bytes")

if __name__ == "__main__":
    main()
