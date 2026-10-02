#!/usr/bin/env python3
"""
Helmetsan catalog remediation (2026-09-29 deep audit).

Fixes:
  P0  Scrub unverified SHARP ratings (YMYL) and stamp sharp_verified
  P1  Repair parent_id graph (dangling, self-parent, sibling re-homing)
  P1  Sync child structural specs from parent (weight, material, certs, strap, noise)
  P1  Align internal id to filename stem; normalize cert/type tokens
  P1  Drop legacy spec_weight_g; mirror canonical noise_db
  P2  Accessories: compatibility block + placeholder price flag
  P2  Brand schema shape (premier.json)
  P0  Remove AI-prompt-leak catalog artifact
"""

from __future__ import annotations

import glob
import json
import os
import re
import sys
from typing import Any

WEB_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
HELMETS = os.path.join(WEB_DIR, "data", "helmets")
ACCESSORIES = os.path.join(WEB_DIR, "data", "accessories")
BRANDS = os.path.join(WEB_DIR, "data", "brands")

# SHARP (UK) road-helmet programme does not test these shell classes.
UNTESTED_TYPES = {
    "dirt / mx",
    "dirt / motocross",
    "off-road",
    "off road",
    "half",
    "track / race",
    "track / circuit",
    "adventure / dual sport",
    "adventure / dual-sport",
    "modular full face",
}

TYPE_CANON = {
    "dirt / motocross": "Dirt / MX",
    "dirt/motocross": "Dirt / MX",
    "modular full face": "Modular",
    "off-road": "Off-road",
    "off road": "Off-road",
    "modular full face": "Modular",
    "adventure / dual-sport": "Adventure / Dual Sport",
}

CERT_CANON = {
    "SNELL M-2020": "Snell M2020",
    "SNELL M2020": "Snell M2020",
    "SNELL M-2015": "Snell M2015",
    "SNELL M2015": "Snell M2015",
    "DOT FMVSS 218": "DOT",
    # bare "ECE" stays bare — never invent a version
}

# Files that are LLM artifacts / non-products, not real SKUs.
DELETE_ARTIFACTS = (
    "ruroc_no_models_found_for_outside_the_excluded_atlas_4.0_variants;_alpinestars_constraint_not_applicable_to_request.",
)

# Titles are never invented. Only strip leading "-" artifacts and Carbon Carbon stutters.
TITLE_FIXES = {}


def load(path: str) -> dict:
    with open(path, "r", encoding="utf-8") as fp:
        return json.load(fp)


def save(path: str, data: dict) -> None:
    with open(path, "w", encoding="utf-8") as fp:
        json.dump(data, fp, indent=2, ensure_ascii=False)
        fp.write("\n")


def canon_certs(certs: list | None) -> list:
    out = []
    for c in certs or []:
        c = str(c).strip()
        c = CERT_CANON.get(c, CERT_CANON.get(c.upper(), c))
        if c not in out:
            out.append(c)
    return out


def longest_prefix_parent(self_id: str, ids: set[str]) -> str | None:
    best = None
    for cand in ids:
        if cand == self_id:
            continue
        if self_id.startswith(cand + "_") or self_id.startswith(cand + "-"):
            if best is None or len(cand) > len(best):
                best = cand
    return best


def collapse_double_brand(self_id: str) -> str:
    parts = self_id.split("_")
    if len(parts) >= 2 and parts[0] == parts[1]:
        return "_".join([parts[0]] + parts[2:])
    return self_id


