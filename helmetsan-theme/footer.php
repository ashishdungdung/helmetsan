<?php
/**
 * Footer template.
 *
 * @package HelmetsanTheme
 */
?>
</main>
<footer class="site-footer">
    <div class="site-footer__widgets">
        <div class="site-footer__inner">
            <div class="site-footer__brand">
                <a href="<?php echo esc_url(helmetsan_url('/')); ?>" class="site-footer__brand-title"><?php bloginfo('name'); ?></a>
                <p><?php esc_html_e('Helmetsan is a trademark, owned and operated by Ash Digital Services.', 'helmetsan-theme'); ?></p>
                <?php if ((bool) get_theme_mod('helmetsan_show_made_in_india', true)) : ?>
                    <p class="site-footer__india">
                        <?php printf(
                            esc_html__('Made with %s in India', 'helmetsan-theme'),
                            '<span aria-hidden="true">&lt;3</span>'
                        ); ?>
                        <span class="site-footer__india-mark" aria-hidden="true">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" role="img">
                                <path d="M9.2 2.2l3 1.2 2.8-.6 1.4 2 2.6.9-.3 2.8 1.7 2-1.7 2 .3 2.8-2.6.9-1.4 2-2.8-.6-3 1.2-1.6-2.4-2.5-1.1.4-2.6-1.8-2.2 1.8-2.2-.4-2.6 2.5-1.1L9.2 2.2z"></path>
                            </svg>
                        </span>
                    </p>
                <?php endif; ?>
            </div>
            <div class="site-footer__menus">
                <div class="site-footer__menus-grid">
                    <div class="site-footer__col">
                        <h3 class="site-footer__col-title"><?php esc_html_e('Catalog', 'helmetsan-theme'); ?></h3>
                        <ul class="site-footer__col-links">
                            <li><a href="<?php echo esc_url(helmetsan_url('/helmets/')); ?>"><?php esc_html_e('Helmets', 'helmetsan-theme'); ?></a></li>
                            <li><a href="<?php echo esc_url(helmetsan_url('/brands/')); ?>"><?php esc_html_e('Brands', 'helmetsan-theme'); ?></a></li>
                            <li><a href="<?php echo esc_url(helmetsan_url('/accessories/')); ?>"><?php esc_html_e('Accessories', 'helmetsan-theme'); ?></a></li>
                            <li><a href="<?php echo esc_url(helmetsan_url('/motorcycles/')); ?>"><?php esc_html_e('Motorcycles', 'helmetsan-theme'); ?></a></li>
                            <li><a href="<?php echo esc_url(helmetsan_url('/dealers/')); ?>"><?php esc_html_e('Dealers Directory', 'helmetsan-theme'); ?></a></li>
                        </ul>
                    </div>
                    <div class="site-footer__col">
                        <h3 class="site-footer__col-title"><?php esc_html_e('Resources', 'helmetsan-theme'); ?></h3>
                        <ul class="site-footer__col-links">
                            <li><a href="<?php echo esc_url(helmetsan_url('/safety-standards/')); ?>"><?php esc_html_e('Safety Standards', 'helmetsan-theme'); ?></a></li>
                            <?php
                            $helmetTypesUrl = helmetsan_theme_find_page_url_by_slug('helmet-types');
                            if ($helmetTypesUrl) : ?>
                                <li><a href="<?php echo esc_url($helmetTypesUrl); ?>"><?php esc_html_e('Helmet Types', 'helmetsan-theme'); ?></a></li>
                            <?php endif; ?>
                            <?php
                            $comparisonUrl = helmetsan_theme_find_page_url_by_slug('comparison');
                            if ($comparisonUrl) : ?>
                                <li><a href="<?php echo esc_url($comparisonUrl); ?>"><?php esc_html_e('Helmet Comparison', 'helmetsan-theme'); ?></a></li>
                            <?php endif; ?>
                            <?php
                            $certDocsUrl = helmetsan_theme_find_page_url_by_slug('certification-documents');
                            if ($certDocsUrl) : ?>
                                <li><a href="<?php echo esc_url($certDocsUrl); ?>"><?php esc_html_e('Certifications & Documents', 'helmetsan-theme'); ?></a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                    <div class="site-footer__col">
                        <h3 class="site-footer__col-title"><?php esc_html_e('Company', 'helmetsan-theme'); ?></h3>
                        <ul class="site-footer__col-links">
                            <?php
                            $aboutUrl = helmetsan_theme_find_page_url_by_slug('about');
                            if ($aboutUrl) : ?>
                                <li><a href="<?php echo esc_url($aboutUrl); ?>"><?php esc_html_e('About Helmetsan', 'helmetsan-theme'); ?></a></li>
                            <?php endif; ?>
                            <?php
                            $contactUrl = helmetsan_theme_find_page_url_by_slug('contact');
                            if ($contactUrl) : ?>
                                <li><a href="<?php echo esc_url($contactUrl); ?>"><?php esc_html_e('Contact Us', 'helmetsan-theme'); ?></a></li>
                            <?php endif; ?>
                            <?php
                            $blogUrl = helmetsan_theme_find_page_url_by_slug('blog');
                            if ($blogUrl) : ?>
                                <li><a href="<?php echo esc_url($blogUrl); ?>"><?php esc_html_e('Blog & News', 'helmetsan-theme'); ?></a></li>
                            <?php endif; ?>
                        </ul>
                    </div>
                </div>
            </div>
        </div>
        <div class="site-footer__widget-areas">
            <?php if (is_active_sidebar('footer-1')) : ?><div class="site-footer__widget-col"><?php dynamic_sidebar('footer-1'); ?></div><?php endif; ?>
            <?php if (is_active_sidebar('footer-2')) : ?><div class="site-footer__widget-col"><?php dynamic_sidebar('footer-2'); ?></div><?php endif; ?>
            <?php if (is_active_sidebar('footer-3')) : ?><div class="site-footer__widget-col"><?php dynamic_sidebar('footer-3'); ?></div><?php endif; ?>
        </div>
    </div>

    <?php $socialLinks = helmetsan_theme_get_social_links(); ?>
    <?php if ($socialLinks !== []) : ?>
        <div class="site-footer__social-wrap">
            <div class="site-footer__social">
                <?php foreach ($socialLinks as $item) : ?>
                    <a class="site-footer__social-link" href="<?php echo esc_url($item['url']); ?>" target="_blank" rel="noopener noreferrer" aria-label="<?php echo esc_attr($item['title']); ?>">
                        <?php echo helmetsan_theme_social_icon_svg($item['url'], $item['title']); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped ?>
                    </a>
                <?php endforeach; ?>
            </div>
        </div>
    <?php endif; ?>

    <?php
    $copyright = (string) get_theme_mod('helmetsan_copyright_text', '© {year} {site_name}. All rights reserved.');
    $copyright = str_replace(
        ['{year}', '{site_name}'],
        [(string) gmdate('Y'), (string) get_bloginfo('name')],
        $copyright
    );
    $requiredLegalLinks = helmetsan_theme_get_required_legal_links();
    ?>
    <div class="site-footer__bottomline">
        <p class="site-footer__copyright"><?php echo esc_html($copyright); ?></p>
        <?php if ($requiredLegalLinks !== []) : ?>
            <nav aria-label="Required legal links">
                <ul class="menu menu--legal-inline">
                    <?php foreach ($requiredLegalLinks as $link) : ?>
                        <li><a href="<?php echo esc_url($link['url']); ?>"><?php echo esc_html($link['label']); ?></a></li>
                    <?php endforeach; ?>
                </ul>
            </nav>
        <?php endif; ?>
    </div>
</footer>
<?php 
get_template_part('template-parts/sticky-comparison-bar');
get_template_part('template-parts/ai-selection-tool');
wp_footer(); 
?>
</body>
</html>
