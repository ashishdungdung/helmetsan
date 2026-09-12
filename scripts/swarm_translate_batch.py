#!/usr/bin/env python3
"""
Silicon Swarm Batch Translation Engine for Helmetsan
Supports:
  - Node A (Local M4 Pro @ 127.0.0.1:1234)        – fast instruct models
  - Node B (Worker M3 Pro @ 192.168.2.223:1235)   – Qwen2.5 / Bonsai-27B
  - --swarm mode: dispatches helmets to BOTH nodes concurrently via ThreadPoolExecutor

Usage examples:
  python swarm_translate_batch.py --lang de --batch-size 10 --node node_a
  python swarm_translate_batch.py --lang de --batch-size 20 --swarm
  python swarm_translate_batch.py --lang zh --batch-size 5 --swarm --force-en-ids 101,202,303

NOTE: Never unloads or changes the loaded model in LM Studio.
"""

import sys
import os
import json
import time
import re
import argparse
import urllib.request
import urllib.error
import subprocess
import threading
from concurrent.futures import ThreadPoolExecutor, as_completed
from queue import Queue, Empty

REMOTE_SSH_HOST = "root@31.70.136.154"
REMOTE_WP_PATH  = "/var/www/helmetsan.com/public"

NODE_CONFIGS = {
    "node_a": {
        "name":          "Node A - M4 Pro (Local)",
        "base_url":      "http://127.0.0.1:1234/v1",
        "api_key":       None,
        "default_model": "qwen/qwen3.8-27b",
        "timeout":       180.0,
        "max_tokens":    2048,
    },
    "node_b": {
        "name":          "Node B - M3 Pro (Worker)",
        "base_url":      "http://192.168.2.223:1235/v1",
        "api_key":       "sk-lm-CVQsP6r0:MOCDnKCodyTrTwrfdgiU",
        "default_model": "qwen2.5-7b-instruct",
        "timeout":       120.0,
        "max_tokens":    1000,
    },
}

# Thread-safe print lock
_print_lock = threading.Lock()

def tprint(*args, **kwargs):
    with _print_lock:
        print(*args, **kwargs)

def get_loaded_model(node_cfg):
    """Detect the currently loaded model on the target node without altering memory."""
    try:
        url = f"{node_cfg['base_url']}/models"
        req = urllib.request.Request(url)
        if node_cfg.get("api_key"):
            req.add_header("Authorization", f"Bearer {node_cfg['api_key']}")
        with urllib.request.urlopen(req, timeout=5.0) as res:
            data = json.loads(res.read().decode())
            models = data.get("data", [])
            if models:
                # If preferred model is loaded, use it
                for m in models:
                    if m["id"] == node_cfg["default_model"]:
                        return m["id"]
                return models[0]["id"]
    except Exception as e:
        tprint(f"Warning: Could not detect model from {node_cfg['base_url']}/models ({e}). Using default: {node_cfg['default_model']}")
    return node_cfg["default_model"]

def call_lm_studio(node_cfg, model, prompt):
    """Call LM Studio chat completions endpoint with node-specific timeouts and auth."""
    payload = {
        "model": model,
        "messages": [
            {
                "role": "user",
                "content": prompt
            }
        ],
        "temperature": 0.1,
        "max_tokens": node_cfg["max_tokens"]
    }
    
    url = f"{node_cfg['base_url']}/chat/completions"
    headers = {"Content-Type": "application/json"}
    if node_cfg.get("api_key"):
        headers["Authorization"] = f"Bearer {node_cfg['api_key']}"
        
    req = urllib.request.Request(
        url,
        data=json.dumps(payload).encode("utf-8"),
        headers=headers
    )
    
    t0 = time.time()
    try:
        with urllib.request.urlopen(req, timeout=node_cfg["timeout"]) as response:
            result = json.loads(response.read().decode("utf-8"))
            elapsed = time.time() - t0
            choice = result["choices"][0]["message"]
            content = choice.get("content", "").strip()
            # If model returned reasoning instead of direct content:
            if not content and "reasoning_content" in choice:
                content = choice["reasoning_content"].strip()
            return content, elapsed
    except Exception as e:
        print(f"❌ Error communicating with LM Studio on {node_cfg['name']}: {e}")
        return None, 0

