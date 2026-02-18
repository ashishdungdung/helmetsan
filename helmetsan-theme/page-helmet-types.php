<?php
/**
 * Helmet types hub page template.
 *
 * @package HelmetsanTheme
 */

get_header();

$terms = get_terms([
    'taxonomy' => 'helmet_type',
    'hide_empty' => false,
]);

$allowedSlugs = [
    'full-face',
    'modular',
    'open-face',
    'half',
    'dirt-mx',
    'adventure-dual-sport',
    'touring',
    'track-race',
    'youth',
    'snow',
    'carbon-fiber',
    'graphics',
    'sale',
];

if (is_array($terms) && $terms !== []) {
    $terms = array_values(array_filter($terms, static function ($term) use ($allowedSlugs): bool {
        return $term instanceof WP_Term && in_array((string) $term->slug, $allowedSlugs, true);
    }));
}
?>
<section class="hs-section">
    <header class="hs-section__head">
        <h1><?php the_title(); ?></h1>
        <p>Taxonomy-backed helmet categories with direct archive links.</p>
    </header>
    <?php if (have_posts()) : while (have_posts()) : the_post(); ?>
        <div class="hs-panel"><?php the_content(); ?></div>
    <?php endwhile; endif; ?>

    <?php if (is_array($terms) && $terms !== []) : ?>
        <div class="helmet-grid">
            <?php foreach ($terms as $term) : if (! ($term instanceof WP_Term)) { continue; } ?>
                <article class="hs-panel entity-card">
                    <h3 class="entity-card__title"><a class="hs-link" href="<?php echo esc_url(get_term_link($term)); ?>"><?php echo esc_html($term->name); ?></a></h3>
                    <div class="entity-card__meta">
                        <code><?php echo esc_html((string) $term->count); ?> helmets</code>
                        <code><?php echo esc_html($term->slug); ?></code>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <p>No helmet types available yet.</p>
    <?php endif; ?>
</section>
<?php
get_footer();
