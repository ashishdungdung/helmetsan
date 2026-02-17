<?php
/**
 * Single dealer template.
 *
 * @package HelmetsanTheme
 */

get_header();

if (have_posts()) {
    while (have_posts()) {
        the_post();
        $id = get_the_ID();
        $phone = (string) get_post_meta($id, 'dealer_phone', true);
        $email = (string) get_post_meta($id, 'dealer_email', true);
        $website = (string) get_post_meta($id, 'dealer_website', true);
        $address = (string) get_post_meta($id, 'dealer_address', true);
        $regions = get_the_terms($id, 'region');
        ?>
        <article <?php post_class('hs-section'); ?>>
            <header class="hs-section__head">
                <p class="hs-eyebrow">Dealer Profile</p>
                <h1><?php the_title(); ?></h1>
            </header>

            <section class="hs-stat-grid">
                <article class="hs-stat-card"><span>Region Tags</span><strong><?php echo esc_html(is_array($regions) ? (string) count($regions) : '0'); ?></strong></article>
                <article class="hs-stat-card"><span>Contact</span><strong><?php echo esc_html($phone !== '' ? $phone : 'N/A'); ?></strong></article>
                <article class="hs-stat-card"><span>Email</span><strong><?php echo esc_html($email !== '' ? $email : 'N/A'); ?></strong></article>
            </section>

            <div class="hs-panel"><?php the_content(); ?></div>

            <section class="hs-panel">
                <h2>Dealer Information</h2>
                <dl class="helmetsan-specs-grid">
                    <div class="helmetsan-specs-row"><dt>Address</dt><dd><?php echo esc_html($address !== '' ? $address : 'N/A'); ?></dd></div>
                    <div class="helmetsan-specs-row"><dt>Phone</dt><dd><?php echo esc_html($phone !== '' ? $phone : 'N/A'); ?></dd></div>
                    <div class="helmetsan-specs-row"><dt>Email</dt><dd><?php echo esc_html($email !== '' ? $email : 'N/A'); ?></dd></div>
                    <div class="helmetsan-specs-row"><dt>Website</dt><dd><?php if ($website !== '') : ?><a class="hs-link" href="<?php echo esc_url($website); ?>" target="_blank" rel="noopener noreferrer"><?php echo esc_html($website); ?></a><?php else : ?>N/A<?php endif; ?></dd></div>
                </dl>
            </section>
        </article>
        <?php
    }
}

get_footer();

