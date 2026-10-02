#!/usr/bin/env python3
"""
=============================================================================
         HELMETSAN MULTI-MODEL SWARM TRANSLATION BOT
=============================================================================
High-throughput, multi-instance, multi-model swarm translation engine.
Deploys specialized model routing:
  - CJK (zh, ja): Moonshot AI Kimi-K3 (NVIDIA NIM) / GPT-5.6-Luna
  - Romance (es, fr, it, pt): GPT-5.6-Luna (Experiential Labs) / DeepSeek-V4-Flash
  - Germanic & Slavic (de, nl, pl): DeepSeek-V4-Flash / Meta Llama-3.3-70B
  - Safety & Homologation: Deterministic TokenMasker + DeepSeek-R1

Features:
  - Zero lock contention with parallel multi-language fan-out
  - Distributed atomic lease manager (swarm_leases.db) for multi-bot deployments
  - 0-token Translation Memory (TM) cache for recurring gear specs
  - Automated remote bridge sync to WordPress Polylang clusters
"""

import sys
import os
import json
import time
import random
import re
import argparse
import subprocess
import threading
from concurrent.futures import ThreadPoolExecutor, as_completed
from typing import Dict, List, Optional, Any, Tuple
import requests
from requests.adapters import HTTPAdapter
from urllib3.util.retry import Retry
import warnings
warnings.filterwarnings("ignore")

# Path configurations
SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
PROJECT_DIR = os.path.dirname(SCRIPT_DIR)
sys.path.append(SCRIPT_DIR)

from token_masker import TokenMasker
from swarm_lease_manager import SwarmLeaseManager
from translation_memory import get_translation_memory

# API Configurations
EXPERIENTIAL_GATEWAY_URL = "https://api.experientiallabs.ai/v1/chat/completions"
NVIDIA_NIM_CHAT_URL      = "https://integrate.api.nvidia.com/v1/chat/completions"

def _load_vault_key(key_name: str) -> str:
    val = os.environ.get(key_name, "").strip()
    if val:
        return val
    vault_path = os.path.expanduser("~/.config/antigravity/ai_mesh.env")
    if os.path.exists(vault_path):
        try:
            with open(vault_path, "r", encoding="utf-8") as f:
                for line in f:
                    trimmed = line.strip()
                    if trimmed.startswith(f"{key_name}="):
                        return trimmed[len(f"{key_name}="):].strip()
        except Exception:
            pass
    return ""

DEFAULT_EXPLABS_KEY = _load_vault_key("EXPLABS_API_KEY")
DEFAULT_NVIDIA_KEY  = _load_vault_key("NVIDIA_API_KEY")

REMOTE_SSH_HOST  = "root@31.70.136.154"
REMOTE_WP_PATH   = "/var/www/helmetsan.com/public"
REMOTE_BRIDGE    = "/var/www/helmetsan.com/scripts/translate_bridge.php"

ALL_TARGET_LANGS = ["de", "zh", "fr", "es", "it", "pl", "pt", "nl", "ja"]

LANG_LABELS = {
    "de": "German (de_DE)",
    "zh": "Chinese (zh_CN)",
    "fr": "French (fr_FR)",
    "es": "Spanish (es_ES)",
    "it": "Italian (it_IT)",
    "pl": "Polish (pl_PL)",
    "pt": "Portuguese (pt_PT)",
    "nl": "Dutch (nl_NL)",
    "ja": "Japanese (ja)"
}

