<?php
/**
 * Polylang Bidirectionality & Link Integrity Auditor
 */

if (!defined('ABSPATH')) {
    die("Direct access forbidden.\n");
}

global $wpdb;

echo "=================================================================\n";
echo "   HELMETSAN POLYLANG BIDIRECTIONALITY & LINK INTEGRITY AUDIT    \n";
echo "=================================================================\n\n";

$targetLangs = ['en', 'de', 'zh', 'fr', 'es', 'it', 'pl', 'pt', 'nl', 'ja'];

// 1. Audit post_translations
$terms = $wpdb->get_results("
    SELECT tt.term_taxonomy_id, tt.term_id, tt.description
    FROM {$wpdb->term_taxonomy} tt
    WHERE tt.taxonomy = 'post_translations'
");

echo "1. Checking Post Translation Clusters...\n";
echo "   Total tracked translation clusters: " . count($terms) . "\n";

$checkedPosts = 0;
$danglingPosts = 0;
$langMismatches = 0;
$asymmetricLinks = 0;
$sampleIssues = [];

// Track clusters by post type
$postTypeStats = [];

foreach ($terms as $t) {
    $cluster = maybe_unserialize($t->description);
    if (!is_array($cluster) || empty($cluster)) {
        continue;
    }

    $clusterPostType = null;

    foreach ($cluster as $lang => $postId) {
        $postId = (int)$postId;
        if (!$postId) continue;
        $checkedPosts++;

        $post = get_post($postId);
        if (!$post || $post->post_status === 'trash') {
            $danglingPosts++;
            if (count($sampleIssues) < 15) {
                $sampleIssues[] = "Dangling ID in cluster #{$t->term_id}: {$lang} => #{$postId} (not found or trashed)";
            }
            continue;
        }

        if (!$clusterPostType) {
            $clusterPostType = $post->post_type;
            if (!isset($postTypeStats[$clusterPostType])) {
                $postTypeStats[$clusterPostType] = ['clusters' => 0, 'posts' => 0];
            }
            $postTypeStats[$clusterPostType]['clusters']++;
        }
        $postTypeStats[$clusterPostType]['posts']++;

        // 1. Verify actual post language matches cluster key
        $actualLang = function_exists('pll_get_post_language') ? pll_get_post_language($postId) : '';
        if ($actualLang !== $lang) {
            $langMismatches++;
            if (count($sampleIssues) < 15) {
                $sampleIssues[] = "Language mismatch on post #{$postId}: expected '{$lang}', got '{$actualLang}'";
            }
        }

        // 2. Check reciprocal linkage from this post
        $postTranslations = function_exists('pll_get_post_translations') ? pll_get_post_translations($postId) : [];
        foreach ($cluster as $otherLang => $otherId) {
            $otherId = (int)$otherId;
            if ($otherId <= 0) continue;

            $linkedId = isset($postTranslations[$otherLang]) ? (int)$postTranslations[$otherLang] : 0;
            if ($linkedId !== $otherId) {
                $asymmetricLinks++;
                if (count($sampleIssues) < 15) {
                    $sampleIssues[] = "Asymmetry: Post #{$postId} ({$lang}) points to {$otherLang} => #{$linkedId}, expected #{$otherId}";
                }
            }

            // Also test pll_get_post helper directly
            $helperResolved = function_exists('pll_get_post') ? (int)pll_get_post($postId, $otherLang) : 0;
            if ($helperResolved !== $otherId) {
                $asymmetricLinks++;
                if (count($sampleIssues) < 15) {
                    $sampleIssues[] = "pll_get_post({$postId}, '{$otherLang}') returned #{$helperResolved}, expected #{$otherId}";
                }
            }
        }
    }
}

echo "\n   Post Type Breakdown:\n";
foreach ($postTypeStats as $pt => $s) {
    echo sprintf("     - %-15s : %5d clusters, %6d posts\n", $pt, $s['clusters'], $s['posts']);
}

echo "\n   Post Verification Results:\n";
echo "     - Total post nodes tested:    {$checkedPosts}\n";
echo "     - Dangling / dead IDs:        {$danglingPosts}\n";
echo "     - Language mismatches:        {$langMismatches}\n";
echo "     - Asymmetric / broken links:  {$asymmetricLinks}\n";

// 2. Audit Taxonomy Term translations
echo "\n2. Checking Taxonomy Term Translation Clusters...\n";
$termClusters = $wpdb->get_results("
    SELECT tt.term_taxonomy_id, tt.term_id, tt.description
    FROM {$wpdb->term_taxonomy} tt
    WHERE tt.taxonomy = 'term_translations'
");
echo "   Total tracked term translation clusters: " . count($termClusters) . "\n";

$checkedTerms = 0;
$danglingTerms = 0;
$asymmetricTerms = 0;

foreach ($termClusters as $tc) {
    $tCluster = maybe_unserialize($tc->description);
    if (!is_array($tCluster) || empty($tCluster)) continue;

    foreach ($tCluster as $lang => $termId) {
        $termId = (int)$termId;
        if (!$termId) continue;
        $checkedTerms++;

        $term = get_term($termId);
        if (!$term || is_wp_error($term)) {
            $danglingTerms++;
            continue;
        }

        $termTranslations = function_exists('pll_get_term_translations') ? pll_get_term_translations($termId) : [];
        foreach ($tCluster as $otherLang => $otherTermId) {
            $otherTermId = (int)$otherTermId;
            if (!$otherTermId) continue;

            $linkedTermId = isset($termTranslations[$otherLang]) ? (int)$termTranslations[$otherLang] : 0;
            if ($linkedTermId !== $otherTermId) {
                $asymmetricTerms++;
                if (count($sampleIssues) < 15) {
                    $sampleIssues[] = "Term Asymmetry: Term #{$termId} ({$lang}) missing reciprocal translation to {$otherLang} => #{$otherTermId}";
                }
            }
        }
    }
}

echo "   Term Verification Results:\n";
echo "     - Total term nodes tested:    {$checkedTerms}\n";
echo "     - Dangling term IDs:          {$danglingTerms}\n";
echo "     - Asymmetric term links:      {$asymmetricTerms}\n";

// 3. Output Issues or Success
echo "\n-----------------------------------------------------------------\n";
if (!empty($sampleIssues)) {
    echo "⚠️ ISSUES FOUND (" . count($sampleIssues) . " sample):\n";
    foreach ($sampleIssues as $issue) {
        echo "  - " . $issue . "\n";
    }
} else {
    echo "✅ PERFECT BIDIRECTIONALITY CONFIRMED ACROSS ALL LANGUAGES!\n";
    echo "   All reciprocal links (A -> B and B -> A) are 100% synchronized.\n";
}
echo "=================================================================\n";
