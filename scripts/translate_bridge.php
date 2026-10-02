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
        $order = strtoupper($inputData['order'] ?? 'ASC');
        $shardId = max(0, (int)($inputData['shard_id'] ?? 0));
        $numShards = max(1, (int)($inputData['num_shards'] ?? 1));
        $offset = max(0, (int)($inputData['offset'] ?? 0));
        handle_fetch_candidates($lang, $limit, $excludeIds, $postId, $order, $shardId, $numShards, $offset);
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
function handle_fetch_candidates($lang, $limit, $excludeIds = [], $targetPostId = 0, $order = 'ASC', $shardId = 0, $numShards = 1, $offset = 0) {
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
        $shardSql = "";
        if ($numShards > 1) {
            $shardSql = $wpdb->prepare(" AND (p.ID %% %d) = %d", $numShards, $shardId);
        }
        
        $orderSql = ($order === 'DESC') ? "ORDER BY p.ID DESC" : "ORDER BY p.ID ASC";
        if ($order === 'RAND') {
            $orderSql = "ORDER BY RAND()";
        }
        
        $offsetSql = "";
        if ($offset > 0) {
            $offsetSql = $wpdb->prepare(" OFFSET %d", $offset);
        }

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
              {$shardSql}
            {$orderSql}
            {$offsetSql}
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
    
    // Acquire system-wide exclusive lock across all CLI instances to prevent InnoDB deadlocks
    $lockFp = fopen('/tmp/helmetsan_bridge_save.lock', 'c+');
    if ($lockFp) {
        flock($lockFp, LOCK_EX);
    }
    
    if (function_exists('wp_defer_term_counting')) {
        wp_defer_term_counting(true);
    }
    if (function_exists('wp_defer_comment_counting')) {
        wp_defer_comment_counting(true);
    }
    if (function_exists('wp_suspend_cache_invalidation')) {
        wp_suspend_cache_invalidation(true);
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
            
            $nowLocal = current_time('mysql');
            $nowGmt   = current_time('mysql', 1);

            $computedSlug = $slug;
            if (empty($computedSlug)) {
                $computedSlug = sanitize_title($title);
            }
            if (empty($computedSlug)) {
                $computedSlug = "helmet-{$enId}-{$lang}";
            }

            $existingId = function_exists('pll_get_post') ? (int)pll_get_post($enId, $lang) : 0;
            
            if ($existingId > 0) {
                $newId = $existingId;
                $wpdb->update(
                    $wpdb->posts,
                    [
                        'post_title'        => $title,
                        'post_content'      => $content,
                        'post_excerpt'      => $excerpt,
                        'post_name'         => $computedSlug,
                        'post_modified'     => $nowLocal,
                        'post_modified_gmt' => $nowGmt,
                    ],
                    ['ID' => $newId],
                    ['%s', '%s', '%s', '%s', '%s', '%s'],
                    ['%d']
                );
            } else {
                // High-speed direct SQL insert bypassing 2,600ms wp_insert_post overhead
                $postData = [
                    'post_author'           => 1,
                    'post_date'             => $nowLocal,
                    'post_date_gmt'         => $nowGmt,
                    'post_content'          => $content,
                    'post_title'            => $title,
                    'post_excerpt'          => $excerpt,
                    'post_status'           => 'publish',
                    'comment_status'        => 'closed',
                    'ping_status'           => 'closed',
                    'post_password'         => '',
                    'post_name'             => $computedSlug,
                    'to_ping'               => '',
                    'pinged'                => '',
                    'post_modified'         => $nowLocal,
                    'post_modified_gmt'     => $nowGmt,
                    'post_content_filtered' => '',
                    'post_parent'           => 0,
                    'guid'                  => '',
                    'menu_order'            => 0,
                    'post_type'             => 'helmet',
                    'post_mime_type'        => '',
                    'comment_count'         => 0,
                ];

                $inserted = $wpdb->insert($wpdb->posts, $postData);
                if (false === $inserted) {
                    $hasFatalBatchError = true;
                    $results[] = [
                        'en_id' => $enId,
                        'success' => false,
                        'error' => 'wpdb->insert failed: ' . $wpdb->last_error
                    ];
                    break;
                }
                $newId = (int)$wpdb->insert_id;

                // Set stable canonical GUID
                $wpdb->update(
                    $wpdb->posts,
                    ['guid' => home_url('/?post_type=helmet&p=' . $newId)],
                    ['ID' => $newId],
                    ['%s'],
                    ['%d']
                );
            }
            
            // Polylang assignment & atomic bi-directional linking
            if (function_exists('pll_set_post_language')) {
                pll_set_post_language($newId, $lang);
            }
            if (function_exists('pll_get_post_translations') && function_exists('pll_save_post_translations')) {
                $existing = pll_get_post_translations($enId) ?: [];
                $translations = array_merge($existing, ['en' => $enId, $lang => $newId]);
                pll_save_post_translations($translations);
            }
            
            // Copy base metadata and localized data using high-speed bulk SQL insert
            $metaRows = [];
            $metaValues = [];

            $meta = get_post_meta($enId);
            $skipMetaKeys = array_flip([
                '_edit_lock', '_edit_last', '_wp_old_slug', '_wp_old_date', '_pingme', '_encloseme'
            ]);

            foreach ($meta as $k => $vals) {
                if (isset($skipMetaKeys[$k])) {
                    continue;
                }
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
                    $metaRows[] = "(%d, %s, %s)";
                    $metaValues[] = $newId;
                    $metaValues[] = $k;
                    $metaValues[] = is_string($v) ? $v : maybe_serialize($v);
                }
            }

            // Append localized fields
            if (!empty($marketing)) {
                $metaRows[] = "(%d, %s, %s)";
                $metaValues[] = $newId;
                $metaValues[] = 'marketing_description';
                $metaValues[] = $marketing;
            }
            if (!empty($tech)) {
                $metaRows[] = "(%d, %s, %s)";
                $metaValues[] = $newId;
                $metaValues[] = 'technical_analysis';
                $metaValues[] = $tech;
            }
            if (!empty($features)) {
                $metaRows[] = "(%d, %s, %s)";
                $metaValues[] = $newId;
                $metaValues[] = 'features_json';
                $metaValues[] = json_encode($features, JSON_UNESCAPED_UNICODE);
            }
            if (!empty($sizingFit)) {
                $metaRows[] = "(%d, %s, %s)";
                $metaValues[] = $newId;
                $metaValues[] = 'sizing_fit_json';
                $metaValues[] = json_encode($sizingFit, JSON_UNESCAPED_UNICODE);
            }
            if (!empty($excerpt)) {
                $metaRows[] = "(%d, %s, %s)";
                $metaValues[] = $newId;
                $metaValues[] = '_yoast_wpseo_metadesc';
                $metaValues[] = $excerpt;
            }

            if ($existingId > 0) {
                $wpdb->delete($wpdb->postmeta, ['post_id' => $newId]);
            }

            if (!empty($metaRows)) {
                $bulkSql = "INSERT INTO {$wpdb->postmeta} (post_id, meta_key, meta_value) VALUES " . implode(',', $metaRows);
                $wpdb->query($wpdb->prepare($bulkSql, $metaValues));
            }
            
            // Copy taxonomies with Polylang mapping (fast under deferred term counting)
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

            // Invalidate post caches and prime metadata cache
            clean_post_cache($newId);
            if (function_exists('wp_cache_delete')) {
                wp_cache_delete($newId, 'post_meta');
            }
            if (function_exists('update_meta_cache')) {
                update_meta_cache('post', [$newId]);
            }
            
            $results[] = [
                'en_id' => $enId,
                'new_id' => $newId,
                'title' => $title,
                'permalink' => get_permalink($newId),
                'status' => ($existingId > 0) ? 'updated' : 'created',
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
        if (function_exists('wp_suspend_cache_invalidation')) {
            wp_suspend_cache_invalidation(false);
        }
        if (function_exists('wp_defer_term_counting')) {
            wp_defer_term_counting(false);
        }
        if (function_exists('wp_defer_comment_counting')) {
            wp_defer_comment_counting(false);
        }
        if (!empty($lockFp)) {
            flock($lockFp, LOCK_UN);
            fclose($lockFp);
        }
    }
    
    echo json_encode([
        'success' => true,
        'results' => $results
    ]);
}
