#!/usr/bin/env python3
"""
=============================================================================
             HELMETSAN METAL TRANSLATION BOT (NODE A - M4 PRO)
=============================================================================
Autonomous, high-throughput catalog translation bot powered by Apple Silicon
Metal GPU acceleration on local Node A (LM Studio @ 127.0.0.1:1234).

Features:
  - Metal GPU-accelerated local inference (google/gemma-3-4b or qwen/qwen3.8-27b)
  - Batch candidate fetcher & batch WP database ingestion via remote bridge
  - State persistence (resumes seamlessly from checkpoint)
  - Interactive live dashboard & background daemon mode (--daemon, --status, --stop)
  - Multi-language pipeline (de -> zh -> fr -> es -> it -> pl -> pt -> nl -> ja)
"""

import sys
import os
import json
import time
import re
import signal
import argparse
import urllib.request
import urllib.error
import subprocess
import threading
import logging
from logging.handlers import RotatingFileHandler
from concurrent.futures import ThreadPoolExecutor
from datetime import datetime, timedelta

# Configuration
NODE_A_URL       = "http://127.0.0.1:1234/v1"
DEFAULT_MODEL    = "google/gemma-4-12b-qat"
REMOTE_SSH_HOST  = "root@31.70.136.154"
REMOTE_WP_PATH   = "/var/www/helmetsan.com/public"
REMOTE_BRIDGE    = "/var/www/helmetsan.com/scripts/translate_bridge.php"

SCRIPT_DIR       = os.path.dirname(os.path.abspath(__file__))
STATE_FILE       = os.path.join(SCRIPT_DIR, "metal_bot_state.json")
STAGING_FILE     = os.path.join(SCRIPT_DIR, "metal_bot_staging.json")
LOG_FILE         = os.path.join(SCRIPT_DIR, "metal_bot.log")
PID_FILE         = os.path.join(SCRIPT_DIR, "metal_bot.pid")

try:
    from translation_memory import get_translation_memory
except ImportError:
    import sys
    sys.path.append(SCRIPT_DIR)
    from translation_memory import get_translation_memory

ALL_LANGS = ["de", "zh", "fr", "es", "it", "pl", "pt", "nl", "ja"]

LANG_NAMES = {
    "de": "German (Deutsch)",
    "zh": "Chinese (中文)",
    "fr": "French (Français)",
    "es": "Spanish (Español)",
    "it": "Italian (Italiano)",
    "pl": "Polish (Polski)",
    "pt": "Portuguese (Português)",
    "nl": "Dutch (Nederlands)",
    "ja": "Japanese (日本語)"
}

# Thread-safe lock for local staging file access
_staging_lock = threading.RLock()

def load_staging():
    with _staging_lock:
        if os.path.exists(STAGING_FILE):
            try:
                with open(STAGING_FILE, "r", encoding="utf-8") as f:
                    return json.load(f)
            except Exception:
                pass
        return []

def save_staging(items):
    with _staging_lock:
        try:
            with open(STAGING_FILE, "w", encoding="utf-8") as f:
                json.dump(items, f, indent=2, ensure_ascii=False)
        except Exception as e:
            log(f"⚠️ Failed to save staging: {e}")

def append_staging(item):
    with _staging_lock:
        items = load_staging()
        items = [it for it in items if it.get("en_id") != item.get("en_id")]
        items.append(item)
        save_staging(items)

# Setup rotating log handler (10MB max size, 3 backup files)
_bot_logger = logging.getLogger("metal_translation_bot")
_bot_logger.setLevel(logging.INFO)
if not _bot_logger.handlers:
    _rfh = RotatingFileHandler(LOG_FILE, maxBytes=10 * 1024 * 1024, backupCount=3, encoding="utf-8")
    _rfh.setFormatter(logging.Formatter("[%(asctime)s] %(message)s", datefmt="%Y-%m-%d %H:%M:%S"))
    _bot_logger.addHandler(_rfh)

def log(msg):
    timestamp = datetime.now().strftime("%Y-%m-%d %H:%M:%S")
    line = f"[{timestamp}] {msg}"
    print(line, flush=True)
    try:
        _bot_logger.info(msg)
    except Exception:
        pass
        pass

def save_state(state):
    try:
        with open(STATE_FILE, "w", encoding="utf-8") as f:
            json.dump(state, f, indent=2)
    except Exception as e:
        log(f"⚠️ Failed to save state: {e}")

