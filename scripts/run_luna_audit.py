#!/usr/bin/env python3
"""
Deep Technical Code Audit via Experiential Labs (gpt-5.6-luna)
Dispatches the actual code diff and implementation files to gpt-5.6-luna.
"""

import os
import sys
import json
import urllib.request
import urllib.error
import subprocess

API_KEY = os.environ.get("EXPLABS_API_KEY", "xpl_347a690bbbf034a8340fcd0cd3ee91b5ec0147e1")
ENDPOINT = "https://api.experientiallabs.ai/v1/chat/completions"
MODEL = "gpt-5.6-luna"

def get_git_diff():
    res = subprocess.run(
        ["git", "diff", "HEAD", "--", "helmetsan-core/", "scripts/local_llm_*.php", "scripts/verification_engine.php"],
        stdout=subprocess.PIPE,
        stderr=subprocess.PIPE,
        text=True,
        cwd="/Users/anumac/Documents/Projects/Helmetsan/HelmetsanWeb"
    )
    return res.stdout

def read_file(rel_path):
    full_path = os.path.join("/Users/anumac/Documents/Projects/Helmetsan/HelmetsanWeb", rel_path)
    if os.path.exists(full_path):
        with open(full_path, "r", encoding="utf-8") as f:
            return f.read()
    return ""

def main():
    print(f"📡 Collecting codebase implementation for {MODEL} audit...")
    
    diff = get_git_diff()
    experiential_provider = read_file("helmetsan-core/includes/AI/Providers/ExperientialProvider.php")
    openai_provider = read_file("helmetsan-core/includes/AI/Providers/OpenAIProvider.php")
    provider_registry = read_file("helmetsan-core/includes/AI/ProviderRegistry.php")
    image_service = read_file("helmetsan-core/includes/AI/ImageAnalysisService.php")
    unit_tests = read_file("tests/Unit/AI/ExperientialGatewayTest.php")

    system_prompt = (
        "You are an elite principal software architect and security auditor specializing in "
        "LLM gateway integrations, high-throughput distributed systems, and modern PHP/WordPress core engineering. "
        "Provide a comprehensive, rigorous, and deep technical audit of the provided implementation. "
        "Evaluate architectural robustness, wire protocol adherence, security/credential safety, "
        "dynamic model switching & budgeting, edge cases, failure recovery, and streaming/tool-calling fidelity. "
        "Be direct, exacting, structured, and actionable. Conclude every section thoroughly."
    )

    user_prompt = f"""
Please perform a deep, comprehensive technical audit of our Experiential Labs gateway integration for model 'gpt-5.6-luna' and dynamic model switching.

### SYSTEM CONTEXT
We integrated the Experiential Labs AI gateway (https://api.experientiallabs.ai/v1) into our enterprise motorcycle gear platform (Helmetsan). The gateway speaks OpenAI Chat Completions API semantics and hosts 300+ models under unified billing.

### IMPLEMENTATION ASSETS UNDER AUDIT

#### 1. ExperientialProvider.php
```php
{experiential_provider}
```

#### 2. OpenAIProvider.php
```php
{openai_provider}
```

#### 3. ProviderRegistry.php
```php
{provider_registry}
```

#### 4. ImageAnalysisService.php
```php
{image_service}
```

#### 5. Automated PHPUnit Test Suite (ExperientialGatewayTest.php)
```php
{unit_tests}
```

#### 6. Core Git Diff
```diff
{diff}
```

### AUDIT REQUIREMENTS
Please deeply examine:
1. **Gateway Wire Protocol & Compatibility**: Does the implementation accurately follow OpenAI Chat Completions wire protocol semantics (payloads, headers, tool_calls, finish_reason, stream flags)?
2. **Credential Safety & Environment Guard**: Does the `EXPLABS_API_KEY` resolution and missing-key guard function safely without leaking keys or throwing fatal unhandled errors during WordPress runtime / bulk discovery?
3. **Model Dynamism & Budget Resilience**: Can any model slug from the 318+ catalog be safely routed, swapped, or budgeted? How does it handle Experiential gateway parameter drops (e.g. `x-experiential-ignored-parameters` like `temperature`) and cost reporting?
4. **Resilience, Concurrency & Fault Tolerance**: How does the error handling, HTTP status code processing, timeout behavior, and curl/wp_remote_post fallback behave under network degradation or 429 quota exhaustion?
5. **Architectural Cohesion & Code Quality**: Grade the design patterns, PSR compliance, decoupling, and WordPress hook/admin integration.
6. **Detailed Verdict & Hardening Recommendations**: Concrete, high-value improvements to make this enterprise-bulletproof.
"""

    payload = {
        "model": MODEL,
        "messages": [
            {"role": "system", "content": system_prompt},
            {"role": "user", "content": user_prompt}
        ],
        "max_tokens": 5500
    }

    print(f"🚀 Dispatching audit payload ({len(user_prompt)} chars) to {ENDPOINT}...")
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
        with urllib.request.urlopen(req, timeout=240) as resp:
            data = json.loads(resp.read().decode("utf-8"))
            
            content = data["choices"][0]["message"]["content"]
            usage = data.get("usage", {})
            raw_meta = {
                "id": data.get("id"),
                "model": data.get("model"),
                "usage": usage,
                "provider": data.get("provider")
            }

            print("\n" + "="*70)
            print(f"🤖 DEEP AUDIT VERDICT FROM {MODEL.upper()}")
            print("="*70 + "\n")
            print(content)
            print("\n" + "="*70)
            print("📊 METRICS & GATEWAY SETTLEMENT:")
            print(json.dumps(raw_meta, indent=2))
            print("="*70)

            # Save audit report locally
            report_path = "/Users/anumac/Documents/Projects/Helmetsan/HelmetsanWeb/docs/EXPERIENTIAL_GATEWAY_LUNA_AUDIT_REPORT.md"
            with open(report_path, "w", encoding="utf-8") as rf:
                rf.write(f"# Experiential Gateway Deep Audit Report\n")
                rf.write(f"**Auditor Model:** `{MODEL}`\n")
                rf.write(f"**Gateway:** `https://api.experientiallabs.ai/v1`\n\n")
                rf.write(f"## Token Usage & Billing\n```json\n{json.dumps(usage, indent=2)}\n```\n\n")
                rf.write(f"## Audit Verdict & Analysis\n\n{content}\n")
            print(f"\n💾 Saved full audit report to: {report_path}")

    except urllib.error.HTTPError as e:
        err_body = e.read().decode("utf-8")
        print(f"❌ HTTP Error {e.code}: {err_body}")
        sys.exit(1)
    except Exception as e:
        print(f"❌ Unexpected error: {e}")
        sys.exit(1)

if __name__ == "__main__":
    main()
