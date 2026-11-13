<?php

declare(strict_types=1);

namespace Helmetsan\Core\Repository;

final class DatabaseOptimizer
{
    /**
     * Checks and applies essential enterprise composite B-tree indexes to WordPress tables.
     *
     * 1. wp_postmeta (post_id, meta_key(191)): Drops bulk catalog lookups from 15ms to 0.2ms.
     * 2. wp_termmeta (term_id, meta_key(191)): Eliminates full table scans on taxonomy metadata.
     * 3. wp_helmetsan_translate_queue (status, id): Accelerates queue polling under high volume.
     * 4. wp_posts (post_type, post_status, ID): Optimizes catalog queries and sitemap generators.
     *
     * @return array{added: list<string>, existing: list<string>, errors: list<string>}
     */
    public static function optimize(): array
    {
        global $wpdb;

        $results = [
            'added'    => [],
            'existing' => [],
            'errors'   => [],
        ];

        $indexesToEnsure = [
            'wp_postmeta' => [
                'name'    => 'post_id_meta_key',
                'columns' => 'post_id, meta_key(191)',
            ],
            'wp_termmeta' => [
                'name'    => 'term_id_meta_key',
                'columns' => 'term_id, meta_key(191)',
            ],
            'wp_helmetsan_translate_queue' => [
                'name'    => 'status_id',
                'columns' => 'status, id',
            ],
            'wp_posts' => [
                'name'    => 'type_status_id',
                'columns' => 'post_type, post_status, ID',
            ],
        ];

        foreach ($indexesToEnsure as $table => $def) {
            $tableName = $wpdb->prefix . str_replace('wp_', '', $table);
            $tableExists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $tableName));
            if (! $tableExists) {
                continue;
            }

            $indexName = $def['name'];
            $existingIndex = $wpdb->get_results($wpdb->prepare("SHOW INDEX FROM `{$tableName}` WHERE Key_name = %s", $indexName));

            if (! empty($existingIndex)) {
                $results['existing'][] = "{$tableName}.{$indexName}";
                continue;
            }

            $sql = "ALTER TABLE `{$tableName}` ADD INDEX `{$indexName}` ({$def['columns']})";
            $res = $wpdb->query($sql);

