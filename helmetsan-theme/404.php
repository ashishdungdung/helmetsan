<?php
/**
 * Helmetsan 404 Decision Hub Page Template
 */

get_header();
?>

<div class="hs-container hs-py-12">
    <div class="hs-404-hub hs-text-center hs-max-w-2xl hs-mx-auto">
        <div class="hs-badge hs-badge-accent hs-mb-4">404 ERROR</div>
        <h1 class="hs-title-lg hs-mb-3">We Couldn't Find That Helmet</h1>
        <p class="hs-text-soft hs-mb-8">The page or helmet specification you requested might have been moved, renamed, or is undergoing entity normalization.</p>

        <!-- Search Bar -->
        <form role="search" method="get" class="hs-search-form hs-mb-8" action="<?php echo esc_url(helmetsan_url('/')); ?>">
            <div class="hs-search-input-wrap">
                <input type="search" class="hs-input hs-search-input" placeholder="Search 2,200+ helmets, brands, motorcycles..." value="<?php echo get_search_query(); ?>" name="s" required />
                <button type="submit" class="hs-btn hs-btn-primary">Search</button>
            </div>
        </form>

        <!-- Popular Search Chips -->
        <div class="hs-mb-10">
            <span class="hs-text-xs hs-uppercase hs-text-muted hs-font-semibold hs-block hs-mb-3">Popular Searches</span>
            <div class="hs-flex hs-flex-wrap hs-justify-center hs-gap-2">
                <a href="<?php echo esc_url(helmetsan_url('/helmets/?brand=arai')); ?>" class="hs-chip">Arai</a>
                <a href="<?php echo esc_url(helmetsan_url('/helmets/?brand=shoei')); ?>" class="hs-chip">Shoei</a>
                <a href="<?php echo esc_url(helmetsan_url('/helmets/?homologation=ece-22-06')); ?>" class="hs-chip hs-chip-success">🟢 ECE 22.06</a>
                <a href="<?php echo esc_url(helmetsan_url('/helmets/?type=full-face')); ?>" class="hs-chip">Full Face</a>
                <a href="<?php echo esc_url(helmetsan_url('/motorcycles/')); ?>" class="hs-chip">Himalayan 450</a>
            </div>
        </div>

        <!-- Quick Hub Links -->
        <div class="hs-grid hs-grid-cols-2 md:hs-grid-cols-4 hs-gap-4">
            <a href="<?php echo esc_url(helmetsan_url('/helmets/')); ?>" class="hs-card hs-p-4 hs-text-center hs-hover-lift">
                <div class="hs-text-xl hs-mb-1">🪖</div>
                <div class="hs-font-semibold hs-text-sm">Helmets</div>
                <div class="hs-text-xs hs-text-muted">2,235 Models</div>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/brands/')); ?>" class="hs-card hs-p-4 hs-text-center hs-hover-lift">
                <div class="hs-text-xl hs-mb-1">🏢</div>
                <div class="hs-font-semibold hs-text-sm">Brands</div>
                <div class="hs-text-xs hs-text-muted">58 Manufacturers</div>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/accessories/')); ?>" class="hs-card hs-p-4 hs-text-center hs-hover-lift">
                <div class="hs-text-xl hs-mb-1">🔧</div>
                <div class="hs-font-semibold hs-text-sm">Accessories</div>
                <div class="hs-text-xs hs-text-muted">Visors & Comms</div>
            </a>
            <a href="<?php echo esc_url(helmetsan_url('/safety-standards/')); ?>" class="hs-card hs-p-4 hs-text-center hs-hover-lift">
                <div class="hs-text-xl hs-mb-1">🛡️</div>
                <div class="hs-font-semibold hs-text-sm">Safety Standards</div>
                <div class="hs-text-xs hs-text-muted">ECE, DOT, Snell</div>
            </a>
        </div>
    </div>
</div>

<?php
get_footer();
