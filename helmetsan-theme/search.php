<?php
/**
 * Search results template.
 *
 * @package HelmetsanTheme
 */

get_header();
?>
<section class="hs-section hs-section--archive">
    <header class="hs-archive-hero">
        <h1 class="hs-archive-hero__title">
            <?php printf(esc_html__('Search Results for: %s', 'helmetsan-theme'), '<span>' . get_search_query() . '</span>'); ?>
        </h1>
        <div class="hs-archive-hero__content">
            <form role="search" method="get" class="hs-search-bar" action="<?php echo esc_url( home_url( '/' ) ); ?>" style="margin-top: 1.5rem; margin-bottom: 0;">
                <label class="screen-reader-text" for="hs-page-search"><?php echo _x( 'Search for:', 'label', 'helmetsan-theme' ); ?></label>
                <input type="search" id="hs-page-search" placeholder="<?php echo esc_attr_x( 'Search helmets, brands, accessories...', 'placeholder', 'helmetsan-theme' ); ?>" value="<?php echo get_search_query(); ?>" name="s" />
                <button type="submit" class="hs-btn">Search</button>
            </form>
        </div>
    </header>

    <div class="hs-catalog" style="display: block; margin-top: 2rem;">
        <div class="hs-catalog__results" style="width: 100%;">
            <?php if (have_posts()) : ?>
                <div class="hs-catalog__topbar hs-panel">
                    <div class="hs-catalog__count">
                        <?php
                        global $wp_query;
                        printf(esc_html(_n('%s result found', '%s results found', $wp_query->found_posts, 'helmetsan-theme')), number_format_i18n($wp_query->found_posts));
                        ?>
                    </div>
                </div>

                <section class="hs-catalog__results-content" style="margin-top: 1.5rem;">
                    <div class="helmet-grid">
                        <?php
                        while (have_posts()) :
                            the_post();
                            $pt = get_post_type();
                            if ($pt === 'helmet') {
                                get_template_part('template-parts/helmet-card');
                            } elseif (in_array($pt, ['brand', 'accessory', 'motorcycle', 'dealer'], true)) {
                                get_template_part('template-parts/entity-card');
                            } else {
                                ?>
                                <article <?php post_class('hs-entity-card hs-panel'); ?>>
                                    <div class="hs-entity-card__content" style="padding: 1.5rem;">
                                        <h3 class="hs-entity-card__title" style="margin-bottom: 0.5rem;"><a href="<?php the_permalink(); ?>" class="hs-link-overlay"><?php the_title(); ?></a></h3>
                                        <div class="hs-entity-card__meta" style="color: var(--hs-muted);"><?php the_excerpt(); ?></div>
                                    </div>
                                </article>
                                <?php
                            }
                        endwhile;
                        ?>
                    </div>

                    <nav class="hs-pagination-wrap" aria-label="<?php esc_attr_e( 'Search pagination', 'helmetsan-theme' ); ?>">
                        <?php
                        echo wp_kses_post(paginate_links([
                            'prev_text' => __( '&larr; Prev', 'helmetsan-theme' ),
                            'next_text' => __( 'Next &rarr;', 'helmetsan-theme' ),
                            'type'      => 'plain',
                        ]));
                        ?>
                    </nav>
                </section>
            <?php else : ?>
                <div class="hs-panel" style="padding: 3rem; text-align: center;">
                    <p><?php esc_html_e('No content found matching your search. Try different keywords or browse our catalog.', 'helmetsan-theme'); ?></p>
                </div>
            <?php endif; ?>
        </div>
    </div>
</section>

<?php get_footer(); ?>
