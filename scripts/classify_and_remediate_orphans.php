<?php
/**
 * Helmetsan Orphan Remediation & Cluster Healing Engine
 * Usage: 
 *   wp eval-file /var/www/helmetsan.com/public/wp-content/classify_and_remediate_orphans.php --apply=0 (Dry Run)
 *   wp eval-file /var/www/helmetsan.com/public/wp-content/classify_and_remediate_orphans.php --apply=1 (Live Execution)
 */
defined('ABSPATH') || exit;

if (!defined('WP_CLI') || !WP_CLI) {
    fwrite(STDERR, "This tool must run via WP-CLI.\n");
    exit(1);
}

global $wpdb;

// Parse CLI flags
$apply = false;
$limit = 0;
foreach ($args as $arg) {
    if (strpos($arg, 'apply=') === 0 || $arg === '--apply=1') {
        $apply = (int)str_replace(['apply=', '--apply='], '', $arg) === 1;
    }
    if (strpos($arg, 'limit=') === 0 || strpos($arg, '--limit=') === 0) {
        $limit = (int)str_replace(['limit=', '--limit='], '', $arg);
    }
}
if (isset($assoc_args['apply'])) $apply = (int)$assoc_args['apply'] === 1;
if (isset($assoc_args['limit'])) $limit = (int)$assoc_args['limit'];

WP_CLI::log($apply ? "⚡ LIVE EXECUTION MODE: Changes will be written to DB!" : "🔍 DRY RUN MODE: Auditing orphans without modifying DB.");

