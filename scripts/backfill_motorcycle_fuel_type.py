#!/usr/bin/env python3
"""
Backfill missing fuel_type + powertrain on data/motorcycles/*.json (AUD2).

`fuel_type` and `powertrain` are an invariant pair in this catalog: every one
of the 1,261 labelled records carries both, so they are always written together.

Classification is deterministic (no LLM, no network):

  electric  The record's own identity fields assert an EV (Matter AERA,
            Obben Rorr). Their petrol-shaped specs are a separate AUD2-01
            class defect and are deliberately left untouched here.
  petrol    displacement_cc > 0, fuel_capacity_l > 0, no battery_capacity_kwh.
            This profile matches all 859 already-labelled petrols and none
            of the 402 already-labelled electrics.
  (null)    No rule fires -> reported under "unresolved", never guessed.

Usage:
    python3 scripts/backfill_motorcycle_fuel_type.py --dry-run
    python3 scripts/backfill_motorcycle_fuel_type.py
"""

from __future__ import annotations

import glob
import json
import os
import re
import sys

WEB_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
MOTORCYCLES = os.path.join(WEB_DIR, "data", "motorcycles")

ELECTRIC_IDENTITY_RE = re.compile(r"\b(?:Matter AERA|Obben Rorr)\b", re.I)

POWERTRAIN_FOR = {
    "petrol": "internal combustion engine",
    "electric": "electric",
}


def load(path: str) -> dict:
    with open(path, "r", encoding="utf-8") as fp:
        return json.load(fp)


def save(path: str, data: dict) -> None:
    with open(path, "w", encoding="utf-8") as fp:
        json.dump(data, fp, indent=2, ensure_ascii=False)
        fp.write("\n")


def classify(record: dict) -> str | None:
    title = record.get("title") or ""
    if ELECTRIC_IDENTITY_RE.search(title):
        return "electric"

    displacement = record.get("displacement_cc") or 0
    fuel = record.get("fuel_capacity_l") or 0
    has_battery = bool(record.get("battery_capacity_kwh"))
    if displacement > 0 and fuel > 0 and not has_battery:
        return "petrol"
    return None


def main() -> int:
    dry_run = "--dry-run" in sys.argv
    report = {
        "petrol": 0,
        "electric": 0,
        "already_labelled": 0,
        "unresolved": [],
        "pair_invariant_violations": [],
    }

    paths = sorted(glob.glob(os.path.join(MOTORCYCLES, "*.json")))
    for path in paths:
        record = load(path)
        label = record.get("fuel_type")

        if label:
            report["already_labelled"] += 1
            if record.get("powertrain") != POWERTRAIN_FOR.get(label):
                report["pair_invariant_violations"].append(record.get("title"))
            continue

        label = classify(record)
        if label is None:
            report["unresolved"].append(record.get("title") or os.path.basename(path))
            continue

        record["fuel_type"] = label
        record["powertrain"] = POWERTRAIN_FOR[label]
        if not dry_run:
            save(path, record)
        report[label] += 1

    report["scanned"] = len(paths)
    report["dry_run"] = dry_run
    print(json.dumps(report, indent=2, ensure_ascii=False))

    if report["pair_invariant_violations"]:
        return 1
    return 0


if __name__ == "__main__":
    sys.exit(main())
