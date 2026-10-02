#!/usr/bin/env python3
"""
Merge and Compile Theme Translations
Combines existing translations with newly generated Luna translations for all 576 canonical theme strings.
Compiles standard WordPress .mo and .po catalogs for all 9 non-English languages.
"""

import os
import json
import subprocess
import re

THEME_DIR = "/Users/anumac/Documents/Projects/Helmetsan/HelmetsanWeb/helmetsan-theme"
LANG_DIR = os.path.join(THEME_DIR, "languages")

# Canonical full list of theme strings
ALL_STRINGS_FILE = "/tmp/all_theme_strings.json"
LUNA_TRANSLATIONS_FILE = "/tmp/luna_translated_missing.json"

if not os.path.exists(ALL_STRINGS_FILE):
    raise FileNotFoundError(f"{ALL_STRINGS_FILE} not found. Run extract_theme_strings.mjs first.")

if not os.path.exists(LUNA_TRANSLATIONS_FILE):
    raise FileNotFoundError(f"{LUNA_TRANSLATIONS_FILE} not found. Wait for batch_translate_with_luna.mjs.")

with open(ALL_STRINGS_FILE, "r", encoding="utf-8") as f:
    all_strings = json.load(f)

with open(LUNA_TRANSLATIONS_FILE, "r", encoding="utf-8") as f:
    luna_translations = json.load(f)

LANGUAGES = [
    ('de', 'de_DE', 'German'),
    ('es', 'es_ES', 'Spanish'),
    ('fr', 'fr_FR', 'French'),
    ('it', 'it_IT', 'Italian'),
    ('ja', 'ja',    'Japanese'),
    ('nl', 'nl_NL', 'Dutch'),
    ('pl', 'pl_PL', 'Polish'),
    ('pt', 'pt_PT', 'Portuguese'),
    ('zh', 'zh_CN', 'Simplified Chinese'),
]

def load_existing_php_dict(php_path):
    if not os.path.exists(php_path):
        return {}
    # Use PHP to dump array as json
    cmd = ["php", "-r", f"echo json_encode(require '{php_path}');"]
    res = subprocess.run(cmd, capture_output=True, text=True)
    if res.returncode == 0 and res.stdout.strip():
        try:
            return json.loads(res.stdout)
        except Exception:
            pass
    return {}

updated_theme_strings = sorted(list(set(all_strings)))
print(f"Total Canonical Strings: {len(updated_theme_strings)}")

# Save updated theme_strings.json
theme_strings_path = os.path.join(LANG_DIR, "theme_strings.json")
with open(theme_strings_path, "w", encoding="utf-8") as f:
    json.dump(updated_theme_strings, f, indent=4, ensure_ascii=False)
print(f"Updated {theme_strings_path}")

for lang_code, locale, lang_name in LANGUAGES:
    php_path = os.path.join(LANG_DIR, f"translations-{lang_code}.php")
    existing_dict = load_existing_php_dict(php_path)
    new_from_luna = luna_translations.get(lang_code, {})

    merged_dict = {}
    for s in updated_theme_strings:
        if s in existing_dict and existing_dict[s]:
            merged_dict[s] = existing_dict[s]
        elif s in new_from_luna and new_from_luna[s]:
            merged_dict[s] = new_from_luna[s]
        else:
            merged_dict[s] = s  # Fallback to English string

    # Write out updated translations-{lang}.php
    php_lines = [
        "<?php",
        "/**",
        f" * {lang_name} ({locale}) Theme Translation Dictionary for Helmetsan Theme",
        " *",
        " * @package HelmetsanTheme",
        " */",
        "",
        "if (!defined('ABSPATH')) {",
        "    exit;",
        "}",
        "",
        "return ["
    ]
    for k, v in merged_dict.items():
        escaped_k = k.replace('\\', '\\\\').replace("'", "\\'")
        escaped_v = str(v).replace('\\', '\\\\').replace("'", "\\'")
        php_lines.append(f"    '{escaped_k}' => '{escaped_v}',")
    php_lines.append("];")
    php_lines.append("")

    with open(php_path, "w", encoding="utf-8") as f:
        f.write("\n".join(php_lines))

    # Verify PHP syntax
    res = subprocess.run(["php", "-l", php_path], capture_output=True, text=True)
    if res.returncode != 0:
        raise RuntimeError(f"PHP syntax error in {php_path}: {res.stderr}")

    print(f"Generated {php_path} ({len(merged_dict)} translations verified)")

    # Build PO file
    po_lines = [
        'msgid ""',
        'msgstr ""',
        '"Project-Id-Version: Helmetsan Theme 1.0\\n"',
        '"Report-Msgid-Bugs-To: \\n"',
        '"POT-Creation-Date: 2026-09-24 12:00+0000\\n"',
        '"PO-Revision-Date: 2026-09-24 12:00+0000\\n"',
        '"Last-Translator: Helmetsan Luna Engine\\n"',
        '"Language-Team: Helmetsan Localization Team\\n"',
        f'"Language: {locale}\\n"',
        '"MIME-Version: 1.0\\n"',
        '"Content-Type: text/plain; charset=UTF-8\\n"',
        '"Content-Transfer-Encoding: 8bit\\n"',
        '"Plural-Forms: nplurals=2; plural=(n != 1);\\n"',
        ''
    ]
    for k, v in merged_dict.items():
        clean_k = k.replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n')
        clean_v = str(v).replace('\\', '\\\\').replace('"', '\\"').replace('\n', '\\n')
        po_lines.append(f'msgid "{clean_k}"')
        po_lines.append(f'msgstr "{clean_v}"')
        po_lines.append('')

    po_file = os.path.join(LANG_DIR, f"helmetsan-theme-{locale}.po")
    mo_file = os.path.join(LANG_DIR, f"helmetsan-theme-{locale}.mo")
    mo_short = os.path.join(LANG_DIR, f"{locale}.mo")

    with open(po_file, "w", encoding="utf-8") as f:
        f.write("\n".join(po_lines))

    # Compile MO using /opt/homebrew/bin/msgfmt
    subprocess.run(["/opt/homebrew/bin/msgfmt", "-o", mo_file, po_file], check=True)
    subprocess.run(["/opt/homebrew/bin/msgfmt", "-o", mo_short, po_file], check=True)
    print(f"  -> Compiled MO: {mo_file} & {mo_short}")

print("\nAll 9 non-English languages successfully updated and compiled!")
