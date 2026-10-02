#!/usr/bin/env python3
"""
Experiential Labs Headless Task Runner & Offloader
--------------------------------------------------
Offloads heavy LLM workloads (code refactoring, content generation, audits, 
translations, and enrichments) directly to the Experiential Labs Gateway,
preserving IDE AI tokens and utilizing Experiential credits.

Supported Models:
- gpt-5.6-luna (Deep reasoning, architectural audits, complex code generation)
- qwen3.8-27b (High throughput, fast batch content, translations)
- deepseek-v4-flash (Fast lightweight enrichment)
"""

import os
import sys
import json
import argparse
import urllib.request
import urllib.error
import time

DEFAULT_BASE_URL = "https://api.experientiallabs.ai/v1"
DEFAULT_MODEL = "gpt-6-luna"

def get_api_key():
    key = os.environ.get("EXPLABS_API_KEY", "").strip()
    if not key:
        vault_path = os.path.expanduser("~/.config/antigravity/ai_mesh.env")
        if os.path.exists(vault_path):
            with open(vault_path, "r", encoding="utf-8") as f:
                for line in f:
                    trimmed = line.strip()
                    if trimmed.startswith("EXPLABS_API_KEY="):
                        key = trimmed[len("EXPLABS_API_KEY="):].strip()
                        break
    if not key:
        print("❌ Error: EXPLABS_API_KEY environment variable is not set in environment or ~/.config/antigravity/ai_mesh.env.")
        sys.exit(1)
    return key

def dispatch_completion(prompt, model=DEFAULT_MODEL, system_prompt=None, max_tokens=4000, temperature=0.7, json_mode=False, prompt_cache_key=None):
    api_key = get_api_key()
    endpoint = f"{DEFAULT_BASE_URL}/chat/completions"

    messages = []
    if system_prompt:
        messages.append({"role": "system", "content": system_prompt})
    messages.append({"role": "user", "content": prompt})

    payload = {
        "model": model,
        "messages": messages,
        "max_tokens": max_tokens
    }

    if prompt_cache_key:
        payload["prompt_cache_key"] = prompt_cache_key

    # Experiential Labs reasoning models (like gpt-6-luna) drop temperature
    if "luna" not in model.lower():
        payload["temperature"] = temperature

    if json_mode:
        payload["response_format"] = {"type": "json_object"}

    headers = {
        "Authorization": f"Bearer {api_key}",
        "Content-Type": "application/json"
    }
    if prompt_cache_key:
        headers["X-Prompt-Cache-Key"] = prompt_cache_key

    data_bytes = json.dumps(payload).encode("utf-8")
    req = urllib.request.Request(
        endpoint,
        data=data_bytes,
        headers=headers,
        method="POST"
    )

    start_time = time.time()
    try:
        with urllib.request.urlopen(req, timeout=240) as resp:
            elapsed = time.time() - start_time
            ignored_params = resp.headers.get("x-experiential-ignored-parameters")
            raw_body = resp.read().decode("utf-8")
            data = json.loads(raw_body)

            choice = data["choices"][0] if "choices" in data and len(data["choices"]) > 0 else {}
            content = choice.get("message", {}).get("content", "")
            finish_reason = choice.get("finish_reason")
            usage = data.get("usage", {})

            return {
                "success": True,
                "content": content,
                "model": data.get("model", model),
                "finish_reason": finish_reason,
                "usage": usage,
                "elapsed_seconds": round(elapsed, 2),
                "ignored_params": ignored_params
            }

    except urllib.error.HTTPError as e:
        err_body = e.read().decode("utf-8")
        return {
            "success": False,
            "status_code": e.code,
            "error": err_body,
            "elapsed_seconds": round(time.time() - start_time, 2)
        }
    except Exception as e:
        return {
            "success": False,
            "error": str(e),
            "elapsed_seconds": round(time.time() - start_time, 2)
        }

def handle_ping(args):
    print(f"📡 Pinging Experiential Labs Gateway with model '{args.model}'...")
    res = dispatch_completion("Return a 15-word confirmation of your operational status and model name.", model=args.model, max_tokens=500)
    if res["success"]:
        print(f"✅ Gateway Active ({res['elapsed_seconds']}s)")
        content_str = res.get("content") or ""
        print(f"🤖 Response: {content_str.strip() if content_str else '[Reasoning completed, no visible text]'}")
        print(f"📊 Usage: {json.dumps(res['usage'])}")
        if res.get("ignored_params"):
            print(f"ℹ️ Ignored parameters: {res['ignored_params']}")
    else:
        print(f"❌ Ping Failed: {res.get('status_code', '')} {res.get('error')}")

def handle_task(args):
    prompt = args.prompt
    if args.input_file:
        if not os.path.exists(args.input_file):
            print(f"❌ Input file not found: {args.input_file}")
            sys.exit(1)
        with open(args.input_file, "r", encoding="utf-8") as f:
            file_content = f.read()
        prompt = f"{prompt}\n\n### CONTEXT FILE ({args.input_file}):\n```\n{file_content}\n```"

    print(f"🚀 Dispatching task to Experiential Labs [{args.model}] (input size: {len(prompt)} chars)...")
    res = dispatch_completion(
        prompt,
        model=args.model,
        system_prompt=args.system_prompt,
        max_tokens=args.max_tokens,
        json_mode=args.json
    )

    if not res["success"]:
        print(f"❌ Task Failed ({res.get('elapsed_seconds')}s): {res.get('error')}")
        sys.exit(1)

    print(f"✅ Completed in {res['elapsed_seconds']}s")
    usage = res["usage"]
    reasoning_tokens = usage.get("completion_tokens_details", {}).get("reasoning_tokens", 0)
    print(f"📊 Settlement: {usage.get('total_tokens', 0)} total tokens ({reasoning_tokens} reasoning tokens, cost: ${usage.get('cost', 0.0)})")

    if args.output_file:
        with open(args.output_file, "w", encoding="utf-8") as f:
            f.write(res["content"])
        print(f"💾 Output saved directly to: {args.output_file}")
    else:
        print("\n" + "="*60 + "\n" + res["content"] + "\n" + "="*60)

def main():
    parser = argparse.ArgumentParser(description="Experiential Labs Headless Task Runner")
    parser.add_argument("--model", default=DEFAULT_MODEL, help=f"Model slug (default: {DEFAULT_MODEL})")
    parser.add_argument("--task", default="prompt", choices=["prompt", "ping"], help="Task mode")
    parser.add_argument("--prompt", default="", help="Prompt instruction")
    parser.add_argument("--system-prompt", default="You are an expert AI software architect and senior technical engineer.", help="System prompt")
    parser.add_argument("--input-file", default=None, help="Path to input context file")
    parser.add_argument("--output-file", default=None, help="Path to output file")
    parser.add_argument("--max-tokens", type=int, default=4000, help="Max tokens (default: 4000)")
    parser.add_argument("--json", action="store_true", help="Enforce JSON response format")

    args = parser.parse_args()

    if args.task == "ping":
        handle_ping(args)
    else:
        if not args.prompt and not args.input_file:
            print("❌ Error: Must specify --prompt or --input-file")
            sys.exit(1)
        handle_task(args)

if __name__ == "__main__":
    main()