def load_state():
    if os.path.exists(STATE_FILE):
        try:
            with open(STATE_FILE, "r", encoding="utf-8") as f:
                return json.load(f)
        except Exception:
            pass
    return {
        "active_language": "de",
        "session_start": datetime.now().isoformat(),
        "total_translated": 0,
        "total_failed": 0,
        "history": [],
        "languages": {}
    }

# ─────────────────────────────────────────────────────────────────────────────
# Remote Bridge Client
# ─────────────────────────────────────────────────────────────────────────────
def run_bridge_command(payload, timeout=45):
    """Send JSON payload via SSH to remote bridge script with keep-alive."""
    cmd = [
        "ssh", "-o", "ConnectTimeout=10",
        "-o", "ServerAliveInterval=15",
        "-o", "ServerAliveCountMax=8",
        REMOTE_SSH_HOST,
        f"wp --path={REMOTE_WP_PATH} eval-file {REMOTE_BRIDGE} --allow-root"
    ]
    json_bytes = json.dumps(payload).encode("utf-8")
    try:
        res = subprocess.run(cmd, input=json_bytes, capture_output=True, timeout=timeout)
        if res.returncode != 0:
            log(f"❌ Bridge command error (code {res.returncode}): {res.stderr.decode('utf-8', errors='ignore')}")
            return None
        out = res.stdout.decode("utf-8", errors="ignore").strip()
        # Parse JSON
        match = re.search(r"\{[\s\S]*\}", out)
        if match:
            return json.loads(match.group(0))
        return json.loads(out)
    except subprocess.TimeoutExpired:
        log("❌ Bridge command timed out.")
        return None
    except Exception as e:
        log(f"❌ Bridge communication exception: {e}")
        return None

def fetch_stats():
    """Fetch current catalog counts across all languages."""
    data = run_bridge_command({"action": "stats"})
    if data and data.get("success"):
        return data
    return None

def fetch_candidates(lang, limit=20, exclude_ids=None, post_id=None):
    """Fetch untranslated candidate helmets for target language."""
    payload = {
        "action": "fetch_candidates",
        "lang": lang,
        "limit": limit,
        "exclude_ids": exclude_ids or []
    }
    if post_id:
        payload["post_id"] = int(post_id)
    data = run_bridge_command(payload, timeout=60)
    if data is not None and data.get("success"):
        return data.get("candidates", [])
    return None

def save_batch_translations(items):
    """Push translated batch to WordPress."""
    payload = {
        "action": "save_batch",
        "items": items
    }
    data = run_bridge_command(payload, timeout=180)
    if data and data.get("success"):
        return data.get("results", [])
    return []

# ─────────────────────────────────────────────────────────────────────────────
# LM Studio Metal Inference Client
# ─────────────────────────────────────────────────────────────────────────────
def check_node_a(model_name):
    """Verify Node A LM Studio server and model availability."""
    try:
        req = urllib.request.Request(f"{NODE_A_URL}/models")
        with urllib.request.urlopen(req, timeout=5.0) as res:
            data = json.loads(res.read().decode("utf-8"))
            models = [m.get("id") for m in data.get("data", [])]
            return True, models
    except Exception as e:
        return False, str(e)

