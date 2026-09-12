<?php
require_once 'wp-load.php';

$option = get_option('helmetsan_revenue');
if (is_array($option)) {
    if (isset($option['affiliate_networks']['amazon'])) {
        $option['affiliate_networks']['amazon']['tag'] = 'vtete-20';
        echo "Updating affiliate_networks['amazon']['tag'] to vtete-20\n";
    }
    
    // Also ensure top level tags are correct if they were somehow old
    $option['amazon_tag'] = 'vtete-20';
    $option['amazon_tag_uk'] = 'vtete-20';
    $option['amazon_tag_de'] = 'vtete-20';
    $option['amazon_tag_fr'] = 'vtete-20';
    
    // Keep India as it is if it's already set to virginiatete-21
    if (empty($option['amazon_tag_in'])) {
        $option['amazon_tag_in'] = 'virginiatete-21';
    }

    update_option('helmetsan_revenue', $option);
    echo "Successfully updated helmetsan_revenue option.\n";
} else {
    echo "Could not find helmetsan_revenue option or it's not an array.\n";
}
