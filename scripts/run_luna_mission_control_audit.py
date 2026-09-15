#!/usr/bin/env python3
"""
Exhaustive Technical & Security Audit of Helmetsan Mission Control (HelmetsanManager)
Dispatched to gpt-5.6-luna via Experiential Labs AI Gateway.
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

def main():
    print(f"📡 Gathering Helmetsan Mission Control (HelmetsanManager) codebase for {MODEL} audit...")

    server_js = read_file(os.path.join(MANAGER_DIR, "server.js"))
    package_json = read_file(os.path.join(MANAGER_DIR, "package.json"))
    index_html = read_file(os.path.join(MANAGER_DIR, "public", "index.html"), max_chars=12000)
    app_js = read_file(os.path.join(MANAGER_DIR, "public", "app.js"), max_chars=25000)

    print(f"📦 Files collected:")
    print(f"   - server.js: {len(server_js)} chars")
    print(f"   - package.json: {len(package_json)} chars")
    print(f"   - index.html: {len(index_html)} chars")
    print(f"   - app.js: {len(app_js)} chars")

    system_prompt = (
        "You are an elite Principal Systems Architect, Lead Cybersecurity Auditor, and Enterprise Node.js / Operations Infrastructure Specialist. "
        "You are tasked with conducting an exhaustive, uncompromising, and deeply structured technical and security audit of "
        "'Helmetsan Mission Control' (HelmetsanManager) — an internal operations dashboard, deployment orchestrator, and AI compute grid controller "
        "for the Helmetsan motorcycle gear platform.\n\n"
        "Provide an in-depth, rigorous, line-level code evaluation. "
        "Highlight all critical vulnerabilities (RCE, path traversal, credential exposure, CSRF, DOM XSS), "
        "architectural hazards (blocking execSync on the event loop, process lifecycle bugs, memory leaks), "
        "and concurrency/reliability flaws. "
        "Be direct, uncompromising, structured, and deliver concrete code remediation diffs for every issue identified."
    )

    user_prompt = f"""
Please perform an exhaustive, deep technical and security audit of Helmetsan Mission Control (`HelmetsanManager`).

### ARCHITECTURE & SYSTEM OVERVIEW
Helmetsan Mission Control is an Express + WebSocket operations hub running locally (default: port 3005) with direct execution bridges into:
1. `HelmetsanWeb` (WordPress theme/plugin, local LM Studio scripts, Python AI swarm nodes, data JSON repository).
2. `HelmetsanMobile` (React Native / Expo app, SQLite compilation scripts).
3. Production server at `31.70.136.154` via SSH, WP-CLI, and Cloudflare Edge API.

---

### SOURCE CODE ASSETS UNDER AUDIT

#### 1. HelmetsanManager/package.json
```json
{package_json}
```

#### 2. HelmetsanManager/server.js (COMPLETE FULL SOURCE)
```javascript
{server_js}
```

#### 3. HelmetsanManager/public/app.js (CLIENT ARCHITECTURE & LOGIC)
```javascript
{app_js}
```

#### 4. HelmetsanManager/public/index.html (UI STRUCTURE & CONTROLS)
```html
{index_html}
```

---

### AUDIT INSTRUCTIONS & REQUIRED SECTIONS

Please evaluate thoroughly across the following dimensions and format your findings into a comprehensive master report:

1. **Executive Summary & Production Readiness Score**:
   - Overall architecture grade (A-F), security score (0-100), and operational risk tier.
   - Primary design strengths and critical vulnerabilities at a glance.

2. **Critical Vulnerabilities & Security Assessment (CVE-Grade)**:
   - **Remote Code Execution (RCE) Vectors**: Analyze every `exec`, `execSync`, and `spawn` call with untrusted input (e.g. `/api/sqlite/query`, `/api/swarm/audit-single`, `/api/catalog/drift`, `/api/action/deploy-web`).
   - **Path Traversal & Arbitrary File Access**: Inspect `/api/catalog/:entity/:id`, file readers, and data paths.
   - **Plaintext Credentials & Information Exposure**: Evaluate `/api/vault`, hardcoded SSH passwords, Cloudflare API tokens, and Amazon OAuth client secrets.
   - **Lack of Authentication, Authorization & CSRF Exposure**: Evaluate the risk of `cors()` with no origin restrictions, lack of auth tokens/cookies, and DNS rebinding / drive-by intranet exploitation.