def build_system_prompt(lang):
    """Build high-precision language system instruction for gear cataloging."""
    if lang == "de":
        return (
            "You are a professional German motorcycle gear catalog translator. "
            "Translate the product into authentic German (de_DE). "
            "CRITICAL: Always use 'Helm', 'Integralhelm', or 'Motorradhelm' for helmet. NEVER use 'Hütchen'. "
            "Use standard terms: 'Klapphelm' (modular), 'Sonnenblende' (sun visor), 'Doppel-D-Ring' (double D). "
            "Keep brand names and model numbers untranslated. "
            "Return ONLY a valid JSON object. No Markdown code fences, no explanations."
        )
    elif lang == "zh":
        return (
            "You are a professional motorcycle gear catalog translator. "
            "Translate the product details into Simplified Chinese (中文). "
            "Keep brand names and model numbers untranslated. "
            "Use standard terminology: 全盔 (full face), 揭面盔 (modular), 双D扣 (Double D-Ring), 遮阳镜 (sun visor). "
            "Return ONLY a valid JSON object. No explanations."
        )
    elif lang == "fr":
        return (
            "You are a professional French motorcycle gear catalog translator. "
            "Translate the product into authentic French (fr_FR). "
            "Use standard terms: 'Casque intégral', 'Casque modulable', 'Écran solaire', 'Boucle double D'. "
            "Keep brand names and model numbers untranslated. "
            "Return ONLY a valid JSON object. No explanations."
        )
    elif lang == "es":
        return (
            "You are a professional Spanish motorcycle gear catalog translator. "
            "Translate the product into authentic Spanish (es_ES). "
            "Use standard terms: 'Casco integral', 'Casco modular', 'Visor solar', 'Cierre doble D'. "
            "Keep brand names and model numbers untranslated. "
            "Return ONLY a valid JSON object. No explanations."
        )
    elif lang == "it":
        return (
            "You are a professional Italian motorcycle gear catalog translator. "
            "Translate the product into authentic Italian (it_IT). "
            "Use standard terms: 'Casco integrale', 'Casco modulare', 'Visierino parasole', 'Chiusura a doppia D'. "
            "Keep brand names and model numbers untranslated. "
            "Return ONLY a valid JSON object. No explanations."
        )
    elif lang == "pl":
        return (
            "You are a professional Polish motorcycle gear catalog translator. "
            "Translate the product into authentic Polish (pl_PL). "
            "Use standard terms: 'Kask integralny', 'Kask szczękowy', 'Blenda przeciwsłoneczna', 'Zapięcie DD'. "
            "Keep brand names and model numbers untranslated. "
            "Return ONLY a valid JSON object. No explanations."
        )
    elif lang == "pt":
        return (
            "You are a professional Portuguese motorcycle gear catalog translator. "
            "Translate the product into authentic Portuguese (pt_PT). "
            "Use standard terms: 'Capacete integral', 'Capacete modular', 'Viseira solar', 'Fecho duplo D'. "
            "Keep brand names and model numbers untranslated. "
            "Return ONLY a valid JSON object. No explanations."
        )
    elif lang == "nl":
        return (
            "You are a professional Dutch motorcycle gear catalog translator. "
            "Translate the product into authentic Dutch (nl_NL). "
            "Use standard terms: 'Integraalhelm', 'Systeemhelm', 'Zonnevizier', 'Dubbel-D sluiting'. "
            "Keep brand names and model numbers untranslated. "
            "Return ONLY a valid JSON object. No explanations."
        )
    elif lang == "ja":
        return (
            "You are a professional Japanese motorcycle gear catalog translator. "
            "Translate the product into natural Japanese (日本語). "
            "Use standard terms: フルフェイス, システムヘルメット, インナーサンバイザー, Dリング. "
            "Keep brand names and model numbers untranslated. "
            "Return ONLY a valid JSON object. No explanations."
        )
    return "Translate into the target language. Return ONLY a valid JSON object."

def call_metal_model(model, system_prompt, user_payload, max_tokens=1500, timeout=90):
    """Execute inference via Apple Silicon Metal on Node A."""
    req_body = {
        "model": model,
        "messages": [
            {"role": "system", "content": system_prompt},
            {"role": "user", "content": json.dumps(user_payload, ensure_ascii=False)}
        ],
        "temperature": 0.1,
        "max_tokens": max_tokens
    }
    
    url = f"{NODE_A_URL}/chat/completions"
    headers = {"Content-Type": "application/json"}
    req = urllib.request.Request(url, data=json.dumps(req_body).encode("utf-8"), headers=headers)
    
    t0 = time.time()
    try:
        with urllib.request.urlopen(req, timeout=timeout) as response:
            result = json.loads(response.read().decode("utf-8"))
            elapsed = time.time() - t0
            msg = result["choices"][0]["message"]
            content = msg.get("content", "").strip()
            if not content and "reasoning_content" in msg:
                content = msg["reasoning_content"].strip()
            return content, elapsed
    except Exception as e:
        log(f"❌ Metal inference call failed: {e}")
        return None, 0

def clean_json(text):
    """Extract clean JSON object from model response."""
    if not text:
        return None
    # Strip thinking / reasoning tags defensively if model ever emits them
    cleaned = re.sub(r"<(thought|think)>[\s\S]*?<\/\1>", "", text, flags=re.IGNORECASE).strip()
    # Strip fences
    cleaned = re.sub(r"^```(?:json)?\s*", "", cleaned.strip(), flags=re.MULTILINE)
    cleaned = re.sub(r"\s*```$", "", cleaned.strip(), flags=re.MULTILINE)
    
    match = re.search(r"\{[\s\S]*\}", cleaned)
    if match:
        try:
            return json.loads(match.group(0))
        except Exception:
            pass
    try:
        return json.loads(cleaned)
    except Exception:
        return None

