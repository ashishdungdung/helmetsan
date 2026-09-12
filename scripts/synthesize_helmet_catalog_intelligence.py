#!/usr/bin/env python3
"""
Synthesize Helmet Catalog Intelligence & Editorial Overviews
Executes the Helmet Intelligence Matrix across all catalog helmets:
- HelmetsanWeb/data/helmets/*.json (2,235 records)
- HelmetsanWeb/helmets/*.json (2,260 records)
- HelmetsanWeb/data/helmets_unified_master_memory_index.json
"""

import os
import sys
import json
import glob
import time
from helmet_intelligence_matrix import HelmetIntelligenceMatrix

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
WEB_DIR = os.path.dirname(SCRIPT_DIR)
DATA_HELMETS_DIR = os.path.join(WEB_DIR, "data", "helmets")
ROOT_HELMETS_DIR = os.path.join(WEB_DIR, "helmets")
MASTER_INDEX_PATH = os.path.join(WEB_DIR, "data", "helmets_unified_master_memory_index.json")

def process_helmet_item(item):
    """
    Enriches a helmet item with the Helmet Intelligence Matrix and dynamic editorial.
    """
    res = HelmetIntelligenceMatrix.generate_intelligence(item)
    
    # 1. Update Core Editorial, Verdict, and Authenticated Pros & Cons
    item["description"] = res["editorial_overview"]
    item["editorial_overview"] = res["editorial_overview"]
    item["rider_takeaway"] = res["rider_takeaway"]
    item["intelligence_matrix"] = res["intelligence_matrix"]
    item["pros_and_cons"] = res["pros_and_cons"]
    item["fitment_notes"] = res["fitment_notes"]
    item["story"] = res["story"]
    item["editorial_story"] = res["story"]
    
    # 2. Synchronize realistic noise rating across specs
    if "specs" in item and isinstance(item["specs"], dict):
        item["specs"]["noise_db_at_100kph"] = res["noise_db_val"]
    item["noise_db"] = res["noise_db_val"]
    
    # 3. Contextualize Variants
    variants = item.get("variants")
    if isinstance(variants, list) and len(variants) > 0:
        for v in variants:
            v_title = v.get("title") or v.get("color") or "Variant"
            v_finish = v.get("finish") or item.get("finish") or "Gloss"
            v_narrative = f"{v_title} ({v_finish} finish) — {res['intelligence_matrix']['finish_and_aesthetic']['finish_narrative']}"
            v["finish_profile"] = v_narrative
            v["editorial_note"] = f"{item.get('brand')} {item.get('title')} in {v_title}. {res['rider_takeaway']}"
            v["story"] = res["story"]
            v["fitment_notes"] = res["fitment_notes"]

    return item

def run_catalog_synthesis():
    start_time = time.time()
    print("🚀 Starting Helmet Catalog Intelligence Synthesis...")
    
    # --- 1. Process data/helmets ---
    data_files = sorted(glob.glob(os.path.join(DATA_HELMETS_DIR, "*.json")))
    print(f"📦 Processing {len(data_files)} files in {DATA_HELMETS_DIR}...")
    updated_data = 0
    for fpath in data_files:
        try:
            with open(fpath, "r", encoding="utf-8") as fp:
                item = json.load(fp)
            updated_item = process_helmet_item(item)
            with open(fpath, "w", encoding="utf-8") as fp:
                json.dump(updated_item, fp, indent=2, ensure_ascii=False)
            updated_data += 1
        except Exception as e:
            print(f"⚠️ Error processing {fpath}: {e}")

    print(f"✅ Successfully updated {updated_data} records in data/helmets.")

    # --- 2. Process root helmets ---
    root_files = sorted(glob.glob(os.path.join(ROOT_HELMETS_DIR, "*.json")))
    print(f"📦 Processing {len(root_files)} files in {ROOT_HELMETS_DIR}...")
    updated_root = 0
    for fpath in root_files:
        try:
            with open(fpath, "r", encoding="utf-8") as fp:
                item = json.load(fp)
            updated_item = process_helmet_item(item)
            with open(fpath, "w", encoding="utf-8") as fp:
                json.dump(updated_item, fp, indent=2, ensure_ascii=False)
            updated_root += 1
        except Exception as e:
            print(f"⚠️ Error processing {fpath}: {e}")

    print(f"✅ Successfully updated {updated_root} records in root helmets.")

    # --- 3. Process Master Memory Index ---
    if os.path.exists(MASTER_INDEX_PATH):
        print(f"📦 Updating Master Memory Index at {MASTER_INDEX_PATH}...")
        try:
            with open(MASTER_INDEX_PATH, "r", encoding="utf-8") as fp:
                master = json.load(fp)
            
            catalog = master.get("helmets_catalog")
            updated_index_count = 0
            if isinstance(catalog, dict):
                for hid, hdata in catalog.items():
                    catalog[hid] = process_helmet_item(hdata)
                    updated_index_count += 1
            elif isinstance(catalog, list):
                for idx, hdata in enumerate(catalog):
                    catalog[idx] = process_helmet_item(hdata)
                    updated_index_count += 1
            
            # Update metadata
            if "system" in master:
                master["system"]["intelligence_matrix_version"] = "v2.5 (Tone 3 Balanced Editorial)"
                master["system"]["last_intelligence_synthesis"] = time.strftime("%Y-%m-%d %H:%M:%S")

            with open(MASTER_INDEX_PATH, "w", encoding="utf-8") as fp:
                json.dump(master, fp, indent=2, ensure_ascii=False)
            
            print(f"✅ Master Memory Index updated ({updated_index_count} helmets indexed).")
        except Exception as e:
            print(f"⚠️ Error updating Master Index: {e}")

    elapsed = time.time() - start_time
    total_processed = updated_data + updated_root
    throughput = total_processed / elapsed if elapsed > 0 else 0
    print(f"\n🎉 Completed synthesis of {total_processed} helmet files in {elapsed:.2f}s ({throughput:.1f} helmets/sec)!")

if __name__ == "__main__":
    run_catalog_synthesis()
