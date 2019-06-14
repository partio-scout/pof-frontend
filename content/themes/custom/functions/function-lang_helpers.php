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

/**
 * Get search base url
 *
 * @param  string $lang If defined get the base url for only this language.
 * @return mixed        Either all base urls or a single one if $lang was defined.
 */
function search_base( $lang = null ) {
    $base = [
        'fi' => 'haku',
        'en' => 'search',
        'sv' => 'sök',
    ];

    return $lang ? $base[ $lang ] : $base;
}

/**
 * Get pagination base url
 *
 * @param  string $lang If defined get the base url for only this language.
 * @return mixed        Either all base urls or a single one if $lang was defined.
 */
function pagination_base( $lang = null ) {
    $base = [
        'fi' => 'sivu',
        'en' => 'page',
        'sv' => 'sida',
    ];

    return $lang ? $base[ $lang ] : $base;
}

/**
 * Generate a search url
 *
 * @param  string $search What is the search qyery.
 * @param  string $lang   What language to use, defaults to null which will get the current language.
 * @return string         Search url.
 */
function generate_search_url( $search = '', $lang = null ) {
    $lang = $lang !== null ? $lang : pll_current_language();

    $url = '/' . ( pll_default_language() !== $lang ? $lang . '/' : '' ) . search_base( $lang ) . '/' . $search;

    return $url;
}

/**
 * Get the short version of the locale
 *
 * @return string
 */
function get_short_locale() : string {
    $locale = reset( str_word_count( get_locale(), 1 ) );
    return $locale;
}
