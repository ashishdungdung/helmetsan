<?php
// create_helmets_seed.php — Helmetsan helmet seed data generator
//
// Usage:
//   php create_helmets_seed.php                           # Output JSON to stdout
//   php create_helmets_seed.php --output=path/to/file.json
//   php create_helmets_seed.php --validate                # Validate only, no output
//   php create_helmets_seed.php --stats                   # Show stats after output
//   php create_helmets_seed.php --split-dir=helmets/      # Write per-helmet JSON files
//   php create_helmets_seed.php --help

// ── CLI Arguments ──────────────────────────────────────────────────────
$opts = getopt('', ['output:', 'validate', 'stats', 'split-dir:', 'help']);

if (isset($opts['help'])) {
    fwrite(STDERR, <<<HELP
Helmetsan Seed Generator

Options:
  --output=<file>      Write JSON to file instead of stdout
  --validate           Validate data (check IDs, descriptions) then exit
  --stats              Print summary stats to stderr after generation
  --split-dir=<dir>    Also write individual JSON files to <dir>/ for ingestPath
  --help               Show this help

Examples:
  php create_helmets_seed.php > helmets_seed.json
  php create_helmets_seed.php --output=helmetsan-core/seed-data/helmets_seed.json --stats
  php create_helmets_seed.php --validate
  php create_helmets_seed.php --split-dir=helmetsan-core/seed-data/helmets/ --stats

HELP
    );
    exit(0);
}

