import json
import os
import re

print("Generating ultra-premium high-detail helmets...")

new_helmets = [
    {
        "id": "hjc-rpha-11-carbon",
        "title": "HJC RPHA 11 Pro Carbon",
        "brand": "HJC",
        "type": "Full Face",
        "helmet_family": "RPHA",
        "head_shape": "Intermediate Oval",
        "helmet_types": ["Full Face", "Track / Race", "Carbon Fiber"],
        "features": [
            "Premium Integrated Matrix (P.I.M. Plus) Carbon Fiber shell",
            "Extremely lightweight design for track use",
            "Advanced channeling ventilation system (ACS)",
            "MultiCool antibacterial interior, moisture-wicking and quick drying",
            "Emergency cheek pad release system",
            "Includes dark smoke shield and anti-fog insert lens"
        ],
        "specs": {
            "weight_g": 1300,
            "weight_lbs": 2.87,
            "material": "Carbon Fiber",
            "shell_sizes": 3,
            "certifications": ["DOT", "ECE 22.05"]
        },
        "product_details": {
            "style": "Sport / Track",
            "mfr_product_number": "RPHA-11-CARB",
            "sizing_fit": "Snug race fit. Intermediate oval head shape.",
            "description": "The RPHA 11 Pro Carbon stands at the top of HJC's lineup, featuring a full carbon fiber shell that delivers extreme lightness and superior impact resistance. Designed for the track but street-legal, it provides massive airflow and uncompromising safety."
        },
        "price": { "current": 529.99, "currency": "USD" },
        "geo_pricing": {
            "US": { "price": 529.99, "currency": "USD", "availability": "in_stock", "updated_at": "2026-02-22" },
            "EU": { "price": 489.95, "currency": "EUR", "availability": "in_stock", "updated_at": "2026-02-22" },
            "IN": { "price": 44000, "currency": "INR", "availability": "limited", "updated_at": "2026-02-22" }
        },
        "variants": [
            {
                "id": "hjc-rpha-11-carbon-solid-md",
                "color": "Matte Carbon",
                "size": "MD",
                "sku": "HJC-R11C-MCRB-MD",
                "price": 529.99,
                "currency": "USD",
                "availability": "in_stock"
            }
        ],
        "entity": "helmet"
    },
    {
        "id": "klim-krios-pro",
        "title": "Klim Krios Pro",
        "brand": "Klim",
        "type": "Adventure / Dual Sport",
        "helmet_family": "Krios",
        "head_shape": "Intermediate Oval",
        "helmet_types": ["Adventure", "Adventure / Dual Sport", "Carbon Fiber"],
        "features": [
            "Full carbon fiber shell construction",
            "Koroyd energy-absorbing technology built into the EPS",
            "Transitions photochromic visor included",
            "FIDLOCK magnetic chin strap closure",
            "Four ride modes: Street, Adventure, Dirt, Trail",
            "Pinlock-ready polycarbonate anti-scratch visor"
        ],
        "specs": {
            "weight_g": 1350,
            "weight_lbs": 2.97,
            "material": "Carbon Fiber / Koroyd",
            "shell_sizes": 2,
            "certifications": ["DOT", "ECE 22.06"]
        },
        "product_details": {
            "style": "Adventure Touring",
            "mfr_product_number": "KLIM-KR-PRO",
            "sizing_fit": "True to size. Intermediate oval.",
            "description": "Redefining the ADV helmet segment, the Klim Krios Pro integrates Koroyd material that crushes uniformly on impact, reducing trauma. Combined with a Transitions visor and full carbon shell, it offers unbeatable versatility across all terrains."
        },
        "price": { "current": 699.99, "currency": "USD" },
        "geo_pricing": {
            "US": { "price": 699.99, "currency": "USD", "availability": "in_stock", "updated_at": "2026-02-22" },
            "EU": { "price": 649.00, "currency": "EUR", "availability": "in_stock", "updated_at": "2026-02-22" }
        },
        "variants": [
            {
                "id": "klim-krios-pro-arsenal-lg",
                "color": "Arsenal Gray",
                "size": "LG",
                "sku": "KLM-KRO-ARS-LG",
                "price": 699.99,
                "currency": "USD",
                "availability": "in_stock"
            }
        ],
        "entity": "helmet"
    },
    {
        "id": "shoei-x-15",
        "title": "Shoei X-Fifteen",
        "brand": "Shoei",
        "type": "Track / Race",
        "helmet_family": "X-Series",
        "head_shape": "Intermediate Oval",
        "helmet_types": ["Full Face", "Track / Race"],
        "features": [
            "Advanced Integrated Matrix Plus (AIM+) shell",
            "MotoGP developed aerodynamic design reduces drag by 6.1%",
            "CWR-F2R racing shield with tear-off posts",
            "Cheek pad cooling system routes air through EPS",
            "Fully adjustable 3D max-dry custom interior",
            "E.Q.R.S (Emergency Quick Release System)"
        ],
        "specs": {
            "weight_g": 1540,
            "weight_lbs": 3.39,
            "material": "AIM+ Fiberglass",
            "shell_sizes": 4,
            "certifications": ["DOT", "Snell M2020", "ECE 22.06", "FIM"]
        },
        "product_details": {
            "style": "Professional Race",
            "mfr_product_number": "SHO-X15-01",
            "sizing_fit": "Fitted race cut. Perfect intermediate oval.",
            "description": "The apex of Shoei's racing development, the X-Fifteen is born straight from MotoGP. Relentlessly honed in their wind tunnel to minimize lift and drag at 200mph, it offers a wider field of vision and unparalleled high-speed stability."
        },
        "price": { "current": 899.99, "currency": "USD" },
        "geo_pricing": {
            "US": { "price": 899.99, "currency": "USD", "availability": "in_stock", "updated_at": "2026-02-22" }
        },
        "variants": [
            {
                "id": "shoei-x-15-marquez-7-md",
                "color": "Marquez 7 TC-1",
                "size": "MD",
                "sku": "SHO-X15-MQ7-MD",
                "price": 1049.99,
                "currency": "USD",
                "availability": "in_stock"
            }
        ],
        "entity": "helmet"
    },
    {
        "id": "scorpion-exo-r1-air",
        "title": "Scorpion EXO-R1 Air Carbon",
        "brand": "Scorpion EXO",
        "type": "Track / Race",
        "helmet_family": "EXO-R1",
        "head_shape": "Intermediate Oval",
        "helmet_types": ["Full Face", "Track / Race", "Carbon Fiber"],
        "features": [
            "Resin-infused TCT-U 3K carbon fiber shell",
            "AirFit cheek pad inflation system for custom fit",
            "Elip-Tec II ratchet system for secure shield seal",
            "Includes clear and dark smoke Pinlock-ready shields",
            "KwikWick III antimicrobial washable liner",
            "Aero-tuned shell geometry"
        ],
        "specs": {
            "weight_g": 1380,
            "weight_lbs": 3.04,
            "material": "TCT-U Carbon Fiber",
            "shell_sizes": 3,
            "certifications": ["DOT", "ECE 22.05", "FIM"]
        },
        "product_details": {
            "style": "Track / Race",
            "mfr_product_number": "EXO-R1-AIR",
            "sizing_fit": "Intermediate oval. Runs slightly snug due to AirFit system.",
            "description": "Worn by Fabio Quartararo, the EXO-R1 Air Carbon delivers championship-level performance without the championship-level price tag. The AirFit pump system ensures the cheek pads hug your face perfectly at 180mph."
        },
        "price": { "current": 549.95, "currency": "USD" },
        "geo_pricing": {
            "US": { "price": 549.95, "currency": "USD", "availability": "in_stock", "updated_at": "2026-02-22" },
            "EU": { "price": 469.00, "currency": "EUR", "availability": "in_stock", "updated_at": "2026-02-22" }
        },
        "variants": [
            {
                "id": "scorpion-exo-r1-air-solid-carbon",
                "color": "Solid Carbon",
                "size": "MD",
                "sku": "SCO-R1C-SOL-MD",
                "price": 549.95,
                "currency": "USD",
                "availability": "in_stock"
            }
        ],
        "entity": "helmet"
    },
    {
        "id": "schuberth-c5",
        "title": "Schuberth C5",
        "brand": "Schuberth",
        "type": "Modular",
        "helmet_family": "C-Series",
        "head_shape": "Intermediate Oval",
        "helmet_types": ["Modular", "Touring"],
        "features": [
            "DFP (Direct Fiber Processing) fiberglass shell reinforced with carbon fiber",
            "Extreme acoustic isolation (85 dB(A) at 100 km/h on a naked bike)",
            "Pre-installed HD speakers, antennas, and mic for Sena SC2 system",
            "Anti-Roll-Off-System (A.R.O.S.)",
            "Memory function visor mechanism",
            "Customizable interior padding (Schuberth Individual Concept)"
        ],
        "specs": {
            "weight_g": 1640,
            "weight_lbs": 3.61,
            "material": "DFP Fiberglass / Carbon",
            "shell_sizes": 2,
            "certifications": ["DOT", "ECE 22.06"]
        },
        "product_details": {
            "style": "Premium Touring",
            "mfr_product_number": "SCH-C5-01",
            "sizing_fit": "True intermediate oval. Highly customizable.",
            "description": "The C5 is the state-of-the-art modular from Schuberth. Blending ultimate safety with aerodynamic brilliance, it sets a new standard for noise reduction in flip-up helmets while meeting strict ECE 22.06 standards."
        },
        "price": { "current": 749.00, "currency": "USD" },
        "geo_pricing": {
            "US": { "price": 749.00, "currency": "USD", "availability": "in_stock", "updated_at": "2026-02-22" },
            "EU": { "price": 629.00, "currency": "EUR", "availability": "in_stock", "updated_at": "2026-02-22" }
        },
        "variants": [
            {
                "id": "schuberth-c5-matte-black-lg",
                "color": "Matte Black",
                "size": "LG",
                "sku": "SCH-C5-MBK-LG",
                "price": 749.00,
                "currency": "USD",
                "availability": "in_stock"
            }
        ],
        "entity": "helmet"
    },
    {
        "id": "bell-moto-10-spherical",
        "title": "Bell Moto-10 Spherical",
        "brand": "Bell",
        "type": "Dirt / MX",
        "helmet_family": "Moto",
        "head_shape": "Intermediate Oval",
        "helmet_types": ["Dirt / MX", "Off-Road", "Carbon Fiber"],
        "features": [
            "Spherical Technology powered by MIPS",
            "Segmented 3K Carbon Fiber shell",
            "Thermal Exchange Airflow System (T.E.A.S.)",
            "NMR (No Missed Races) collarbone bumpers",
            "Panoramic goggle port",
            "Magnefusion emergency removal cheek pads"
        ],
        "specs": {
            "weight_g": 1390,
            "weight_lbs": 3.06,
            "material": "3K Carbon Fiber",
            "shell_sizes": 3,
            "certifications": ["DOT", "Snell M2020", "ECE 22.05"]
        },
        "product_details": {
            "style": "Pro Motocross",
            "mfr_product_number": "BEL-M10-SPH",
            "sizing_fit": "Sport-focused true fit.",
            "description": "The first off-road helmet to feature Spherical Technology backed by MIPS. The Moto-10 represents the peak of Bell's MX evolution, featuring segmented carbon construction and advanced rotational impact management."
        },
        "price": { "current": 899.95, "currency": "USD" },
        "geo_pricing": {
            "US": { "price": 899.95, "currency": "USD", "availability": "in_stock", "updated_at": "2026-02-22" }
        },
        "variants": [
            {
                "id": "bell-moto-10-fasthouse-lg",
                "color": "Fasthouse DITD",
                "size": "LG",
                "sku": "BEL-M10-FH-LG",
                "price": 919.95,
                "currency": "USD",
                "availability": "in_stock"
            }
        ],
        "entity": "helmet"
    },
    {
        "id": "ls2-adv-mx701",
        "title": "LS2 Explorer Carbon MX701",
        "brand": "LS2",
        "type": "Adventure / Dual Sport",
        "helmet_family": "Explorer",
        "head_shape": "Intermediate Oval",
        "helmet_types": ["Adventure", "Adventure / Dual Sport", "Carbon Fiber"],
        "features": [
            "Wide-weave pure carbon fiber shell",
            "Drop-down internal sun visor",
            "Dynamic flow-through ventilation",
            "Optically correct Class A polycarbonate scratch-resistant visor",
            "Emergency release cheek pads",
            "Hypoallergenic, breathable, laser-cut interior"
        ],
        "specs": {
            "weight_g": 1380,
            "weight_lbs": 3.04,
            "material": "Carbon Fiber",
            "shell_sizes": 3,
            "certifications": ["DOT", "ECE 22.06"]
        },
        "product_details": {
            "style": "Adventure Touring",
            "mfr_product_number": "LS2-MX701-C",
            "sizing_fit": "Intermediate oval.",
            "description": "An incredibly light adventure helmet featuring a pure carbon fiber shell and integrated sun visor. Combines road touring comfort with off-road capability."
        },
        "price": { "current": 429.98, "currency": "USD" },
        "geo_pricing": {
            "US": { "price": 429.98, "currency": "USD", "availability": "in_stock", "updated_at": "2026-02-22" },
            "EU": { "price": 399.00, "currency": "EUR", "availability": "in_stock", "updated_at": "2026-02-22" }
        },
        "variants": [
            {
                "id": "ls2-explorer-carbon-solid-lg",
                "color": "Carbon Solid",
                "size": "LG",
                "sku": "LS2-MX7C-SOL-LG",
                "price": 429.98,
                "currency": "USD",
                "availability": "in_stock"
            }
        ],
        "entity": "helmet"
    }
]

out_dir = "/Users/anumac/Documents/Helmetsan/data/helmets"
if not os.path.exists(out_dir):
    os.makedirs(out_dir)

count = 0
for helmet in new_helmets:
    filepath = os.path.join(out_dir, f"{helmet['id']}.json")
    with open(filepath, 'w') as f:
        json.dump(helmet, f, indent=2)
    count += 1

print(f"✅ Generated {count} new ultra-premium helmets efficiently!")
