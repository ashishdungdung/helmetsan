#!/usr/bin/env python3
"""
Deep Technical Strategy & Audit on Yoast SEO Coexistence & Synergy via gpt-5.6-luna.
"""

import os
import sys
import json
import urllib.request
import urllib.error

API_KEY = os.environ.get("EXPLABS_API_KEY", "xpl_347a690bbbf034a8340fcd0cd3ee91b5ec0147e1")
ENDPOINT = "https://api.experientiallabs.ai/v1/chat/completions"
MODEL = "gpt-5.6-luna"

def read_file(rel_path):
    full_path = os.path.join("/Users/anumac/Documents/Projects/Helmetsan/HelmetsanWeb", rel_path)
    if os.path.exists(full_path):
        with open(full_path, "r", encoding="utf-8") as f:
            return f.read()
    return ""

def main():
    print(f"📡 Gathering Yoast integration context for {MODEL}...")
    
    auto_seo = read_file("helmetsan-core/includes/Seo/AutoSeoObserver.php")
    schema_service = read_file("helmetsan-core/includes/Seo/SchemaService.php")
    sitemap_enhancer = read_file("helmetsan-core/includes/Seo/SitemapEnhancer.php")
    yoast_seeder = read_file("helmetsan-core/includes/Seo/YoastSeoSeeder.php")

    system_prompt = (
        "You are an elite principal WordPress architect and technical SEO engineer specializing in "
        "enterprise Yoast SEO integration, schema graph unification, indexables lifecycle, and zero-conflict multi-system SEO architecture. "
        "Analyze the provided Helmetsan SEO code and formulate a comprehensive, non-conflicting, high-leverage strategic blueprint. "
        "Provide an in-depth technical report, an phased architectural roadmap, and a precise implementation plan."
    )

    user_prompt = f"""
We want to achieve 100% harmonious, zero-conflict, best-practice coexistence and strategic enhancement with Yoast SEO on Helmetsan (an enterprise motorcycle helmet and gear platform).
We do NOT want to fight, duplicate, or conflict with Yoast; rather, we want to make the BEST possible strategic use of Yoast's hooks, APIs, Schema Graph, Indexables, OpenGraph, breadcrumbs, and sitemaps.

### CURRENT IMPLEMENTATION CONTEXT

1. AutoSeoObserver.php:
```php
{auto_seo[:3000]}
... (truncated)
```

2. SchemaService.php (first 3000 chars):
```php
{schema_service[:3000]}
... (truncated)
```

3. SitemapEnhancer.php:
```php
{sitemap_enhancer[:3000]}
... (truncated)
```

4. YoastSeoSeeder.php:
```php
{yoast_seeder[:2500]}
... (truncated)
```

### STRATEGIC QUESTIONS & REQUIREMENTS
Please evaluate deeply and deliver:
1. **Comprehensive Conflict & Duplication Audit**:
   - Where are we currently duplicating or conflicting with Yoast? (e.g. standalone JSON-LD Schema tags for WebSite/Organization/Breadcrumbs vs Yoast's unified @graph; canonical filters; sitemap indexes; robots tags).
   - What happens when Yoast and Helmetsan both output schema, robots, canonicals, or meta descriptions?

2. **Yoast Schema Graph Unification Blueprint**:
   - How should Helmetsan inject its rich Product (helmet, motorcycle, accessory), Offer, AggregateRating, Brand, Review, and FAQPage nodes directly into Yoast's interconnected `@graph` (`wpseo_schema_graph` or `wpseo_schema_graph_pieces`) so Google parses one unified entity graph rooted in `#website` and `#webpage`?
   - How to gracefully suppress Yoast's duplicate/placeholder pieces or suppress Helmetsan's standalone tags when Yoast is present, while maintaining fallback if Yoast is deactivated?

3. **Indexables & Meta Synchronization**:
   - How to ensure `_yoast_wpseo_*` meta changes sync seamlessly with Yoast's Indexables table (`wp_yoast_indexable`) without causing stale caches or requiring manual reindexing?
   - How to leverage Yoast's OpenGraph (`wpseo_opengraph_*`) and Twitter card pipelines for helmet gallery images, spec cards, and price badges?

4. **Sitemap & Permalinks Harmony**:
   - How to ensure our custom sitemaps (/sitemap-brands.xml, /sitemap-comparisons.xml, /sitemap-motorcycles.xml, /sitemap-helmets-images.xml) integrate cleanly into Yoast's `/sitemap_index.xml` without duplicate pinging, infinite redirects, or crawler confusion?

5. **Phased Strategic Roadmap & Architectural Specification**:
   - Phase 1: Conflict Elimination & Fallback Detection.
   - Phase 2: Schema Graph Integration (Single-Graph Doctrine).
   - Phase 3: Metadata & OpenGraph Synergy.
   - Phase 4: Sitemaps, Canonicalization & Crawl Governance.

6. **Actionable Implementation Plan**:
   - File-by-file changes, specific hooks, priority levels, and unit test verification strategies.
"""

    payload = {
        "model": MODEL,
        "messages": [
            {"role": "system", "content": system_prompt},
            {"role": "user", "content": user_prompt}
        ],
        "max_tokens": 5500
    }

    print(f"🚀 Dispatching Yoast strategy prompt to {ENDPOINT}...")
    req = urllib.request.Request(
        ENDPOINT,
        data=json.dumps(payload).encode("utf-8"),
        headers={
            "Authorization": f"Bearer {API_KEY}",
            "Content-Type": "application/json"
        },
        method="POST"
    )

    try:
        with urllib.request.urlopen(req, timeout=240) as resp:
            data = json.loads(resp.read().decode("utf-8"))
            content = data["choices"][0]["message"]["content"]
            usage = data.get("usage", {})

            output_file = "/Users/anumac/Documents/Projects/Helmetsan/HelmetsanWeb/docs/YOAST_COEXISTENCE_AND_ENHANCEMENT_STRATEGY.md"
            with open(output_file, "w", encoding="utf-8") as f:
                f.write("# Yoast SEO Coexistence & Strategic Enhancement Blueprint\n")
                f.write(f"**Generated by Model:** `{MODEL}` via Experiential Labs Gateway\n")
                f.write(f"**Token Usage:** {json.dumps(usage, indent=2)}\n\n")
                f.write(content)

            print(f"✅ Strategy saved to {output_file}")
            print(f"📊 Token usage: {usage}")
    except Exception as e:
        print(f"❌ Error: {e}")

if __name__ == "__main__":
    main()
