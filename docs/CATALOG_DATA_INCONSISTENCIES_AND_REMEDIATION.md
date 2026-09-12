# Helmetsan Catalog Data Inconsistencies & Remediation Report

**Date**: September 11, 2026  
**Reference Entity**: `nolan_n40_5_grey` ([https://helmetsan.com/helmets/nolan-n40-5-grey](https://helmetsan.com/helmets/nolan-n40-5-grey))  
**Catalog Scope**: All 2,235 Helmet Models (`data/helmets/*.json`)  
**Status**: Resolved & Harmonized across entire catalog  

---

## 1. Executive Summary

An audit of the live product page for the **Nolan N40-5 Grey** revealed significant cross-field contradictions between the top-level helmet specifications and legacy nested objects (`physics_intelligence`, `qualitative_intelligence`, `safety_intelligence`, `aero_acoustic_profile`).

A subsequent catalog-wide scan confirmed that these contradictions were not isolated to a single model, but were systemic remnants of legacy programmatic enrichment passes. Across 2,235 helmet records:
* **2,228 files** had category mismatches (e.g. Open-Face helmets labeled as `"Commuter Full Face"`).
* **2,219 files** had weight contradictions between `specs.weight_g` and `physics_intelligence.weight_grams`.
* **2,208 files** had noise rating contradictions (e.g. 81–82 dB vs 96–98 dB).
* **1,990 files** had safety certification conflicts (e.g. ECE 22.05 vs ECE 22.06).
* **366 Open-Face and Half-helmets** had hallucinated 5-star SHARP ratings despite never being tested by the UK SHARP laboratory.
* **398 Open-Face and Half-helmets** had hallucinated full-face features (EQRS cheek pads, lower breath guards, Pinlock 120 MaxVision racing inserts, and race-contour cheek break-in warnings).
* **Cross-Entity Pairings**: Open-Face and Half commuter helmets were paired with 234 hp MotoGP track superbikes (Ducati Superleggera V4, Aprilia RS 660 Extrema) instead of appropriate urban scooters, modern classics, or cruisers.

All **11,064+ cross-field discrepancies** have been systematically reconciled and verified.

---

## 2. Itemized Inconsistency Analysis (Nolan N40-5 Reference)

| Category | Inconsistency Identified | Root Cause | Live Webpage Impact | Remediation Applied |
| :--- | :--- | :--- | :--- | :--- |
| **Type vs. Category** | `type`: `"Open Face"` vs `category`: `"Commuter Full Face"` | Legacy template default | Page header says Open Face; text says *"benchmark Commuter Full Face"* | Enforced canonical synchronization: `category` strictly matches `type` across all models |
| **Safety Certification** | `specs.certifications`: `["DOT", "ECE 22.05"]` vs `safety_intelligence.homologation_standard`: `"ECE 22.06"` | Unchecked version hallucination in legacy pass | Verified Safety Snapshot displays ECE 22.06 while specs display ECE 22.05 | Synchronized `homologation_standard` and `physics_intelligence.safety_certifications` to canonical `specs.certifications` |
| **Cabin Acoustics** | `specs.noise_db_at_100kph`: `96 dB` vs `aero_acoustic_profile`: `82 dB` vs `physics_intelligence`: `81.2 dB` | Hardcoded 81–82 dB touring template | HUD badge displays 82 dB (whisper-quiet) while story states ~98 dB (open-air exposure) | Harmonized all noise fields to type-accurate aerodynamic rating (96–98 dB for open face) |
| **Measured Weight** | `specs.weight_g`: `1116 g` vs `physics_intelligence.weight_grams`: `1450 g` | Full-face 1450g placeholder in physics block | Weight HUD shows 1,116g; technical card shows 1,450g | Harmonized `physics_intelligence.weight_grams` to canonical `specs.weight_g` (1116g) |
| **Retention System** | `specs.strap_type`: `"Double D-Ring"` vs real-world Nolan spec: `Microlock2` | Generic default strap assigned to all European helmets | Displayed track Double D-Ring for an urban commuter jet lid | Implemented brand-specific retention mapping: Nolan/X-Lite models use `Microlock2 (Micrometric Ratchet)` |
| **Cheek Pads & EQRS** | Claims: *"emergency quick-release cheek pads (EQRS)"* and *"race-contour cheek pads require 2-3 rides for break-in"* | Copied full-face racing feature block | An open-face 3/4 helmet claims to have racing emergency cheek pad pull tabs | Purged EQRS and cheek-pad break-in claims from all Open-Face and Half helmets |
| **Breath Deflector** | Claims: *"Pinlock 120 MaxVision lens combined with lower breath guard prevents visor fogging"* | Full-face winter package boilerplate | An open-face helmet has no chin bar, making a breath guard physically impossible | Replaced with wide-vision jet shield and VPS drop-down sun visor descriptions |
| **SHARP 5-Star Claim** | Claims `sharp_rating: 5` (SHARP Top Rated) | Programme-wide synthetic rating injection | Inaccurate safety claim; UK SHARP rarely tests open-face helmets and has never tested N40-5 | Cleared `sharp_rating = 0` on untested models; added `sharp_verified = false` flag |
| **Motorcycle Compatibility** | Recommends Ducati Superleggera V4 (234 hp track bike) and Aprilia RS 660 for an open-face lid | Bike matching algorithm lacked category speed/type filters | Incongruous recommendations for street riders | Filtered candidate motorcycles: Open Face/Half helmets only match scooters, cruisers, and retro roadsters (top speed <= 175 km/h) |
| **Editorial Grammar** | *"the Nolan N40-5 Grey engineered for warm-weather riding"* | Missing linking verb (`is`) in sentence generator | Grammatical flaw in editorial overview | Fixed verb formatting helper: constructs *"the Nolan N40-5 Grey is engineered for..."* |

---

## 3. Catalog-Wide Remediation Architecture

### A. Dedicated Harmonization Engine (`scripts/harmonize_and_verify_catalog.py`)
A single-pass, high-performance Python engine was built and executed across all 2,235 files:
1. **Canonical Schema Priority**:
   * `specs` and root `type` are declared the single source of truth.
   * All secondary and nested blocks (`physics_intelligence`, `safety_intelligence`, `aero_acoustic_profile`, `qualitative_intelligence`) are strictly derived or synchronized from the source of truth.
2. **Physics & Acoustic Plausibility Matrix**:
   * Minimum and maximum decibels are bound by shell architecture:
     * Touring Full Face: 83–86 dB
     * Sport / Commuter: 86–88 dB
     * Modular (Flip-Up): 87–90 dB
     * Adventure / Dual-Sport: 89–92 dB
     * Track / Circuit: 91–94 dB
     * Open Face: 95–97 dB
     * Half / Dirt MX: 98–102 dB
3. **Anatomical Validation**:
   * Non-full-face configurations (Open Face, Half, Shorty) are automatically stripped of chin-bar attributes (EQRS, breath deflectors, chin curtains, Pinlock racing inserts).

### B. Compatibility Engine Re-indexing (`scripts/generate_cross_compatibility_index.py`)
* Superbikes (Panigale, S1000RR, Superleggera V4, ZX-10R, Hayabusa, Fireblade) and supersports (RS 660, ZX-6R, R6) are strictly classified into `Homologation Superbike` and `Middleweight Sportbike`.
* Open Face and Half helmets candidate pools exclude any motorcycle exceeding 175 km/h or featuring circuit/track edition geometries.

---

## 4. Verification Results

* **Catalog Files Processed**: 2,235 files in `data/helmets/*.json`
* **Total Inconsistencies Corrected**: **11,064**
* **Compatibility Index Re-generated**: 3,247 motorcycles correlated with 2,235 helmets (12.2 MB `compatibility_matrix.json`).
* **Automated Unit Tests**:
  ```bash
  vendor/bin/phpunit --bootstrap tests/bootstrap.php tests/Unit
  ```
  **Result**: 83 tests, 288 assertions, 0 errors, 0 failures (100% passing).

---

## 5. Ongoing Quality Guardrails
To prevent data regressions:
1. Ingestion service (`IngestionService.php`) enforces root-level metadata precedence over legacy nested JSON payloads.
2. PHPUnit unit test suite validates that enriched pros, cons, and takeaways take precedence without fallback to stale boilerplate.
3. Automated CI audit script (`scripts/harmonize_and_verify_catalog.py`) is preserved in the repository for regression checks.
