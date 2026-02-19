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
Helmetsan Seed Generator (Deterministic)

Options:
  --output=<file>      Write JSON to file instead of stdout
  --validate           Validate data (check IDs, descriptions) then exit
  --stats              Print summary stats to stderr after generation
  --split-dir=<dir>    Also write individual JSON files to <dir>/ for ingestPath
  --help               Show this help

HELP
    );
    exit(0);
}

$brands = array (
  'Shoei' => 
  array (
    'RF-1400' => 
    array (
      'type' => 'Full Face',
      'price' => 579.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'Snell M2020',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1640,
      'mat' => 'Fiberglass',
      'desc' => 'The Shoei RF-1400 is the latest in the RF series, focused on lightweight performance and quietness.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'SHO-RF14-MBK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'SHO-RF14-WHT',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Nocturne TC-5',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'SHO-RF14-NOC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Prologue TC-1',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'SHO-RF14-PRO-R',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Accolade TC-10',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SHO-RF14-ACC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Neotec 3' => 
    array (
      'type' => 'Modular',
      'price' => 899.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1700,
      'mat' => 'AIM',
      'desc' => 'Shoei Neotec 3 offers premium modular convenience with ECE 22.06 safety.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'SHO-NEO3-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Matte Deep Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'SHO-NEO3-MDG',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'SHO-NEO3-WHT',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Grasp TC-1',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'SHO-NEO3-GRA',
          'price_adj' => 100,
          'is_graphic' => true,
        ),
      ),
    ),
    'X-Fifteen' => 
    array (
      'type' => 'Track / Race',
      'price' => 899.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'Snell M2020',
        2 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1450,
      'mat' => 'AIM+',
      'desc' => 'The X-Fifteen is a true race-bred helmet, developed in MotoGP.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'SHO-X15-MBK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'SHO-X15-WHT',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Marquez 7 TC-1',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'SHO-X15-MAR',
          'price_adj' => 130,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Proxy TC-11',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SHO-X15-PRO',
          'price_adj' => 130,
          'is_graphic' => true,
        ),
      ),
    ),
    'GT-Air 3' => 
    array (
      'type' => 'Touring',
      'price' => 699.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1550,
      'mat' => 'AIM',
      'desc' => 'The GT-Air 3 is the ultimate sport-touring helmet with sun visor.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'SHO-GTA3-MBK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Brilliant Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'SHO-GTA3-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Discipline TC-2',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'SHO-GTA3-DIS',
          'price_adj' => 100,
          'is_graphic' => true,
        ),
      ),
    ),
    'Hornet X2' => 
    array (
      'type' => 'Adventure / Dual Sport',
      'price' => 649.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'Snell M2015',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1750,
      'mat' => 'AIM+',
      'desc' => 'Designed for on-and-off road, the Hornet X2 balances aerodynamics and ventilation.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'SHO-HORN-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'SHO-HORN-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'SHO-HORN-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Raptor Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'SHO-HORN-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Camo Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'SHO-HORN-CAM',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Street Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'SHO-HORN-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'J-Cruise II' => 
    array (
      'type' => 'Open Face',
      'price' => 549.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1350,
      'mat' => 'AIM',
      'desc' => 'Premium open face touring helmet with extended internal sun shield.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'SHO-JCRU-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'SHO-JCRU-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'SHO-JCRU-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Camo TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SHO-JCRU-CAM',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Vortex TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SHO-JCRU-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Racer Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'SHO-JCRU-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Glamster' => 
    array (
      'type' => 'Full Face',
      'price' => 599.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1250,
      'mat' => 'AIM',
      'desc' => 'Neo-retro style meets modern safety and comfort.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'SHO-GLAM-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'SHO-GLAM-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'SHO-GLAM-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Racer Grey',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'SHO-GLAM-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Tech TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SHO-GLAM-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Urban TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SHO-GLAM-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'VFX-EVO' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 539.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'Snell M2020',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1420,
      'mat' => 'AIM+',
      'desc' => 'Top-tier motocross helmet with M.E.D.S. rotational impact system.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'SHO-VFXE-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'SHO-VFXE-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'SHO-VFXE-YEL',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Solid TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SHO-VFXE-SOL',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Tech TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SHO-VFXE-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Operator TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SHO-VFXE-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'RF-1200' => 
    array (
      'type' => 'Full Face',
      'price' => 499.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'Snell M2015',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1520,
      'mat' => 'AIM',
      'desc' => 'Predecessor to the RF-1400, still popular for its proven performance.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'SHO-RF12-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'SHO-RF12-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Stripe TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SHO-RF12-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Track TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SHO-RF12-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Urban Red',
          'family' => 'Red',
          'finish' => 'matte',
          'sku' => 'SHO-RF12-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Camo Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'SHO-RF12-CAM',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Neotec II' => 
    array (
      'type' => 'Modular',
      'price' => 829.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1760,
      'mat' => 'AIM',
      'desc' => 'Previous-gen modular with integrated SRL2 Sena comms ready.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'SHO-NEOT-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'SHO-NEOT-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'SHO-NEOT-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Urban TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SHO-NEOT-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Operator Blue',
          'family' => 'Blue',
          'finish' => 'matte',
          'sku' => 'SHO-NEOT-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Stripe Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'SHO-NEOT-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Arai' => 
  array (
    'Corsair-X' => 
    array (
      'type' => 'Track / Race',
      'price' => 979.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'Snell M2020',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1590,
      'mat' => 'PB-SNC2',
      'desc' => 'The Corsair-X is Arai\'s flagship race helmet with adjustable airflow.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Black Frost',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'ARA-CORX-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Aluminum Silver',
          'family' => 'Silver',
          'finish' => 'gloss',
          'sku' => 'ARA-CORX-SIL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Nakagami 3',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'ARA-CORX-NAK',
          'price_adj' => 130,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Isle of Man TT 2024',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'ARA-CORX-IOM',
          'price_adj' => 150,
          'is_graphic' => true,
        ),
      ),
    ),
    'Contour-X' => 
    array (
      'type' => 'Full Face',
      'price' => 749.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'Snell M2020',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1550,
      'mat' => 'PB-cLc2',
      'desc' => 'Designed for high-speed touring stability and comfort.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Diamond Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'ARA-CONX-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Fluorescent Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'ARA-CONX-YEL',
          'price_adj' => 10,
        ),
        2 => 
        array (
          'name' => 'Snake Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'ARA-CONX-SNK',
          'price_adj' => 100,
          'is_graphic' => true,
        ),
      ),
    ),
    'Regent-X' => 
    array (
      'type' => 'Full Face',
      'price' => 579.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'Snell M2020',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1500,
      'mat' => 'PB-cLc1',
      'desc' => 'Excellent entry into the premium Arai lineup, focusing on comfort.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'ARA-REGE-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'ARA-REGE-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Camo Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'ARA-REGE-CAM',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Apex Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'ARA-REGE-APE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Raptor TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'ARA-REGE-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Track Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'ARA-REGE-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Signet-X' => 
    array (
      'type' => 'Full Face',
      'price' => 699.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'Snell M2020',
      ),
      'shape' => 'Long Oval',
      'weight' => 1580,
      'mat' => 'PB-cLc',
      'desc' => 'Specifically built for riders with a longer head shape.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'ARA-SIGN-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'ARA-SIGN-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Course TC-3',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'ARA-SIGN-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Course TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'ARA-SIGN-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Operator Grey',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'ARA-SIGN-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Vortex TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'ARA-SIGN-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Quantum-X' => 
    array (
      'type' => 'Full Face',
      'price' => 699.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'Snell M2020',
      ),
      'shape' => 'Round Oval',
      'weight' => 1590,
      'mat' => 'PB-cLc',
      'desc' => 'Specifically built for riders with a rounder head shape.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'ARA-QUAN-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'ARA-QUAN-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'ARA-QUAN-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Solid TC-5',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'ARA-QUAN-SOL',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Operator Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'ARA-QUAN-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Flow TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'ARA-QUAN-FLO',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'XD-5' => 
    array (
      'type' => 'Adventure / Dual Sport',
      'price' => 839.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'Snell M2020',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1680,
      'mat' => 'PB-cLc2',
      'desc' => 'The ultimate adventure helmet, successor to the legendary XD-4.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Black Frost',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'ARA-XD5-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Adventure Grey',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'ARA-XD5-GRY',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Discovery Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'ARA-XD5-DIS',
          'price_adj' => 120,
          'is_graphic' => true,
        ),
      ),
    ),
    'Classic-V' => 
    array (
      'type' => 'Open Face',
      'price' => 489.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'Snell M2020',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1200,
      'mat' => 'PB-cLc',
      'desc' => 'Heritage styling with modern Arai protection.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'ARA-CLAS-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'ARA-CLAS-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Operator Blue',
          'family' => 'Blue',
          'finish' => 'matte',
          'sku' => 'ARA-CLAS-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Flow TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'ARA-CLAS-FLO',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Street TC-3',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'ARA-CLAS-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'VX-Pro4' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 749.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'Snell M2020',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1430,
      'mat' => 'cLc',
      'desc' => 'Professional grade motocross protection.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'ARA-VXPR-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'ARA-VXPR-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Street TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'ARA-VXPR-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Flow Red',
          'family' => 'Red',
          'finish' => 'matte',
          'sku' => 'ARA-VXPR-FLO',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Street TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'ARA-VXPR-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Racer Grey',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'ARA-VXPR-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'DT-X' => 
    array (
      'type' => 'Full Face',
      'price' => 579.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'Snell M2020',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1540,
      'mat' => 'PB-cLc',
      'desc' => 'Versatile all-road helmet bridging sport and touring capabilities.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'ARA-DTX-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'ARA-DTX-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'ARA-DTX-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Track Blue',
          'family' => 'Blue',
          'finish' => 'matte',
          'sku' => 'ARA-DTX-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Vortex TC-5',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'ARA-DTX-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Raptor Grey',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'ARA-DTX-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Defiant-X' => 
    array (
      'type' => 'Full Face',
      'price' => 699.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'Snell M2020',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1570,
      'mat' => 'PB-cLc2',
      'desc' => 'Features the unique Pro Shade system for ultimate versatility.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'ARA-DEFI-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'ARA-DEFI-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Vortex Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'ARA-DEFI-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Tech TC-3',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'ARA-DEFI-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Operator TC-3',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'ARA-DEFI-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'AGV' => 
  array (
    'Pista GP RR' => 
    array (
      'type' => 'Track / Race',
      'price' => 1449.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
        2 => 'FIM',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1450,
      'mat' => 'Carbon Fiber',
      'desc' => 'Exact replica of the helmet worn by MotoGP professionals.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Carbon',
          'family' => 'Carbon',
          'finish' => 'gloss',
          'sku' => 'AGV-PIS-GC',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Iridium Carbon',
          'family' => 'Carbon',
          'finish' => 'matte',
          'sku' => 'AGV-PIS-IC',
          'price_adj' => 200,
          'is_graphic' => true,
        ),
        2 => 
        array (
          'name' => 'Soleluna 2023',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'AGV-PIS-SOL',
          'price_adj' => 250,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Winter Test 2005',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'AGV-PIS-WIN',
          'price_adj' => 300,
          'is_graphic' => true,
        ),
      ),
    ),
    'K6 S' => 
    array (
      'type' => 'Full Face',
      'price' => 549.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1255,
      'mat' => 'Carbon-Aramid',
      'desc' => 'The lightest road helmet in the world per AGV.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'AGV-K6S-MBK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Nardo Grey',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'AGV-K6S-NAR',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Petrolio',
          'family' => 'Blue',
          'finish' => 'matte',
          'sku' => 'AGV-K6S-PET',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Slashcut Black/Red',
          'family' => 'Red',
          'finish' => 'matte',
          'sku' => 'AGV-K6S-SLA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'K1 S' => 
    array (
      'type' => 'Full Face',
      'price' => 219.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1500,
      'mat' => 'Thermoplastic',
      'desc' => 'Aerodynamic shape and racing ventilation for everyday riders.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'AGV-K1S-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'AGV-K1S-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Urban Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'AGV-K1S-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Vortex TC-5',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'AGV-K1S-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Flow Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'AGV-K1S-FLO',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Sportmodular' => 
    array (
      'type' => 'Modular',
      'price' => 849.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1295,
      'mat' => 'Carbon Fiber',
      'desc' => 'Modular convenience with full carbon fiber construction.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'AGV-SPOR-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'AGV-SPOR-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Camo Red',
          'family' => 'Red',
          'finish' => 'matte',
          'sku' => 'AGV-SPOR-CAM',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Course Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'AGV-SPOR-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Stripe Grey',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'AGV-SPOR-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Course Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'AGV-SPOR-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'AX9' => 
    array (
      'type' => 'Adventure / Dual Sport',
      'price' => 649.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1445,
      'mat' => 'Carbon-Aramid-Glass',
      'desc' => 'Lightweight, comfortable, and adaptable for 4 different configurations.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'AGV-AX9-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'AGV-AX9-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Track Blue',
          'family' => 'Blue',
          'finish' => 'matte',
          'sku' => 'AGV-AX9-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Camo TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'AGV-AX9-CAM',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Course Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'AGV-AX9-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Operator TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'AGV-AX9-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'X3000' => 
    array (
      'type' => 'Full Face',
      'price' => 449.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1370,
      'mat' => 'Fibreglass',
      'desc' => 'Inspired by the historic models worn by Agostini.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'AGV-X300-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'AGV-X300-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Tech TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'AGV-X300-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Stripe TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'AGV-X300-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Racer TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'AGV-X300-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Racer Grey',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'AGV-X300-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'K3' => 
    array (
      'type' => 'Full Face',
      'price' => 269.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1550,
      'mat' => 'Thermoplastic',
      'desc' => 'Versatile road helmet with internal sun visor.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'AGV-K3-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'AGV-K3-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'AGV-K3-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Camo TC-5',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'AGV-K3-CAM',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Operator Blue',
          'family' => 'Blue',
          'finish' => 'matte',
          'sku' => 'AGV-K3-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Track TC-3',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'AGV-K3-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Tourmodular' => 
    array (
      'type' => 'Modular',
      'price' => 649.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1620,
      'mat' => 'Carbon-Aramid-Glass',
      'desc' => 'Designed for endless journeys with maximum safety.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'AGV-TOUR-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'AGV-TOUR-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'AGV-TOUR-YEL',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Urban TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'AGV-TOUR-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Camo TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'AGV-TOUR-CAM',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Street TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'AGV-TOUR-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'K5 S' => 
    array (
      'type' => 'Full Face',
      'price' => 399.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1460,
      'mat' => 'Fiberglass',
      'desc' => 'Mid-range sport-touring with internal sun visor and wide field of view.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'AGV-K5S-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'AGV-K5S-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'AGV-K5S-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Course Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'AGV-K5S-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Racer Red',
          'family' => 'Red',
          'finish' => 'matte',
          'sku' => 'AGV-K5S-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Camo TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'AGV-K5S-CAM',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'AX-8 EVO' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 479.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1200,
      'mat' => 'Carbon-Aramid',
      'desc' => 'Ultra-light off-road helmet designed in collaboration with MotoGP riders.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'AGV-AX8E-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'AGV-AX8E-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Apex Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'AGV-AX8E-APE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Urban TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'AGV-AX8E-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Vortex TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'AGV-AX8E-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Apex TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'AGV-AX8E-APE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'HJC' => 
  array (
    'RPHA 1N' => 
    array (
      'type' => 'Track / Race',
      'price' => 699.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
        2 => 'FIM',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1500,
      'mat' => 'PIM+',
      'desc' => 'FIM homologated racing helmet available to the public.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'HJC-R1-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Red Bull Austin',
          'family' => 'Blue',
          'finish' => 'matte',
          'sku' => 'HJC-R1-RBA',
          'price_adj' => 100,
          'is_graphic' => true,
        ),
        2 => 
        array (
          'name' => 'Quartararo Replica',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'HJC-R1-QUA',
          'price_adj' => 100,
          'is_graphic' => true,
        ),
      ),
    ),
    'RPHA 11 Pro' => 
    array (
      'type' => 'Track / Race',
      'price' => 399.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1420,
      'mat' => 'PIM+',
      'desc' => 'Known for excellent airflow and lightweight construction.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Semi Flat Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'HJC-R11-SFB',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Venom Marvel',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'HJC-R11-VEN',
          'price_adj' => 100,
          'is_graphic' => true,
        ),
        2 => 
        array (
          'name' => 'Anti Venom',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'HJC-R11-ANT',
          'price_adj' => 100,
          'is_graphic' => true,
        ),
      ),
    ),
    'RPHA 71' => 
    array (
      'type' => 'Touring',
      'price' => 499.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1600,
      'mat' => 'PIM Evo',
      'desc' => 'Premium sport-touring model with advanced shock resistance.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'HJC-RPHA-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'HJC-RPHA-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'HJC-RPHA-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Camo TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'HJC-RPHA-CAM',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Stripe Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'HJC-RPHA-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Course Blue',
          'family' => 'Blue',
          'finish' => 'matte',
          'sku' => 'HJC-RPHA-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'RPHA 91' => 
    array (
      'type' => 'Modular',
      'price' => 549.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1700,
      'mat' => 'PIM Evo',
      'desc' => 'Functional modular helmet with P/J homologation.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'HJC-RPHA-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'HJC-RPHA-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'HJC-RPHA-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Track TC-5',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'HJC-RPHA-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Apex Blue',
          'family' => 'Blue',
          'finish' => 'matte',
          'sku' => 'HJC-RPHA-APE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Apex Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'HJC-RPHA-APE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'i10' => 
    array (
      'type' => 'Full Face',
      'price' => 169.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'Snell M2020',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1650,
      'mat' => 'Polycarbonate',
      'desc' => 'Snell protection at an entry-level price point.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'HJC-I10-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'HJC-I10-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'HJC-I10-YEL',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Solid TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'HJC-I10-SOL',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Racer TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'HJC-I10-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Course Red',
          'family' => 'Red',
          'finish' => 'matte',
          'sku' => 'HJC-I10-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'F70' => 
    array (
      'type' => 'Full Face',
      'price' => 269.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1550,
      'mat' => 'Fiberglass',
      'desc' => 'Progressive shell design with an elegant appearance.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'HJC-F70-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'HJC-F70-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'HJC-F70-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Apex TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'HJC-F70-APE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Raptor Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'HJC-F70-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Raptor TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'HJC-F70-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'i90' => 
    array (
      'type' => 'Modular',
      'price' => 224.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1720,
      'mat' => 'Polycarbonate',
      'desc' => 'Compact modular helmet with integrated sun shield.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'HJC-I90-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'HJC-I90-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Tech TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'HJC-I90-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Solid TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'HJC-I90-SOL',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Track TC-5',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'HJC-I90-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'CS-R3' => 
    array (
      'type' => 'Full Face',
      'price' => 139.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1600,
      'mat' => 'Polycarbonate',
      'desc' => 'Budget-friendly street helmet with proven reliability.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'HJC-CSR3-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'HJC-CSR3-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Tech TC-3',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'HJC-CSR3-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Operator TC-5',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'HJC-CSR3-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Tech Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'HJC-CSR3-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Raptor TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'HJC-CSR3-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'V10' => 
    array (
      'type' => 'Full Face',
      'price' => 329.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1450,
      'mat' => 'Fiberglass',
      'desc' => 'Classic look with new-age performance and safety.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'HJC-V10-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'HJC-V10-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Solid Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'HJC-V10-SOL',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Urban TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'HJC-V10-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Track Red',
          'family' => 'Red',
          'finish' => 'matte',
          'sku' => 'HJC-V10-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Track Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'HJC-V10-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'DS-X1' => 
    array (
      'type' => 'Adventure / Dual Sport',
      'price' => 189.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1650,
      'mat' => 'Polycarbonate',
      'desc' => 'Great value dual-sport helmet for street and trail.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'HJC-DSX1-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'HJC-DSX1-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Street TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'HJC-DSX1-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Stripe TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'HJC-DSX1-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Urban TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'HJC-DSX1-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Operator Grey',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'HJC-DSX1-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'i30' => 
    array (
      'type' => 'Open Face',
      'price' => 129.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1200,
      'mat' => 'Polycarbonate',
      'desc' => 'Modern open face with wide-eye port and internal sun visor.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'HJC-I30-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'HJC-I30-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'HJC-I30-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Racer Grey',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'HJC-I30-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Vortex Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'HJC-I30-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Raptor TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'HJC-I30-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'CL-17' => 
    array (
      'type' => 'Full Face',
      'price' => 159.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'Snell M2015',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1630,
      'mat' => 'Polycarbonate',
      'desc' => 'Battle-proven full face with ACS ventilation and Snell certification.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'HJC-CL17-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'HJC-CL17-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Track TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'HJC-CL17-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Camo Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'HJC-CL17-CAM',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Racer TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'HJC-CL17-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Solid TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'HJC-CL17-SOL',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'IS-Cruiser' => 
    array (
      'type' => 'Half',
      'price' => 109.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 950,
      'mat' => 'Polycarbonate',
      'desc' => 'Lightweight half-helmet with sun shield for cruiser riding.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'HJC-ISCR-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'HJC-ISCR-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Urban TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'HJC-ISCR-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Raptor TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'HJC-ISCR-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Course Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'HJC-ISCR-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Tech TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'HJC-ISCR-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Bell' => 
  array (
    'Race Star Flex DLX' => 
    array (
      'type' => 'Track / Race',
      'price' => 819.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'Snell M2020',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1500,
      'mat' => 'Carbon Fiber',
      'desc' => 'Dedicated racer with 3k carbon shell and Flex energy management.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Carbon',
          'family' => 'Carbon',
          'finish' => 'matte',
          'sku' => 'BEL-RS-MC',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Rsd The Zone',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'BEL-RS-RSD',
          'price_adj' => 100,
          'is_graphic' => true,
        ),
      ),
    ),
    'Star DLX MIPS' => 
    array (
      'type' => 'Full Face',
      'price' => 569.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'Snell M2020',
        2 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1600,
      'mat' => 'Tri-Matrix',
      'desc' => 'Street optimized version of the Star with MIPS protection.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'BEL-STAR-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'BEL-STAR-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'BEL-STAR-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Apex TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'BEL-STAR-APE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Raptor TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'BEL-STAR-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Solid TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'BEL-STAR-SOL',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Qualifier DLX MIPS' => 
    array (
      'type' => 'Full Face',
      'price' => 289.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1550,
      'mat' => 'Polycarbonate',
      'desc' => 'Includes proactive transition shield and MIPS.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'BEL-QUAL-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'BEL-QUAL-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'BEL-QUAL-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Operator TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'BEL-QUAL-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Apex TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'BEL-QUAL-APE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Solid TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'BEL-QUAL-SOL',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Moto-9S Flex' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 599.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'Snell M2020',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1450,
      'mat' => 'Composite',
      'desc' => 'Pro-level protection for the dirt track.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'BEL-M9S-MBK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Tomac Replica',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'BEL-M9S-TOM',
          'price_adj' => 100,
          'is_graphic' => true,
        ),
        2 => 
        array (
          'name' => 'Fasthouse DID',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'BEL-M9S-FAS',
          'price_adj' => 100,
          'is_graphic' => true,
        ),
      ),
    ),
    'MX-9 Adventure MIPS' => 
    array (
      'type' => 'Adventure / Dual Sport',
      'price' => 239.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1690,
      'mat' => 'Polycarbonate',
      'desc' => 'Highly versatile helmet for exploring the unknown.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'BEL-MX9A-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'BEL-MX9A-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Stripe TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'BEL-MX9A-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Course Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'BEL-MX9A-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Solid TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'BEL-MX9A-SOL',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Custom 500' => 
    array (
      'type' => 'Open Face',
      'price' => 139.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1100,
      'mat' => 'Fiberglass',
      'desc' => 'The original helmet design, updated with modern technology.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'BEL-CUST-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'BEL-CUST-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Track TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'BEL-CUST-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Solid Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'BEL-CUST-SOL',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Apex Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'BEL-CUST-APE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Stripe Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'BEL-CUST-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Bullitt' => 
    array (
      'type' => 'Full Face',
      'price' => 439.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Round Oval',
      'weight' => 1470,
      'mat' => 'Fiberglass',
      'desc' => 'Retro styling with huge eyeport for visibility.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'BEL-BULL-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'BEL-BULL-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'BEL-BULL-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Tech TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'BEL-BULL-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Course TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'BEL-BULL-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Flow TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'BEL-BULL-FLO',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'SRT Modular' => 
    array (
      'type' => 'Modular',
      'price' => 389.95,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1760,
      'mat' => 'Fiberglass',
      'desc' => 'Flip-up convenience with high-grade fiberglass shell.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'BEL-SRTM-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'BEL-SRTM-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Street Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'BEL-SRTM-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Track TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'BEL-SRTM-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Flow TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'BEL-SRTM-FLO',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Broozer' => 
    array (
      'type' => 'Modular',
      'price' => 289.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1350,
      'mat' => 'Polycarbonate',
      'desc' => 'Dual-certified modular with removable chin bar.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'BEL-BROO-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'BEL-BROO-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'BEL-BROO-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Tech Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'BEL-BROO-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Camo TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'BEL-BROO-CAM',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Stripe TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'BEL-BROO-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Eliminator' => 
    array (
      'type' => 'Full Face',
      'price' => 349.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1550,
      'mat' => 'Composite',
      'desc' => 'Flat-track inspired cruiser helmet with ProVision anti-fog shield.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'BEL-ELIM-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'BEL-ELIM-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Tech Grey',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'BEL-ELIM-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Stripe Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'BEL-ELIM-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Tech TC-3',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'BEL-ELIM-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Urban TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'BEL-ELIM-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Pit Boss' => 
    array (
      'type' => 'Half',
      'price' => 179.95,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 950,
      'mat' => 'Tri-Matrix',
      'desc' => 'Premium half-helmet with drop-down sun shield.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'BEL-PITB-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'BEL-PITB-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Flow Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'BEL-PITB-FLO',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Flow TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'BEL-PITB-FLO',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Operator TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'BEL-PITB-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Raptor TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'BEL-PITB-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Rogue' => 
    array (
      'type' => 'Half',
      'price' => 249.95,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1200,
      'mat' => 'Fiberglass',
      'desc' => 'Unique half-helmet with detachable muzzle for aggressive styling.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'BEL-ROGU-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'BEL-ROGU-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Track TC-5',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'BEL-ROGU-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Solid Red',
          'family' => 'Red',
          'finish' => 'matte',
          'sku' => 'BEL-ROGU-SOL',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Vortex Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'BEL-ROGU-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Street TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'BEL-ROGU-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Mag-9' => 
    array (
      'type' => 'Open Face',
      'price' => 169.95,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1300,
      'mat' => 'Polycarbonate',
      'desc' => 'Versatile sena-ready open face with internal sun shield.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'BEL-MAG9-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'BEL-MAG9-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Tech TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'BEL-MAG9-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Stripe Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'BEL-MAG9-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Flow Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'BEL-MAG9-FLO',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Raptor Red',
          'family' => 'Red',
          'finish' => 'matte',
          'sku' => 'BEL-MAG9-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Schuberth' => 
  array (
    'C5' => 
    array (
      'type' => 'Modular',
      'price' => 749.0,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1640,
      'mat' => 'Fiberglass',
      'desc' => 'The masterpiece of flip-up helmets with P/J homologation.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'SCH-C5-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'SCH-C5-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Apex TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SCH-C5-APE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Apex Blue',
          'family' => 'Blue',
          'finish' => 'matte',
          'sku' => 'SCH-C5-APE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Tech TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SCH-C5-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Tech Red',
          'family' => 'Red',
          'finish' => 'matte',
          'sku' => 'SCH-C5-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'E2' => 
    array (
      'type' => 'Adventure / Dual Sport',
      'price' => 799.0,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1680,
      'mat' => 'Refined Fiberglass',
      'desc' => 'Evolution of the E1, bridging adventure and touring.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'SCH-E2-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'SCH-E2-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Urban TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SCH-E2-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Stripe Grey',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'SCH-E2-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Operator Blue',
          'family' => 'Blue',
          'finish' => 'matte',
          'sku' => 'SCH-E2-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Street TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SCH-E2-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'S3' => 
    array (
      'type' => 'Full Face',
      'price' => 649.0,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1590,
      'mat' => 'Direct Fiber',
      'desc' => 'Sport touring with acoustic optimization.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'SCH-S3-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'SCH-S3-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Urban Blue',
          'family' => 'Blue',
          'finish' => 'matte',
          'sku' => 'SCH-S3-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Apex TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SCH-S3-APE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Urban TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SCH-S3-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Operator TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SCH-S3-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'M1 Pro' => 
    array (
      'type' => 'Open Face',
      'price' => 499.0,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1395,
      'mat' => 'Fiberglass',
      'desc' => 'Perfect for naked bikes and cruisers with pre-installed comms.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'SCH-M1PR-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'SCH-M1PR-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'SCH-M1PR-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Raptor TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SCH-M1PR-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Solid Grey',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'SCH-M1PR-SOL',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Apex Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'SCH-M1PR-APE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'R2 Carbon' => 
    array (
      'type' => 'Track / Race',
      'price' => 899.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Long Oval',
      'weight' => 1300,
      'mat' => 'Carbon Fiber',
      'desc' => 'Schuberth\'s first pure race helmet with carbon shell and MIPS.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'SCH-R2CA-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'SCH-R2CA-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Urban Blue',
          'family' => 'Blue',
          'finish' => 'matte',
          'sku' => 'SCH-R2CA-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Urban TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SCH-R2CA-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Street TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SCH-R2CA-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Vortex Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'SCH-R2CA-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'M2' => 
    array (
      'type' => 'Adventure / Dual Sport',
      'price' => 749.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Long Oval',
      'weight' => 1650,
      'mat' => 'Fiberglass',
      'desc' => 'Premium adventure modular with S-Com ready and electric sun visor.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'SCH-M2-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'SCH-M2-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'SCH-M2-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Track TC-5',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SCH-M2-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Apex TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SCH-M2-APE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Solid TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SCH-M2-SOL',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Icon' => 
  array (
    'Airflite' => 
    array (
      'type' => 'Full Face',
      'price' => 290.0,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Long Oval',
      'weight' => 1690,
      'mat' => 'Polycarbonate',
      'desc' => 'Aggressive styling with massive ventilation.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'ICO-AIRF-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'ICO-AIRF-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Racer Grey',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'ICO-AIRF-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Street TC-3',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'ICO-AIRF-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Airform' => 
    array (
      'type' => 'Full Face',
      'price' => 225.0,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1650,
      'mat' => 'Polycarbonate',
      'desc' => 'Combines features from Airframe and Airflite for versatile use.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'ICO-AIRF-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'ICO-AIRF-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Operator Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'ICO-AIRF-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Variant Pro' => 
    array (
      'type' => 'Adventure / Dual Sport',
      'price' => 350.0,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1620,
      'mat' => 'Composite',
      'desc' => 'Wind-tunnel tested visor for stability at speed.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'ICO-VARI-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'ICO-VARI-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'ICO-VARI-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Racer TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'ICO-VARI-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Domain' => 
    array (
      'type' => 'Full Face',
      'price' => 350.0,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1550,
      'mat' => 'FRP',
      'desc' => 'Aggressive street helmet with integrated FRP chin bar and ICON styling.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'ICO-DOMA-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'ICO-DOMA-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'ICO-DOMA-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Track Blue',
          'family' => 'Blue',
          'finish' => 'matte',
          'sku' => 'ICO-DOMA-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Elsinore' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 275.0,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1400,
      'mat' => 'Composite',
      'desc' => 'Vintage MX styling meets modern composite construction and safety.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'ICO-ELSI-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'ICO-ELSI-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Camo TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'ICO-ELSI-CAM',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Tech TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'ICO-ELSI-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Airform MIPS' => 
    array (
      'type' => 'Full Face',
      'price' => 275.0,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1500,
      'mat' => 'Polycarbonate',
      'desc' => 'Airform with MIPS rotational impact protection and Icon styling.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'ICO-AIRF-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'ICO-AIRF-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Flow TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'ICO-AIRF-FLO',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Track Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'ICO-AIRF-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Domain MIPS' => 
    array (
      'type' => 'Full Face',
      'price' => 399.0,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
        2 => 'Snell M2020',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1450,
      'mat' => 'Fiberglass',
      'desc' => 'Triple-certified with MIPS — Icon\'s most protective street helmet.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'ICO-DOMA-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'ICO-DOMA-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Camo TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'ICO-DOMA-CAM',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Raptor TC-3',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'ICO-DOMA-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Scorpion' => 
  array (
    'EXO-R420' => 
    array (
      'type' => 'Full Face',
      'price' => 159.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'Snell M2020',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1620,
      'mat' => 'Polycarbonate',
      'desc' => 'Incredible value — Snell M2020 and DOT certified at an entry-level price.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'SCO-EXOR-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'SCO-EXOR-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Raptor TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SCO-EXOR-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Course TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SCO-EXOR-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Vortex Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'SCO-EXOR-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Track TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SCO-EXOR-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'EXO-R1 Air' => 
    array (
      'type' => 'Track / Race',
      'price' => 429.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1380,
      'mat' => 'TCT-Ultra',
      'desc' => 'Race-grade helmet with ultra-lightweight TCT composite shell.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'SCO-EXOR-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'SCO-EXOR-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Camo Red',
          'family' => 'Red',
          'finish' => 'matte',
          'sku' => 'SCO-EXOR-CAM',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Racer TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SCO-EXOR-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Vortex TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SCO-EXOR-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Raptor Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'SCO-EXOR-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'EXO-AT950' => 
    array (
      'type' => 'Adventure / Dual Sport',
      'price' => 269.95,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1780,
      'mat' => 'Polycarbonate',
      'desc' => 'Modular adventure helmet with removable peak and electric shield option.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'SCO-EXOA-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'SCO-EXOA-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Camo TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SCO-EXOA-CAM',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Tech Red',
          'family' => 'Red',
          'finish' => 'matte',
          'sku' => 'SCO-EXOA-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Racer TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SCO-EXOA-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Raptor Red',
          'family' => 'Red',
          'finish' => 'matte',
          'sku' => 'SCO-EXOA-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'EXO-1400 Air' => 
    array (
      'type' => 'Full Face',
      'price' => 399.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1450,
      'mat' => 'TCT-U',
      'desc' => 'Premium full face with TCT-U shell and KwikWick III liner.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'SCO-EXO1-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'SCO-EXO1-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Stripe TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SCO-EXO1-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Racer TC-5',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SCO-EXO1-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Raptor TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SCO-EXO1-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Course Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'SCO-EXO1-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Covert FX' => 
    array (
      'type' => 'Full Face',
      'price' => 249.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1400,
      'mat' => 'Composite',
      'desc' => 'Converts from full face to open face with removable rear and front covers.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'SCO-COVE-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'SCO-COVE-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'SCO-COVE-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Raptor Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'SCO-COVE-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Tech Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'SCO-COVE-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Course TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SCO-COVE-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Belfast' => 
    array (
      'type' => 'Open Face',
      'price' => 199.95,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1100,
      'mat' => 'Fiberglass',
      'desc' => 'Retro open face with internal sun visor and fiberglass shell.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'SCO-BELF-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'SCO-BELF-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'SCO-BELF-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Flow TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SCO-BELF-FLO',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Street Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'SCO-BELF-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Operator Red',
          'family' => 'Red',
          'finish' => 'matte',
          'sku' => 'SCO-BELF-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'EXO-GT930' => 
    array (
      'type' => 'Modular',
      'price' => 349.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1700,
      'mat' => 'Fiberglass',
      'desc' => 'Transformer modular with optional chin-guard removal for open-face mode.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'SCO-EXOG-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'SCO-EXOG-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'SCO-EXOG-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Stripe TC-3',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SCO-EXOG-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Stripe Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'SCO-EXOG-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Track Grey',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'SCO-EXOG-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'EXO-Combat II' => 
    array (
      'type' => 'Full Face',
      'price' => 299.95,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1300,
      'mat' => 'Composite',
      'desc' => 'Urban streetfighter helmet with removable front mask and goggle strap.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'SCO-EXOC-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'SCO-EXOC-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'SCO-EXOC-YEL',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Camo Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'SCO-EXOC-CAM',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Flow TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SCO-EXOC-FLO',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Camo TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SCO-EXOC-CAM',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Shark' => 
  array (
    'Race-R Pro GP' => 
    array (
      'type' => 'Track / Race',
      'price' => 1099.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1450,
      'mat' => 'Carbon-Aramid',
      'desc' => 'MotoGP-grade race helmet with carbon-aramid shell and double-D ring.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'SHA-RACE-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'SHA-RACE-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Tech TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SHA-RACE-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Course Grey',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'SHA-RACE-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Street TC-5',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SHA-RACE-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Vortex TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SHA-RACE-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Spartan GT' => 
    array (
      'type' => 'Full Face',
      'price' => 479.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1450,
      'mat' => 'Fiberglass',
      'desc' => 'Sport-touring with integrated sun visor and autoseal system.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'SHA-SPAR-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'SHA-SPAR-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Vortex TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SHA-SPAR-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Raptor TC-5',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SHA-SPAR-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Solid TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SHA-SPAR-SOL',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Skwal i3' => 
    array (
      'type' => 'Full Face',
      'price' => 349.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1500,
      'mat' => 'Thermoplastic',
      'desc' => 'Integrated LED lighting system for enhanced visibility on the road.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'SHA-SKWA-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'SHA-SKWA-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Racer Red',
          'family' => 'Red',
          'finish' => 'matte',
          'sku' => 'SHA-SKWA-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Operator TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SHA-SKWA-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Vortex Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'SHA-SKWA-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'D-Skwal 3' => 
    array (
      'type' => 'Full Face',
      'price' => 249.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1450,
      'mat' => 'Thermoplastic',
      'desc' => 'Sporty entry-level with aggressive design and anti-fog visor.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'SHA-DSKW-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'SHA-DSKW-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'SHA-DSKW-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Operator Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'SHA-DSKW-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Tech TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SHA-DSKW-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Urban TC-3',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SHA-DSKW-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Evo-One 2' => 
    array (
      'type' => 'Modular',
      'price' => 549.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1580,
      'mat' => 'Composite',
      'desc' => 'Full 180° flip modular — chin bar rotates completely behind the helmet.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'SHA-EVOO-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'SHA-EVOO-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Course Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'SHA-EVOO-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Tech Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'SHA-EVOO-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Course TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SHA-EVOO-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Solid TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SHA-EVOO-SOL',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Nolan' => 
  array (
    'N100-5' => 
    array (
      'type' => 'Modular',
      'price' => 449.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1700,
      'mat' => 'Lexan',
      'desc' => 'Italian modular with N-Com communication system preinstalled.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'NOL-N100-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'NOL-N100-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'NOL-N100-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Raptor TC-3',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'NOL-N100-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Track TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'NOL-N100-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Track TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'NOL-N100-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'N80-8' => 
    array (
      'type' => 'Full Face',
      'price' => 299.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1500,
      'mat' => 'Lexan',
      'desc' => 'Sport-touring full face with ultra-wide visor and pinlock included.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'NOL-N808-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'NOL-N808-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Course Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'NOL-N808-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Operator TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'NOL-N808-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Course TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'NOL-N808-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Racer Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'NOL-N808-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'N120-1' => 
    array (
      'type' => 'Modular',
      'price' => 549.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1700,
      'mat' => 'Lexan',
      'desc' => 'Crossover flip-up/jet modular with wide visor field and N-Com ready.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'NOL-N120-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'NOL-N120-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Apex Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'NOL-N120-APE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Camo Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'NOL-N120-CAM',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Raptor TC-5',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'NOL-N120-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Urban TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'NOL-N120-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'N60-6' => 
    array (
      'type' => 'Full Face',
      'price' => 249.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1450,
      'mat' => 'Polycarbonate',
      'desc' => 'Mid-range sport with VPS sun shield and Clima Comfort liner.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'NOL-N606-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'NOL-N606-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Racer TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'NOL-N606-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Solid TC-5',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'NOL-N606-SOL',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Urban TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'NOL-N606-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Track Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'NOL-N606-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'N21 Visor' => 
    array (
      'type' => 'Open Face',
      'price' => 199.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1100,
      'mat' => 'Polycarbonate',
      'desc' => 'Urban open face with integrated visor and N-Com communication ready.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'NOL-N21V-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'NOL-N21V-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Racer Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'NOL-N21V-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Solid TC-3',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'NOL-N21V-SOL',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Racer TC-3',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'NOL-N21V-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Tech TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'NOL-N21V-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'LS2' => 
  array (
    'Stream 2' => 
    array (
      'type' => 'Full Face',
      'price' => 129.98,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1550,
      'mat' => 'KPA',
      'desc' => 'Best-selling budget full face with KPA shell technology.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'LS2-STRE-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'LS2-STRE-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Vortex Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'LS2-STRE-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Vortex TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'LS2-STRE-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Tech TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'LS2-STRE-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Raptor Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'LS2-STRE-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Advant' => 
    array (
      'type' => 'Modular',
      'price' => 349.98,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1650,
      'mat' => 'KPA',
      'desc' => 'P/J-homologated modular with 180° flip-up chin bar.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'LS2-ADVA-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'LS2-ADVA-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Stripe Grey',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'LS2-ADVA-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Operator TC-3',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'LS2-ADVA-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Street Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'LS2-ADVA-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Vortex Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'LS2-ADVA-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Explorer' => 
    array (
      'type' => 'Adventure / Dual Sport',
      'price' => 249.98,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Long Oval',
      'weight' => 1550,
      'mat' => 'HPFC',
      'desc' => 'Value adventure helmet with peak visor and long oval fit.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'LS2-EXPL-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'LS2-EXPL-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Urban Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'LS2-EXPL-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Tech Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'LS2-EXPL-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Course TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'LS2-EXPL-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Raptor TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'LS2-EXPL-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Challenger Carbon' => 
    array (
      'type' => 'Full Face',
      'price' => 359.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1350,
      'mat' => 'Carbon Fiber',
      'desc' => 'Carbon shell sport with HPFC multi-axial direction impact protection.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'LS2-CHAL-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'LS2-CHAL-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Solid Blue',
          'family' => 'Blue',
          'finish' => 'matte',
          'sku' => 'LS2-CHAL-SOL',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Urban TC-5',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'LS2-CHAL-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Urban TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'LS2-CHAL-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Raptor TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'LS2-CHAL-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Explorer C' => 
    array (
      'type' => 'Adventure / Dual Sport',
      'price' => 399.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1500,
      'mat' => 'Carbon Fiber',
      'desc' => 'Carbon adventure with removable peak, Pinlock, and speaker pockets.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'LS2-EXPL-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'LS2-EXPL-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Operator TC-3',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'LS2-EXPL-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Course TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'LS2-EXPL-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Racer TC-3',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'LS2-EXPL-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Raptor TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'LS2-EXPL-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Copter' => 
    array (
      'type' => 'Open Face',
      'price' => 99.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1050,
      'mat' => 'Polycarbonate',
      'desc' => 'Budget-friendly urban open face with retractable sun visor.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'LS2-COPT-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'LS2-COPT-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'LS2-COPT-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Course TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'LS2-COPT-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        4 => 
        array (
          'name' => 'Racer TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'LS2-COPT-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        5 => 
        array (
          'name' => 'Apex TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'LS2-COPT-APE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Fox Racing' => 
  array (
    'V3 RS' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 599.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1260,
      'mat' => 'Carbon-Composite',
      'desc' => 'Top-of-the-line MX helmet with MIPS and dual-density EPS.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'FOX-V3RS-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'FOX-V3RS-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Tech Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'FOX-V3RS-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Solid TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'FOX-V3RS-SOL',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'V1' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 219.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1350,
      'mat' => 'Polycarbonate',
      'desc' => 'Entry-level MX helmet from Fox with magnetic visor release.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'FOX-V1-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'FOX-V1-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'FOX-V1-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Track Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'FOX-V1-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'V2' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 299.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1315,
      'mat' => 'Fiberglass',
      'desc' => 'Mid-range MX with MIPS brain protection system and fiberglass shell.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'FOX-V2-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'FOX-V2-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Flow Blue',
          'family' => 'Blue',
          'finish' => 'matte',
          'sku' => 'FOX-V2-FLO',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Raptor Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'FOX-V2-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'V1 Lux' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 179.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1350,
      'mat' => 'Polycarbonate',
      'desc' => 'Entry MX with MIPS liner and magnetic visor release system.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'FOX-V1LU-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'FOX-V1LU-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'FOX-V1LU-YEL',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Street Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'FOX-V1LU-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Proframe RS' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 299.95,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 750,
      'mat' => 'Composite',
      'desc' => 'Open-face mountain / enduro with full MIPS and ultra-light design.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'FOX-PROF-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'FOX-PROF-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'FOX-PROF-YEL',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Raptor TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'FOX-PROF-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Alpinestars' => 
  array (
    'Supertech M10' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 649.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1240,
      'mat' => 'Carbon',
      'desc' => 'Ultra-lightweight carbon MX helmet with A-Head fitment system.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'ALP-SUPE-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'ALP-SUPE-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'ALP-SUPE-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Apex Blue',
          'family' => 'Blue',
          'finish' => 'matte',
          'sku' => 'ALP-SUPE-APE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'SM5' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 289.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1350,
      'mat' => 'Polymer',
      'desc' => 'Affordable Alpinestars MX helmet with multi-density EPS.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'ALP-SM5-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'ALP-SM5-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Racer TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'ALP-SM5-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Course Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'ALP-SM5-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'S-M10' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 599.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1200,
      'mat' => 'Carbon Fiber',
      'desc' => 'Top MX with full carbon shell and A-Head fitting system.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'ALP-SM10-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'ALP-SM10-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Urban TC-3',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'ALP-SM10-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'S-M5 Action' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 249.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1150,
      'mat' => 'Composite',
      'desc' => 'Mid-tier MX with composite shell and multi-density EPS liner.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'ALP-SM5A-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'ALP-SM5A-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'ALP-SM5A-YEL',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Stripe TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'ALP-SM5A-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Klim' => 
  array (
    'Krios Pro' => 
    array (
      'type' => 'Adventure / Dual Sport',
      'price' => 749.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1300,
      'mat' => 'Carbon Fiber',
      'desc' => 'Premium carbon ADV helmet with Transitions photochromic shield.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'KLI-KRIO-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'KLI-KRIO-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Racer Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'KLI-KRIO-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Street TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'KLI-KRIO-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'F5 Koroyd' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 649.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1100,
      'mat' => 'Carbon Fiber',
      'desc' => 'Revolutionary Koroyd liner crumple zone for superior impact protection.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'KLI-F5KO-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'KLI-F5KO-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Vortex TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'KLI-F5KO-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'TK1200 Karbon' => 
    array (
      'type' => 'Modular',
      'price' => 699.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1550,
      'mat' => 'Carbon Fiber',
      'desc' => 'Carbon modular with Transitions photochromic face shield and Sena 10U.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'KLI-TK12-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'KLI-TK12-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Solid Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'KLI-TK12-SOL',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Operator TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'KLI-TK12-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'F3 Carbon' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 399.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1200,
      'mat' => 'Carbon Fiber',
      'desc' => 'Lightweight carbon MX helmet with Klim channeled EPS ventilation.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'KLI-F3CA-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'KLI-F3CA-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Stripe Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'KLI-F3CA-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Fly Racing' => 
  array (
    'Formula Carbon' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 689.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
        2 => 'Snell M2020',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1290,
      'mat' => 'Carbon Fiber',
      'desc' => 'Triple-certified carbon MX helmet with Conehead EPS technology.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'FLY-FORM-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'FLY-FORM-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'FLY-FORM-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Operator Blue',
          'family' => 'Blue',
          'finish' => 'matte',
          'sku' => 'FLY-FORM-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Kinetic' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 129.95,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1450,
      'mat' => 'Polymer',
      'desc' => 'Budget-friendly entry MX helmet with multiple shell sizes.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'FLY-KINE-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'FLY-KINE-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Flow Grey',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'FLY-KINE-FLO',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Revolt Rush' => 
    array (
      'type' => 'Full Face',
      'price' => 249.95,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1500,
      'mat' => 'Polycarbonate',
      'desc' => 'Street full face with aggressive venting and anti-fog inner shield.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'FLY-REVO-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'FLY-REVO-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'FLY-REVO-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Solid Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'FLY-REVO-SOL',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Odyssey Summit' => 
    array (
      'type' => 'Adventure / Dual Sport',
      'price' => 179.95,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1600,
      'mat' => 'Polycarbonate',
      'desc' => 'Budget dual-sport with electric shield option and peak visor.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'FLY-ODYS-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'FLY-ODYS-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'FLY-ODYS-YEL',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Vortex Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'FLY-ODYS-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  '6D' => 
  array (
    'ATR-2' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 779.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1480,
      'mat' => 'Composite',
      'desc' => 'Patented ODS 2.0 suspension system reduces rotational and angular acceleration.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => '6D-ATR2-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => '6D-ATR2-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Apex Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => '6D-ATR2-APE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Operator Grey',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => '6D-ATR2-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'ATS-1R' => 
    array (
      'type' => 'Track / Race',
      'price' => 749.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1560,
      'mat' => 'Carbon Fiber',
      'desc' => 'Carbon road race helmet with ODS technology for multi-directional impact.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => '6D-ATS1-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => '6D-ATS1-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Flow Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => '6D-ATS1-FLO',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Apex TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => '6D-ATS1-APE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'ATR-2Y' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 595.0,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1150,
      'mat' => 'Carbon-Kevlar',
      'desc' => 'Youth version of ATR-2 with ODS suspension for smaller heads.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => '6D-ATR2-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => '6D-ATR2-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Flow Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => '6D-ATR2-FLO',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Racer Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => '6D-ATR2-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'ATB-2T' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 499.0,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 950,
      'mat' => 'Carbon-Kevlar',
      'desc' => 'Trail/enduro open-face with ODS technology and extended rear coverage.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => '6D-ATB2-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => '6D-ATB2-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => '6D-ATB2-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Urban Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => '6D-ATB2-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Biltwell' => 
  array (
    'Gringo S' => 
    array (
      'type' => 'Full Face',
      'price' => 249.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Round Oval',
      'weight' => 1500,
      'mat' => 'ABS',
      'desc' => 'Classic retro styling with modern DOT/ECE certification and flat shield.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'BIL-GRIN-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'BIL-GRIN-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'BIL-GRIN-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Camo Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'BIL-GRIN-CAM',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Lane Splitter' => 
    array (
      'type' => 'Full Face',
      'price' => 299.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1550,
      'mat' => 'ABS',
      'desc' => 'Modern take on vintage full-face design with quick-release chin strap.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'BIL-LANE-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'BIL-LANE-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Vortex Grey',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'BIL-LANE-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Racer TC-3',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'BIL-LANE-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Bonanza' => 
    array (
      'type' => 'Open Face',
      'price' => 149.95,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Round Oval',
      'weight' => 1000,
      'mat' => 'ABS',
      'desc' => 'Ultra-minimalist open face helmet with hand-painted graphics options.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'BIL-BONA-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'BIL-BONA-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'BIL-BONA-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Street Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'BIL-BONA-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Lanesplitter V2' => 
    array (
      'type' => 'Full Face',
      'price' => 219.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1300,
      'mat' => 'ABS',
      'desc' => 'Updated lane-splitter with improved ventilation and ECE certification.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'BIL-LANE-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'BIL-LANE-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Racer Grey',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'BIL-LANE-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Gringo ECE' => 
    array (
      'type' => 'Full Face',
      'price' => 269.95,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Round Oval',
      'weight' => 1350,
      'mat' => 'ABS',
      'desc' => 'ECE-only version with thinner shell and lighter weight.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'BIL-GRIN-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'BIL-GRIN-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'BIL-GRIN-YEL',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Stripe Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'BIL-GRIN-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Sena' => 
  array (
    'Momentum INC Pro' => 
    array (
      'type' => 'Modular',
      'price' => 549.0,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1750,
      'mat' => 'Fiberglass',
      'desc' => 'World\'s first Smart Helmet with built-in Bluetooth and Mesh intercom.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'SEN-MOME-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'SEN-MOME-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Urban Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'SEN-MOME-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Flow TC-5',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SEN-MOME-FLO',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Outrush R' => 
    array (
      'type' => 'Modular',
      'price' => 249.0,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1680,
      'mat' => 'Polycarbonate',
      'desc' => 'Budget-friendly modular with built-in Sena Bluetooth 5.0.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'SEN-OUTR-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'SEN-OUTR-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Track Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'SEN-OUTR-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Solid TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SEN-OUTR-SOL',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Stryker' => 
    array (
      'type' => 'Full Face',
      'price' => 379.0,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1550,
      'mat' => 'Fiberglass',
      'desc' => 'Full-face with integrated mesh Bluetooth and noise-canceling microphone.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'SEN-STRY-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'SEN-STRY-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'SEN-STRY-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Operator TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SEN-STRY-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'X-Lite' => 
  array (
    'X-803 RS Ultra Carbon' => 
    array (
      'type' => 'Track / Race',
      'price' => 849.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
        1 => 'FIM',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1350,
      'mat' => 'Carbon Fiber',
      'desc' => 'MotoGP-level carbon race helmet with racing spoiler and emergency cheek pads.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'X-L-X803-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'X-L-X803-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'X-L-X803-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Street Blue',
          'family' => 'Blue',
          'finish' => 'matte',
          'sku' => 'X-L-X803-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'X-1005 Ultra Carbon' => 
    array (
      'type' => 'Modular',
      'price' => 699.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1480,
      'mat' => 'Carbon Fiber',
      'desc' => 'Premium carbon modular with P/J homologation and N-Com ready.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'X-L-X100-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'X-L-X100-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Flow TC-5',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'X-L-X100-FLO',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Tech TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'X-L-X100-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'X-552 Ultra Carbon' => 
    array (
      'type' => 'Adventure / Dual Sport',
      'price' => 749.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1500,
      'mat' => 'Carbon Fiber',
      'desc' => 'Carbon adventure helmet with peak visor and anti-fog system.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'X-L-X552-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'X-L-X552-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Solid TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'X-L-X552-SOL',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Nexx' => 
  array (
    'X.R3R' => 
    array (
      'type' => 'Track / Race',
      'price' => 699.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1350,
      'mat' => 'X-Matrix Carbon',
      'desc' => 'Portuguese-made race helmet with multi-density EPS and titanium hardware.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'NEX-X.R3-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'NEX-X.R3-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Street Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'NEX-X.R3-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Urban TC-5',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'NEX-X.R3-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'X.WED3' => 
    array (
      'type' => 'Adventure / Dual Sport',
      'price' => 599.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1550,
      'mat' => 'X-Matrix',
      'desc' => 'Adventure helmet engineered for the most demanding off-road conditions.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'NEX-X.WE-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'NEX-X.WE-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Course TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'NEX-X.WE-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Stripe TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'NEX-X.WE-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'X.Vilitur' => 
    array (
      'type' => 'Modular',
      'price' => 549.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1620,
      'mat' => 'X-Matrix',
      'desc' => 'Touring modular featuring 360-degree panoramic vision.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'NEX-X.VI-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'NEX-X.VI-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'NEX-X.VI-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Raptor Red',
          'family' => 'Red',
          'finish' => 'matte',
          'sku' => 'NEX-X.VI-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'X.G200' => 
    array (
      'type' => 'Full Face',
      'price' => 449.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Round Oval',
      'weight' => 1300,
      'mat' => 'X-Matrix',
      'desc' => 'Retro-styled full face with modern composite shell technology.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'NEX-X.G2-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'NEX-X.G2-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Operator TC-3',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'NEX-X.G2-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Flow TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'NEX-X.G2-FLO',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'SX.100R' => 
    array (
      'type' => 'Full Face',
      'price' => 249.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1500,
      'mat' => 'Polycarbonate',
      'desc' => 'Entry-level road helmet with internal sun visor and pinlock-ready shield.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'NEX-SX.1-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'NEX-SX.1-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'NEX-SX.1-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Apex TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'NEX-SX.1-APE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Caberg' => 
  array (
    'Duke X' => 
    array (
      'type' => 'Modular',
      'price' => 449.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1600,
      'mat' => 'Composite Fibre',
      'desc' => 'Italian-designed modular with DVT (Double Visor Tech) and 180° flip-up.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'CAB-DUKE-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'CAB-DUKE-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'CAB-DUKE-YEL',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Tech Red',
          'family' => 'Red',
          'finish' => 'matte',
          'sku' => 'CAB-DUKE-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Horus' => 
    array (
      'type' => 'Full Face',
      'price' => 349.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1450,
      'mat' => 'Thermoplastic',
      'desc' => 'Sporty full face with aggressive ventilation and built-in sun visor.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'CAB-HORU-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'CAB-HORU-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Track TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'CAB-HORU-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Tech TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'CAB-HORU-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Levo X' => 
    array (
      'type' => 'Modular',
      'price' => 549.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1690,
      'mat' => 'Carbon Fiber',
      'desc' => 'Carbon shell modular with panoramic visor and antibacterial lining.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'CAB-LEVO-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'CAB-LEVO-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Racer Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'CAB-LEVO-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Camo Grey',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'CAB-LEVO-CAM',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Flyon II' => 
    array (
      'type' => 'Open Face',
      'price' => 299.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1100,
      'mat' => 'Carbon Fiber',
      'desc' => 'Ultralight carbon open face weighing just 750g for urban riding.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'CAB-FLYO-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'CAB-FLYO-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'CAB-FLYO-YEL',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Flow Grey',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'CAB-FLYO-FLO',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Ruroc' => 
  array (
    'Atlas 4.0' => 
    array (
      'type' => 'Full Face',
      'price' => 449.0,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1550,
      'mat' => 'Fiberglass',
      'desc' => 'Bold design with Shockwave Bluetooth audio system and magnetic chin vent.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'RUR-ATLA-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'RUR-ATLA-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Street TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'RUR-ATLA-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Solid Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'RUR-ATLA-SOL',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Atlas 4.0 Carbon' => 
    array (
      'type' => 'Full Face',
      'price' => 599.0,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1400,
      'mat' => 'Carbon Fiber',
      'desc' => 'Premium carbon version with advanced RHEON liner technology.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'RUR-ATLA-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'RUR-ATLA-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'RUR-ATLA-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Urban TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'RUR-ATLA-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Atlas 4.0 Track' => 
    array (
      'type' => 'Track / Race',
      'price' => 649.0,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1380,
      'mat' => 'Carbon Fiber',
      'desc' => 'Track-focused carbon with aerodynamic spoiler and emergency release system.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'RUR-ATLA-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'RUR-ATLA-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Tech TC-3',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'RUR-ATLA-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Raptor TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'RUR-ATLA-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Simpson' => 
  array (
    'Outlaw Bandit' => 
    array (
      'type' => 'Full Face',
      'price' => 399.95,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Round Oval',
      'weight' => 1600,
      'mat' => 'Fiberglass',
      'desc' => 'Iconic retro race-inspired design with wide eyeport and flat shield.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'SIM-OUTL-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'SIM-OUTL-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'SIM-OUTL-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Course Red',
          'family' => 'Red',
          'finish' => 'matte',
          'sku' => 'SIM-OUTL-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Ghost Bandit' => 
    array (
      'type' => 'Full Face',
      'price' => 449.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Round Oval',
      'weight' => 1580,
      'mat' => 'Composite',
      'desc' => 'Ghost-style visor with integrated internal sun shield.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'SIM-GHOS-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'SIM-GHOS-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Racer Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'SIM-GHOS-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Tech TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SIM-GHOS-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Mod Bandit' => 
    array (
      'type' => 'Modular',
      'price' => 399.95,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Round Oval',
      'weight' => 1700,
      'mat' => 'Fiberglass',
      'desc' => 'Modular version of the Bandit with full flip-up chin bar.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'SIM-MODB-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'SIM-MODB-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Stripe TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SIM-MODB-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Track TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SIM-MODB-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Venom' => 
    array (
      'type' => 'Full Face',
      'price' => 579.95,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'Snell M2020',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1500,
      'mat' => 'Composite',
      'desc' => 'Pro-grade full face with dual Snell/DOT and FMVSS 218 certification.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'SIM-VENO-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'SIM-VENO-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Street Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'SIM-VENO-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Tech TC-5',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SIM-VENO-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Troy Lee Designs' => 
  array (
    'SE5 Carbon' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 675.0,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1200,
      'mat' => 'Carbon Fiber',
      'desc' => 'Premium carbon MX helmet with MIPS-C2 multi-impact protection.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'TRO-SE5C-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'TRO-SE5C-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'TRO-SE5C-YEL',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Racer TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'TRO-SE5C-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'SE5 Composite' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 475.0,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1350,
      'mat' => 'Composite',
      'desc' => 'Composite MX helmet with MIPS and EPP multi-density liner.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'TRO-SE5C-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'TRO-SE5C-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Apex TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'TRO-SE5C-APE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Track TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'TRO-SE5C-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'GP' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 199.0,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1400,
      'mat' => 'Polycarbonate',
      'desc' => 'Entry-level MX helmet with TLD graphics and comfort liner.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'TRO-GP-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'TRO-GP-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Flow Red',
          'family' => 'Red',
          'finish' => 'matte',
          'sku' => 'TRO-GP-FLO',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'D4 Composite' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 425.0,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1200,
      'mat' => 'Composite',
      'desc' => 'Downhill/enduro with MIPS Spherical and breakaway visor.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'TRO-D4CO-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'TRO-D4CO-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Course TC-3',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'TRO-D4CO-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Apex Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'TRO-D4CO-APE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'GP Mono' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 129.0,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1100,
      'mat' => 'Polycarbonate',
      'desc' => 'Solid color version of the GP for riders who prefer clean aesthetics.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'TRO-GPMO-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'TRO-GPMO-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Street TC-3',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'TRO-GPMO-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Solid Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'TRO-GPMO-SOL',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'GMax' => 
  array (
    'OF-77' => 
    array (
      'type' => 'Open Face',
      'price' => 109.95,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1200,
      'mat' => 'Polycarbonate',
      'desc' => 'Versatile open face with internal sun visor at an unbeatable price.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'GMA-OF77-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'GMA-OF77-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Track Blue',
          'family' => 'Blue',
          'finish' => 'matte',
          'sku' => 'GMA-OF77-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Tech Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'GMA-OF77-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'GM-11' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 99.95,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1500,
      'mat' => 'Polycarbonate',
      'desc' => 'Budget-friendly motocross helmet with dual-sport capability.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'GMA-GM11-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'GMA-GM11-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Tech TC-5',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'GMA-GM11-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Course TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'GMA-GM11-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'AT-21S' => 
    array (
      'type' => 'Adventure / Dual Sport',
      'price' => 149.95,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1650,
      'mat' => 'Polycarbonate',
      'desc' => 'Adventure helmet with integrated sun shield and removable peak.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'GMA-AT21-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'GMA-AT21-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'GMA-AT21-YEL',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Urban TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'GMA-AT21-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'FF-49' => 
    array (
      'type' => 'Full Face',
      'price' => 89.95,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1550,
      'mat' => 'Polycarbonate',
      'desc' => 'Entry-level full face with quick-release shield system.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'GMA-FF49-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'GMA-FF49-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'GMA-FF49-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Stripe TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'GMA-FF49-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'MD-01S' => 
    array (
      'type' => 'Modular',
      'price' => 179.95,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1700,
      'mat' => 'Polycarbonate',
      'desc' => 'Modular dual-sport with electric shield for cold-weather riding.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'GMA-MD01-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'GMA-MD01-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'GMA-MD01-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Tech TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'GMA-MD01-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'GM-11S' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 109.95,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1450,
      'mat' => 'Polycarbonate',
      'desc' => 'Snowmobile/MX crossover with dual lens shield and breath deflector.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'GMA-GM11-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'GMA-GM11-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'GMA-GM11-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Urban Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'GMA-GM11-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'MX-46Y' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 69.95,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1100,
      'mat' => 'Polycarbonate',
      'desc' => 'Youth-sized MX helmet with adjustable visor and washable liner.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'GMA-MX46-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'GMA-MX46-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'GMA-MX46-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Urban TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'GMA-MX46-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Z1R' => 
  array (
    'Range' => 
    array (
      'type' => 'Adventure / Dual Sport',
      'price' => 169.95,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1600,
      'mat' => 'Polycarbonate',
      'desc' => 'Dual-sport with removable peak, sun visor, and moisture-wicking liner.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'Z1R-RANG-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'Z1R-RANG-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Street TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'Z1R-RANG-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Solid TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'Z1R-RANG-SOL',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Warrant' => 
    array (
      'type' => 'Full Face',
      'price' => 129.95,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1500,
      'mat' => 'Polycarbonate',
      'desc' => 'Value-focused full face with anti-scratch shield and padded chin strap.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'Z1R-WARR-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'Z1R-WARR-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'Z1R-WARR-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Tech Red',
          'family' => 'Red',
          'finish' => 'matte',
          'sku' => 'Z1R-WARR-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Solaris' => 
    array (
      'type' => 'Modular',
      'price' => 149.95,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1700,
      'mat' => 'Polycarbonate',
      'desc' => 'Budget modular with drop-down sun visor and speaker pockets.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'Z1R-SOLA-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'Z1R-SOLA-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'Z1R-SOLA-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Course Red',
          'family' => 'Red',
          'finish' => 'matte',
          'sku' => 'Z1R-SOLA-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Rise' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 89.95,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1350,
      'mat' => 'Polycarbonate',
      'desc' => 'Youth and budget MX helmet with multi-position visor.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'Z1R-RISE-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'Z1R-RISE-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Track Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'Z1R-RISE-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Track TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'Z1R-RISE-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Speed and Strength' => 
  array (
    'SS1600' => 
    array (
      'type' => 'Full Face',
      'price' => 149.95,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1600,
      'mat' => 'Polycarbonate',
      'desc' => 'Sport-styled full face with quick-change shield and drop-down sun visor.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'SPE-SS16-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'SPE-SS16-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Track Blue',
          'family' => 'Blue',
          'finish' => 'matte',
          'sku' => 'SPE-SS16-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Stripe TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SPE-SS16-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'SS2400' => 
    array (
      'type' => 'Full Face',
      'price' => 199.95,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1500,
      'mat' => 'Fiberglass',
      'desc' => 'Upgraded fiberglass shell with enhanced ventilation and comfort.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'SPE-SS24-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'SPE-SS24-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'SPE-SS24-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Raptor TC-3',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SPE-SS24-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'SS700' => 
    array (
      'type' => 'Open Face',
      'price' => 99.95,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1100,
      'mat' => 'Polycarbonate',
      'desc' => 'Open face with internal sun visor for cruiser and urban riding.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'SPE-SS70-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'SPE-SS70-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'SPE-SS70-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Urban TC-3',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SPE-SS70-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Sedici' => 
  array (
    'Strada 3' => 
    array (
      'type' => 'Full Face',
      'price' => 199.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1550,
      'mat' => 'Polycarbonate',
      'desc' => 'Italian-designed entry-level full face with sun shield and pinlock-ready visor.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'SED-STRA-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'SED-STRA-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Street TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SED-STRA-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Apex Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'SED-STRA-APE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Strada Carbon' => 
    array (
      'type' => 'Full Face',
      'price' => 399.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1350,
      'mat' => 'Carbon Fiber',
      'desc' => 'Premium carbon shell from Sedici\'s flagship line.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'SED-STRA-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'SED-STRA-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Urban TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SED-STRA-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Street TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SED-STRA-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Primo' => 
    array (
      'type' => 'Open Face',
      'price' => 129.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1050,
      'mat' => 'Polycarbonate',
      'desc' => 'Retro-styled open face with snap-on visor for cafe racer style.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'SED-PRIM-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'SED-PRIM-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'SED-PRIM-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Operator TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SED-PRIM-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Sistema II' => 
    array (
      'type' => 'Modular',
      'price' => 249.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1700,
      'mat' => 'Polycarbonate',
      'desc' => 'Touring modular with integrated sun visor and Sena-ready speaker pockets.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'SED-SIST-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'SED-SIST-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Raptor TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SED-SIST-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Course TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'SED-SIST-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'ILM' => 
  array (
    '902BT' => 
    array (
      'type' => 'Modular',
      'price' => 109.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1700,
      'mat' => 'ABS',
      'desc' => 'Best-selling budget modular with integrated Bluetooth and FM radio.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'ILM-902B-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'ILM-902B-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'ILM-902B-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Course Grey',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'ILM-902B-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    '726X' => 
    array (
      'type' => 'Full Face',
      'price' => 89.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1550,
      'mat' => 'ABS',
      'desc' => 'Ultra-budget full face with quick-release buckle and anti-fog visor.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'ILM-726X-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'ILM-726X-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'ILM-726X-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Vortex TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'ILM-726X-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    953 => 
    array (
      'type' => 'Full Face',
      'price' => 69.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1600,
      'mat' => 'ABS',
      'desc' => 'Entry-level full face for new riders seeking basic DOT protection.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'ILM-953-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'ILM-953-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Stripe TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'ILM-953-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Street TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'ILM-953-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Joe Rocket' => 
  array (
    'RKT-Prime' => 
    array (
      'type' => 'Full Face',
      'price' => 119.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1600,
      'mat' => 'Polycarbonate',
      'desc' => 'Affordable full face with anti-fog shield and removable liner.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'JOE-RKTP-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'JOE-RKTP-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Raptor Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'JOE-RKTP-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Vortex Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'JOE-RKTP-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Airoh' => 
  array (
    'GP550 S' => 
    array (
      'type' => 'Track / Race',
      'price' => 499.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1250,
      'mat' => 'HPC Composite',
      'desc' => 'Italian racetrack helmet with carbon-aramid shell and multi-density EPS.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'AIR-GP55-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'AIR-GP55-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Racer Blue',
          'family' => 'Blue',
          'finish' => 'matte',
          'sku' => 'AIR-GP55-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Matryx' => 
    array (
      'type' => 'Full Face',
      'price' => 599.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1200,
      'mat' => 'HPC Carbon',
      'desc' => 'Carbon sport helmet with Airoh Progressive Compound shell tech.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'AIR-MATR-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'AIR-MATR-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'AIR-MATR-YEL',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Course TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'AIR-MATR-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Commander 2' => 
    array (
      'type' => 'Adventure / Dual Sport',
      'price' => 549.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1550,
      'mat' => 'HRT Composite',
      'desc' => 'Adventure modular with peak visor, sun shield, and GPS mount.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'AIR-COMM-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'AIR-COMM-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'AIR-COMM-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Flow TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'AIR-COMM-FLO',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Aviator 3' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 699.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1100,
      'mat' => 'Carbon Kevlar',
      'desc' => 'Pro-level MX helmet with AMS2 system and emergency cheek pad release.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'AIR-AVIA-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'AIR-AVIA-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Vortex TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'AIR-AVIA-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Stripe Red',
          'family' => 'Red',
          'finish' => 'matte',
          'sku' => 'AIR-AVIA-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Spark 2' => 
    array (
      'type' => 'Full Face',
      'price' => 349.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1400,
      'mat' => 'HRT Composite',
      'desc' => 'Sport touring with integrated sun visor and Pinlock included.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'AIR-SPAR-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'AIR-SPAR-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Street Red',
          'family' => 'Red',
          'finish' => 'matte',
          'sku' => 'AIR-SPAR-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Helios' => 
    array (
      'type' => 'Open Face',
      'price' => 229.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1100,
      'mat' => 'HPC Composite',
      'desc' => 'Lightweight jet helmet with retractable sun visor and Bluetooth ready.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'AIR-HELI-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'AIR-HELI-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'AIR-HELI-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Raptor Red',
          'family' => 'Red',
          'finish' => 'matte',
          'sku' => 'AIR-HELI-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Twist 3' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 179.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1350,
      'mat' => 'HRT Thermoplastic',
      'desc' => 'Entry-level MX with excellent ventilation and replaceable liner.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'AIR-TWIS-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'AIR-TWIS-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Urban Blue',
          'family' => 'Blue',
          'finish' => 'matte',
          'sku' => 'AIR-TWIS-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'TRR S' => 
    array (
      'type' => 'Adventure / Dual Sport',
      'price' => 299.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1500,
      'mat' => 'HRT Thermoplastic',
      'desc' => 'Dual-sport with removable chin guard for open-face conversion.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'AIR-TRRS-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'AIR-TRRS-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Camo TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'AIR-TRRS-CAM',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Tech TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'AIR-TRRS-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'MT Helmets' => 
  array (
    'Thunder 4 SV' => 
    array (
      'type' => 'Full Face',
      'price' => 199.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1450,
      'mat' => 'HPFC Composite',
      'desc' => 'Spanish sport helmet with Max Vision visor and multi-density EPS.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'MTH-THUN-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'MTH-THUN-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'MTH-THUN-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Operator TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'MTH-THUN-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Atom 2 SV' => 
    array (
      'type' => 'Modular',
      'price' => 249.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1650,
      'mat' => 'HPFC Composite',
      'desc' => 'P/J-rated modular with wide panoramic visor and speaker pockets.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'MTH-ATOM-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'MTH-ATOM-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Vortex TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'MTH-ATOM-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Rapide Pro' => 
    array (
      'type' => 'Full Face',
      'price' => 159.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1350,
      'mat' => 'Fiberglass',
      'desc' => 'Aggressive street design with pinlock-ready shield at a great price.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'MTH-RAPI-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'MTH-RAPI-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Course TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'MTH-RAPI-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Apex Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'MTH-RAPI-APE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'District SV' => 
    array (
      'type' => 'Open Face',
      'price' => 99.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1100,
      'mat' => 'Thermoplastic',
      'desc' => 'Urban jet helmet with retractable sun visor for city commuters.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'MTH-DIST-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'MTH-DIST-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'MTH-DIST-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Flow TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'MTH-DIST-FLO',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Falcon' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 129.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1250,
      'mat' => 'Thermoplastic',
      'desc' => 'Entry-level MX with large eye port for goggle compatibility.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'MTH-FALC-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'MTH-FALC-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'MTH-FALC-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Track TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'MTH-FALC-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Streetfighter SV' => 
    array (
      'type' => 'Full Face',
      'price' => 179.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1500,
      'mat' => 'HPFC Composite',
      'desc' => 'Aggressive naked-bike helmet with dark visor and spoiler.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'MTH-STRE-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'MTH-STRE-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Tech Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'MTH-STRE-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Operator Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'MTH-STRE-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Suomy' => 
  array (
    'SR-GP Evo' => 
    array (
      'type' => 'Track / Race',
      'price' => 799.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
        1 => 'FIM',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1350,
      'mat' => 'Carbon Fiber',
      'desc' => 'FIM-homologated race helmet worn in World Superbike Championship.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'SUO-SRGP-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'SUO-SRGP-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'SUO-SRGP-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Apex TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SUO-SRGP-APE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Speedstar' => 
    array (
      'type' => 'Full Face',
      'price' => 499.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1450,
      'mat' => 'Tri-Fiber',
      'desc' => 'Sport road helmet with wide visor and advanced channeling ventilation.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'SUO-SPEE-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'SUO-SPEE-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'SUO-SPEE-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Tech Grey',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'SUO-SPEE-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'MX-Speed Pro' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 399.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1150,
      'mat' => 'Carbon Fiber',
      'desc' => 'Lightweight carbon motocross helmet with ALCANTARA interior.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'SUO-MXSP-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'SUO-MXSP-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'SUO-MXSP-YEL',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Vortex TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'SUO-MXSP-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Stellar' => 
    array (
      'type' => 'Full Face',
      'price' => 349.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1500,
      'mat' => 'Fiberglass',
      'desc' => 'Feature-rich mid-range with double visor and anti-allergic liner.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'SUO-STEL-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'SUO-STEL-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'SUO-STEL-YEL',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Vortex Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'SUO-STEL-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Lazer' => 
  array (
    'Rafale SR' => 
    array (
      'type' => 'Full Face',
      'price' => 449.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1400,
      'mat' => 'Fiberglass',
      'desc' => 'Belgian sport helmet with Optivision visor and PCM cooling liner.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'LAZ-RAFA-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'LAZ-RAFA-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'LAZ-RAFA-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Solid TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'LAZ-RAFA-SOL',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Monaco Evo 2.0' => 
    array (
      'type' => 'Modular',
      'price' => 499.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1650,
      'mat' => 'Fiberglass',
      'desc' => 'Flip-up touring helmet with 180° rotation and P/J certification.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'LAZ-MONA-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'LAZ-MONA-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'LAZ-MONA-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Vortex Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'LAZ-MONA-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Vertigo Evo' => 
    array (
      'type' => 'Open Face',
      'price' => 199.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1050,
      'mat' => 'Polycarbonate',
      'desc' => 'Urban open-face with drop-down visor and removable chin guard option.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'LAZ-VERT-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'LAZ-VERT-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'LAZ-VERT-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Stripe TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'LAZ-VERT-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'OR-3' => 
    array (
      'type' => 'Adventure / Dual Sport',
      'price' => 349.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1550,
      'mat' => 'Composite',
      'desc' => 'Dual-sport adventure with removable peak and Pinlock-ready visor.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'LAZ-OR3-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'LAZ-OR3-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'LAZ-OR3-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Apex TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'LAZ-OR3-APE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'OGK Kabuto' => 
  array (
    'Aeroblade 6' => 
    array (
      'type' => 'Full Face',
      'price' => 549.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1400,
      'mat' => 'ACT Composite',
      'desc' => 'Japanese precision with ultra-aerodynamic shell and Wake Stabilizer.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'OGK-AERO-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'OGK-AERO-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Street TC-3',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'OGK-AERO-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Tech Red',
          'family' => 'Red',
          'finish' => 'matte',
          'sku' => 'OGK-AERO-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Kamui-3' => 
    array (
      'type' => 'Full Face',
      'price' => 349.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1500,
      'mat' => 'Composite',
      'desc' => 'All-around sport helmet with internal sun visor and UV-cut shield.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'OGK-KAMU-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'OGK-KAMU-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Track Blue',
          'family' => 'Blue',
          'finish' => 'matte',
          'sku' => 'OGK-KAMU-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Flow Blue',
          'family' => 'Blue',
          'finish' => 'matte',
          'sku' => 'OGK-KAMU-FLO',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Exceed' => 
    array (
      'type' => 'Open Face',
      'price' => 279.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1100,
      'mat' => 'Composite',
      'desc' => 'Premium jet helmet with IR-cut shield and large eye port.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'OGK-EXCE-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'OGK-EXCE-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Street Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'OGK-EXCE-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Apex TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'OGK-EXCE-APE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Shuma' => 
    array (
      'type' => 'Full Face',
      'price' => 449.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1350,
      'mat' => 'ACT Composite',
      'desc' => 'Track-capable with spoiler and dual-density EPS for racing use.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'OGK-SHUM-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'OGK-SHUM-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'OGK-SHUM-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Raptor TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'OGK-SHUM-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Premier' => 
  array (
    'Trophy' => 
    array (
      'type' => 'Full Face',
      'price' => 399.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1350,
      'mat' => 'Fiberglass',
      'desc' => 'Italian retro-classic full face inspired by 1970s motorsport heritage.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'PRE-TROP-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'PRE-TROP-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'PRE-TROP-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Flow TC-3',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'PRE-TROP-FLO',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Vintage' => 
    array (
      'type' => 'Open Face',
      'price' => 249.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1000,
      'mat' => 'Fiberglass',
      'desc' => 'Authentic retro open face with chrome trim and custom paint options.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'PRE-VINT-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'PRE-VINT-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'PRE-VINT-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Tech TC-5',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'PRE-VINT-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Hyper Carbon' => 
    array (
      'type' => 'Full Face',
      'price' => 599.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1200,
      'mat' => 'Carbon Fiber',
      'desc' => 'Modern sport design with full carbon shell and titanium hardware.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'PRE-HYPE-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'PRE-HYPE-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'PRE-HYPE-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Stripe Red',
          'family' => 'Red',
          'finish' => 'matte',
          'sku' => 'PRE-HYPE-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'JT5' => 
    array (
      'type' => 'Open Face',
      'price' => 179.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1050,
      'mat' => 'Polycarbonate',
      'desc' => 'Classic cafe racer style with peak and interchangeable visor system.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'PRE-JT5-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'PRE-JT5-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Course Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'PRE-JT5-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Street TC-5',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'PRE-JT5-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'ROOF' => 
  array (
    'Boxxer 2' => 
    array (
      'type' => 'Modular',
      'price' => 549.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1500,
      'mat' => 'Composite',
      'desc' => 'Unique chin bar slides upward over the shell — French engineering at its finest.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'ROO-BOXX-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'ROO-BOXX-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'ROO-BOXX-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Camo TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'ROO-BOXX-CAM',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'RO200 Carbon' => 
    array (
      'type' => 'Full Face',
      'price' => 499.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1200,
      'mat' => 'Carbon Fiber',
      'desc' => 'Full carbon race replica with panoramic visor and aerodynamic profile.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'ROO-RO20-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'ROO-RO20-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Course Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'ROO-RO20-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Course Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'ROO-RO20-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Roadster Classic' => 
    array (
      'type' => 'Open Face',
      'price' => 329.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Round Oval',
      'weight' => 1100,
      'mat' => 'Fiberglass',
      'desc' => 'Heritage open-face with goggles for cafe racer and scrambler riders.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'ROO-ROAD-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'ROO-ROAD-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Course TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'ROO-ROAD-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Flow TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'ROO-ROAD-FLO',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Torc' => 
  array (
    'T-1 Retro' => 
    array (
      'type' => 'Full Face',
      'price' => 249.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1400,
      'mat' => 'Fiberglass',
      'desc' => 'Vintage-inspired full face with wide eye port and flat shield.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'TOR-T1RE-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'TOR-T1RE-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Course TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'TOR-T1RE-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Solid TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'TOR-T1RE-SOL',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'T-15B' => 
    array (
      'type' => 'Full Face',
      'price' => 299.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1500,
      'mat' => 'Fiberglass',
      'desc' => 'Bluetooth-integrated full face with 3D speakers and 10-hour battery.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'TOR-T15B-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'TOR-T15B-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'TOR-T15B-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Tech TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'TOR-T15B-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'T-55' => 
    array (
      'type' => 'Open Face',
      'price' => 149.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1050,
      'mat' => 'Fiberglass',
      'desc' => 'Cafe racer open face with classic bubble shield compatibility.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'TOR-T55-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'TOR-T55-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Urban TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'TOR-T55-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'T-14B' => 
    array (
      'type' => 'Full Face',
      'price' => 279.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1500,
      'mat' => 'Fiberglass',
      'desc' => 'Mako-style full face with built-in Bluetooth and sun lens.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'TOR-T14B-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'TOR-T14B-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'TOR-T14B-YEL',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Urban Grey',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'TOR-T14B-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Daytona' => 
  array (
    'Skull Cap' => 
    array (
      'type' => 'Half',
      'price' => 59.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Round Oval',
      'weight' => 750,
      'mat' => 'ABS',
      'desc' => 'Ultra-slim DOT half helmet — one of the smallest profiles available.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'DAY-SKUL-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'DAY-SKUL-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'DAY-SKUL-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Stripe TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'DAY-SKUL-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Cruiser' => 
    array (
      'type' => 'Half',
      'price' => 79.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Round Oval',
      'weight' => 850,
      'mat' => 'Polycarbonate',
      'desc' => 'Classic cruiser half with inner sun shield and padded comfort liner.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'DAY-CRUI-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'DAY-CRUI-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Course TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'DAY-CRUI-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Glide' => 
    array (
      'type' => 'Modular',
      'price' => 149.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1650,
      'mat' => 'Polycarbonate',
      'desc' => 'Budget modular with flip-up chin and inner sun visor.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'DAY-GLID-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'DAY-GLID-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'DAY-GLID-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Raptor TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'DAY-GLID-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Detour' => 
    array (
      'type' => 'Full Face',
      'price' => 99.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1500,
      'mat' => 'Polycarbonate',
      'desc' => 'Entry full face with quick-release shield and ventilation.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'DAY-DETO-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'DAY-DETO-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'DAY-DETO-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Stripe Grey',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'DAY-DETO-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Zox' => 
  array (
    'Condor SVS' => 
    array (
      'type' => 'Modular',
      'price' => 169.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1650,
      'mat' => 'Polycarbonate',
      'desc' => 'Double sun visor modular at a competitive price point.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'ZOX-COND-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'ZOX-COND-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Street Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'ZOX-COND-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Primo C' => 
    array (
      'type' => 'Full Face',
      'price' => 89.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1500,
      'mat' => 'Polycarbonate',
      'desc' => 'No-frills DOT full face — great starter helmet for new riders.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'ZOX-PRIM-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'ZOX-PRIM-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'ZOX-PRIM-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Vortex Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'ZOX-PRIM-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Brigade SVS' => 
    array (
      'type' => 'Full Face',
      'price' => 129.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1500,
      'mat' => 'Polycarbonate',
      'desc' => 'Mid-range full face with internal drop-down sun visor.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'ZOX-BRIG-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'ZOX-BRIG-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Racer TC-5',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'ZOX-BRIG-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Track Grey',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'ZOX-BRIG-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Route 66' => 
    array (
      'type' => 'Half',
      'price' => 59.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Round Oval',
      'weight' => 800,
      'mat' => 'ABS',
      'desc' => 'Classic cruiser half helmet with snap-on face shield option.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'ZOX-ROUT-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'ZOX-ROUT-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Track TC-5',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'ZOX-ROUT-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'GIVI' => 
  array (
    'X.27 Tourer' => 
    array (
      'type' => 'Adventure / Dual Sport',
      'price' => 349.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1600,
      'mat' => 'Composite',
      'desc' => 'Touring-adventure with massive visor, peak, and integrated Bluetooth prep.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'GIV-X.27-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'GIV-X.27-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Operator Blue',
          'family' => 'Blue',
          'finish' => 'matte',
          'sku' => 'GIV-X.27-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Racer TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'GIV-X.27-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    '50.8 Racer' => 
    array (
      'type' => 'Full Face',
      'price' => 249.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1450,
      'mat' => 'Thermoplastic',
      'desc' => 'Sporty road helmet with spoiler and double-D ring closure.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'GIV-50.8-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'GIV-50.8-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Vortex TC-5',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'GIV-50.8-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Track TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'GIV-50.8-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'X.21 Challenger' => 
    array (
      'type' => 'Modular',
      'price' => 299.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1700,
      'mat' => 'Composite',
      'desc' => 'P/J modular with wide flip-up mechanism and micro-ratchet buckle.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'GIV-X.21-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'GIV-X.21-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'GIV-X.21-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Vortex Red',
          'family' => 'Red',
          'finish' => 'matte',
          'sku' => 'GIV-X.21-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    '12.5 Solid' => 
    array (
      'type' => 'Open Face',
      'price' => 99.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1000,
      'mat' => 'Thermoplastic',
      'desc' => 'Compact jet with retractable visor for scooter and city commuting.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'GIV-12.5-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'GIV-12.5-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Operator Grey',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'GIV-12.5-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Camo TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'GIV-12.5-CAM',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Studds' => 
  array (
    'Thunder D7' => 
    array (
      'type' => 'Full Face',
      'price' => 49.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1400,
      'mat' => 'ABS',
      'desc' => 'Popular budget full face for emerging markets with anti-scratch visor.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'STU-THUN-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'STU-THUN-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'STU-THUN-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Course Red',
          'family' => 'Red',
          'finish' => 'matte',
          'sku' => 'STU-THUN-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Shifter D8' => 
    array (
      'type' => 'Full Face',
      'price' => 59.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1350,
      'mat' => 'ABS',
      'desc' => 'Sporty design with large air vents and hypoallergenic padding.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'STU-SHIF-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'STU-SHIF-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Operator Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'STU-SHIF-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Urban Blue',
          'family' => 'Blue',
          'finish' => 'gloss',
          'sku' => 'STU-SHIF-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Marshall' => 
    array (
      'type' => 'Open Face',
      'price' => 34.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 950,
      'mat' => 'ABS',
      'desc' => 'Budget-friendly open face for commuters with adjustable visor.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'STU-MARS-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'STU-MARS-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'STU-MARS-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Urban TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'STU-MARS-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Motocross D5' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 44.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1250,
      'mat' => 'ABS',
      'desc' => 'Affordable off-road helmet with large eye port and ABS shell.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'STU-MOTO-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'STU-MOTO-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Raptor Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'STU-MOTO-RAP',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Steelbird' => 
  array (
    'SBA-21 GT' => 
    array (
      'type' => 'Full Face',
      'price' => 69.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1450,
      'mat' => 'ABS',
      'desc' => 'Indian-made full face with chrome visor option and GT styling.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'STE-SBA2-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'STE-SBA2-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'STE-SBA2-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Urban Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'STE-SBA2-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'SBH-40 Trip' => 
    array (
      'type' => 'Adventure / Dual Sport',
      'price' => 89.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1500,
      'mat' => 'ABS',
      'desc' => 'Budget adventure helmet with removable peak and dual visor.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'STE-SBH4-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'STE-SBH4-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'STE-SBH4-YEL',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Track TC-5',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'STE-SBH4-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Adonis' => 
    array (
      'type' => 'Open Face',
      'price' => 29.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 900,
      'mat' => 'Polycarbonate',
      'desc' => 'Ultra-budget open face for daily commuting in warm climates.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'STE-ADON-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'STE-ADON-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Operator TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'STE-ADON-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Vortex TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'STE-ADON-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'SBA-7' => 
    array (
      'type' => 'Full Face',
      'price' => 54.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1400,
      'mat' => 'ABS',
      'desc' => 'Multi-color graphic options with internal sun shield.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'STE-SBA7-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'STE-SBA7-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'STE-SBA7-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Operator Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'STE-SBA7-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Vega' => 
  array (
    'Crux DX' => 
    array (
      'type' => 'Full Face',
      'price' => 39.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1350,
      'mat' => 'ABS',
      'desc' => 'Feature-packed budget full face with double visor and breath guard.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'VEG-CRUX-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'VEG-CRUX-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'VEG-CRUX-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Apex TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'VEG-CRUX-APE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Lark' => 
    array (
      'type' => 'Open Face',
      'price' => 24.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Round Oval',
      'weight' => 850,
      'mat' => 'ABS',
      'desc' => 'Lightest open face in its class — perfect for short city rides.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'VEG-LARK-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'VEG-LARK-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Camo Red',
          'family' => 'Red',
          'finish' => 'matte',
          'sku' => 'VEG-LARK-CAM',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Tech TC-3',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'VEG-LARK-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Off-Road D/V' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 34.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1200,
      'mat' => 'ABS',
      'desc' => 'Budget off-road with adjustable visor and removable liner.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'VEG-OFFR-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'VEG-OFFR-YEL',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Racer TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'VEG-OFFR-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Course TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'VEG-OFFR-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Boolean Flip-Up' => 
    array (
      'type' => 'Modular',
      'price' => 59.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1600,
      'mat' => 'ABS',
      'desc' => 'Most affordable modular with basic flip-up mechanism and sun visor.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'VEG-BOOL-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'VEG-BOOL-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Flow Grey',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'VEG-BOOL-FLO',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Vortex TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'VEG-BOOL-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Bilmola' => 
  array (
    'Defender' => 
    array (
      'type' => 'Full Face',
      'price' => 149.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1400,
      'mat' => 'Fiberglass',
      'desc' => 'Thai-made sport helmet with pinlock-ready visor and dual-density EPS.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'BIL-DEFE-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'BIL-DEFE-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'BIL-DEFE-YEL',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Apex Grey',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'BIL-DEFE-APE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Explorer' => 
    array (
      'type' => 'Adventure / Dual Sport',
      'price' => 179.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1550,
      'mat' => 'Fiberglass',
      'desc' => 'Budget adventure helmet with removable peak and chin curtain.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'BIL-EXPL-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'BIL-EXPL-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'BIL-EXPL-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Street Red',
          'family' => 'Red',
          'finish' => 'matte',
          'sku' => 'BIL-EXPL-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Nex' => 
    array (
      'type' => 'Open Face',
      'price' => 89.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1000,
      'mat' => 'ABS',
      'desc' => 'Urban open face with inner visor and air-channel ventilation.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'BIL-NEX-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'BIL-NEX-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'BIL-NEX-GRE',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Urban Red',
          'family' => 'Red',
          'finish' => 'gloss',
          'sku' => 'BIL-NEX-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'WCL' => 
  array (
    'Modular Full Face' => 
    array (
      'type' => 'Modular',
      'price' => 109.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1650,
      'mat' => 'ABS',
      'desc' => 'Amazon-popular modular with Bluetooth-ready speaker pockets.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'WCL-MODU-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'WCL-MODU-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Urban TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'WCL-MODU-URB',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Racer TC-3',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'WCL-MODU-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Raider Full Face' => 
    array (
      'type' => 'Full Face',
      'price' => 79.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1500,
      'mat' => 'ABS',
      'desc' => 'Starter full face with quick-release buckle and clear shield.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'WCL-RAID-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'WCL-RAID-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Tech TC-3',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'WCL-RAID-TEC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Camo TC-2',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'WCL-RAID-CAM',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Beanie Half' => 
    array (
      'type' => 'Half',
      'price' => 39.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Round Oval',
      'weight' => 700,
      'mat' => 'ABS',
      'desc' => 'Super-slim DOT half helmet for short-distance cruiser rides.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'WCL-BEAN-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'WCL-BEAN-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'WCL-BEAN-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Street TC-2',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'WCL-BEAN-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'CKX' => 
  array (
    'Tranz 1.5 AMS' => 
    array (
      'type' => 'Modular',
      'price' => 299.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1700,
      'mat' => 'Polycarbonate',
      'desc' => 'Canadian-designed modular with electric shield for cold-weather riding.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'CKX-TRAN-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'CKX-TRAN-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Track TC-5',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'CKX-TRAN-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Titan Original' => 
    array (
      'type' => 'Full Face',
      'price' => 249.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1600,
      'mat' => 'Polycarbonate',
      'desc' => 'Snowmobile-crossover full face with electric lens and breath box.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'CKX-TITA-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'CKX-TITA-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Course TC-1',
          'family' => 'Multi',
          'finish' => 'gloss',
          'sku' => 'CKX-TITA-COU',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Racer Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'CKX-TITA-RAC',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'TX696' => 
    array (
      'type' => 'Dirt / MX',
      'price' => 179.99,
      'cert' => 
      array (
        0 => 'DOT',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1350,
      'mat' => 'Polycarbonate',
      'desc' => 'Four-season off-road with electric shield and dual-sport capability.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'CKX-TX69-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'CKX-TX69-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Solid Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'CKX-TX69-SOL',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
        3 => 
        array (
          'name' => 'Flow Grey',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'CKX-TX69-FLO',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Mission AMS' => 
    array (
      'type' => 'Adventure / Dual Sport',
      'price' => 349.99,
      'cert' => 
      array (
        0 => 'DOT',
        1 => 'ECE 22.05',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1650,
      'mat' => 'Composite',
      'desc' => 'All-season adventure with carbon fiber chin bar and heated shield.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'CKX-MISS-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Anthracite',
          'family' => 'Grey',
          'finish' => 'matte',
          'sku' => 'CKX-MISS-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'CKX-MISS-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Vortex TC-1',
          'family' => 'Multi',
          'finish' => 'matte',
          'sku' => 'CKX-MISS-VOR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
  'Vemar' => 
  array (
    'Hurricane Claw' => 
    array (
      'type' => 'Full Face',
      'price' => 399.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1400,
      'mat' => 'Tri-Composite',
      'desc' => 'Italian sport helmet with advanced cooling channels and quick-release visor.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'VEM-HURR-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Gloss White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'VEM-HURR-WHI',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'VEM-HURR-YEL',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Street Blue',
          'family' => 'Blue',
          'finish' => 'matte',
          'sku' => 'VEM-HURR-STR',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Kona' => 
    array (
      'type' => 'Adventure / Dual Sport',
      'price' => 349.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1600,
      'mat' => 'Fiberglass',
      'desc' => 'Dual-sport with removable peak, Pinlock visor, and anti-fog system.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Matte Black',
          'family' => 'Black',
          'finish' => 'matte',
          'sku' => 'VEM-KONA-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'VEM-KONA-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Pearl White',
          'family' => 'White',
          'finish' => 'gloss',
          'sku' => 'VEM-KONA-WHI',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Operator Blue',
          'family' => 'Blue',
          'finish' => 'matte',
          'sku' => 'VEM-KONA-OPE',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
    'Sharki' => 
    array (
      'type' => 'Modular',
      'price' => 329.99,
      'cert' => 
      array (
        0 => 'ECE 22.06',
      ),
      'shape' => 'Intermediate Oval',
      'weight' => 1650,
      'mat' => 'Composite',
      'desc' => 'Feature-packed modular with double sun visor and intercom cavity.',
      'colorways' => 
      array (
        0 => 
        array (
          'name' => 'Gloss Black',
          'family' => 'Black',
          'finish' => 'gloss',
          'sku' => 'VEM-SHAR-BLK',
          'price_adj' => 0,
        ),
        1 => 
        array (
          'name' => 'Silver Metallic',
          'family' => 'Grey',
          'finish' => 'gloss',
          'sku' => 'VEM-SHAR-GRE',
          'price_adj' => 0,
        ),
        2 => 
        array (
          'name' => 'Hi-Viz Yellow',
          'family' => 'Yellow',
          'finish' => 'gloss',
          'sku' => 'VEM-SHAR-YEL',
          'price_adj' => 0,
        ),
        3 => 
        array (
          'name' => 'Track Blue',
          'family' => 'Blue',
          'finish' => 'matte',
          'sku' => 'VEM-SHAR-TRA',
          'price_adj' => 50,
          'is_graphic' => true,
        ),
      ),
    ),
  ),
);

$output = [];

foreach ($brands as $brandName => $models) {
    foreach ($models as $modelName => $specs) {
        $modelId = strtolower(str_replace([' ', '-', '.', '/'], '_', $brandName . '_' . $modelName));
        
        // Generate Variants from Colorways
        $variants = [];
        
        foreach ($specs['colorways'] as $cw) {
            $variantSlug = strtolower(str_replace([' ', '/'], '-', $cw['name']));
            $variantId = $modelId . '_' . $variantSlug;
            
            // Calculate multi-currency
            $vPrice = $specs['price'] + ($cw['price_adj'] ?? 0);
            $priceEur = round($vPrice * 0.92, 2);
            $priceGbp = round($vPrice * 0.79, 2);
            
            $variants[] = [
                'id' => $variantId,
                'title' => $brandName . ' ' . $modelName . ' ' . $cw['name'],
                'type' => 'variant',
                'parent_id' => $modelId,
                'color' => $cw['name'],
                'color_family' => $cw['family'] ?? 'Multi',
                'sku' => $cw['sku'],
                'finish' => $cw['finish'] ?? 'gloss',
                'is_graphic' => $cw['is_graphic'] ?? false,
                'availability' => $cw['availability'] ?? 'instock',
                'mpn' => $cw['sku'], // Use SKU as MPN
                'price' => [
                    'usd' => $vPrice,
                    'eur' => $priceEur,
                    'gbp' => $priceGbp
                ],
                'specs' => [], // Inherited
                'attributes' => [
                    'sizes' => ['XS', 'S', 'M', 'L', 'XL', '2XL'],
                    'color' => $cw['name']
                ],
                'images' => [],
                'stock_status' => 'instock'
            ];
            
            // Handle Carbon special specs override
            if (isset($cw['family']) && $cw['family'] === 'Carbon') {
                $variants[count($variants)-1]['specs']['material'] = 'Carbon Fiber';
                $variants[count($variants)-1]['specs']['weight_g'] = $specs['weight'] - 100;
            }
        }
        
        // Parent Item
        $item = [
            'id' => $modelId,
            'title' => $brandName . ' ' . $modelName,
            'brand' => $brandName,
            'type' => $specs['type'],
            'helmet_family' => $modelName,
            'price' => [
                'current' => $specs['price'],
                'currency' => 'USD'
            ],
            'certifications' => $specs['cert'],
            'specs' => [
                'material' => $specs['mat'],
                'weight_g' => $specs['weight'],
                'weight_lbs' => round($specs['weight'] / 453.592, 2),
                'shape' => $specs['shape'],
            ],
            'description' => $specs['desc'],
            'variants' => $variants
        ];
        
        $output[] = $item;
    }
}

// ── Output ─────────────────────────────────────────────────────────────
$jsonData = json_encode($output, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);

// Validation Mode
if (isset($opts['validate'])) {
    $ids = array_column($output, 'id');
    $uniqueIds = array_unique($ids);
    $dupes = count($ids) - count($uniqueIds);
    
    // Check variant ID uniqueness
    $allVariantIds = [];
    foreach ($output as $p) {
        foreach ($p['variants'] as $v) {
            $allVariantIds[] = $v['id'];
        }
    }
    $uniqueVarIds = array_unique($allVariantIds);
    $varDupes = count($allVariantIds) - count($uniqueVarIds);
    
    fwrite(STDERR, "\n=== Seed Validation ===\n");
    fwrite(STDERR, "Parent models:  " . count($output) . "\n");
    fwrite(STDERR, "Variants:       " . count($allVariantIds) . "\n");
    fwrite(STDERR, "Total posts:    " . (count($output) + count($allVariantIds)) . "\n");
    fwrite(STDERR, "Brands:         " . count($brands) . "\n");
    
    if ($dupes === 0 && $varDupes === 0) {
        fwrite(STDERR, "✅ No duplicate IDs\n");
    } else {
        fwrite(STDERR, "❌ DUPLICATE IDs Found (Parents: $dupes, Variants: $varDupes)\n");
        if ($varDupes > 0) {
             $counts = array_count_values($allVariantIds);
             foreach ($counts as $id => $c) {
                 if ($c > 1) fwrite(STDERR, "   - $id\n");
             }
        }
        exit(1);
    }
    exit(0);
}

// Write Output
if (isset($opts['output'])) {
    file_put_contents($opts['output'], $jsonData);
    if (isset($opts['stats'])) {
        fwrite(STDERR, "Written to: " . $opts['output'] . " (" . strlen($jsonData) . " bytes)\n");
    }
} else {
    echo $jsonData;
}

// Split Dir
if (isset($opts['split-dir'])) {
    $dir = rtrim($opts['split-dir'], '/');
    if (!is_dir($dir)) mkdir($dir, 0755, true);
    // Cleanup old files
    array_map('unlink', glob("$dir/*.json"));
    
    foreach ($output as $item) {
        $filename = $dir . '/' . $item['id'] . '.json';
        file_put_contents($filename, json_encode($item, JSON_PRETTY_PRINT));
    }
    if (isset($opts['stats'])) fwrite(STDERR, "Split JSON files written to $dir/\n");
}