<?php
/**
 * Dealer CPT archive template - Interactive Map Locator.
 *
 * @package HelmetsanTheme
 */

get_header();

// Fetch active brands for filtering
$brandsQuery = new WP_Query([
    'post_type'      => 'brand',
    'post_status'    => 'publish',
    'posts_per_page' => -1,
    'orderby'        => 'title',
    'order'          => 'ASC',
]);
?>
<section class="hs-section hs-section--locator" style="max-width: var(--hs-max-width, 1200px); margin: 0 auto; padding: var(--hs-sp-10) var(--hs-sp-4);">
    <header class="hs-section__head" style="margin-bottom: var(--hs-sp-8);">
        <p class="hs-eyebrow">Certified Network</p>
        <h1 style="font-size: var(--hs-fs-3xl); font-weight: 700; color: var(--hs-text); margin: 0 0 var(--hs-sp-2) 0;"><?php echo esc_html(post_type_archive_title('', false)); ?></h1>
        <p class="hs-text-muted" style="font-size: 1.1rem; max-width: 600px;">Find authorized retailers, fitting specialists, and official dealers stocking genuine helmets and accessories near you.</p>
    </header>

    <div class="hs-locator-container">
        <!-- Sidebar Filter & Listing Pane -->
        <aside class="hs-locator-sidebar">
            <div class="hs-locator-filter-pane">
                <div class="hs-locator-search-wrap">
                    <input type="text" id="hs-locator-search" placeholder="Search city or store name..." aria-label="Search city or store" />
                </div>
                <div class="hs-locator-selects-row">
                    <select id="hs-locator-brand" aria-label="Filter by Brand">
                        <option value="">— All Brands —</option>
                        <?php if ($brandsQuery->have_posts()) : while ($brandsQuery->have_posts()) : $brandsQuery->the_post(); ?>
                            <option value="<?php the_title_attribute(); ?>"><?php the_title(); ?></option>
                        <?php endwhile; wp_reset_postdata(); endif; ?>
                    </select>
                    <select id="hs-locator-type" aria-label="Filter by Type">
                        <option value="">— All Types —</option>
                        <option value="physical">Physical Stores</option>
                        <option value="online">Online Only</option>
                    </select>
                </div>
                <div class="hs-locator-theme-toggle" style="display: flex; gap: var(--hs-sp-2); margin: var(--hs-sp-1) 0 var(--hs-sp-2) 0;">
                    <button type="button" class="hs-btn hs-btn--sm hs-btn--ghost hs-locator-theme-btn hs-locator-theme-btn--active" data-theme="light" style="flex: 1; justify-content: center; font-size: var(--hs-fs-xs);">☀️ Light Map</button>
                    <button type="button" class="hs-btn hs-btn--sm hs-btn--ghost hs-locator-theme-btn" data-theme="dark" style="flex: 1; justify-content: center; font-size: var(--hs-fs-xs);">🌙 Dark Map</button>
                </div>
                <button type="button" id="hs-locator-geolocation-btn" class="hs-btn hs-btn--ghost" style="width: 100%; justify-content: center; gap: 6px;">
                    📍 Near Me
                </button>
            </div>

            <!-- Scrollable list of matched stores -->
            <div class="hs-locator-list-pane" id="hs-locator-store-list">
                <div class="hs-locator-no-results">Initializing locator map...</div>
            </div>
        </aside>

        <!-- Dynamic Map Container -->
        <main class="hs-locator-map-pane">
            <div id="hs-store-locator-map" aria-label="Store locator map"></div>
        </main>
    </div>
</section>
<?php
get_footer();
