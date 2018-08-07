<?php

function err($str) {
    echo '<p style="color: white; important!">';
    echo $str;
    echo '<p>';
}

// Instantiate DustPress
dustpress();

// Enable dustpress cache
add_filter( 'dustpress/settings/cache', '__return_true' );

// Add geniem functions
include_once( 'functions/function-geniem_admin.php' );

// Add custom post types
include_once( 'functions/function-post_types.php' );

// Enable taxonomies
include_once( 'functions/function-taxonomies.php' );

// Disable emojis
include_once( 'functions/function-disable_emojis.php' );

// Disable discover links
include_once( 'functions/function-disable_discover_links.php' );

// Enqueue scripts and styles
include_once( 'functions/function-scripts_and_styles.php' );

// Add image sizes
include_once( 'functions/function-image_sizes.php' );

// Disable api input fields
include_once( 'functions/function-disable_api_inputs.php' );

// Helpers for api data
include_once( 'functions/function-api_data_helpers.php' );

// Helpers language
include_once( 'functions/function-lang_helpers.php' );

// Menu functions
include_once( 'functions/function-menus.php' );

// Search functions
include_once( 'functions/function-search.php' );

// Dustpress apiimage helper & filter
include_once( 'functions/function-api_image_helper.php' );

// Post seo description generation
include_once( 'functions/function-post-description.php' );

// manifest.json handler
include_once( 'functions/function-manifest.php' );



/*
 * Fixes missing field key mappings in postmeta table for pages
 */
function acf_fix() {

    $dp         = new DustPressHelper();
    $acf_key    = 'group_559a2a90921ab'; // group key
    $fields     = file_get_contents( get_template_directory_uri() . '/acf-json/' . $acf_key . '.json' );
    $fields     = json_decode( $fields, true );
    $fields     = $fields['fields'];

    $pages = $dp->get_acf_posts( ['meta_keys' => 'all', 'posts_per_page' => -1, 'post_type' => 'page'] );

    foreach ( $pages as $page ) {
        foreach ( $page['meta'] as $k => $v ) {
            $idx = [];
            if ( dustpress()->array_search_recursive( $k, $fields, $idx ) ) {
                $key = $fields[$idx[0]]['key'];
                update_post_meta( $page['ID'], '_' . $k, $key );
            }
        }
    }
}
