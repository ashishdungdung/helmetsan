<?php
/**
 * scripts/sanitize_english_slugs.php
 *
 * Normalizes and sanitizes WordPress permalink slugs for English catalog posts
 * (helmets, parent models, accessories, brands) that retained percent-encoded
 * or Chinese characters from earlier seed batches.
 *
 * Resolves stale duplicate collisions by demoting stale older copies so that the
 * active, enriched posts gain the clean canonical URL permalinks.
 *
 * Usage:
 *   wp eval-file scripts/sanitize_english_slugs.php --allow-root [--dry-run] [--post-type=helmet]
 */

if (! defined('ABSPATH')) {
    echo "This script must be run within WordPress via WP-CLI:\n";
    echo "  wp eval-file scripts/sanitize_english_slugs.php --allow-root\n";
    exit(1);
}

$isDryRun = in_array('dry-run', $argv ?? [], true) || in_array('--dry-run', $argv ?? [], true);
$targetPostType = 'helmet';
foreach ($argv ?? [] as $arg) {
    if (str_starts_with($arg, '--post-type=') || str_starts_with($arg, 'post-type=')) {
        $parts = explode('=', $arg);
        $targetPostType = sanitize_key($parts[1] ?? 'helmet');
    }
}

echo "======================================================\n";
echo "Helmetsan Catalog Slug Normalizer & Sanitizer\n";
echo "Target Post Type : " . $targetPostType . "\n";
echo "Execution Mode   : " . ($isDryRun ? "DRY-RUN (Preview Only)" : "APPLY (Database Updates Active)") . "\n";
echo "======================================================\n\n";

global $wpdb;

$sql = "
    SELECT p.ID, p.post_title, p.post_name, p.post_date, p.post_modified, p.post_parent
    FROM {$wpdb->posts} p
    WHERE p.post_type = %s 
      AND p.post_status = 'publish'
      AND p.post_name REGEXP '%[0-9a-fA-F]{2}'
      AND p.ID NOT IN (
          SELECT tr.object_id
          FROM {$wpdb->term_relationships} tr
          JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id AND tt.taxonomy = 'language'
          JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
          WHERE t.slug IN ('zh', 'de')
      )
    ORDER BY p.ID ASC
";

$posts = $wpdb->get_results($wpdb->prepare($sql, $targetPostType));
$total = count($posts);

echo "Found {$total} published English {$targetPostType} posts with encoded slugs.\n\n";

if ($total === 0) {
    echo "✅ No corrupted or percent-encoded slugs found. Exiting.\n";
    exit(0);
}

$updatedCount = 0;
$collisionResolvedCount = 0;
$skippedCount = 0;

foreach ($posts as $idx => $p) {
    $postId = (int) $p->ID;
    $currentSlug = (string) $p->post_name;
    $uniqueId = (string) get_post_meta($postId, '_helmet_unique_id', true);
    if ($uniqueId === '' && $targetPostType === 'accessory') {
        $uniqueId = (string) get_post_meta($postId, '_accessory_unique_id', true);
    }

    // Determine target clean slug
    if ($uniqueId !== '') {
        $targetSlug = sanitize_title(str_replace('_', '-', $uniqueId));
    } else {
        $brand = (string) get_post_meta($postId, 'brand_name', true);
        if ($brand === '') {
            $brand = (string) get_post_meta($postId, 'brand', true);
        }
        $fullTitle = $brand !== '' && stripos($p->post_title, $brand) !== 0
            ? $brand . ' ' . $p->post_title
            : $p->post_title;
        $targetSlug = sanitize_title($fullTitle);
    }

    if ($targetSlug === '' || $targetSlug === $currentSlug) {
        $skippedCount++;
        continue;
    }

    // Check if targetSlug is already claimed by another post
    $existing = $wpdb->get_row($wpdb->prepare(
        "SELECT ID, post_title, post_name, post_date, post_modified FROM {$wpdb->posts} WHERE post_name = %s AND ID != %d AND post_type = %s AND post_status = 'publish' LIMIT 1",
        $targetSlug,
        $postId,
        $targetPostType
    ));

    if ($existing) {
        $otherId = (int) $existing->ID;
        $otherUniqueId = (string) get_post_meta($otherId, '_helmet_unique_id', true);
        
        // If the colliding post is an older duplicate of the same unique_id or created earlier
        $isSameEntity = ($otherUniqueId !== '' && $otherUniqueId === $uniqueId) || (strtotime($p->post_modified) >= strtotime($existing->post_modified));

        if ($isSameEntity) {
            // Demote/rename older stale collision so the active enriched post takes the canonical slug
            $staleSlug = $targetSlug . '-stale-' . $otherId;
            echo "  [COLLISION] Other Post ID {$otherId} ({$existing->post_title}) claims '{$targetSlug}'.\n";
            echo "              -> Demoting Post ID {$otherId} to '{$staleSlug}'\n";

            if (! $isDryRun) {
                $wpdb->update(
                    $wpdb->posts,
                    ['post_name' => $staleSlug],
                    ['ID' => $otherId]
                );
                clean_post_cache($otherId);
            }
            $collisionResolvedCount++;
        } else {
            // Target slug belongs to a genuinely different post; use unique suffix
            $targetSlug = wp_unique_post_slug($targetSlug, $postId, 'publish', $targetPostType, (int) $p->post_parent);
        }
    }

    echo sprintf("[%d/%d] ID %d: '%s' -> '%s'\n", $idx + 1, $total, $postId, urldecode($currentSlug), $targetSlug);

    if (! $isDryRun) {
        $wpdb->update(
            $wpdb->posts,
            [
                'post_name' => $targetSlug,
                'guid'      => home_url("/{$targetPostType}s/{$targetSlug}/"),
            ],
            ['ID' => $postId]
        );
        clean_post_cache($postId);
    }
    $updatedCount++;
}

echo "\n======================================================\n";
echo "Summary:\n";
echo "  Total Processed        : {$total}\n";
echo "  Updated Slugs          : {$updatedCount}\n";
echo "  Collisions Resolved    : {$collisionResolvedCount}\n";
echo "  Skipped                : {$skippedCount}\n";
echo "======================================================\n";

if (! $isDryRun) {
    echo "Flushing rewrite rules and caches...\n";
    flush_rewrite_rules();
    wp_cache_flush();
    echo "✅ Complete!\n";
} else {
    echo "🔍 Dry run finished. Run without --dry-run to apply updates.\n";
}