def clean_json_output(raw_text):
    """Extract and parse JSON from model output."""
    if not raw_text:
        return None
    # Strip markdown fences
    text = re.sub(r"^```(?:json)?\s*", "", raw_text.strip(), flags=re.MULTILINE)
    text = re.sub(r"\s*```$", "", text.strip(), flags=re.MULTILINE)
    
    match = re.search(r"\{[\s\S]*\}", text)
    if match:
        try:
            return json.loads(match.group(0))
        except Exception:
            pass
    try:
        return json.loads(text)
    except Exception:
        return None

def fetch_candidate_helmets(limit, target_lang, force_ids=None):
    """Fetch published English helmets missing translations in target_lang (or specific force_ids)."""
    php_code = f"""<?php
    $forceIds = {repr(force_ids or [])};
    if (!empty($forceIds)) {{
        $candidates = [];
        foreach ($forceIds as $enId) {{
            $h = get_post($enId);
            if ($h) {{
                $candidates[] = [
                    'id' => $h->ID,
                    'title' => $h->post_title,
                    'slug' => $h->post_name,
                    'content' => $h->post_content,
                    'excerpt' => $h->post_excerpt,
                    'marketing' => get_post_meta($h->ID, 'marketing_description', true),
                    'tech' => get_post_meta($h->ID, 'technical_analysis', true),
                    'features' => json_decode((string)get_post_meta($h->ID, 'features_json', true), true) ?: [],
                    'sizing_fit' => json_decode((string)get_post_meta($h->ID, 'sizing_fit_json', true), true) ?: []
                ];
            }}
        }}
        echo json_encode($candidates);
        exit;
    }}

    $helmets = get_posts([
        'post_type' => 'helmet',
        'post_status' => 'publish',
        'posts_per_page' => 200,
        'tax_query' => [
            [
                'taxonomy' => 'language',
                'field' => 'slug',
                'terms' => 'en'
            ]
        ]
    ]);
    $candidates = [];
    foreach ($helmets as $h) {{
        $transId = function_exists('pll_get_post') ? pll_get_post($h->ID, '{target_lang}') : 0;
        if (!$transId) {{
            $candidates[] = [
                'id' => $h->ID,
                'title' => $h->post_title,
                'slug' => $h->post_name,
                'content' => $h->post_content,
                'excerpt' => $h->post_excerpt,
                'marketing' => get_post_meta($h->ID, 'marketing_description', true),
                'tech' => get_post_meta($h->ID, 'technical_analysis', true),
                'features' => json_decode((string)get_post_meta($h->ID, 'features_json', true), true) ?: [],
                'sizing_fit' => json_decode((string)get_post_meta($h->ID, 'sizing_fit_json', true), true) ?: []
            ];
            if (count($candidates) >= {limit}) {{
                break;
            }}
        }}
    }}
    echo json_encode($candidates);
    """
    cmd = ["ssh", REMOTE_SSH_HOST, f"wp --path={REMOTE_WP_PATH} eval-file - --allow-root"]
    res = subprocess.run(cmd, input=php_code, capture_output=True, text=True)
    if res.returncode != 0:
        print(f"❌ Failed to fetch candidate helmets: {res.stderr}")
        return []
    try:
        return json.loads(res.stdout.strip())
    except Exception as e:
        print(f"❌ Failed to parse candidate helmets JSON: {e}\nRaw output: {res.stdout}")
        return []

