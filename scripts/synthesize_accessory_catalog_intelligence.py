#!/usr/bin/env python3
"""
Batch Synthesizer for Accessory Intelligence Matrix
Processes all files in data/accessories/*.json
"""

import os
import glob
import json
import time
from accessory_intelligence_matrix import synthesize_accessory

def run_batch():
    base_dir = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
    acc_dir = os.path.join(base_dir, "data", "accessories")
    
    files = glob.glob(os.path.join(acc_dir, "*.json"))
    print(f"🚀 Starting Accessory Catalog Intelligence Synthesis across {len(files)} files...")
    
    start_time = time.time()
    updated_count = 0
    error_count = 0
    
    for f in files:
        try:
            with open(f, "r", encoding="utf-8") as fp:
                data = json.load(fp)
            
            enriched = synthesize_accessory(data)
            
            with open(f, "w", encoding="utf-8") as fp:
                json.dump(enriched, fp, indent=4, ensure_ascii=False)
                
            updated_count += 1
        except Exception as e:
            print(f"❌ Error processing {f}: {e}")
            error_count += 1
            
    elapsed = time.time() - start_time
    rate = updated_count / elapsed if elapsed > 0 else 0
    
    print(f"✅ Finished synthesizing {updated_count} accessories in {elapsed:.3f}s ({rate:.1f} accessories/sec). Errors: {error_count}")

if __name__ == "__main__":
    run_batch()