def translate_helmet_metal(helmet, lang, model):
    """Translate a single helmet payload with Translation Memory pre-filtering and retry."""
    system_prompt = build_system_prompt(lang)
    
    sizing = helmet.get("sizing_fit")
    fit_notes = ""
    if isinstance(sizing, dict):
        fit_notes = sizing.get("fit_notes", "")
    elif isinstance(sizing, str):
        fit_notes = sizing
        
    features = helmet.get("features") if isinstance(helmet.get("features"), list) else []
    
    # Translation Memory (TM) pre-filtering: deterministic 0-token matching
    tm = get_translation_memory()
    resolved_tm_map, novel_indices, novel_features = tm.partition_features(features, lang)
    
    source_payload = {
        "title": helmet["title"],
        "description": helmet.get("content") or helmet.get("title"),
        "excerpt": helmet.get("excerpt") or "",
        "marketing_description": helmet.get("marketing") or "",
        "technical_analysis": helmet.get("tech") or "",
        "features": novel_features,
        "fit_notes": fit_notes
    }
    
    for attempt in range(1, 4):
        raw_text, elapsed = call_metal_model(model, system_prompt, source_payload)
        parsed = clean_json(raw_text)
        if parsed and (parsed.get("description") or parsed.get("title")):
            # Merge TM-resolved features back in original sequence
            llm_novel_feats = parsed.get("features") if isinstance(parsed.get("features"), list) else []
            parsed["features"] = tm.merge_features(resolved_tm_map, novel_indices, llm_novel_feats, original_features=features)
            return parsed, elapsed
        log(f"⚠️ Retry #{attempt} for helmet #{helmet['id']} ('{helmet['title']}') - JSON parse failed")
        time.sleep(1)
        
    return None, 0

