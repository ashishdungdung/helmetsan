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
                <strong><?php bloginfo('name'); ?></strong>
                <p>Helmetsan is a trademark, owned and operated by Ash Digital Services.</p>
                <?php if ((bool) get_theme_mod('helmetsan_show_made_in_india', true)) : ?>
                    <p class="site-footer__india">
                        Made with <span aria-hidden="true">&lt;3</span> in India
                        <span class="site-footer__india-mark" aria-hidden="true">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="currentColor" role="img">
                                <path d="M9.2 2.2l3 1.2 2.8-.6 1.4 2 2.6.9-.3 2.8 1.7 2-1.7 2 .3 2.8-2.6.9-1.4 2-2.8-.6-3 1.2-1.6-2.4-2.5-1.1.4-2.6-1.8-2.2 1.8-2.2-.4-2.6 2.5-1.1L9.2 2.2z"></path>
                            </svg>
                        </span>
                    </p>
                <?php endif; ?>
            </div>
            <div class="site-footer__menus">
                <?php
                wp_nav_menu([
                    'theme_location' => 'footer',
                    'container'      => 'nav',
                    'menu_class'     => 'menu menu--footer',
                    'fallback_cb'    => false,
                ]);
                ?>
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
<?php wp_footer(); ?>
</body>
</html>