def save_translation_to_wp(en_id, target_lang, trans_data, base_slug):
    """Ingest translated helmet into WordPress on production (updating if already exists)."""
    raw_title = trans_data.get("title", "")
    clean_title_slug = re.sub(r"[^a-zA-Z0-9\-]", "-", raw_title.lower()).strip("-")
    clean_title_slug = re.sub(r"-+", "-", clean_title_slug)
    
    if clean_title_slug and len(clean_title_slug) > 3 and not re.search(r"[\x80-\xff]", clean_title_slug):
        target_slug = clean_title_slug
    else:
        target_slug = f"{base_slug}-{target_lang}"

    payload = {
        "en_id": en_id,
        "lang": target_lang,
        "title": trans_data.get("title", ""),
        "slug": target_slug,
        "content": trans_data.get("description", trans_data.get("content", "")),
        "excerpt": trans_data.get("excerpt", ""),
        "marketing": trans_data.get("marketing_description", ""),
        "tech": trans_data.get("technical_analysis", ""),
        "features": trans_data.get("features", []),
        "sizing_fit": trans_data.get("sizing_fit", {})
    }

    php_code = f"""<?php
    $data = json_decode({repr(json.dumps(payload))}, true);
    $enId = (int)$data['en_id'];
    $lang = $data['lang'];
    $existingId = function_exists('pll_get_post') ? (int) pll_get_post($enId, $lang) : 0;
    
    $postData = [
        'post_type'    => 'helmet',
        'post_title'   => sanitize_text_field($data['title']),
        'post_name'    => sanitize_title($data['slug']),
        'post_content' => wp_kses_post($data['content']),
        'post_excerpt' => sanitize_text_field($data['excerpt']),
        'post_status'  => 'publish'
    ];
    
    if ($existingId > 0) {{
        $postData['ID'] = $existingId;
        $newId = wp_update_post($postData);
    }} else {{
        $newId = wp_insert_post($postData);
    }}

    if (is_wp_error($newId) || !$newId) {{
        echo json_encode(['success' => false, 'error' => 'wp_insert_post/update failed']);
        exit;
    }}
    
    // Set Polylang language
    pll_set_post_language($newId, $lang);
    
    // Bi-directional link
    $translations = pll_get_post_translations($enId);
    $translations['en'] = $enId;
    $translations[$lang] = $newId;
    pll_save_post_translations($translations);
    
    // Copy/sync metadata from master English post
    $meta = get_post_meta($enId);
    foreach ($meta as $k => $vals) {{
        foreach ($vals as $v) {{
            if ($lang !== 'zh' && in_array($k, ['_yoast_wpseo_title', '_yoast_wpseo_metadesc'], true)) {{
                if (is_string($v) && preg_match('/[\\\\x{{4e00}}-\\\\x{{9fff}}]/u', $v)) {{
                    continue;
                }}
            }}
            update_post_meta($newId, $k, maybe_unserialize($v));
        }}
    }}
    
    // Assign specific translated localized meta
    if (!empty($data['marketing'])) {{
        update_post_meta($newId, 'marketing_description', $data['marketing']);
    }}
    if (!empty($data['tech'])) {{
        update_post_meta($newId, 'technical_analysis', $data['tech']);
    }}
    if (!empty($data['features'])) {{
        update_post_meta($newId, 'features_json', json_encode($data['features'], JSON_UNESCAPED_UNICODE));
    }}
    if (!empty($data['sizing_fit'])) {{
        update_post_meta($newId, 'sizing_fit_json', json_encode($data['sizing_fit'], JSON_UNESCAPED_UNICODE));
    }}
    
    // Copy taxonomies
    $taxonomies = get_object_taxonomies('helmet');
    foreach ($taxonomies as $tax) {{
        if ($tax === 'language' || $tax === 'post_translations') continue;
        $terms = wp_get_object_terms($enId, $tax, ['fields' => 'ids']);
        if (!empty($terms) && !is_wp_error($terms)) {{
            $targetTerms = [];
            foreach ($terms as $termId) {{
                $transTermId = function_exists('pll_get_term') ? pll_get_term($termId, $lang) : 0;
                $targetTerms[] = $transTermId > 0 ? $transTermId : $termId;
            }}
            wp_set_object_terms($newId, $targetTerms, $tax);
        }}
    }}

    $permalink = get_permalink($newId);
    echo json_encode([
        'success'   => true,
        'new_id'    => $newId,
        'permalink' => $permalink
    ]);
    """
    cmd = ["ssh", REMOTE_SSH_HOST, f"wp --path={REMOTE_WP_PATH} eval-file - --allow-root"]
    res = subprocess.run(cmd, input=php_code, capture_output=True, text=True)
    if res.returncode != 0:
        return {"success": False, "error": res.stderr}
    try:
        return json.loads(res.stdout.strip())
    except Exception as e:
        return {"success": False, "error": f"Failed to parse WP output: {e}\nRaw: {res.stdout}"}

