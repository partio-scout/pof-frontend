<?php

function enqueue_styles_and_scripts() {

    // Remove jQuery from the front as we use our own
    if ( ! is_admin() ) {
        wp_deregister_script( 'jquery' );
    }

    // Local styles & scripts
    wp_enqueue_style( 'main-css', get_template_directory_uri() . '/assets/dist/main.css', false, '1.2.0' );

    wp_register_script( 'main-js', get_template_directory_uri() . '/assets/dist/main.js', false, '1.2.0' );

    // We have to manually add dustpress-responsive-background-image plugin JS-file, because we deregister jquery.
    wp_enqueue_script( 'dp-resp-bgimg-main', plugins_url( 'dustpress-responsive-background-image/assets/dist/main.js' ), [], '1.2.0', true );

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
    wp_enqueue_script( 'admin-js', get_template_directory_uri() . '/assets/dist/admin.js', false, '1.0.9' );
}
add_action( 'admin_enqueue_scripts', 'enqueue_admin_styles_and_scripts' );

add_action( 'dustpress/js/dependencies', function( $deps ) {
    // Change the dependency from jquery to main-js as jquery is now part of it
    if ( ! is_admin() ) {
        $deps = [ 'main-js' ];
    }

    return $deps;
});
