<?php
require_once '/Users/anumac/Documents/Helmetsan/wp-load.php';

echo "Checking Accessories...\n";
$args = [
    'post_type' => 'accessory',
    'post_status' => 'publish',
    'posts_per_page' => -1,
];
$query = new WP_Query($args);
echo "Total Published Accessories: " . $query->found_posts . "\n";

if ($query->have_posts()) {
    echo "Sample Accessory:\n";
    $query->the_post();
    echo "- Title: " . get_the_title() . "\n";
    echo "- ID: " . get_the_ID() . "\n";
    $terms = get_the_terms(get_the_ID(), 'accessory_category');
    if ($terms && !is_wp_error($terms)) {
        echo "- Categories: ";
        foreach ($terms as $t) {
            echo $t->name . " (ID: " . $t->term_id . "), ";
        }
        echo "\n";
    } else {
        echo "- No categories assigned.\n";
    }
}

echo "\nChecking Accessory Categories Terms:\n";
$terms = get_terms([
    'taxonomy' => 'accessory_category',
    'hide_empty' => false,
]);
foreach ($terms as $term) {
    echo "- " . $term->name . " (Slug: " . $term->slug . ") - Count: " . $term->count . "\n";
}
