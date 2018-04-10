<?php

// returns the search page url for current language
function get_search_page( $lang ) {

}

function get_languages() {
    $languages = apply_filters( 'wpml_active_languages', NULL, array( 'skip_missing' => 0 ) );

    if( !empty( $languages ) ) {
    	return $languages;
    }
}

function pof_theme_setup() {
    load_theme_textdomain( 'pof', get_template_directory() . '/languages' );

    $locale = get_locale();
    $locale_file = get_template_directory() . "/languages/$locale.php";

    if ( is_readable( $locale_file ) ) {
        require_once( $locale_file );
    }
    
    // Enable HTML5 markup support
    add_theme_support( 'html5', [ 'caption', 'comment-form', 'comment-list', 'gallery', 'search-form' ] );
}
add_action( 'after_setup_theme', 'pof_theme_setup' );
