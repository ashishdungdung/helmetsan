#!/usr/bin/env python3
"""
Helmetsan Multi-Engine Ingestion Controller
Production-grade submission and ledgering engine supporting:
1. IndexNow Universal Gateway (api.indexnow.org) -> Replicates to Bing, Yandex, Seznam, Naver, Yep
2. Direct Engine Endpoints: Bing, Yandex, Seznam, Naver
3. Baidu Ziyuan Real-Time Push API (for Simplified Chinese /zh/ catalog)
4. Durable submission recording in MariaDB `wp_helmetsan_indexnow_log`
"""

import sys
import os
import json
import time
import uuid
import hashlib
import argparse
import subprocess
import urllib.request
import urllib.error

HOST = "helmetsan.com"
INDEXNOW_KEY = "c9a72e8140db4e5fb3d6812975ef83a0"
KEY_LOCATION = f"https://{HOST}/{INDEXNOW_KEY}.txt"
REMOTE_HOST = "root@31.70.136.154"
DB_NAME = "wp_helmetsan_com"

ENDPOINTS = {
    "indexnow": "https://api.indexnow.org/indexnow",
    "bing":     "https://www.bing.com/indexnow",
    "yandex":   "https://yandex.com/indexnow",
    "seznam":   "https://search.seznam.cz/indexnow",
    "naver":    "https://searchadvisor.naver.com/indexnow",
}

def run_remote_query(sql):
    cmd = [
        "ssh", "-o", "ControlMaster=no", "-o", "ControlPath=none", "-o", "StrictHostKeyChecking=no",
        REMOTE_HOST,
        f"mariadb {DB_NAME} -e \"{sql}\""
    ]
    res = subprocess.run(cmd, capture_output=True, text=True, check=True)
    return res.stdout.strip()

def fetch_catalog_urls(lang_filter=None, post_type_filter=None):
    """Fetch localized URLs from production database."""
    print("📡 Fetching localized catalog posts from production database...")
    query = """
    SELECT p.ID, p.post_name, p.post_type, t.slug as lang
    FROM wp_posts p
    INNER JOIN wp_term_relationships tr ON p.ID = tr.object_id
    INNER JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'language'
    INNER JOIN wp_terms t ON tt.term_id = t.term_id
    WHERE p.post_type IN ('helmet', 'accessory', 'motorcycle', 'brand')
      AND p.post_status = 'publish'
    ORDER BY p.ID ASC;
    """
    cmd = [
        "ssh", "-o", "ControlMaster=no", "-o", "ControlPath=none", "-o", "StrictHostKeyChecking=no",
        REMOTE_HOST,
        f"mariadb {DB_NAME} -e \"{query}\" --skip-column-names"
    ]
    res = subprocess.run(cmd, capture_output=True, text=True, check=True)
    lines = res.stdout.strip().split("\n")
    print(f"📊 Retrieved {len(lines)} published localized post records.")

    cpt_routes = {
        'helmet': 'helmets',
        'accessory': 'accessories',
        'motorcycle': 'motorcycles',
        'brand': 'brands'
    }

    url_records = []
    # Primary hub pages
    hub_langs = ["en", "de", "zh", "fr", "es", "it", "pl", "pt", "nl", "ja"]
    if lang_filter and lang_filter != "all":
        hub_langs = [lang_filter]

    for l in hub_langs:
        prefix = "" if l == "en" else f"{l}/"
        for section in ["", "helmets/", "accessories/", "motorcycles/", "brands/", "comparison/"]:
            u = f"https://{HOST}/{prefix}{section}"
            url_records.append({
                "url": u,
                "post_id": 0,
                "lang": l,
                "post_type": "hub"
            })

    for line in lines:
        parts = line.strip().split("\t")
        if len(parts) >= 4:
            p_id, slug, p_type, p_lang = int(parts[0]), parts[1], parts[2], parts[3]
            if lang_filter and lang_filter != "all" and p_lang != lang_filter:
                continue
            if post_type_filter and p_type != post_type_filter:
                continue

            cpt_route = cpt_routes.get(p_type, p_type)
            if p_lang == "en":
                u = f"https://{HOST}/{cpt_route}/{slug}/"
            else:
                u = f"https://{HOST}/{p_lang}/{cpt_route}/{slug}/"

            url_records.append({
                "url": u,
                "post_id": p_id,
                "lang": p_lang,
                "post_type": p_type
            })

    # Deduplicate by URL
    seen = set()
    unique_records = []
    for r in url_records:
        if r["url"] not in seen:
            seen.add(r["url"])
            unique_records.append(r)

    print(f"✅ Filtered & deduplicated to {len(unique_records)} unique URLs.")
    return unique_records