// Ensure redirect table exists
$table_redirects = $wpdb->prefix . 'hs_redirects';
$wpdb->query("CREATE TABLE IF NOT EXISTS {$table_redirects} (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    source_path VARCHAR(255) NOT NULL,
    target_path VARCHAR(255) NOT NULL,
    status_code SMALLINT NOT NULL DEFAULT 301,
    created_at DATETIME NOT NULL,
    UNIQUE KEY uq_source (source_path)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;");

// Query all published unclustered helmet posts
$limit_sql = $limit > 0 ? "LIMIT {$limit}" : "";

$orphans = $wpdb->get_results("
    SELECT p.ID, p.post_title, p.post_name, t_lang.slug AS lang, pm_uid.meta_value AS unique_id
    FROM {$wpdb->posts} p
    JOIN {$wpdb->term_relationships} tr_lang ON p.ID = tr_lang.object_id
    JOIN {$wpdb->term_taxonomy} tt_lang ON tr_lang.term_taxonomy_id = tt_lang.term_taxonomy_id AND tt_lang.taxonomy = 'language'
    JOIN {$wpdb->terms} t_lang ON tt_lang.term_id = t_lang.term_id
    LEFT JOIN {$wpdb->term_relationships} tr_cluster ON p.ID = tr_cluster.object_id
        AND tr_cluster.term_taxonomy_id IN (
            SELECT term_taxonomy_id FROM {$wpdb->term_taxonomy} WHERE taxonomy = 'post_translations'
        )
    LEFT JOIN {$wpdb->postmeta} pm_uid ON p.ID = pm_uid.post_id AND pm_uid.meta_key = '_helmet_unique_id'
    WHERE p.post_type = 'helmet' AND p.post_status = 'publish' AND tr_cluster.term_taxonomy_id IS NULL
    {$limit_sql}
");

WP_CLI::log(sprintf("Found %d unclustered orphan candidates to process.", count($orphans)));

$stats = ['A' => 0, 'B' => 0, 'C' => 0, 'D' => 0];
$log_entries = [];

foreach ($orphans as $orphan) {
    $uid = (string)$orphan->unique_id;
    $lang = (string)$orphan->lang;
    $id = (int)$orphan->ID;

    if (empty($uid)) {
        // Class D: No unique ID, corrupt or incomplete
        $stats['D']++;
        WP_CLI::warning(sprintf("[Class D] Post #%d (%s) has empty _helmet_unique_id. Marking 410 candidate.", $id, $lang));
        if ($apply) {
            wp_update_post(['ID' => $id, 'post_status' => 'draft']);
        }
        continue;
    }

    // Check if an existing clustered post exists with this unique_id in this exact language
    $existing_clustered = $wpdb->get_var($wpdb->prepare("
        SELECT p.ID 
        FROM {$wpdb->posts} p
        JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_helmet_unique_id'
        JOIN {$wpdb->term_relationships} tr_l ON p.ID = tr_l.object_id
        JOIN {$wpdb->term_taxonomy} tt_l ON tr_l.term_taxonomy_id = tt_l.term_taxonomy_id AND tt_l.taxonomy = 'language'
        JOIN {$wpdb->terms} t_l ON tt_l.term_id = t_l.term_id
        JOIN {$wpdb->term_relationships} tr_c ON p.ID = tr_c.object_id
        JOIN {$wpdb->term_taxonomy} tt_c ON tr_c.term_taxonomy_id = tt_c.term_taxonomy_id AND tt_c.taxonomy = 'post_translations'
        WHERE p.post_type = 'helmet' AND p.post_status = 'publish'
          AND pm.meta_value = %s AND t_l.slug = %s AND p.ID != %d
        LIMIT 1
    ", $uid, $lang, $id));

    if ($existing_clustered) {
        // Class A: Duplicate of an already clustered post in the same language!
        $stats['A']++;
        $target_url = get_permalink($existing_clustered);
        $source_url = get_permalink($id);
        $src_path = wp_parse_url($source_url, PHP_URL_PATH);
        $tgt_path = wp_parse_url($target_url, PHP_URL_PATH);

        WP_CLI::log(sprintf("[Class A] Post #%d (%s, %s) is DUPLICATE of clustered #%d -> Redirect 301: %s", 
            $id, $lang, $uid, $existing_clustered, $tgt_path));

        if ($apply) {
            if ($src_path && $tgt_path && $src_path !== $tgt_path) {
                $wpdb->replace($table_redirects, [
                    'source_path' => $src_path,
                    'target_path' => $tgt_path,
                    'status_code' => 301,
                    'created_at'  => current_time('mysql')
                ]);
            }
            wp_delete_post($id, true);
        }
    } else {
        // Check if an English canonical exists with this unique_id to attach to (Class B)
        $en_canonical = $wpdb->get_var($wpdb->prepare("
            SELECT p.ID 
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON p.ID = pm.post_id AND pm.meta_key = '_helmet_unique_id'
            JOIN {$wpdb->term_relationships} tr_l ON p.ID = tr_l.object_id
            JOIN {$wpdb->term_taxonomy} tt_l ON tr_l.term_taxonomy_id = tt_l.term_taxonomy_id AND tt_l.taxonomy = 'language'
            JOIN {$wpdb->terms} t_l ON tt_l.term_id = t_l.term_id
            WHERE p.post_type = 'helmet' AND p.post_status = 'publish'
              AND pm.meta_value = %s AND t_l.slug = 'en'
            LIMIT 1
        ", $uid));

        if ($en_canonical && function_exists('pll_get_post_translations')) {
            $translations = pll_get_post_translations($en_canonical);
            if (!isset($translations[$lang])) {
                // Class B: Valid translation missing its cluster link!
                $stats['B']++;
                $translations[$lang] = $id;
                WP_CLI::log(sprintf("[Class B] Re-clustering Post #%d (%s, %s) into English Canonical #%d", 
                    $id, $lang, $uid, $en_canonical));

                if ($apply && function_exists('pll_save_post_translations')) {
                    pll_save_post_translations($translations);
                }
            } else {
                // Another post already claimed that language in the cluster
                $stats['A']++;
                $survivor = $translations[$lang];
                $src_path = wp_parse_url(get_permalink($id), PHP_URL_PATH);
                $tgt_path = wp_parse_url(get_permalink($survivor), PHP_URL_PATH);
                WP_CLI::log(sprintf("[Class A-Collision] Post #%d (%s) collided with cluster member #%d -> Redirect 301", 
                    $id, $lang, $survivor));
                if ($apply && $src_path && $tgt_path && $src_path !== $tgt_path) {
                    $wpdb->replace($table_redirects, [
                        'source_path' => $src_path,
                        'target_path' => $tgt_path,
                        'status_code' => 301,
                        'created_at'  => current_time('mysql')
                    ]);
                    wp_delete_post($id, true);
                }
            }
        } else {
            // Class C: Unique product or no EN counterpart
            $stats['C']++;
            WP_CLI::log(sprintf("[Class C] Post #%d (%s, %s) is unique to this locale.", $id, $lang, $uid));
        }
    }
}

WP_CLI::success(sprintf("Remediation Audit Complete! Class A (Duplicates): %d | Class B (Re-cluster): %d | Class C (Unique): %d | Class D (Corrupt): %d", 
    $stats['A'], $stats['B'], $stats['C'], $stats['D']));
