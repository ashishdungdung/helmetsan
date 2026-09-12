<?php
/**
 * Helmetsan Remote Translation Bridge
 * High-performance batch candidate fetcher and ingestion engine.
 * Run via WP-CLI: wp --path=/var/www/helmetsan.com/public eval-file /var/www/helmetsan.com/scripts/translate_bridge.php --action=...
 */

if (!defined('ABSPATH')) {
    // If not loaded through WP-CLI, exit
    if (php_sapi_name() !== 'cli') {
        die("CLI access only.\n");
    }
}

// Read payload from STDIN or argument
$rawInput = file_get_contents('php://stdin');
$inputData = !empty($rawInput) ? json_decode($rawInput, true) : [];

$action = $inputData['action'] ?? ($args[0] ?? 'stats');

global $wpdb;

switch ($action) {
    case 'stats':
        handle_stats();
        break;

    case 'fetch_candidates':
        $lang = sanitize_text_field($inputData['lang'] ?? 'de');
        $limit = max(1, min(100, (int)($inputData['limit'] ?? 25)));
        $excludeIds = array_map('intval', $inputData['exclude_ids'] ?? []);
        $postId = (int)($inputData['post_id'] ?? 0);
        handle_fetch_candidates($lang, $limit, $excludeIds, $postId);
        break;

    case 'save_batch':
        $items = $inputData['items'] ?? [];
        handle_save_batch($items);
        break;

    default:
        echo json_encode(['error' => "Unknown action: {$action}"]);
        exit(1);
}

/**
 * Handle Stats across all languages
 */
function handle_stats() {
    global $wpdb;
    
    $langs = ['en', 'de', 'zh', 'fr', 'es', 'it', 'pl', 'pt', 'nl', 'ja'];
    $counts = [];
    
    // Count published helmets by language
    $sql = "
        SELECT t.slug as lang, count(p.ID) as count
        FROM {$wpdb->posts} p
        INNER JOIN {$wpdb->term_relationships} tr ON (p.ID = tr.object_id)
        INNER JOIN {$wpdb->term_taxonomy} tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id)
        INNER JOIN {$wpdb->terms} t ON (tt.term_id = t.term_id)
        WHERE p.post_type = 'helmet'
          AND p.post_status = 'publish'
          AND tt.taxonomy = 'language'
        GROUP BY t.slug
    ";
    $rows = $wpdb->get_results($sql);
    foreach ($rows as $r) {
        $counts[$r->lang] = (int)$r->count;
    }
    
    foreach ($langs as $l) {
        if (!isset($counts[$l])) {
            $counts[$l] = 0;
        }
    }
    
    echo json_encode([
        'success' => true,
        'total_en' => $counts['en'] ?? 0,
        'languages' => $counts
    ]);
}

/**
 * Fetch candidate helmets missing translation in target language
 */