# ─────────────────────────────────────────────────────────────────────────────
# Multi-Model Swarm Dispatcher with HTTP Keep-Alive Connection Pooling
# ─────────────────────────────────────────────────────────────────────────────
class SwarmModelClient:
    """Manages multi-provider LLM inference with automated failover and persistent connection pooling."""

    def __init__(self, explabs_key: Optional[str] = None, nvidia_key: Optional[str] = None):
        self.explabs_key = explabs_key or os.environ.get("EXPLABS_API_KEY") or DEFAULT_EXPLABS_KEY
        self.nvidia_key  = nvidia_key or os.environ.get("NVIDIA_API_KEY") or DEFAULT_NVIDIA_KEY
        self.session = requests.Session()
        retries = Retry(total=2, backoff_factor=0.3, status_forcelist=[500, 502, 503, 504])
        adapter = HTTPAdapter(pool_connections=8, pool_maxsize=16, max_retries=retries)
        self.session.mount("https://", adapter)

    def route_for_language(self, lang: str) -> List[Tuple[str, str]]:
        """
        Returns list of (provider, model_name) candidate pairs in prioritized fallback order.
        """
        if lang in ["zh", "ja"]:
            # East Asian CJK: GPT-6-Luna primary (~1.4s), DeepSeek-V4.1-Flash fallback (~1.0s), Kimi-K3 3rd fallback
            return [
                ("experiential", "gpt-6-luna"),
                ("experiential", "deepseek-v4.1-flash"),
                ("nvidia_nim", "moonshotai/kimi-k3")
            ]
        elif lang in ["es", "fr", "it", "pt"]:
            # Romance editorial: DeepSeek-V4.1-Flash primary (~0.6s), GPT-6-Luna arbiter (~1.4s)
            return [
                ("experiential", "deepseek-v4.1-flash"),
                ("experiential", "gpt-6-luna")
            ]
        else: # de, nl, pl
            # Germanic & Slavic: DeepSeek-V4.1-Flash primary (~0.6s), GPT-6-Luna fallback (~1.4s)
            return [
                ("experiential", "deepseek-v4.1-flash"),
                ("experiential", "gpt-6-luna")
            ]

    def complete(self, prompt: str, system_prompt: str, lang: str, timeout: int = 60) -> Tuple[Optional[str], Optional[str]]:
        """Executes translation call using the optimal specialist model with automated failover."""
        candidates = self.route_for_language(lang)

        for p_type, model in candidates:
            try:
                if p_type == "experiential":
                    res = self._call_experiential(prompt, system_prompt, model, lang, timeout)
                else:
                    res = self._call_nvidia_nim(prompt, system_prompt, model, timeout)

                if res:
                    clean_res = self._extract_json_block(res)
                    return clean_res, model
            except Exception:
                continue

        return None, None

    def _call_experiential(self, prompt: str, system_prompt: str, model: str, lang: str, timeout: int = 60) -> Optional[str]:
        cache_key = f"helmetsan:catalog_translate:{lang}:v1.0"
        headers = {
            "Authorization": f"Bearer {self.explabs_key}",
            "Content-Type": "application/json",
            "Accept": "application/json",
            "X-Prompt-Cache-Key": cache_key
        }
        payload = {
            "model": model,
            "prompt_cache_key": cache_key,
            "messages": [
                {"role": "system", "content": system_prompt},
                {"role": "user", "content": prompt}
            ],
            "temperature": 0.1,
            "max_tokens": 3000
        }
        for attempt in range(4):
            try:
                resp = self.session.post(EXPERIENTIAL_GATEWAY_URL, json=payload, headers=headers, timeout=timeout)
                if resp.status_code == 200:
                    data = resp.json()
                    return data["choices"][0]["message"]["content"]
                elif resp.status_code == 429:
                    sleep_time = 2.0 * (attempt + 1) + random.uniform(0.5, 1.5)
                    time.sleep(sleep_time)
                    continue
                else:
                    return None
            except Exception:
                time.sleep(1.0)
        return None

    def _call_nvidia_nim(self, prompt: str, system_prompt: str, model: str, timeout: int) -> Optional[str]:
        headers = {
            "Authorization": f"Bearer {self.nvidia_key}",
            "Content-Type": "application/json",
            "Accept": "application/json"
        }
        payload = {
            "model": model,
            "messages": [
                {"role": "system", "content": system_prompt},
                {"role": "user", "content": prompt}
            ],
            "temperature": 0.1,
            "max_tokens": 3000
        }
        resp = self.session.post(NVIDIA_NIM_CHAT_URL, json=payload, headers=headers, timeout=timeout)
        if resp.status_code == 200:
            data = resp.json()
            return data["choices"][0]["message"]["content"]
        return None

    def _extract_json_block(self, text: str) -> str:
        text = text.strip()
        # Extract markdown code blocks
        if "```json" in text:
            match = re.search(r"```json\s*(\{[\s\S]*?\})\s*```", text)
            if match:
                return match.group(1)
        if "```" in text:
            match = re.search(r"```\s*(\{[\s\S]*?\})\s*```", text)
            if match:
                return match.group(1)
        # Direct bracket match
        match = re.search(r"\{[\s\S]*\}", text)
        if match:
            return match.group(0)
        return text