# ─────────────────────────────────────────────────────────────────────────────
# Autonomous Live Runner
# ─────────────────────────────────────────────────────────────────────────────
class TranslationBot:
    def __init__(self, target_lang="de", model=DEFAULT_MODEL, batch_size=10, all_langs=False, max_count=None, workers=2, post_id=None):
        self.target_lang = target_lang
        self.model = model
        self.post_id = post_id
        self.batch_size = 1 if post_id else batch_size
        self.all_langs = all_langs
        self.max_count = 1 if post_id else max_count
        self.workers = 1 if post_id else max(1, workers)
        self.total_session_translated = 0
        self.running = True
        self.state = load_state()
        self.baseline_tokens_saved_tm = self.state.get("tokens_saved_tm", 0)
        self.baseline_tm_hits = self.state.get("tm_hits", 0)
        self.failed_ids = set()

    def stop(self, signum=None, frame=None):
        log("\n🛑 Stopping Translation Bot gracefully...")
        self.running = False

    def print_banner(self, stats):
        print("\n" + "=" * 70)
        print("  ⚡ HELMETSAN METAL TRANSLATION BOT  –  NODE A (APPLE M4 PRO)")
        print("=" * 70)
        print(f"  🧠 Model:        {self.model} (Metal Acceleration)")
        print(f"  🌐 Active Lang:  {self.target_lang.upper()} ({LANG_NAMES.get(self.target_lang, '')})")
        print(f"  ⚡ Workers:      {self.workers} concurrent streams")
        print(f"  📦 Batch Size:   {self.batch_size} helmets / round")
        if self.max_count:
            print(f"  🎯 Session Goal: {self.max_count} helmets")
        print(f"  📊 Catalog Stats:")
        if stats:
            total_en = stats.get("total_en", 5415)
            languages = stats.get("languages", {})
            for l in ALL_LANGS:
                cnt = languages.get(l, 0)
                pct = (cnt / total_en * 100) if total_en else 0
                marker = "👉" if l == self.target_lang else "  "
                print(f"     {marker} {l.upper():<4} : {cnt:>5} / {total_en} ({pct:>5.1f}%)")
        print("=" * 70 + "\n", flush=True)

    def run_language_loop(self, lang):
        log(f"🚀 Starting translation pipeline for target: [{lang.upper()}] ({LANG_NAMES.get(lang, '')})")
        
        consecutive_empty = 0
        round_num = 0
        session_t0 = time.time()

        while self.running:
            if self.max_count and self.total_session_translated >= self.max_count:
                log(f"🎯 Target goal of {self.max_count} helmets reached! Finishing run.")
                self.running = False
                break

            # 1. Flush any previously staged items from previous rounds/runs
            staged = load_staging()
            if staged:
                log(f"🔄 Retrying ingestion of {len(staged)} previously translated helmets from local staging...")
                save_results = save_batch_translations(staged)
                saved_en_ids = set(r["en_id"] for r in save_results if r.get("success"))
                if saved_en_ids:
                    staged = [it for it in staged if it.get("en_id") not in saved_en_ids]
                    save_staging(staged)
                    success_cnt = len(saved_en_ids)
                    self.total_session_translated += success_cnt
                    log(f"🎉 Staged batch committed: {success_cnt} helmets saved to WP.")

            round_num += 1
            needed = (self.max_count - self.total_session_translated) if self.max_count else self.batch_size
            fetch_limit = max(1, min(self.batch_size, needed))
            
            log(f"\n--- [Round {round_num}] Fetching {fetch_limit} candidate helmets for [{lang.upper()}] ---")
            
            candidates = fetch_candidates(lang, limit=fetch_limit, exclude_ids=list(self.failed_ids), post_id=self.post_id)
            
            if candidates is None:
                log(f"⚠️ Remote bridge timed out or returned error. Retrying in 5s...")
                time.sleep(5)
                continue

            if len(candidates) == 0:
                consecutive_empty += 1
                log(f"ℹ️ No untranslated helmets found for [{lang.upper()}]. (Check #{consecutive_empty})")
                if consecutive_empty >= 2:
                    log(f"🎉 100% COMPLETE! All helmets translated for [{lang.upper()}].")
                    break
                time.sleep(2)
                continue

            consecutive_empty = 0
            log(f"📥 Fetched {len(candidates)} candidates. Translating on Apple Silicon GPU...")

            def _process_candidate(idx_helmet):
                idx, helmet = idx_helmet
                if not self.running:
                    return None
                try:
                    h_id = helmet["id"]
                    h_title = helmet["title"]
                    h_slug = helmet["slug"]

                    parsed, elapsed = translate_helmet_metal(helmet, lang, self.model)
                    if not parsed:
                        log(f"  ❌ #{h_id} '{h_title}' translation failed. Adding to temporary exclude.")
                        self.failed_ids.add(h_id)
                        return None

                    log(f"  ✅ [{idx}/{len(candidates)}] #{h_id} translated in {elapsed:.2f}s: \"{parsed.get('title', h_title)}\"")

                    # Generate clean target slug with language suffix to prevent WP slug collisions
                    raw_title = parsed.get("title", "")
                    clean_title_slug = re.sub(r"[^a-zA-Z0-9\-]", "-", raw_title.lower()).strip("-")
                    clean_title_slug = re.sub(r"-+", "-", clean_title_slug)

                    # Always append language suffix to prevent WP collisions and CJK truncation
                    if lang in ("zh", "ja"):
                        target_slug = f"{h_slug}-{lang}"
                    elif clean_title_slug and len(clean_title_slug) > 3:
                        if not clean_title_slug.endswith(f"-{lang}"):
                            target_slug = f"{clean_title_slug}-{lang}"
                        else:
                            target_slug = clean_title_slug
                    else:
                        target_slug = f"{h_slug}-{lang}"

                    return {
                        "en_id": h_id,
                        "lang": lang,
                        "title": parsed.get("title", h_title),
                        "slug": target_slug,
                        "content": parsed.get("description") or parsed.get("content") or helmet.get("content", ""),
                        "excerpt": parsed.get("excerpt") or helmet.get("excerpt", ""),
                        "marketing": parsed.get("marketing_description") or "",
                        "tech": parsed.get("technical_analysis") or "",
                        "features": parsed.get("features") or [],
                        "sizing_fit": {"fit_notes": parsed.get("fit_notes", "")}
                    }
                except Exception as ex:
                    log(f"  ⚠️ Error processing candidate #{helmet.get('id')}: {ex}. Skipping.")
                    self.failed_ids.add(helmet.get("id"))
                    return None

            items_to_process = list(enumerate(candidates, 1))
            if self.workers > 1:
                with ThreadPoolExecutor(max_workers=self.workers) as pool:
                    results = list(pool.map(_process_candidate, items_to_process))
                translated_batch = [r for r in results if r is not None]
            else:
                translated_batch = []
                for item in items_to_process:
                    res = _process_candidate(item)
                    if res:
                        translated_batch.append(res)

            if not translated_batch:
                log("⚠️ No successful translations in this round. Continuing...")
                continue

            # Ingest batch into WordPress with on-disk staging safety
            for item in translated_batch:
                append_staging(item)

            staged_to_save = load_staging()
            log(f"💾 Saving {len(staged_to_save)} staged translated helmets to WordPress database...")
            save_results = save_batch_translations(staged_to_save)

            saved_en_ids = set(r["en_id"] for r in save_results if r.get("success"))
            if saved_en_ids:
                remaining = [it for it in staged_to_save if it.get("en_id") not in saved_en_ids]
                save_staging(remaining)
                success_cnt = len(saved_en_ids)
            else:
                success_cnt = 0
                log(f"⚠️ Remote save did not report success. Retaining {len(staged_to_save)} items in staging for zero-token retry.")

            self.total_session_translated += success_cnt
            elapsed_total = time.time() - session_t0
            speed = (self.total_session_translated / (elapsed_total / 60)) if elapsed_total > 0 else 0

            # Update token metrics in state file (cumulative across runs)
            tm = get_translation_memory()
            session_tokens_saved = tm.stats.get("tokens_saved", 0)
            session_tm_hits = tm.stats.get("tm_hits", 0)
            self.state["tokens_saved_tm"] = self.baseline_tokens_saved_tm + session_tokens_saved
            self.state["tm_hits"] = self.baseline_tm_hits + session_tm_hits
            self.state["total_translated"] = self.state.get("total_translated", 0) + success_cnt
            self.state["active_language"] = lang
            self.state["last_update"] = datetime.now().isoformat()
            self.state["speed_helmets_per_min"] = round(speed, 2)
            save_state(self.state)

            log(f"🎉 Batch saved: {success_cnt}/{len(staged_to_save)} succeeded. "
                f"Session Total: {self.total_session_translated} | Speed: {speed:.1f} helmets/min | "
                f"TM Hits: {self.state['tm_hits']} ({self.state['tokens_saved_tm']} total tokens saved, +{session_tokens_saved} this session)")

            for res in save_results:
                if res.get("success"):
                    log(f"   🔗 #{res['en_id']} -> WP ID #{res['new_id']} ({res.get('permalink', '')})")

            if self.max_count and self.total_session_translated >= self.max_count:
                log(f"🎯 Target goal of {self.max_count} helmets reached! Finishing run.")
                self.running = False
                break

            time.sleep(0.5)

    def run(self):
        signal.signal(signal.SIGINT, self.stop)
        signal.signal(signal.SIGTERM, self.stop)

        # 1. Health check Node A
        ok, models = check_node_a(self.model)
        if not ok:
            log(f"❌ Error: LM Studio Node A is offline at {NODE_A_URL}. Details: {models}")
            sys.exit(1)
        log(f"🟢 LM Studio Node A is ONLINE. Available models: {models}")

        # 2. Get initial stats
        stats = fetch_stats()
        self.print_banner(stats)

        # 3. Process languages
        langs_to_process = ALL_LANGS if self.all_langs else [self.target_lang]
        
        # Make sure the requested target_lang is first
        if self.all_langs and self.target_lang in langs_to_process:
            langs_to_process.remove(self.target_lang)
            langs_to_process.insert(0, self.target_lang)

        for lang in langs_to_process:
            if not self.running:
                break
            self.target_lang = lang
            self.run_language_loop(lang)

        log("🏁 Translation Bot run finished.")

