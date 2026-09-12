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
     * Starts the element output.
     */
    public function start_el(&$output, $item, $depth = 0, $args = null, $id = 0)
    {
        $classes = empty($item->classes) ? [] : (array) $item->classes;
        
        // Add ARIA attributes if this is a mega-menu trigger
        if (array_intersect(['mega-menu--helmets', 'mega-menu--brands', 'mega-menu--accessories', 'mega-menu--motorcycles'], $classes)) {
            $item->attr_title = $item->attr_title ?: $item->title;
            // Note: aria-expanded should be toggled by JS, but we set initial state here.
            $item->description = 'has-mega-menu'; 
        }

        parent::start_el($output, $item, $depth, $args, $id);
        
        // Inject aria-haspopup directly into the generated link if it's a mega menu
        if (str_contains($output, 'has-mega-menu')) {
            $output = str_replace('<a ', '<a aria-haspopup="true" aria-expanded="false" ', $output);
        }
    }

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
