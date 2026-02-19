<?php
/**
 * Accessory archive template.
 *
 * @package HelmetsanTheme
 */

get_header();

$search = isset($_GET['s']) ? sanitize_text_field((string) $_GET['s']) : '';
$category = isset($_GET['accessory_category']) ? sanitize_text_field((string) $_GET['accessory_category']) : '';

// Auto-select category if on taxonomy page
$queriedObject = get_queried_object();
if ($queriedObject instanceof WP_Term && $queriedObject->taxonomy === 'accessory_category' && $category === '') {
    $category = $queriedObject->slug;
}

$helmetType = isset($_GET['helmet_type']) ? sanitize_text_field((string) $_GET['helmet_type']) : '';
$pinlock = isset($_GET['pinlock_ready']) ? sanitize_text_field((string) $_GET['pinlock_ready']) : '';
$electric = isset($_GET['electric']) ? sanitize_text_field((string) $_GET['electric']) : '';
$snow = isset($_GET['snow']) ? sanitize_text_field((string) $_GET['snow']) : '';

$categories = get_terms([
    'taxonomy' => 'accessory_category',
    'hide_empty' => false, // Changed to false to show all discovered categories
]);
$helmetTypes = get_terms([
    'taxonomy' => 'helmet_type',
    'hide_empty' => true,
]);

$themeDir = get_stylesheet_directory_uri();
$hero_img = $themeDir . '/assets/images/hubs/accessories_hub_hero.png';

$is_discovery_mode = (empty($search) && empty($category) && empty($helmetType) && empty($pinlock) && empty($electric) && empty($snow));
?>

