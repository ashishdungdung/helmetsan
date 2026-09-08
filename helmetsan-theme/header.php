<?php
/**
 * Header template.
 *
 * @package HelmetsanTheme
 */
?><!doctype html>
<html <?php language_attributes(); ?> data-theme="dark">
<head>
    <meta charset="<?php bloginfo('charset'); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <?php if (function_exists('helmetsan_is_china_visitor') && helmetsan_is_china_visitor()) : ?>
        <link rel="preconnect" href="https://fonts.loli.net">
    <?php else : ?>
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <?php endif; ?>
    <?php wp_head(); ?>
    <script>
    (function(){var k='helmetsan_theme';var t=['light','dark'];try{var s=localStorage.getItem(k);if(s&&t.indexOf(s)!==-1){document.documentElement.setAttribute('data-theme',s);}else{document.documentElement.setAttribute('data-theme','dark');}}catch(e){}})();
    </script>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<div class="hs-scroll-progress-bar" aria-hidden="true"></div>
<a class="screen-reader-text skip-link" href="#main-content"><?php esc_html_e('Skip to content', 'helmetsan-theme'); ?></a>
<div class="site-topbar">
    <div class="site-topbar__inner">
        <span class="site-topbar__tagline">Global Helmet Intelligence Platform</span>
        <div class="site-topbar__right">
            <?php
            wp_nav_menu([
                'theme_location' => 'secondary',
                'container'      => 'nav',
                'menu_class'     => 'menu menu--secondary',
                'fallback_cb'    => false,
            ]);
            ?>
            <!-- Language Selector -->
            <?php
            if (function_exists('pll_the_languages')) {
                $queriedId = is_singular() ? get_queried_object_id() : 0;
                $switcherArgs = ['raw' => 1, 'hide_if_empty' => 0];
                if ($queriedId > 0) {
                    $switcherArgs['post_id'] = $queriedId;
                }
                $languages = pll_the_languages($switcherArgs);
                $defaultSupported = function_exists('pll_languages_list') ? pll_languages_list() : ['en', 'de', 'zh', 'fr', 'es', 'it', 'pl', 'pt', 'nl', 'ja'];
                $publicLangs = apply_filters('helmetsan_public_languages', $defaultSupported);

                if (!empty($languages)) {
                    // Compute current path relative to root without language prefix
                    $currentUri = $_SERVER['REQUEST_URI'] ?? '/';
                    $currentPath = parse_url($currentUri, PHP_URL_PATH) ?: '/';
                    $supportedPrefixes = ['de', 'zh', 'fr', 'es', 'it', 'pl', 'pt', 'nl', 'ja'];
                    foreach ($supportedPrefixes as $sl) {
                        if ($currentPath === "/{$sl}" || $currentPath === "/{$sl}/") {
                            $currentPath = '/';
                            break;
                        } elseif (str_starts_with($currentPath, "/{$sl}/")) {
                            $currentPath = substr($currentPath, strlen("/{$sl}"));
                            break;
                        }
                    }

                    ?>
                    <div class="hs-language-selector-wrapper">
                        <select class="hs-language-select" aria-label="<?php esc_attr_e( 'Select Language', 'helmetsan-theme' ); ?>" onchange="if(this.value && this.value!==window.location.href){window.location.href=this.value;}">
                            <?php
                            foreach ($languages as $langSlug => $lang) {
                                // Only display populated languages in public selector
                                if (! in_array($langSlug, $publicLangs, true)) {
                                    continue;
                                }

                                $isCurrent = !empty($lang['current_lang']);
                                $noTranslation = !empty($lang['no_translation']);
                                $url = (string) ($lang['url'] ?? '');
                                $name = esc_html($lang['name'] ?? $langSlug);

                                // Explicitly verify direct translation permalink for singular posts/pages
                                if ($queriedId > 0 && function_exists('pll_get_post')) {
                                    $transPostId = (int) pll_get_post($queriedId, $langSlug);
                                    if ($transPostId > 0 && get_post_status($transPostId) === 'publish') {
                                        $url = get_permalink($transPostId);
                                        $noTranslation = false;
                                    }
                                }

                                // If no direct translation exists, fallback to catalog archive or home
                                if ($noTranslation && !$isCurrent) {
                                    if (is_singular('helmet')) {
                                        $url = helmetsan_url('/helmets/', $langSlug);
                                    } elseif (is_singular('brand')) {
                                        $url = helmetsan_url('/brands/', $langSlug);
                                    } elseif (is_singular('accessory')) {
                                        $url = helmetsan_url('/accessories/', $langSlug);
                                    } elseif (is_singular('motorcycle')) {
                                        $url = helmetsan_url('/motorcycles/', $langSlug);
                                    } elseif ($currentPath !== '/') {
                                        $url = helmetsan_url($currentPath, $langSlug);
                                    } else {
                                        $url = helmetsan_url('/', $langSlug);
                                    }
                                }

                                echo sprintf(
                                    '<option value="%s" %s>%s</option>',
                                    esc_url($url),
                                    $isCurrent ? 'selected="selected"' : '',
                                    $name
                                );
                            }
                            ?>
                        </select>
                    </div>
                    <?php
                }
            }
            ?>
        </div>
    </div>
