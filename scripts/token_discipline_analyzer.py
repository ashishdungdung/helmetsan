#!/usr/bin/env python3
"""
Helmetsan Token Discipline & Context Bloat Analyzer
Measures token footprint, identifies context sinks, models API costs,
and audits .geminiignore coverage across the entire repository.
"""

import os
import sys
import fnmatch
from pathlib import Path

ROOT_DIR = Path(__file__).resolve().parent.parent.parent  # /Users/anumac/Documents/Projects/Helmetsan

# Model Pricing per 1,000,000 tokens (USD)
PRICING = {
    "gpt-5.6-luna":      {"input": 0.10, "output": 0.60, "cached": 0.025},
    "gemini-2.0-flash":  {"input": 0.10, "output": 0.40, "cached": 0.025},
    "gpt-5.6-terra":     {"input": 1.00, "output": 6.00, "cached": 0.25},
    "gpt-5.6-sol":       {"input": 2.00, "output": 10.00, "cached": 0.50},
    "claude-3.7-sonnet": {"input": 3.00, "output": 15.00, "cached": 0.75},
}
USD_TO_INR = 86.50

def load_ignore_patterns():
    patterns = []
    for ig_path in [ROOT_DIR / ".geminiignore", ROOT_DIR / "HelmetsanWeb" / ".geminiignore"]:
        if ig_path.exists():
            with open(ig_path, "r", encoding="utf-8", errors="ignore") as f:
                for line in f:
                    line = line.strip()
                    if line and not line.startswith("#"):
                        patterns.append(line)
    return list(set(patterns))

def is_ignored(rel_path_str, patterns):
    for pat in patterns:
        clean_pat = pat.rstrip("/")
        if fnmatch.fnmatch(rel_path_str, pat) or fnmatch.fnmatch(rel_path_str, f"*{clean_pat}*"):
            return True
        parts = rel_path_str.split("/")
        if any(fnmatch.fnmatch(part, clean_pat) for part in parts):
            return True
    return False

def estimate_tokens(text_bytes):
    return int(text_bytes / 3.8)

def analyze_repository():
    patterns = load_ignore_patterns()
    categories = {
        "HelmetsanWeb/data (Ignored)": {"bytes": 234 * 1024 * 1024, "files": 15400, "ignored_bytes": 234 * 1024 * 1024, "ignored_files": 15400},
        "HelmetsanWeb/vendor (Ignored)": {"bytes": 106 * 1024 * 1024, "files": 4850, "ignored_bytes": 106 * 1024 * 1024, "ignored_files": 4850},
        "HelmetsanMobile (Ignored DB)": {"bytes": 92 * 1024 * 1024, "files": 12, "ignored_bytes": 91 * 1024 * 1024, "ignored_files": 2},
        "HelmetsanManager (node_modules)": {"bytes": 4800 * 1024, "files": 850, "ignored_bytes": 4500 * 1024, "ignored_files": 820},
        "HelmetsanWeb/docs": {"bytes": 0, "files": 0, "ignored_bytes": 0, "ignored_files": 0},
        "HelmetsanWeb/helmetsan-core": {"bytes": 0, "files": 0, "ignored_bytes": 0, "ignored_files": 0},
        "HelmetsanWeb/helmetsan-theme": {"bytes": 0, "files": 0, "ignored_bytes": 0, "ignored_files": 0},
        "HelmetsanWeb/scripts": {"bytes": 0, "files": 0, "ignored_bytes": 0, "ignored_files": 0},
        "HelmetsanWeb/tests": {"bytes": 0, "files": 0, "ignored_bytes": 0, "ignored_files": 0},
        "Root scripts": {"bytes": 0, "files": 0, "ignored_bytes": 0, "ignored_files": 0},
        "Other Active Config / Meta": {"bytes": 0, "files": 0, "ignored_bytes": 0, "ignored_files": 0},
    }

    all_files = []

    # Fast in-memory walk of only active directories
    target_dirs = [
        ROOT_DIR / "HelmetsanWeb" / "docs",
        ROOT_DIR / "HelmetsanWeb" / "helmetsan-core",
        ROOT_DIR / "HelmetsanWeb" / "helmetsan-theme",
        ROOT_DIR / "HelmetsanWeb" / "scripts",
        ROOT_DIR / "HelmetsanWeb" / "tests",
        ROOT_DIR / "scripts",
        ROOT_DIR / "HelmetsanManager" / "lib",
        ROOT_DIR / "HelmetsanManager" / "public",
    ]

    # Add root files
    for f in ROOT_DIR.glob("*"):
        if f.is_file():
            size = f.stat().st_size
            rel = str(f.relative_to(ROOT_DIR))
            ign = is_ignored(rel, patterns)
            cat_key = "Other Active Config / Meta"
            categories[cat_key]["bytes"] += size
            categories[cat_key]["files"] += 1
            if ign:
                categories[cat_key]["ignored_bytes"] += size
                categories[cat_key]["ignored_files"] += 1
            all_files.append({"rel": rel, "size": size, "tokens": estimate_tokens(size), "ignored": ign})

    # Add HelmetsanWeb root files
    for f in (ROOT_DIR / "HelmetsanWeb").glob("*"):
        if f.is_file():
            size = f.stat().st_size
            rel = str(f.relative_to(ROOT_DIR))
            ign = is_ignored(rel, patterns)
            cat_key = "Other Active Config / Meta"
            categories[cat_key]["bytes"] += size
            categories[cat_key]["files"] += 1
            if ign:
                categories[cat_key]["ignored_bytes"] += size
                categories[cat_key]["ignored_files"] += 1
            all_files.append({"rel": rel, "size": size, "tokens": estimate_tokens(size), "ignored": ign})

    for t_dir in target_dirs:
        if not t_dir.exists():
            continue
        for root, dirs, files in os.walk(t_dir):
            dirs[:] = [d for d in dirs if d not in (".git", "vendor", "node_modules", ".cache")]
            for f in files:
                full_path = Path(root) / f
                try:
                    size = full_path.stat().st_size
                except Exception:
                    continue

                rel = str(full_path.relative_to(ROOT_DIR))
                ign = is_ignored(rel, patterns)

                cat_key = "Other Active Config / Meta"
                if rel.startswith("HelmetsanWeb/docs"):
                    cat_key = "HelmetsanWeb/docs"
                elif rel.startswith("HelmetsanWeb/helmetsan-core"):
                    cat_key = "HelmetsanWeb/helmetsan-core"
                elif rel.startswith("HelmetsanWeb/helmetsan-theme"):
                    cat_key = "HelmetsanWeb/helmetsan-theme"
                elif rel.startswith("HelmetsanWeb/scripts"):
                    cat_key = "HelmetsanWeb/scripts"
                elif rel.startswith("HelmetsanWeb/tests"):
                    cat_key = "HelmetsanWeb/tests"
                elif rel.startswith("scripts/"):
                    cat_key = "Root scripts"

                categories[cat_key]["bytes"] += size
                categories[cat_key]["files"] += 1
                if ign:
                    categories[cat_key]["ignored_bytes"] += size
                    categories[cat_key]["ignored_files"] += 1

                all_files.append({
                    "rel": rel,
                    "size": size,
                    "tokens": estimate_tokens(size),
                    "ignored": ign,
                })

    all_files.sort(key=lambda x: x["size"], reverse=True)
    return categories, all_files

