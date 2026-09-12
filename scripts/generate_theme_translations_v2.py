#!/usr/bin/env python3
"""
Enhanced Theme Translation Generator V2
Supports 9 foreign languages:
- de (German)
- zh (Simplified Chinese)
- fr (French)
- es (Spanish)
- it (Italian)
- pl (Polish)
- pt (Portuguese)
- nl (Dutch)
- ja (Japanese)

Includes all 260+ theme keys, navigation strings, and mega-menu items.
Compiles standard WordPress .mo and .po catalogs.
"""

import os
import json
import subprocess

THEME_DIR = "/Users/anumac/Documents/Projects/Helmetsan/HelmetsanWeb/helmetsan-theme"
LANG_DIR = os.path.join(THEME_DIR, "languages")

# Base English / Common Keys
with open("/tmp/de_keys.json", "r", encoding="utf-8") as f:
    base_keys = json.load(f)

# Extra navigation & mega menu keys
extra_keys = [
    "Shop by Type", "Shop by Brand", "Riding Style & Safety", "Features & Style",
    "Helmet Accessories", "Sale & Closeouts", "View All Helmets", "View All Brands",
    "View All Accessories", "View All Motorcycles", "Full Face Helmets", "Modular Helmets",
    "Open Face Helmets", "Half Helmets", "Dirt / Motocross Helmets", "Adventure / Dual Sport Helmets",
    "Touring Helmets", "Track / Race Helmets", "Youth Helmets", "Snowmobile Helmets",
    "Street", "Cruiser", "Sportbike", "Touring", "Adventure", "Dirt / MX", "Track",
    "Urban / Commuter", "Snow", "ECE Certified", "ECE 22.06", "Snell Certified",
    "FIM Certified", "DOT Approved", "MIPS Technology", "Rotational Impact Protection",
    "High Visibility Helmets", "Internal Sun Visor", "Photochromic", "Electric Shield",
    "Dual Lens Shield", "1 Shell", "2 Shells", "3 Shells", "4+ Shell Sizes",
    "Solid Color Helmets", "Graphic Helmets", "Race Replica", "Face Shields",
    "Pinlock Inserts", "Replacement Liners", "Cheek Pads", "Bluetooth Systems",
    "Action Camera Mounts", "Cleaning Kits", "Helmet Bags", "Helmets on Sale",
    "Closeout Helmets", "Last Chance", "Open Box", "Helmets Mega Menu"
]

all_canonical_keys = list(dict.fromkeys(list(base_keys.keys()) + extra_keys))
print(f"Total Canonical Keys: {len(all_canonical_keys)}")

# Write updated script
