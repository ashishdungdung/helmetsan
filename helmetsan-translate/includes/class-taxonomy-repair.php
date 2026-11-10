<?php
declare(strict_types=1);

namespace HelmetsanTranslate;

defined('ABSPATH') || exit;

class TaxonomyRepair {
    /**
     * Fixes taxonomy gaps across all non-English helmets by syncing missing
     * helmet_brand, helmet_type, and certification terms from English master posts.
     */
    public static function repair_all(bool $dry_run = false): array {
        global $wpdb;

        $results = [
            'brands_fixed' => 0,
            'types_fixed'  => 0,
            'certs_fixed'  => 0,
            'posts_processed' => 0,
            'dry_run'      => $dry_run
        ];

        // 1. Fetch all published non-English helmets
        $helmets = $wpdb->get_results("
            SELECT p.ID as child_id, tt_lang.slug as lang
            FROM {$wpdb->posts} p
            JOIN {$wpdb->term_relationships} t_rel ON p.ID = t_rel.object_id
            JOIN {$wpdb->term_taxonomy} tt_l ON t_rel.term_taxonomy_id = tt_l.term_taxonomy_id AND tt_l.taxonomy = 'language'
            JOIN {$wpdb->terms} tt_lang ON tt_l.term_id = tt_lang.term_id AND tt_lang.slug != 'en'
            WHERE p.post_type = 'helmet' AND p.post_status = 'publish'
        ");

        if (empty($helmets)) return $results;

        // Cache existing term relationships to avoid thousands of individual queries
        $existing_brands = self::get_assigned_terms_map('helmet_brand');
        $existing_types  = self::get_assigned_terms_map('helmet_type');
        $existing_certs  = self::get_assigned_terms_map('certification');

        // Pre-fetch Polylang translation mappings for helmet post clusters
        $en_master_map = [];
        $clusters = $wpdb->get_results("
            SELECT description FROM {$wpdb->term_taxonomy} 
            WHERE taxonomy = 'post_translations'
        ");
        foreach ($clusters as $c) {
            $trans = @unserialize($c->description);
            if (is_array($trans) && !empty($trans['en'])) {
                $en_id = (int) $trans['en'];
                foreach ($trans as $l => $pid) {
                    if ($l !== 'en') {
                        $en_master_map[(int) $pid] = $en_id;
                    }
                }
            }
        }

        // Pre-fetch EN master terms
        $en_brands = self::get_assigned_terms_map('helmet_brand', true);
        $en_types  = self::get_assigned_terms_map('helmet_type', true);
        $en_certs  = self::get_assigned_terms_map('certification', true);

        // Pre-cache Polylang taxonomy term mappings
        $term_map = self::get_polylang_term_map();

        foreach ($helmets as $h) {
            $child_id = (int) $h->child_id;
            $lang = $h->lang;
            $en_id = $en_master_map[$child_id] ?? (function_exists('pll_get_post') ? pll_get_post($child_id, 'en') : null);
            if (!$en_id) continue;

            $results['posts_processed']++;

            // A. Check helmet_brand (shared taxonomy)
            if (empty($existing_brands[$child_id]) && !empty($en_brands[$en_id])) {
                $brand_terms = $en_brands[$en_id];
                if (!$dry_run) {
                    wp_set_object_terms($child_id, $brand_terms, 'helmet_brand', true);
                }
                $results['brands_fixed'] += count($brand_terms);
            }

            // B. Check helmet_type (translated taxonomy)
            if (empty($existing_types[$child_id]) && !empty($en_types[$en_id])) {
                $target_type_ids = [];
                foreach ($en_types[$en_id] as $en_term_id) {
                    $mapped = $term_map[$en_term_id][$lang] ?? (function_exists('pll_get_term') ? pll_get_term($en_term_id, $lang) : null);
                    if ($mapped) $target_type_ids[] = (int) $mapped;
                }
                if (!empty($target_type_ids)) {
                    if (!$dry_run) {
                        wp_set_object_terms($child_id, $target_type_ids, 'helmet_type', true);
                    }
                    $results['types_fixed'] += count($target_type_ids);
                }
            }

            // C. Check certification (translated taxonomy)
            if (empty($existing_certs[$child_id]) && !empty($en_certs[$en_id])) {
                $target_cert_ids = [];
                foreach ($en_certs[$en_id] as $en_cert_id) {
                    $mapped = $term_map[$en_cert_id][$lang] ?? (function_exists('pll_get_term') ? pll_get_term($en_cert_id, $lang) : null);
                    if ($mapped) $target_cert_ids[] = (int) $mapped;
                }
                if (!empty($target_cert_ids)) {
                    if (!$dry_run) {
                        wp_set_object_terms($child_id, $target_cert_ids, 'certification', true);
                    }
                    $results['certs_fixed'] += count($target_cert_ids);
                }
            }
        }

        return $results;
    }

    private static function get_assigned_terms_map(string $taxonomy, bool $en_only = false): array {
        global $wpdb;
        $sql = "
            SELECT tr.object_id, tt.term_id 
            FROM {$wpdb->term_relationships} tr
            JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = %s
        ";
        $rows = $wpdb->get_results($wpdb->prepare($sql, $taxonomy));
        $map = [];
        foreach ($rows as $r) {
            $map[(int) $r->object_id][] = (int) $r->term_id;
        }
        return $map;
    }

    private static function get_polylang_term_map(): array {
        global $wpdb;
        $clusters = $wpdb->get_results("
            SELECT description FROM {$wpdb->term_taxonomy} 
            WHERE taxonomy = 'term_translations'
        ");
        $map = [];
        foreach ($clusters as $c) {
            $trans = @unserialize($c->description);
            if (is_array($trans) && !empty($trans['en'])) {
                $en_id = (int) $trans['en'];
                foreach ($trans as $l => $tid) {
                    $map[$en_id][$l] = (int) $tid;
                }
            }
        }
        return $map;
    }
}
