<?php

function enqueue_styles_and_scripts(){

    // replace jquery for front end with a newer version
    if ( ! is_admin() ) {
        wp_deregister_script( 'jquery' );
        wp_register_script( 'jquery', 'https://ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js', false, '2.1.5' );
    }

    // jquery
    wp_enqueue_script( 'jquery' );

    // Local styles & scripts
    wp_enqueue_style( 'main-css', get_template_directory_uri() . '/assets/dist/main.css', false, '1.1.0', all );

    wp_register_script( 'main-js', get_template_directory_uri() . '/assets/dist/main.js', false, '1.1.0', all );
    // Localize data for javascript to use.
    $localized_data = array(
        // Contains titles for search
        'tips_url'   => get_field( 'tips-send-url', 'option' ),
    );
    wp_localize_script( 'main-js', 'pof', $localized_data );

    $lang_slug = pll_current_language();
    wp_localize_script( 'main-js', 'pof_lang', [
        'slug'            => $lang_slug,
        'search_base'     => search_base( $lang_slug ),
        'pagination_base' => pagination_base( $lang_slug ),
    ]);
    wp_enqueue_script( 'main-js' );
}
add_action( 'wp_enqueue_scripts', 'enqueue_styles_and_scripts' );


function enqueue_admin_styles_and_scripts( $hook ) {
    wp_enqueue_script( 'admin-js', get_template_directory_uri() . '/assets/dist/admin.js', false, '1.0.9', all );
}
add_action( 'admin_enqueue_scripts', 'enqueue_admin_styles_and_scripts' );
