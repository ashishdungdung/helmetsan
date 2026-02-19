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

$themeDir = get_stylesheet_directory_uri();
$categoryImages = [];
foreach ($allowedSlugs as $slug) {
    // Check if customized image exists, else fallback to full-face or placeholder
    $categoryImages[$slug] = $themeDir . '/assets/images/categories/' . $slug . '.png';
}
// Specific Fallbacks for types without distinct images yet
$categoryImages['adventure-dual-sport'] = $categoryImages['full-face'];
$categoryImages['snow'] = $categoryImages['full-face'];
$categoryImages['carbon-fiber'] = $categoryImages['full-face'];

if (is_array($terms) && $terms !== []) {
    $terms = array_values(array_filter($terms, static function ($term) use ($allowedSlugs): bool {
        return $term instanceof WP_Term && in_array((string) $term->slug, $allowedSlugs, true);
    }));
}
?>
<section class="hs-section">
    <header class="hs-section__head">
        <h1><?php the_title(); ?></h1>
        <p>Explore our curated collections by riding style, safety standards, and specialized features.</p>
    </header>


    <?php if (is_array($terms) && $terms !== []) : ?>
        <div class="helmet-grid">
            <?php foreach ($terms as $term) : if (! ($term instanceof WP_Term)) { continue; } 
                $img = $categoryImages[$term->slug] ?? $categoryImages['full-face'];
                ?>
                <article class="hs-panel entity-card entity-card--hub">
                    <div class="entity-card__image-container">
                        <img src="<?php echo esc_url($img); ?>" alt="<?php echo esc_attr($term->name); ?>" class="entity-card__hero" />
                        <div class="entity-card__overlay">
                             <div class="entity-card__count"><?php echo esc_html((string) $term->count); ?> Models</div>
                        </div>
                    </div>
                    <div class="entity-card__content">
                        <h3 class="entity-card__title">
                            <a class="hs-link hs-link--stretched" href="<?php echo esc_url(get_term_link($term)); ?>"><?php echo esc_html($term->name); ?></a>
                        </h3>
                        <p class="entity-card__excerpt"><?php echo esc_html($term->description ?: 'Premium selection of ' . $term->name . ' helmets.'); ?></p>
                        <div class="entity-card__footer">
                            <span class="hs-btn hs-btn--ghost hs-btn--small">View Catalog →</span>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php else : ?>
        <p>No helmet types available yet.</p>
    <?php endif; ?>
</section>
<style>
.entity-card--hub {
    padding: 0;
    overflow: hidden;
    transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
    display: flex;
    flex-direction: column;
}
.entity-card--hub:hover {
    transform: translateY(-8px);
}
.entity-card__image-container {
    position: relative;
    height: 200px;
    overflow: hidden;
}
.entity-card__hero {
    width: 100%;
    height: 100%;
    object-fit: cover;
    transition: transform 0.5s ease;
}
.entity-card--hub:hover .entity-card__hero {
    transform: scale(1.1);
}
.entity-card__overlay {
    position: absolute;
    top: 12px;
    right: 12px;
    z-index: 2;
}
.entity-card__count {
    background: rgba(255, 255, 255, 0.9);
    backdrop-filter: blur(4px);
    padding: 4px 12px;
    border-radius: 999px;
    font-size: 12px;
    font-weight: 700;
    color: var(--hs-text);
    box-shadow: 0 4px 12px rgba(0,0,0,0.1);
}
.entity-card__content {
    padding: 1.25rem;
    flex-grow: 1;
    display: flex;
    flex-direction: column;
}
.entity-card__excerpt {
    font-size: 14px;
    line-height: 1.5;
    margin-bottom: 1rem;
    color: var(--hs-muted);
}
.entity-card__footer {
    margin-top: auto;
}
.hs-link--stretched::after {
    content: '';
    position: absolute;
    top: 0; right: 0; bottom: 0; left: 0;
    z-index: 1;
}
.hs-btn--small {
    padding: 0.35rem 0.85rem;
    font-size: 13px;
    pointer-events: none;
}
</style>
<?php
get_footer();