# ─────────────────────────────────────────────
#  Prompt builder
# ─────────────────────────────────────────────
def build_translation_prompt(helmet, lang):
    title    = helmet["title"]
    features = helmet.get("features", [])
    sizing   = helmet.get("sizing_fit", {})

    if lang == "de":
        return f"""You are a professional German motorcycle gear catalog translator. Translate the following product details into authentic German (de_DE).
Do NOT translate the brand name or model number ("{title}").
Use industry-standard German motorcycle terms (e.g. "Klapphelm" for modular, "Integralhelm" for full face, "Sonnenblende" for sun visor, "Doppel-D-Ring").

Title: {title}
Description: {helmet.get('content') or 'Geschaffen für endlose Reisen mit maximaler Sicherheit.'}
Excerpt: {helmet.get('excerpt') or 'Premium Touringhelm.'}
Marketing: {helmet.get('marketing') or ''}
Technical: {helmet.get('tech') or ''}
Features: {json.dumps(features)}
FitNotes: {sizing.get('fit_notes', '')}

Output ONLY a valid JSON object with keys:
"title", "description", "excerpt", "marketing_description", "technical_analysis", "features", "fit_notes"."""
    elif lang == "fr":
        return f"""You are a professional French motorcycle gear catalog translator. Translate the following product details into authentic French (fr_FR).
Do NOT translate the brand name or model number ("{title}").
Use industry-standard French motorcycle terms (e.g. "Casque modulable" for modular, "Casque intégral" for full face, "Écran solaire" for sun visor, "Boucle double D").

Title: {title}
Description: {helmet.get('content') or 'Conçu pour les longs trajets avec une sécurité maximale.'}
Excerpt: {helmet.get('excerpt') or 'Casque touring haut de gamme.'}
Marketing: {helmet.get('marketing') or ''}
Technical: {helmet.get('tech') or ''}
Features: {json.dumps(features)}
FitNotes: {sizing.get('fit_notes', '')}

Output ONLY a valid JSON object with keys:
"title", "description", "excerpt", "marketing_description", "technical_analysis", "features", "fit_notes"."""
    elif lang == "es":
        return f"""You are a professional Spanish motorcycle gear catalog translator. Translate the following product details into authentic Spanish (es_ES).
Do NOT translate the brand name or model number ("{title}").
Use industry-standard Spanish motorcycle terms (e.g. "Casco modular" for modular, "Casco integral" for full face, "Visor solar" for sun visor, "Cierre doble D").

Title: {title}
Description: {helmet.get('content') or 'Diseñado para viajes largos con la máxima seguridad.'}
Excerpt: {helmet.get('excerpt') or 'Casco touring de alta gama.'}
Marketing: {helmet.get('marketing') or ''}
Technical: {helmet.get('tech') or ''}
Features: {json.dumps(features)}
FitNotes: {sizing.get('fit_notes', '')}

Output ONLY a valid JSON object with keys:
"title", "description", "excerpt", "marketing_description", "technical_analysis", "features", "fit_notes"."""
    elif lang == "it":
        return f"""You are a professional Italian motorcycle gear catalog translator. Translate the following product details into authentic Italian (it_IT).
Do NOT translate the brand name or model number ("{title}").
Use industry-standard Italian motorcycle terms (e.g. "Casco modulare" for modular, "Casco integrale" for full face, "Visierino parasole" for sun visor, "Chiusura a doppia D").

Title: {title}
Description: {helmet.get('content') or 'Progettato per lunghi viaggi con la massima sicurezza.'}
Excerpt: {helmet.get('excerpt') or 'Casco touring premium.'}
Marketing: {helmet.get('marketing') or ''}
Technical: {helmet.get('tech') or ''}
Features: {json.dumps(features)}
FitNotes: {sizing.get('fit_notes', '')}

Output ONLY a valid JSON object with keys:
"title", "description", "excerpt", "marketing_description", "technical_analysis", "features", "fit_notes"."""
    elif lang == "pl":
        return f"""You are a professional Polish motorcycle gear catalog translator. Translate the following product details into authentic Polish (pl_PL).
Do NOT translate the brand name or model number ("{title}").
Use industry-standard Polish motorcycle terms (e.g. "Kask szczękowy" for modular, "Kask integralny" for full face, "Blenda przeciwsłoneczna" for sun visor, "Zapięcie DD").

Title: {title}
Description: {helmet.get('content') or 'Stworzony na dalekie trasy z zachowaniem maksymalnego bezpieczeństwa.'}
Excerpt: {helmet.get('excerpt') or 'Kask turystyczny klasy premium.'}
Marketing: {helmet.get('marketing') or ''}
Technical: {helmet.get('tech') or ''}
Features: {json.dumps(features)}
FitNotes: {sizing.get('fit_notes', '')}

Output ONLY a valid JSON object with keys:
"title", "description", "excerpt", "marketing_description", "technical_analysis", "features", "fit_notes"."""
    elif lang == "pt":
        return f"""You are a professional Portuguese motorcycle gear catalog translator. Translate the following product details into authentic Portuguese (pt_PT).
Do NOT translate the brand name or model number ("{title}").
Use industry-standard Portuguese motorcycle terms (e.g. "Capacete modular" for modular, "Capacete integral" for full face, "Viseira solar" for sun visor, "Fecho duplo D").

Title: {title}
Description: {helmet.get('content') or 'Concebido para viagens longas com a máxima segurança.'}
Excerpt: {helmet.get('excerpt') or 'Capacete de turismo premium.'}
Marketing: {helmet.get('marketing') or ''}
Technical: {helmet.get('tech') or ''}
Features: {json.dumps(features)}
FitNotes: {sizing.get('fit_notes', '')}

Output ONLY a valid JSON object with keys:
"title", "description", "excerpt", "marketing_description", "technical_analysis", "features", "fit_notes"."""
    elif lang == "nl":
        return f"""You are a professional Dutch motorcycle gear catalog translator. Translate the following product details into authentic Dutch (nl_NL).
Do NOT translate the brand name or model number ("{title}").
Use industry-standard Dutch motorcycle terms (e.g. "Systeemhelm" for modular, "Integraalhelm" for full face, "Zonnevizier" for sun visor, "Dubbel-D sluiting").

Title: {title}
Description: {helmet.get('content') or 'Ontworpen voor lange reizen met maximale veiligheid.'}
Excerpt: {helmet.get('excerpt') or 'Premium touringhelm.'}
Marketing: {helmet.get('marketing') or ''}
Technical: {helmet.get('tech') or ''}
Features: {json.dumps(features)}
FitNotes: {sizing.get('fit_notes', '')}

Output ONLY a valid JSON object with keys:
"title", "description", "excerpt", "marketing_description", "technical_analysis", "features", "fit_notes"."""
    elif lang == "ja":
        return f"""You are a professional Japanese motorcycle gear catalog translator. Translate the following product details into natural, authentic Japanese (日本語).
Do NOT translate the brand name or model number ("{title}").
Use industry-standard Japanese motorcycle gear terms (e.g. "システムヘルメット" for modular, "フルフェイス" for full face, "インナーサンバイザー" for sun visor, "Dリング").

Title: {title}
Description: {helmet.get('content') or '最高の安全性と快適性を備えたロングツーリングモデル。'}
Excerpt: {helmet.get('excerpt') or 'プレミアムツーリングヘルメット。'}
Marketing: {helmet.get('marketing') or ''}
Technical: {helmet.get('tech') or ''}
Features: {json.dumps(features)}
FitNotes: {sizing.get('fit_notes', '')}

Output ONLY a valid JSON object with keys:
"title", "description", "excerpt", "marketing_description", "technical_analysis", "features", "fit_notes"."""
    else:  # zh
        return f"""You are an expert motorcycle gear catalog translator. Translate the following product details into Simplified Chinese (中文).
Keep brand names and model numbers untranslated.

Title: {title}
Description: {helmet.get('content') or 'Premium motorcycle helmet.'}
Excerpt: {helmet.get('excerpt') or 'Premium touring helmet.'}
Marketing: {helmet.get('marketing') or ''}
Technical: {helmet.get('tech') or ''}
Features: {json.dumps(features)}
FitNotes: {sizing.get('fit_notes', '')}

Output ONLY a valid JSON object with keys:
"title", "description", "excerpt", "marketing_description", "technical_analysis", "features", "fit_notes"."""


