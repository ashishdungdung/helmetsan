#!/usr/bin/env python3
"""
Repair electric/powertrain contradictions on the Matter AERA / Obben Rorr records.

These 22 records are genuinely electric (name + brand + fuel_type) but carry
fabricated petrol specs — the same defect class as AUD2-01.

Structured fields are corrected deterministically against the convention used by
the 367 clean electric records:
    displacement_cc        -> 0
    fuel_capacity_l        -> 0
    matrix.displacement    -> "high-efficiency electric"

Prose that still asserts a combustion engine is rewritten by the local
LM Studio node (google/gemma-4-12b-qat, max_tokens >= 500) per the local AI
protocol. If the node is unreachable the record is queued to
data/task_queue.json and the run exits gracefully — never a cloud fallback.

Usage:
    python3 scripts/fix_electric_spec_contradictions.py --dry-run
    python3 scripts/fix_electric_spec_contradictions.py
"""

from __future__ import annotations

import glob
import json
import os
import re
import sys
import urllib.error
import urllib.request

WEB_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
MOTORCYCLES = os.path.join(WEB_DIR, "data", "motorcycles")
TASK_QUEUE = os.path.join(WEB_DIR, "data", "task_queue.json")

LM_BASE = os.environ.get("LM_STUDIO_BASE_URL", "http://127.0.0.1:1234/v1")
LM_MODEL = "google/gemma-4-12b-qat"

TARGET_RE = re.compile(r"Matter AERA|Obben Rorr", re.I)
FORBIDDEN = (r"\b\d+cc\b", r"fuel tank", r"fuel economy", r"\bpowerplant\b",
             r"\bengine\b", r"radiator", r"internal combustion")


def load(path: str) -> dict:
    with open(path, "r", encoding="utf-8") as fp:
        return json.load(fp)


def save(path: str, data: dict) -> None:
    with open(path, "w", encoding="utf-8") as fp:
        json.dump(data, fp, indent=2, ensure_ascii=False)
        fp.write("\n")


def contaminated(text: object) -> bool:
    return isinstance(text, str) and any(
        re.search(p, text, re.I) for p in FORBIDDEN)


def node_online() -> bool:
    try:
        with urllib.request.urlopen(f"{LM_BASE}/models", timeout=4) as res:
            models = [m["id"] for m in json.loads(res.read()).get("data", [])]
        return LM_MODEL in models
    except (urllib.error.URLError, OSError, ValueError):
        return False


def rewrite(text: str, title: str, feedback: str = "") -> str:
    # Keep this prompt terse: a long constraint list sends Gemma-4 into runaway
    # reasoning that exhausts max_tokens and returns empty content.
    prompt = (
        f"Rewrite this as an electric motorcycle description. Remove combustion "
        f"references (cc, engine, powerplant, fuel tank, fuel economy, radiator). "
        f"Keep the HP, Nm and seat height numbers. Output only the paragraph.\n\n{text}"
    )
    if feedback:
        prompt += f"\n\nYour previous output was rejected: {feedback}"
    body = json.dumps({
        "model": LM_MODEL,
        "messages": [{"role": "user", "content": prompt}],
        "max_tokens": 1600,
        "temperature": 0.2,
    }).encode()
    req = urllib.request.Request(
        f"{LM_BASE}/chat/completions", data=body,
        headers={"Content-Type": "application/json"})
    with urllib.request.urlopen(req, timeout=180) as res:
        payload = json.loads(res.read())
    out = (payload["choices"][0]["message"]["content"] or "").strip()
    return re.sub(r"^(?:Model|Subject):\s*[^\n]*\n+", "", out).strip()


