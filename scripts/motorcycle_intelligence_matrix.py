#!/usr/bin/env python3
"""
Motorcycle Editorial Intelligence Matrix & Smart Logic Engine
Generates authentic, magazine-grade editorial overviews and structured intelligence matrices
blending powertrain kinetics with accessible, real-world rider takeaways (Tone 3).
"""

import re
import hashlib

class MotorcycleIntelligenceMatrix:
    """
    Multi-dimensional intelligence matrix mapping motorcycle powertrain kinetics,
    chassis dynamics, seat height ergonomics, and rider mission profiles.
    """

    CATEGORY_PROFILES = {
        "superbike": {
            "name": "Homologation Superbike & Closed-Circuit Track",
            "stance": "Aggressive Full Forward Tuck",
            "terrain": "Track-day circuits, sweeping canyons, and closed-course apexes",
            "verbs": ["engineered for blistering apex speeds and track dominance", "born on the race grid to deliver uncompromised high-speed aerodynamic downforce", "tuned for uncompromising circuit performance with laser-guided cornering stability"]
        },
        "sport": {
            "name": "Middleweight Sport & Canyon Carving",
            "stance": "Dedicated Forward Lean",
            "terrain": "Spirited twisties, technical canyon roads, and weekend track sessions",
            "verbs": ["tailored for aggressive twisties and sharp canyon transitions", "engineered for responsive agility and high-rpm engine exhilaration", "delivers razor-sharp chassis feedback for riders hunting apexes"]
        },
        "adv": {
            "name": "Adventure Touring & All-Terrain Exploration",
            "stance": "Upright Commanding Stance",
            "terrain": "Highway asphalt, mountain switchbacks, gravel washboards, and rugged trails",
            "verbs": ["engineered for uncompromised trans-continental exploration", "purpose-built to crush coast-to-coast highway miles and conquer rugged backcountry tracks", "delivers all-weather long-haul versatility with long-travel suspension compliance"]
        },
        "roadster": {
            "name": "Urban Roadster & Naked Streetfighter",
            "stance": "Upright Neutral Street Stance",
            "terrain": "Urban lane-splitting, daily city commuting, and weekend backroad blasts",
            "verbs": ["crafted for flickable urban agility and stoplight-to-stoplight punch", "tuned for spirited street performance with an intuitive wide-handlebar command", "delivers muscular mid-range acceleration paired with effortless city maneuvering"]
        },
        "cruiser": {
            "name": "Performance Cruiser & Heritage Custom",
            "stance": "Relaxed Feet-Forward Stance",
            "terrain": "Open highways, scenic coastal byways, and relaxed urban cruising",
            "verbs": ["combines low-slung road presence with generous low-end torque rumble", "engineered for relaxed boulevard cruising backed by muscular roll-on passing power", "delivers authentic heritage styling paired with modern highway mile-munching comfort"]
        },
        "tourer": {
            "name": "Luxury Grand Tourer & Cross-Country",
            "stance": "All-Day Ergonomic Touring Stance",
            "terrain": "Interstate highways, cross-country transits, and two-up touring",
            "verbs": ["engineered to turn 1,000km days into effortless armchair transits", "built for luxurious two-up cross-country expeditions with supreme wind protection", "delivers unwavering high-speed stability and premier mile-eating comfort"]
        },
        "scooter": {
            "name": "Urban Commuter & High-Efficiency Mobility",
            "stance": "Upright Step-Through Stance",
            "terrain": "Dense city traffic, urban errands, and daily stop-and-go commuting",
            "verbs": ["crafted for effortless city navigation and exceptional fuel economy", "engineered for instant twist-and-go urban mobility with generous under-seat utility", "delivers ultra-nimble urban agility for riders navigating congested city centers"]
        }
    }

    @classmethod
    def resolve_category_key(cls, category_str, title_str):
        c = (category_str or "").lower()
        t = (title_str or "").lower()
        comb = f"{c} {t}"

        if any(w in comb for w in ["homologation", "superbike", "rr 310", "track", "supersport", "r1", "s1000rr", "fireblade", "panigale"]):
            return "superbike"
        elif any(w in comb for w in ["adventure", "adv", "tourer", "scrambler", "himalayan", "tiger", "gs", "africa twin", "transalp"]):
            if "luxury" in comb or "grand tourer" in comb or "supercharged" in comb:
                return "tourer"
            return "adv"
        elif any(w in comb for w in ["cruiser", "bobber", "classic", "bullet", "meteor", "super meteor", "rebel", "vulcan", "speedmaster"]):
            return "cruiser"
        elif any(w in comb for w in ["sport tourer", "grand tourer"]):
            return "tourer"
        elif any(w in comb for w in ["scooter", "commuter scooter", "electric scooter"]):
            return "scooter"
        elif any(w in comb for w in ["middleweight sportbike", "sportbike"]):
            return "sport"
        else:
            return "roadster"

    @classmethod
    def generate_intelligence(cls, item):
        title = str(item.get("title") or "Motorcycle")
        brand = str(item.get("brand") or "Motorcycle")
        category = str(item.get("category") or "Urban Roadster")
        country = str(item.get("country_origin") or "")
        
        # Specs
        cc = item.get("displacement_cc") or 0
        hp = item.get("power_hp") or 0
        nm = item.get("torque_nm") or 0
        weight_kg = item.get("curb_weight_kg") or 0
        seat_mm = item.get("seat_height_mm") or 800
        fuel_l = item.get("fuel_capacity_l") or 14
        riding_pos = item.get("riding_position") or "Upright Neutral"
        rec_helmets = item.get("recommended_helmet_types") or ["Full Face"]

        cat_key = cls.resolve_category_key(category, title)
        cat_profile = cls.CATEGORY_PROFILES[cat_key]

        # Clean title
        t_clean = title.replace("_", " ").strip()
        if t_clean.lower().startswith(brand.lower()):
            t_clean = t_clean[len(brand):].strip()
        t_clean = re.sub(r'^[_\-\s]+', '', t_clean).strip()
        full_name = f"{brand} {t_clean}"

        # Dynamic hash
        h_seed = int(hashlib.md5(f"{brand}_{title}_{item.get('id')}".encode()).hexdigest(), 16)
        verb = cat_profile["verbs"][h_seed % len(cat_profile["verbs"])]
        style_idx = h_seed % 3

        # Inseam confidence mapping
        if seat_mm < 790:
            seat_desc = f"an accessible {seat_mm}mm seat height that allows easy flat-footing for virtually all rider heights"
            confidence_tag = "Low-Slung Flat-Foot Friendly (<790mm)"
        elif seat_mm <= 825:
            seat_desc = f"a balanced {seat_mm}mm seat height providing confident footing and natural knee comfort"
            confidence_tag = "Universal Inseam Ergonomics (790-825mm)"
        else:
            seat_desc = f"a commanding {seat_mm}mm perch delivering excellent ground clearance and expansive sightlines"
            confidence_tag = "Commanding Tall Perch (>825mm)"

        # Powertrain phrasing
        cc_str = f"{cc}cc" if cc > 0 else "high-efficiency electric"
        hp_str = f"{hp} HP" if hp > 0 else "responsive electric output"
        nm_str = f"{nm} Nm of torque" if nm > 0 else "instant linear torque"
        weight_str = f"a curb weight of {weight_kg} kg" if weight_kg > 0 else "a balanced lightweight chassis"

        # --- SENTENCE 1: Mission & Category Hook ---
        if style_idx == 0:
            s1 = f"The {full_name} is {verb}, positioned in the {category} segment for riders demanding engaging everyday dynamics."
        elif style_idx == 1:
            s1 = f"Engineered for riders seeking confident performance and refined dynamics, the {full_name} is {verb}."
        else:
            s1 = f"Representing {brand}'s prowess in the {category} class, the {full_name} is {verb}."

        # --- SENTENCE 2: Powertrain & Kinetic Dynamics ---
        if cc > 0 and hp > 0:
            if style_idx == 0:
                s2 = f"Powered by a {cc_str} engine generating {hp_str} and {nm_str} against {weight_str}, it delivers predictable, roll-on throttle response across its usable powerband."
            elif style_idx == 1:
                s2 = f"At its heart lies a {cc_str} powertrain delivering {hp_str} and {nm_str}, tuned to translate its {weight_kg} kg mass into punchy mid-range acceleration."
            else:
                s2 = f"A {cc_str} powerplant pumps out {hp_str} and {nm_str}, striking an optimal balance between fuel economy and spirited passing power."
        else:
            s2 = f"Its tuned powertrain delivers immediate, tractable torque against {weight_str}, ensuring responsive roll-on acceleration in demanding road conditions."

        # --- SENTENCE 3: Ergonomics & Living-With-It ---
        if style_idx == 0:
            s3 = f"The cockpit is configured with an {riding_pos} riding stance and {seat_desc}, making daily city navigation as intuitive as weekend backroad escapes."
        elif style_idx == 1:
            s3 = f"Riders are greeted by an ergonomic {riding_pos} posture paired with {seat_desc}, backed by a {fuel_l}L fuel tank for extended saddle stints."
        else:
            s3 = f"With {seat_desc} and a comfortable {riding_pos} layout, it offers fatigue-free ergonomics whether carving traffic or cruising the open highway."

        editorial_overview = f"{s1} {s2} {s3}"

        # Rider verdict
        if cat_key == "superbike":
            verdict = "A razor-sharp circuit weapon that demands respect and rewards precise, aggressive riding."
        elif cat_key == "sport":
            verdict = "The sweet spot for apex hunters who want track-level thrills without sacrificing real-world street manners."
        elif cat_key == "adv":
            verdict = "An all-conquering globetrotter ready for continuous highway transits and rugged off-the-beaten-path detours."
        elif cat_key == "cruiser":
            verdict = "A torque-rich boulevard bruiser that turns every highway mile into a relaxed, stylish journey."
        elif cat_key == "tourer":
            verdict = "The pinnacle of cross-country touring comfort, isolating riders from wind buffeting over endless tarmac."
        elif cat_key == "scooter":
            verdict = "An ultra-practical urban runabout that slices through gridlock with zero fuss and maximum economy."
        else:
            verdict = "An engaging, versatile roadster that balances streetfighter attitude with effortless everyday usability."

        intelligence_matrix = {
            "mission_and_persona": {
                "category": category,
                "riding_stance": cat_profile["stance"],
                "target_terrain": cat_profile["terrain"]
            },
            "powertrain_and_chassis": {
                "displacement": cc_str,
                "power_hp": hp,
                "torque_nm": nm,
                "curb_weight_kg": weight_kg,
                "power_to_weight": f"{round((hp / weight_kg) * 100, 1)} HP/100kg" if (hp > 0 and weight_kg > 0) else "Optimal street balance"
            },
            "ergonomics_and_cockpit": {
                "seat_height_mm": seat_mm,
                "inseam_confidence": confidence_tag,
                "riding_position": riding_pos,
                "fuel_capacity_l": fuel_l,
                "estimated_range_km": f"~{int(fuel_l * 28)} km" if fuel_l > 0 else "N/A"
            },
            "recommended_gear": {
                "recommended_helmets": rec_helmets if isinstance(rec_helmets, list) else [rec_helmets],
                "riding_gear_style": "Armored textile / leather jacket, gauntlet gloves, and reinforced riding boots"
            },
            "verdict_takeaway": verdict
        }

        return {
            "editorial_overview": editorial_overview,
            "rider_takeaway": verdict,
            "intelligence_matrix": intelligence_matrix
        }

if __name__ == "__main__":
    import json
    samples = [
        "/Users/anumac/Documents/Projects/Helmetsan/HelmetsanWeb/data/motorcycles/bajaj_auto_ct_110x_rugged_commuter_performance_edition.json",
        "/Users/anumac/Documents/Projects/Helmetsan/HelmetsanWeb/data/motorcycles/royal_enfield_classic_500_tribute_black.json",
        "/Users/anumac/Documents/Projects/Helmetsan/HelmetsanWeb/data/motorcycles/tvs_motor_company_apache_rr_310_bto_carbon_urban_commuter.json"
    ]
    for s in samples:
        with open(s) as fp:
            item = json.load(fp)
        res = MotorcycleIntelligenceMatrix.generate_intelligence(item)
        print("="*60)
        print("BIKE:", item.get("title"))
        print("VERDICT:", res["rider_takeaway"])
        print("OVERVIEW:\n", res["editorial_overview"])