# ─────────────────────────────────────────────────────────────────────────────
# Remote Bridge SSH Client
# ─────────────────────────────────────────────────────────────────────────────
def run_bridge_command(payload: Dict[str, Any], timeout: int = 90) -> Optional[Dict[str, Any]]:
    """Executes WP-CLI translate bridge command over SSH multiplexing."""
    cmd = [
        "ssh",
        "-o", "ControlMaster=auto",
        "-o", "ControlPath=/tmp/ssh_helmetsan_%r@%h:%p",
        "-o", "ControlPersist=10m",
        "-o", "ConnectTimeout=10",
        "-o", "ServerAliveInterval=15",
        "-o", "ServerAliveCountMax=8",
        REMOTE_SSH_HOST,
        f"wp --path={REMOTE_WP_PATH} eval-file {REMOTE_BRIDGE} --allow-root"
    ]
    json_bytes = json.dumps(payload).encode("utf-8")
    try:
        res = subprocess.run(cmd, input=json_bytes, capture_output=True, timeout=timeout)
        if res.returncode != 0:
            return None
        out = res.stdout.decode("utf-8", errors="ignore").strip()
        match = re.search(r"\{[\s\S]*\}", out)
        if match:
            return json.loads(match.group(0))
        return json.loads(out)
    except Exception:
        return None


# ─────────────────────────────────────────────────────────────────────────────
# Prompts & Translation Assembly
# ─────────────────────────────────────────────────────────────────────────────
def get_system_prompt_for_lang(lang: str) -> str:
    lang_name = LANG_LABELS.get(lang, lang)
    return f"""You are Helmetsan's Senior Multilingual Motorcycle Gear Translator & Technical Localization Engineer.
Target Locale: {lang_name}.

CRITICAL TECHNICAL GUIDELINES:
1. Preserve all brand names (e.g. Shoei, Arai, AGV, HJC, Shark, Nolan, X-Lite) without translation.
2. Preserve all model codes (e.g. X-SPR Pro, Pista GP RR, RPHA 12, NXR2) without modification.
3. Preserve all protected placeholder tokens (e.g. __TOKEN_001__, __TOKEN_002__) EXACTLY as written.
4. Use standard regional motorcycle terminology:
   - DE: 'Helm', 'Integralhelm', 'Klapphelm', 'Sonnenblende', 'Doppel-D-Ring', 'Belüftung'. (NEVER use 'Hütchen').
   - ZH: 全盔 (full face), 揭面盔 (modular), 双D扣 (Double D), 遮阳镜 (sun visor), 碳纤维 (carbon fiber).
   - FR: 'Casque intégral', 'Casque modulable', 'Écran solaire', 'Boucle double D'.
   - ES: 'Casco integral', 'Casco modular', 'Visor solar', 'Cierre doble D'.
   - IT: 'Casco integrale', 'Casco modulare', 'Visierino parasole', 'Chiusura a doppia D'.
   - PL: 'Kask integralny', 'Kask szczękowy', 'Blenda przeciwsłoneczna', 'Zapięcie DD'.
   - PT: 'Capacete integral', 'Capacete modular', 'Viseira solar', 'Fecho duplo D'.
   - NL: 'Integraalhelm', 'Systeemhelm', 'Zonnevizier', 'Dubbel-D sluiting'.
   - JA: フルフェイスヘルメット (full face), システムヘルメット (modular), インナーバイザー (sun visor), ダブルDリング (Double D).

Output Format: Return STRICTLY a valid JSON object matching the input schema. No conversational filler, no markdown codeblocks."""


