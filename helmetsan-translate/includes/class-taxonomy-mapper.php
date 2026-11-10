<?php
declare(strict_types=1);

namespace HelmetsanTranslate;

defined('ABSPATH') || exit;

class TaxonomyMapper {
    public static function sync_post_taxonomies(int $en_post_id, int $target_post_id, string $target_lang): void {
        if (!function_exists('pll_get_term')) return;

        $post_type = get_post_type($en_post_id);
        $taxonomies = get_object_taxonomies($post_type, 'names');

        foreach ($taxonomies as $taxonomy) {
            // Skip language and translation taxonomies
            if (in_array($taxonomy, ['language', 'post_translations', 'term_language', 'term_translations'], true)) {
                continue;
            }

            $en_terms = wp_get_object_terms($en_post_id, $taxonomy, ['fields' => 'all']);
            if (is_wp_error($en_terms) || empty($en_terms)) continue;

            $target_term_ids = [];
            foreach ($en_terms as $en_term) {
                $target_term_id = pll_get_term($en_term->term_id, $target_lang);

                // Auto-create localized taxonomy term if missing
                if (!$target_term_id) {
                    $target_term_id = self::create_localized_term($en_term, $taxonomy, $target_lang);
                }

                if ($target_term_id) {
                    $target_term_ids[] = (int) $target_term_id;
                }
            }

            if (!empty($target_term_ids)) {
                wp_set_object_terms($target_post_id, $target_term_ids, $taxonomy);
            }
        }
    }

    private static function create_localized_term(\WP_Term $en_term, string $taxonomy, string $target_lang): ?int {
        // Generate translated or canonical term name
        $name = $en_term->name;
        $slug = $en_term->slug;

        // Check if parent term exists and map parent
        $parent_id = 0;
        if ($en_term->parent) {
            $mapped_parent = pll_get_term($en_term->parent, $target_lang);
            if ($mapped_parent) {
                $parent_id = (int) $mapped_parent;
            }
        }

        $inserted = wp_insert_term($name, $taxonomy, [
            'slug' => $slug,
            'parent' => $parent_id,
            'description' => $en_term->description
        ]);

        if (is_wp_error($inserted)) {
            // If slug collision, try fetching existing term
            $existing = get_term_by('slug', $slug, $taxonomy);
            if ($existing) {
                $term_id = (int) $existing->term_id;
            } else {
                return null;
            }
        } else {
            $term_id = (int) $inserted['term_id'];
        }

        // Set language on the new term and link to master term cluster
        if (function_exists('pll_set_term_language') && function_exists('pll_save_term_translations')) {
            pll_set_term_language($term_id, $target_lang);

            $term_translations = function_exists('pll_get_term_translations') ? pll_get_term_translations($en_term->term_id) : [];
            $term_translations['en'] = $en_term->term_id;
            $term_translations[$target_lang] = $term_id;
            pll_save_term_translations($term_translations);
        }

        return $term_id;
    }
}
