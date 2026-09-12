#!/usr/bin/env python3
"""
Synthesize Motorcycle Catalog Intelligence & Editorial Overviews
Executes the Motorcycle Intelligence Matrix across all catalog motorcycles:
- HelmetsanWeb/data/motorcycles/*.json (3,247 records)
- HelmetsanWeb/data/motorcycles_unified_master_memory_index.json
"""

import os
import sys
import json
import glob
import time
from motorcycle_intelligence_matrix import MotorcycleIntelligenceMatrix

SCRIPT_DIR = os.path.dirname(os.path.abspath(__file__))
WEB_DIR = os.path.dirname(SCRIPT_DIR)
DATA_MOTORCYCLES_DIR = os.path.join(WEB_DIR, "data", "motorcycles")
MASTER_INDEX_PATH = os.path.join(WEB_DIR, "data", "motorcycles_unified_master_memory_index.json")

def process_motorcycle_item(item):
    res = MotorcycleIntelligenceMatrix.generate_intelligence(item)
    item["description"] = res["editorial_overview"]
    item["editorial_overview"] = res["editorial_overview"]
    item["rider_takeaway"] = res["rider_takeaway"]
    item["intelligence_matrix"] = res["intelligence_matrix"]
    return item

def run_motorcycle_synthesis():
    start_time = time.time()
    print("🚀 Starting Motorcycle Catalog Intelligence Synthesis...")
    
    # Process data/motorcycles
    bike_files = sorted(glob.glob(os.path.join(DATA_MOTORCYCLES_DIR, "*.json")))
    print(f"🏍️ Processing {len(bike_files)} files in {DATA_MOTORCYCLES_DIR}...")
    updated_bikes = 0
    for fpath in bike_files:
        try:
            with open(fpath, "r", encoding="utf-8") as fp:
                item = json.load(fp)
            updated_item = process_motorcycle_item(item)
            with open(fpath, "w", encoding="utf-8") as fp:
                json.dump(updated_item, fp, indent=2, ensure_ascii=False)
            updated_bikes += 1
        except Exception as e:
            print(f"⚠️ Error processing {fpath}: {e}")

    print(f"✅ Successfully updated {updated_bikes} motorcycle records.")

    # Process Master Memory Index if exists
    if os.path.exists(MASTER_INDEX_PATH):
        print(f"📦 Updating Motorcycle Master Memory Index at {MASTER_INDEX_PATH}...")
        try:
            with open(MASTER_INDEX_PATH, "r", encoding="utf-8") as fp:
                master = json.load(fp)
            
            catalog = master.get("motorcycles_catalog") or master.get("motorcycles")
            updated_index_count = 0
            if isinstance(catalog, dict):
                for mid, mdata in catalog.items():
                    catalog[mid] = process_motorcycle_item(mdata)
                    updated_index_count += 1
            elif isinstance(catalog, list):
                for idx, mdata in enumerate(catalog):
                    catalog[idx] = process_motorcycle_item(mdata)
                    updated_index_count += 1
            
            if "system" in master:
                master["system"]["intelligence_matrix_version"] = "v2.5 (Tone 3 Balanced Editorial)"
                master["system"]["last_intelligence_synthesis"] = time.strftime("%Y-%m-%d %H:%M:%S")

            with open(MASTER_INDEX_PATH, "w", encoding="utf-8") as fp:
                json.dump(master, fp, indent=2, ensure_ascii=False)
            
            print(f"✅ Motorcycle Master Memory Index updated ({updated_index_count} bikes indexed).")
        except Exception as e:
            print(f"⚠️ Error updating Motorcycle Master Index: {e}")

    elapsed = time.time() - start_time
    throughput = updated_bikes / elapsed if elapsed > 0 else 0
    print(f"\n🎉 Completed synthesis of {updated_bikes} motorcycle files in {elapsed:.2f}s ({throughput:.1f} bikes/sec)!")

if __name__ == "__main__":
    run_motorcycle_synthesis()
