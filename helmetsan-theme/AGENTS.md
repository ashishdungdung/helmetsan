# helmetsan-theme: Theme Architecture & Frontend Standards

This rule applies specifically to the `helmetsan-theme/` theme subsystem.

## 1. GeneratePress-Hybrid Hook Architecture
- Helmetsan uses a GeneratePress-hybrid architecture.
- Inject custom layouts via GeneratePress action hooks (`generate_before_content`, `generate_after_content`, etc.).
- Never modify parent theme files.

## 2. CSS Design Tokens & Bundle Pipeline
- Use CSS Variables defined in `assets/css/design-tokens.css` and `base.css` for colors, spacing, and transitions.
- Source stylesheets: `assets/css/components.css`, `pages.css`, `base.css`, `mega-menu.css`.
- **CRITICAL**: After editing any source CSS file, always recompile the production bundle:
  ```bash
  python3 scripts/bundle_and_minify_css.py
  ```
- **Never edit or read `assets/css/helmetsan-bundle.min.css` directly.**

## 3. Asset URI Invariant
- Always use `get_stylesheet_directory_uri()` for child theme assets, images, and scripts.
- Never use `get_template_directory_uri()`, which resolves to the parent theme (GeneratePress) and causes 404 errors.

## 4. Vanilla JavaScript & Dynamic Hydration
- Zero heavy frontend frameworks. Use focused Vanilla JS modules in `assets/js/`.
- Client-side geotargeting and currency selection dynamically read `window.helmetsan_geo_config` without page reload.
