#!/usr/bin/env python3
"""
Helmetsan IndexNow Surge Submitter
Submits canonical and localized URLs to the IndexNow protocol (Bing, Yandex, Seznam, Naver)
in compliant batches of up to 10,000 URLs per request.
"""

import sys
import os
import json
import time
import urllib.request
import urllib.error
import sqlite3

HOST = "helmetsan.com"
API_KEY = "c9a72e8140db4e5fb3d6812975ef83a0"
KEY_LOCATION = f"https://{HOST}/{API_KEY}.txt"
INDEXNOW_ENDPOINT = "https://api.indexnow.org/indexnow"
BATCH_SIZE = 5000  # Conservative safe batch size below 10,000 limit

def load_canonical_urls_from_server():
    """
    Query the WordPress database directly via remote SSH or local script to collect
    all published helmet, accessory, motorcycle, and brand URLs across all 10 languages.
    """
    import subprocess
    cmd = [
        "ssh", "-o", "ControlMaster=no", "-o", "ControlPath=none", "-o", "StrictHostKeyChecking=no",
        "root@31.70.136.154",
        """cd /var/www/helmetsan.com/public && sudo -u www-data wp db query "
        SELECT p.ID, p.post_name, p.post_type, t.slug as lang
        FROM wp_posts p
        INNER JOIN wp_term_relationships tr ON p.ID = tr.object_id
        INNER JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'language'
        INNER JOIN wp_terms t ON tt.term_id = t.term_id
        WHERE p.post_type IN ('helmet', 'accessory', 'motorcycle', 'brand')
          AND p.post_status = 'publish'
        ORDER BY p.ID ASC;
        " --skip-column-names"""
    ]
    print("📡 Fetching localized catalog posts from production database...")
    res = subprocess.run(cmd, capture_output=True, text=True, check=True)
    lines = res.stdout.strip().split("\n")
    print(f"📊 Retrieved {len(lines)} published localized post records.")

    urls = []
    # Primary hub pages across all 10 languages
    langs = ["", "de/", "zh/", "fr/", "es/", "it/", "pl/", "pt/", "nl/", "ja/"]
    for lang_prefix in langs:
        urls.append(f"https://{HOST}/{lang_prefix}")
        urls.append(f"https://{HOST}/{lang_prefix}helmets/")
        urls.append(f"https://{HOST}/{lang_prefix}accessories/")
        urls.append(f"https://{HOST}/{lang_prefix}motorcycles/")
        urls.append(f"https://{HOST}/{lang_prefix}brands/")
        urls.append(f"https://{HOST}/{lang_prefix}comparison/")

    # Plural route mapping per CPT
    cpt_routes = {
        'helmet': 'helmets',
        'accessory': 'accessories',
        'motorcycle': 'motorcycles',
        'brand': 'brands'
    }

    for line in lines:
        parts = line.strip().split("\t")
        if len(parts) >= 4:
            p_id, slug, post_type, lang = parts[0], parts[1], parts[2], parts[3]
            cpt_route = cpt_routes.get(post_type, post_type)
            if lang == "en":
                url = f"https://{HOST}/{cpt_route}/{slug}/"
            else:
                url = f"https://{HOST}/{lang}/{cpt_route}/{slug}/"
            urls.append(url)

    urls = sorted(list(dict.fromkeys(urls)))
    print(f"✅ Generated {len(urls)} unique canonical & localized URLs.")
    return urls

def submit_indexnow_batch(batch, batch_num, total_batches):
    payload = {
        "host": HOST,
        "key": API_KEY,
        "keyLocation": KEY_LOCATION,
        "urlList": batch
    }
    data = json.dumps(payload).encode("utf-8")
    req = urllib.request.Request(
        INDEXNOW_ENDPOINT,
        data=data,
        headers={
            "Content-Type": "application/json; charset=utf-8",
            "User-Agent": "Helmetsan-IndexNow-Surge/2.0"
        }
    )

    print(f"⚡ [Batch {batch_num}/{total_batches}] Submitting {len(batch)} URLs to IndexNow...")
    for attempt in range(1, 4):
        try:
            with urllib.request.urlopen(req, timeout=30) as resp:
                code = resp.getcode()
                body = resp.read().decode("utf-8")
                print(f"   ✅ [Batch {batch_num}] HTTP {code} OK / Accepted. ({len(batch)} URLs)")
                return True
        except urllib.error.HTTPError as e:
            code = e.code
            body = e.read().decode("utf-8")
            if code in (200, 202):
                print(f"   ✅ [Batch {batch_num}] HTTP {code} Accepted.")
                return True
            print(f"   ⚠️ [Batch {batch_num}] HTTP {code} (Attempt {attempt}): {body}")
            if code == 429:
                sleep_sec = 10 * attempt
                print(f"   ⏳ Throttled. Backing off for {sleep_sec}s...")
                time.sleep(sleep_sec)
            else:
                time.sleep(3)
        except Exception as e:
            print(f"   ❌ [Batch {batch_num}] Network error (Attempt {attempt}): {e}")
            time.sleep(3)
    return False

def main():
    dry_run = "--dry-run" in sys.argv
    limit = None
    for arg in sys.argv:
        if arg.startswith("--limit="):
            limit = int(arg.split("=")[1])

    urls = load_canonical_urls_from_server()
    if limit:
        urls = urls[:limit]
        print(f"⚠️ Limited to {limit} URLs.")

    batches = [urls[i:i + BATCH_SIZE] for i in range(0, len(urls), BATCH_SIZE)]
    print(f"📦 Sliced {len(urls)} URLs into {len(batches)} batches of up to {BATCH_SIZE} URLs each.")

    if dry_run:
        print("🔍 DRY RUN: Sample batch preview (first 5 URLs):")
        for u in batches[0][:5]:
            print(f"   - {u}")
        print("Dry run complete. No network requests sent.")
        return

    success_batches = 0
    start_time = time.time()
    for idx, batch in enumerate(batches, 1):
        ok = submit_indexnow_batch(batch, idx, len(batches))
        if ok:
            success_batches += 1
        # Respectful pause between bulk batches
        time.sleep(2)

    elapsed = time.time() - start_time
    print(f"\n🎉 IndexNow Surge Completed in {elapsed:.1f}s!")
    print(f"   Successful Batches: {success_batches}/{len(batches)}")
    print(f"   Total URLs Submitted: {len(urls)}")

if __name__ == "__main__":
    main()
