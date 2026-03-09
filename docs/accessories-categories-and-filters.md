# Accessory categories and filters

How **accessory_category** and filters work, and how to fix "Nothing Found" when filtering (e.g. by Audio Kits).

## Why do "Browse by category" cards show 0 Items?

Each card shows the **number of accessories assigned to that category term** (`accessory_category` taxonomy). If you have many accessories but most cards show "0 Items", those accessories **do not have that term assigned** yet.

**Fix (on the server):**

1. **Create/update category terms** (if you haven’t already):
   ```bash
   wp helmetsan seed-accessory-categories
   ```
   (Or run `wp eval-file scripts/seed_accessory_categories.php` from the repo root.)

2. **Assign every accessory to a category** from its meta (`accessory_type`, `accessory_parent_category`, `accessory_subcategory`):
   ```bash
   wp --path=/var/www/helmetsan.com/public backfill-accessory-categories --allow-root
   ```
   This maps each accessory’s type/parent category to one of the canonical category names and sets the term. After this, category counts on the cards will reflect real assignments.

3. If many accessories still have **no** type/parent_category/subcategory in the DB, use **AI fill-missing** then backfill again:
   ```bash
   wp helmetsan ai fill-missing --post-type=accessory --only-incomplete --allow-root
   wp helmetsan backfill-accessory-categories --allow-root
   ```
   Fill-missing uses the AI module (Helmetsan → AI) to set `accessory_type`, `accessory_parent_category`, `accessory_subcategory`, and `accessory_color` from the product title/context. Parent category is constrained to canonical names so backfill can assign the term. Then run backfill to assign the taxonomy term to each accessory.

4. If backfill reports **"had no mapping"** for many accessories (e.g. they have type/parent set to values like "Variant" that don’t match the canonical list), use **refill-unmapped** so AI overwrites those fields with canonical-style values, then backfill again:
   ```bash
   wp helmetsan ai fill-missing --post-type=accessory --refill-unmapped --allow-root
   wp helmetsan backfill-accessory-categories --allow-root
   ```
   `--refill-unmapped` includes accessories that have **no** `accessory_category` term and re-fills `accessory_type` and `accessory_parent_category` even when those meta fields are already set, so the backfill mapper can assign a term.

## Why there are two “category” sections (product type vs brand)

Pages that show both **“Browse by category”** (e.g. Accessories) and **“Our brands”** / **“Brands”** are not duplicating the same thing:

- **Accessory (or product) categories** = by **product type**: Visors, Communications, Goggles, Cleaning, etc. These are `accessory_category` terms and are used on `/accessories/` under “Browse by category.”
- **Brands** = by **manufacturer**: SHOEI, ARAI, Cardo, etc. These are the `brand` post type and are used in “Brands” / “Our brands” sections.

So one axis is “what kind of product,” the other is “who makes it.” To avoid the feeling of “double categories,” the theme labels them clearly: e.g. “Browse by category” with sub “By product type (visors, comms, care, etc.)” and “Brands” with sub “Shop by manufacturer.” Do **not** put helmet types (Full Face, Modular, etc.) into `accessory_category`; those belong to the `helmet_type` taxonomy and to the Helmets section, not the Accessories category grid.

## Why "Nothing Found" happens

The `/accessories/` archive filters by **taxonomy** `accessory_category` (slug, e.g. `audio-kits`). If no published accessories have that term assigned, the filter returns zero results.

Terms are created by **seeding** (see below). Assignments come from:

1. **Ingestion** — JSON payload `parent_category`, `subcategory`, or (when those are missing) a mapping from `type` to a category term name.
2. **Backfill** — For existing accessories that have no category, category is inferred from meta: `accessory_parent_category`, `accessory_subcategory`, `accessory_type`, or by slug lookup.

## Canonical category list (Browse by category)

These match the mega menu and filter panel. Slugs are derived from names (e.g. "Audio Kits" → `audio-kits`).

**Communication & Tech:** Bluetooth Headsets, Mesh Intercoms, Helmet Cameras, Audio Kits, GPS Navigation, Smart Helmet Add-ons  

**Visors & Optics:** Face Shields, Pinlock Inserts, Tear-Offs, Goggles, Replacement Lenses, Anti-Fog Solutions, Sun Visors  

**Comfort & Care:** Cheek Pads, Liners, Helmet Cleaners, Visor Cleaners, Helmet Bags, Balaclavas, Breath Guards  

**Safety & Parts:** Breath Boxes, Peak Visors, Replacement Vents, Pivot Kits, Chin Curtains, Reflective Stickers  

Plus: Communications, Maintenance & Care, Electronics, Inner Liners (legacy/generic).

## Fix filters: seed terms then backfill

On the server (WordPress root, e.g. `/var/www/helmetsan.com/public`):

```bash
# 1. Create or update all accessory_category terms (with correct slugs)
wp helmetsan seed-accessory-categories

# 2. Assign category to every accessory from type/parent_category/subcategory
wp helmetsan backfill-accessory-categories

# If you changed the mapping and want to re-apply for all accessories:
wp helmetsan backfill-accessory-categories --force
```

After this, filters like `?accessory_category[]=audio-kits` will return accessories that have the "Audio Kits" category assigned.

## Type → category mapping (plugin)

`AccessoryService::mapTypeToAccessoryCategory()` maps payload `type` (and backfill uses parent_category, subcategory) to one of the canonical category **names**. Examples: "Bluetooth Headset" → "Bluetooth Headsets", "Hearing Protection" → "Audio Kits", "Pinlock" → "Pinlock Inserts". If your JSON uses different strings, add them to the mapping in `helmetsan-core/includes/Accessory/AccessoryService.php` or ensure `parent_category` / `subcategory` in the payload use the exact category names above.

## Helmet type compatibility filter

Accessories can have **helmet_type** terms assigned (e.g. "Full Face", "Modular") so the "Helmet type compatibility" filter works. Those come from ingestion payload `compatible_helmet_types` (normalized via `HelmetTypeNormalizer`). Backfill does not set helmet_type; use ingestion or a dedicated script if you need to backfill compatibility.

## Compatible helmets / brands (JSON)

You can store which specific helmets or brands an accessory fits in meta (for display or future filters):

- **compatible_helmet_types** — taxonomy terms (already supported).
- **compatible_brands_json** — array of brand names or slugs (already in ingestion).
- **compatible_helmet_families_json** — array of family names (already in ingestion).
- **compatible_helmets_json** — optional array of helmet post IDs or `_helmet_unique_id` values; can be maintained in JSON and ingested for "compatible with these helmets" links.

Schema and ingestion can be extended to support `compatible_helmets_json` if you add it to accessory JSON and to `AccessoryService` / `MetaRegistrar`.

## Entity card and "variant" label

Accessory cards use **accessory_category** terms for the category tags and "View product" link. The "variant" label on some cards was likely from meta or a taxonomy term; the card now shows category tags and meta (type/parent/subcategory) so the label reflects real data. If you still see "variant" anywhere, it may be from a term or meta value that you can rename or unset.

## Reseed and SEO

After fixing categories:

1. **Reseed** — If you change JSON (e.g. add `parent_category` or `compatible_helmets`), run ingestion for accessories (Data / Reseed → path `accessories` or sync pull with apply).
2. **SEO** — Run `wp helmetsan seo seed --post-type=accessory` (optionally `--use-ai`) so Yoast title/meta/focus keyword are set for accessory pages.
3. **Fill-missing** — For blank meta (e.g. descriptions): `wp helmetsan ai fill-missing --post-type=accessory --limit=0`.

See [Enrichment process](enrichment-process.md) and [Data flow](data-flow.md).