def validate(text: str, original: str) -> list[str]:
    errors = [f"still contains '{p}'" for p in FORBIDDEN if re.search(p, text, re.I)]
    if not 150 <= len(text) <= 900:
        errors.append(f"length {len(text)} outside 150-900")
    hp = re.search(r"(\d+)\s*HP", original, re.I)
    if hp and not re.search(rf"{hp.group(1)}\s*HP", text, re.I):
        errors.append("dropped the horsepower figure")
    seat = re.search(r"(\d{3})\s*mm seat height", original, re.I)
    if seat and not re.search(rf"{seat.group(1)}\s*mm seat height", text, re.I):
        errors.append("dropped the seat height figure")
    invented = set(re.findall(r"\d+", text)) - set(re.findall(r"\d+", original))
    if invented:
        errors.append(f"introduced new numbers {sorted(invented)}")
    return errors


def fix_structured(record: dict) -> bool:
    changed = False
    if record.get("displacement_cc"):
        record["displacement_cc"] = 0
        changed = True
    if record.get("fuel_capacity_l"):
        record["fuel_capacity_l"] = 0
        changed = True

    matrix = record.setdefault("intelligence_matrix", {}).setdefault(
        "powertrain_and_chassis", {})
    if matrix.get("displacement") != "high-efficiency electric":
        matrix["displacement"] = "high-efficiency electric"
        changed = True

    desc = record.get("yoast_metadesc")
    if isinstance(desc, str) and "engine power" in desc:
        record["yoast_metadesc"] = desc.replace("engine power", "motor power")
        changed = True

    for acc in record.get("compatible_accessories") or []:
        reason = acc.get("reason")
        if isinstance(reason, str) and "engine braking" in reason:
            acc["reason"] = reason.replace("engine braking", "regenerative braking")
            changed = True

    quals = record.get("qualitative_intelligence") or {}
    for key in ("real_world_pros", "real_world_cons"):
        items = quals.get(key)
        if isinstance(items, list):
            kept = [i for i in items if not contaminated(i)]
            if len(kept) != len(items):
                quals[key] = kept
                changed = True
    return changed


def main() -> int:
    dry_run = "--dry-run" in sys.argv
    report = {"records": 0, "structured_fixed": 0, "prose_rewritten": 0,
              "rejected": [], "queued": [], "dry_run": dry_run}

    paths = [p for p in sorted(glob.glob(os.path.join(MOTORCYCLES, "*.json")))
             if TARGET_RE.search(load(p).get("title", ""))]
    report["records"] = len(paths)

    online = node_online()
    if not online and not dry_run:
        report["queued"] = [os.path.basename(p) for p in paths]
        queue = load(TASK_QUEUE) if os.path.isfile(TASK_QUEUE) else []
        queue.extend({"task": "fix_electric_spec_contradictions",
                      "file": os.path.basename(p), "reason": "lm_studio_offline"}
                     for p in paths)
        save(TASK_QUEUE, queue)
        print(json.dumps(report, indent=2))
        print("LM Studio offline -> queued, nothing written.")
        return 0

    for path in paths:
        record = load(path)
        if fix_structured(record):
            report["structured_fixed"] += 1

        original = record.get("description") or ""
        if contaminated(original):
            outcome = None
            feedback = ""
            for attempt in range(2):
                try:
                    candidate = rewrite(original, record.get("title", ""), feedback)
                except (urllib.error.URLError, OSError, ValueError, KeyError) as exc:
                    print(f"  ! {os.path.basename(path)} call failed: {exc}",
                          file=sys.stderr)
                    report["rejected"].append(os.path.basename(path))
                    break
                errors = validate(candidate, original)
                if not errors:
                    outcome = candidate
                    break
                feedback = "; ".join(errors)
                print(f"  ~ retry {attempt + 1} {os.path.basename(path)}: "
                      f"{feedback}", file=sys.stderr)
            if outcome:
                record["description"] = outcome
                record["editorial_overview"] = outcome
                report["prose_rewritten"] += 1
            else:
                report["rejected"].append(os.path.basename(path))
            print(f"  [{report['prose_rewritten']}/{report['records']}] "
                  f"{os.path.basename(path)}", file=sys.stderr)

        if not dry_run:
            save(path, record)

    print(json.dumps(report, indent=2))
    return 0


if __name__ == "__main__":
    sys.exit(main())