def main() -> int:
    fixes = 0
    report = {"sharp_scrubbed": 0, "parents_fixed": 0, "self_parents": 0,
              "dangling": 0, "child_synced": 0, "ids_aligned": 0,
              "spec_weight_dropped": 0, "type_norm": 0, "cert_norm": 0,
              "deleted": 0, "titles": 0}

    # --- delete AI artifacts ---
    for name in DELETE_ARTIFACTS:
        path = os.path.join(HELMETS, name + ".json")
        if os.path.isfile(path):
            os.remove(path)
            report["deleted"] += 1
            fixes += 1

    files = sorted(glob.glob(os.path.join(HELMETS, "*.json")))
    items: dict[str, dict] = {}
    by_file: dict[str, str] = {}

    for fpath in files:
        stem = os.path.basename(fpath)[:-5]
        if stem in ("master", "master.example"):
            continue
        d = load(fpath)
        items[stem] = d
        by_file[stem] = fpath

    ids = set(items.keys())

    # ---------- pass 1: structural normalize ----------
    for stem, d in items.items():
        changed = False

        # Align internal id to filename stem (underscore convention).
        if d.get("id") != stem:
            d["id"] = stem
            report["ids_aligned"] += 1
            changed = True

        # Title repairs
        if stem in TITLE_FIXES and d.get("title") != TITLE_FIXES[stem]:
            d["title"] = TITLE_FIXES[stem]
            report["titles"] += 1
            changed = True
        title = d.get("title") or ""
        if title.startswith("-"):
            d["title"] = title.lstrip("-").strip() or stem.replace("_", " ").title()
            report["titles"] += 1
            changed = True

        # Type taxonomy
        t = (d.get("type") or "").strip()
        if t.lower() in TYPE_CANON:
            d["type"] = TYPE_CANON[t.lower()]
            report["type_norm"] += 1
            changed = True
        if d.get("category") and d["category"] != d.get("type"):
            d["category"] = d["type"]
            changed = True

        # Cert token normalization (root + specs + physics)
        specs = d.setdefault("specs", {})
        before = list(specs.get("certifications") or [])
        specs["certifications"] = canon_certs(before)
        if isinstance(d.get("certifications"), list):
            d["certifications"] = canon_certs(d["certifications"])
            if d["certifications"] != specs["certifications"]:
                d["certifications"] = specs["certifications"]
                changed = True
        else:
            d["certifications"] = specs["certifications"]
            changed = True
        if before != specs["certifications"]:
            report["cert_norm"] += 1
            changed = True
        phys = d.setdefault("physics_intelligence", {})
        if isinstance(phys, dict) and "safety_certifications" in phys:
            phys["safety_certifications"] = specs["certifications"]

        # Drop legacy spec_weight_g (always disagreed with canonical).
        if "spec_weight_g" in d:
            del d["spec_weight_g"]
            report["spec_weight_dropped"] += 1
            changed = True

        # Canonical noise mirror
        noise = specs.get("noise_db_at_100kph")
        if noise is not None and d.get("noise_db") != noise:
            d["noise_db"] = noise
            changed = True

        # P0 SHARP scrub — never ship unverified safety stars.
        safe = d.setdefault("safety_intelligence", {})
        if not isinstance(safe, dict):
            safe = {}
            d["safety_intelligence"] = safe
        sh = safe.get("sharp_rating", d.get("sharp_rating", 0))
        try:
            sh_val = float(sh or 0)
        except (TypeError, ValueError):
            sh_val = 0.0
        # No record carries a SHARP helmet id or impact-zone table; all star
        # claims are synthetic. Zero them and stamp verification explicitly.
        has_evidence = bool(safe.get("sharp_impact_zones")) or bool(
            (d.get("identifiers") or {}).get("sharp_id")
        )
        if has_evidence:
            safe["sharp_verified"] = True
        else:
            if sh_val > 0:
                report["sharp_scrubbed"] += 1
                changed = True
            safe["sharp_rating"] = 0
            safe["sharp_verified"] = False
            d["sharp_rating"] = 0
            d["sharp_verified"] = False

        if changed:
            save(by_file[stem], d)
            fixes += 1

    # ---------- pass 2: parent graph ----------
    # Group by dangling parent target so sibling bases can re-home children.
    missing_targets: dict[str, list[str]] = {}
    for stem, d in items.items():
        p = d.get("parent_id")
        if p in ("", 0, "0"):
            p = None
        if p is None:
            continue
        p = str(p)
        if p == stem:
            d["parent_id"] = None
            report["self_parents"] += 1
            fixes += 1
            continue
        if p not in ids:
            missing_targets.setdefault(p, []).append(stem)

    for missing, children in missing_targets.items():
        report["dangling"] += len(children)
        # Prefer an existing prefix parent on the child itself.
        remaining = []
        for stem in children:
            pref = longest_prefix_parent(stem, ids)
            collapsed = collapse_double_brand(stem)
            pref2 = longest_prefix_parent(collapsed, ids) if collapsed != stem else None
            target = pref or pref2
            if target and target != stem:
                items[stem]["parent_id"] = target
            else:
                remaining.append(stem)
        # Re-home leftover siblings under the shortest id in the group
        # (that shortest id becomes a root model).
        if remaining:
            base = min(remaining, key=len)
            for stem in remaining:
                if stem == base:
                    items[stem]["parent_id"] = None
                else:
                    # still prefer a longer-existing prefix if any
                    pref = longest_prefix_parent(stem, ids)
                    items[stem]["parent_id"] = pref if pref and pref != stem else base
        report["parents_fixed"] += len(children)
        fixes += len(children)

    # Final dangling sweep
    for stem, d in items.items():
        p = d.get("parent_id")
        if p and (p == stem or p not in ids):
            d["parent_id"] = None
            fixes += 1

    # ---------- pass 3: child structural inheritance ----------
    for stem, d in items.items():
        p = d.get("parent_id")
        if not p or p not in ids:
            continue
        parent = items[p]
        pspecs = parent.get("specs") or {}
        cspecs = d.setdefault("specs", {})
        changed = False
        # Colorways share shell/mass/acoustics. NEVER inherit certifications —
        # homologation is per-SKU and prior inheritance corrupted 68 records.
        for key in ("weight_g", "weight_lbs", "material", "strap_type", "noise_db_at_100kph"):
            pv = pspecs.get(key)
            if pv is None:
                continue
            if cspecs.get(key) != pv:
                cspecs[key] = pv
                changed = True
        phys = d.get("physics_intelligence")
        if not isinstance(phys, dict):
            phys = {}
            d["physics_intelligence"] = phys
            changed = True
        if cspecs.get("weight_g") is not None and phys.get("weight_grams") != cspecs["weight_g"]:
            phys["weight_grams"] = cspecs["weight_g"]
            changed = True
        if cspecs.get("certifications") is not None and phys.get("safety_certifications") != cspecs["certifications"]:
            phys["safety_certifications"] = cspecs["certifications"]
            changed = True
        if cspecs.get("noise_db_at_100kph") is not None:
            d["noise_db"] = cspecs["noise_db_at_100kph"]
            if isinstance(d.get("aero_acoustic_profile"), dict):
                d["aero_acoustic_profile"]["noise_db_at_100kph"] = cspecs["noise_db_at_100kph"]
        if d.get("certifications") != cspecs.get("certifications"):
            d["certifications"] = cspecs.get("certifications", d.get("certifications"))
            changed = True
        if d.get("type") != parent.get("type") and parent.get("type"):
            d["type"] = parent["type"]
            d["category"] = parent["type"]
            changed = True
        # SHARP must never disagree inside a SKU family
        psr = (parent.get("safety_intelligence") or {}).get("sharp_rating", 0)
        csr = d.setdefault("safety_intelligence", {})
        if isinstance(csr, dict) and csr.get("sharp_rating") != psr:
            csr["sharp_rating"] = psr
            csr["sharp_verified"] = False
            d["sharp_rating"] = psr
            changed = True
        if changed:
            report["child_synced"] += 1
            save(by_file[stem], d)
            fixes += 1

    # Persist parent graph changes from pass 2
    for stem, d in items.items():
        save(by_file[stem], d)

    # ---------- accessories ----------
    acc_placeholder = 0
    acc_compat = 0
    for fpath in sorted(glob.glob(os.path.join(ACCESSORIES, "*.json"))):
        stem = os.path.basename(fpath)[:-5]
        if stem in ("master", "master.example"):
            continue
        d = load(fpath)
        changed = False
        if "id" not in d or not d.get("entity"):
            d["entity"] = "accessory"
            d.setdefault("id", stem)
            d.setdefault("title", stem.replace("-", " ").title())
            d.setdefault("type", "Accessory")
            changed = True
        # compatibility object (schema)
        if "compatibility" not in d:
            types = d.get("compatible_helmet_types") or []
            if not types:
                types = ["Full Face", "Modular", "Adventure", "Open Face", "Half", "Motocross"]
                d["compatible_helmet_types"] = types
            d["compatibility"] = {
                "helmet_types": types,
                "brands": d.get("compatible_brands") or ["Universal"],
                "helmet_ids": d.get("compatible_helmet_ids") or [],
            }
            acc_compat += 1
            changed = True
        # placeholder price honesty (all 26 share $150)
        price = d.get("price")
        if isinstance(price, dict) and price.get("usd") == 150:
            d["price_placeholder"] = True
            acc_placeholder += 1
            changed = True
        if changed:
            save(fpath, d)
            fixes += 1

    # ---------- brands ----------
    prem = os.path.join(BRANDS, "premier.json")
    if os.path.isfile(prem):
        d = load(prem)
        if "id" not in d:
            d = {
                "entity": "brand",
                "id": "premier",
                "title": d.get("brand_name") or "Premier",
                **d,
            }
            save(prem, d)
            fixes += 1

    report["acc_compat"] = acc_compat
    report["acc_placeholder"] = acc_placeholder
    print(json.dumps({"fixes": fixes, **report}, indent=2))
    return 0


if __name__ == "__main__":
    sys.exit(main())
