#!/usr/bin/env python3
"""
Deep Multi-Phase Exhaustive Technical & Security Audit of Helmetsan Mission Control
Auditor Engine: gpt-5.6-luna (via Experiential Labs AI Gateway)
Executes 3 targeted, deep audit phases and synthesizes a master publication-grade report.
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
    print(f"🚀 Launching {phase_name} ({len(user_prompt)} chars) -> {MODEL}")
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
    print("📡 Gathering Mission Control source files...")
    server_js = read_file(os.path.join(MANAGER_DIR, "server.js"))
    package_json = read_file(os.path.join(MANAGER_DIR, "package.json"))
    app_js = read_file(os.path.join(MANAGER_DIR, "public", "app.js"))
    index_html = read_file(os.path.join(MANAGER_DIR, "public", "index.html"), max_chars=15000)

    # ─────────────────────────────────────────────────────────────────────────
    # PHASE 1: Threat Modeling, Vulnerability Exploitation & Attack Chains
    # ─────────────────────────────────────────────────────────────────────────
    sys_prompt_p1 = (
        "You are an elite Principal Cybersecurity Architect, Threat Modeling Specialist, and Offensive Security Auditor. "
        "You are conducting a deep, relentless vulnerability and exploit analysis of Helmetsan Mission Control (HelmetsanManager). "
        "Analyze the provided server and architecture code with ruthless precision. "
        "Provide full exploit Proof-of-Concepts (PoCs), STRIDE threat modeling, multi-step attack chains (from browser drive-by to root production compromise), "
        "and line-level vulnerability dissections for every endpoint and data flow."
    )

    user_prompt_p1 = f"""
## PHASE 1 AUDIT: THREAT MODELING, EXPLOITATION PROOFS & ATTACK CHAINS

### CONTEXT
Helmetsan Mission Control (`HelmetsanManager/server.js`) is an operations hub running on port 3005 with direct access to local development repos (`HelmetsanWeb`, `HelmetsanMobile`) and SSH access to production (`31.70.136.154`).

### ASSETS UNDER AUDIT
`package.json`:
```json
{package_json}
```

`server.js` (Lines 1 to 550 - Architecture, Endpoints, Actions, SQLite, Cloudflare, SSH):
```javascript
{server_js[:24000]}
```

### REQUIRED DELIVERABLES FOR PHASE 1:
1. **STRIDE Threat Modeling Matrix**:
   - For every component (HTTP API, WebSocket, SQLite Console, SSH Bridge, Cloudflare Purge, File System Reader, Process Spawner), categorize: Spoofing, Tampering, Repudiation, Information Disclosure, Denial of Service, Elevation of Privilege.
2. **End-to-End Attack Chains (Detailed Step-by-Step Scenarios)**:
   - **Attack Chain A: The Drive-By Browser Pivot (CORS + Zero Auth + RCE)**. Walk through how an admin browsing the web on their laptop visits a malicious website `attacker.com`, which triggers cross-origin fetch requests to `http://localhost:3005` to execute arbitrary shell code via `/api/sqlite/query` or `/api/swarm/audit-single`, exfiltrate `/api/vault` credentials, and deploy backdoors to production `31.70.136.154`.
   - **Attack Chain B: Local Network / WiFi Lateral Movement**. How an attacker on the same local network / WiFi reaches port 3005 and leverages the unauthenticated endpoints.
   - **Attack Chain C: Malicious Helmet Data Ingestion to Command Execution**.
