<?php
// verify_inheritance_remote.php

// Find a child helmet that is NOT a Carbon variant (as Carbon variants override material)
// We look for a variant where color is NOT Carbon.
$args = [
    'post_type' => 'helmet',
    'post_parent__not_in' => [0],
    'posts_per_page' => 10,
    'fields' => 'ids',
    'meta_query' => [
        [
            'key' => 'spec_shell_material',
            'compare' => 'NOT EXISTS' // Or empty
        ]
    ]
];

$children = get_posts($args);
$targetChild = null;

foreach ($children as $cid) {
    if (strpos(get_the_title($cid), 'Carbon') === false) {
        $targetChild = $cid;
        break;
    }
}

if (!$targetChild) {
    // Fallback: just pick the first one and hope
    $targetChild = $children[0] ?? 0;
}

if ($targetChild === 0) {
    echo "NO_CHILD_FOUND";
    exit;
}

$parentId = wp_get_post_parent_id($targetChild);
$parentId = wp_get_post_parent_id($targetChild);
// Use helper function to avoid namespace issues
if (!function_exists('helmetsan_core')) {
    echo "ERROR: helmetsan_core() function not found. Plugin inactive?\n";
    exit(1);
}
$service = helmetsan_core()->helmets();

$directMeta = get_post_meta($targetChild, 'spec_shell_material', true);
$parentMeta = get_post_meta($parentId, 'spec_shell_material', true);
$inheritedMeta = $service->getInheritedMeta($targetChild, 'spec_shell_material');

echo "--------------------------------------------------\n";
echo "Inheritance Verification\n";
echo "--------------------------------------------------\n";
echo "Child ID:  " . $targetChild . "\n";
echo "Parent ID: " . $parentId . "\n";
echo "Direct Meta (Child):  '" . $directMeta . "'\n";
echo "Meta (Parent):        '" . $parentMeta . "'\n";
echo "Inherited (Service):  '" . $inheritedMeta . "'\n";
echo "--------------------------------------------------\n";

if ($directMeta === '' && $parentMeta !== '' && $inheritedMeta === $parentMeta) {
    echo "RESULT: SUCCESS\n";
} else {
    echo "RESULT: FAILURE\n";
    // diverse checks
    if ($directMeta !== '') echo "Failure Reason: Child has direct meta.\n";
    if ($parentMeta === '') echo "Failure Reason: Parent has empty meta.\n";
    if ($inheritedMeta !== $parentMeta) echo "Failure Reason: Inherited value mismatch.\n";
}