$brands = [
    'Shoei' => [
        'RF-1400' => ['type' => 'Full Face', 'price' => 579.99, 'cert' => ['DOT', 'Snell M2020'], 'shape' => 'Intermediate Oval', 'weight' => 1640, 'mat' => 'Fiberglass', 'desc' => 'The Shoei RF-1400 is the latest in the RF series, focused on lightweight performance and quietness.'],
        'Neotec 3' => ['type' => 'Modular', 'price' => 899.99, 'cert' => ['DOT', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1700, 'mat' => 'AIM', 'desc' => 'Shoei Neotec 3 offers premium modular convenience with ECE 22.06 safety.'],
        'X-Fifteen' => ['type' => 'Track / Race', 'price' => 899.99, 'cert' => ['DOT', 'Snell M2020', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1450, 'mat' => 'AIM+', 'desc' => 'The X-Fifteen is a true race-bred helmet, developed in MotoGP.'],
        'GT-Air 3' => ['type' => 'Touring', 'price' => 699.99, 'cert' => ['DOT', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1550, 'mat' => 'AIM', 'desc' => 'The GT-Air 3 is the ultimate sport-touring helmet with sun visor.'],
        'Hornet X2' => ['type' => 'Adventure / Dual Sport', 'price' => 649.99, 'cert' => ['DOT', 'Snell M2015'], 'shape' => 'Intermediate Oval', 'weight' => 1750, 'mat' => 'AIM+', 'desc' => 'Designed for on-and-off road, the Hornet X2 balances aerodynamics and ventilation.'],
        'J-Cruise II' => ['type' => 'Open Face', 'price' => 549.99, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1350, 'mat' => 'AIM', 'desc' => 'Premium open face touring helmet with extended internal sun shield.'],
        'Glamster' => ['type' => 'Full Face', 'price' => 599.99, 'cert' => ['DOT', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1250, 'mat' => 'AIM', 'desc' => 'Neo-retro style meets modern safety and comfort.'],
        'VFX-EVO' => ['type' => 'Dirt / MX', 'price' => 539.99, 'cert' => ['DOT', 'Snell M2020'], 'shape' => 'Intermediate Oval', 'weight' => 1420, 'mat' => 'AIM+', 'desc' => 'Top-tier motocross helmet with M.E.D.S. rotational impact system.'],
    ],
    'Arai' => [
        'Corsair-X' => ['type' => 'Track / Race', 'price' => 979.95, 'cert' => ['DOT', 'Snell M2020'], 'shape' => 'Intermediate Oval', 'weight' => 1590, 'mat' => 'PB-SNC2', 'desc' => 'The Corsair-X is Arai\'s flagship race helmet with adjustable airflow.'],
        'Contour-X' => ['type' => 'Full Face', 'price' => 749.95, 'cert' => ['DOT', 'Snell M2020'], 'shape' => 'Intermediate Oval', 'weight' => 1550, 'mat' => 'PB-cLc2', 'desc' => 'Designed for high-speed touring stability and comfort.'],
        'Regent-X' => ['type' => 'Full Face', 'price' => 579.95, 'cert' => ['DOT', 'Snell M2020'], 'shape' => 'Intermediate Oval', 'weight' => 1500, 'mat' => 'PB-cLc1', 'desc' => 'Excellent entry into the premium Arai lineup, focusing on comfort.'],
        'Signet-X' => ['type' => 'Full Face', 'price' => 699.95, 'cert' => ['DOT', 'Snell M2020'], 'shape' => 'Long Oval', 'weight' => 1580, 'mat' => 'PB-cLc', 'desc' => 'Specifically built for riders with a longer head shape.'],
        'Quantum-X' => ['type' => 'Full Face', 'price' => 699.95, 'cert' => ['DOT', 'Snell M2020'], 'shape' => 'Round Oval', 'weight' => 1590, 'mat' => 'PB-cLc', 'desc' => 'Specifically built for riders with a rounder head shape.'],
        'XD-5' => ['type' => 'Adventure / Dual Sport', 'price' => 839.95, 'cert' => ['DOT', 'Snell M2020'], 'shape' => 'Intermediate Oval', 'weight' => 1680, 'mat' => 'PB-cLc2', 'desc' => 'The ultimate adventure helmet, successor to the legendary XD-4.'],
        'Classic-V' => ['type' => 'Open Face', 'price' => 489.95, 'cert' => ['DOT', 'Snell M2020'], 'shape' => 'Intermediate Oval', 'weight' => 1200, 'mat' => 'PB-cLc', 'desc' => 'Heritage styling with modern Arai protection.'],
        'VX-Pro4' => ['type' => 'Dirt / MX', 'price' => 749.95, 'cert' => ['DOT', 'Snell M2020'], 'shape' => 'Intermediate Oval', 'weight' => 1430, 'mat' => 'cLc', 'desc' => 'Professional grade motocross protection.'],
    ],
    'AGV' => [
        'Pista GP RR' => ['type' => 'Track / Race', 'price' => 1449.95, 'cert' => ['DOT', 'ECE 22.06', 'FIM'], 'shape' => 'Intermediate Oval', 'weight' => 1450, 'mat' => 'Carbon Fiber', 'desc' => 'Exact replica of the helmet worn by MotoGP professionals.'],
        'K6 S' => ['type' => 'Full Face', 'price' => 549.95, 'cert' => ['DOT', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1255, 'mat' => 'Carbon-Aramid', 'desc' => 'The lightest road helmet in the world per AGV.'],
        'K1 S' => ['type' => 'Full Face', 'price' => 219.95, 'cert' => ['DOT', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1500, 'mat' => 'Thermoplastic', 'desc' => 'Aerodynamic shape and racing ventilation for everyday riders.'],
        'Sportmodular' => ['type' => 'Modular', 'price' => 849.95, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Intermediate Oval', 'weight' => 1295, 'mat' => 'Carbon Fiber', 'desc' => 'Modular convenience with full carbon fiber construction.'],
        'AX9' => ['type' => 'Adventure / Dual Sport', 'price' => 649.95, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Intermediate Oval', 'weight' => 1445, 'mat' => 'Carbon-Aramid-Glass', 'desc' => 'Lightweight, comfortable, and adaptable for 4 different configurations.'],
        'X3000' => ['type' => 'Full Face', 'price' => 449.95, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Intermediate Oval', 'weight' => 1370, 'mat' => 'Fibreglass', 'desc' => 'Inspired by the historic models worn by Agostini.'],
        'K3' => ['type' => 'Full Face', 'price' => 269.95, 'cert' => ['DOT', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1550, 'mat' => 'Thermoplastic', 'desc' => 'Versatile road helmet with internal sun visor.'],
        'Tourmodular' => ['type' => 'Modular', 'price' => 649.95, 'cert' => ['DOT', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1620, 'mat' => 'Carbon-Aramid-Glass', 'desc' => 'Designed for endless journeys with maximum safety.'],
    ],
    'HJC' => [
        'RPHA 1N' => ['type' => 'Track / Race', 'price' => 699.99, 'cert' => ['DOT', 'ECE 22.06', 'FIM'], 'shape' => 'Intermediate Oval', 'weight' => 1500, 'mat' => 'PIM+', 'desc' => 'FIM homologated racing helmet available to the public.'],
        'RPHA 11 Pro' => ['type' => 'Track / Race', 'price' => 399.99, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Intermediate Oval', 'weight' => 1420, 'mat' => 'PIM+', 'desc' => 'Known for excellent airflow and lightweight construction.'],
        'RPHA 71' => ['type' => 'Touring', 'price' => 499.99, 'cert' => ['DOT', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1600, 'mat' => 'PIM Evo', 'desc' => 'Premium sport-touring model with advanced shock resistance.'],
        'RPHA 91' => ['type' => 'Modular', 'price' => 549.99, 'cert' => ['DOT', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1700, 'mat' => 'PIM Evo', 'desc' => 'Functional modular helmet with P/J homologation.'],
        'i10' => ['type' => 'Full Face', 'price' => 169.99, 'cert' => ['DOT', 'Snell M2020'], 'shape' => 'Intermediate Oval', 'weight' => 1650, 'mat' => 'Polycarbonate', 'desc' => 'Snell protection at an entry-level price point.'],
        'F70' => ['type' => 'Full Face', 'price' => 269.99, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1550, 'mat' => 'Fiberglass', 'desc' => 'Progressive shell design with an elegant appearance.'],
        'i90' => ['type' => 'Modular', 'price' => 224.99, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1720, 'mat' => 'Polycarbonate', 'desc' => 'Compact modular helmet with integrated sun shield.'],
        'CS-R3' => ['type' => 'Full Face', 'price' => 139.99, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1600, 'mat' => 'Polycarbonate', 'desc' => 'Budget-friendly street helmet with proven reliability.'],
        'V10' => ['type' => 'Full Face', 'price' => 329.99, 'cert' => ['DOT', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1450, 'mat' => 'Fiberglass', 'desc' => 'Classic look with new-age performance and safety.'],
        'DS-X1' => ['type' => 'Adventure / Dual Sport', 'price' => 189.99, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1650, 'mat' => 'Polycarbonate', 'desc' => 'Great value dual-sport helmet for street and trail.'],
    ],
    'Bell' => [
        'Race Star Flex DLX' => ['type' => 'Track / Race', 'price' => 819.95, 'cert' => ['DOT', 'Snell M2020'], 'shape' => 'Intermediate Oval', 'weight' => 1500, 'mat' => 'Carbon Fiber', 'desc' => 'Dedicated racer with 3k carbon shell and Flex energy management.'],
        'Star DLX MIPS' => ['type' => 'Full Face', 'price' => 569.95, 'cert' => ['DOT', 'Snell M2020', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1600, 'mat' => 'Tri-Matrix', 'desc' => 'Street optimized version of the Star with MIPS protection.'],
        'Qualifier DLX MIPS' => ['type' => 'Full Face', 'price' => 289.95, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Intermediate Oval', 'weight' => 1550, 'mat' => 'Polycarbonate', 'desc' => 'Includes proactive transition shield and MIPS.'],
        'Moto-9S Flex' => ['type' => 'Dirt / MX', 'price' => 599.95, 'cert' => ['DOT', 'Snell M2020'], 'shape' => 'Intermediate Oval', 'weight' => 1450, 'mat' => 'Composite', 'desc' => 'Pro-level protection for the dirt track.'],
        'MX-9 Adventure MIPS' => ['type' => 'Adventure / Dual Sport', 'price' => 239.95, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Intermediate Oval', 'weight' => 1690, 'mat' => 'Polycarbonate', 'desc' => 'Highly versatile helmet for exploring the unknown.'],
        'Custom 500' => ['type' => 'Open Face', 'price' => 139.95, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Intermediate Oval', 'weight' => 1100, 'mat' => 'Fiberglass', 'desc' => 'The original helmet design, updated with modern technology.'],
        'Bullitt' => ['type' => 'Full Face', 'price' => 439.95, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Round Oval', 'weight' => 1470, 'mat' => 'Fiberglass', 'desc' => 'Retro styling with huge eyeport for visibility.'],
        'SRT Modular' => ['type' => 'Modular', 'price' => 389.95, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1760, 'mat' => 'Fiberglass', 'desc' => 'Flip-up convenience with high-grade fiberglass shell.'],
        'Broozer' => ['type' => 'Modular', 'price' => 289.95, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Intermediate Oval', 'weight' => 1350, 'mat' => 'Polycarbonate', 'desc' => 'Dual-certified modular with removable chin bar.'],
    ],
    'Schuberth' => [
        'C5' => ['type' => 'Modular', 'price' => 749.00, 'cert' => ['DOT', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1640, 'mat' => 'Fiberglass', 'desc' => 'The masterpiece of flip-up helmets with P/J homologation.'],
        'E2' => ['type' => 'Adventure / Dual Sport', 'price' => 799.00, 'cert' => ['DOT', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1680, 'mat' => 'Refined Fiberglass', 'desc' => 'Evolution of the E1, bridging adventure and touring.'],
        'S3' => ['type' => 'Full Face', 'price' => 649.00, 'cert' => ['DOT', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1590, 'mat' => 'Direct Fiber', 'desc' => 'Sport touring with acoustic optimization.'],
        'M1 Pro' => ['type' => 'Open Face', 'price' => 499.00, 'cert' => ['DOT', 'ECE'], 'shape' => 'Intermediate Oval', 'weight' => 1395, 'mat' => 'Fiberglass', 'desc' => 'Perfect for naked bikes and cruisers with pre-installed comms.'],
    ],
    'Icon' => [
        'Airflite' => ['type' => 'Full Face', 'price' => 290.00, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Long Oval', 'weight' => 1690, 'mat' => 'Polycarbonate', 'desc' => 'Aggressive styling with massive ventilation.'],
        'Airform' => ['type' => 'Full Face', 'price' => 225.00, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Intermediate Oval', 'weight' => 1650, 'mat' => 'Polycarbonate', 'desc' => 'Combines features from Airframe and Airflite for versatile use.'],
        'Variant Pro' => ['type' => 'Adventure / Dual Sport', 'price' => 350.00, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Intermediate Oval', 'weight' => 1620, 'mat' => 'Composite', 'desc' => 'Wind-tunnel tested visor for stability at speed.'],
        'Domain' => ['type' => 'Full Face', 'price' => 350.00, 'cert' => ['DOT', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1550, 'mat' => 'FRP', 'desc' => 'Aggressive street helmet with integrated FRP chin bar and ICON styling.'],
        'Elsinore' => ['type' => 'Dirt / MX', 'price' => 275.00, 'cert' => ['DOT', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1400, 'mat' => 'Composite', 'desc' => 'Vintage MX styling meets modern composite construction and safety.'],
    ],
    'Scorpion' => [
        'EXO-R420' => ['type' => 'Full Face', 'price' => 159.95, 'cert' => ['DOT', 'Snell M2020'], 'shape' => 'Intermediate Oval', 'weight' => 1620, 'mat' => 'Polycarbonate', 'desc' => 'Incredible value — Snell M2020 and DOT certified at an entry-level price.'],
        'EXO-R1 Air' => ['type' => 'Track / Race', 'price' => 429.95, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Intermediate Oval', 'weight' => 1380, 'mat' => 'TCT-Ultra', 'desc' => 'Race-grade helmet with ultra-lightweight TCT composite shell.'],
        'EXO-AT950' => ['type' => 'Adventure / Dual Sport', 'price' => 269.95, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1780, 'mat' => 'Polycarbonate', 'desc' => 'Modular adventure helmet with removable peak and electric shield option.'],
        'EXO-1400 Air' => ['type' => 'Full Face', 'price' => 399.95, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Intermediate Oval', 'weight' => 1450, 'mat' => 'TCT-U', 'desc' => 'Premium full face with TCT-U shell and KwikWick III liner.'],
        'Covert FX' => ['type' => 'Full Face', 'price' => 249.95, 'cert' => ['DOT', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1400, 'mat' => 'Composite', 'desc' => 'Converts from full face to open face with removable rear and front covers.'],
        'Belfast' => ['type' => 'Open Face', 'price' => 199.95, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1100, 'mat' => 'Fiberglass', 'desc' => 'Retro open face with internal sun visor and fiberglass shell.'],
    ],
    'Shark' => [
        'Race-R Pro GP' => ['type' => 'Track / Race', 'price' => 1099.99, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Intermediate Oval', 'weight' => 1450, 'mat' => 'Carbon-Aramid', 'desc' => 'MotoGP-grade race helmet with carbon-aramid shell and double-D ring.'],
        'Spartan GT' => ['type' => 'Full Face', 'price' => 479.99, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Intermediate Oval', 'weight' => 1450, 'mat' => 'Fiberglass', 'desc' => 'Sport-touring with integrated sun visor and autoseal system.'],
    ],
    'Nolan' => [
        'N100-5' => ['type' => 'Modular', 'price' => 449.95, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Intermediate Oval', 'weight' => 1700, 'mat' => 'Lexan', 'desc' => 'Italian modular with N-Com communication system preinstalled.'],
        'N80-8' => ['type' => 'Full Face', 'price' => 299.95, 'cert' => ['DOT', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1500, 'mat' => 'Lexan', 'desc' => 'Sport-touring full face with ultra-wide visor and pinlock included.'],
    ],
    'LS2' => [
        'Stream 2' => ['type' => 'Full Face', 'price' => 129.98, 'cert' => ['DOT', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1550, 'mat' => 'KPA', 'desc' => 'Best-selling budget full face with KPA shell technology.'],
        'Advant' => ['type' => 'Modular', 'price' => 349.98, 'cert' => ['DOT', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1650, 'mat' => 'KPA', 'desc' => 'P/J-homologated modular with 180° flip-up chin bar.'],
        'Explorer' => ['type' => 'Adventure / Dual Sport', 'price' => 249.98, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Long Oval', 'weight' => 1550, 'mat' => 'HPFC', 'desc' => 'Value adventure helmet with peak visor and long oval fit.'],
    ],
    'Fox Racing' => [
        'V3 RS' => ['type' => 'Dirt / MX', 'price' => 599.95, 'cert' => ['DOT', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1260, 'mat' => 'Carbon-Composite', 'desc' => 'Top-of-the-line MX helmet with MIPS and dual-density EPS.'],
        'V1' => ['type' => 'Dirt / MX', 'price' => 219.95, 'cert' => ['DOT', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1350, 'mat' => 'Polycarbonate', 'desc' => 'Entry-level MX helmet from Fox with magnetic visor release.'],
        'V2' => ['type' => 'Dirt / MX', 'price' => 299.95, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Intermediate Oval', 'weight' => 1315, 'mat' => 'Fiberglass', 'desc' => 'Mid-range MX with MIPS brain protection system and fiberglass shell.'],
    ],
    'Alpinestars' => [
        'Supertech M10' => ['type' => 'Dirt / MX', 'price' => 649.95, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Intermediate Oval', 'weight' => 1240, 'mat' => 'Carbon', 'desc' => 'Ultra-lightweight carbon MX helmet with A-Head fitment system.'],
        'SM5' => ['type' => 'Dirt / MX', 'price' => 289.95, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Intermediate Oval', 'weight' => 1350, 'mat' => 'Polymer', 'desc' => 'Affordable Alpinestars MX helmet with multi-density EPS.'],
    ],
    'Klim' => [
        'Krios Pro' => ['type' => 'Adventure / Dual Sport', 'price' => 749.99, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Intermediate Oval', 'weight' => 1300, 'mat' => 'Carbon Fiber', 'desc' => 'Premium carbon ADV helmet with Transitions photochromic shield.'],
        'F5 Koroyd' => ['type' => 'Dirt / MX', 'price' => 649.99, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Intermediate Oval', 'weight' => 1100, 'mat' => 'Carbon Fiber', 'desc' => 'Revolutionary Koroyd liner crumple zone for superior impact protection.'],
    ],
    'Fly Racing' => [
        'Formula Carbon' => ['type' => 'Dirt / MX', 'price' => 689.95, 'cert' => ['DOT', 'ECE 22.05', 'Snell M2020'], 'shape' => 'Intermediate Oval', 'weight' => 1290, 'mat' => 'Carbon Fiber', 'desc' => 'Triple-certified carbon MX helmet with Conehead EPS technology.'],
        'Kinetic' => ['type' => 'Dirt / MX', 'price' => 129.95, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1450, 'mat' => 'Polymer', 'desc' => 'Budget-friendly entry MX helmet with multiple shell sizes.'],
    ],
    '6D' => [
        'ATR-2' => ['type' => 'Dirt / MX', 'price' => 779.95, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Intermediate Oval', 'weight' => 1480, 'mat' => 'Composite', 'desc' => 'Patented ODS 2.0 suspension system reduces rotational and angular acceleration.'],
        'ATS-1R' => ['type' => 'Track / Race', 'price' => 749.95, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Intermediate Oval', 'weight' => 1560, 'mat' => 'Carbon Fiber', 'desc' => 'Carbon road race helmet with ODS technology for multi-directional impact.'],
    ],
    'Biltwell' => [
        'Gringo S' => ['type' => 'Full Face', 'price' => 249.95, 'cert' => ['DOT', 'ECE 22.06'], 'shape' => 'Round Oval', 'weight' => 1500, 'mat' => 'ABS', 'desc' => 'Classic retro styling with modern DOT/ECE certification and flat shield.'],
        'Lane Splitter' => ['type' => 'Full Face', 'price' => 299.95, 'cert' => ['DOT', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1550, 'mat' => 'ABS', 'desc' => 'Modern take on vintage full-face design with quick-release chin strap.'],
        'Bonanza' => ['type' => 'Open Face', 'price' => 149.95, 'cert' => ['DOT'], 'shape' => 'Round Oval', 'weight' => 1000, 'mat' => 'ABS', 'desc' => 'Ultra-minimalist open face helmet with hand-painted graphics options.'],
    ],
    'Sena' => [
        'Momentum INC Pro' => ['type' => 'Modular', 'price' => 549.00, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1750, 'mat' => 'Fiberglass', 'desc' => 'World\'s first Smart Helmet with built-in Bluetooth and Mesh intercom.'],
        'Outrush R' => ['type' => 'Modular', 'price' => 249.00, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1680, 'mat' => 'Polycarbonate', 'desc' => 'Budget-friendly modular with built-in Sena Bluetooth 5.0.'],
        'Stryker' => ['type' => 'Full Face', 'price' => 379.00, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1550, 'mat' => 'Fiberglass', 'desc' => 'Full-face with integrated mesh Bluetooth and noise-canceling microphone.'],
    ],
    'X-Lite' => [
        'X-803 RS Ultra Carbon' => ['type' => 'Track / Race', 'price' => 849.99, 'cert' => ['ECE 22.06', 'FIM'], 'shape' => 'Intermediate Oval', 'weight' => 1350, 'mat' => 'Carbon Fiber', 'desc' => 'MotoGP-level carbon race helmet with racing spoiler and emergency cheek pads.'],
        'X-1005 Ultra Carbon' => ['type' => 'Modular', 'price' => 699.99, 'cert' => ['ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1480, 'mat' => 'Carbon Fiber', 'desc' => 'Premium carbon modular with P/J homologation and N-Com ready.'],
        'X-552 Ultra Carbon' => ['type' => 'Adventure / Dual Sport', 'price' => 749.99, 'cert' => ['ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1500, 'mat' => 'Carbon Fiber', 'desc' => 'Carbon adventure helmet with peak visor and anti-fog system.'],
    ],
    'Nexx' => [
        'X.R3R' => ['type' => 'Track / Race', 'price' => 699.99, 'cert' => ['ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1350, 'mat' => 'X-Matrix Carbon', 'desc' => 'Portuguese-made race helmet with multi-density EPS and titanium hardware.'],
        'X.WED3' => ['type' => 'Adventure / Dual Sport', 'price' => 599.99, 'cert' => ['ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1550, 'mat' => 'X-Matrix', 'desc' => 'Adventure helmet engineered for the most demanding off-road conditions.'],
        'X.Vilitur' => ['type' => 'Modular', 'price' => 549.99, 'cert' => ['ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1620, 'mat' => 'X-Matrix', 'desc' => 'Touring modular featuring 360-degree panoramic vision.'],
        'X.G200' => ['type' => 'Full Face', 'price' => 449.99, 'cert' => ['ECE 22.06'], 'shape' => 'Round Oval', 'weight' => 1300, 'mat' => 'X-Matrix', 'desc' => 'Retro-styled full face with modern composite shell technology.'],
        'SX.100R' => ['type' => 'Full Face', 'price' => 249.99, 'cert' => ['ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1500, 'mat' => 'Polycarbonate', 'desc' => 'Entry-level road helmet with internal sun visor and pinlock-ready shield.'],
    ],
    'Caberg' => [
        'Duke X' => ['type' => 'Modular', 'price' => 449.99, 'cert' => ['ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1600, 'mat' => 'Composite Fibre', 'desc' => 'Italian-designed modular with DVT (Double Visor Tech) and 180° flip-up.'],
        'Horus' => ['type' => 'Full Face', 'price' => 349.99, 'cert' => ['ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1450, 'mat' => 'Thermoplastic', 'desc' => 'Sporty full face with aggressive ventilation and built-in sun visor.'],
        'Levo X' => ['type' => 'Modular', 'price' => 549.99, 'cert' => ['ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1690, 'mat' => 'Carbon Fiber', 'desc' => 'Carbon shell modular with panoramic visor and antibacterial lining.'],
        'Flyon II' => ['type' => 'Open Face', 'price' => 299.99, 'cert' => ['ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1100, 'mat' => 'Carbon Fiber', 'desc' => 'Ultralight carbon open face weighing just 750g for urban riding.'],
    ],
    'Ruroc' => [
        'Atlas 4.0' => ['type' => 'Full Face', 'price' => 449.00, 'cert' => ['DOT', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1550, 'mat' => 'Fiberglass', 'desc' => 'Bold design with Shockwave Bluetooth audio system and magnetic chin vent.'],
        'Atlas 4.0 Carbon' => ['type' => 'Full Face', 'price' => 599.00, 'cert' => ['DOT', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1400, 'mat' => 'Carbon Fiber', 'desc' => 'Premium carbon version with advanced RHEON liner technology.'],
        'Atlas 4.0 Track' => ['type' => 'Track / Race', 'price' => 649.00, 'cert' => ['DOT', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1380, 'mat' => 'Carbon Fiber', 'desc' => 'Track-focused carbon with aerodynamic spoiler and emergency release system.'],
    ],
    'Simpson' => [
        'Outlaw Bandit' => ['type' => 'Full Face', 'price' => 399.95, 'cert' => ['DOT'], 'shape' => 'Round Oval', 'weight' => 1600, 'mat' => 'Fiberglass', 'desc' => 'Iconic retro race-inspired design with wide eyeport and flat shield.'],
        'Ghost Bandit' => ['type' => 'Full Face', 'price' => 449.95, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Round Oval', 'weight' => 1580, 'mat' => 'Composite', 'desc' => 'Ghost-style visor with integrated internal sun shield.'],
        'Mod Bandit' => ['type' => 'Modular', 'price' => 399.95, 'cert' => ['DOT'], 'shape' => 'Round Oval', 'weight' => 1700, 'mat' => 'Fiberglass', 'desc' => 'Modular version of the Bandit with full flip-up chin bar.'],
        'Venom' => ['type' => 'Full Face', 'price' => 579.95, 'cert' => ['DOT', 'Snell M2020'], 'shape' => 'Intermediate Oval', 'weight' => 1500, 'mat' => 'Composite', 'desc' => 'Pro-grade full face with dual Snell/DOT and FMVSS 218 certification.'],
    ],
    'Troy Lee Designs' => [
        'SE5 Carbon' => ['type' => 'Dirt / MX', 'price' => 675.00, 'cert' => ['DOT', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1200, 'mat' => 'Carbon Fiber', 'desc' => 'Premium carbon MX helmet with MIPS-C2 multi-impact protection.'],
        'SE5 Composite' => ['type' => 'Dirt / MX', 'price' => 475.00, 'cert' => ['DOT', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1350, 'mat' => 'Composite', 'desc' => 'Composite MX helmet with MIPS and EPP multi-density liner.'],
        'GP' => ['type' => 'Dirt / MX', 'price' => 199.00, 'cert' => ['DOT', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1400, 'mat' => 'Polycarbonate', 'desc' => 'Entry-level MX helmet with TLD graphics and comfort liner.'],
    ],
    'GMax' => [
        'OF-77' => ['type' => 'Open Face', 'price' => 109.95, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1200, 'mat' => 'Polycarbonate', 'desc' => 'Versatile open face with internal sun visor at an unbeatable price.'],
        'GM-11' => ['type' => 'Dirt / MX', 'price' => 99.95, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1500, 'mat' => 'Polycarbonate', 'desc' => 'Budget-friendly motocross helmet with dual-sport capability.'],
        'AT-21S' => ['type' => 'Adventure / Dual Sport', 'price' => 149.95, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1650, 'mat' => 'Polycarbonate', 'desc' => 'Adventure helmet with integrated sun shield and removable peak.'],
        'FF-49' => ['type' => 'Full Face', 'price' => 89.95, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1550, 'mat' => 'Polycarbonate', 'desc' => 'Entry-level full face with quick-release shield system.'],
        'MD-01S' => ['type' => 'Modular', 'price' => 179.95, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1700, 'mat' => 'Polycarbonate', 'desc' => 'Modular dual-sport with electric shield for cold-weather riding.'],
    ],
    'Z1R' => [
        'Range' => ['type' => 'Adventure / Dual Sport', 'price' => 169.95, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1600, 'mat' => 'Polycarbonate', 'desc' => 'Dual-sport with removable peak, sun visor, and moisture-wicking liner.'],
        'Warrant' => ['type' => 'Full Face', 'price' => 129.95, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1500, 'mat' => 'Polycarbonate', 'desc' => 'Value-focused full face with anti-scratch shield and padded chin strap.'],
        'Solaris' => ['type' => 'Modular', 'price' => 149.95, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1700, 'mat' => 'Polycarbonate', 'desc' => 'Budget modular with drop-down sun visor and speaker pockets.'],
        'Rise' => ['type' => 'Dirt / MX', 'price' => 89.95, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1350, 'mat' => 'Polycarbonate', 'desc' => 'Youth and budget MX helmet with multi-position visor.'],
    ],
    'Speed and Strength' => [
        'SS1600' => ['type' => 'Full Face', 'price' => 149.95, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1600, 'mat' => 'Polycarbonate', 'desc' => 'Sport-styled full face with quick-change shield and drop-down sun visor.'],
        'SS2400' => ['type' => 'Full Face', 'price' => 199.95, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1500, 'mat' => 'Fiberglass', 'desc' => 'Upgraded fiberglass shell with enhanced ventilation and comfort.'],
        'SS700' => ['type' => 'Open Face', 'price' => 99.95, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1100, 'mat' => 'Polycarbonate', 'desc' => 'Open face with internal sun visor for cruiser and urban riding.'],
    ],
    'Sedici' => [
        'Strada 3' => ['type' => 'Full Face', 'price' => 199.99, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Intermediate Oval', 'weight' => 1550, 'mat' => 'Polycarbonate', 'desc' => 'Italian-designed entry-level full face with sun shield and pinlock-ready visor.'],
        'Strada Carbon' => ['type' => 'Full Face', 'price' => 399.99, 'cert' => ['DOT', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1350, 'mat' => 'Carbon Fiber', 'desc' => 'Premium carbon shell from Sedici\'s flagship line.'],
        'Primo' => ['type' => 'Open Face', 'price' => 129.99, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1050, 'mat' => 'Polycarbonate', 'desc' => 'Retro-styled open face with snap-on visor for cafe racer style.'],
        'Sistema II' => ['type' => 'Modular', 'price' => 249.99, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Intermediate Oval', 'weight' => 1700, 'mat' => 'Polycarbonate', 'desc' => 'Touring modular with integrated sun visor and Sena-ready speaker pockets.'],
    ],
    'ILM' => [
        '902BT' => ['type' => 'Modular', 'price' => 109.99, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1700, 'mat' => 'ABS', 'desc' => 'Best-selling budget modular with integrated Bluetooth and FM radio.'],
        '726X' => ['type' => 'Full Face', 'price' => 89.99, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1550, 'mat' => 'ABS', 'desc' => 'Ultra-budget full face with quick-release buckle and anti-fog visor.'],
        '953' => ['type' => 'Full Face', 'price' => 69.99, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1600, 'mat' => 'ABS', 'desc' => 'Entry-level full face for new riders seeking basic DOT protection.'],
    ],
    'Joe Rocket' => [
        'RKT-Prime' => ['type' => 'Full Face', 'price' => 119.99, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1600, 'mat' => 'Polycarbonate', 'desc' => 'Affordable full face with anti-fog shield and removable liner.'],
    ],
    // Additional models for existing brands
    'Shoei Extra' => [
        'RF-1200' => ['type' => 'Full Face', 'price' => 499.99, 'cert' => ['DOT', 'Snell M2015'], 'shape' => 'Intermediate Oval', 'weight' => 1520, 'mat' => 'AIM', 'desc' => 'Predecessor to the RF-1400, still popular for its proven performance.', '_real_brand' => 'Shoei'],
        'Neotec II' => ['type' => 'Modular', 'price' => 829.99, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Intermediate Oval', 'weight' => 1760, 'mat' => 'AIM', 'desc' => 'Previous-gen modular with integrated SRL2 Sena comms ready.', '_real_brand' => 'Shoei'],
    ],
    'Arai Extra' => [
        'DT-X' => ['type' => 'Full Face', 'price' => 579.95, 'cert' => ['DOT', 'Snell M2020'], 'shape' => 'Intermediate Oval', 'weight' => 1540, 'mat' => 'PB-cLc', 'desc' => 'Versatile all-road helmet bridging sport and touring capabilities.', '_real_brand' => 'Arai'],
        'Defiant-X' => ['type' => 'Full Face', 'price' => 699.95, 'cert' => ['DOT', 'Snell M2020'], 'shape' => 'Intermediate Oval', 'weight' => 1570, 'mat' => 'PB-cLc2', 'desc' => 'Features the unique Pro Shade system for ultimate versatility.', '_real_brand' => 'Arai'],
    ],
    'AGV Extra' => [
        'K5 S' => ['type' => 'Full Face', 'price' => 399.95, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Intermediate Oval', 'weight' => 1460, 'mat' => 'Fiberglass', 'desc' => 'Mid-range sport-touring with internal sun visor and wide field of view.', '_real_brand' => 'AGV'],
        'AX-8 EVO' => ['type' => 'Dirt / MX', 'price' => 479.95, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Intermediate Oval', 'weight' => 1200, 'mat' => 'Carbon-Aramid', 'desc' => 'Ultra-light off-road helmet designed in collaboration with MotoGP riders.', '_real_brand' => 'AGV'],
    ],
    'Bell Extra' => [
        'Eliminator' => ['type' => 'Full Face', 'price' => 349.95, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Intermediate Oval', 'weight' => 1550, 'mat' => 'Composite', 'desc' => 'Flat-track inspired cruiser helmet with ProVision anti-fog shield.', '_real_brand' => 'Bell'],
        'Pit Boss' => ['type' => 'Half', 'price' => 179.95, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 950, 'mat' => 'Tri-Matrix', 'desc' => 'Premium half-helmet with drop-down sun shield.', '_real_brand' => 'Bell'],
        'Rogue' => ['type' => 'Half', 'price' => 249.95, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1200, 'mat' => 'Fiberglass', 'desc' => 'Unique half-helmet with detachable muzzle for aggressive styling.', '_real_brand' => 'Bell'],
        'Mag-9' => ['type' => 'Open Face', 'price' => 169.95, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1300, 'mat' => 'Polycarbonate', 'desc' => 'Versatile sena-ready open face with internal sun shield.', '_real_brand' => 'Bell'],
    ],
    'HJC Extra' => [
        'i30' => ['type' => 'Open Face', 'price' => 129.99, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 1200, 'mat' => 'Polycarbonate', 'desc' => 'Modern open face with wide-eye port and internal sun visor.', '_real_brand' => 'HJC'],
        'CL-17' => ['type' => 'Full Face', 'price' => 159.99, 'cert' => ['DOT', 'Snell M2015'], 'shape' => 'Intermediate Oval', 'weight' => 1630, 'mat' => 'Polycarbonate', 'desc' => 'Battle-proven full face with ACS ventilation and Snell certification.', '_real_brand' => 'HJC'],
        'IS-Cruiser' => ['type' => 'Half', 'price' => 109.99, 'cert' => ['DOT'], 'shape' => 'Intermediate Oval', 'weight' => 950, 'mat' => 'Polycarbonate', 'desc' => 'Lightweight half-helmet with sun shield for cruiser riding.', '_real_brand' => 'HJC'],
    ],
    'Scorpion Extra' => [
        'EXO-GT930' => ['type' => 'Modular', 'price' => 349.95, 'cert' => ['DOT', 'ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1700, 'mat' => 'Fiberglass', 'desc' => 'Transformer modular with optional chin-guard removal for open-face mode.', '_real_brand' => 'Scorpion'],
        'EXO-Combat II' => ['type' => 'Full Face', 'price' => 299.95, 'cert' => ['ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1300, 'mat' => 'Composite', 'desc' => 'Urban streetfighter helmet with removable front mask and goggle strap.', '_real_brand' => 'Scorpion'],
    ],
    'Shark Extra' => [
        'Skwal i3' => ['type' => 'Full Face', 'price' => 349.99, 'cert' => ['ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1500, 'mat' => 'Thermoplastic', 'desc' => 'Integrated LED lighting system for enhanced visibility on the road.', '_real_brand' => 'Shark'],
        'D-Skwal 3' => ['type' => 'Full Face', 'price' => 249.99, 'cert' => ['ECE 22.06'], 'shape' => 'Intermediate Oval', 'weight' => 1450, 'mat' => 'Thermoplastic', 'desc' => 'Sporty entry-level with aggressive design and anti-fog visor.', '_real_brand' => 'Shark'],
        'Evo-One 2' => ['type' => 'Modular', 'price' => 549.99, 'cert' => ['DOT', 'ECE 22.05'], 'shape' => 'Intermediate Oval', 'weight' => 1580, 'mat' => 'Composite', 'desc' => 'Full 180° flip modular — chin bar rotates completely behind the helmet.', '_real_brand' => 'Shark'],
    ],
];

$output = [];
$colorOptions = [
    'Matte Black', 'Gloss White', 'Red', 'Blue', 'Graphic X', 'Graphic Y', 'Carbon', 'Hi-Viz Yellow', 'Silver', 'Grey'
];
$sizeOptions = ['XS', 'S', 'M', 'L', 'XL', '2XL'];

foreach ($brands as $brandName => $models) {
    foreach ($models as $modelName => $specs) {
        // Handle "Extra" entries — use real brand name if specified
        $actualBrand = isset($specs['_real_brand']) ? $specs['_real_brand'] : $brandName;
        // Remove "Extra" suffix from brand key to avoid polluting IDs
        $actualBrand = str_replace(' Extra', '', $actualBrand);
        
        $modelId = strtolower(str_replace([' ', '-', '.', '/'], '_', $actualBrand . '_' . $modelName));
        
        // Generate Variants (Children)
        $variants = [];
        $selectedColors = array_rand(array_flip($colorOptions), rand(3, 8)); // Random 3-8 colors
        if (!is_array($selectedColors)) $selectedColors = [$selectedColors];
        
        foreach ($selectedColors as $color) {
            $variantPriceUsd = $specs['price'];
            if (strpos($color, 'Graphic') !== false) $variantPriceUsd += 50;
            if ($color === 'Carbon' && strpos(strtolower($specs['mat']), 'carbon') === false) continue;
            if ($color === 'Carbon') $variantPriceUsd += 100;

            // Multi-currency calculation (Approximate)
            $priceEur = round($variantPriceUsd * 0.92, 2);
            $priceGbp = round($variantPriceUsd * 0.79, 2);
            
            $variantId = $modelId . '_' . strtolower(str_replace(' ', '_', $color));
            
            $variants[] = [
                'id' => $variantId,
                'title' => $actualBrand . ' ' . $modelName . ' ' . $color,
                'type' => 'variant',
                'parent_id' => $modelId,
                'color' => $color,
                'mpn' => strtoupper(substr($actualBrand, 0, 3)) . '-' . rand(10000, 99999),
                'price' => [
                    'usd' => $variantPriceUsd,
                    'eur' => $priceEur,
                    'gbp' => $priceGbp
                ],
                // VARIANTS: We intentionally OMIT shared specs (material, weight, certs, shape)
                // to test the Inheritance Model.
                // We only include attributes that differ (e.g. specific weight for Carbon).
                'specs' => [], 
                'attributes' => [
                    'sizes' => $sizeOptions, // Available sizes for this color
                    'color' => $color
                ],
                'images' => [], // Placeholders
                'stock_status' => 'instock'
            ];
            
            // Override weight/material for Carbon variants
            if ($color === 'Carbon') {
                $variants[count($variants)-1]['specs']['material'] = 'Carbon Fiber';
                $variants[count($variants)-1]['specs']['weight_g'] = $specs['weight'] - 100; // Lighter
            }
        }
        
        // Ensure at least one variant
        if (empty($variants)) {
             $variants[] = [
                'id' => $modelId . '_matte_black',
                'title' => $actualBrand . ' ' . $modelName . ' Matte Black',
                'type' => 'variant',
                'parent_id' => $modelId,
                'color' => 'Matte Black',
                'mpn' => strtoupper(substr($actualBrand, 0, 3)) . '-00001',
                'price' => ['usd' => $specs['price'], 'eur' => round($specs['price']*0.92, 2), 'gbp' => round($specs['price']*0.79, 2)],
                'specs' => [], // Inherit from parent
                'attributes' => ['sizes' => $sizeOptions, 'color' => 'Matte Black'],
                'images' => [],
                'stock_status' => 'instock'
             ];
        }

        // Parent Item
        $item = [
            'id' => $modelId,
            'title' => $actualBrand . ' ' . $modelName,
            'brand' => $actualBrand,
            'type' => $specs['type'], // Used for taxonomy
            'helmet_family' => $modelName,
            'price' => [
                'current' => $specs['price'], // Base price for display
                'currency' => 'USD'
            ],
            'specs' => [
                'material' => $specs['mat'],
                'weight_g' => $specs['weight'],
                'certifications' => $specs['cert'],
                'warranty_years' => 5,
                'strap_type' => (strpos($specs['type'], 'Touring') !== false || strpos($specs['type'], 'Modular') !== false) ? 'Micrometric' : 'Double D-Ring',
            ],
            'head_shape' => $specs['shape'],
            'features_data' => [
                'visor' => ['Pinlock Ready', 'Anti-Scratch', 'UV Protection', 'Quick Release System'],
                'liner' => ['Removable', 'Washable', 'Antibacterial', 'Emergency Release System (EQRS)']
            ],
            'technical_analysis' => "The **{$actualBrand} {$modelName}** represents a significant step forward in {$specs['type']} helmet design.\n\n### Shell Construction\nConstructed using {$actualBrand}'s proprietary {$specs['mat']} technology, this helmet achieves a remarkable balance of strength and weight ({$specs['weight']}g). The shell is designed to disburse impact energy efficiently while minimizing rotational forces.\n\n### Ventilation\nThe aero-tuned ventilation system features multiple intake and exhaust ports, ensuring optimal airflow even at low speeds. This makes it particularly suitable for both track days and long-distance touring.\n\n### Interior\nThe interior liner is fully removable, washable, and moisture-wicking. It is designed to accommodate eyewear and features emergency quick-release cheek pads.",
            'key_specs_json' => [
                'Shell Construction' => $specs['mat'],
                'Weight' => "{$specs['weight']}g (+/- 50g)",
                'Certifications' => implode(', ', $specs['cert']),
                'Ventilation' => 'Multi-port Flow System',
                'Buckle' => 'Double D-Ring'
            ],
            'compatible_accessories_json' => ['AC-001', 'AC-002', 'AC-003'], // Mock IDs
            'product_details' => [
                'description' => isset($specs['desc']) ? $specs['desc'] : "The {$actualBrand} {$modelName} offers premium performance for {$specs['type']} riders."
            ],
            'features' => ['Removable Liner', 'Speaker Pockets', 'Ventilation System', 'Anti-Fog Ready'],
            'affiliate' => [
                'amazon_asin' => 'B00FAKE' . rand(1000, 9999)
            ],
            'children' => $variants // Storing full child objects here for IngestionService recursive processing
        ];
        
        $output[] = $item;
    }
}

// ── Validation ─────────────────────────────────────────────────────────
$parentCount = 0;
$variantCount = 0;
$brandSet = [];
$typeSet = [];
$idMap = [];
$missingDesc = [];
$duplicateIds = [];

foreach ($output as $item) {
    $parentCount++;
    $brandSet[$item['brand']] = true;
    $type = $item['type'] ?? 'Unknown';
    $typeSet[$type] = ($typeSet[$type] ?? 0) + 1;

    // Check for duplicate IDs
    if (isset($idMap[$item['id']])) {
        $duplicateIds[] = $item['id'];
    }
    $idMap[$item['id']] = true;

    // Check descriptions
    $desc = $item['product_details']['description'] ?? '';
    if (empty($desc) || strpos($desc, 'offers premium performance') !== false) {
        $missingDesc[] = $item['id'];
    }

    foreach ($item['children'] ?? [] as $child) {
        $variantCount++;
        if (isset($idMap[$child['id']])) {
            $duplicateIds[] = $child['id'];
        }
        $idMap[$child['id']] = true;
    }
}

$totalPosts = $parentCount + $variantCount;

if (isset($opts['validate'])) {
    fwrite(STDERR, "\n=== Seed Validation ===\n");
    fwrite(STDERR, "Parent models:  {$parentCount}\n");
    fwrite(STDERR, "Variants:       {$variantCount}\n");
    fwrite(STDERR, "Total posts:    {$totalPosts}\n");
    fwrite(STDERR, "Brands:         " . count($brandSet) . "\n");
    fwrite(STDERR, "Unique IDs:     " . count($idMap) . "\n");

    if (!empty($duplicateIds)) {
        fwrite(STDERR, "\n❌ DUPLICATE IDs (" . count($duplicateIds) . "):\n");
        foreach ($duplicateIds as $dup) {
            fwrite(STDERR, "   - {$dup}\n");
        }
        exit(1);
    } else {
        fwrite(STDERR, "✅ No duplicate IDs\n");
    }

    if (!empty($missingDesc)) {
        fwrite(STDERR, "\n⚠️  Generic/missing descriptions (" . count($missingDesc) . "):\n");
        foreach (array_slice($missingDesc, 0, 10) as $md) {
            fwrite(STDERR, "   - {$md}\n");
        }
    } else {
        fwrite(STDERR, "✅ All models have custom descriptions\n");
    }

    fwrite(STDERR, "\nType distribution:\n");
    arsort($typeSet);
    foreach ($typeSet as $type => $cnt) {
        fwrite(STDERR, "  {$type}: {$cnt}\n");
    }
    exit(0);
}

// ── Output ─────────────────────────────────────────────────────────────
$json = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

if (isset($opts['output'])) {
    $outFile = $opts['output'];
    $dir = dirname($outFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    file_put_contents($outFile, $json);
    fwrite(STDERR, "Written to: {$outFile} (" . strlen($json) . " bytes)\n");
} else {
    echo $json;
}

// ── Split mode ─────────────────────────────────────────────────────────
if (isset($opts['split-dir'])) {
    $splitDir = rtrim($opts['split-dir'], '/') . '/';
    if (!is_dir($splitDir)) {
        mkdir($splitDir, 0755, true);
    }
    // Clean existing
    array_map('unlink', glob($splitDir . '*.json'));

    foreach ($output as $item) {
        $filename = $splitDir . $item['id'] . '.json';
        file_put_contents($filename, json_encode($item, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }
    fwrite(STDERR, "Split {$parentCount} files to: {$splitDir}\n");
}

// ── Stats ──────────────────────────────────────────────────────────────
if (isset($opts['stats'])) {
    fwrite(STDERR, "\n📊 Seed Stats:\n");
    fwrite(STDERR, "  Models:   {$parentCount}\n");
    fwrite(STDERR, "  Variants: {$variantCount}\n");
    fwrite(STDERR, "  Total:    {$totalPosts}\n");
    fwrite(STDERR, "  Brands:   " . count($brandSet) . "\n");
}