function handle_fetch_candidates($lang, $limit, $excludeIds = [], $targetPostId = 0) {
    global $wpdb;
    
    $limitVal = max(1, min(100, intval($limit)));
    $excludeSet = array_flip(array_map('intval', $excludeIds));
    
    // 1. Query published English helmets
    if ($targetPostId > 0) {
        $posts = $wpdb->get_results($wpdb->prepare("
            SELECT p.ID, p.post_title, p.post_name, p.post_content, p.post_excerpt
            FROM {$wpdb->posts} p
            WHERE p.ID = %d AND p.post_type = 'helmet'
        ", $targetPostId));
    } else {
        $sql = "
            SELECT p.ID, p.post_title, p.post_name, p.post_content, p.post_excerpt
            FROM {$wpdb->posts} p
            INNER JOIN {$wpdb->term_relationships} tr ON (p.ID = tr.object_id)
            INNER JOIN {$wpdb->term_taxonomy} tt ON (tr.term_taxonomy_id = tt.term_taxonomy_id)
            INNER JOIN {$wpdb->terms} t ON (tt.term_id = t.term_id)
            WHERE p.post_type = 'helmet'
              AND p.post_status = 'publish'
              AND tt.taxonomy = 'language'
              AND t.slug = 'en'
            ORDER BY p.ID ASC
        ";
        $posts = $wpdb->get_results($sql);
    }
    
    $candidates = [];
    
    foreach ($posts as $p) {
        $enId = (int)$p->ID;
        if ($targetPostId <= 0) {
            if (isset($excludeSet[$enId])) {
                continue;
            }
            // Check if already translated using official Polylang API
            if (function_exists('pll_get_post') && pll_get_post($enId, $lang)) {
                continue;
            }
        }
        
        $mkt = get_post_meta($enId, 'marketing_description', true);
        $tech = get_post_meta($enId, 'technical_analysis', true);
        $features = json_decode((string)get_post_meta($enId, 'features_json', true), true) ?: [];
        $sizing = json_decode((string)get_post_meta($enId, 'sizing_fit_json', true), true) ?: [];
        
        $candidates[] = [
            'id' => $enId,
            'title' => $p->post_title,
            'slug' => $p->post_name,
            'content' => $p->post_content,
            'excerpt' => $p->post_excerpt,
            'marketing' => $mkt,
            'tech' => $tech,
            'features' => $features,
            'sizing_fit' => $sizing
        ];
        
        if (count($candidates) >= $limitVal) {
            break;
        }
    }
    
    echo json_encode([
        'success' => true,
        'count' => count($candidates),
        'candidates' => $candidates
    ]);
}

/**
 * Ingest a batch of translated helmets with DB transaction safety and meta filtering
 */
function handle_save_batch($items) {
    global $wpdb;
    
    if (empty($items) || !is_array($items)) {
        echo json_encode(['success' => false, 'error' => 'No items provided']);
        return;
    }
    
    if (function_exists('wp_defer_term_counting')) {
        wp_defer_term_counting(true);
    }
    if (function_exists('wp_defer_comment_counting')) {
        wp_defer_comment_counting(true);
    }
    
    $results = [];
    $taxonomies = get_object_taxonomies('helmet');
    $hasFatalBatchError = false;

    // Start DB Transaction for atomic batch ingestion
    $wpdb->query('START TRANSACTION');

    try {
        foreach ($items as $data) {
            $enId = (int)($data['en_id'] ?? 0);
            $lang = sanitize_text_field($data['lang'] ?? '');
            $title = sanitize_text_field($data['title'] ?? '');
            $slug = sanitize_title($data['slug'] ?? '');
            $content = wp_kses_post($data['content'] ?? '');
            $excerpt = sanitize_text_field($data['excerpt'] ?? '');
            $marketing = sanitize_textarea_field($data['marketing'] ?? '');
            $tech = sanitize_textarea_field($data['tech'] ?? '');
            $features = is_array($data['features'] ?? null) ? $data['features'] : [];
            $sizingFit = is_array($data['sizing_fit'] ?? null) ? $data['sizing_fit'] : [];
            
            if (!$enId || !$lang || empty($title)) {
                $results[] = [
                    'en_id' => $enId,
                    'success' => false,
                    'error' => 'Missing required fields (en_id, lang, title)'
                ];
                continue;
            }
            
            $existingId = function_exists('pll_get_post') ? (int)pll_get_post($enId, $lang) : 0;
            
            $postData = [
                'post_type'    => 'helmet',
                'post_title'   => $title,
                'post_name'    => $slug ?: "helmet-{$enId}-{$lang}",
                'post_content' => $content,
                'post_excerpt' => $excerpt,
                'post_status'  => 'publish'
            ];
            
            if ($existingId > 0) {
                $postData['ID'] = $existingId;
                $newId = wp_update_post($postData);
            } else {
                $newId = wp_insert_post($postData);
            }
            
            if (is_wp_error($newId) || !$newId) {
                $hasFatalBatchError = true;
                $results[] = [
                    'en_id' => $enId,
                    'success' => false,
                    'error' => is_wp_error($newId) ? $newId->get_error_message() : 'wp_insert_post failed'
                ];
                break;
            }
            
            // Polylang assignment & bi-directional linking
            if (function_exists('pll_set_post_language')) {
                pll_set_post_language($newId, $lang);
            }
            if (function_exists('pll_get_post_translations') && function_exists('pll_save_post_translations')) {
                $translations = pll_get_post_translations($enId);
                $translations['en'] = $enId;
                $translations[$lang] = $newId;
                pll_save_post_translations($translations);
            }
            
            // Copy base metadata from master English post (skipping internal edit locks & slug history)
            $meta = get_post_meta($enId);
            $skipMetaKeys = array_flip([
                '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date', '_pingme', '_encloseme'
            ]);

            foreach ($meta as $k => $vals) {
                if (isset($skipMetaKeys[$k])) {
                    continue;
                }
                // Skip fields that are populated with localized data below to avoid redundant DB writes
                if ($k === 'marketing_description' && !empty($marketing)) continue;
                if ($k === 'technical_analysis' && !empty($tech)) continue;
                if ($k === 'features_json' && !empty($features)) continue;
                if ($k === 'sizing_fit_json' && !empty($sizingFit)) continue;
                if ($k === '_yoast_wpseo_metadesc' && !empty($excerpt)) continue;

                foreach ($vals as $v) {
                    if ($lang !== 'zh' && in_array($k, ['_yoast_wpseo_title', '_yoast_wpseo_metadesc'], true)) {
                        if (is_string($v) && preg_match('/[\x{4e00}-\x{9fff}]/u', $v)) {
                            continue;
                        }
                    }
                    update_post_meta($newId, $k, maybe_unserialize($v));
                }
            }
            
            // Save translated localized metadata
            if (!empty($marketing)) {
                update_post_meta($newId, 'marketing_description', $marketing);
            }
            if (!empty($tech)) {
                update_post_meta($newId, 'technical_analysis', $tech);
            }
            if (!empty($features)) {
                update_post_meta($newId, 'features_json', json_encode($features, JSON_UNESCAPED_UNICODE));
            }
            if (!empty($sizingFit)) {
                update_post_meta($newId, 'sizing_fit_json', json_encode($sizingFit, JSON_UNESCAPED_UNICODE));
            }
            if (!empty($excerpt)) {
                update_post_meta($newId, '_yoast_wpseo_metadesc', $excerpt);
            }
            
            // Copy taxonomies with Polylang mapping
            foreach ($taxonomies as $tax) {
                if ($tax === 'language' || $tax === 'post_translations') continue;
                $terms = wp_get_object_terms($enId, $tax, ['fields' => 'ids']);
                if (!empty($terms) && !is_wp_error($terms)) {
                    $targetTerms = [];
                    foreach ($terms as $termId) {
                        $transTermId = function_exists('pll_get_term') ? pll_get_term($termId, $lang) : 0;
                        $targetTerms[] = $transTermId > 0 ? $transTermId : $termId;
                    }
                    wp_set_object_terms($newId, $targetTerms, $tax);
                }
            }
            
            $results[] = [
                'en_id' => $enId,
                'new_id' => $newId,
                'title' => $title,
                'permalink' => get_permalink($newId),
                'success' => true
            ];
        }

        if ($hasFatalBatchError) {
            $wpdb->query('ROLLBACK');
            echo json_encode([
                'success' => false,
                'error' => 'Batch transaction rolled back due to error',
                'results' => $results
            ]);
            return;
        } else {
            $wpdb->query('COMMIT');
        }
    } catch (Throwable $e) {
        $wpdb->query('ROLLBACK');
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        return;
    } finally {
        if (function_exists('wp_defer_term_counting')) {
            wp_defer_term_counting(false);
        }
        if (function_exists('wp_defer_comment_counting')) {
            wp_defer_comment_counting(false);
        }
    }
    
    echo json_encode([
        'success' => true,
        'results' => $results
    ]);
}
