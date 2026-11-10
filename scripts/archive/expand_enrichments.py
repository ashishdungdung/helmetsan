# Additional in-depth technical sections for guides 6, 7, 9, 10, 11, 12, 13
additional = {
    "best-modular-flip-up-helmets-guide": """
<h2>9. Chin Bar Ingress Protection: Weather Sealing in Torrential Rain</h2>
<p>While impact safety is paramount, daily riders and continental tourers judge modular helmets by their ability to keep water out during 6-hour rainstorms. In a full-face helmet, the chin bar is an impenetrable solid wall. In a modular, the dividing seam between the chin bar and shell sits directly in the path of 75 mph wind-driven rain.</p>

<p>Top-tier models (such as the Shoei Neotec 3 and Schuberth C5) combat water intrusion through precision dual-stage sealing:
1. <strong>Channel-Draining Gaskets:</strong> Below the main visor seal, a secondary trough molded from ethylene propylene diene monomer (EPDM) synthetic rubber catches any micro-droplets that bypass the seam, funneling water downward and out through gravity drain weep holes behind the chin curtain.
2. <strong>Over-Center Latch Tensioning:</strong> When the chin bar is swung shut, the internal steel pawls do not merely click into place; they act as cams that pull the chin bar inward by 1.5mm, compressing the rubber gasket under continuous mechanical preload. This creates a hermetic seal capable of resisting pressurized highway spray.</p>
""",

    "quietest-motorcycle-helmets-highway-commuting": """
<h2>9. Aerodynamics of Motorcycle Mirrors and Handguards</h2>
<p>In comprehensive aero-acoustic laboratory testing, riders often blame their helmets for noise when the true root cause is motorcycle peripheral hardware. Large, square rearview mirrors positioned close to the handlebars shed high-frequency turbulent vortices that hit the side of the helmet shell right at the rider's ear level.</p>

<p>Similarly, wide adventure handguards create localized wake turbulence that trips oncoming laminar air. Swapping stock mirrors for aerodynamic teardrop mirrors or bar-end mirrors can clean the airflow around the rider's chest and shoulders, reducing cabin noise by an additional 2 to 4 dB(A). Always evaluate your motorcycle's total aerodynamic cockpit system when pursuing acoustic silence.</p>
""",

    "how-helmet-ventilation-works-channel-routing": """
<h2>9. Cold-Weather Vent Management and Thermal Headliners</h2>
<p>In sub-freezing winter riding, excessive ventilation becomes a dangerous hazard. Direct freezing drafts across the forehead can induce brain freeze headaches and reduce peripheral blood flow. Elite all-season helmets feature customizable thermal baffles:</p>
<ul>
    <li><strong>EPS Shutter Flaps:</strong> Helmets like the Shoei GT-Air 3 and Schuberth C5 include sliding fabric or foam shutter flaps that can be closed from inside the crown liner, physically blocking the internal EPS airflow channels while keeping exterior vents sealed against road salt and slush.</li>
    <li><strong>Winter Chin Curtains:</strong> Extended neoprene lower chin skirts clip under the jawline, blocking cold air drafts from rushing up into the eyeport and keeping exhaled respiratory heat directed downward through the bottom exhaust perimeter.</li>
</ul>
""",

    "adventure-dual-sport-helmets-buyers-guide": """
<h2>9. Sand, Dust, and Desert Filtration Architecture</h2>
<p>When riding through desert sand dunes, volcanic silt, or powdery gravel roads, conventional street helmet vents ingest fine particulate dust that irritates the rider's eyes and lungs. True off-road and adventure helmets incorporate specialized filtration systems:</p>
<ol>
    <li><strong>Replaceable Foam Mouth Filters:</strong> The front chin vent houses an open-cell polyurethane foam filter saturated in light filter oil. This traps sand grains and trail dust while maintaining free respiratory airflow. When clogged, the filter can be extracted in seconds, rinsed with soap, and reinstalled without tools.</li>
    <li><strong>Eyeport Gasket Dust Sealing:</strong> The rubber beading surrounding the eyeport is molded with wide, dual-flange lips that mate seamlessly against the closed face shield or the foam perimeter of motocross goggles, forming an impenetrable barrier against desert dust storms.</li>
</ol>
""",

    "track-day-helmet-requirements-fim-homologation": """
<h2>9. Visor Defogging and Breath Deflectors at 170 MPH</h2>
<p>On a road racing circuit, fogging a visor going into a 150 mph brake marker is terrifying. Because track riders breathe heavily under maximum heart-rate exertion (often exceeding 165 bpm during a 20-minute sprint race), moisture management is critical.</p>

<p>Track-specific helmets feature rigid, contoured <strong>breath deflectors</strong> that channel exhaled air directly down toward the chin curtain and away from the face shield. Paired with Optical Class 1 Pinlock 120 MaxVision racing inserts and two-dimensional flat race shields that provide zero optical distortion at acute lean angles, track-certified helmets guarantee crystal-clear vision from the starting lights to the checkered flag.</p>
""",

    "integrated-bluetooth-comm-systems-vs-aftermarket": """
<h2>9. Battery Chemistry, Cold-Weather Performance & Fast Charging</h2>
<p>Motorcyclists often ride through diverse climate extremes. Lithium-ion battery chemistry behaves very differently at 0°C (32°F) than at 25°C (77°F). In sub-freezing mountain rides, internal battery resistance increases, which can cut operational intercom talk time in half on budget communicators.</p>

<p>Flagship systems (such as the Cardo Packtalk Edge and Sena 50S) utilize advanced lithium-polymer cells with smart battery management systems (BMS) and USB-C fast charging. A brief 20-minute coffee stop gives the unit over two hours of full mesh talk time, ensuring you are never stranded without GPS navigation instructions or group communications during all-day touring epics.</p>
""",

    "how-to-wash-helmet-liners-maintain-visors": """
<h2>9. Visor Baseplate Realignment & Tension Calibration</h2>
<p>Over thousands of miles, road vibrations and repeated visor openings can cause baseplate mounting screws to back out slightly. If you notice a faint wind whistle on one side of your face shield, or if rain droplets seep past the upper gasket at speed, your visor ratchet baseplate is out of alignment.</p>

<p>To calibrate baseplate tension:
1. Loosen the two mounting screws on the affected baseplate by one half-turn so the plate can slide with slight friction.
2. Snap the visor fully closed and lock the central latch tab.
3. Apply firm, even palm pressure on the outside of the face shield directly over the pivot, pushing the visor snugly against the rubber eyeport beading.
4. While maintaining palm pressure, carefully tighten both baseplate screws to secure the alignment. Test by sliding a dollar bill between the closed visor and rubber seal; the bill should meet firm resistance all the way around.</p>
"""
}

# Update compile_master_seeder.py with these additions
with open("HelmetsanWeb/scripts/compile_master_seeder.py") as f:
    text = f.read()

# Merge additional into enrichments
for k, v in additional.items():
    pattern = f'"{k}": """'
    if pattern in text:
        text = text.replace(pattern, pattern + v)

with open("HelmetsanWeb/scripts/compile_master_seeder.py", "w") as f:
    f.write(text)

print("Enrichments expanded successfully.")
