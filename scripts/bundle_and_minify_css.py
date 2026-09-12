#!/usr/bin/env python3
"""
Bundle and minify core CSS stylesheets for HelmetsanTheme.
Collapses design-tokens, base, components, mega-menu, and pages into a single production bundle.
"""

import os
import re

ROOT_DIR = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
THEME_DIR = os.path.join(ROOT_DIR, "helmetsan-theme")
CSS_DIR = os.path.join(THEME_DIR, "assets", "css")

FILES_TO_BUNDLE = [
    os.path.join(CSS_DIR, "design-tokens.css"),
    os.path.join(CSS_DIR, "base.css"),
    os.path.join(CSS_DIR, "components.css"),
    os.path.join(CSS_DIR, "mega-menu.css"),
    os.path.join(CSS_DIR, "pages.css"),
]

def minify_css(css: str) -> str:
    # Strip comments
    css = re.sub(r"/\*.*?\*/", "", css, flags=re.DOTALL)
    # Collapse whitespace
    css = re.sub(r"\s+", " ", css)
    # Remove space around delimiters
    css = re.sub(r"\s*([:;{}])\s*", r"\1", css)
    # Remove trailing semicolons
    css = re.sub(r";}", "}", css)
    return css.strip()

def build_bundle():
    bundled = "/* Helmetsan Production Stylesheet Bundle - Auto-generated */\n"
    for file_path in FILES_TO_BUNDLE:
        if os.path.exists(file_path):
            with open(file_path, "r", encoding="utf-8") as fp:
                minified = minify_css(fp.read())
                bundled += "\n" + minified
        else:
            print(f"⚠️ Warning: File not found: {file_path}")

    out_file = os.path.join(CSS_DIR, "helmetsan-bundle.min.css")
    with open(out_file, "w", encoding="utf-8") as fp:
        fp.write(bundled.strip())

    size_kb = os.path.getsize(out_file) / 1024
    print(f"✅ Generated {out_file} ({size_kb:.1f} KB)")

if __name__ == "__main__":
    build_bundle()