def record_batch_in_db(batch_records, engine, batch_id, response_code, status):
    """Write submission ledger batch into MariaDB `wp_helmetsan_indexnow_log`."""
    if not batch_records:
        return

    values = []
    for r in batch_records:
        u = r["url"]
        u_hash = hashlib.sha256(u.encode("utf-8")).hexdigest()
        p_id = r.get("post_id", 0)
        p_id_sql = f"{p_id}" if p_id else "NULL"
        lang = r.get("lang", "en")[:2]
        esc_url = u.replace("'", "\\'")
        values.append(
            f"('{u_hash}', '{esc_url}', {p_id_sql}, '{lang}', '{engine}', {response_code}, '{batch_id}', '{status}', NOW())"
        )

    # Insert in chunks of 500 to keep SQL statement length manageable
    chunk_size = 500
    for i in range(0, len(values), chunk_size):
        chunk = values[i:i + chunk_size]
        sql = f"""
        INSERT INTO wp_helmetsan_indexnow_log (
            url_hash, url, post_id, lang, engine, response_code, batch_id, status, created_at
        ) VALUES {', '.join(chunk)};
        """
        try:
            run_remote_query(sql)
        except Exception as e:
            print(f"   ⚠️ DB ledger insert warning: {e}")

def submit_indexnow_batch(batch_records, engine_name, batch_num, total_batches, dry_run=False):
    endpoint = ENDPOINTS.get(engine_name, ENDPOINTS["indexnow"])
    url_list = [r["url"] for r in batch_records]
    batch_id = str(uuid.uuid4())

    if dry_run:
        print(f"🔍 [DRY-RUN] Would submit {len(url_list)} URLs to {engine_name} ({endpoint})")
        return True

    payload = {
        "host": HOST,
        "key": INDEXNOW_KEY,
        "keyLocation": KEY_LOCATION,
        "urlList": url_list
    }
    data = json.dumps(payload).encode("utf-8")
    req = urllib.request.Request(
        endpoint,
        data=data,
        headers={
            "Content-Type": "application/json; charset=utf-8",
            "User-Agent": f"Helmetsan-IndexNow-MultiEngine/2.0 ({engine_name})"
        }
    )

    print(f"⚡ [{engine_name.upper()}] [Batch {batch_num}/{total_batches}] Submitting {len(url_list)} URLs...")
    for attempt in range(1, 4):
        try:
            with urllib.request.urlopen(req, timeout=30) as resp:
                code = resp.getcode()
                print(f"   ✅ [{engine_name.upper()}] HTTP {code} OK / Accepted. ({len(url_list)} URLs)")
                record_batch_in_db(batch_records, engine_name, batch_id, code, "success")
                return True
        except urllib.error.HTTPError as e:
            code = e.code
            body = e.read().decode("utf-8")
            if code in (200, 202):
                print(f"   ✅ [{engine_name.upper()}] HTTP {code} Accepted.")
                record_batch_in_db(batch_records, engine_name, batch_id, code, "success")
                return True
            print(f"   ⚠️ [{engine_name.upper()}] HTTP {code} (Attempt {attempt}): {body[:120]}")
            if code == 429:
                sleep_sec = 10 * attempt
                print(f"   ⏳ Throttled. Backing off for {sleep_sec}s...")
                time.sleep(sleep_sec)
            else:
                time.sleep(3)
        except Exception as e:
            print(f"   ❌ [{engine_name.upper()}] Network error (Attempt {attempt}): {e}")
            time.sleep(3)

    record_batch_in_db(batch_records, engine_name, batch_id, 599, "failed")
    return False