# ─────────────────────────────────────────────────────────────────────────────
# Daemon Controls
# ─────────────────────────────────────────────────────────────────────────────
def check_pid():
    if os.path.exists(PID_FILE):
        try:
            with open(PID_FILE, "r") as f:
                pid = int(f.read().strip())
            os.kill(pid, 0)
            return pid
        except OSError:
            os.remove(PID_FILE)
    return None

def start_daemon(args):
    pid = check_pid()
    if pid:
        print(f"⚠️ Translation Bot is already running with PID {pid}.")
        sys.exit(0)

    # Spawn process in background
    cmd = [
        sys.executable, os.path.abspath(__file__),
        "--lang", args.lang,
        "--model", args.model,
        "--batch-size", str(args.batch_size),
        "--workers", str(args.workers)
    ]
    if args.count:
        cmd.extend(["--count", str(args.count)])
    if args.all_langs:
        cmd.append("--all-langs")

    with open(LOG_FILE, "a") as log_out:
        proc = subprocess.Popen(
            cmd,
            stdout=log_out,
            stderr=log_out,
            stdin=subprocess.DEVNULL,
            start_new_session=True
        )

    with open(PID_FILE, "w") as f:
        f.write(str(proc.pid))

    print(f"🚀 Translation Bot started in background (PID: {proc.pid})")
    print(f"📄 Follow logs with: tail -f {LOG_FILE}")
    print(f"📊 Check status with: python3 {os.path.basename(__file__)} --status")

