last3 = {
    "how-helmet-ventilation-works-channel-routing": """
<h2>10. The Aerodynamic Penalty of Top Vents: Drag and Range</h2>
<p>On high-performance electric motorcycles (such as Zero, Energica, and LiveWire) or lightweight sportbikes, aerodynamic drag directly impacts vehicle range and top speed. In wind tunnel tests conducted at 80 mph, deploying aggressive crown scoop vents can increase total helmet drag coefficient (Cd) by up to 8%. For long-distance touring riders who value battery or fuel efficiency, running vents in half-open detent positions balances thermal cooling with streamlined boundary layer airflow.</p>
""",
    "adventure-dual-sport-helmets-buyers-guide": """
<h2>10. Goggle Strap Retention and Neck Roll Ergonomics</h2>
<p>When switching to off-road goggles on an adventure ride, securing the goggle strap is vital. Elite dual-sport helmets incorporate molded rear strap guide channels and rubberized grip pads on the back of the shell. These prevent the elastic goggle band from sliding upward or snapping off your helmet when navigating rocky terrain, whoops, or steep forest descents.</p>

<p>Additionally, adventure helmets feature specialized collarbone cutaways along the bottom neck roll. Pioneered by AGV and Klim, these curved bottom edges provide clearance for the clavicles during sudden head movement, preventing painful collarbone impact injuries during unexpected trail get-offs.</p>
""",
    "integrated-bluetooth-comm-systems-vs-aftermarket": """
<h2>10. Noise-Canceling Microphone Biomechanics: Boom vs Wired</h2>
<p>The clarity of your motorcycle voice communications depends heavily on microphone positioning and capsule acoustics. Full-face helmets utilize compact wired button microphones placed inside the chin bar directly in front of the mouth, shielded by dense acoustic foam windsocks.</p>

<p>Modular and open-face helmets require semi-rigid boom microphones with dual-capsule directional noise canceling. Digital signal processors (DSP) compare sound waves arriving at the front capsule (your voice) with sound waves arriving at the rear capsule (ambient wind turbulence), subtracting up to 30 dB of background road noise so your riding companions hear only crisp, crystal-clear speech even with your visor cracked open at 65 mph.</p>
"""
}

with open("HelmetsanWeb/scripts/compile_master_seeder.py") as f:
    text = f.read()

for k, v in last3.items():
    pattern = f'"{k}": """'
    if pattern in text:
        text = text.replace(pattern, pattern + v)

with open("HelmetsanWeb/scripts/compile_master_seeder.py", "w") as f:
    f.write(text)

print("Last 3 expanded.")