# ─────────────────────────────────────────────────────────────────────────────
# Swarm Orchestrator Engine
# ─────────────────────────────────────────────────────────────────────────────
class SwarmTranslationOrchestrator:
    """Coordinates multi-model, multi-instance, parallel multi-language translation."""

    def __init__(self, workers: int = 6, batch_size: int = 5, instance_id: Optional[str] = None):
        self.workers = workers
        self.batch_size = batch_size
        self.client = SwarmModelClient()
        self.lease_mgr = SwarmLeaseManager(instance_id=instance_id)
        self.tm = get_translation_memory()
        self.staging: List[Dict[str, Any]] = []
        self.staging_lock = threading.Lock()
        self.flush_lock = threading.Lock()
        self.metrics = {
            "translated": 0,
            "failed": 0,
            "tm_hits": 0,
            "start_time": time.time()
        }

    def translate_helmet_for_lang(self, candidate: Dict[str, Any], lang: str) -> Optional[Dict[str, Any]]:
        """Translates a single helmet for a specific target language."""
        post_id = candidate["id"]
        en_title = candidate.get("title", "")
        en_content = candidate.get("content", "")
        en_excerpt = candidate.get("excerpt", "")
        raw_features = candidate.get("features", [])

        # 1. Feature translation via Translation Memory (0-token cache)
        resolved_feat_map, novel_indices, novel_feats = self.tm.partition_features(raw_features, lang)
        self.metrics["tm_hits"] += (len(raw_features) - len(novel_feats))

        # 2. Token Masking on title and descriptions
        masked_title, title_tokens = TokenMasker.mask(en_title)
        masked_content, content_tokens = TokenMasker.mask(en_content)
        masked_excerpt, excerpt_tokens = TokenMasker.mask(en_excerpt)

        # 3. Build input payload for model
        input_payload = {
            "title": masked_title,
            "short_description": masked_excerpt,
            "description": masked_content[:2500], # Bound context length for max speed
            "novel_features": novel_feats
        }

        user_prompt = f"Translate this motorcycle helmet catalog record into {LANG_LABELS.get(lang, lang)}:\n\n{json.dumps(input_payload, ensure_ascii=False, indent=2)}"
        system_prompt = get_system_prompt_for_lang(lang)

        # 4. Swarm LLM call
        raw_json, model_used = self.client.complete(user_prompt, system_prompt, lang)
        if not raw_json:
            return None

        try:
            trans_data = json.loads(raw_json)
        except Exception:
            return None

        # 5. Restore Protected Tokens
        out_title = TokenMasker.unmask(trans_data.get("title", en_title), title_tokens)
        out_content = TokenMasker.unmask(trans_data.get("description", en_content), content_tokens)
        out_excerpt = TokenMasker.unmask(trans_data.get("short_description", en_excerpt), excerpt_tokens)

        # 6. Merge Novel Features with TM Cache
        translated_novel = trans_data.get("novel_features", [])
        final_features = self.tm.merge_features(resolved_feat_map, novel_indices, translated_novel, raw_features)

        # 7. Safety Validation: Verify original homologations & brands preserved
        t_valid, _ = TokenMasker.validate_tokens_preserved(title_tokens, out_title)
        if not t_valid:
            out_title = en_title # Fallback to original brand title if model dropped key tokens

        return {
            "en_id": post_id,
            "lang": lang,
            "title": out_title,
            "content": out_content,
            "excerpt": out_excerpt,
            "features": final_features,
            "model_used": model_used or "unknown"
        }

    def process_work_unit(self, candidate: Dict[str, Any], lang: str, dry_run: bool = False) -> None:
        """Worker task processing a single (candidate, lang) work item."""
        post_id = candidate["id"]

        # Atomic claim
        if not self.lease_mgr.claim_task(post_id, lang):
            return

        try:
            t0 = time.time()
            result = self.translate_helmet_for_lang(candidate, lang)
            elapsed = time.time() - t0

            if result:
                with self.staging_lock:
                    self.staging.append(result)
                    self.metrics["translated"] += 1
                    count = len(self.staging)

                print(f"[{lang.upper()}] ✅ ID {post_id} translated via {result['model_used']} ({elapsed:.2f}s) — Staged: {count}/{self.batch_size}")
                if dry_run:
                    self.lease_mgr.release_task(post_id, lang)

                if not dry_run and count >= self.batch_size:
                    self.flush_staging_to_remote()
            else:
                self.metrics["failed"] += 1
                self.lease_mgr.release_task(post_id, lang)
                print(f"[{lang.upper()}] ❌ ID {post_id} translation failed — lease released.")

        except Exception as e:
            self.metrics["failed"] += 1
            self.lease_mgr.release_task(post_id, lang)
            print(f"[{lang.upper()}] ❌ Exception processing ID {post_id}: {e}")

    def flush_staging_to_remote(self) -> None:
        """Flushes staging queue to WordPress database in safe chunked batches."""
        with self.flush_lock:
            with self.staging_lock:
                if not self.staging:
                    return
                items_to_push = list(self.staging)
                self.staging.clear()

            CHUNK_SIZE = 10
            while items_to_push:
                chunk = items_to_push[:CHUNK_SIZE]
                print(f"\n🚀 [BRIDGE SYNC] Flushing sub-batch of {len(chunk)} translations to WordPress (remaining: {len(items_to_push)})...")
                t0 = time.time()
                res = run_bridge_command({"action": "save_batch", "items": chunk}, timeout=120)
                elapsed = time.time() - t0

                if res and res.get("success"):
                    results = res.get("results", [])
                    saved = sum(1 for r in results if r.get("status") in ["created", "updated"])
                    print(f"✅ [BRIDGE SYNC] Successfully persisted {saved}/{len(chunk)} items in {elapsed:.2f}s\n")
                    for item in chunk:
                        self.lease_mgr.complete_task(item["en_id"], item["lang"])
                    items_to_push = items_to_push[CHUNK_SIZE:]
                else:
                    print(f"⚠️ [BRIDGE SYNC] Batch sync warning or timeout. Re-queueing {len(items_to_push)} items...")
                    with self.staging_lock:
                        self.staging.extend(items_to_push)
                    break

    def run_swarm(
        self, 
        target_langs: List[str], 
        limit_per_lang: int = 10, 
        post_id: Optional[int] = None, 
        dry_run: bool = False,
        shard_id: int = 0,
        num_shards: int = 1,
        order: str = "asc",
        continuous: bool = False,
        delay: float = 2.0
    ) -> None:
        """Main swarm loop fanning out across languages and helmets in parallel."""
        print("=" * 70)
        print("   HELMETSAN MULTI-MODEL SWARM TRANSLATION ORCHESTRATOR")
        print(f"   Instance ID: {self.lease_mgr.instance_id} | Workers: {self.workers}")
        print(f"   Shard: {shard_id}/{num_shards} | Order: {order.upper()} | Continuous: {continuous}")
        print(f"   Target Locales: {', '.join(target_langs)} ({len(target_langs)} languages)")
        print(f"   Experiential Gateway: Active (GPT-5.6-Luna & DeepSeek-V4-Flash Fleet)")
        print("=" * 70)

        # 1. Clean up stale leases
        cleaned = self.lease_mgr.clean_expired_leases()
        if cleaned > 0:
            print(f"🧹 Cleaned {cleaned} expired task leases from earlier sessions.")

        empty_passes = 0

        while True:
            # 2. Fetch untranslated candidate helmets per language
            per_lang_candidates: Dict[str, List[Dict[str, Any]]] = {}

            print(f"\n🔍 Fetching candidates across target locales (Shard {shard_id}/{num_shards}, Order {order.upper()})...")
            for lang in target_langs:
                active_claimed = self.lease_mgr.get_active_claimed_ids(lang)
                payload = {
                    "action": "fetch_candidates",
                    "lang": lang,
                    "limit": limit_per_lang,
                    "shard_id": shard_id,
                    "num_shards": num_shards,
                    "order": order,
                    "exclude_ids": active_claimed
                }
                if post_id:
                    payload["post_id"] = post_id

                res = run_bridge_command(payload, timeout=60)
                if res and res.get("success"):
                    candidates = res.get("candidates", [])
                    unclaimed = self.lease_mgr.filter_unclaimed(candidates, lang)
                    per_lang_candidates[lang] = unclaimed
                    print(f"   [{lang.upper()}] Fetched {len(candidates)} candidates ({len(unclaimed)} unclaimed)")
                else:
                    per_lang_candidates[lang] = []

            # Interleave work units across languages so all workers run in parallel without single-language lockups
            work_items: List[Tuple[Dict[str, Any], str]] = []
            max_len = max((len(c_list) for c_list in per_lang_candidates.values()), default=0)

            for idx in range(max_len):
                for lang in target_langs:
                    if idx < len(per_lang_candidates[lang]):
                        work_items.append((per_lang_candidates[lang][idx], lang))

            if not work_items:
                empty_passes += 1
                if not continuous or post_id is not None:
                    print("🎉 No pending translation candidates found across the specified locales!")
                    break
                else:
                    if empty_passes % 5 == 0:
                        print(f"⏳ Waiting for candidates / leases to refresh in {delay * 3:.1f}s...")
                    time.sleep(delay * 3)
                    continue

            empty_passes = 0
            print(f"\n⚡ Initiating Parallel Swarm execution: {len(work_items)} total tasks across {self.workers} worker threads...")

            # 3. Parallel Swarm Execution (Thread pool)
            with ThreadPoolExecutor(max_workers=self.workers) as executor:
                futures = [
                    executor.submit(self.process_work_unit, candidate, lang, dry_run)
                    for candidate, lang in work_items
                ]
                for f in as_completed(futures):
                    pass # Wait for completion

            # 4. Flush remaining staged translations
            if not dry_run and self.staging:
                self.flush_staging_to_remote()

            if not continuous or post_id is not None:
                break

            time.sleep(delay)

        total_time = time.time() - self.metrics["start_time"]
        print("=" * 70)
        print(f"🏁 SWARM RUN COMPLETE: {self.metrics['translated']} translations saved, {self.metrics['failed']} failed.")
        print(f"⏱️  Total Duration: {total_time:.2f}s | TM Cache Hits: {self.metrics['tm_hits']}")
        print("=" * 70)