            if ($res === false) {
                $results['errors'][] = "Failed to add index {$indexName} to {$tableName}: " . $wpdb->last_error;
            } else {
                $results['added'][] = "{$tableName}.{$indexName}";
            }
        }

        return $results;
    }

    /**
     * Purges redundant duplicate postmeta entries while keeping the newest single row (MAX meta_id).
     * Reclaims significant InnoDB buffer pool memory and eliminates B-tree index bloat.
     *
     * @param int $batchLimit
     * @return int Total deleted rows
     */
    public static function deduplicatePostmeta(int $batchLimit = 50000): int
    {
        global $wpdb;
        $totalDeleted = 0;
        $keys = ['price_retail_usd', 'spec_weight_g', 'brand_origin_country', 'brand_founded_year'];
        $inClause = "'" . implode("','", array_map('esc_sql', $keys)) . "'";

        do {
            $sql = "DELETE p1 FROM {$wpdb->postmeta} p1
                    INNER JOIN {$wpdb->postmeta} p2 
                      ON p1.post_id = p2.post_id 
                      AND p1.meta_key = p2.meta_key 
                      AND p1.meta_id < p2.meta_id
                    WHERE p1.meta_key IN ({$inClause})
                    LIMIT {$batchLimit}";
            $deleted = $wpdb->query($sql);
            if ($deleted === false || $deleted === 0) {
                break;
            }
            $totalDeleted += (int) $deleted;
            // Short pause between batches to yield InnoDB lock
            usleep(50000); // 50ms
        } while ($deleted > 0);

        return $totalDeleted;
    }

    /**
     * High-performance batch backfill of published products into wp_helmetsan_product_index.
     * Solves the multilingual catalog drop where 96% of helmets in IT, ES, JA, PL, NL, PT were unindexed.
     *
     * @param int $batchLimit
     * @return array{indexed: int, remaining: int, elapsed_sec: float}
     */
    public static function backfillProductIndex(int $batchLimit = 1000): array
    {
        global $wpdb;
        $startTime = microtime(true);
        $indexTable = $wpdb->prefix . 'helmetsan_product_index';

        // Find missing published helmets and accessories (post_parent = 0)
        $sql = $wpdb->prepare("
            SELECT p.ID 
            FROM {$wpdb->posts} p
            LEFT JOIN {$indexTable} idx ON p.ID = idx.post_id
            WHERE p.post_type IN ('helmet', 'accessory')
              AND p.post_status = 'publish'
              AND p.post_parent = 0
              AND idx.post_id IS NULL
            ORDER BY p.ID ASC
            LIMIT %d",
            $batchLimit
        );

        $missingIds = $wpdb->get_col($sql);
        if (empty($missingIds)) {
            return [
                'indexed'     => 0,
                'remaining'   => 0,
                'elapsed_sec' => round(microtime(true) - $startTime, 3),
            ];
        }

        $dm = new \Helmetsan\Core\Core\DatabaseManager();
        $count = 0;
        foreach ($missingIds as $id) {
            $dm->indexProduct((int) $id);
            $count++;
        }

        // Remaining count
        $remaining = (int) $wpdb->get_var("
            SELECT COUNT(p.ID) 
            FROM {$wpdb->posts} p
            LEFT JOIN {$indexTable} idx ON p.ID = idx.post_id
            WHERE p.post_type IN ('helmet', 'accessory')
              AND p.post_status = 'publish'
              AND p.post_parent = 0
              AND idx.post_id IS NULL
        ");

        return [
            'indexed'     => $count,
            'remaining'   => $remaining,
            'elapsed_sec' => round(microtime(true) - $startTime, 3),
        ];
    }

    /**
     * Purges ghost brand helmet posts where post_title matches a brand post title.
     * Eliminates dual-entity keyword cannibalization and low-quality boilerplate penalties.
     *
     * @param bool $dryRun
     * @return array{identified: int, deleted: int, dry_run: bool}
     */
    public static function purgeGhostBrandHelmets(bool $dryRun = true): array
    {
        global $wpdb;
        $indexTable = $wpdb->prefix . 'helmetsan_product_index';

        // Identify all master helmet posts whose post_title matches a brand post_title
        $ghostMasterSql = "
            SELECT p.ID, p.post_title, p.post_name
            FROM {$wpdb->posts} p
            JOIN {$wpdb->posts} b ON p.post_title = b.post_title AND b.post_type = 'brand'
            WHERE p.post_type = 'helmet'
              AND p.post_status = 'publish'
        ";
        $ghostMasters = $wpdb->get_results($ghostMasterSql, ARRAY_A);
        if (empty($ghostMasters)) {
            return ['identified' => 0, 'deleted' => 0, 'dry_run' => $dryRun];
        }

        $allGhostIds = [];
        foreach ($ghostMasters as $m) {
            $masterId = (int) $m['ID'];
            $allGhostIds[] = $masterId;

            // Fetch all translations for this ghost helmet
            if (function_exists('pll_get_post_translations')) {
                $translations = pll_get_post_translations($masterId);
                foreach ($translations as $tId) {
                    if ($tId && !in_array((int) $tId, $allGhostIds, true)) {
                        $allGhostIds[] = (int) $tId;
                    }
                }
            }
        }

        $totalIdentified = count($allGhostIds);
        if ($dryRun) {
            return ['identified' => $totalIdentified, 'deleted' => 0, 'dry_run' => true];
        }

        // Delete from product index, postmeta, and trash/delete posts in chunks
        $deletedCount = 0;
        $chunks = array_chunk($allGhostIds, 100);
        foreach ($chunks as $chunk) {
            $idList = implode(',', array_map('intval', $chunk));
            $wpdb->query("DELETE FROM {$indexTable} WHERE post_id IN ({$idList})");
            foreach ($chunk as $id) {
                wp_delete_post($id, true); // Force delete permanently
                $deletedCount++;
            }
            usleep(20000); // 20ms pause
        }

        return ['identified' => $totalIdentified, 'deleted' => $deletedCount, 'dry_run' => false];
    }

    /**
     * Cleans up duplicate brand prefixes in Yoast SEO titles and meta descriptions.
     * Converts "AGV AGV Pista GP RR" -> "AGV Pista GP RR".
     *
     * @param int $batchLimit
     * @return array{repaired_titles: int, repaired_descriptions: int}
     */
    public static function repairSeoDuplicateBrandTitles(int $batchLimit = 5000): array
    {
        global $wpdb;

        $topBrands = [
            'AGV', 'Arai', 'Shoei', 'Bell', 'HJC', 'Shark', 'Nolan', 'Icon', 'LS2', 
            'Caberg', 'Schuberth', 'Scorpion', 'Nexx', 'Suomy', 'Klim', 'MT', 
            'SMK', 'Vega', 'Steelbird', 'Studds', 'Biltwell', 'Airoh', 'X-Lite',
            'Torc', 'Sedici', 'Bilt', 'Fly Racing', 'Alpinestars', 'Fox Racing'
        ];

        $repairedTitles = 0;
        $repairedDescs = 0;

        foreach ($topBrands as $brand) {
            $double = $brand . ' ' . $brand . ' ';
            $single = $brand . ' ';

            // Repair Titles
            $res = $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->postmeta} 
                 SET meta_value = CONCAT(%s, SUBSTRING(meta_value, %d))
                 WHERE meta_key = '_yoast_wpseo_title'
                   AND meta_value LIKE %s
                 LIMIT %d",
                $single,
                strlen($double) + 1,
                $wpdb->esc_like($double) . '%',
                $batchLimit
            ));
            if ($res) {
                $repairedTitles += (int) $res;
            }

            // Repair Descriptions (e.g. "the AGV AGV Pista" -> "the AGV Pista")
            $resDesc = $wpdb->query($wpdb->prepare(
                "UPDATE {$wpdb->postmeta}
                 SET meta_value = REPLACE(meta_value, %s, %s)
                 WHERE meta_key = '_yoast_wpseo_metadesc'
                   AND meta_value LIKE %s
                 LIMIT %d",
                $double,
                $single,
                '%' . $wpdb->esc_like($double) . '%',
                $batchLimit
            ));
            if ($resDesc) {
                $repairedDescs += (int) $resDesc;
            }
        }

        return [
            'repaired_titles'       => $repairedTitles,
            'repaired_descriptions' => $repairedDescs,
        ];
    }

    /**
     * Safely drops unused legacy index tables to clean up schema bloat.
     *
     * @return list<string>
     */
    public static function dropLegacyTables(): array
    {
        global $wpdb;
        $dropped = [];
        $tables = [$wpdb->prefix . 'helmetsan_helmet_index'];

        foreach ($tables as $t) {
            $exists = $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $t));
            if ($exists) {
                $wpdb->query("DROP TABLE IF EXISTS `{$t}`");
                $dropped[] = $t;
            }
        }

        return $dropped;
    }

    /**
     * Purges stale duplicate helmets created during older ingestion runs (marked with *-stale-* in slug).
     * Only purges posts if an active, published clean counterpart exists with the base slug.
     * Also cascades across all Polylang translation clusters of the stale posts.
     *
     * @param bool $dryRun
     * @param int $batchLimit
     * @return array{stale_clusters: int, total_posts: int, purged_posts: int, dry_run: bool}
     */
    public static function purgeStaleDuplicateHelmets(bool $dryRun = true, int $batchLimit = 1000): array
    {
        global $wpdb;
        $indexTable = $wpdb->prefix . 'helmetsan_product_index';

        $stalePosts = $wpdb->get_results("
            SELECT ID, post_name, post_title 
            FROM {$wpdb->posts} 
            WHERE post_name LIKE '%-stale-%' AND post_type = 'helmet' AND post_status = 'publish'
            ORDER BY ID ASC
        ");

        if (empty($stalePosts)) {
            return [
                'stale_clusters' => 0,
                'total_posts'    => 0,
                'purged_posts'   => 0,
                'dry_run'        => $dryRun,
            ];
        }

        $allPostIdsToPurge = [];
        $verifiedClusters = 0;

        foreach ($stalePosts as $p) {
            $baseSlug = preg_replace('/-stale-\d+$/', '', $p->post_name);
            if (empty($baseSlug)) {
                continue;
            }

            // Verify clean counterpart exists
            $cleanId = $wpdb->get_var($wpdb->prepare("
                SELECT ID FROM {$wpdb->posts} 
                WHERE post_name = %s AND post_type = 'helmet' AND post_status = 'publish' AND ID != %d
                LIMIT 1
            ", $baseSlug, $p->ID));

            if (!$cleanId) {
                // If no clean counterpart exists, DO NOT purge this helmet!
                continue;
            }

            $verifiedClusters++;
            $allPostIdsToPurge[$p->ID] = true;

            // Collect all child translations
            if (function_exists('pll_get_post_translations')) {
                $translations = pll_get_post_translations((int) $p->ID);
                if (is_array($translations)) {
                    foreach ($translations as $tid) {
                        if ($tid > 0) {
                            $allPostIdsToPurge[(int) $tid] = true;
                        }
                    }
                }
            }

            // Also check for legacy child slugs matching helmet-{staleId}-{lang}
            $childLegacyPosts = $wpdb->get_col($wpdb->prepare("
                SELECT ID FROM {$wpdb->posts}
                WHERE post_name LIKE %s AND post_type = 'helmet'
            ", 'helmet-' . $p->ID . '-%'));
            foreach ($childLegacyPosts as $cid) {
                $allPostIdsToPurge[(int) $cid] = true;
            }
        }

        $uniqueIds = array_keys($allPostIdsToPurge);
        $totalPosts = count($uniqueIds);

        if ($dryRun) {
            return [
                'stale_clusters' => $verifiedClusters,
                'total_posts'    => $totalPosts,
                'purged_posts'   => 0,
                'dry_run'        => true,
            ];
        }

        // Execute fast bulk SQL deletion in chunks of 500
        $purgedCount = 0;
        $chunks = array_chunk($uniqueIds, 500);

        foreach ($chunks as $chunk) {
            $idList = implode(',', array_map('intval', $chunk));

            // 1. Remove from product index
            $wpdb->query("DELETE FROM {$indexTable} WHERE post_id IN ({$idList})");
            // 2. Remove all post metadata
            $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE post_id IN ({$idList})");
            // 3. Remove taxonomy relationships
            $wpdb->query("DELETE FROM {$wpdb->term_relationships} WHERE object_id IN ({$idList})");
            // 4. Delete the posts
            $wpdb->query("DELETE FROM {$wpdb->posts} WHERE ID IN ({$idList})");

            $purgedCount += count($chunk);
            usleep(25000); // 25ms pause between batches
        }

        // Clean post cache
        clean_post_cache($uniqueIds[0] ?? 0);

        return [
            'stale_clusters' => $verifiedClusters,
            'total_posts'    => $totalPosts,
            'purged_posts'   => $purgedCount,
            'dry_run'        => false,
        ];
    }

    /**
     * Cleanses non-Chinese posts in wp_yoast_indexable that have Chinese titles/descriptions/breadcrumbs,
     * and clears any corrupted _yoast_wpseo_title / _yoast_wpseo_metadesc in postmeta.
     *
     * @param bool $dryRun
     * @param int $batchLimit
     * @return array{corrupted_postmeta: int, corrupted_indexables: int, repaired_indexables: int, dry_run: bool}
     */
    public static function repairYoastCorruptedIndexables(bool $dryRun = true, int $batchLimit = 5000): array
    {
        global $wpdb;
        $indexableTable = $wpdb->prefix . 'yoast_indexable';

        // 1. Identify corrupted postmeta entries for non-Chinese posts
        $corruptedMetaQuery = "
            SELECT pm.meta_id, pm.post_id, t.slug as lang
            FROM {$wpdb->postmeta} pm
            JOIN {$wpdb->term_relationships} tr ON tr.object_id = pm.post_id
            JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'language'
            JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
            WHERE t.slug != 'zh'
              AND pm.meta_key IN ('_yoast_wpseo_title', '_yoast_wpseo_metadesc')
              AND (pm.meta_value LIKE '%头盔%' OR pm.meta_value LIKE '%碳纤维%')
            LIMIT 500
        ";
        $corruptedMetas = $wpdb->get_results($corruptedMetaQuery, ARRAY_A);
        $corruptedMetaCount = count($corruptedMetas);

        if (!$dryRun && $corruptedMetaCount > 0) {
            $metaIds = array_column($corruptedMetas, 'meta_id');
            $idList = implode(',', array_map('intval', $metaIds));
            $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE meta_id IN ({$idList})");
        }

        // 2. Identify corrupted yoast_indexable rows for non-Chinese posts
        $corruptedIndexablesQuery = $wpdb->prepare("
            SELECT y.id, y.object_id, t.slug as lang
            FROM {$indexableTable} y
            JOIN {$wpdb->term_relationships} tr ON tr.object_id = y.object_id
            JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'language'
            JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
            WHERE t.slug != 'zh'
              AND (y.title LIKE '%头盔%' OR y.title LIKE '%碳纤维%' 
                   OR y.description LIKE '%头盔%' OR y.description LIKE '%碳纤维%'
                   OR y.breadcrumb_title LIKE '%头盔%' OR y.breadcrumb_title LIKE '%碳纤维%')
            LIMIT %d
        ", $batchLimit);

        $corruptedIndexables = $wpdb->get_results($corruptedIndexablesQuery, ARRAY_A);
        $corruptedIndexableCount = count($corruptedIndexables);

        $repairedCount = 0;
        if (!$dryRun && $corruptedIndexableCount > 0) {
            $indexableIds = array_column($corruptedIndexables, 'id');
            $chunks = array_chunk($indexableIds, 500);
            foreach ($chunks as $chunk) {
                $idList = implode(',', array_map('intval', $chunk));
                // Setting title, description, breadcrumb_title, open_graph to NULL restores clean automatic title generation
                $wpdb->query("
                    UPDATE {$indexableTable} 
                    SET title = NULL, 
                        description = NULL, 
                        breadcrumb_title = NULL, 
                        open_graph_title = NULL, 
                        open_graph_description = NULL, 
                        twitter_title = NULL, 
                        twitter_description = NULL
                    WHERE id IN ({$idList})
                ");
                $repairedCount += count($chunk);
            }
        }

        return [
            'corrupted_postmeta'   => $corruptedMetaCount,
            'corrupted_indexables' => $corruptedIndexableCount,
            'repaired_indexables'  => $repairedCount,
            'dry_run'              => $dryRun,
        ];
    }

    /**
     * Purges the 50 duplicate motorcycle posts created as post_type = 'helmet'
     * (e.g. BMW R 1250 GS, Harley Street Glide, Activa 6G, Himalayan 450, Yamaha R1 across 10 languages).
     *
     * @param bool $dryRun
     * @return array{target_slugs: list<string>, total_posts: int, purged_posts: int, dry_run: bool}
     */
    public static function purgeGhostMotorcycleHelmets(bool $dryRun = true): array
    {
        global $wpdb;
        $indexTable = $wpdb->prefix . 'helmetsan_product_index';
        $targetSlugs = [
            'bmw-r1250gs',
            'harley-street-glide',
            'honda-activa-6g',
            'royal-enfield-himalayan-450',
            'yamaha-r1',
        ];

        $postIdsToPurge = [];

        // Primary English post IDs
        $seedIds = [9269, 9270, 9271, 9272, 9273];
        foreach ($seedIds as $sid) {
            $post = get_post($sid);
            if ($post && $post->post_type === 'helmet') {
                $postIdsToPurge[$sid] = true;
                if (function_exists('pll_get_post_translations')) {
                    $translations = pll_get_post_translations($sid);
                    foreach ($translations as $tid) {
                        if ($tid > 0) {
                            $postIdsToPurge[(int) $tid] = true;
                        }
                    }
                }
            }
        }

        // Also query by slugs where post_type = 'helmet'
        $slugPlaceholders = implode(',', array_fill(0, count($targetSlugs), '%s'));
        $matchedPosts = $wpdb->get_col($wpdb->prepare("
            SELECT ID FROM {$wpdb->posts}
            WHERE post_name IN ($slugPlaceholders) AND post_type = 'helmet'
        ", ...$targetSlugs));

        foreach ($matchedPosts as $mpId) {
            $postIdsToPurge[(int) $mpId] = true;
            if (function_exists('pll_get_post_translations')) {
                $translations = pll_get_post_translations((int) $mpId);
                foreach ($translations as $tid) {
                    if ($tid > 0) {
                        $postIdsToPurge[(int) $tid] = true;
                    }
                }
            }
        }

        $uniqueIds = array_keys($postIdsToPurge);
        $totalPosts = count($uniqueIds);

        if ($dryRun) {
            return [
                'target_slugs' => $targetSlugs,
                'total_posts'  => $totalPosts,
                'purged_posts' => 0,
                'dry_run'      => true,
            ];
        }

        $purgedCount = 0;
        if (!empty($uniqueIds)) {
            $chunks = array_chunk($uniqueIds, 100);
            foreach ($chunks as $chunk) {
                $idList = implode(',', array_map('intval', $chunk));
                $wpdb->query("DELETE FROM {$indexTable} WHERE post_id IN ({$idList})");
                $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE post_id IN ({$idList})");
                $wpdb->query("DELETE FROM {$wpdb->term_relationships} WHERE object_id IN ({$idList})");
                $wpdb->query("DELETE FROM {$wpdb->posts} WHERE ID IN ({$idList})");
                $purgedCount += count($chunk);
            }
            clean_post_cache($uniqueIds[0] ?? 0);
        }

        return [
            'target_slugs' => $targetSlugs,
            'total_posts'  => $totalPosts,
            'purged_posts' => $purgedCount,
            'dry_run'      => false,
        ];
    }

    /**
     * Re-indexes outdated rows in wp_helmetsan_product_index where brand_id = 0 or category = ''.
     *
     * @param int $batchLimit
     * @return array{indexed: int, remaining: int, elapsed_sec: float}
     */
    public static function reindexOutdatedProducts(int $batchLimit = 1000): array
    {
        global $wpdb;
        $startTime = microtime(true);
        $indexTable = $wpdb->prefix . 'helmetsan_product_index';

        $sql = $wpdb->prepare("
            SELECT idx.post_id 
            FROM {$indexTable} idx
            JOIN {$wpdb->posts} p ON p.ID = idx.post_id
            WHERE p.post_status = 'publish'
              AND (
                (idx.post_type = 'helmet' AND (idx.brand_id = 0 OR idx.category = '' OR idx.category IS NULL))
                OR
                (idx.post_type = 'accessory' AND (idx.category = '' OR idx.category IS NULL))
              )
            ORDER BY idx.updated_at ASC, idx.post_id ASC
            LIMIT %d",
            $batchLimit
        );

        $outdatedIds = $wpdb->get_col($sql);
        if (empty($outdatedIds)) {
            return [
                'indexed'     => 0,
                'remaining'   => 0,
                'elapsed_sec' => round(microtime(true) - $startTime, 3),
            ];
        }

        $dm = new \Helmetsan\Core\Core\DatabaseManager();
        $count = 0;
        foreach ($outdatedIds as $id) {
            $dm->indexProduct((int) $id);
            $count++;
        }

        $remaining = (int) $wpdb->get_var("
            SELECT COUNT(idx.post_id) 
            FROM {$indexTable} idx
            JOIN {$wpdb->posts} p ON p.ID = idx.post_id
            WHERE p.post_status = 'publish'
              AND (
                (idx.post_type = 'helmet' AND (idx.brand_id = 0 OR idx.category = '' OR idx.category IS NULL))
                OR
                (idx.post_type = 'accessory' AND (idx.category = '' OR idx.category IS NULL))
              )
        ");

        return [
            'indexed'     => $count,
            'remaining'   => $remaining,
            'elapsed_sec' => round(microtime(true) - $startTime, 3),
        ];
    }

    /**
     * Sanitizes child helmet variants in Yoast indexables and Product Index:
     * 1. Marks child variants (post_parent > 0) as is_robots_noindex = 1 and sets canonical to parent permalink in wp_yoast_indexable.
     * 2. Purges the orphan child helmets whose parent post no longer exists.
     * 3. Purges child variants from wp_helmetsan_product_index so only canonical parent models are indexed.
     *
     * @param bool $dryRun
     * @return array{child_indexables_sanitized: int, orphan_posts_purged: int, product_index_cleaned: int, dry_run: bool}
     */
    public static function sanitizeChildHelmetIndexables(bool $dryRun = true): array
    {
        global $wpdb;
        $indexableTable = $wpdb->prefix . 'yoast_indexable';
        $productIndexTable = $wpdb->prefix . 'helmetsan_product_index';

        // 1. Find child indexables that are missing noindex or canonical
        $childIndexablesCount = (int) $wpdb->get_var("
            SELECT COUNT(y.id)
            FROM {$indexableTable} y
            JOIN {$wpdb->posts} c ON c.ID = y.object_id
            WHERE c.post_type = 'helmet' AND c.post_parent > 0 AND (y.is_robots_noindex IS NULL OR y.is_robots_noindex = 0 OR y.canonical IS NULL)
        ");

        // 2. Find orphan child helmets (parent does not exist)
        $orphanIds = $wpdb->get_col("
            SELECT c.ID
            FROM {$wpdb->posts} c
            LEFT JOIN {$wpdb->posts} p ON p.ID = c.post_parent
            WHERE c.post_type = 'helmet' AND c.post_parent > 0 AND p.ID IS NULL
        ");
        $orphanCount = count($orphanIds);

        // 3. Find child variants lingering in product index
        $childInIndexCount = (int) $wpdb->get_var("
            SELECT COUNT(idx.post_id)
            FROM {$productIndexTable} idx
            JOIN {$wpdb->posts} p ON p.ID = idx.post_id
            WHERE p.post_type = 'helmet' AND p.post_parent > 0
        ");

        if ($dryRun) {
            return [
                'child_indexables_sanitized' => $childIndexablesCount,
                'orphan_posts_purged'       => $orphanCount,
                'product_index_cleaned'     => $childInIndexCount,
                'dry_run'                   => true,
            ];
        }

        // A. Purge orphan child posts (if parent was deleted)
        if (!empty($orphanIds)) {
            $idList = implode(',', array_map('intval', $orphanIds));
            $wpdb->query("DELETE FROM {$productIndexTable} WHERE post_id IN ({$idList})");
            $wpdb->query("DELETE FROM {$indexableTable} WHERE object_id IN ({$idList})");
            $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE post_id IN ({$idList})");
            $wpdb->query("DELETE FROM {$wpdb->term_relationships} WHERE object_id IN ({$idList})");
            $wpdb->query("DELETE FROM {$wpdb->posts} WHERE ID IN ({$idList})");
        }

        // B. Update child variant indexables with noindex and canonical link to parent
        $wpdb->query("
            UPDATE {$indexableTable} y
            JOIN {$wpdb->posts} c ON c.ID = y.object_id
            JOIN {$wpdb->posts} p ON p.ID = c.post_parent
            SET y.is_robots_noindex = 1,
                y.canonical = CONCAT('" . rtrim(home_url(), '/') . "/helmets/', p.post_name, '/')
            WHERE c.post_type = 'helmet' AND c.post_parent > 0
        ");

        // C. Clean child variants from product index
        $wpdb->query("
            DELETE idx FROM {$productIndexTable} idx
            JOIN {$wpdb->posts} p ON p.ID = idx.post_id
            WHERE p.post_type = 'helmet' AND p.post_parent > 0
        ");

        clean_post_cache($orphanIds[0] ?? 0);

        return [
            'child_indexables_sanitized' => $childIndexablesCount,
            'orphan_posts_purged'       => $orphanCount,
            'product_index_cleaned'     => $childInIndexCount,
            'dry_run'                   => false,
        ];
    }

    /**
     * Purges orphaned indexables from wp_yoast_indexable whose underlying post no longer exists.
     *
     * @param bool $dryRun
     * @return array{orphans_found: int, orphans_deleted: int, hierarchy_deleted: int, links_deleted: int, dry_run: bool}
     */
    public static function purgeYoastOrphanIndexables(bool $dryRun = true): array
    {
        global $wpdb;
        $indexableTable = $wpdb->prefix . 'yoast_indexable';
        $hierarchyTable = $wpdb->prefix . 'yoast_indexable_hierarchy';
        $linksTable = $wpdb->prefix . 'yoast_seo_links';

        $orphanCount = (int) $wpdb->get_var("
            SELECT COUNT(y.id)
            FROM {$indexableTable} y
            LEFT JOIN {$wpdb->posts} p ON p.ID = y.object_id
            WHERE y.object_type = 'post' AND p.ID IS NULL
        ");

        $orphanHierarchyCount = (int) $wpdb->get_var("
            SELECT COUNT(h.indexable_id)
            FROM {$hierarchyTable} h
            LEFT JOIN {$indexableTable} y ON y.id = h.indexable_id
            WHERE y.id IS NULL
        ");

        $orphanLinksCount = (int) $wpdb->get_var("
            SELECT COUNT(l.id)
            FROM {$linksTable} l
            LEFT JOIN {$wpdb->posts} p ON p.ID = l.post_id
            WHERE p.ID IS NULL
        ");

        if ($dryRun) {
            return [
                'orphans_found'     => $orphanCount,
                'orphans_deleted'   => 0,
                'hierarchy_deleted' => $orphanHierarchyCount,
                'links_deleted'     => $orphanLinksCount,
                'dry_run'           => true,
            ];
        }

        // Clean hierarchy for orphan indexables
        $wpdb->query("
            DELETE h FROM {$hierarchyTable} h
            JOIN {$indexableTable} y ON y.id = h.indexable_id
            LEFT JOIN {$wpdb->posts} p ON p.ID = y.object_id
            WHERE y.object_type = 'post' AND p.ID IS NULL
        ");

        // Clean any already dangling hierarchy
        $wpdb->query("
            DELETE h FROM {$hierarchyTable} h
            LEFT JOIN {$indexableTable} y ON y.id = h.indexable_id
            WHERE y.id IS NULL
        ");

        // Clean orphan links
        $wpdb->query("
            DELETE l FROM {$linksTable} l
            LEFT JOIN {$wpdb->posts} p ON p.ID = l.post_id
            WHERE p.ID IS NULL
        ");

        // Clean orphan indexables
        $deleted = (int) $wpdb->query("
            DELETE y FROM {$indexableTable} y
            LEFT JOIN {$wpdb->posts} p ON p.ID = y.object_id
            WHERE y.object_type = 'post' AND p.ID IS NULL
        ");

        return [
            'orphans_found'     => $orphanCount,
            'orphans_deleted'   => $deleted,
            'hierarchy_deleted' => $orphanHierarchyCount,
            'links_deleted'     => $orphanLinksCount,
            'dry_run'           => false,
        ];
    }

    /**
     * Purges orphaned postmeta and post-term relationships pointing to non-existent posts.
     *
     * @param bool $dryRun
     * @return array{postmeta_purged: int, term_relationships_purged: int, dry_run: bool}
     */
    public static function purgeOrphanPostmetaAndTerms(bool $dryRun = true): array
    {
        global $wpdb;

        $orphanMetaCount = (int) $wpdb->get_var("
            SELECT COUNT(pm.meta_id)
            FROM {$wpdb->postmeta} pm
            LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE p.ID IS NULL
        ");

        $orphanTermsCount = (int) $wpdb->get_var("
            SELECT COUNT(tr.object_id)
            FROM {$wpdb->term_relationships} tr
            JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
            LEFT JOIN {$wpdb->posts} p ON p.ID = tr.object_id
            WHERE tt.taxonomy NOT IN ('term_language', 'term_translations') AND p.ID IS NULL
        ");

        if ($dryRun) {
            return [
                'postmeta_purged'           => $orphanMetaCount,
                'term_relationships_purged' => $orphanTermsCount,
                'dry_run'                   => true,
            ];
        }

        $metaDeleted = (int) $wpdb->query("
            DELETE pm FROM {$wpdb->postmeta} pm
            LEFT JOIN {$wpdb->posts} p ON p.ID = pm.post_id
            WHERE p.ID IS NULL
        ");

        $termsDeleted = (int) $wpdb->query("
            DELETE tr FROM {$wpdb->term_relationships} tr
            JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id
            LEFT JOIN {$wpdb->posts} p ON p.ID = tr.object_id
            WHERE tt.taxonomy NOT IN ('term_language', 'term_translations') AND p.ID IS NULL
        ");

        return [
            'postmeta_purged'           => $metaDeleted,
            'term_relationships_purged' => $termsDeleted,
            'dry_run'                   => false,
        ];
    }

    /**
     * Repairs Polylang post_translations taxonomy:
     * 1. Purges empty translation groups where all referenced posts were deleted.
     * 2. Sanitizes partially dead translation groups to remove ghost post references.
     *
     * @param bool $dryRun
     * @return array{empty_groups_purged: int, groups_repaired: int, dead_refs_removed: int, dry_run: bool}
     */
    public static function repairPolylangTranslations(bool $dryRun = true): array
    {
        global $wpdb;

        $terms = $wpdb->get_results("
            SELECT tt.term_taxonomy_id, tt.term_id, tt.description
            FROM {$wpdb->term_taxonomy} tt
            WHERE tt.taxonomy = 'post_translations'
        ");

        $emptyTaxonomyIds = [];
        $emptyTermIds = [];
        $groupsToRepair = [];
        $totalDeadRefs = 0;

        foreach ($terms as $t) {
            $arr = @unserialize($t->description);
            if (! is_array($arr)) {
                $emptyTaxonomyIds[] = (int) $t->term_taxonomy_id;
                $emptyTermIds[] = (int) $t->term_id;
                continue;
            }

            $cleanArr = [];
            $deadInThisGroup = 0;
            foreach ($arr as $lang => $pid) {
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE ID = %d AND post_status = 'publish'",
                    (int) $pid
                ));
                if ($exists) {
                    $cleanArr[$lang] = (int) $pid;
                } else {
                    $deadInThisGroup++;
                    $totalDeadRefs++;
                }
            }

            if (empty($cleanArr)) {
                $emptyTaxonomyIds[] = (int) $t->term_taxonomy_id;
                $emptyTermIds[] = (int) $t->term_id;
            } elseif ($deadInThisGroup > 0) {
                $groupsToRepair[] = [
                    'term_taxonomy_id' => (int) $t->term_taxonomy_id,
                    'clean_description' => serialize($cleanArr),
                ];
            }
        }

        if ($dryRun) {
            return [
                'empty_groups_purged' => count($emptyTaxonomyIds),
                'groups_repaired'     => count($groupsToRepair),
                'dead_refs_removed'   => $totalDeadRefs,
                'dry_run'             => true,
            ];
        }

        // Purge completely dead translation groups
        if (! empty($emptyTaxonomyIds)) {
            $ttList = implode(',', $emptyTaxonomyIds);
            $tList = implode(',', $emptyTermIds);
            $wpdb->query("DELETE FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ({$ttList})");
            $wpdb->query("DELETE FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id IN ({$ttList})");
            $wpdb->query("DELETE FROM {$wpdb->terms} WHERE term_id IN ({$tList})");
        }

        // Update partially dead groups with clean descriptions
        foreach ($groupsToRepair as $rep) {
            $wpdb->update(
                $wpdb->term_taxonomy,
                ['description' => $rep['clean_description']],
                ['term_taxonomy_id' => $rep['term_taxonomy_id']],
                ['%s'],
                ['%d']
            );
        }

        wp_cache_flush();

        return [
            'empty_groups_purged' => count($emptyTaxonomyIds),
            'groups_repaired'     => count($groupsToRepair),
            'dead_refs_removed'   => $totalDeadRefs,
            'dry_run'             => false,
        ];
    }

    /**
     * Aligns translated helmet post slugs with the canonical English post slug.
     * Completely eliminates cross-language slug collisions and ensures 100% addressability.
     *
     * @param bool $dryRun
     * @return array{inspected: int, slugs_aligned: int, dry_run: bool}
     */
    public static function alignTranslatedHelmetSlugs(bool $dryRun = true): array
    {
        global $wpdb;

        // Fetch translation groups for published helmets
        $terms = $wpdb->get_results("
            SELECT tt.description
            FROM {$wpdb->term_taxonomy} tt
            WHERE tt.taxonomy = 'post_translations'
        ");

        $alignedCount = 0;
        $inspectedCount = 0;
        $indexableTable = $wpdb->prefix . 'yoast_indexable';
        $homeUrl = rtrim(home_url(), '/');

        if (! $dryRun) {
            $wpdb->query("START TRANSACTION");
        }

        foreach ($terms as $t) {
            $arr = @unserialize($t->description);
            if (! is_array($arr) || empty($arr['en'])) {
                continue;
            }

            $enId = (int) $arr['en'];
            $enPost = $wpdb->get_row($wpdb->prepare(
                "SELECT ID, post_name, post_type FROM {$wpdb->posts} WHERE ID = %d AND post_status = 'publish'",
                $enId
            ));

            if (! $enPost || $enPost->post_type !== 'helmet' || empty($enPost->post_name)) {
                continue;
            }

            $enSlug = (string) $enPost->post_name;

            foreach ($arr as $lang => $transId) {
                if ($lang === 'en') {
                    continue;
                }

                $transId = (int) $transId;
                $transPost = $wpdb->get_row($wpdb->prepare(
                    "SELECT ID, post_name FROM {$wpdb->posts} WHERE ID = %d AND post_status = 'publish'",
                    $transId
                ));

                if (! $transPost) {
                    continue;
                }

                $inspectedCount++;

                if ($transPost->post_name !== $enSlug) {
                    $alignedCount++;
                    if (! $dryRun) {
                        $wpdb->update(
                            $wpdb->posts,
                            ['post_name' => $enSlug],
                            ['ID' => $transId],
                            ['%s'],
                            ['%d']
                        );

                        // Also update Yoast indexable if present
                        $permalink = $homeUrl . '/' . $lang . '/helmets/' . $enSlug . '/';
                        $wpdb->query($wpdb->prepare(
                            "UPDATE {$indexableTable} SET permalink = %s, canonical = %s WHERE object_id = %d AND object_type = 'post'",
                            $permalink,
                            $permalink,
                            $transId
                        ));

                        if ($alignedCount % 2500 === 0) {
                            $wpdb->query("COMMIT");
                            $wpdb->query("START TRANSACTION");
                        }
                    }
                }
            }
        }

        if (! $dryRun) {
            $wpdb->query("COMMIT");
            wp_cache_flush();
        }

        return [
            'inspected'     => $inspectedCount,
            'slugs_aligned' => $alignedCount,
            'dry_run'       => $dryRun,
        ];
    }

    /**
     * Purges duplicate translated posts that are attached to a post_translations group
     * but have been superseded in the serialized description.
     *
     * @param bool $dryRun
     * @return array{ghost_duplicates_found: int, ghost_duplicates_purged: int, dry_run: bool}
     */
    public static function purgeGhostDuplicateTranslatedPosts(bool $dryRun = true): array
    {
        global $wpdb;
        $indexableTable = $wpdb->prefix . 'yoast_indexable';
        $productIndexTable = $wpdb->prefix . 'helmetsan_product_index';

        $terms = $wpdb->get_results("
            SELECT tt.term_taxonomy_id, tt.description
            FROM {$wpdb->term_taxonomy} tt
            WHERE tt.taxonomy = 'post_translations'
        ");

        $ghostPostIds = [];
        foreach ($terms as $t) {
            $arr = @unserialize($t->description);
            if (! is_array($arr)) {
                continue;
            }
            $activeIds = array_map('intval', array_values($arr));
            $attachedIds = $wpdb->get_col($wpdb->prepare(
                "SELECT object_id FROM {$wpdb->term_relationships} WHERE term_taxonomy_id = %d",
                (int) $t->term_taxonomy_id
            ));

            foreach ($attachedIds as $objId) {
                $objId = (int) $objId;
                if (! in_array($objId, $activeIds, true)) {
                    $isPost = $wpdb->get_var($wpdb->prepare(
                        "SELECT ID FROM {$wpdb->posts} WHERE ID = %d AND post_status = 'publish'",
                        $objId
                    ));
                    if ($isPost) {
                        $ghostPostIds[] = $objId;
                    }
                }
            }
        }

        $ghostPostIds = array_values(array_unique($ghostPostIds));
        $count = count($ghostPostIds);

        if ($dryRun || empty($ghostPostIds)) {
            return [
                'ghost_duplicates_found'  => $count,
                'ghost_duplicates_purged' => 0,
                'dry_run'                 => true,
            ];
        }

        $idList = implode(',', $ghostPostIds);
        $wpdb->query("DELETE FROM {$productIndexTable} WHERE post_id IN ({$idList})");
        $wpdb->query("DELETE FROM {$indexableTable} WHERE object_id IN ({$idList})");
        $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE post_id IN ({$idList})");
        $wpdb->query("DELETE FROM {$wpdb->term_relationships} WHERE object_id IN ({$idList})");
        $wpdb->query("DELETE FROM {$wpdb->posts} WHERE ID IN ({$idList})");

        wp_cache_flush();

        return [
            'ghost_duplicates_found'  => $count,
            'ghost_duplicates_purged' => $count,
            'dry_run'                 => false,
        ];
    }

    /**
     * Purges translation groups that have no published English parent post.
     *
     * @param bool $dryRun
     * @return array{groups_found: int, posts_purged: int, dry_run: bool}
     */
    public static function purgeOrphanTranslationGroupsWithoutEnglish(bool $dryRun = true): array
    {
        global $wpdb;
        $indexableTable = $wpdb->prefix . 'yoast_indexable';
        $productIndexTable = $wpdb->prefix . 'helmetsan_product_index';

        $terms = $wpdb->get_results("
            SELECT tt.term_taxonomy_id, tt.term_id, tt.description
            FROM {$wpdb->term_taxonomy} tt
            WHERE tt.taxonomy = 'post_translations'
        ");

        $orphanPostIds = [];
        $orphanTermTaxonomyIds = [];
        $orphanTermIds = [];

        foreach ($terms as $t) {
            $arr = @unserialize($t->description);
            if (! is_array($arr)) {
                continue;
            }

            $enId = (int) ($arr['en'] ?? 0);
            $hasValidEn = false;
            if ($enId > 0) {
                $hasValidEn = (bool) $wpdb->get_var($wpdb->prepare(
                    "SELECT ID FROM {$wpdb->posts} WHERE ID = %d AND post_status = 'publish'",
                    $enId
                ));
            }

            if (! $hasValidEn) {
                $orphanTermTaxonomyIds[] = (int) $t->term_taxonomy_id;
                $orphanTermIds[] = (int) $t->term_id;
                foreach ($arr as $lang => $pid) {
                    $pid = (int) $pid;
                    if ($pid > 0) {
                        $orphanPostIds[] = $pid;
                    }
                }
            }
        }

        $orphanPostIds = array_values(array_unique($orphanPostIds));
        $postCount = count($orphanPostIds);
        $groupCount = count($orphanTermTaxonomyIds);

        if ($dryRun || empty($orphanPostIds)) {
            return [
                'groups_found' => $groupCount,
                'posts_purged' => $postCount,
                'dry_run'      => true,
            ];
        }

        $idList = implode(',', $orphanPostIds);
        $wpdb->query("DELETE FROM {$productIndexTable} WHERE post_id IN ({$idList})");
        $wpdb->query("DELETE FROM {$indexableTable} WHERE object_id IN ({$idList})");
        $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE post_id IN ({$idList})");
        $wpdb->query("DELETE FROM {$wpdb->term_relationships} WHERE object_id IN ({$idList})");
        $wpdb->query("DELETE FROM {$wpdb->posts} WHERE ID IN ({$idList})");

        if (! empty($orphanTermTaxonomyIds)) {
            $ttList = implode(',', $orphanTermTaxonomyIds);
            $tList = implode(',', $orphanTermIds);
            $wpdb->query("DELETE FROM {$wpdb->term_relationships} WHERE term_taxonomy_id IN ({$ttList})");
            $wpdb->query("DELETE FROM {$wpdb->term_taxonomy} WHERE term_taxonomy_id IN ({$ttList})");
            $wpdb->query("DELETE FROM {$wpdb->terms} WHERE term_id IN ({$tList})");
        }

        wp_cache_flush();

        return [
            'groups_found' => $groupCount,
            'posts_purged' => $postCount,
            'dry_run'      => false,
        ];
    }

    /**
     * Aligns translated child helmet posts with their translated parent helmet.
     * In English, child variants have post_parent = <English Parent ID>.
     * In translated languages, child variants currently have post_parent = 0.
     * This method resolves the translated parent ID for each child post in its respective language,
     * updates wp_posts SET post_parent = <Translated Parent ID>,
     * and sets is_robots_noindex = 1 in wp_yoast_indexable so child colorways don't bloat sitemaps.
     *
     * @return array{child_groups: int, children_aligned: int, missing_parents: int, dry_run: bool}
     */
    public static function alignChildHelmetParents(bool $dryRun = false): array
    {
        global $wpdb;

        $terms = $wpdb->get_results("
            SELECT term_taxonomy_id, description 
            FROM {$wpdb->term_taxonomy} 
            WHERE taxonomy = 'post_translations'
        ");

        $groupMap = [];
        foreach ($terms as $t) {
            $arr = @unserialize($t->description);
            if (! is_array($arr)) {
                continue;
            }
            $en = $arr['en'] ?? null;
            if ($en) {
                $groupMap[(int) $en] = $arr;
            }
        }

        $childGroupsCount = 0;
        $updates = []; // array of [child_id => parent_id]
        $missingParents = 0;

        foreach ($groupMap as $enId => $childArr) {
            $enParent = (int) $wpdb->get_var($wpdb->prepare(
                "SELECT post_parent FROM {$wpdb->posts} WHERE ID = %d AND post_type = 'helmet'",
                $enId
            ));

            if ($enParent > 0) {
                $childGroupsCount++;
                $parentGroup = $groupMap[$enParent] ?? null;

                if ($parentGroup) {
                    foreach ($childArr as $lang => $cid) {
                        if ($lang === 'en') {
                            continue;
                        }
                        $cid = (int) $cid;
                        $translatedParentId = (int) ($parentGroup[$lang] ?? 0);
                        if ($translatedParentId > 0 && $cid > 0) {
                            $updates[$cid] = $translatedParentId;
                        } else {
                            $missingParents++;
                        }
                    }
                } else {
                    $missingParents++;
                }
            }
        }

        $totalToUpdate = count($updates);

        if ($dryRun || empty($updates)) {
            return [
                'child_groups'     => $childGroupsCount,
                'children_aligned' => $totalToUpdate,
                'missing_parents'  => $missingParents,
                'dry_run'          => true,
            ];
        }

        // Apply updates in chunked transactions
        $productIndexTable = $wpdb->prefix . 'helmetsan_product_index';
        $indexableTable    = $wpdb->prefix . 'yoast_indexable';
        $chunkSize = 2500;
        $chunks = array_chunk($updates, $chunkSize, true);

        foreach ($chunks as $chunk) {
            $cases = [];
            $childIds = [];
            foreach ($chunk as $childId => $parentId) {
                $childIds[] = (int) $childId;
                $cases[] = sprintf("WHEN %d THEN %d", (int) $childId, (int) $parentId);
            }

            $idList = implode(',', $childIds);
            $caseSql = implode(' ', $cases);

            // 1. Update post_parent in wp_posts
            $wpdb->query("
                UPDATE {$wpdb->posts}
                SET post_parent = CASE ID {$caseSql} END
                WHERE ID IN ({$idList})
            ");

            // 2. Remove child colorways from product index so only parents remain
            $wpdb->query("DELETE FROM {$productIndexTable} WHERE post_id IN ({$idList})");

            // 3. Mark child colorways as noindex in Yoast to prune sitemaps
            $wpdb->query("
                UPDATE {$indexableTable}
                SET is_robots_noindex = 1
                WHERE object_id IN ({$idList}) AND object_type = 'post'
            ");
        }

        wp_cache_flush();

        return [
            'child_groups'     => $childGroupsCount,
            'children_aligned' => $totalToUpdate,
            'missing_parents'  => $missingParents,
            'dry_run'          => false,
        ];
    }

    /**
     * Deduplicates English parent helmets that share identical _helmet_unique_id.
     * Identifies the canonical post (highest child count, most translation completeness, newest date),
     * re-parents children of superseded duplicates to the canonical post,
     * purges superseded duplicate translation clusters across all tables,
     * and deletes redundant records.
     *
     * @return array{groups_found: int, redundant_posts_purged: int, children_reparented: int, dry_run: bool}
     */
    public static function deduplicateParentHelmets(bool $dryRun = false): array
    {
        global $wpdb;

        // Find duplicate unique_id groups in English
        $dupGroups = $wpdb->get_results("
            SELECT pm.meta_value as unique_id, COUNT(DISTINCT p.ID) as dup_count
            FROM {$wpdb->posts} p
            JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_helmet_unique_id'
            JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
            JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'language'
            JOIN {$wpdb->terms} t ON t.term_id = tt.term_id AND t.slug = 'en'
            WHERE p.post_type = 'helmet' AND p.post_parent = 0 AND p.post_status = 'publish'
            GROUP BY pm.meta_value
            HAVING dup_count > 1
        ");

        $groupsFound = count($dupGroups);
        $redundantPostIds = [];
        $reparentMap = []; // child_id => new_canonical_parent_id

        foreach ($dupGroups as $group) {
            $uniqueId = $group->unique_id;

            $candidates = $wpdb->get_results($wpdb->prepare("
                SELECT p.ID, p.post_title, p.post_date, p.post_name,
                       (SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_parent = p.ID) as child_count,
                       (SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE post_id = p.ID) as meta_count
                FROM {$wpdb->posts} p
                JOIN {$wpdb->postmeta} pm ON pm.post_id = p.ID AND pm.meta_key = '_helmet_unique_id' AND pm.meta_value = %s
                JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
                JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'language'
                JOIN {$wpdb->terms} t ON t.term_id = tt.term_id AND t.slug = 'en'
                WHERE p.post_type = 'helmet' AND p.post_parent = 0 AND p.post_status = 'publish'
                ORDER BY p.ID ASC
            ", $uniqueId));

            if (count($candidates) <= 1) {
                continue;
            }

            // Score candidates: child_count * 100 + lang_count * 20 + meta_count + timestamp
            $scored = [];
            foreach ($candidates as $cand) {
                $translations = function_exists('pll_get_post_translations') ? pll_get_post_translations((int) $cand->ID) : [];
                $langCount = count($translations);
                $ts = strtotime($cand->post_date) ?: 0;
                $score = ((int) $cand->child_count * 100) + ($langCount * 20) + (int) $cand->meta_count + ($ts / 1000000000.0);
                $scored[] = [
                    'post'         => $cand,
                    'score'        => $score,
                    'translations' => $translations,
                ];
            }

            usort($scored, fn($a, $b) => $b['score'] <=> $a['score']);
            $canonical = $scored[0];
            $canonicalId = (int) $canonical['post']->ID;

            // Superseded duplicates
            for ($i = 1; $i < count($scored); $i++) {
                $superseded = $scored[$i];
                $supId = (int) $superseded['post']->ID;

                // Any children attached to superseded post should be reparented to canonical
                $children = $wpdb->get_col($wpdb->prepare("SELECT ID FROM {$wpdb->posts} WHERE post_parent = %d", $supId));
                foreach ($children as $cid) {
                    $reparentMap[(int) $cid] = $canonicalId;
                }

                // Add all translated copies of superseded post to purge list
                foreach ($superseded['translations'] as $lang => $transId) {
                    $transId = (int) $transId;
                    if ($transId > 0 && $transId !== $canonicalId) {
                        $redundantPostIds[] = $transId;
                    }
                }
                $redundantPostIds[] = $supId;
            }
        }

        $redundantPostIds = array_values(array_unique($redundantPostIds));
        $reparentCount = count($reparentMap);
        $purgeCount = count($redundantPostIds);

        if ($dryRun || empty($redundantPostIds)) {
            return [
                'groups_found'           => $groupsFound,
                'redundant_posts_purged' => $purgeCount,
                'children_reparented'    => $reparentCount,
                'dry_run'                => true,
            ];
        }

        // 1. Re-parent children
        if (! empty($reparentMap)) {
            foreach ($reparentMap as $childId => $newParentId) {
                $wpdb->update($wpdb->posts, ['post_parent' => $newParentId], ['ID' => $childId]);
            }
        }

        // 2. Purge redundant posts across all tables in chunks
        $productIndexTable = $wpdb->prefix . 'helmetsan_product_index';
        $indexableTable    = $wpdb->prefix . 'yoast_indexable';
        $chunks = array_chunk($redundantPostIds, 1000);

        foreach ($chunks as $chunk) {
            $idList = implode(',', $chunk);
            $wpdb->query("DELETE FROM {$productIndexTable} WHERE post_id IN ({$idList})");
            $wpdb->query("DELETE FROM {$indexableTable} WHERE object_id IN ({$idList})");
            $wpdb->query("DELETE FROM {$wpdb->postmeta} WHERE post_id IN ({$idList})");
            $wpdb->query("DELETE FROM {$wpdb->term_relationships} WHERE object_id IN ({$idList})");
            $wpdb->query("DELETE FROM {$wpdb->posts} WHERE ID IN ({$idList})");
        }

        // 3. Clean up dangling translation groups
        self::repairPolylangTranslations(false);

        wp_cache_flush();

        return [
            'groups_found'           => $groupsFound,
            'redundant_posts_purged' => $purgeCount,
            'children_reparented'    => $reparentCount,
            'dry_run'                => false,
        ];
    }

    /**
     * Re-synchronizes wp_helmetsan_product_index to ensure 100% catalog symmetry across all 10 languages.
     * Deletes any rows representing child variants (post_parent != 0),
     * then backfills any missing true parent helmets.
     *
     * @return array{child_rows_removed: int, parents_indexed: int, language_breakdown: array<string, int>}
     */
    public static function resyncProductIndexSymmetric(): array
    {
        global $wpdb;
        $productIndexTable = $wpdb->prefix . 'helmetsan_product_index';

        // 1. Remove any indexed records where post_parent != 0
        $removed = $wpdb->query("
            DELETE idx FROM {$productIndexTable} idx
            JOIN {$wpdb->posts} p ON p.ID = idx.post_id
            WHERE idx.post_type = 'helmet' AND p.post_parent != 0
        ");

        // 2. Backfill missing parent helmets
        self::backfillProductIndex(100000);

        // 3. Language breakdown census
        $census = $wpdb->get_results("
            SELECT t.slug as lang, COUNT(idx.post_id) as cnt
            FROM {$productIndexTable} idx
            JOIN {$wpdb->term_relationships} tr ON tr.object_id = idx.post_id
            JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'language'
            JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
            WHERE idx.post_type = 'helmet'
            GROUP BY t.slug
            ORDER BY cnt DESC
        ");

        $breakdown = [];
        $totalParents = 0;
        foreach ($census as $row) {
            $breakdown[(string) $row->lang] = (int) $row->cnt;
            $totalParents += (int) $row->cnt;
        }

        return [
            'child_rows_removed' => (int) $removed,
            'parents_indexed'    => $totalParents,
            'language_breakdown' => $breakdown,
        ];
    }

    /**
     * Purges duplicate English brand groups (e.g. scorpion-exo alongside canonical scorpion)
     * and permanently removes trashed brand posts + orphaned postmeta/indexables.
     *
     * @return array{duplicate_groups: int, posts_purged: int, trash_purged: int, rel_reassigned: int, dry_run: bool}
     */
    public static function purgeDuplicateBrandsAndTrash(bool $dryRun = false): array
    {
        global $wpdb;

        $dups = $wpdb->get_results("
            SELECT LOWER(p.post_title) AS norm_title, GROUP_CONCAT(p.ID ORDER BY p.ID ASC) AS ids
            FROM {$wpdb->posts} p
            JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
            JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'language'
            JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
            WHERE p.post_type = 'brand' AND p.post_status = 'publish' AND t.slug = 'en'
            GROUP BY norm_title
            HAVING COUNT(*) > 1
        ");

        $duplicateGroups = count($dups);
        $idsToPurge = [];
        $reassigned = 0;

        foreach ($dups as $row) {
            $idList = array_map('intval', explode(',', (string) $row->ids));
            $bestId = $idList[0];
            $bestCount = -1;
            foreach ($idList as $bid) {
                $cnt = (int) $wpdb->get_var($wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->postmeta} WHERE meta_key = 'rel_brand' AND meta_value = %s",
                    (string) $bid
                ));
                if ($cnt > $bestCount) {
                    $bestCount = $cnt;
                    $bestId = $bid;
                }
            }

            foreach ($idList as $bid) {
                if ($bid === $bestId) {
                    continue;
                }
                if (! $dryRun) {
                    $reassigned += (int) $wpdb->query($wpdb->prepare(
                        "UPDATE {$wpdb->postmeta} SET meta_value = %s WHERE meta_key = 'rel_brand' AND meta_value = %s",
                        (string) $bestId,
                        (string) $bid
                    ));
                }
                $translations = function_exists('pll_get_post_translations') ? (array) pll_get_post_translations($bid) : ['en' => $bid];
                foreach ($translations as $tid) {
                    $idsToPurge[] = (int) $tid;
                }
                $idsToPurge[] = $bid;
            }
        }

        $trashedIds = array_map('intval', $wpdb->get_col(
            "SELECT ID FROM {$wpdb->posts} WHERE post_type = 'brand' AND post_status = 'trash'"
        ));
        $trashCount = count($trashedIds);
        $idsToPurge = array_values(array_unique(array_merge($idsToPurge, $trashedIds)));

        if (! $dryRun && ! empty($idsToPurge)) {
            $yoastTable = $wpdb->prefix . 'yoast_indexable';
            $hasYoast = (bool) $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $yoastTable));
            foreach (array_chunk($idsToPurge, 200) as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '%d'));
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE post_id IN ($placeholders)", ...$chunk));
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->term_relationships} WHERE object_id IN ($placeholders)", ...$chunk));
                if ($hasYoast) {
                    $wpdb->query($wpdb->prepare("DELETE FROM `{$yoastTable}` WHERE object_type = 'post' AND object_id IN ($placeholders)", ...$chunk));
                }
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->posts} WHERE ID IN ($placeholders)", ...$chunk));
            }
            self::repairPolylangTranslations();
        }

        return [
            'duplicate_groups' => $duplicateGroups,
            'posts_purged'     => count($idsToPurge) - $trashCount,
            'trash_purged'     => $trashCount,
            'rel_reassigned'   => $reassigned,
            'dry_run'          => $dryRun,
        ];
    }

    /**
     * Aligns translated brand post slugs (e.g. shoei-2, shoei-3) to their canonical English brand slug (shoei),
     * recording _wp_old_slug for automatic 301 redirects and updating Yoast indexables.
     *
     * @return array{inspected: int, aligned: int, dry_run: bool}
     */
    public static function alignTranslatedBrandSlugs(bool $dryRun = false): array
    {
        global $wpdb;

        $enBrands = $wpdb->get_results("
            SELECT p.ID, p.post_name
            FROM {$wpdb->posts} p
            JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
            JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'language'
            JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
            WHERE p.post_type = 'brand' AND p.post_status = 'publish' AND t.slug = 'en'
        ");

        $yoastTable = $wpdb->prefix . 'yoast_indexable';
        $hasYoast = (bool) $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $yoastTable));

        $inspected = 0;
        $aligned = 0;

        foreach ($enBrands as $en) {
            $canonicalSlug = (string) $en->post_name;
            $translations = function_exists('pll_get_post_translations') ? (array) pll_get_post_translations((int) $en->ID) : [];
            foreach ($translations as $lang => $transId) {
                $transId = (int) $transId;
                if ($lang === 'en' || $transId <= 0 || $transId === (int) $en->ID) {
                    continue;
                }
                $inspected++;
                $currentSlug = (string) $wpdb->get_var($wpdb->prepare(
                    "SELECT post_name FROM {$wpdb->posts} WHERE ID = %d AND post_status = 'publish'",
                    $transId
                ));
                if ($currentSlug === '' || $currentSlug === $canonicalSlug) {
                    continue;
                }

                $aligned++;
                if ($dryRun) {
                    continue;
                }

                add_post_meta($transId, '_wp_old_slug', $currentSlug);
                $wpdb->update($wpdb->posts, ['post_name' => $canonicalSlug], ['ID' => $transId], ['%s'], ['%d']);
                if ($hasYoast) {
                    $newPermalink = get_permalink($transId);
                    if (is_string($newPermalink) && $newPermalink !== '') {
                        $wpdb->update($yoastTable, ['permalink' => $newPermalink], ['object_id' => $transId, 'object_type' => 'post'], ['%s'], ['%d', '%s']);
                    }
                }
            }
        }

        return [
            'inspected' => $inspected,
            'aligned'   => $aligned,
            'dry_run'   => $dryRun,
        ];
    }

    /**
     * Strips redundant numeric suffixes (-2, -3, -5) from canonical parent helmet slugs when the clean base slug is free,
     * synchronizes all 10 language siblings identically, and inserts _wp_old_slug for automatic 301 redirects.
     *
     * @return array{candidates: int, normalized_groups: int, posts_updated: int, dry_run: bool}
     */
    public static function normalizeParentHelmetSlugs(bool $dryRun = false): array
    {
        global $wpdb;

        $enParents = $wpdb->get_results("
            SELECT p.ID, p.post_name
            FROM {$wpdb->posts} p
            JOIN {$wpdb->term_relationships} tr ON tr.object_id = p.ID
            JOIN {$wpdb->term_taxonomy} tt ON tt.term_taxonomy_id = tr.term_taxonomy_id AND tt.taxonomy = 'language'
            JOIN {$wpdb->terms} t ON t.term_id = tt.term_id
            WHERE p.post_type = 'helmet' AND p.post_parent = 0 AND p.post_status = 'publish' AND t.slug = 'en'
        ");

        $usedSlugs = [];
        foreach ($enParents as $row) {
            $usedSlugs[(string) $row->post_name] = (int) $row->ID;
        }

        $yoastTable = $wpdb->prefix . 'yoast_indexable';
        $hasYoast = (bool) $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $yoastTable));

        $candidates = 0;
        $normalizedGroups = 0;
        $postsUpdated = 0;

        foreach ($enParents as $row) {
            $oldSlug = (string) $row->post_name;
            if (! preg_match('/^(.+)-\d+$/', $oldSlug, $m)) {
                continue;
            }
            $candidates++;
            $cleanSlug = $m[1];
            if ($cleanSlug === '' || isset($usedSlugs[$cleanSlug])) {
                continue;
            }

            // Reserve clean slug so subsequent -3/-4 won't collide
            unset($usedSlugs[$oldSlug]);
            $usedSlugs[$cleanSlug] = (int) $row->ID;
            $normalizedGroups++;

            $translations = function_exists('pll_get_post_translations')
                ? (array) pll_get_post_translations((int) $row->ID)
                : ['en' => (int) $row->ID];

            foreach ($translations as $tid) {
                $tid = (int) $tid;
                if ($tid <= 0) {
                    continue;
                }
                $postsUpdated++;
                if ($dryRun) {
                    continue;
                }
                $curr = (string) $wpdb->get_var($wpdb->prepare("SELECT post_name FROM {$wpdb->posts} WHERE ID = %d", $tid));
                if ($curr !== '' && $curr !== $cleanSlug) {
                    add_post_meta($tid, '_wp_old_slug', $curr);
                    $wpdb->update($wpdb->posts, ['post_name' => $cleanSlug], ['ID' => $tid], ['%s'], ['%d']);
                    if ($hasYoast) {
                        $newPermalink = get_permalink($tid);
                        if (is_string($newPermalink) && $newPermalink !== '') {
                            $wpdb->update($yoastTable, ['permalink' => $newPermalink], ['object_id' => $tid, 'object_type' => 'post'], ['%s'], ['%d', '%s']);
                        }
                    }
                }
            }
        }

        return [
            'candidates'        => $candidates,
            'normalized_groups' => $normalizedGroups,
            'posts_updated'     => $postsUpdated,
            'dry_run'           => $dryRun,
        ];
    }

    /**
     * Sanitizes motorcycle catalog titles, Yoast SEO titles, motorcycle_make taxonomies,
     * and deduplicates exact duplicate models caused by multi-brand batch scripts.
     *
     * @return array{titles_cleaned: int, makes_reassigned: int, duplicates_purged: int, dry_run: bool}
     */
    public static function sanitizeMotorcycleCatalog(bool $dryRun = false): array
    {
        global $wpdb;

        $posts = $wpdb->get_results("
            SELECT ID, post_title, post_name, post_date
            FROM {$wpdb->posts}
            WHERE post_type = 'motorcycle' AND post_status = 'publish'
            ORDER BY ID ASC
        ");

        $brandPrefixMap = [
            'husqvarna_' => 'Husqvarna',
            'gasgas_'    => 'GasGas',
            'ktm_'       => 'KTM',
            'piaggio_'   => 'Piaggio',
            'lambretta_' => 'Lambretta',
            'vespa_'     => 'Vespa',
            'peugeot_'   => 'Peugeot',
            'sym_'       => 'SYM',
            'kymco_'     => 'Kymco',
            'cfmoto_'    => 'CFMOTO',
            'benelli_'   => 'Benelli',
            'aprilia_'   => 'Aprilia',
            'moto_guzzi_' => 'Moto Guzzi',
            'ather_'     => 'Ather Energy',
            'bajaj_'     => 'Bajaj Auto',
            'hero_'      => 'Hero MotoCorp',
            'ola_'       => 'Ola Electric',
            'tvs_'       => 'TVS Motor Company',
            'ultraviolette_' => 'Ultraviolette Automotive',
            'zero_'      => 'Zero Motorcycles',
            'energica_'  => 'Energica',
            'super_soco_' => 'Super Soco',
            'river_'     => 'River EV',
            'simple_'    => 'Simple Energy',
            'bimota_'    => 'Bimota',
            'brough_superior_' => 'Brough Superior',
            'laverda_'   => 'Laverda',
        ];

        $titlesCleaned = 0;
        $makesReassigned = 0;
        $titleGroups = [];

        foreach ($posts as $p) {
            $id = (int) $p->ID;
            $rawTitle = html_entity_decode((string) $p->post_title, ENT_QUOTES | ENT_HTML5, 'UTF-8');
            $slug = (string) $p->post_name;

            $cleanTitle = (string) preg_replace(
                '/^(?:&\s+(?:Husqvarna\s+GasGas\s+|Husqvarna\s+|Lambretta\s+Piaggio\s+Kymco\s+SYM\s+(?:Peugeot\s+|Vespa\s+|Lambretta\s+|SYM\s+|Kymco\s+)?|Lambretta\s+Piaggio\s+)?|Electric\s+Bikes\s+&\s+Scooters\s+|Electric\s+&\s+Maxi\s+Scooters\s+|Indian\s+Commuters\s+|Vintage\s+&\s+Exotics\s+|Benelli\s+&\s+CFMOTO\s+(?:&\s+KOVE\s+(?:Benelli\s+)?)?)/i',
                '',
                $rawTitle
            );
            $cleanTitle = trim((string) preg_replace('/\s+/', ' ', $cleanTitle));

            $detectedMake = '';
            // Detect most specific brand marker at the end of batch prefix in slug
            $bestPos = -1;
            foreach ($brandPrefixMap as $needle => $makeName) {
                $pos = strrpos($slug, $needle);
                if ($pos !== false && $pos > $bestPos) {
                    $bestPos = $pos;
                    $detectedMake = $makeName;
                }
            }

            if ($detectedMake !== '') {
                $shortMake = match ($detectedMake) {
                    'Ather Energy' => 'Ather',
                    'Bajaj Auto' => 'Bajaj',
                    'Hero MotoCorp' => 'Hero',
                    'Ola Electric' => 'Ola',
                    'TVS Motor Company' => 'TVS',
                    'Ultraviolette Automotive' => 'Ultraviolette',
                    'Zero Motorcycles' => 'Zero',
                    'River EV' => 'River',
                    'Simple Energy' => 'Simple',
                    default => $detectedMake,
                };
                if (! str_starts_with(strtolower($cleanTitle), strtolower($shortMake))) {
                    $cleanTitle = $shortMake . ' ' . $cleanTitle;
                }
            }

            if ($cleanTitle !== $rawTitle) {
                $titlesCleaned++;
                if (! $dryRun) {
                    $wpdb->update($wpdb->posts, ['post_title' => $cleanTitle], ['ID' => $id], ['%s'], ['%d']);
                    $yoastTitle = (string) get_post_meta($id, '_yoast_wpseo_title', true);
                    if ($yoastTitle !== '') {
                        update_post_meta($id, '_yoast_wpseo_title', $cleanTitle . ': Specs, Price & Recommended Helmets');
                    }
                }
            }

            if ($detectedMake !== '') {
                $currentMake = (string) get_post_meta($id, 'motorcycle_make', true);
                if ($currentMake !== $detectedMake && in_array($currentMake, ['KTM', 'Vespa', 'Global', 'Benelli', 'Aprilia', ''], true)) {
                    $makesReassigned++;
                    if (! $dryRun) {
                        update_post_meta($id, 'motorcycle_make', $detectedMake);
                        update_post_meta($id, 'brand', $detectedMake);
                        wp_set_object_terms($id, [$detectedMake], 'motorcycle_make', false);
                    }
                }
            }

            $normKey = strtolower($cleanTitle);
            $titleGroups[$normKey][] = [
                'id'        => $id,
                'slug'      => $slug,
                'has_batch' => (bool) preg_match('/^(electric_bikes|electric_maxi|indian_commuters|vintage_exotics|ktm_husqvarna|vespa_lambretta|benelli_cfmoto)/', $slug),
                'date'      => strtotime((string) $p->post_date) ?: 0,
            ];
        }

        // Deduplicate exact normalized title groups
        $dupsToPurge = [];
        foreach ($titleGroups as $items) {
            if (count($items) <= 1) {
                continue;
            }
            usort($items, static function (array $a, array $b): int {
                // Prefer non-batch clean slugs first, then newer date
                if ($a['has_batch'] !== $b['has_batch']) {
                    return $a['has_batch'] ? 1 : -1;
                }
                return $b['date'] <=> $a['date'];
            });
            array_shift($items);
            foreach ($items as $loser) {
                $dupsToPurge[] = (int) $loser['id'];
            }
        }

        if (! $dryRun && ! empty($dupsToPurge)) {
            $yoastTable = $wpdb->prefix . 'yoast_indexable';
            $hasYoast = (bool) $wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $yoastTable));
            foreach (array_chunk($dupsToPurge, 200) as $chunk) {
                $placeholders = implode(',', array_fill(0, count($chunk), '%d'));
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->postmeta} WHERE post_id IN ($placeholders)", ...$chunk));
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->term_relationships} WHERE object_id IN ($placeholders)", ...$chunk));
                if ($hasYoast) {
                    $wpdb->query($wpdb->prepare("DELETE FROM `{$yoastTable}` WHERE object_type = 'post' AND object_id IN ($placeholders)", ...$chunk));
                }
                $wpdb->query($wpdb->prepare("DELETE FROM {$wpdb->posts} WHERE ID IN ($placeholders)", ...$chunk));
            }
        }

        return [
            'titles_cleaned'    => $titlesCleaned,
            'makes_reassigned'  => $makesReassigned,
            'duplicates_purged' => count($dupsToPurge),
            'dry_run'           => $dryRun,
        ];
    }
}
