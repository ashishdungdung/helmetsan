<?php
/**
 * Mega Menu Walker.
 *
 * @package HelmetsanTheme
 */

if (! defined('ABSPATH')) {
    exit;
}

class Helmetsan_Mega_Menu_Walker extends Walker_Nav_Menu
{
    /**
     * Ends the element output, if needed.
     *
     * @param string   $output Used to append additional content (passed by reference).
     * @param WP_Post  $item   Page data object. Not used.
     * @param int      $depth  Depth of page. Not Used.
     * @param stdClass $args   An object of wp_nav_menu() arguments.
     */
    public function end_el(&$output, $item, $depth = 0, $args = null)
    {
        // Check for specific classes mapped to mega menus
        $classes = empty($item->classes) ? [] : (array) $item->classes;
        $megaMenuType = '';

        if (in_array('mega-menu--helmets', $classes, true)) {
            $megaMenuType = 'helmet';
        } elseif (in_array('mega-menu--brands', $classes, true)) {
            $megaMenuType = 'brands';
        } elseif (in_array('mega-menu--accessories', $classes, true)) {
            $megaMenuType = 'accessories';
        } elseif (in_array('mega-menu--motorcycles', $classes, true)) {
            $megaMenuType = 'motorcycles';
        }

        if ($megaMenuType !== '') {
            ob_start();
            helmetsan_render_mega_menu($megaMenuType);
            $megaMenuHtml = ob_get_clean();
            $output .= $megaMenuHtml;
        }

        $output .= "</li>\n";
    }
}