# ─────────────────────────────────────────────────────────────────────────────
# CLI Entrypoint
# ─────────────────────────────────────────────────────────────────────────────
def main():
    parser = argparse.ArgumentParser(description="Helmetsan Multi-Model Swarm Translation Bot")
    parser.add_argument("--langs", default="all", help="Comma-separated language codes or 'all'")
    parser.add_argument("--workers", type=int, default=6, help="Concurrent worker threads (default: 6)")
    parser.add_argument("--limit", type=int, default=15, help="Helmets per language to fetch (default: 15)")
    parser.add_argument("--batch-size", type=int, default=5, help="Batch size before flushing to remote WP (default: 5)")
    parser.add_argument("--post-id", type=int, default=None, help="Translate specific helmet post ID")
    parser.add_argument("--shard-id", type=int, default=0, help="Shard ID for multi-instance partitioning (default: 0)")
    parser.add_argument("--num-shards", type=int, default=1, help="Total number of parallel shards/instances (default: 1)")
    parser.add_argument("--order", choices=["asc", "desc", "rand"], default="asc", help="Ordering strategy (default: asc)")
    parser.add_argument("--continuous", action="store_true", help="Run continuously in background until catalog is fully translated")
    parser.add_argument("--delay", type=float, default=2.0, help="Seconds between batches in continuous mode (default: 2.0)")
    parser.add_argument("--dry-run", action="store_true", help="Perform translation without remote database persistence")
    parser.add_argument("--status", action="store_true", help="Print live catalog counts and active lease statistics")
    parser.add_argument("--instance-id", default=None, help="Custom bot instance identifier")
    args = parser.parse_args()

    if args.status:
        print("📊 Fetching live catalog statistics across all 10 language databases...")
        stats = run_bridge_command({"action": "stats"}, timeout=30)
        lease_mgr = SwarmLeaseManager()
        lease_stats = lease_mgr.get_stats()

        if stats and stats.get("success"):
            print("\nCatalog Status by Language:")
            langs = stats.get("languages", {})
            for code, count in langs.items():
                label = LANG_LABELS.get(code, code.upper())
                print(f"   [{code.upper()}] {label:<24}: {count:>6} helmets")
        print("\nSwarm Coordinator Status:")
        print(f"   Active Bot Instances : {len(lease_stats['active_instances'])}")
        print(f"   Active Leases In-Flight: {lease_stats['active_leases']}")
        print(f"   Completed Leases Tracked: {lease_stats['completed_tasks']}\n")
        return

    target_langs = ALL_TARGET_LANGS if args.langs == "all" else [l.strip() for l in args.langs.split(",") if l.strip()]

    orchestrator = SwarmTranslationOrchestrator(
        workers=args.workers,
        batch_size=args.batch_size,
        instance_id=args.instance_id
    )

    orchestrator.run_swarm(
        target_langs=target_langs,
        limit_per_lang=args.limit,
        post_id=args.post_id,
        dry_run=args.dry_run,
        shard_id=args.shard_id,
        num_shards=args.num_shards,
        order=args.order,
        continuous=args.continuous,
        delay=args.delay
    )


if __name__ == "__main__":
    main()