def stop_daemon():
    pid = check_pid()
    if not pid:
        print("ℹ️ Translation Bot is not running.")
        return
    try:
        os.kill(pid, signal.SIGTERM)
        print(f"🛑 Sent SIGTERM to Translation Bot (PID {pid}).")
        # Wait up to 5 seconds
        for _ in range(10):
            time.sleep(0.5)
            if not check_pid():
                print("✅ Process stopped.")
                return
        os.kill(pid, signal.SIGKILL)
        print("⚡ Force killed.")
    except Exception as e:
        print(f"❌ Error stopping process: {e}")

def print_status():
    pid = check_pid()
    state = load_state()
    stats = fetch_stats()

    print("\n" + "=" * 65)
    print("        HELMETSAN METAL TRANSLATION BOT STATUS")
    print("=" * 65)
    print(f"  Status:         {'🟢 RUNNING (PID ' + str(pid) + ')' if pid else '⚪ STOPPED'}")
    print(f"  Active Lang:    {state.get('active_language', 'de').upper()}")
    print(f"  Total Success:  {state.get('total_translated', 0)} helmets")
    print(f"  Speed:          {state.get('speed_helmets_per_min', 0)} helmets/min")
    print(f"  Last Activity:  {state.get('last_update', 'N/A')}")
    print("-" * 65)
    print("  Catalog Language Progress:")
    if stats:
        total_en = stats.get("total_en", 5415)
        languages = stats.get("languages", {})
        for l in ALL_LANGS:
            cnt = languages.get(l, 0)
            pct = (cnt / total_en * 100) if total_en else 0
            bar_len = int(pct / 5)
            bar = "█" * bar_len + "░" * (20 - bar_len)
            print(f"    {l.upper():<3} [{bar}] {cnt:>5}/{total_en} ({pct:>5.1f}%)")
    print("=" * 65 + "\n")

# ─────────────────────────────────────────────────────────────────────────────
# CLI Entry Point
# ─────────────────────────────────────────────────────────────────────────────
def main():
    parser = argparse.ArgumentParser(description="Helmetsan Metal Autonomous Translation Bot")
    parser.add_argument("--lang", default="de", choices=ALL_LANGS, help="Target language (default: de)")
    parser.add_argument("--model", default=DEFAULT_MODEL, help=f"LM Studio model (default: {DEFAULT_MODEL})")
    parser.add_argument("--batch-size", type=int, default=10, help="Batch size for fetch/save rounds (default: 10)")
    parser.add_argument("--workers", type=int, default=2, help="Number of concurrent translation workers (default: 2)")
    parser.add_argument("--count", type=int, default=None, help="Target count of helmets to translate in this run (e.g. 200)")
    parser.add_argument("--post-id", type=int, default=None, help="Translate a specific helmet ID on-demand")
    parser.add_argument("--all-langs", action="store_true", help="Continue sequentially through all 9 languages")
    parser.add_argument("--daemon", action="store_true", help="Launch bot as background daemon")
    parser.add_argument("--stop", action="store_true", help="Stop running background daemon")
    parser.add_argument("--status", action="store_true", help="Print current status and statistics")

    args = parser.parse_args()

    if args.status:
        print_status()
        return

    if args.stop:
        stop_daemon()
        return

    if args.daemon:
        start_daemon(args)
        return

    bot = TranslationBot(
        target_lang=args.lang,
        model=args.model,
        batch_size=args.batch_size,
        all_langs=args.all_langs,
        max_count=args.count,
        workers=args.workers,
        post_id=args.post_id
    )
    bot.run()

if __name__ == "__main__":
    main()