# ─────────────────────────────────────────────
#  Core worker (single helmet, any node)
# ─────────────────────────────────────────────
def translate_one(helmet, lang, node_cfg, model, result_list):
    """Translate a single helmet on the given node and save to WordPress."""
    h_id  = helmet["id"]
    title = helmet["title"]
    slug  = helmet["slug"]
    node  = node_cfg["name"]

    prompt = build_translation_prompt(helmet, lang)
    tprint(f"  ⚙️  [{node}] Translating #{h_id}: \"{title}\"")

    raw_output, elapsed = call_lm_studio(node_cfg, model, prompt)
    parsed = clean_json_output(raw_output)

    if not parsed or "description" not in parsed:
        tprint(f"  ❌ [{node}] JSON parse failed for #{h_id}. Skipping.")
        result_list.append({"en_id": h_id, "en_title": title, "status": "FAILED",
                             "error": "Model JSON parse failure", "node": node})
        return

    tprint(f"  ✅ [{node}] #{h_id} translated in {elapsed:.1f}s  →  saving to WP...")
    wp_res = save_translation_to_wp(h_id, lang, parsed, slug)

    if wp_res.get("success"):
        tprint(f"  🌐 [{node}] #{h_id} → {wp_res['permalink']}")
        result_list.append({
            "en_id":      h_id,
            "en_title":   title,
            "trans_id":   wp_res["new_id"],
            "trans_title":parsed.get("title"),
            "trans_url":  wp_res["permalink"],
            "status":     "SUCCESS",
            "time_sec":   round(elapsed, 1),
            "node":       node,
        })
    else:
        tprint(f"  ❌ [{node}] WP save failed for #{h_id}: {wp_res.get('error')}")
        result_list.append({"en_id": h_id, "en_title": title, "status": "FAILED",
                             "error": wp_res.get("error"), "node": node})


