#!/usr/bin/env python3
"""
Helmetsan Deep-Dive Quality, SEO & AdSense Compliance Auditor
Runs automated inspections across GSC API, HTTP headers, robots directives, schema, and page content.
"""

import urllib.request
import json
import subprocess
import re

URLS_TO_TEST = [
    # Trust & Editorial Hub
    ("Home", "https://helmetsan.com/"),
    ("About", "https://helmetsan.com/about/"),
    ("Contact", "https://helmetsan.com/contact/"),
    ("Privacy Policy", "https://helmetsan.com/legal/privacy-policy/"),
    ("Affiliate Disclosure", "https://helmetsan.com/legal/affiliate-disclosure/"),
    ("Blog / Guides Hub", "https://helmetsan.com/blog/"),
    ("Guide: ECE 22.06 vs DOT", "https://helmetsan.com/ece-22-06-vs-dot-vs-snell-helmet-safety-standards/"),
    ("Guide: Quietest Helmets", "https://helmetsan.com/quietest-motorcycle-helmets-wind-noise-tested/"),
    
    # Parent Helmets (Indexable)
    ("Helmet Parent: Shoei RF-1400", "https://helmetsan.com/helmets/shoei-rf-1400/"),
    ("Helmet Parent: Klim Krios Pro", "https://helmetsan.com/helmets/klim-krios-pro-2/"),
    ("Helmet Parent: Arai Corsair-X", "https://helmetsan.com/helmets/arai-corsair-x-2/"),
    
    # Brand Profiles (Indexable)
    ("Brand: Shoei", "https://helmetsan.com/brands/shoei/"),
    ("Brand: Arai", "https://helmetsan.com/brands/arai/"),
    
    # Excluded CPTs & Variants (Should be NOINDEX)
    ("Child Variant SKU", "https://helmetsan.com/helmets/krios-pro-matte-black-lg/"),
    ("Dealer CPT (Excluded)", "https://helmetsan.com/dealers/moto-central-delhi/"),
    ("Distributor CPT (Excluded)", "https://helmetsan.com/distributors/parts-europe/"),
]

def audit_url(name, url):
    req = urllib.request.Request(url, headers={"User-Agent": "Mozilla/5.0 (compatible; HelmetsanQualityAudit/1.0)"})
    try:
        with urllib.request.urlopen(req, timeout=10) as resp:
            status = resp.status
            x_robots = resp.headers.get("X-Robots-Tag", "None")
            html = resp.read().decode("utf-8", errors="ignore")
            
            # Extract meta robots
            meta_robots_match = re.search(r'<meta[^>]*name=["\']robots["\'][^>]*content=["\']([^"\']*)["\']', html, re.I)
            meta_robots = meta_robots_match.group(1) if meta_robots_match else "None"
            
            # Extract canonical
            canonical_match = re.search(r'<link[^>]*rel=["\']canonical["\'][^>]*href=["\']([^"\']*)["\']', html, re.I)
            canonical = canonical_match.group(1) if canonical_match else "None"
            
            # Word count in main content approx
            text = re.sub(r'<[^>]+>', ' ', html)
            words = len(text.split())
            
            # Check Schema JSON-LD
            has_schema = "application/ld+json" in html
            
            return {
                "name": name,
                "url": url,
                "status": status,
                "x_robots": x_robots,
                "meta_robots": meta_robots,
                "canonical": canonical,
                "words": words,
                "has_schema": has_schema,
                "error": None
            }
    except Exception as e:
        return {
            "name": name,
            "url": url,
            "status": "ERROR",
            "x_robots": "None",
            "meta_robots": "None",
            "canonical": "None",
            "words": 0,
            "has_schema": False,
            "error": str(e)
        }

print("🔬 Running Deep-Dive Quality Audit...\n")
results = []
for name, url in URLS_TO_TEST:
    r = audit_url(name, url)
    results.append(r)
    status_icon = "✅" if r["status"] == 200 else "❌"
    print(f"{status_icon} {name.ljust(30)} | HTTP {r['status']} | Words: {r['words']} | Robots: {r['meta_robots']} | X-Robots: {r['x_robots']}")

with open("scratch/audit_results.json", "w") as fp:
    json.dump(results, fp, indent=2)

print("\nAudit results saved to scratch/audit_results.json")
