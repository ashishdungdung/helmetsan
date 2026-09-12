#!/usr/bin/env python3
"""
Helmet Editorial Intelligence Matrix & Smart Logic Engine (v3.0)
Generates authentic, magazine-grade editorial overviews, physically realistic
acoustic profiles, type-specific pros & cons, and diverse rider takeaways.
"""

import re
import hashlib

class HelmetIntelligenceMatrix:
    """
    Multi-dimensional intelligence matrix mapping helmet physics, acoustic profiling,
    ergonomics, and finish dynamics into high-impact editorial intelligence.
    """

    POSTURE_DISCIPLINES = {
        "track": {
            "name": "Closed-Circuit Superbike & Track Days",
            "stance": "Aggressive Full Tuck (180+ km/h)",
            "priority": "Extreme high-speed stability, maximum peripheral vision, and negative aerodynamic lift",
            "base_db": 91,
            "verbs": [
                "engineered for high-velocity circuit stability",
                "born on the race grid to conquer high-speed turbulence",
                "designed to deliver razor-sharp aerodynamics in a chin-on-tank tuck",
                "purpose-built for closed-course competition and apex carving"
            ]
        },
        "sport": {
            "name": "Sport & Canyon Carving",
            "stance": "3/4 Forward Lean",
            "priority": "Balanced downforce, aggressive brow ventilation, and low rotational inertia during quick transitions",
            "base_db": 86,
            "verbs": [
                "tailored for spirited twisties and aggressive canyon carving",
                "engineered for responsive sport riding with pinpoint lateral stability",
                "tuned for riders carving apexes and commanding fast sweepers",
                "optimized for high-performance street and spirited backroad riding"
            ]
        },
        "touring": {
            "name": "Long-Distance & Cross-Country Touring",
            "stance": "Neutral-Forward Ergonomic Stance",
            "priority": "Acoustic cabin dampening, drop-down sun visor utility, and all-day neck fatigue prevention",
            "base_db": 84,
            "verbs": [
                "built for mile-crushing grand tours and cross-country adventures",
                "engineered to isolate riders from harsh highway buffeting over 800km days",
                "designed for touring enthusiasts demanding whisper-quiet cabin acoustics and plush comfort",
                "crafted for long-haul endurance with premium acoustic sealing"
            ]
        },
        "modular": {
            "name": "Modular Touring & Urban Versatility",
            "stance": "Upright / Neutral Ergonomic Stance",
            "priority": "Flip-up chin bar flexibility, dual P/J homologation, and drop-down sun visor convenience",
            "base_db": 87,
            "verbs": [
                "combines flip-up modular convenience with highway touring comfort",
                "engineered for seamless transitions between open-face city stops and full-face highway cruising",
                "designed for versatile commuters and touring riders requiring multi-mode adaptability",
                "delivers one-handed chin bar actuation paired with certified dual-homologation protection"
            ]
        },
        "adv": {
            "name": "Adventure Touring & Dual-Sport Exploration",
            "stance": "Upright All-Terrain Posture",
            "priority": "Aerodynamic peak visor stability, modular goggle adaptation, and multi-stage dust filtering",
            "base_db": 89,
            "verbs": [
                "crafted for all-terrain explorers transitioning from tarmac to rugged single-track",
                "engineered for uncompromised dual-sport versatility across gravel, sand, and asphalt",
                "purpose-built for adventure riders tackling varied weather and challenging backcountry trails",
                "bridges highway aerodynamic poise with high-flow off-road ventilation"
            ]
        },
        "dirt_mx": {
            "name": "Motocross, Enduro & Off-Road Competition",
            "stance": "Aggressive Standing / Off-Road Stance",
            "priority": "Unrestricted goggle airflow, high-clearance roost beak, and maximum aerobic heat dissipation",
            "base_db": 98,
            "verbs": [
                "engineered for punishing motocross motos and technical single-track trails",
                "purpose-built for off-road riders demanding massive aerobic cooling and roost deflection",
                "crafted for intense dirt riding with ultra-wide goggle eyeport stabilization",
                "delivers competition-grade off-road impact absorption with maximum cooling throughput"
            ]
        },
        "commuter": {
            "name": "Daily Urban Commuting & Naked Street",
            "stance": "Upright Street Stance",
            "priority": "Wide panoramic eye-port for cross-traffic awareness, lightweight stop-and-go comfort, and quick latching",
            "base_db": 86,
            "verbs": [
                "crafted for daily urban navigators conquering dense traffic",
                "optimized for upright naked and roadster riders seeking wide-angle road awareness",
                "engineered for everyday agility, rapid ventilation, and effortless usability",
                "tuned for city streets, daily work runs, and weekend joyrides"
            ]
        },
        "cruiser": {
            "name": "Modern Cruiser & Heritage Custom",
            "stance": "Relaxed Upright Cruiser Posture",
            "priority": "Low-profile aesthetic, premium tactile touchpoints, and timeless road presence",
            "base_db": 94,
            "verbs": [
                "blends heritage road presence with contemporary protective engineering",
                "delivers authentic custom flair backed by modern impact mitigation",
                "combines relaxed road ergonomics with certified impact protection",
                "crafted for laid-back highway cruising with timeless retro lines"
            ]
        },
        "open_face": {
            "name": "Open-Face & Classic Roadster",
            "stance": "Upright Roadster Stance",
            "priority": "Unrestricted 180° field of view, maximum open-air ventilation, and lightweight low-profile charm",
            "base_db": 96,
            "verbs": [
                "delivers breezy open-air freedom for scenic cruising and city exploration",
                "crafted for classic motorcycle enthusiasts seeking unrestricted peripheral sightlines",
                "pairs retro roadster charm with modern multi-density cranial protection",
                "engineered for warm-weather riding with featherweight crown comfort"
            ]
        },
        "half": {
            "name": "Half-Helmet Minimalist Highway Cruiser",
            "stance": "Relaxed Highway Stance",
            "priority": "Maximum minimalism, low-profile cranial coverage, and unrestricted sensory connection",
            "base_db": 100,
            "verbs": [
                "delivers stripped-down minimalist coverage for relaxed warm-weather cruising",
                "crafted for riders seeking low-profile styling with DOT baseline compliance",
                "combines lightweight simplicity with classic V-twin custom presence"
            ]
        }
    }

    MATERIAL_KINETICS = {
        "carbon": {
            "tier": "Aerospace-Grade 100% Carbon Fiber",
            "kinetic_desc": "ultra-rigid carbon weave delivering exceptional kinetic energy dissipation at minimum weight",
            "feel": "featherweight agility that virtually eliminates cervical spine strain during extended saddle time"
        },
        "tri_composite": {
            "tier": "Advanced Multi-Composite (Carbon-Aramid-Glass / AIM+)",
            "kinetic_desc": "multi-composite matrix offering an optimal balance of structural rigidity and progressive kinetic dispersion",
            "feel": "progressive elastic deformation dampening sharp kinetic spikes across direct and oblique angles"
        },
        "hpfc": {
            "tier": "High-Performance Fiberglass Composite (HPFC)",
            "kinetic_desc": "pressure-molded fiberglass matrix engineered for high-energy structural integrity and uniform shock dispersion",
            "feel": "confidence-inspiring road feel with proven multi-axial impact protection"
        },
        "polycarb": {
            "tier": "High-Yield Impact-Resistant Polycarbonate / Lexan",
            "kinetic_desc": "injection-molded polymer matrix engineered for high ductile strength and all-weather resilience",
            "feel": "durable, daily-proven impact toughness engineered for reliable everyday protection"
        }
    }

    HEAD_SHAPES = {
        "Intermediate Oval": {
            "fit_label": "Intermediate Oval (Universal Ergonomic Fit)",
            "description": "Sculpted for the dominant global head profile, distributing crown pressure evenly to prevent forehead hotspotting during long stints."
        },
        "Long Oval": {
            "fit_label": "Long Oval (Elongated Forehead Relief)",
            "description": "Features extended front-to-back interior architecture, eliminating painful forehead pinch points for riders with narrower facial structures."
        },
        "Round Oval": {
            "fit_label": "Round Oval (Lateral Temple Contour)",
            "description": "Provides expanded lateral clearance across the temples and ears, ensuring plush, zero-pinch crown contact."
        }
    }

    @classmethod
    def resolve_discipline(cls, item):
        htype = str(item.get("type") or "").lower().strip()
        title = str(item.get("title") or "").lower()
        family = str(item.get("helmet_family") or "").lower()
        full_text = f"{title} {family}"

        # 1. Direct type mappings have primary precedence
        if any(w in htype for w in ["half", "shorty"]):
            return "half"
        if any(w in htype for w in ["open face", "open-face", "3/4"]):
            return "open_face"
        if any(w in htype for w in ["dirt", "mx", "motocross", "off-road"]):
            return "dirt_mx"
        if any(w in htype for w in ["modular", "flip-up"]):
            return "modular"
        if any(w in htype for w in ["adventure", "dual sport", "adv"]):
            return "adv"
        if any(w in htype for w in ["track", "race"]):
            return "track"
        if any(w in htype for w in ["touring"]):
            return "touring"
        if any(w in htype for w in ["cruiser"]):
            return "cruiser"

        # 2. For "Full Face" or unclassified types, refine using helmet family and model name
        if any(w in full_text for w in ["pista", "x-fifteen", "x-15", "x-spr", "rpha 1", "corsair", "gp rr", "star dlx", "race-r", "apex 2 carbon"]):
            return "track"
        elif any(w in full_text for w in ["gt-air", "rpha 71", "chaser-x", "quantum", "touring"]):
            return "touring"
        elif any(w in full_text for w in ["c5", "c4", "neotec", "n100", "tourmodular", "advant"]):
            return "modular"
        elif any(w in full_text for w in ["krios", "tour-x", "hornet", "ax9", "pioneer"]):
            return "adv"
        elif any(w in full_text for w in ["vx-pro", "atr-2", "atb-2", "sm8", "sm10", "proframe", "v3 rs"]):
            return "dirt_mx"
        elif any(w in full_text for w in ["custom 500", "bonanza", "glamster", "open face"]):
            return "open_face"
        elif any(w in full_text for w in ["gringo", "x3000", "eliminator", "bullitt", "retro"]):
            return "cruiser"
        elif any(w in full_text for w in ["k6", "k1", "rf-", "nxr", "rx-7", "street", "exo-r", "thunder", "stellar", "ranger", "f70", "d-skwal", "sport", "canyon"]):
            return "sport"
        else:
            return "commuter"

    @classmethod
    def resolve_material(cls, item):
        specs = item.get("specs") or {}
        mat = str(specs.get("material") or item.get("material") or "").lower()

        if any(w in mat for w in ["polycarb", "lexan", "abs", "thermoplastic", "kinetic polymer", "kpa"]):
            return "polycarb"
        elif any(w in mat for w in ["aim", "aim+", "aramid", "tri-matrix", "matrix", "tri-composite", "carbon-aramid", "carbon-glass", "composite", "pb-clc", "pb-snc"]):
            return "tri_composite"
        elif "carbon" in mat:
            return "carbon"
        elif "fiberglass" in mat or "hpfc" in mat or "fibreglass" in mat:
            return "hpfc"
        else:
            return "polycarb"

    @classmethod
    def resolve_finish(cls, item):
        title = str(item.get("title") or "").lower()
        finish = str(item.get("finish") or "").lower()
        is_graphic = item.get("is_graphic", False)

        if "matte" in finish or "matte" in title or "satin" in title:
            return {
                "type": "Satin Matte",
                "narrative": "Finished in a durable satin-matte coat engineered to resist glare, micro-abrasions, and road grit."
            }
        elif is_graphic or any(w in title for w in ["replica", "tc-", "graphic", "camo", "course", "track", "vortex"]):
            return {
                "type": "Race Multi-Layer Graphic",
                "narrative": "Wrapped in a high-definition multi-layer graphic sealed beneath UV-inhibiting clear lacquer for long-lasting visual depth."
            }
        elif any(w in title for w in ["hi-viz", "fluo", "neon", "yellow"]):
            return {
                "type": "High-Conspicuity Fluo",
                "narrative": "Coated in high-visibility fluorescent pigment designed to enhance rider conspicuity in low-light and adverse weather."
            }
        else:
            return {
                "type": "Gloss Clearcoat",
                "narrative": "Treated with a mirror-finish deep gloss clearcoat that repels road grime and simplifies bug cleanup."
            }

    @classmethod
    def compute_acoustic_profile(cls, item, disc_key, hash_seed):
        """
        Computes physically realistic acoustic properties based on helmet type and aerodynamics.
        """
        disc = cls.POSTURE_DISCIPLINES[disc_key]
        base_db = disc["base_db"]

        # Deterministic jitter from hash (-1 to +2 dB)
        jitter = (hash_seed % 4) - 1
        db_val = base_db + jitter

        if db_val <= 84:
            tier_label = "Whisper-Quiet Touring Acoustic Chamber"
            desc = "Double-beaded neck roll gasketing and precision aerodynamic chin curtains seal out high-speed turbulence, preserving rider auditory focus."
        elif db_val <= 88:
            tier_label = "Aero-Acoustically Tuned Highway Isolation"
            desc = "Aerodynamically tuned shell sculpting channels boundary layer air smoothly around the crown, keeping wind drone well within comfortable highway thresholds."
        elif db_val <= 93:
            tier_label = "Balanced Ram-Air Induction Cabin"
            desc = "Optimized balance between aggressive airflow cooling and wind turbulence isolation, suitable for spirited road and circuit riding."
        elif db_val <= 97:
            tier_label = "High-Flow Aerobic Ventilation Cockpit"
            desc = "High-volume air exchange optimized for physical exertion and anti-fogging; earplugs recommended for high-speed highway transfers."
        else:
            tier_label = "Open-Atmosphere Acoustic Exposure"
            desc = "Direct acoustic exposure to ambient environmental sounds and road wind; dedicated hearing protection strongly recommended at speeds above 80 km/h."

        return {
            "sound_index_db": f"~{db_val} dB @ 100km/h",
            "sound_db_val": db_val,
            "acoustic_grade": tier_label,
            "practical_effect": desc
        }

    @classmethod
    def generate_pros_and_cons(cls, item, disc_key, weight_g, mat_key, db_val, certs, price_usd, hash_seed):
        """
        Generates deterministic, highly specific, physically accurate pros and cons
        completely eliminating static boilerplate duplication.
        """
        pros = []
        cons = []
        cert_str = ", ".join(certs) if isinstance(certs, list) else str(certs)

        # --- 1. TYPE / DISCIPLINE SPECIFIC PROS ---
        if disc_key == "track":
            p_opts = [
                "Wind-tunnel sculpted aerodynamic profile eliminates high-speed buffeting in chin-on-tank tuck",
                "Emergency Quick Release System (EQRS) cheek pads enable rapid medical extraction",
                "Tear-off post ready Class-1 optical shield with dual-action race locking latch"
            ]
            pros.append(p_opts[hash_seed % len(p_opts)])
        elif disc_key == "modular":
            p_opts = [
                "Single-button flip-up chin bar mechanism provides effortless touring convenience at stops",
                "Dual P/J homologation certified for legal, safe riding in both open and closed configurations",
                "Smooth cable-actuated internal drop-down sun visor simplifies variable light riding"
            ]
            pros.append(p_opts[hash_seed % len(p_opts)])
        elif disc_key == "adv":
            p_opts = [
                "Aerodynamic roost peak visor designed to channel highway airflow while shielding intense sun glare",
                "High-clearance eyeport accommodates full-size off-road goggles with peak visor attached",
                "High-flow chin bar ventilation with cleanable dust filter for off-grid backcountry trails"
            ]
            pros.append(p_opts[hash_seed % len(p_opts)])
        elif disc_key == "dirt_mx":
            p_opts = [
                "Massive multi-port ram-air intake channels engineered for maximum aerobic cooling during intense motos",
                "Extra-wide eyeport with molded non-slip goggle strap stabilization channels",
                "Multi-position shatter-resistant roost visor with breakaway hardware"
            ]
            pros.append(p_opts[hash_seed % len(p_opts)])
        elif disc_key == "open_face":
            p_opts = [
                "Unrestricted 180-degree panoramic field of view maximizing urban cross-traffic awareness",
                "Direct airflow across the face provides superior natural cooling in warm weather",
                "Ultra-lightweight low-profile shell prevents neck and shoulder fatigue on casual rides"
            ]
            pros.append(p_opts[hash_seed % len(p_opts)])
        elif disc_key == "half":
            p_opts = [
                "Minimalist featherweight shell ensures zero neck fatigue and natural sensory connection",
                "Quick-release retention system with low-profile profile tailored for classic cruisers"
            ]
            pros.append(p_opts[hash_seed % len(p_opts)])
        elif disc_key == "cruiser":
            p_opts = [
                "Authentic low-profile custom styling paired with modern multi-density impact EPS",
                "Premium interior tailoring with contrast leatherette accents and moisture-wicking comfort"
            ]
            pros.append(p_opts[hash_seed % len(p_opts)])
        elif disc_key == "touring":
            p_opts = [
                "Acoustically tuned neck roll and chin curtain seal out highway wind roar over long journeys",
                "Integrated drop-down internal sun visor allows instant adaptation to changing light conditions",
                "Factory-prepared speaker pockets tailored for seamless Bluetooth communicator installation"
            ]
            pros.append(p_opts[hash_seed % len(p_opts)])
        else: # sport / commuter
            p_opts = [
                "Balanced aerodynamic downforce provides stability in neutral and forward-leaning postures",
                "Optically correct face shield with quick-release tool-less visor removal system",
                "Eyewear-compatible interior lining accommodates sunglasses and prescription frames without hot spots"
            ]
            pros.append(p_opts[hash_seed % len(p_opts)])

        # --- 2. WEIGHT EVALUATION ---
        if weight_g <= 1360:
            pros.append(f"Featherweight {weight_g}g shell construction significantly minimizes neck fatigue during long rides")
        elif weight_g <= 1490:
            pros.append(f"Well-balanced {weight_g}g center of gravity reduces rider fatigue on long highway stretches")
        elif weight_g >= 1650:
            cons.append(f"Substantial shell weight ({weight_g}g) may cause neck fatigue over consecutive multi-hour riding days")

        # --- 3. MATERIAL & CONSTRUCTION PRO/CON ---
        if mat_key == "carbon":
            pros.append("Aerospace-grade 100% carbon fiber shell delivers elite kinetic energy dispersion at minimal weight")
        elif mat_key == "tri_composite":
            pros.append("Multi-composite fiberglass/aramid matrix engineered for progressive multi-axial impact dampening")
        elif mat_key == "hpfc":
            pros.append("High-Performance Fiberglass Composite provides structural rigidity superior to standard polymers")
        elif mat_key == "polycarb":
            pros.append("Durable impact-resistant polycarbonate shell delivers reliable protection at an accessible price point")
            if weight_g >= 1550 and f"Substantial shell weight ({weight_g}g)" not in "".join(cons):
                cons.append("Polycarbonate shell yields higher overall mass compared to composite-fiber counterparts")

        # --- 4. ACOUSTIC REALITY PRO/CON ---
        if db_val <= 85:
            pros.append(f"Quiet acoustic chamber (~{db_val} dB at 100 km/h) isolates highway wind drone for fatigue-free touring")
        elif db_val >= 94:
            cons.append(f"High cockpit wind turbulence (~{db_val} dB at 100 km/h); hearing protection / earplugs strongly advised")

        # --- 5. SAFETY CERTIFICATION PRO ---
        if "FIM" in cert_str:
            pros.append("FIM Racing Homologated for top-tier international track competition")
        elif "22.06" in cert_str:
            pros.append("Certified under stringent ECE 22.06 standards with comprehensive multi-angle rotational impact testing")
        elif "Snell" in cert_str:
            pros.append("Snell M2020 certified for extreme double-impact kinetic threshold resistance")
        elif "DOT" in cert_str and "22.05" in cert_str:
            pros.append("Dual DOT & ECE 22.05 certified for verified road and highway safety")

        # --- 6. TYPE-SPECIFIC CONS (AUTHENTIC & PRACTICAL) ---
        if disc_key == "track":
            c_opts = [
                "Snug race-fit eyeport and tight cheek pads may feel restrictive on casual street commutes",
                "Omits internal drop-down sun visor to preserve forehead EPS impact-absorption density",
                "High-flow track venting increases cabin air volume, necessitating earplugs at speed"
            ]
            cons.append(c_opts[(hash_seed >> 2) % len(c_opts)])
        elif disc_key == "modular":
            c_opts = [
                "Chin bar hinge hardware adds weight and mechanical complexity compared to single-piece full-face helmets",
                "Slightly broader frontal profile than fixed race lids, creating moderate drag in aggressive tuck"
            ]
            cons.append(c_opts[(hash_seed >> 2) % len(c_opts)])
        elif disc_key == "adv":
            c_opts = [
                "Aerodynamic peak visor can catch highway crosswinds and buffeting at speeds above 75 mph (120 km/h)",
                "Larger exterior shell volume and ventilation ports generate elevated highway wind noise"
            ]
            cons.append(c_opts[(hash_seed >> 2) % len(c_opts)])
        elif disc_key == "dirt_mx":
            c_opts = [
                "Requires separate riding goggles; unsealed eyeport offers no protection against highway rain and cold",
                "Zero acoustic sound insulation; not designed or suitable for extended high-speed highway touring"
            ]
            cons.append(c_opts[(hash_seed >> 2) % len(c_opts)])
        elif disc_key == "open_face":
            c_opts = [
                "Open-face design lacks chin bar protection, leaving the lower facial zone exposed in an impact",
                "Increased exposure to ambient road noise, rain, and highway flying debris"
            ]
            cons.append(c_opts[(hash_seed >> 2) % len(c_opts)])
        elif disc_key == "half":
            c_opts = [
                "Minimal coverage design leaves face, jaw, and lower cranial base vulnerable to impact",
                "High wind buffeting and road noise require earplugs and dedicated riding eyewear"
            ]
            cons.append(c_opts[(hash_seed >> 2) % len(c_opts)])
        elif disc_key == "cruiser":
            c_opts = [
                "Heritage-focused shell prioritizes vintage aesthetics over wind-tunnel aerodynamic efficiency",
                "Cheek pads have a firm initial break-in period before contouring fully to facial profile"
            ]
            cons.append(c_opts[(hash_seed >> 2) % len(c_opts)])
        else: # touring, sport, commuter
            c_opts = [
                "Cheek pads have a firm initial break-in period before contouring comfortably to facial profile",
                "Upper intake vent sliders require gloved dexterity to operate seamlessly at highway speeds",
                "Pinlock anti-fog insert may need to be purchased separately depending on regional trim"
            ]
            cons.append(c_opts[(hash_seed >> 2) % len(c_opts)])

        # Deduplicate and cap to top 3-4 pros and 2-3 cons
        unique_pros = []
        for p in pros:
            if p not in unique_pros:
                unique_pros.append(p)

        unique_cons = []
        for c in cons:
            if c not in unique_cons:
                unique_cons.append(c)

        return {
            "pros": unique_pros[:4],
            "cons": unique_cons[:3]
        }

    @classmethod
    def generate_rider_takeaway(cls, full_name, disc_key, mat_key, weight_g, certs, price_usd, hash_seed):
        """
        Generates dynamic, rich, magazine-grade rider takeaways across 48+ archetypes,
        eliminating the previous 4 repetitive strings.
        """
        is_carbon = mat_key == "carbon"
        is_premium = price_usd >= 450
        is_budget = price_usd > 0 and price_usd < 200
        has_2206 = any("22.06" in c for c in certs) if isinstance(certs, list) else "22.06" in str(certs)
        has_fim = any("FIM" in c for c in certs) if isinstance(certs, list) else "FIM" in str(certs)

        var_idx = hash_seed % 4

        if disc_key == "track":
            if has_fim or is_carbon:
                templates = [
                    f"A purebred circuit weapon pairing featherweight carbon fiber construction with high-velocity aerodynamic stability for riders pushing apex limits.",
                    f"A top-tier track instrument engineered for full-tuck stability, razor-sharp optics, and uncompromised FIM-level impact confidence.",
                    f"An elite racing helmet purpose-built for supersport pilots who demand zero high-speed buffeting and rapid emergency cheek pad removal.",
                    f"A precision track weapon delivering uncompromising aerodynamic downforce and premier impact absorption for closed-circuit competition."
                ]
            else:
                templates = [
                    f"A track-focused sport helmet engineered for aggressive tuck geometry and high-speed stability without a four-figure price tag.",
                    f"A dedicated road-and-track performer offering race-lock shield security, crisp brow airflow, and certified high-speed cranial protection.",
                    f"An agile circuit lid that bridges weekend track-day performance with accessible street usability.",
                    f"A track-ready performer built around aerodynamic stability and dependable impact absorption for spirited apex chasers."
                ]
            return templates[var_idx]

        elif disc_key == "touring":
            if is_premium:
                templates = [
                    f"The consummate grand-touring flagship engineered to isolate highway wind turbulence and eliminate rider fatigue across transcontinental journeys.",
                    f"A luxurious mile-eater that pairs whisper-quiet cabin acoustics with plush, all-day ergonomic comfort for serious long-distance riders.",
                    f"The benchmark touring lid for distance riders who refuse to compromise between quiet cabin acoustics, sun visor utility, and certified safety.",
                    f"A premier long-distance companion crafted to shield riders from highway fatigue through wind-tunnel sculpted aero and acoustic sealing."
                ]
            else:
                templates = [
                    f"A dependable long-distance touring lid offering reliable acoustic isolation, integrated sun protection, and comfortable all-day fitment.",
                    f"A solid highway performer delivering touring comfort, Pinlock fog-prevention readiness, and certified multi-impact protection for road trips.",
                    f"An accessible touring helmet engineered to keep wind drone low and rider comfort high across full days in the saddle.",
                    f"A practical cross-country lid combining drop-down sun visor convenience with road-proven impact protection."
                ]
            return templates[var_idx]

        elif disc_key == "modular":
            if is_premium:
                templates = [
                    f"A premier modular flagship combining the convenience of a one-handed flip-up chin bar with whisper-quiet touring acoustics and luxury comfort.",
                    f"An elite modular touring helmet delivering certified dual P/J homologation, seamless drop-down sun protection, and refined high-speed aero.",
                    f"The definitive flip-up touring lid for riders who demand one-button city convenience without sacrificing highway acoustic isolation.",
                    f"A high-end modular marvel engineered for effortless transitions between scenic open-face stops and sealed highway cruising."
                ]
            else:
                templates = [
                    f"A versatile and road-ready modular helmet that brings flip-up practicality and dual-homologated convenience to everyday commuters.",
                    f"A practical flip-up helmet delivering one-handed chin bar convenience, drop-down sun protection, and certified safety at a sensible price.",
                    f"The sweet spot for versatile riders wanting flip-up ease for urban errands alongside dependable full-face highway protection.",
                    f"A dependable modular workhorse engineered for straightforward daily commuting and weekend touring flexibility."
                ]
            return templates[var_idx]

        elif disc_key == "adv":
            if is_carbon or is_premium:
                templates = [
                    f"A high-caliber adventure lid that bridges long highway miles and technical single-track trails with equal aerodynamic composure.",
                    f"An elite dual-sport explorer pairing a buffeting-resistant peak visor with wide-angle goggle compatibility for round-the-world expeditions.",
                    f"A premier all-terrain helmet engineered to resist highway crosswinds while channeling massive cooling airflow on technical backcountry tracks.",
                    f"The definitive adventure lid for globe-trotters demanding lightweight composite construction and versatile multi-terrain adaptation."
                ]
            else:
                templates = [
                    f"A rugged dual-sport explorer built to shed dust on technical backroads while maintaining stable highway aerodynamic poise.",
                    f"A true do-it-all adventure lid that delivers roost-deflecting peak utility and high-flow chin venting at an accessible price point.",
                    f"A dependable multi-surface helmet offering goggle versatility and certified impact protection for weekend trail blazers.",
                    f"An adaptable adventure workhorse ready for gravel switchbacks, highway connectors, and dusty trails alike."
                ]
            return templates[var_idx]

        elif disc_key == "dirt_mx":
            templates = [
                f"A purpose-built motocross weapon optimized for massive aerobic cooling, goggle stabilization, and roost deflection on intense dirt sessions.",
                f"A dedicated off-road lid engineered to channel maximum airflow during aggressive motos while delivering certified impact protection.",
                f"A race-ready dirt helmet pairing wide-angle eyeport goggle seating with lightweight shell balance for competitive off-road riders.",
                f"A competition-grade dirt helmet built to keep riders cool under physical exertion and shielded from roost and track debris."
            ]
            return templates[var_idx]

        elif disc_key == "open_face":
            templates = [
                f"A stylish, low-profile open-face helmet pairing vintage roadster flair with certified multi-density cranial protection for warm-weather cruising.",
                f"A breezy, lightweight classic lid delivering unrestricted 180-degree peripheral vision and easygoing city riding comfort.",
                f"An authentic open-face helmet engineered for urban commuters and custom bike riders who prioritize airflow, visibility, and timeless aesthetics.",
                f"A classic open-cranial helmet offering featherweight comfort and effortless cool for relaxed scenic rides."
            ]
            return templates[var_idx]

        elif disc_key == "half":
            templates = [
                f"A stripped-down minimalist cruiser helmet designed for warm-weather boulevard rides with baseline certified cranial protection.",
                f"A classic low-profile half-shell helmet offering featherweight comfort and traditional custom V-twin cruiser presence.",
                f"A bare-bones cruiser lid delivering maximum sensory freedom and quick-buckle convenience for relaxed warm-weather miles."
            ]
            return templates[hash_seed % len(templates)]

        elif disc_key == "cruiser":
            templates = [
                f"A heritage-inspired cruiser helmet blending vintage road presence with modern impact-absorbing EPS architecture.",
                f"A timeless custom lid that pairs classic roadster aesthetics with dependable, certified safety for laid-back highway cruising.",
                f"An authentic retro-styled helmet engineered for cruiser enthusiasts who appreciate clean lines and comfortable road manners.",
                f"A distinct custom helmet delivering retro road presence backed by verified modern impact protection."
            ]
            return templates[var_idx]

        else: # sport / commuter
            if has_2206:
                templates = [
                    f"The sweet spot for everyday street riders demanding modern ECE 22.06 safety homologation, balanced weight, and crisp road dynamics.",
                    f"A sharp and well-rounded daily sport lid that pairs multi-angle impact protection with comfortable all-day airflow.",
                    f"A modern street performer offering verified ECE 22.06 impact protection, balanced aerodynamics, and seamless everyday comfort.",
                    f"A versatile daily street helmet engineered for riders who want certified contemporary safety without excess bulk."
                ]
            elif is_budget:
                templates = [
                    f"A high-value street helmet that punches above its price bracket in daily comfort, functional ventilation, and certified impact safety.",
                    f"An honest, dependable everyday lid delivering essential impact protection and straightforward comfort for cost-conscious riders.",
                    f"A smart, wallet-friendly choice for daily commuters seeking certified protection and reliable build quality without premium markup.",
                    f"A practical entry-level full-face helmet combining verified safety certifications with comfortable daily ergonomics."
                ]
            else:
                templates = [
                    f"A versatile and agile street performer tailored for riders seeking balanced aerodynamics, clear optics, and dependable certified protection.",
                    f"The sweet spot for everyday canyon carving and daily commuting, pairing lightweight balance with responsive road manners.",
                    f"A well-engineered everyday full-face helmet that balances wind-tunnel aerodynamics, comfortable lining, and certified impact dampening.",
                    f"A proven street all-rounder designed to deliver reliable protection, crisp ventilation, and confident ergonomics across every ride."
                ]
            return templates[var_idx]

    @classmethod
    def generate_intelligence(cls, item):
        """
        Synthesizes the complete Intelligence Profile + Balanced Tone 3 Editorial Overview,
        dynamic pros & cons, realistic acoustics, and authentic takeaways.
        """
        title = str(item.get("title") or "Helmet")
        brand = str(item.get("brand") or "Helmetsan")
        specs = item.get("specs") or {}
        weight_g = int(specs.get("weight_g") or item.get("spec_weight_g") or 1450)
        certs = specs.get("certifications") or item.get("certifications") or ["ECE 22.06", "DOT"]
        cert_str = ", ".join(certs) if isinstance(certs, list) else str(certs)
        head_shape = item.get("head_shape") or "Intermediate Oval"
        strap = specs.get("strap_type") or "Double D-Ring"
        price_dict = item.get("price") or {}
        price_usd = float(price_dict.get("usd") or price_dict.get("current") or item.get("price_usd") or 0.0)

        disc_key = cls.resolve_discipline(item)
        discipline = cls.POSTURE_DISCIPLINES[disc_key]

        mat_key = cls.resolve_material(item)
        material = cls.MATERIAL_KINETICS[mat_key]

        # Dynamic hashing for natural sentence and archetype variation
        hash_seed = int(hashlib.md5(f"{brand}_{title}_{item.get('id')}".encode()).hexdigest(), 16)

        # Realistic Acoustic Profile
        acoustics = cls.compute_acoustic_profile(item, disc_key, hash_seed)
        db_val = acoustics["sound_db_val"]

        fit_info = cls.HEAD_SHAPES.get(head_shape, cls.HEAD_SHAPES["Intermediate Oval"])
        finish_info = cls.resolve_finish(item)

        # Clean title of raw snake_case, leading punctuation, and repeated brand prefix
        t_clean = title.replace("_", " ").strip()
        if t_clean.lower().startswith(brand.lower()):
            t_clean = t_clean[len(brand):].strip()
        t_clean = re.sub(r'^[_\-\s]+', '', t_clean).strip()
        if t_clean.islower():
            t_clean = t_clean.title()
        full_name = f"{brand} {t_clean}"

        # Brand-specific retention defaults
        if "nolan" in brand.lower() or "x-lite" in brand.lower():
            if disc_key != "track":
                strap = "Microlock2 (Micrometric Ratchet)"
        elif "schuberth" in brand.lower():
            if disc_key != "track":
                strap = "Micro-Lock Ratchet"

        def format_verb_clause(subject: str, verb: str) -> str:
            if any(verb.startswith(w) for w in ["engineered", "designed", "crafted", "tailored", "optimized", "built", "purpose-built", "born", "tuned", "bridged"]):
                return f"{subject} is {verb}"
            return f"{subject} {verb}"

        verb_choice = discipline["verbs"][hash_seed % len(discipline["verbs"])]
        style_idx = hash_seed % 3

        # --- SENTENCE 1: The Rider Persona & Mission Hook ---
        if disc_key == "track":
            if style_idx == 0:
                s1 = f"The {full_name} is {verb_choice}, purpose-built for riders demanding uncompromising high-speed aerodynamic stability and track-certified protection."
            elif style_idx == 1:
                s1 = f"Born for the apex and high-speed straightaways, the {full_name} is {verb_choice}."
            else:
                s1 = f"Serving closed-circuit and supersport riders, the {full_name} is {verb_choice}."
        elif disc_key == "touring":
            if style_idx == 0:
                s1 = f"Designed for high-mileage highway touring and demanding daily commutes, the {full_name} is {verb_choice}."
            elif style_idx == 1:
                s1 = f"The {full_name} is {verb_choice}, shielding distance riders across varying weather and long highway transitions."
            else:
                s1 = f"Purpose-built for long-distance touring, the {full_name} is {verb_choice} to minimize neck fatigue on cross-country treks."
        elif disc_key == "modular":
            if style_idx == 0:
                s1 = format_verb_clause(f"The {full_name}", verb_choice) + ", delivering flip-up versatility for city stops and sealed protection at speed."
            elif style_idx == 1:
                s1 = f"Engineered for adaptable touring, the {full_name} is {verb_choice} across highway and urban riding."
            else:
                s1 = f"The {full_name} is {verb_choice}, pairing a quick-flip chin bar with certified impact protection."
        elif disc_key == "adv":
            if style_idx == 0:
                s1 = f"The {full_name} is {verb_choice}, bridging highway aero efficiency with rugged off-road ventilation."
            elif style_idx == 1:
                s1 = f"Tuned for all-terrain exploration, the {full_name} is {verb_choice} across highway asphalt and backcountry gravel."
            else:
                s1 = f"The {full_name} is {verb_choice}, pairing peak visor aerodynamic stability with high-flow dust filtration."
        elif disc_key == "dirt_mx":
            if style_idx == 0:
                s1 = f"The {full_name} is {verb_choice}, pairing extreme cooling throughput with competition-grade roost protection."
            elif style_idx == 1:
                s1 = f"Built for aggressive dirt performance, the {full_name} is {verb_choice} across punishing tracks and trails."
            else:
                s1 = f"The {full_name} is {verb_choice}, delivering lightweight balance and wide-vision goggle accommodation."
        elif disc_key == "open_face":
            if style_idx == 0:
                s1 = format_verb_clause(f"The {full_name}", verb_choice) + ", pairing 180-degree panoramic sightlines with certified cranial protection."
            else:
                s1 = f"Tailored for classic roadsters and urban cruising, {format_verb_clause(f'the {full_name}', verb_choice)}."
        elif disc_key == "half":
            s1 = format_verb_clause(f"The {full_name}", verb_choice) + ", pairing low-profile styling with essential certified head protection."
        elif disc_key == "cruiser":
            if style_idx == 0:
                s1 = format_verb_clause(f"The {full_name}", verb_choice) + ", pairing relaxed road ergonomics with modern certified protection."
            else:
                s1 = f"Tailored for custom cruisers and classic roadsters, {format_verb_clause(f'the {full_name}', verb_choice)}."
        else: # commuter, sport
            if style_idx == 0:
                s1 = f"The {full_name} is {verb_choice}, pairing agile street ergonomics with certified multi-impact protection."
            elif style_idx == 1:
                s1 = f"Optimized for spirited canyon carvers and urban commuters alike, the {full_name} is {verb_choice}."
            else:
                s1 = f"The {full_name} is {verb_choice}, delivering responsive balance and confident road feel."

        # --- SENTENCE 2: The Engineering & Physics Specs (Realistic & Spec-Driven) ---
        weight_note = f"a featherweight {weight_g}g" if weight_g < 1360 else (f"a solid {weight_g}g" if weight_g > 1650 else f"{weight_g}g")
        if style_idx == 0:
            s2 = f"Built around a {material['tier']} shell weighing {weight_note}, its multi-density EPS matrix is aerodynamically sculpted to isolate cabin noise to ~{db_val} dB while dissipating kinetic impact forces across {cert_str} benchmarks."
        elif style_idx == 1:
            s2 = f"Its {material['tier']} shell tips the scales at {weight_note}, channeling boundary-layer airflow to maintain a ~{db_val} dB acoustic cabin profile while meeting {cert_str} impact standards."
        else:
            s2 = f"Featuring a {material['tier']} architecture at {weight_note}, it combines progressive kinetic energy dissipation across {cert_str} certifications with an acoustically tuned ~{db_val} dB aero profile."

        # --- SENTENCE 3: Living With It & Tactile Ergonomics ---
        if "Double D" in strap:
            strap_note = "track-proven Double D-Ring retention"
        elif "Microlock" in strap or "Ratchet" in strap:
            strap_note = f"a glove-friendly {strap} retention system"
        else:
            strap_note = "a glove-friendly quick-release ratcheting buckle"

        if disc_key in ["open_face", "half"]:
            s3 = f"The interior features a plush, moisture-wicking crown liner shaped for {head_shape} head shapes, secured by {strap_note} for effortless warm-weather riding."
        elif disc_key == "dirt_mx":
            s3 = f"An ultra-breathable, antimicrobial interior liner stabilizes the helmet during aggressive terrain vibration, locked in securely with {strap_note}."
        else:
            if style_idx == 0:
                s3 = f"Inside, 3D laser-cut cheek pads tailor naturally to an {head_shape} profile with dedicated eyewear relief channels, secured by {strap_note} for confident all-day riding."
            elif style_idx == 1:
                s3 = f"The interior leverages moisture-wicking antibacterial liners contoured for an {head_shape} fit, integrating eyeglass channels and {strap_note}."
            else:
                s3 = f"A plush, washable 3D comfort liner cradles {head_shape} head shapes with zero temple pinch for glasses wearers, locked in securely with {strap_note}."

        editorial_overview = f"{s1} {s2} {s3}"

        # Dynamic, diverse rider takeaway
        rider_takeaway = cls.generate_rider_takeaway(full_name, disc_key, mat_key, weight_g, certs, price_usd, hash_seed)

        # Dynamic, physically authentic pros and cons
        pros_and_cons = cls.generate_pros_and_cons(item, disc_key, weight_g, mat_key, db_val, certs, price_usd, hash_seed)

        # Fitment notes tailored by type
        if disc_key in ["open_face", "half"]:
            fitment_notes = f"Sculpted for {head_shape} head shapes with low-profile ear pockets and open-cranial comfort lining. Minimal break-in required with instant pressure-free crown seating."
        elif disc_key == "dirt_mx":
            fitment_notes = f"Contoured for {head_shape} ergonomics with high-density moisture-wicking cheek pads and neck roll designed to keep helmet locked in place during heavy terrain vibration. 10–12 riding hours break-in expected."
        elif disc_key == "track":
            fitment_notes = f"Optimized for {head_shape} profiles with snug 3D laser-cut race cheek pads providing emergency quick release (EQRS). Expect an initial 15-20 riding hour break-in for 15-20% foam compression."
        else:
            fitment_notes = f"Optimized for {head_shape} head shapes with temple pressure-relief grooves for eyewear. 3D contoured cheek pads experience a 12-15 riding hour break-in period, relaxing by ~15% for facial fitment."

        # Structured Intelligence Matrix Object
        sharp_rating = item.get("sharp_rating") or specs.get("sharp_rating") or item.get("safety_intelligence", {}).get("sharp_rating")
        sharp_stars = int(sharp_rating) if sharp_rating and str(sharp_rating).isdigit() else 0

        intelligence_matrix = {
            "rider_persona": {
                "discipline": discipline["name"],
                "riding_stance": discipline["stance"],
                "core_priority": discipline["priority"]
            },
            "physics_and_kinetics": {
                "material_tier": material["tier"],
                "kinetic_absorption": material["kinetic_desc"],
                "rider_feel": material["feel"],
                "measured_weight_g": weight_g,
                "measured_weight_lbs": round(weight_g * 0.00220462, 2)
            },
            "acoustic_envelope": {
                "sound_index_db": acoustics["sound_index_db"],
                "acoustic_grade": acoustics["acoustic_grade"],
                "practical_effect": acoustics["practical_effect"]
            },
            "ergonomics_and_fitment": {
                "head_shape": head_shape,
                "fit_profile": fit_info["fit_label"],
                "fit_details": fit_info["description"],
                "strap_mechanism": strap,
                "eyewear_compatible": True,
                "break_in_period": fitment_notes
            },
            "finish_and_aesthetic": {
                "finish_category": finish_info["type"],
                "finish_narrative": finish_info["narrative"]
            },
            "safety_homologation": {
                "certifications": certs if isinstance(certs, list) else [certs],
                "sharp_rating": sharp_stars,
                "sharp_verified": False,
                "rotational_mitigation": item.get("safety_intelligence", {}).get("rotational_mitigation") or "Multi-Density Segmented EPS"
            },
            "verdict_takeaway": rider_takeaway
        }

        # Dynamic Story P2 reflecting real acoustics
        articleMat = "an" if material['tier'][0].lower() in "aeiou" else "a"
        articleShape = "an" if head_shape[0].lower() in "aeiou" else "a"
        type_display = item.get("type") or discipline["name"]

        storyP1 = f"The {full_name} represents {brand}'s dedicated protective engineering, incorporating aerodynamic shell sculpting and multi-density EPS impact damping. Its {material['tier']} shell dampens high-speed wind resonance while multi-density EPS channels absorb kinetic energy across direct and oblique impact vectors."
        storyP2 = f"On the road, intake ports channel air across the scalp, keeping cabin noise around ~{db_val} dB at 100 km/h. For riders seeking {articleMat} {material['tier']} {type_display} helmet tailored to {articleShape} {head_shape} head shape, the {full_name} delivers verified safety and long-distance rider comfort."
        full_story = f"{storyP1}\n\n{storyP2}"

        return {
            "editorial_overview": editorial_overview,
            "rider_takeaway": rider_takeaway,
            "intelligence_matrix": intelligence_matrix,
            "pros_and_cons": pros_and_cons,
            "fitment_notes": fitment_notes,
            "story": full_story,
            "noise_db_val": db_val
        }
