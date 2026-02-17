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
<header class="site-header">
    <div class="site-header__inner">
        <a href="<?php echo esc_url(home_url('/')); ?>" class="site-header__brand"><?php bloginfo('name'); ?></a>
        <?php
        wp_nav_menu([
            'theme_location' => 'primary',
            'container'      => 'nav',
            'menu_class'     => 'menu',
            'fallback_cb'    => false,
        ]);
        ?>
    </div>
</header>
<main class="site-main">
