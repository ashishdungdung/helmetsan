#!/usr/bin/env python3
"""
Helmetsan In-Memory Catalog Data Linter
Loads all 2,235 helmet JSON records into RAM and validates physical,
morphological, and relational integrity rules in <300ms.
"""

import os
import glob
import json
import time
import sys

WEB_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DATA_HELMETS_DIR = os.path.join(WEB_DIR, "data", "helmets")

def run_in_memory_linter():
    start_load = time.time()
    files = sorted(glob.glob(os.path.join(DATA_HELMETS_DIR, "*.json")))
    if not files:
        print("❌ No helmet files found!")
        sys.exit(1)

    # 1. Load entire catalog into RAM
    catalog = []
    for f in files:
        with open(f, "r", encoding="utf-8") as fp:
            catalog.append((f, json.load(fp)))
    load_time = (time.time() - start_load) * 1000

    # 2. In-Memory Analytical Audit Sweep
    start_sweep = time.time()
    violations = []

    for fpath, item in catalog:
        fname = os.path.basename(fpath)
        htype = str(item.get("type") or "").strip()
        cat = str(item.get("category") or "").strip()
        specs = item.get("specs") or {}
        p_intel = item.get("physics_intelligence") or {}
        s_intel = item.get("safety_intelligence") or {}
        q_intel = item.get("qualitative_intelligence") or {}
        a_prof = item.get("aero_acoustic_profile") or {}

        # Rule 1: Canonical Type vs Category
        if cat != htype:
            violations.append((fname, f"Category mismatch: type='{htype}' vs category='{cat}'"))

        # Rule 2: Weight Consistency
        w_specs = specs.get("weight_g")
        w_phys = p_intel.get("weight_grams")
        if w_specs and w_phys and w_specs != w_phys:
            violations.append((fname, f"Weight discrepancy: specs={w_specs}g vs physics_intel={w_phys}g"))
        if w_specs and (w_specs < 800 or w_specs > 2200):
            violations.append((fname, f"Physically implausible weight: {w_specs}g"))

        # Rule 3: Acoustic Realism & Synchronization
        db_specs = specs.get("noise_db_at_100kph")
        db_aero = a_prof.get("noise_db_at_100kph")
        if db_specs and db_aero and db_specs != db_aero:
            violations.append((fname, f"Noise discrepancy: specs={db_specs}dB vs aero_profile={db_aero}dB"))

        if "open face" in htype.lower() or "half" in htype.lower():
            if db_specs and db_specs < 92:
                violations.append((fname, f"Implausible open-face noise: {db_specs}dB (too quiet for open-air)"))

        # Rule 4: Form-Factor Boundaries (No Full-Face artifacts on Open/Half/Dirt)
        if "open face" in htype.lower() or "half" in htype.lower():
            # Check cheek pads in qualitative cons
            q_cons = " ".join([str(c) for c in q_intel.get("real_world_cons", [])]).lower()
            if "cheek" in q_cons:
                violations.append((fname, "Open-face helmet has cheek pad break-in con"))
            # Check EQRS
            if s_intel.get("emergency_release_system") is True:
                violations.append((fname, "Open-face helmet claims EQRS emergency release"))
            # Check breath guard
            winter = str(q_intel.get("winter_fogging_resilience", "")).lower()
            if "breath guard" in winter:
                violations.append((fname, "Open-face helmet claims lower breath guard"))

        if "dirt" in htype.lower() or "mx" in htype.lower():
            pros_str = " ".join([str(p) for p in item.get("pros_and_cons", {}).get("pros", [])]).lower()
            if "optical shield" in pros_str and "goggle" not in pros_str:
                violations.append((fname, "Dirt/MX helmet claims optical face shield"))

        # Rule 5: Safety Certification Plausibility
        if "open face" in htype.lower() or "half" in htype.lower():
            r1 = item.get("sharp_rating") or 0
            r2 = s_intel.get("sharp_rating") or 0
            if (isinstance(r1, (int, float)) and r1 > 0) or (isinstance(r2, (int, float)) and r2 > 0):
                violations.append((fname, "Open-face helmet claims non-zero SHARP rating (untested)"))

        # Rule 6: Relational Feasibility (Motorcycle Pairings)
        if "open face" in htype.lower() or "half" in htype.lower():
            for m in item.get("compatible_motorcycles", []):
                m_title = str(m.get("title", "")).lower()
                m_cat = str(m.get("category", "")).lower()
                if any(sb in m_title for sb in ["superleggera", "panigale", "s1000rr", "rs 660", "cbr1000rr", "superbike"]):
                    violations.append((fname, f"Open-face helmet paired with superbike '{m.get('title')}'"))

    sweep_time = (time.time() - start_sweep) * 1000
    total_time = load_time + sweep_time

    print("=" * 65)
    print("⚡ HELMETSAN IN-MEMORY CATALOG DATA LINTER")
    print("=" * 65)
    print(f"📦 Total Catalog Loaded in RAM: {len(catalog)} helmets")
    print(f"⏱️  RAM Dataset Load Time:      {load_time:.2f} ms")
    print(f"🔍 In-Memory Sweep Audit Time:  {sweep_time:.2f} ms")
    print(f"⚡ Total Execution Time:         {total_time:.2f} ms ({(len(catalog) / (total_time / 1000)):.1f} helmets/sec)")
    print("-" * 65)

    if violations:
        print(f"❌ FAILED: Found {len(violations)} rule violations across catalog:")
        for fn, err in violations[:15]:
            print(f"  - [{fn}] {err}")
        if len(violations) > 15:
            print(f"  ... and {len(violations) - 15} more.")
        sys.exit(1)
    else:
        print("✅ PASSED: 100% of catalog records comply with all physical,")
        print("   morphological, and relational data integrity rules!")
        print("=" * 65)
        sys.exit(0)

if __name__ == "__main__":
    run_in_memory_linter()