<section class="hs-section">
    <header class="hs-section__head">
        <h1><?php echo esc_html(single_term_title('', false)); ?></h1>
        <?php if (term_description()) : ?>
            <div class="hs-section__desc"><?php echo term_description(); ?></div>
        <?php else : ?>
            <p>Accessory catalog with compatibility metadata and feature tags.</p>
        <?php endif; ?>
    </header>

    <form class="hs-filter-bar" method="get" action="<?php echo esc_url(get_post_type_archive_link('accessory')); ?>">
        <input type="search" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search accessories" />
        <select name="accessory_category">
            <option value="">All Accessory Categories</option>
            <?php
            if (is_array($categories)) :
                foreach ($categories as $term) :
                    if (! ($term instanceof WP_Term)) {
                        continue;
                    }
                    ?>
                    <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($category, $term->slug); ?>><?php echo esc_html($term->name); ?></option>
                <?php endforeach;
            endif;
            ?>
        </select>
        <select name="helmet_type">
            <option value="">Helmet Type Compatibility</option>
            <?php
            if (is_array($helmetTypes)) :
                foreach ($helmetTypes as $term) :
                    if (! ($term instanceof WP_Term)) {
                        continue;
                    }
                    ?>
                    <option value="<?php echo esc_attr($term->slug); ?>" <?php selected($helmetType, $term->slug); ?>><?php echo esc_html($term->name); ?></option>
                <?php endforeach;
            endif;
            ?>
        </select>
        <select name="pinlock_ready">
            <option value="">Pinlock Ready: Any</option>
            <option value="1" <?php selected($pinlock, '1'); ?>>Pinlock Ready</option>
            <option value="0" <?php selected($pinlock, '0'); ?>>Not Pinlock Ready</option>
        </select>
        <select name="electric">
            <option value="">Electric: Any</option>
            <option value="1" <?php selected($electric, '1'); ?>>Electric Compatible</option>
            <option value="0" <?php selected($electric, '0'); ?>>Non-Electric</option>
        </select>
        <select name="snow">
            <option value="">Snow: Any</option>
            <option value="1" <?php selected($snow, '1'); ?>>Snow Compatible</option>
            <option value="0" <?php selected($snow, '0'); ?>>Non-Snow</option>
        </select>
        <button class="hs-btn hs-btn--primary" type="submit">Apply</button>
    </form>

    <?php if ($is_discovery_mode && !empty($categories)) : ?>
        <div class="helmet-grid">
            <?php foreach ($categories as $term) : 
                $title = $term->name;
                $desc = $term->description ?: "Explore our " . $term->name . " collection.";
                $link = get_term_link($term);
                $img = $themeDir . '/assets/images/hubs/accessory_category/' . $term->slug . '.png';
                
                // Fallback for image
                if (!file_exists(get_stylesheet_directory() . '/assets/images/hubs/accessory_category/' . $term->slug . '.png')) {
                    $img = $hero_img;
                }
            ?>
                <a href="<?php echo esc_url($link); ?>" class="hs-discovery-card">
                    <article class="hs-discovery-card__inner">
                        <div class="hs-discovery-card__image-container">
                            <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($title); ?>" class="hs-discovery-card__image">
                            <span class="hs-discovery-card__badge"><?php echo esc_html($term->count); ?> Items</span>
                        </div>
                        <div class="hs-discovery-card__content">
                            <h3 class="hs-discovery-card__title"><?php echo esc_html($title); ?></h3>
                            <p class="hs-discovery-card__description"><?php echo esc_html($desc); ?></p>
                            <span class="hs-discovery-card__cta">View Catalog <svg viewBox="0 0 24 24"><path d="M5 12h14m-7-7 7 7-7 7"/></svg></span>
                        </div>
                    </article>
                </a>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <?php
        $args = [
            'post_type' => 'accessory',
            'post_status' => 'publish',
            'posts_per_page' => 18,
            'paged' => max(1, get_query_var('paged')),
            'orderby' => 'title',
            'order' => 'ASC',
        ];
        if ($search !== '') {
            $args['s'] = $search;
        }
        $taxQuery = [];
        $currentTerm = get_queried_object();
        if ($currentTerm instanceof WP_Term && ! isset($_GET['accessory_category'])) {
           $category = $currentTerm->slug;
        }

        if ($category !== '') {
            $taxQuery[] = [
                'taxonomy' => 'accessory_category',
                'field' => 'slug',
                'terms' => $category,
            ];
        }
        if ($helmetType !== '') {
            $taxQuery[] = [
                'taxonomy' => 'helmet_type',
                'field' => 'slug',
                'terms' => $helmetType,
            ];
        }
        if ($taxQuery !== []) {
            $args['tax_query'] = $taxQuery;
        }
        $metaQuery = [];
        if ($pinlock !== '') {
            $metaQuery[] = ['key' => 'accessory_pinlock_ready', 'value' => $pinlock === '1' ? '1' : '0'];
        }
        if ($electric !== '') {
            $metaQuery[] = ['key' => 'accessory_electric_compatible', 'value' => $electric === '1' ? '1' : '0'];
        }
        if ($snow !== '') {
            $metaQuery[] = ['key' => 'accessory_snow_compatible', 'value' => $snow === '1' ? '1' : '0'];
        }
        if ($metaQuery !== []) {
            $args['meta_query'] = $metaQuery;
        }
        $query = new WP_Query($args);
        if ($query->have_posts()) : ?>
            <div class="helmet-grid">
                <?php while ($query->have_posts()) : $query->the_post(); ?>
                    <?php get_template_part('template-parts/entity', 'card'); ?>
                <?php endwhile; ?>
            </div>
            <?php
            $addArgs = [];
            foreach (['s', 'accessory_category', 'helmet_type', 'pinlock_ready', 'electric', 'snow'] as $argKey) {
                if (! isset($_GET[$argKey])) {
                    continue;
                }
                $addArgs[$argKey] = sanitize_text_field(wp_unslash((string) $_GET[$argKey]));
            }
            the_posts_pagination([
                'total' => $query->max_num_pages,
                'add_args' => array_filter($addArgs, static fn(string $v): bool => $v !== ''),
            ]);
            wp_reset_postdata();
            ?>
        <?php else : ?>
            <p>No accessories found.</p>
        <?php endif; ?>
    <?php endif; ?>
</section>

<?php
get_footer();