</div>
<header class="site-header">
    <div class="site-header__inner">
        <a href="<?php echo esc_url(helmetsan_url('/')); ?>" class="site-header__brand"><?php bloginfo('name'); ?></a>
        
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

        <div class="site-header__actions">
            <!-- Search Trigger (triggers overlay) -->
            <button class="hs-search-trigger hs-mobile-search-trigger" aria-label="<?php esc_attr_e( 'Open Search', 'helmetsan-theme' ); ?>">
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><circle cx="11" cy="11" r="8"/><path d="M21 21l-4.35-4.35"/></svg>
            </button>

            <!-- Currency Selector -->
            <div class="hs-currency-selector-wrapper">
                <select class="hs-currency-select" aria-label="<?php esc_attr_e( 'Select Currency', 'helmetsan-theme' ); ?>">
                    <?php
                    $visitorCc = 'IN';
                    if (function_exists('helmetsan_core')) {
                        $visitorCc = helmetsan_core()->geo()->getCountry();
                    }
                    $supportedCountriesList = [
                        'US' => 'US ($) · United States',
                        'CA' => 'CA (CA$) · Canada',
                        'MX' => 'MX (MX$) · Mexico',
                        'GB' => 'UK (£) · United Kingdom',
                        'DE' => 'DE (€) · Germany',
                        'FR' => 'FR (€) · France',
                        'IT' => 'IT (€) · Italy',
                        'ES' => 'ES (€) · Spain',
                        'PL' => 'PL (zł) · Poland',
                        'IN' => 'IN (₹) · India',
                        'JP' => 'JP (¥) · Japan',
                        'AU' => 'AU (A$) · Australia',
                        'BR' => 'BR (R$) · Brazil',
                        'AE' => 'AE (AED) · UAE',
                        'NG' => 'NG (₦) · Nigeria',
                        'KE' => 'KE (KSh) · Kenya',
                        'EG' => 'EG (E£) · Egypt',
                        'MA' => 'MA (MAD) · Morocco',
                        'GH' => 'GH (GH₵) · Ghana',
                        'UG' => 'UG (USh) · Uganda',
                        'TZ' => 'TZ (TSh) · Tanzania'
                    ];
                    foreach ($supportedCountriesList as $cc => $label) {
                        echo sprintf(
                            '<option value="%s" %s>%s</option>',
                            esc_attr($cc),
                            selected($cc, $visitorCc, false),
                            esc_html($label)
                        );
                    }
                    ?>
                </select>
            </div>

            <!-- Theme Toggle -->
            <button type="button" id="hs-theme-toggle" class="hs-theme-toggle" aria-label="<?php esc_attr_e( 'Switch to dark mode', 'helmetsan-theme' ); ?>">
                <span data-theme-icon aria-hidden="true" class="hs-theme-toggle__icon hs-theme-toggle__icon--light"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="4"/><path d="M12 2v2M12 20v2M4.93 4.93l1.41 1.41M17.66 17.66l1.41 1.41M2 12h2M20 12h2M6.34 17.66l-1.41 1.41M19.07 4.93l-1.41 1.41"/></svg></span>
                <span data-theme-icon aria-hidden="true" class="hs-theme-toggle__icon hs-theme-toggle__icon--dark"><svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/></svg></span>
            </button>

            <!-- Hamburger toggle (mobile only) -->
            <button class="hs-nav-toggle" aria-label="Menu" aria-expanded="false">
                <span></span><span></span><span></span>
            </button>
        </div>
    </div>
</header>

<div class="hs-modal-backdrop" id="hsModalBackdrop" aria-hidden="true"></div>

<div class="hs-search-overlay" id="hsSearchOverlay" aria-hidden="true">
    <div class="hs-search-overlay__inner">
        <div class="hs-search-overlay__header">
            <h2 class="hs-search-overlay__title">Discovery</h2>
            <button class="hs-search-overlay__close" aria-label="Close Search">&times;</button>
        </div>
        <form role="search" method="get" class="hs-search-overlay__form" action="<?php echo esc_url( helmetsan_url( '/' ) ); ?>">
            <input type="search" placeholder="Search helmets, brands, certifications..." name="s" autofocus />
            <button type="submit" class="hs-btn hs-btn--primary">Search Platform</button>
        </form>
        <div class="hs-search-overlay__suggestions">
            <h3>Popular Searches</h3>
            <div class="hs-pill-grid">
                <a href="<?php echo esc_url(helmetsan_url('/?s=carbon+fiber')); ?>" class="hs-pill-tag">Carbon Fiber</a>
                <a href="<?php echo esc_url(helmetsan_url('/?s=ece+22.06')); ?>" class="hs-pill-tag">ECE 22.06</a>
                <a href="<?php echo esc_url(helmetsan_url('/?s=shoei')); ?>" class="hs-pill-tag">Shoei</a>
                <a href="<?php echo esc_url(helmetsan_url('/?s=bell')); ?>" class="hs-pill-tag">Bell</a>
            </div>
        </div>
    </div>
</div>
<main id="main-content" class="site-main">
    <?php
    if (! is_front_page() && function_exists('helmetsan_breadcrumb')) :
        $bcItems = helmetsan_breadcrumb(false);
        if (is_array($bcItems) && count($bcItems) > 1) :
            ?>
            <div class="hs-breadcrumb-wrapper">
                <div class="hs-breadcrumb-inner">
                    <?php helmetsan_breadcrumb(true); ?>
                </div>
            </div>
            <?php
        endif;
    endif;
    ?>
