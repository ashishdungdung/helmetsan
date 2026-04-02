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

## PHP 8.1+ Standards

- **Strict Typing**: Use `declare(strict_types=1);` in all logic files.
- **Hook-Driven Logic**: Place complex logic in the plugin (`helmetsan-core`) and use actions/filters to expose it to the theme.
- **Templating**: Always use `template-parts/` for modular, reusable UI fragments (e.g., `card-helmet.php`).

## Verified Patterns

- **Catalog Filters**: AJAX response should return a JSON object with `html` (posts) and `counts` (active filters).
- **Dark Mode**: Managed via `theme-toggle.js` setting `[data-theme="dark"]` on `<html>`. Switch colors via CSS Variables.
- **Accessibility**: Ensure 4.5:1 contrast for all "Electric" accents against "Midnight" backgrounds.
