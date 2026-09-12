# Helmetsan Navigation & Menu Configuration

This document provides a technical guide to the navigation system, including the registered menu locations, Polylang multi-lingual configuration, the custom Mega Menu walker, and layout styling.

---

## 1. Registered Menu Locations

The theme registers nine menu locations in [setup.php](file:///Users/anumac/Documents/%20Projects/Helmetsan/helmetsan-theme/inc/setup.php#L23-L34):

| Location Identifier | Theme Location Label | Associated Database Menu Slug | Notes |
|:---|:---|:---|:---|
| `primary` | Primary Menu | `helmetsan-primary` | Main desktop header menu |
| `secondary` | Secondary Menu | `helmetsan-secondary` | Top bar right links |
| `footer` | Footer Menu | `helmetsan-footer` | Main footer columns |
| `legal` | Legal Menu | `helmetsan-legal` | Footer bottom-line links |
| `social` | Social Menu | *N/A* | Custom social icon list |
| `mega_brands` | Mega Menu: Brands | `brands-mega-menu` | Mega menu dropdown for Brands |
| `mega_accessories` | Mega Menu: Accessories | `accessories-mega-menu` | Mega menu dropdown for Accessories |
| `mega_motorcycles` | Mega Menu: Motorcycles | `motorcycles-mega-menu` | Mega menu dropdown for Motorcycles |
| `mega_helmets` | Mega Menu: Helmets | *N/A* | Falls back to JSON catalog |

---

## 2. Polylang Compatibility

When the **Polylang** multi-lingual plugin is active, it intercepts standard theme location queries (`theme_mods_helmetsan-theme` -> `nav_menu_locations`). Instead of retrieving the standard WordPress mappings, it stores locale-specific overrides in the global `polylang` option under the `nav_menus` key.

### Option Format
The mappings are stored in the database in the `polylang` option under the following nested array structure:
```json
{
  "nav_menus": {
    "helmetsan-theme": {
      "location_name": {
        "en": 36,
        "de": 36,
        "zh": 36
      }
    }
  }
}
```
If a new locale is added or the plugin's menu settings get reset, these location mappings can go blank, causing the desktop mega menus or navigation bars to disappear.

### Synchronizing Mappings
To easily repair or replicate the mappings across all configured locales, use the standalone utility script:
```bash
wp --path=/var/www/helmetsan.com/public --allow-root eval-file /var/www/helmetsan.com/public/scripts/sync-polylang-menus.php
```
This script automatically:
1. Fetches all active Polylang language codes.
2. Resolves registered theme menu locations to the correct database menus by slug.
3. Implements **smart language fallback mapping**: it checks if a localized menu exists matching the pattern `{default-slug}-{lang}` (e.g. `helmetsan-primary-de`). If so, it maps it; if not, it automatically falls back to the default English menu (e.g. `helmetsan-primary`), preventing empty or vanished menus.
4. Writes the language-to-menu ID mappings to the `polylang` option.

---

## 3. Mega Menu Architecture

### Custom Walker
The header primary navigation uses a custom menu walker, [Helmetsan_Mega_Menu_Walker](file:///Users/anumac/Documents/%20Projects/Helmetsan/helmetsan-theme/inc/class-mega-menu-walker.php), which intercepts menu items containing specific CSS classes:
- `mega-menu--helmets`
- `mega-menu--brands`
- `mega-menu--accessories`
- `mega-menu--motorcycles`

When these classes are detected on a top-level menu item, the walker appends `aria-haspopup="true"` to the anchor link and calls `helmetsan_render_mega_menu($type)` at the end of the element to output the mega menu container block.

### Rendering Engine & Fallbacks
The [helmetsan_render_mega_menu](file:///Users/anumac/Documents/%20Projects/Helmetsan/helmetsan-theme/inc/template-tags.php#L700-L821) function resolves the dropdown content in two ways:

1. **WordPress Nav Menu Tree (Preferred)**:
   It checks the location matching `mega_{type}` (e.g. `mega_brands`). If mapped, it fetches the menu items (`wp_get_nav_menu_items`), builds a nested tree (parent menu items as column headings, child items as links), and outputs a responsive grid.
2. **JSON Catalog (Fallback)**:
   If no navigation menu is assigned, it searches for a fallback catalog file `data/catalogs/{type}-mega-menu.json` in the upload folder or theme assets. (Currently, the `mega_helmets` location utilizes the `helmet-mega-menu.json` fallback).

---

## 4. CSS & Layout Styling

### Desktop Layout (≥ 961px)
To present a premium full-width look:
* The `.site-header__inner` container uses CSS grid:
  ```css
  display: grid;
  grid-template-columns: 1fr auto 1fr;
  align-items: center;
  ```
  This places the brand logo on the far left, centers the primary navigation menu, and aligns the utility actions (search, dark/light toggle) on the far right.
* Top-level list items with mega menus are styled as `position: static !important` (see [mega-menu.css](file:///Users/anumac/Documents/%20Projects/Helmetsan/helmetsan-theme/assets/css/mega-menu.css#L12-L14)).
* The mega menu block `.hs-mega-menu` is positioned absolutely:
  ```css
  position: absolute;
  left: 0;
  right: 0;
  top: 100%;
  width: 100%;
  ```
  This forces the dropdown to span the full width of the viewport/sticky header rather than wrapping within the parent menu list item.

### Mobile Layout (≤ 960px)
When resized to mobile viewports:
* The desktop grid structure is deactivated. The primary menu converts into a full-screen immersive navigation drawer overlay (`.hs-primary-nav`) with a glassmorphism background (`var(--hs-panel)` with large backdrop blur).
* The mega menu is rendered as an accordion using native HTML `<details>` and `<summary>` tags with helper styles (`.hs-mobile-nav-group`). Top-level headings function as summary labels that toggle child lists vertically with pure CSS rotation indicators.