def submit_baidu_batch(batch_records, token, batch_num, total_batches, dry_run=False):
    """Baidu real-time push API for Simplified Chinese catalog."""
    url_list = [r["url"] for r in batch_records]
    batch_id = str(uuid.uuid4())
    endpoint = f"http://data.zz.baidu.com/urls?site=https://{HOST}&token={token}"

    if dry_run:
        print(f"🔍 [DRY-RUN] Would submit {len(url_list)} Chinese URLs to Baidu API")
        return True

    payload = "\n".join(url_list).encode("utf-8")
    req = urllib.request.Request(
        endpoint,
        data=payload,
        headers={
            "Content-Type": "text/plain",
            "User-Agent": "Helmetsan-Baidu-Ingest/2.0"
        }
    )

    print(f"⚡ [BAIDU] [Batch {batch_num}/{total_batches}] Pushing {len(url_list)} URLs...")
    for attempt in range(1, 4):
        try:
            with urllib.request.urlopen(req, timeout=30) as resp:
                code = resp.getcode()
                body = resp.read().decode("utf-8")
                print(f"   ✅ [BAIDU] HTTP {code}: {body}")
                record_batch_in_db(batch_records, "baidu", batch_id, code, "success")
                return True
        except urllib.error.HTTPError as e:
            code = e.code
            body = e.read().decode("utf-8")
            print(f"   ⚠️ [BAIDU] HTTP {code}: {body}")
            record_batch_in_db(batch_records, "baidu", batch_id, code, "failed")
            return False
        except Exception as e:
            print(f"   ❌ [BAIDU] Network error (Attempt {attempt}): {e}")
            time.sleep(3)

    record_batch_in_db(batch_records, "baidu", batch_id, 599, "failed")
    return False

def print_ledger_summary():
    print("\n📋 Current MariaDB IndexNow & Ingestion Ledger Summary:")
    sql = """
    SELECT
        engine,
        status,
        COUNT(*) as total_urls,
        COUNT(DISTINCT batch_id) as total_batches,
        MIN(created_at) as first_sub,
        MAX(created_at) as latest_sub
    FROM wp_helmetsan_indexnow_log
    GROUP BY engine, status
    ORDER BY engine, status;
    """
    out = run_remote_query(sql)
    print(out)

def main():
    parser = argparse.ArgumentParser(description="Helmetsan Multi-Engine Ingestion Controller.")
    parser.add_argument("--engine", choices=["indexnow", "bing", "yandex", "seznam", "naver", "all_direct", "baidu"],
                        default="indexnow", help="Target search engine endpoint (default: indexnow universal gateway)")
    parser.add_argument("--lang", default="all", help="Language code filter (e.g. en, de, zh, ja, all)")
    parser.add_argument("--post-type", choices=["helmet", "accessory", "motorcycle", "brand"], help="Filter by CPT")
    parser.add_argument("--limit", type=int, help="Limit number of URLs to submit")
    parser.add_argument("--batch-size", type=int, default=5000, help="Batch size per request (default: 5000, max 10000)")
    parser.add_argument("--baidu-token", type=str, default=os.getenv("BAIDU_ZIYUAN_TOKEN"), help="Baidu Ziyuan Webmaster token")
    parser.add_argument("--dry-run", action="store_true", help="Simulate run without sending HTTP requests")
    parser.add_argument("--ledger-summary", action="store_true", help="Print MariaDB submission ledger summary and exit")
    args = parser.parse_args()

    if args.ledger_summary:
        print_ledger_summary()
        return

    records = fetch_catalog_urls(lang_filter=args.lang, post_type_filter=args.post_type)
    if args.limit:
        records = records[:args.limit]
        print(f"⚠️ Limited to {args.limit} URLs.")

    batch_size = min(max(1, args.batch_size), 10000)
    batches = [records[i:i + batch_size] for i in range(0, len(records), batch_size)]
    print(f"📦 Partitioned into {len(batches)} batches of up to {batch_size} URLs each.")

    if args.engine == "baidu":
        if not args.baidu_token and not args.dry_run:
            print("⚠️ Baidu token not provided via --baidu-token or BAIDU_ZIYUAN_TOKEN env. Enqueuing with dry-run mode.")
            args.dry_run = True
        zh_records = [r for r in records if r["lang"] == "zh"]
        zh_batches = [zh_records[i:i + 2000] for i in range(0, len(zh_records), 2000)]
        print(f"🇨🇳 Found {len(zh_records)} Simplified Chinese URLs for Baidu across {len(zh_batches)} batches.")
        for idx, batch in enumerate(zh_batches, 1):
            submit_baidu_batch(batch, args.baidu_token or "dry_run_token", idx, len(zh_batches), args.dry_run)
            time.sleep(1)
        print_ledger_summary()
        return

    engines_to_run = [args.engine]
    if args.engine == "all_direct":
        engines_to_run = ["bing", "yandex", "seznam"]

    for eng in engines_to_run:
        print(f"\n🚀 Launching ingestion surge for provider: {eng.upper()}...")
        for idx, batch in enumerate(batches, 1):
            submit_indexnow_batch(batch, eng, idx, len(batches), args.dry_run)
            if not args.dry_run:
                time.sleep(2)  # Healthy inter-batch breathing window

    print_ledger_summary()

if __name__ == "__main__":
    main()
