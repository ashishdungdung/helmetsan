<?php
/**
 * Header template.
 *
 * @package HelmetsanTheme
 */
?><!doctype html>
<html <?php language_attributes(); ?> data-theme="light">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php wp_head(); ?>
    <script>
    (function(){var k='helmetsan_theme';var t=['light','dark'];try{var s=localStorage.getItem(k);if(s&&t.indexOf(s)!==-1){document.documentElement.setAttribute('data-theme',s);}}catch(e){}})();
    </script>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="site-topbar">
    <div class="site-topbar__inner">
        <span class="site-topbar__tagline">Global Helmet Intelligence Platform</span>
        <div class="site-topbar__actions">
            <button type="button" id="hs-theme-toggle" class="hs-theme-toggle" aria-label="<?php esc_attr_e( 'Switch to dark mode', 'helmetsan-theme' ); ?>">
                <span data-theme-icon aria-hidden="true" class="hs-theme-toggle__icon hs-theme-toggle__icon--light"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg></span>
                <span data-theme-icon aria-hidden="true" class="hs-theme-toggle__icon hs-theme-toggle__icon--dark"><svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></span>
            </button>
        </div>
        <?php
        wp_nav_menu([
            'theme_location' => 'secondary',
            'container'      => 'nav',
            'menu_class'     => 'menu menu--secondary',
            'fallback_cb'    => false,
        ]);
        ?>
    </div>
</div>
<header class="site-header">
    <div class="site-header__inner">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="site-header__brand"><?php bloginfo('name'); ?></a>
        <button class="hs-nav-toggle" aria-label="Menu" aria-expanded="false">
            <span></span><span></span><span></span>
        </button>
        <?php
        wp_nav_menu([
            'theme_location' => 'primary',
            'container'      => 'nav',
            'container_class' => 'hs-primary-nav',
            'menu_class'     => 'menu menu--primary',
            'fallback_cb'    => false,
            'walker'         => new Helmetsan_Mega_Menu_Walker(),
        ]);
        ?>
    </div>
</header>
<main class="site-main">
