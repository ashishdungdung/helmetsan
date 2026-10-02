#!/usr/bin/env python3
"""
Helmetsan Edge Pre-Warming Crawler Engine
Crawls top-tier catalog URLs across all 10 languages to prime Nginx FastCGI microcache
and Cloudflare edge caches with bounded concurrency and rate limiting.
"""

import sys
import time
import subprocess
import urllib.request
import urllib.error
from concurrent.futures import ThreadPoolExecutor, as_completed

HOST = "helmetsan.com"
MAX_WORKERS = 8  # Safe concurrency to avoid origin CPU spikes
RATE_LIMIT_PAUSE = 0.05  # 50ms pause per worker request

def fetch_top_tier_urls(limit_helmets=1000):
    """
    Fetch top helmets by review count / verification score across all 10 languages.
    """
    cmd = [
        "ssh", "-o", "ControlMaster=no", "-o", "ControlPath=none", "-o", "StrictHostKeyChecking=no",
        "root@31.70.136.154",
        f"""cd /var/www/helmetsan.com/public && sudo -u www-data wp db query "
        SELECT p.ID, p.post_name, p.post_type, t.slug as lang
        FROM wp_posts p
        INNER JOIN wp_term_relationships tr ON p.ID = tr.object_id
        INNER JOIN wp_term_taxonomy tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'language'
        INNER JOIN wp_terms t ON tt.term_id = t.term_id
        WHERE p.post_type = 'helmet'
          AND p.post_status = 'publish'
        ORDER BY p.comment_count DESC, p.ID DESC
        LIMIT {limit_helmets};
        " --skip-column-names"""
    ]
    print(f"📡 Fetching top {limit_helmets} helmet records across languages...")
    res = subprocess.run(cmd, capture_output=True, text=True, check=True)
    lines = res.stdout.strip().split("\n")

    urls = []
    # Primary hub & taxonomy pages
    langs = ["", "de/", "zh/", "fr/", "es/", "it/", "pl/", "pt/", "nl/", "ja/"]
    for lang_prefix in langs:
        urls.append(f"https://{HOST}/{lang_prefix}")
        urls.append(f"https://{HOST}/{lang_prefix}helmets/")
        urls.append(f"https://{HOST}/{lang_prefix}accessories/")
        urls.append(f"https://{HOST}/{lang_prefix}motorcycles/")
        urls.append(f"https://{HOST}/{lang_prefix}brands/")
        urls.append(f"https://{HOST}/{lang_prefix}comparison/")

    for line in lines:
        parts = line.strip().split("\t")
        if len(parts) >= 4:
            p_id, slug, post_type, lang = parts[0], parts[1], parts[2], parts[3]
            if lang == "en":
                url = f"https://{HOST}/helmets/{slug}/"
            else:
                url = f"https://{HOST}/{lang}/helmets/{slug}/"
            urls.append(url)

    urls = sorted(list(dict.fromkeys(urls)))
    print(f"✅ Prepared {len(urls)} top-tier URLs for cache priming.")
    return urls

def prewarm_url(url):
    req = urllib.request.Request(
        url,
        headers={
            "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36 (Helmetsan-Prewarm)",
            "Accept": "text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8",
            "X-Helmetsan-Prewarm": "1",
        }
    )
    t0 = time.time()
    try:
        with urllib.request.urlopen(req, timeout=15) as resp:
            code = resp.getcode()
            content = resp.read()
            duration = (time.time() - t0) * 1000
            headers = dict(resp.headers)
            cf_cache = headers.get("cf-cache-status", "NONE")
            fcgi_cache = headers.get("x-fastcgi-cache", "NONE")
            return {
                "url": url,
                "code": code,
                "bytes": len(content),
                "ms": duration,
                "cf": cf_cache,
                "fcgi": fcgi_cache,
                "ok": True
            }
    except Exception as e:
        duration = (time.time() - t0) * 1000
        return {
            "url": url,
            "code": 0,
            "bytes": 0,
            "ms": duration,
            "error": str(e),
            "ok": False
        }

def main():
    limit = 1000
    for arg in sys.argv:
        if arg.startswith("--limit="):
            limit = int(arg.split("=")[1])

    urls = fetch_top_tier_urls(limit_helmets=limit)
    print(f"🚀 Commencing Pre-Warm Sweep across {len(urls)} URLs with {MAX_WORKERS} threads...\n")

    hits = 0
    misses = 0
    errors = 0
    total_bytes = 0
    total_ms = 0
    completed = 0

    start_time = time.time()
    with ThreadPoolExecutor(max_workers=MAX_WORKERS) as executor:
        futures = {executor.submit(prewarm_url, u): u for u in urls}
        for future in as_completed(futures):
            res = future.result()
            completed += 1
            if res["ok"]:
                total_bytes += res["bytes"]
                total_ms += res["ms"]
                if res.get("fcgi") == "HIT" or res.get("cf") == "HIT":
                    hits += 1
                else:
                    misses += 1
            else:
                errors += 1

            if completed % 100 == 0 or completed == len(urls):
                avg_ms = (total_ms / completed) if completed > 0 else 0
                pct = (completed / len(urls)) * 100
                print(f"[{pct:5.1f}%] {completed}/{len(urls)} | Hits: {hits} | Miss/Primed: {misses} | Errors: {errors} | Avg: {avg_ms:.1f}ms")
            time.sleep(RATE_LIMIT_PAUSE)

    elapsed = time.time() - start_time
    print(f"\n🎉 Pre-Warming Sweep Finished in {elapsed:.1f}s!")
    print(f"   Total URLs: {completed}")
    print(f"   Cache Hits: {hits}")
    print(f"   Cache Primed (Misses on initial load): {misses}")
    print(f"   Errors: {errors}")
    print(f"   Total Data Transferred: {total_bytes / (1024 * 1024):.2f} MB")

if __name__ == "__main__":
    main()