# ─────────────────────────────────────────────
#  Swarm mode: parallel dispatch across 2 nodes
# ─────────────────────────────────────────────
def run_swarm(candidates, lang, results):
    """
    Distribute helmets across Node A and Node B concurrently.
    Jobs are pulled from a shared thread-safe Queue; both node workers
    run simultaneously via ThreadPoolExecutor.
    """
    job_queue = Queue()
    for helmet in candidates:
        job_queue.put(helmet)

    # Detect models once per node (serial, before spawning threads)
    node_models = {}
    for key in ["node_a", "node_b"]:
        cfg   = NODE_CONFIGS[key]
        model = get_loaded_model(cfg)
        node_models[key] = model
        print(f"  🔍 {cfg['name']} → active model: {model}")

    def node_worker(node_key):
        cfg   = NODE_CONFIGS[node_key]
        model = node_models[node_key]
        tprint(f"\n🟢 {cfg['name']} worker started.")
        while True:
            try:
                helmet = job_queue.get_nowait()
            except Empty:
                break
            translate_one(helmet, lang, cfg, model, results)
            job_queue.task_done()
        tprint(f"🏴 {cfg['name']} worker finished.")

    with ThreadPoolExecutor(max_workers=2, thread_name_prefix="swarm") as executor:
        futures = {
            executor.submit(node_worker, "node_a"): "node_a",
            executor.submit(node_worker, "node_b"): "node_b",
        }
        for future in as_completed(futures):
            exc = future.exception()
            if exc:
                tprint(f"⚠️  Worker {futures[future]} raised exception: {exc}")


# ─────────────────────────────────────────────
#  Sequential single-node mode
# ─────────────────────────────────────────────
def run_single_node(candidates, lang, node_cfg, results, model_override=None):
    model = model_override or get_loaded_model(node_cfg)
    print(f"  🔍 Active model: {model}\n")
    for idx, helmet in enumerate(candidates, 1):
        print(f"[{idx}/{len(candidates)}] #{helmet['id']}: \"{helmet['title']}\"")
        translate_one(helmet, lang, node_cfg, model, results)
        print()


