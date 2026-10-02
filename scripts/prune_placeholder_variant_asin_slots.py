#!/usr/bin/env python3
"""
Remove placeholder Amazon locale slots from helmet variants (AUD2-09).

AUD2-09: 36,124 variant-level Amazon country-locale slots carry
`asin: null` + `status: search_fallback` — the catalog generator
pre-allocated all 22 marketplaces for every variant without ever
resolving a real PA-API identifier.

Nothing at runtime reads them:
  * RevenueService::buildSmartHybridUrl() reads a variant's `title` only
    (Stage 3 query source) — never its `identifiers`.
  * export-mobile-db.py reads `identifiers.amazon.us`, and already falls
    back to `f"{brand} {title} {v_color}"` when it is absent.
  * hybrid_affiliate_resolver.mjs falls back to `searchTerms`.

A variant's registry is dropped only when *no* locale holds a concrete
ASIN, so any real identifier is preserved. Sibling keys (sku, ean, mpn,
gtin, retailer_skus) are never touched, and parent-level `identifiers`
is left alone — its per-locale search fallback is the documented
"404 Dog Prevention" stage 3 of the hybrid redirect engine.

Usage:
    python3 scripts/prune_placeholder_variant_asin_slots.py --dry-run
    python3 scripts/prune_placeholder_variant_asin_slots.py
"""

from __future__ import annotations

import glob
import json
import os
import sys

WEB_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
HELMETS = os.path.join(WEB_DIR, "data", "helmets")


def load(path: str) -> dict:
    with open(path, "r", encoding="utf-8") as fp:
        return json.load(fp)


def save(path: str, data: dict) -> None:
    with open(path, "w", encoding="utf-8") as fp:
        json.dump(data, fp, indent=2, ensure_ascii=False)
        fp.write("\n")


def prune(variants: list) -> tuple[int, int, int]:
    """Drop all-placeholder amazon registries. Returns (slots, kept, variants)."""
    slots = kept_slots = pruned_variants = 0
    for variant in variants:
        identifiers = variant.get("identifiers")
        if not isinstance(identifiers, dict):
            continue
        registry = identifiers.get("amazon")
        if not isinstance(registry, dict) or not registry:
            continue

        slots += len(registry)
        if any(isinstance(slot, dict) and slot.get("asin") for slot in registry.values()):
            kept_slots += len(registry)
            continue

        del identifiers["amazon"]
        if not identifiers:
            variant.pop("identifiers", None)
        pruned_variants += 1
    return slots, kept_slots, pruned_variants


def main() -> int:
    dry_run = "--dry-run" in sys.argv
    report = {
        "slots_seen": 0,
        "slots_pruned": 0,
        "slots_kept_with_asin": 0,
        "variants_pruned": 0,
        "files_changed": 0,
        "dry_run": dry_run,
    }

    for path in sorted(glob.glob(os.path.join(HELMETS, "*.json"))):
        record = load(path)
        variants = record.get("variants")
        if not isinstance(variants, list) or not variants:
            continue

        before = report["slots_pruned"], report["variants_pruned"]
        slots, kept, pruned_variants = prune(variants)
        if pruned_variants == 0:
            continue

        report["slots_seen"] += slots
        report["slots_pruned"] += slots - kept
        report["slots_kept_with_asin"] += kept
        report["variants_pruned"] += pruned_variants
        report["files_changed"] += 1
        assert before is not None
        if not dry_run:
            save(path, record)

    print(json.dumps(report, indent=2))
    return 0


if __name__ == "__main__":
    sys.exit(main())