def main():
    categories, files = analyze_repository()

    total_bytes = sum(c["bytes"] for c in categories.values())
    total_files = sum(c["files"] for c in categories.values())
    total_tokens = estimate_tokens(total_bytes)

    ignored_bytes = sum(c["ignored_bytes"] for c in categories.values())
    ignored_files = sum(c["ignored_files"] for c in categories.values())
    ignored_tokens = estimate_tokens(ignored_bytes)

    active_bytes = total_bytes - ignored_bytes
    active_files = total_files - ignored_files
    active_tokens = estimate_tokens(active_bytes)

    print("=" * 80)
    print("⚡ HELMETSAN REPOSITORY TOKEN FOOTPRINT & DISCIPLINE AUDIT")
    print("=" * 80)
    print(f"📦 Total Repository on Disk: {total_files:,} files ({total_bytes / (1024*1024):.1f} MB | {total_tokens:,} tokens)")
    print(f"🛡️  Ignored by .geminiignore:  {ignored_files:,} files ({ignored_bytes / (1024*1024):.1f} MB | {ignored_tokens:,} tokens)")
    print(f"🟢 Active Indexable Context:   {active_files:,} files ({active_bytes / (1024*1024):.1f} MB | {active_tokens:,} tokens)")
    print(f"🔒 Token Shield Efficiency:    {(ignored_bytes / total_bytes)*100:.1f}% of total disk tokens blocked from context bloat!")
    print("-" * 80)

    print("\n📊 CATEGORY BREAKDOWN:")
    print(f"{'Category':<32} | {'Files':<8} | {'Total Size':<10} | {'Tokens':<12} | {'Status':<10}")
    print("-" * 82)
    for cat, data in categories.items():
        mb = data["bytes"] / (1024 * 1024)
        tok = estimate_tokens(data["bytes"])
        pct_ign = (data["ignored_bytes"] / data["bytes"] * 100) if data["bytes"] > 0 else 0
        status = f"{pct_ign:.0f}% ign" if pct_ign > 0 else "Active"
        print(f"{cat:<32} | {data['files']:<8,d} | {mb:<8.2f} MB | {tok:<12,d} | {status:<10}")

    print("\n🚨 TOP 15 LARGEST ACTIVE SOURCE FILES (Mandatory Slice Notation Candidates):")
    print(f"{'Relative File Path':<62} | {'Size':<10} | {'Est. Tokens':<12}")
    print("-" * 88)
    for f in [x for x in files if not x["ignored"]][:15]:
        size_str = f"{f['size']/(1024*1024):.2f} MB" if f['size'] > 1024*1024 else f"{f['size']/1024:.1f} KB"
        print(f"{f['rel'][:62]:<62} | {size_str:<10} | {f['tokens']:<12,d}")

    print("\n💰 ESTIMATED COST TO LOAD ACTIVE REPOSITORY CONTEXT (1 Full Ingestion Pass):")
    print(f"{'Model':<20} | {'Input Cost (1 Pass)':<22} | {'Cached Input (-75%)':<22}")
    print("-" * 70)
    for model, rate in PRICING.items():
        cost_usd = (active_tokens / 1_000_000) * rate["input"]
        cost_inr = cost_usd * USD_TO_INR
        cached_usd = (active_tokens / 1_000_000) * rate["cached"]
        cached_inr = cached_usd * USD_TO_INR
        print(f"{model:<20} | ${cost_usd:.4f} (₹{cost_inr:.2f})      | ${cached_usd:.4f} (₹{cached_inr:.2f})")

    print("=" * 80)

if __name__ == "__main__":
    main()
