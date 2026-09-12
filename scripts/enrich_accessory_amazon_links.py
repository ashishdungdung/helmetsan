#!/usr/bin/env python3
"""
Enrich all accessories with Amazon search/affiliate marketplace links.
"""

import os
import glob
import json
import urllib.parse

WEB_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
ACCESSORIES_DIR = os.path.join(WEB_DIR, "data", "accessories")

def enrich_accessories():
    files = sorted(glob.glob(os.path.join(ACCESSORIES_DIR, "*.json")))
    print(f"📦 Enriching {len(files)} accessories with Amazon marketplace links...")
    
    updated_count = 0
    for f in files:
        with open(f, "r", encoding="utf-8") as fp:
            d = json.load(fp)
        
        title = d.get("title", "")
        if not title:
            continue
        
        encoded_query = urllib.parse.quote_plus(title)
        amazon_url = f"https://www.amazon.com/s?k={encoded_query}&tag=helmetsan-20"
        
        mp = d.get("marketplace_links")
        if not isinstance(mp, dict):
            mp = {}
        
        mp["amazon"] = amazon_url
        d["marketplace_links"] = mp
        
        # Also ensure affiliate_links structure
        aff = d.get("affiliate_links")
        if not isinstance(aff, dict):
            aff = {}
        aff["amazon"] = {
            "url": amazon_url,
            "network": "amazon"
        }
        d["affiliate_links"] = aff
        
        with open(f, "w", encoding="utf-8") as fp:
            json.dump(d, fp, indent=2, ensure_ascii=False)
        updated_count += 1

    print(f"✅ Successfully enriched {updated_count} / {len(files)} accessories in RAM/Disk!")

if __name__ == "__main__":
    enrich_accessories()
