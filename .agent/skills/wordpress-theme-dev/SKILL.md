# Skill: Advanced WordPress Theme Development

A specialized instruction set for building modern, high-speed, hook-driven WordPress themes with the **"Midnight & Electric"** design language.

## Design Philosophy

- **Midnight & Electric Aesthetic**:
    - **Backgrounds**: Deep, curated blacks and dark greys (e.g., `#0a0a0b`).
    - **Accents**: High-contrast, vibrant electric colors (e.g., Electric Blue `#0070f3`, Neon Purple).
    - **UI Pattern**: "App-centric UX"—dense information cards, glassmorphism, and interactive data visualizations.
- **Typography**: **Inter** (humanist sans-serif) for high-density readability.
- **Aesthetics First**: Every UI element must have clear hover states, subtle micro-animations (0.2s ease), and premium spacing (Modular Scale).

## Architecture & Performance

- **GeneratePress-Hybrid Strategy**:
    - Use GeneratePress as the lightweight core.
    - Build functionality via **GeneratePress Hooks** (`generate_before_content`, etc.) to keep parent theme files untouched.
- **Modular Styles (Vanilla CSS)**:
    - Use **Design Tokens** (CSS Variables) for all colors, spacing, and transitions.
    - Avoid utility-heavy frameworks unless strictly requested. Use standard `base.css`, `components.css`, and `pages.css`.
- **Zero-Dependency Interactivity**:
    - Prioritize **Vanilla JS** over jQuery or heavy frameworks.
    - Use **Chart.js v4** for high-performance data visualization.
    - Use native **WordPress AJAX/REST API** for dynamic catalog filtering.
- **Asset Optimization**:
    - Efficiently enqueue only what is needed per page.
    - Use `filemtime` for dynamic versioning/cache-busting.
    - **Path Resolver Rule**: Always use `get_stylesheet_directory_uri()` for child theme assets. Do not use `get_template_directory_uri()`, which points to the parent theme (GeneratePress) and causes 404 errors.

## PHP 8.1+ Standards

- **Strict Typing**: Use `declare(strict_types=1);` in all logic files.
- **Hook-Driven Logic**: Place complex logic in the plugin (`helmetsan-core`) and use actions/filters to expose it to the theme.
- **Templating**: Always use `template-parts/` for modular, reusable UI fragments (e.g., `card-helmet.php`).
- **No Fake/Slop UI**: Omit any UI buttons or visual indicators (e.g. "Earn Rewards") that do not have operational backend systems in place.

## Verified Patterns

- **Catalog Filters (AJAX)**: AJAX response should return a JSON object with `html` (posts) and `counts` (active filters).
- **Premium Filter UI (archive-helmet.php + pages.css + filters.js)**:
  - Collapsible summaries with SVG icons, selection badges, and animated chevrons.
  - Price Range inputs displaying local currency symbol (`$`, `€`, `£`) as absolute prefix.
  - Custom checkboxes utilizing `accent-color` targeting the theme's core primary accent.
  - Sticky footer container displaying visual actions: Clear All (X icon) and Show Results (Search icon).
- **Certification SVG Badges**: Render official standard SVGs (`assets/images/certifications/`) dynamically on single templates. Ensure high-contrast click and hover scaling effects are configured.
- **Parent CPT Query Safety**: Any query fetching lists of helmets (homepage featured items, archive widgets, recommendations) must explicitly include `'post_parent' => 0` to prevent child variants from polluting layout elements.
- **Dark Mode**: Managed via `theme-toggle.js` setting `[data-theme="dark"]` on `<html>`. Switch colors via CSS Variables.
- **Accessibility**: Ensure 4.5:1 contrast for all "Electric" accents against "Midnight" backgrounds.

