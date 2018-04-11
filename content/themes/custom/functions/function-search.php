<?php
add_action( 'init', 'page_rewrite', 999, 0 );
/**
 * Rewrite the loadmore
 */
function page_rewrite() {
    global $wp_rewrite;
    // This rule matches /haku/{search clause}(/sivu/{page number}/)
    add_rewrite_rule( '^haku/([^/]+)(/sivu/([^/]+))?(/)?$', 'index.php?s=$matches[1]&paged=$matches[2]', 'top' );
    
    // This rule matches /guid/{post guid}/)
    add_rewrite_rule( 'guid/(.+)', 'index.php?pagename=guid&guid=$matches[1]', 'top' );
}
add_action( 'init', 'translate_base_rewrite', 999, 0 );
/**
 * This changes the default pagination and search base.
 */
function translate_base_rewrite() {
    global $wp_rewrite;
    $wp_rewrite->pagination_base = 'sivu';
    $wp_rewrite->search_base     = 'haku';
}
add_action( 'template_redirect', 'search_redirect' );
/**
 * Redirects search results from /?s=query to /search/query/, converts %20 to +
 *
 * @link http://txfx.net/wordpress-plugins/nice-search/
 */
function search_redirect() {
    global $wp_rewrite;
    if ( ! isset( $wp_rewrite ) || ! is_object( $wp_rewrite ) || ! $wp_rewrite->get_search_permastruct() ) {
        return;
    }
    $search_parameter = filter_input( INPUT_GET, 's', FILTER_SANITIZE_URL );
    if ( is_search() && ! is_admin() && ! empty( $search_parameter ) ) {
        wp_safe_redirect( get_search_link() );
        exit();
    }
}

add_filter( 'query_vars', 'add_query_vars_filter' );
function add_query_vars_filter( $vars ){
    $vars[] = "guid";
    $vars[] = "lang";
    return $vars;
}