# ─────────────────────────────────────────────
#  Entry point
# ─────────────────────────────────────────────
def main():
    parser = argparse.ArgumentParser(description="Helmetsan Silicon Swarm Batch Translation")
    parser.add_argument("--batch-size",   type=int, default=10,   help="Helmets to process (default 10)")
    parser.add_argument("--lang",         type=str, default="de",
                        choices=["de", "zh", "fr", "es", "it", "pl", "pt", "nl", "ja"],
                        help="Target language (de, zh, fr, es, it, pl, pt, nl, ja)")
    parser.add_argument("--model",        type=str, default=None,
                        help="Explicit model identifier to use (e.g. qwen/qwen3.8-27b or google/gemma-3-4b)")
    parser.add_argument("--node",         type=str, default="node_a",
                        choices=["node_a", "node_b", "bonsai"],
                        help="Single compute node (ignored when --swarm is set)")
    parser.add_argument("--swarm",        action="store_true",
                        help="Distribute helmets across Node A AND Node B concurrently")
    parser.add_argument("--force-en-ids", type=str, default=None,
                        help="Comma-separated EN post IDs to (re)translate")
    args = parser.parse_args()

    force_ids = ([int(x.strip()) for x in args.force_en_ids.split(",") if x.strip()]
                 if args.force_en_ids else None)

    mode_label = "PARALLEL SWARM (Node A + Node B)" if args.swarm else f"Single Node ({args.node})"
    print("\n================================================================")
    print(f"🚀 HELMETSAN SILICON SWARM TRANSLATION  –  TARGET [{args.lang.upper()}]")
    print(f"🖥️  Mode: {mode_label}")
    if args.model:
        print(f"🧠 Model: {args.model}")
    print("================================================================\n")

    # 1 – Fetch candidates
    print(f"📦 [1/3] Fetching up to {args.batch_size} candidate(s) needing [{args.lang}] translation...")
    candidates = fetch_candidate_helmets(args.batch_size, args.lang, force_ids)
    print(f"  → Found {len(candidates)} helmet(s) to process.\n")

    if not candidates:
        print("✅ No helmets requiring translation. Everything is up to date!")
        return

    results     = []
    total_start = time.time()

    # 2 – Translate
    if args.swarm:
        print("🔍 [2/3] Detecting models on both nodes...\n")
        run_swarm(candidates, args.lang, results)
    else:
        node_key = "node_b" if args.node in ["node_b", "bonsai"] else "node_a"
        node_cfg = NODE_CONFIGS[node_key]
        print(f"🔍 [2/3] Using {node_cfg['name']}...")
        run_single_node(candidates, args.lang, node_cfg, results, model_override=args.model)

    # 3 – Cache purge
    print("🧹 [3/3] Purging Nginx FastCGI microcache and WordPress object cache...")
    subprocess.run(
        ["ssh", REMOTE_SSH_HOST,
         "rm -rf /var/cache/nginx/* /dev/shm/nginx_cache/* && "
         f"wp --path={REMOTE_WP_PATH} cache flush --allow-root && "
         "systemctl reload nginx"],
        capture_output=True
    )

    # Summary
    total_time = time.time() - total_start
    successes  = [r for r in results if r.get("status") == "SUCCESS"]
    failures   = [r for r in results if r.get("status") == "FAILED"]

    print("\n================================================================")
    print("🏁 SWARM TRANSLATION BATCH COMPLETE")
    print("================================================================")
    print(f"  Total Processed : {len(results)}")
    print(f"  Successful      : {len(successes)}")
    print(f"  Failed          : {len(failures)}")
    print(f"  Total Wall Time : {total_time:.1f}s")

    if args.swarm and successes:
        print("\n  Per-node throughput:")
        for key in ["node_a", "node_b"]:
            node_name = NODE_CONFIGS[key]["name"]
            node_ok   = [r for r in successes if r.get("node") == node_name]
            avg_t     = (sum(r["time_sec"] for r in node_ok) / len(node_ok)) if node_ok else 0
            print(f"    {node_name}: {len(node_ok)} translated  (avg {avg_t:.1f}s each)")

    if successes:
        print("\n  ✅ Translated URLs:")
        for r in successes:
            print(f"    {r.get('node','?')} → {r['trans_url']}")

    if failures:
        print("\n  ❌ Failures:")
        for r in failures:
            print(f"    EN #{r['en_id']} \"{r['en_title']}\": {r.get('error', 'unknown')}")

    print("================================================================\n")


if __name__ == "__main__":
    main()
