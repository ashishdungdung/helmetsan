#!/usr/bin/env python3
"""
Helmetsan Unified Master In-Memory RAM Index Compiler.

Single source of truth: data/helmets/*.json
Keys every record by filename stem (canonical id). Never trusts drifted
legacy mirrors (spec_weight_g, root sharp_rating) over specs/safety_intelligence.
"""

from __future__ import annotations

import glob
import json
import os
import time

WEB_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
DATA_HELMETS_DIR = os.path.join(WEB_DIR, "data", "helmets")
OUTPUT_INDEX = os.path.join(WEB_DIR, "data", "helmets_unified_master_memory_index.json")


def _as_list(value):
    if isinstance(value, list):
        return value
    if isinstance(value, str):
        return [value] if value else []
    return []


def _moto_titles(raw):
    """compatible_motorcycles entries are dicts with .title or plain strings."""
    out = []
    for item in _as_list(raw)[:3]:
        if isinstance(item, dict):
            title = item.get("title") or item.get("id") or ""
            if title:
                out.append(title)
        elif isinstance(item, str):
            out.append(item)
    return out


def _sharp(d: dict) -> tuple[int, bool]:
    safe = d.get("safety_intelligence") or {}
    if not isinstance(safe, dict):
        safe = {}
    raw = safe.get("sharp_rating", d.get("sharp_rating", 0))
    try:
        rating = int(float(raw or 0))
    except (TypeError, ValueError):
        rating = 0
    verified = bool(safe.get("sharp_verified", d.get("sharp_verified", False)))
    # Unverified stars must never surface as positive ratings.
    if rating > 0 and not verified:
        return 0, False
    return max(0, min(5, rating)), verified


def _amazon(ident: dict):
    amazon = ident.get("amazon") or {}
    us = amazon.get("us") or {}
    asin = us.get("asin")
    status = us.get("status") or ("verified" if asin else "missing")
    return asin, status


def build_in_memory_index() -> None:
    start_time = time.time()
    files = sorted(glob.glob(os.path.join(DATA_HELMETS_DIR, "*.json")))
    print(f"🚀 Compiling {len(files)} helmets into Unified Master In-Memory RAM Index...")

    index = {
        "metadata": {
            "compiled_at": time.strftime("%Y-%m-%d %H:%M:%S"),
            "total_helmets": len(files),
            "schema_version": "2.2-source-truth",
            "source": "Helmetsan Single Source of Truth",
            "keying": "filename stem == id",
        },
        "by_id": {},
        "by_brand": {},
        "by_type": {},
        "by_head_shape": {},
        "by_family": {},
        "by_parent": {},
    }

    skipped = 0
    for fpath in files:
        stem = os.path.basename(fpath)[:-5]
        if stem in ("master", "master.example"):
            skipped += 1
            continue
        with open(fpath, "r", encoding="utf-8") as fp:
            d = json.load(fp)

        # Canonical id is the filename stem; keep JSON id aligned.
        hid = stem
        specs = d.get("specs") or {}
        if not isinstance(specs, dict):
            specs = {}
        safe = d.get("safety_intelligence") or {}
        if not isinstance(safe, dict):
            safe = {}
        ident = d.get("identifiers") or {}
        if not isinstance(ident, dict):
            ident = {}

        brand = d.get("brand") or "Unknown"
        htype = d.get("type") or "Full Face"
        head_shape = (
            d.get("head_shape")
            or (d.get("sizing_fit") or {}).get("head_shape")
            or "Intermediate Oval"
        )
        noise = specs.get("noise_db_at_100kph")
        if noise is None:
            noise = d.get("noise_db")
        asin_us, asin_status = _amazon(ident)
        sku = ident.get("sku") or d.get("sku") or ""
        sharp_rating, sharp_verified = _sharp(d)
        parent_id = d.get("parent_id") or None
        if parent_id in ("", 0, "0"):
            parent_id = None
        pac = d.get("pros_and_cons") or {}
        if not isinstance(pac, dict):
            pac = {}

        index["by_id"][hid] = {
            "id": hid,
            "title": d.get("title", ""),
            "brand": brand,
            "type": htype,
            "helmet_family": d.get("helmet_family") or "",
            "head_shape": head_shape,
            "model_year": d.get("model_year"),
            "parent_id": parent_id,
            "weight_g": specs.get("weight_g"),
            "noise_db": noise,
            "material": specs.get("material"),
            "strap_type": specs.get("strap_type"),
            "certifications": _as_list(specs.get("certifications")),
            "sharp_rating": sharp_rating,
            "sharp_verified": sharp_verified,
            "price": d.get("price") or {},
            "asin_us": asin_us,
            "asin_status": asin_status,
            "sku": sku,
            "rider_takeaway": d.get("rider_takeaway") or "",
            "pros": _as_list(pac.get("pros")),
            "cons": _as_list(pac.get("cons")),
            "compatible_motorcycles": _moto_titles(d.get("compatible_motorcycles")),
            "file": os.path.basename(fpath),
        }
        index["by_brand"].setdefault(brand, []).append(hid)
        index["by_type"].setdefault(htype, []).append(hid)
        index["by_head_shape"].setdefault(head_shape, []).append(hid)
        fam = d.get("helmet_family") or ""
        if fam:
            index["by_family"].setdefault(fam, []).append(hid)
        if parent_id:
            index["by_parent"].setdefault(parent_id, []).append(hid)

    index["metadata"]["total_helmets"] = len(index["by_id"])
    index["metadata"]["skipped_templates"] = skipped

    with open(OUTPUT_INDEX, "w", encoding="utf-8") as fp:
        json.dump(index, fp, indent=2, ensure_ascii=False)
        fp.write("\n")

    elapsed = time.time() - start_time
    file_size_mb = os.path.getsize(OUTPUT_INDEX) / (1024 * 1024)
    print(f"✅ In-Memory Master Index compiled in {elapsed:.2f}s!")
    print(f"📁 Output: {OUTPUT_INDEX} ({file_size_mb:.2f} MB)")
    print(
        f"📊 Indexed {len(index['by_id'])} helmets across "
        f"{len(index['by_brand'])} brands, {len(index['by_type'])} types, "
        f"{len(index['by_family'])} families, {len(index['by_parent'])} parent groups."
    )


if __name__ == "__main__":
    build_in_memory_index()