3. **Concurrency, Process Lifecycle & Event-Loop Architecture**:
   - Evaluate `execSync` / `safeExec` blocking the Node.js single-threaded event loop for multi-second system calls.
   - Evaluate background processes (`metroProcess`, `remoteTailProcess`, `translationActiveProcess`) and PID file management (`getBotPid`, orphan handling, signal propagation).
   - Evaluate WebSocket connection lifecycle, memory leaks, client disconnection handling, and log buffer bounds.

4. **Data Layer, I/O Scalability & Catalog Performance**:
   - Performance implications of `countJsonFiles`, `readJson`, and synchronous file scanning over 5,400+ helmets and 3,200+ bikes on every request.
   - Cache invalidation and drift detection.

5. **Client-Side Security & Frontend Engineering (`app.js` / `index.html`)**:
   - DOM XSS risks in terminal rendering (`innerHTML` vs `escapeHtml` consistency), table generation, and data studio rendering.
   - WebSocket auto-reconnect behavior and error resilience.

6. **Exhaustive Remediation Plan & Hardening Blueprint**:
   - Concrete, drop-in replacement code snippets for `server.js` and `app.js` to eliminate all RCEs, secure the vault, sanitize inputs, decouple blocking I/O, and secure WebSocket.
   - Phased action checklist (P0 Critical, P1 High, P2 Moderate).
"""

    payload = {
        "model": MODEL,
        "messages": [
            {"role": "system", "content": system_prompt},
            {"role": "user", "content": user_prompt}
        ],
        "max_tokens": 7000
    }

    print(f"🚀 Dispatching audit request to {ENDPOINT} ({len(user_prompt)} chars)...")
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
        with urllib.request.urlopen(req, timeout=300) as resp:
            elapsed = time.time() - start_time
            data = json.loads(resp.read().decode("utf-8"))

            content = data["choices"][0]["message"]["content"]
            usage = data.get("usage", {})
            finish_reason = data["choices"][0].get("finish_reason")
            raw_meta = {
                "id": data.get("id"),
                "model": data.get("model"),
                "elapsed_seconds": round(elapsed, 2),
                "finish_reason": finish_reason,
                "usage": usage,
                "provider": data.get("provider")
            }

            print("\n" + "="*75)
            print(f"🤖 HELMETSAN MISSION CONTROL AUDIT VERDICT — {MODEL.upper()}")
            print("="*75 + "\n")
            print(content)
            print("\n" + "="*75)
            print("📊 AUDIT TELEMETRY & GATEWAY USAGE:")
            print(json.dumps(raw_meta, indent=2))
            print("="*75)

            # Save full report
            report_path = os.path.join(WEB_DIR, "docs", "HELMETSAN_MISSION_CONTROL_LUNA_AUDIT_REPORT.md")
            with open(report_path, "w", encoding="utf-8") as rf:
                rf.write(f"# Helmetsan Mission Control (HelmetsanManager) — Technical & Security Audit\n\n")
                rf.write(f"**Auditor Engine:** `{MODEL}` (Experiential Labs AI Gateway)\n")
                rf.write(f"**Audit Date:** {time.strftime('%Y-%m-%d %H:%M:%S')}\n")
                rf.write(f"**Target System:** `HelmetsanManager` (Unified Mission Control & Operations Dashboard)\n\n")
                rf.write(f"## Telemetry & Token Accounting\n```json\n{json.dumps(raw_meta, indent=2)}\n```\n\n")
                rf.write(f"---\n\n## Deep Audit Report\n\n{content}\n")

            print(f"\n💾 Saved exhaustive audit report to: {report_path}")

    except urllib.error.HTTPError as e:
        err_body = e.read().decode("utf-8")
        print(f"❌ HTTP Error {e.code}: {err_body}")
        sys.exit(1)
    except Exception as e:
        print(f"❌ Unexpected error during audit: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()
