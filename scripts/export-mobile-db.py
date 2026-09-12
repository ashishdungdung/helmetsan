#!/usr/bin/env python3
"""
Helmetsan Master Catalog SQLite Compiler for HelmetsanMobile
Builds an offline-first, highly optimized SQLite database (catalog.db) with FTS5 search
from the master JSON files in HelmetsanWeb/data/.
"""

import os
import sys
import json
import glob
import sqlite3
from pathlib import Path

def get_paths():
    script_dir = Path(__file__).resolve().parent
    web_dir = script_dir.parent
    root_dir = web_dir.parent
    mobile_dir = root_dir / "HelmetsanMobile"
    db_dir = mobile_dir / "assets" / "database"
    db_path = db_dir / "catalog.db"
    data_dir = web_dir / "data"
    
    return web_dir, mobile_dir, db_dir, db_path, data_dir

def init_database(conn):
    cursor = conn.cursor()
    
    # 1. Brands Table
    cursor.execute("""
    CREATE TABLE IF NOT EXISTS brands (
        id TEXT PRIMARY KEY,
        name TEXT NOT NULL,
        slug TEXT,
        country TEXT,
        description TEXT,
        helmet_count INTEGER DEFAULT 0
    );
    """)
    
    # 2. Helmets Table
    cursor.execute("""
    CREATE TABLE IF NOT EXISTS helmets (
        id TEXT PRIMARY KEY,
        slug TEXT,
        title TEXT NOT NULL,
        brand TEXT NOT NULL,
        helmet_type TEXT,
        helmet_family TEXT,
        head_shape TEXT,
        material TEXT,
        strap_type TEXT,
        weight_g INTEGER,
        weight_lbs REAL,
        certifications TEXT,
        price_usd REAL DEFAULT 0,
        price_inr REAL DEFAULT 0,
        price_eur REAL DEFAULT 0,
        price_gbp REAL DEFAULT 0,
        price_jpy REAL DEFAULT 0,
        description TEXT,
        variants_count INTEGER DEFAULT 0,
        raw_json TEXT
    );
    """)
    
    # 3. Variants Table
    cursor.execute("""
    CREATE TABLE IF NOT EXISTS variants (
        id TEXT PRIMARY KEY,
        helmet_id TEXT NOT NULL,
        title TEXT,
        color TEXT,
        color_family TEXT,
        sku TEXT,
        finish TEXT,
        availability TEXT,
        price_usd REAL,
        price_inr REAL,
        FOREIGN KEY (helmet_id) REFERENCES helmets (id) ON DELETE CASCADE
    );
    """)
    
    # 4. Indexes for rapid filtering
    cursor.execute("CREATE INDEX IF NOT EXISTS idx_helmets_brand ON helmets(brand);")
    cursor.execute("CREATE INDEX IF NOT EXISTS idx_helmets_type ON helmets(helmet_type);")
    cursor.execute("CREATE INDEX IF NOT EXISTS idx_helmets_head_shape ON helmets(head_shape);")
    cursor.execute("CREATE INDEX IF NOT EXISTS idx_helmets_price_usd ON helmets(price_usd);")
    cursor.execute("CREATE INDEX IF NOT EXISTS idx_variants_helmet_id ON variants(helmet_id);")
    
    # 5. Motorcycles Table
    cursor.execute("""
    CREATE TABLE IF NOT EXISTS motorcycles (
        id TEXT PRIMARY KEY,
        slug TEXT,
        title TEXT NOT NULL,
        brand TEXT NOT NULL,
        category TEXT,
        displacement_cc INTEGER,
        power_hp REAL,
        torque_nm REAL,
        curb_weight_kg REAL,
        seat_height_mm INTEGER,
        fuel_capacity_l REAL,
        riding_position TEXT,
        price_usd REAL DEFAULT 0,
        price_inr REAL DEFAULT 0,
        description TEXT,
        verdict TEXT,
        raw_json TEXT
    );
    """)
    cursor.execute("CREATE INDEX IF NOT EXISTS idx_motorcycles_brand ON motorcycles(brand);")
    cursor.execute("CREATE INDEX IF NOT EXISTS idx_motorcycles_category ON motorcycles(category);")
    cursor.execute("CREATE INDEX IF NOT EXISTS idx_motorcycles_displacement ON motorcycles(displacement_cc);")

    # 6. Full Text Search 5 (FTS5) for Helmets and Motorcycles
    cursor.execute("""
    CREATE VIRTUAL TABLE IF NOT EXISTS helmets_fts USING fts5(
        id UNINDEXED,
        title,
        brand,
        helmet_type,
        material,
        certifications,
        description,
        tokenize = 'porter ascii'
    );
    """)
    
    cursor.execute("""
    CREATE VIRTUAL TABLE IF NOT EXISTS motorcycles_fts USING fts5(
        id UNINDEXED,
        title,
        brand,
        category,
        riding_position,
        description,
        tokenize = 'porter ascii'
    );
    """)

    # 7. Accessories Table
    cursor.execute("""
    CREATE TABLE IF NOT EXISTS accessories (
        id TEXT PRIMARY KEY,
        title TEXT NOT NULL,
        brand TEXT NOT NULL,
        category TEXT,
        subcategory TEXT,
        price_usd REAL DEFAULT 0,
        price_inr REAL DEFAULT 0,
        description TEXT,
        verdict TEXT,
        raw_json TEXT
    );
    """)
    cursor.execute("CREATE INDEX IF NOT EXISTS idx_accessories_brand ON accessories(brand);")
    cursor.execute("CREATE INDEX IF NOT EXISTS idx_accessories_sub ON accessories(subcategory);")

    # 8. Full Text Search 5 (FTS5) for Accessories
    cursor.execute("""
    CREATE VIRTUAL TABLE IF NOT EXISTS accessories_fts USING fts5(
        id UNINDEXED,
        title,
        brand,
        subcategory,
        description,
        tokenize = 'porter ascii'
    );
    """)

    # 9. Cross-Entity Compatibility Junction Table
    cursor.execute("""
    CREATE TABLE IF NOT EXISTS compatibility (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        entity_a_id TEXT NOT NULL,
        entity_b_id TEXT NOT NULL,
        entity_a_type TEXT NOT NULL,
        entity_b_type TEXT NOT NULL,
        score INTEGER DEFAULT 80,
        reason TEXT
    );
    """)
    cursor.execute("CREATE INDEX IF NOT EXISTS idx_compat_a ON compatibility(entity_a_id);")
    cursor.execute("CREATE INDEX IF NOT EXISTS idx_compat_b ON compatibility(entity_b_id);")
    cursor.execute("CREATE INDEX IF NOT EXISTS idx_compat_types ON compatibility(entity_a_type, entity_b_type);")
    
    conn.commit()