3. **Deep Vulnerability Dissection & Concrete Exploitation Proofs (PoC Payloads)**:
   - **MC-001 (Zero Authentication & Interface Exposure)**: Exact mechanics, request/response headers, exploitation impact.
   - **MC-002 (/api/vault Plaintext Secret Streaming)**: JSON payload analysis, credential harvesting.
   - **MC-003 (Hardcoded Production SSH, Cloudflare, and Amazon OAuth Secrets)**: Exposure vectors in git, logs, memory, and runtime.
   - **MC-004 (RCE in /api/swarm/audit-single)**: Shell injection via `safeExec(`python3 "${{pyScript}}" --file "${{id}}"`). Exact curl payload demonstrating command execution (e.g. creating `/tmp/pwned`).
   - **MC-005 (Arbitrary SQL & RCE in /api/sqlite/query)**: Shell escaping flaws in Python inline evaluation and SQL injection / SQLite ATTACH / filesystem corruption. Exact curl payload.
   - **MC-006 (Path Traversal & Arbitrary JSON Read in /api/catalog/:entity/:id)**: Analysis of `path.join(DATA_DIR, entity, \`${{id}}.json\`)` without canonicalization or validation.
   - **MC-007 (Permissive CORS & Intranet CSRF)**: Analysis of `app.use(cors())` enabling arbitrary origin requests with methods POST, GET, OPTIONS.
"""

    p1_content, p1_usage, p1_time = call_luna(sys_prompt_p1, user_prompt_p1, "Phase 1 (Threat Modeling & Exploits)")

    # ─────────────────────────────────────────────────────────────────────────
    # PHASE 2: Subprocesses, Event Loop, PID Hazards & Concurrency Architecture
    # ─────────────────────────────────────────────────────────────────────────
    sys_prompt_p2 = (
        "You are an elite Principal Node.js Systems Engineer and Distributed Systems Architect. "
        "You are conducting a deep runtime, concurrency, event loop, and process lifecycle audit of Helmetsan Mission Control. "
        "Evaluate blocking synchronous I/O, child process trees, signal propagation, PID file races, and WebSocket streaming fidelity."
    )

    user_prompt_p2 = f"""
## PHASE 2 AUDIT: CONCURRENCY, PROCESS LIFECYCLES, EVENT LOOP & WEBSOCKET ENGINE

### ASSETS UNDER AUDIT
`server.js` (Lines 500 to 1093 - Vault, Swarm, Translation, Metal Bot, WebSocket, Ingest Tail):
```javascript
{server_js[22000:]}
```

### REQUIRED DELIVERABLES FOR PHASE 2:
1. **Event-Loop Starvation & Latency Impact Analysis**:
   - Audit all 11 instances of `safeExec()` (`execSync`). Calculate blocking duration on the single Node.js thread during SSH calls, curl network probes, git status, and python script execution.
   - What happens to HTTP connection queuing, WebSocket pings/pongs, and active client streams when `safeExec` runs an 8-second SSH command?
2. **Subprocess Lifecycle & Process Tree Leaks**:
   - In-depth review of `metroProcess`, `remoteTailProcess`, `translationActiveProcess`, and swarm subprocesses.
   - How `child.kill('SIGTERM')` fails to kill child process trees (e.g. Metro bundler spawning Node/Watchman, Python spawning worker pools, SSH spawning remote tail).
   - Analysis of orphaned background processes, port exhaustion, and resource leaks.
3. **PID File Race Conditions & Stale Process Hijacking**:
   - Deep critique of `getBotPid()` and `METAL_BOT_PID`.
   - What happens if the OS recycles a PID after a crash, and `/api/translation/bot/stop` sends SIGTERM/SIGKILL to an unrelated process?
   - Propose an atomic, verifiable PID/supervisor mechanism with process group tracking.
4. **WebSocket Architecture, Memory Leaks & Channel Isolation**:
   - Evaluate `broadcastLog()` and connection pooling (`wss.clients.forEach`).
   - Audit memory leaks: what happens to `translationLogsBuffer` under sustained streaming?
   - Backpressure and slow consumers: what happens if a WebSocket client on high latency stalls while a process emits 1,000 lines/second?
   - Channel isolation: why the current `channel` filtering allows sensitive system logs to leak into public translation streams.
5. **Data Layer I/O & File System Bottlenecks**:
   - Synchronous filesystem scanning in `countJsonFiles()` and `readJson()` across 5,415 helmets, 3,249 bikes, accessories, and brands on every `/api/catalog/summary` and `/api/catalog/health/audit` hit.
   - Performance profiling under concurrent user load.
"""

    p2_content, p2_usage, p2_time = call_luna(sys_prompt_p2, user_prompt_p2, "Phase 2 (Concurrency & Runtime Engine)")

    # ─────────────────────────────────────────────────────────────────────────
    # PHASE 3: Frontend DOM Security & Complete Hardened Production Codebase
    # ─────────────────────────────────────────────────────────────────────────
    sys_prompt_p3 = (
        "You are an elite Full-Stack Security Architect and Lead Staff Software Engineer. "
        "You are conducting a thorough client-side DOM security audit of Helmetsan Mission Control (app.js & index.html), "
        "and authoring the definitive, complete, drop-in production-grade refactored code to eliminate ALL vulnerabilities found in Phases 1 & 2."
    )

    user_prompt_p3 = f"""
## PHASE 3 AUDIT: FRONTEND DOM AUDIT & DEFINITIVE PRODUCTION-GRADE REFACTORED CODEBASE

### ASSETS UNDER AUDIT
`public/index.html`:
```html
{index_html}
```

`public/app.js` (First 15,000 chars):
```javascript
{app_js[:15000]}
```

`public/app.js` (Middle & Tail 15,000 chars):
```javascript
{app_js[15000:30000]}
```

### REQUIRED DELIVERABLES FOR PHASE 3:
1. **Client-Side DOM Security & XSS Sink Audit**:
   - Audit all instances of `innerHTML`, `outerHTML`, and unescaped DOM insertion across `app.js`.
   - Examine `addTerminalLine()`: does `innerHTML += html` with `data-text` create an XSS sink if terminal output contains control sequences or raw quotes?
   - Audit catalog card rendering, AI comparison table generation, and query console output.
   - WebSocket auto-reconnect storm behavior: `ws.onclose = () => setTimeout(connectWS, 2000)` without backoff or jitter.
2. **Definitive Production-Hardened Refactored `server.js`**:
   - Provide the complete, drop-in production replacement for `server.js` incorporating:
     - Mandatory Bearer Token authentication (`Authorization: Bearer <token>`) with timing-safe comparison.
     - Strict loopback binding (`127.0.0.1`).
     - Restricted CORS (only `http://localhost:3005` or strict Origin validation).
     - Asynchronous, non-blocking process execution (`execFileAsync` / `safeExecFile`) with explicit argument arrays (ZERO shell string interpolation).
     - Parameterized, safe SQLite runner with strict query validation.
     - Strict path normalization and allowlist validation for catalog entities and IDs (preventing path traversal).
     - Complete removal of `/api/vault` plaintext disclosure (replaced with secure configuration status).
     - Externalized secrets via environment variables (`process.env`).
     - Process group termination (`terminateProcessTree`) for Metro, Python bots, and SSH.
     - Authenticated WebSocket upgrade with backpressure buffering and channel authorization.
3. **Hardened Client Integration (`app.js` updates)**:
   - Updated `connectWS` with Bearer token authentication and exponential backoff.
   - Safe DOM rendering utilizing `textContent` and `createElement` instead of vulnerable `innerHTML` concatenation.
"""

    p3_content, p3_usage, p3_time = call_luna(sys_prompt_p3, user_prompt_p3, "Phase 3 (Frontend DOM & Hardened Implementation)")

    # ─────────────────────────────────────────────────────────────────────────
    # MASTER SYNTHESIS: Compile Unified Comprehensive Report
    # ─────────────────────────────────────────────────────────────────────────
    print("\n📝 Compiling Master Comprehensive Audit Document...")
    report_path = os.path.join(WEB_DIR, "docs", "HELMETSAN_MISSION_CONTROL_LUNA_AUDIT_REPORT.md")

    total_tokens = p1_usage.get("total_tokens", 0) + p2_usage.get("total_tokens", 0) + p3_usage.get("total_tokens", 0)
    total_elapsed = p1_time + p2_time + p3_time

    with open(report_path, "w", encoding="utf-8") as rf:
        rf.write(f"# Helmetsan Mission Control (HelmetsanManager) — Master Security, Concurrency & Architectural Audit\n\n")
        rf.write(f"**Auditor Engine:** `{MODEL}` (Experiential Labs AI Gateway)\n")
        rf.write(f"**Audit Execution Date:** {time.strftime('%Y-%m-%d %H:%M:%S')}\n")
        rf.write(f"**Target System:** `HelmetsanManager` (Unified Mission Control & Operations Dashboard)\n")
        rf.write(f"**Total Audit Tokens:** {total_tokens:,} tokens across 3 exhaustive phases\n")
        rf.write(f"**Cumulative Inference Time:** {total_elapsed:.2f} seconds\n\n")

        rf.write("## Phase Execution Telemetry\n\n")
        rf.write(f"| Phase | Focus Domain | Duration | Completion Tokens | Total Tokens |\n")
        rf.write(f"|---|---|---:|---:|---:|\n")
        rf.write(f"| **Phase 1** | Threat Modeling, Exploitation Proofs & Attack Chains | {p1_time:.1f}s | {p1_usage.get('completion_tokens'):,} | {p1_usage.get('total_tokens'):,} |\n")
        rf.write(f"| **Phase 2** | Concurrency, Event-Loop Starvation & Subprocess Engine | {p2_time:.1f}s | {p2_usage.get('completion_tokens'):,} | {p2_usage.get('total_tokens'):,} |\n")
        rf.write(f"| **Phase 3** | Frontend DOM Sinks & Complete Hardened Implementation | {p3_time:.1f}s | {p3_usage.get('completion_tokens'):,} | {p3_usage.get('total_tokens'):,} |\n")
        rf.write(f"| **TOTAL** | **Comprehensive Master Suite** | **{total_elapsed:.1f}s** | **{p1_usage.get('completion_tokens',0)+p2_usage.get('completion_tokens',0)+p3_usage.get('completion_tokens',0):,}** | **{total_tokens:,}** |\n\n")
        rf.write("---\n\n")

        rf.write("# PART I: THREAT MODELING, VULNERABILITY REGISTER & EXPLOIT PROOFS\n\n")
        rf.write(p1_content + "\n\n")
        rf.write("---\n\n")

        rf.write("# PART II: CONCURRENCY, EVENT-LOOP STARVATION, PROCESS LIFECYCLES & WEBSOCKET ENGINE\n\n")
        rf.write(p2_content + "\n\n")
        rf.write("---\n\n")

        rf.write("# PART III: FRONTEND DOM AUDIT & DEFINITIVE PRODUCTION-GRADE REFACTORED CODEBASE\n\n")
        rf.write(p3_content + "\n\n")

    print(f"🎉 Exhaustive Master Audit Report written to: {report_path}")
    print(f"📄 File size: {os.path.getsize(report_path):,} bytes")

if __name__ == "__main__":
    main()
