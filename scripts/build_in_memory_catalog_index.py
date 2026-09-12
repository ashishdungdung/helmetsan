#!/usr/bin/env python3
"""
Helmetsan Unified Master In-Memory RAM Index Compiler
Compiles all 2,235 harmonized helmets into a single, high-speed RAM index
for sub-millisecond querying, comparison, and cache warming.
"""

import os
import glob
import json
import time

WEB_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DATA_HELMETS_DIR = os.path.join(WEB_DIR, "data", "helmets")
OUTPUT_INDEX = os.path.join(WEB_DIR, "data", "helmets_unified_master_memory_index.json")

def build_in_memory_index():
    start_time = time.time()
    files = sorted(glob.glob(os.path.join(DATA_HELMETS_DIR, "*.json")))
    print(f"🚀 Compiling {len(files)} helmets into Unified Master In-Memory RAM Index...")

    index = {
        "metadata": {
            "compiled_at": time.strftime("%Y-%m-%d %H:%M:%S"),
            "total_helmets": len(files),
            "schema_version": "2.1-harmonized",
            "source": "Helmetsan Single Source of Truth"
        },
        "by_id": {},
        "by_brand": {},
        "by_type": {},
        "by_head_shape": {}
    }

    for fpath in files:
        with open(fpath, "r", encoding="utf-8") as fp:
            d = json.load(fp)
        
        hid = d.get("id")
        if not hid:
            continue

        brand = d.get("brand", "Unknown")
        htype = d.get("type", "Full Face")
        head_shape = d.get("head_shape", "Intermediate Oval")
        specs = d.get("specs", {})

        item_summary = {
            "id": hid,
            "title": d.get("title", ""),
            "brand": brand,
            "type": htype,
            "head_shape": head_shape,
            "weight_g": specs.get("weight_g"),
            "noise_db": d.get("noise_db") or specs.get("noise_db_at_100kph"),
            "material": specs.get("material"),
            "strap_type": specs.get("strap_type"),
            "certifications": specs.get("certifications", []),
            "sharp_rating": d.get("sharp_rating", 0),
            "price": d.get("price", {}),
            "rider_takeaway": d.get("rider_takeaway", ""),
            "pros": d.get("pros_and_cons", {}).get("pros", []),
            "cons": d.get("pros_and_cons", {}).get("cons", []),
            "compatible_motorcycles": [m.get("title") for m in d.get("compatible_motorcycles", [])[:3]],
            "file": os.path.basename(fpath)
        }

        index["by_id"][hid] = item_summary
        index["by_brand"].setdefault(brand, []).append(hid)
        index["by_type"].setdefault(htype, []).append(hid)
        index["by_head_shape"].setdefault(head_shape, []).append(hid)

    with open(OUTPUT_INDEX, "w", encoding="utf-8") as fp:
        json.dump(index, fp, indent=2, ensure_ascii=False)

    elapsed = time.time() - start_time
    file_size_mb = os.path.getsize(OUTPUT_INDEX) / (1024 * 1024)
    print(f"✅ In-Memory Master Index compiled in {elapsed:.2f}s!")
    print(f"📁 Output: {OUTPUT_INDEX} ({file_size_mb:.2f} MB)")
    print(f"📊 Indexed {len(index['by_id'])} helmets across {len(index['by_brand'])} brands and {len(index['by_type'])} types.")

if __name__ == "__main__":
    build_in_memory_index()
