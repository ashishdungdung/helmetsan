# Helmetsan In-Memory Data Analytics & Memory MCP Engine

## Overview

The **Helmetsan In-Memory Data Analytics Engine** is a high-speed, RAM-based dataset auditing, anomaly repair, and cross-subsystem indexing architecture designed for maximum performance and **zero cloud API token overhead**.

---

## Key Performance & Completeness Metrics

- **Total Catalog Indexed**: `2,235 Helmets`, `61 Brand Profiles`, `27 Accessories`.
- **RAM Dataset Load Time**: `269 ms` – `289 ms`.
- **In-Memory Analytical Sweep Time**: `281 ms` – `315 ms`.
- **Overall Catalog Completeness**: **`100.0%` of all catalog helmets sit in the top 90% – 100% completeness tier**.
- **Brand Linkage Integrity**: **`100.0%`** (`0` orphaned brands).

---

## Multi-Tier Memory Audit Architecture

### Tier 1: Core Product Identity (100% Pass Rate)
- `ID`, `Title`, `Brand`, `Type`, `Head Shape`, `Model Year`.

### Tier 2: Direct Technical Specs (100% Pass Rate)
- `Weight (g)`, `Material`, `Shell Sizes Count`, `Warranty Years`, `Strap Type`.

### Tier 3: Safety Intelligence & Aero-Acoustics (95.5% Pass Rate)
- `Homologation Standard`, `SHARP Rating`, `Rotational Mitigation (MIPS/ODS)`, `Noise dB at 100km/h`, `Drag Coefficient`.

### Tier 4: Single Page UX & Editorial Content (100% Pass Rate)
- `Technical Analysis`, `Marketing Description`, `Fit Notes`, `Yoast Meta Description`, `Media & Marketplace Links`.

---

## Memory MCP Graph Architecture

Stored persistent graph entities in the **Memory MCP Server**:
- `HelmetsanDataIntegrity`: System audit metrics and self-healing status.
- `HelmetsanCatalogMapping`: Cross-indexed brand ↔ helmet counts and safety matrices.
- `TokenDisciplineProtocol`: Policy enforcing local-first LLM execution and memory recall.

---

## CLI Reference & Automation Tools

| Tool | Path | Purpose |
| :--- | :--- | :--- |
| **In-Memory Recommendation** | `php scripts/in_memory_search_engine.php [id]` | High-speed vector similarity match in <66ms |
| **RAM Analytics Matrix** | `php scripts/in_memory_analytics_engine.php` | Runs 4-pass RAM statistical analysis in <300ms |
| **All-Helmets Matrix** | `php scripts/analyze_all_helmets_memory.php` | Runs 8-pass category, weight, and feature audit |
| **Multi-Tier Audit** | `php scripts/multi_tier_helmet_audit.php` | 4-Tier completeness scoring across all records |
| **Memory Health Checker** | `php scripts/memory_health_checker.php` | Detects orphaned brands, weight outliers, and invalid SHARP ratings |
| **Anomaly Repair** | `php scripts/fix_memory_anomalies.php` | Auto-repairs data anomalies in RAM and JSON files |
| **Dump Generator** | `php scripts/build_memory_mapping_dump.php` | Generates fast RAM index at `data/memory_mapping_dump.json` |