def compile_catalog():
    web_dir, mobile_dir, db_dir, db_path, data_dir = get_paths()
    
    db_dir.mkdir(parents=True, exist_ok=True)
    if db_path.exists():
        db_path.unlink()
        
    print(f"🚀 Initializing SQLite catalog database at:\n   {db_path}")
    conn = sqlite3.connect(str(db_path))
    init_database(conn)
    cursor = conn.cursor()
    
    # Ingest Brands
    brand_files = glob.glob(str(data_dir / "brands" / "*.json"))
    print(f"📦 Processing {len(brand_files)} brands...")
    for b_path in brand_files:
        try:
            with open(b_path, "r", encoding="utf-8") as f:
                b = json.load(f)
                b_id = b.get("id") or Path(b_path).stem
                name = b.get("name") or b.get("title") or b_id.replace("_", " ").title()
                country = b.get("country") or b.get("origin_country") or ""
                desc = b.get("description") or ""
                slug = b.get("slug") or b_id.replace("_", "-")
                
                cursor.execute("""
                INSERT OR REPLACE INTO brands (id, name, slug, country, description)
                VALUES (?, ?, ?, ?, ?)
                """, (b_id, name, slug, country, desc))
        except Exception as e:
            continue
            
    # Ingest Helmets
    helmet_files = glob.glob(str(data_dir / "helmets" / "*.json"))
    print(f"🏍️ Processing {len(helmet_files)} helmets...")
    
    brand_counts = {}
    helmets_batch = []
    variants_batch = []
    fts_batch = []
    
    for h_path in helmet_files:
        try:
            with open(h_path, "r", encoding="utf-8") as f:
                h = json.load(f)
                h_id = h.get("id") or Path(h_path).stem
                title = h.get("title") or h.get("name") or h_id.replace("_", " ").title()
                brand = h.get("brand") or "Independent"
                h_type = h.get("type") or h.get("category") or "Full Face"
                family = h.get("helmet_family") or ""
                head_shape = h.get("head_shape") or ""
                
                specs = h.get("specs") or {}
                material = specs.get("material") or ""
                strap = specs.get("strap_type") or ""
                weight_g = specs.get("weight_g") or 0
                weight_lbs = specs.get("weight_lbs") or 0.0
                
                certs = specs.get("certifications") or []
                certs_str = ", ".join(certs) if isinstance(certs, list) else str(certs)
                
                prices = h.get("price") or {}
                p_usd = float(prices.get("usd") or 0)
                p_inr = float(prices.get("inr") or 0)
                p_eur = float(prices.get("eur") or 0)
                p_gbp = float(prices.get("gbp") or 0)
                p_jpy = float(prices.get("jpy") or 0)
                
                desc = h.get("description") or ""
                variants = h.get("variants") or []
                slug = h.get("slug") or h_id.replace("_", "-")
                
                brand_counts[brand] = brand_counts.get(brand, 0) + 1
                
                helmets_batch.append((
                    h_id, slug, title, brand, h_type, family, head_shape,
                    material, strap, weight_g, weight_lbs, certs_str,
                    p_usd, p_inr, p_eur, p_gbp, p_jpy,
                    desc, len(variants), json.dumps(h)
                ))
                
                fts_batch.append((
                    h_id, title, brand, h_type, material, certs_str, desc
                ))
                
                for v in variants:
                    v_id = v.get("id") or f"{h_id}_{v.get('color', '')}"
                    v_title = v.get("title") or ""
                    v_color = v.get("color") or ""
                    v_color_family = v.get("color_family") or ""
                    v_sku = v.get("sku") or ""
                    v_finish = v.get("finish") or ""
                    v_avail = v.get("availability") or "instock"
                    v_prices = v.get("price") or prices
                    v_usd = float(v_prices.get("usd") or p_usd)
                    v_inr = float(v_prices.get("inr") or p_inr)
                    
                    variants_batch.append((
                        v_id, h_id, v_title, v_color, v_color_family,
                        v_sku, v_finish, v_avail, v_usd, v_inr
                    ))
        except Exception as e:
            continue

    cursor.executemany("""
    INSERT OR REPLACE INTO helmets (
        id, slug, title, brand, helmet_type, helmet_family, head_shape,
        material, strap_type, weight_g, weight_lbs, certifications,
        price_usd, price_inr, price_eur, price_gbp, price_jpy,
        description, variants_count, raw_json
    ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    """, helmets_batch)
    
    cursor.executemany("""
    INSERT INTO helmets_fts (id, title, brand, helmet_type, material, certifications, description)
    VALUES (?, ?, ?, ?, ?, ?, ?)
    """, fts_batch)
    
    if variants_batch:
        cursor.executemany("""
        INSERT OR REPLACE INTO variants (
            id, helmet_id, title, color, color_family,
            sku, finish, availability, price_usd, price_inr
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        """, variants_batch)
        
    # Ingest Motorcycles
    bike_files = glob.glob(str(data_dir / "motorcycles" / "*.json"))
    print(f"🏍️ Processing {len(bike_files)} motorcycles...")
    
    bikes_batch = []
    bikes_fts_batch = []
    
    for b_path in bike_files:
        try:
            with open(b_path, "r", encoding="utf-8") as f:
                b = json.load(f)
                b_id = b.get("id") or Path(b_path).stem
                title = b.get("title") or b.get("name") or b_id.replace("_", " ").title()
                brand = b.get("brand") or b.get("make") or "Independent"
                category = b.get("category") or "Urban Roadster"
                cc = int(b.get("displacement_cc") or 0)
                hp = float(b.get("power_hp") or 0.0)
                nm = float(b.get("torque_nm") or 0.0)
                weight = float(b.get("curb_weight_kg") or 0.0)
                seat_mm = int(b.get("seat_height_mm") or 800)
                fuel_l = float(b.get("fuel_capacity_l") or 14.0)
                riding_pos = b.get("riding_position") or "Upright Neutral"
                
                prices = b.get("price") or {}
                p_usd = float(prices.get("usd") or 0)
                p_inr = float(prices.get("inr") or 0)
                
                desc = b.get("editorial_overview") or b.get("description") or ""
                verdict = b.get("rider_takeaway") or ""
                slug = b.get("slug") or b_id.replace("_", "-")
                
                bikes_batch.append((
                    b_id, slug, title, brand, category, cc, hp, nm, weight,
                    seat_mm, fuel_l, riding_pos, p_usd, p_inr, desc, verdict, json.dumps(b)
                ))
                
                bikes_fts_batch.append((
                    b_id, title, brand, category, riding_pos, desc
                ))
        except Exception:
            continue

    if bikes_batch:
        cursor.executemany("""
        INSERT OR REPLACE INTO motorcycles (
            id, slug, title, brand, category, displacement_cc, power_hp, torque_nm,
            curb_weight_kg, seat_height_mm, fuel_capacity_l, riding_position,
            price_usd, price_inr, description, verdict, raw_json
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        """, bikes_batch)
        
        cursor.executemany("""
        INSERT INTO motorcycles_fts (id, title, brand, category, riding_position, description)
        VALUES (?, ?, ?, ?, ?, ?)
        """, bikes_fts_batch)

    # Ingest Accessories
    acc_files = glob.glob(str(data_dir / "accessories" / "*.json"))
    print(f"🔧 Processing {len(acc_files)} accessories...")
    acc_batch = []
    acc_fts_batch = []
    for a_path in acc_files:
        try:
            with open(a_path, "r", encoding="utf-8") as f:
                a = json.load(f)
                a_id = a.get("id") or Path(a_path).stem
                title = a.get("title") or a_id.replace("-", " ").title()
                brand = a.get("brand") or "Universal"
                category = a.get("category") or a.get("type") or "Accessories"
                sub = a.get("accessory_subcategory") or a.get("subcategory") or ""
                prices = a.get("price") or {}
                p_usd = float(prices.get("usd") or 0)
                p_inr = float(prices.get("inr") or 0)
                desc = a.get("description") or ""
                verdict = (a.get("qualitative_intelligence") or {}).get("editorial_verdict") or ""

                acc_batch.append((
                    a_id, title, brand, category, sub, p_usd, p_inr, desc, verdict, json.dumps(a)
                ))
                acc_fts_batch.append((
                    a_id, title, brand, sub, desc
                ))
        except Exception:
            continue

    if acc_batch:
        cursor.executemany("""
        INSERT OR REPLACE INTO accessories (
            id, title, brand, category, subcategory, price_usd, price_inr, description, verdict, raw_json
        ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        """, acc_batch)

        cursor.executemany("""
        INSERT INTO accessories_fts (id, title, brand, subcategory, description)
        VALUES (?, ?, ?, ?, ?)
        """, acc_fts_batch)

    # Ingest Compatibility Matrix
    compat_file = data_dir / "compatibility_matrix.json"
    compat_batch = []
    if compat_file.exists():
        print("🔗 Ingesting Cross-Entity Compatibility Matrix...")
        try:
            with open(compat_file, "r", encoding="utf-8") as f:
                c_data = json.load(f)

            # Motorcycle matches
            for m_id, matches in c_data.get("motorcycle_matches", {}).items():
                for h in matches.get("recommended_helmets", []):
                    compat_batch.append((
                        m_id, h["id"], "motorcycle", "helmet", h.get("match_score", 90), h.get("match_reason", "")
                    ))
                for a in matches.get("recommended_accessories", []):
                    compat_batch.append((
                        m_id, a["id"], "motorcycle", "accessory", 90, a.get("reason", "")
                    ))

            # Helmet matches
            for h_id, matches in c_data.get("helmet_matches", {}).items():
                for b in matches.get("recommended_motorcycles", []):
                    compat_batch.append((
                        h_id, b["id"], "helmet", "motorcycle", b.get("match_score", 90), b.get("match_reason", "")
                    ))
                for a in matches.get("compatible_accessories", []):
                    compat_batch.append((
                        h_id, a["id"], "helmet", "accessory", 95, a.get("fitment", "")
                    ))
        except Exception as e:
            print(f"⚠️ Error parsing compatibility matrix: {e}")

    if compat_batch:
        cursor.executemany("""
        INSERT INTO compatibility (entity_a_id, entity_b_id, entity_a_type, entity_b_type, score, reason)
        VALUES (?, ?, ?, ?, ?, ?)
        """, compat_batch)

    conn.commit()
    
    # Verification stats
    cursor.execute("SELECT COUNT(*) FROM helmets;")
    total_helmets = cursor.fetchone()[0]
    cursor.execute("SELECT COUNT(*) FROM variants;")
    total_variants = cursor.fetchone()[0]
    cursor.execute("SELECT COUNT(*) FROM brands;")
    total_brands = cursor.fetchone()[0]
    cursor.execute("SELECT COUNT(*) FROM motorcycles;")
    total_motorcycles = cursor.fetchone()[0]
    cursor.execute("SELECT COUNT(*) FROM accessories;")
    total_accessories = cursor.fetchone()[0]
    cursor.execute("SELECT COUNT(*) FROM compatibility;")
    total_compat = cursor.fetchone()[0]
    
    conn.close()
    
    db_size_mb = os.path.getsize(str(db_path)) / (1024 * 1024)
    print("\n✅ Catalog SQLite Compilation Complete!")
    print(f"   📊 Total Helmets Indexed:      {total_helmets:,}")
    print(f"   🎨 Total Variants Indexed:     {total_variants:,}")
    print(f"   🏷️  Total Brands:              {total_brands:,}")
    print(f"   🏍️  Total Motorcycles Indexed: {total_motorcycles:,}")
    print(f"   🔧 Total Accessories Indexed:  {total_accessories:,}")
    print(f"   🔗 Total Compatibility Links:  {total_compat:,}")
    print(f"   💾 Database Size:              {db_size_mb:.2f} MB")
    print(f"   📍 Target:                     {db_path}")

if __name__ == "__main__":
    compile_catalog()
