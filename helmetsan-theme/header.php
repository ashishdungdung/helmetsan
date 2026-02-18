<?php
/**
 * Header template.
 *
 * @package HelmetsanTheme
 */
?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="site-topbar">
    <div class="site-topbar__inner">
        <span class="site-topbar__tagline">Global Helmet Intelligence Platform</span>
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
        <?php
        wp_nav_menu([
            'theme_location' => 'primary',
            'container'      => 'nav',
            'menu_class'     => 'menu menu--primary',
            'fallback_cb'    => false,
        ]);
        ?>
        <button class="hs-mega-menu-toggle" type="button" aria-expanded="false" aria-controls="hs-mega-menu-panel">
            Helmets Menu
        </button>
    </div>
</header>
<div id="hs-mega-menu-panel" class="hs-mega-menu-panel" hidden>
    <?php helmetsan_render_helmet_mega_menu(); ?>
</div>
<main class="site-main">
