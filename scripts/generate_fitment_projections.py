#!/usr/bin/env python3
"""
Helmetsan Materialized Fitment Projection Generator
Strategy formulated with GPT-5.6-Luna in LUNA_GAP_ANALYSIS_AND_ENHANCEMENTS.md.

Extracts 38,196 compatibility records from catalog.db and pre-materializes
high-performance, bounded, directional fitment projections for runtime 0ms overhead:
- motorcycle_helmet_fitments.json: Motorcycle ID -> Top 5 Recommended Helmets
- helmet_motorcycle_fitments.json: Helmet ID -> Top 5 Recommended Motorcycles
"""

import os
import json
import sqlite3
from pathlib import Path

SCRIPT_DIR = Path(__file__).resolve().parent
WEB_DIR = SCRIPT_DIR.parent
DB_PATH = WEB_DIR / "data" / "catalog.db"
PROJECTIONS_DIR = WEB_DIR / "data" / "projections"

PROJECTIONS_DIR.mkdir(parents=True, exist_ok=True)

print("🚀 Initializing Fitment Projection Generator...")
print(f"   Database: {DB_PATH}")

if not DB_PATH.exists():
    print(f"❌ catalog.db not found at {DB_PATH}")
    exit(1)

conn = sqlite3.connect(str(DB_PATH))
conn.row_factory = sqlite3.Row
cursor = conn.cursor()

# 1. Fetch helmets and motorcycles mapping
helmets = {}
for row in cursor.execute("SELECT id, slug, title, brand, helmet_type FROM helmets"):
    h_dict = dict(row)
    helmets[h_dict["id"]] = h_dict
    if h_dict.get("slug"):
        helmets[h_dict["slug"]] = h_dict

motorcycles = {}
for row in cursor.execute("SELECT id, slug, title, brand, category, riding_position FROM motorcycles"):
    m_dict = dict(row)
    motorcycles[m_dict["id"]] = m_dict
    if m_dict.get("slug"):
        motorcycles[m_dict["slug"]] = m_dict

print(f"📦 Loaded {len(helmets)//2} helmets and {len(motorcycles)//2} motorcycles.")

# 2. Query all motorcycle -> helmet compatibility records
print("🔍 Extracting compatibility matrix (38,196 records)...")
compat_rows = cursor.execute("""
    SELECT entity_a_id, entity_b_id, entity_a_type, entity_b_type, score, reason
    FROM compatibility
    ORDER BY score DESC
""").fetchall()

print(f"   Total compatibility rows: {len(compat_rows):,}")

bike_to_helmets = {}
helmet_to_bikes = {}

for row in compat_rows:
    a_id = row["entity_a_id"]
    b_id = row["entity_b_id"]
    a_type = row["entity_a_type"]
    b_type = row["entity_b_type"]
    score = int(row["score"] or 80)
    reason = row["reason"] or ""

    bike_id = None
    helmet_id = None

    if a_type == "motorcycle" and b_type == "helmet":
        bike_id = a_id
        helmet_id = b_id
    elif a_type == "helmet" and b_type == "motorcycle":
        helmet_id = a_id
        bike_id = b_id
    else:
        continue

    helmet_obj = helmets.get(helmet_id) or helmets.get(helmet_id.replace("-", "_"))
    bike_obj = motorcycles.get(bike_id) or motorcycles.get(bike_id.replace("-", "_"))

    h_canonical = helmet_obj["id"] if helmet_obj else helmet_id
    b_canonical = bike_obj["id"] if bike_obj else bike_id

    # Aggregate for Motorcycle -> Helmets (max 5)
    if b_canonical not in bike_to_helmets:
        bike_to_helmets[b_canonical] = []
    if len(bike_to_helmets[b_canonical]) < 5 and not any(item["helmet_id"] == h_canonical for item in bike_to_helmets[b_canonical]):
        bike_to_helmets[b_canonical].append({
            "helmet_id": h_canonical,
            "helmet_title": helmet_obj["title"] if helmet_obj else h_canonical.replace("_", " ").title(),
            "helmet_brand": helmet_obj["brand"] if helmet_obj else "",
            "helmet_type": helmet_obj["helmet_type"] if helmet_obj else "",
            "score": score,
            "reason": reason,
            "reason_code": "motorcycle_fitment"
        })

    # Aggregate for Helmet -> Motorcycles (max 5)
    if h_canonical not in helmet_to_bikes:
        helmet_to_bikes[h_canonical] = []
    if len(helmet_to_bikes[h_canonical]) < 5 and not any(item["motorcycle_id"] == b_canonical for item in helmet_to_bikes[h_canonical]):
        helmet_to_bikes[h_canonical].append({
            "motorcycle_id": b_canonical,
            "motorcycle_title": bike_obj["title"] if bike_obj else b_canonical.replace("_", " ").title(),
            "motorcycle_brand": bike_obj["brand"] if bike_obj else "",
            "motorcycle_category": bike_obj["category"] if bike_obj else "",
            "riding_position": bike_obj["riding_position"] if bike_obj else "",
            "score": score,
            "reason": reason,
            "reason_code": "helmet_fitment"
        })

bike_out_file = PROJECTIONS_DIR / "motorcycle_helmet_fitments.json"
helmet_out_file = PROJECTIONS_DIR / "helmet_motorcycle_fitments.json"

with open(bike_out_file, "w", encoding="utf-8") as f:
    json.dump(bike_to_helmets, f, indent=2, ensure_ascii=False)

with open(helmet_out_file, "w", encoding="utf-8") as f:
    json.dump(helmet_to_bikes, f, indent=2, ensure_ascii=False)

conn.close()

print(f"✅ Materialized Projections Generated Successfully!")
print(f"   🏍️  Motorcycle -> Helmets: {len(bike_to_helmets):,} bikes projected -> {bike_out_file}")
print(f"   🪖 Helmet -> Motorcycles: {len(helmet_to_bikes):,} helmets projected -> {helmet_out_file}")
