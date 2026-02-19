<?php

declare(strict_types=1);

namespace Helmetsan\Core\Frontend;

final class HelmetDataBlock
{
    public function register(): void
    {
        add_shortcode('helmetsan_helmet_specs', [$this, 'shortcode']);
        add_filter('the_content', [$this, 'appendSpecsToHelmetContent']);
    }

    public function appendSpecsToHelmetContent(string $content): string
    {
        if (! is_singular('helmet') || ! in_the_loop() || ! is_main_query()) {
            return $content;
        }

        return $content . $this->renderSpecs((int) get_the_ID());
    }

    /**
     * @param array<string, mixed> $atts
     */
    public function shortcode(array $atts = []): string
    {
        $postId = isset($atts['id']) ? (int) $atts['id'] : (int) get_the_ID();

        if ($postId <= 0 || get_post_type($postId) !== 'helmet') {
            return '';
        }

        return $this->renderSpecs($postId);
    }

    private function renderSpecs(int $postId): string
    {
        $weight       = get_post_meta($postId, 'spec_weight_g', true);
        $material     = get_post_meta($postId, 'spec_shell_material', true);
        $price        = get_post_meta($postId, 'price_retail_usd', true);
        $certTerms    = get_the_terms($postId, 'certification');
        $certificates = [];

        if (is_array($certTerms)) {
            foreach ($certTerms as $term) {
                $certificates[] = (string) $term->name;
            }
        }

        $rows = [
            'Weight'        => $weight !== '' ? (string) $weight . ' g' : 'N/A',
            'Shell Material'=> $material !== '' ? (string) $material : 'N/A',
            'Price'         => $price !== '' ? (new \Helmetsan\Core\Price\CurrencyFormatter())->format((float) $price, 'USD') : 'N/A',
            'Certification' => $certificates !== [] ? implode(', ', $certificates) : 'N/A',
        ];

        ob_start();
        ?>
        <section class="helmetsan-specs" aria-label="Helmet specifications">
            <h2><?php echo esc_html__('Helmet Specs', 'helmetsan-core'); ?></h2>
            <dl class="helmetsan-specs-grid">
                <?php foreach ($rows as $label => $value) : ?>
                    <div class="helmetsan-specs-row">
                        <dt><?php echo esc_html($label); ?></dt>
                        <dd><?php echo esc_html($value); ?></dd>
                    </div>
                <?php endforeach; ?>
            </dl>
        </section>
        <?php

        return (string) ob_get_clean();
    }
}
