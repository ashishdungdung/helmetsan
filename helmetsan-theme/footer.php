<?php
/**
 * Footer template.
 *
 * @package HelmetsanTheme
 */
?>
</main>
<footer class="site-footer">
    <div class="site-footer__inner">
        <div class="site-footer__brand">
            <strong><?php bloginfo('name'); ?></strong>
            <p>Helmetsan is a trademark, owned and operated by Ash Digital Services.</p>
        </div>
        <div class="site-footer__menus">
            <?php
            wp_nav_menu([
                'theme_location' => 'footer',
                'container'      => 'nav',
                'menu_class'     => 'menu menu--footer',
                'fallback_cb'    => false,
            ]);
            wp_nav_menu([
                'theme_location' => 'legal',
                'container'      => 'nav',
                'menu_class'     => 'menu menu--legal',
                'fallback_cb'    => false,
            ]);
            ?>
        </div>
    </div>
    <p class="site-footer__copyright">&copy; <?php echo esc_html((string) gmdate('Y')); ?> <?php bloginfo('name'); ?></p>
</footer>
<?php wp_footer(); ?>
</body>
</html>
