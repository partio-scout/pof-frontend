<?php
add_action( 'init', 'page_rewrite', 999, 0 );
/**
 * Rewrite the loadmore
 */
function page_rewrite() {
    add_rewrite_rule( 'guid/(.+)', 'index.php?pagename=guid&guid=$matches[1]', 'top' );
}

add_action( 'init', 'translate_base_rewrite', 999, 0 );
/**
 * This changes the default pagination and search base.
 */
function translate_base_rewrite() {
    global $wp_rewrite;
    $wp_rewrite->pagination_base = '(?:' . implode( pagination_base(), '|' ) . ')';
    $wp_rewrite->search_base     = '(?:' . implode( search_base(), '|' ) . ')';
}

add_filter( 'pre_get_posts', 'show_empty_search' );
/**
 * Show search page even with an empty query
 *
 * @param  WP_Query $query Current query.
 * @return WP_Query        Modified $query.
 */
function show_empty_search( $query ) {

    // If this is a search url without any search query display the search page anyways
    $search_bases = array_map( 'urlencode', search_base() );
    if (
        $query->is_main_query() &&
        empty( filter_input( INPUT_GET, 's', FILTER_SANITIZE_URL ) ) &&
        (
            (
                array_key_exists( 'name', $query->query ) &&
                in_array( $query->query['name'], $search_bases, true )
            ) ||
            (
                array_key_exists( 'category_name', $query->query ) &&
                in_array( $query->query['category_name'], $search_bases, true )
            )
        )
    ) {
        $query->is_search = true;
        $query->is_home   = false;
    }

    return $query;
}


add_action( 'template_redirect', 'search_redirect' );
/**
 * Redirects search results from /?s=query to /search/query/, converts %20 to +
 *
 * @link http://txfx.net/wordpress-plugins/nice-search/
 */
function search_redirect() {
    global $wp_rewrite, $wp_query;
    if ( ! isset( $wp_rewrite ) || ! is_object( $wp_rewrite ) || ! $wp_rewrite->get_search_permastruct() ) {
        return;
    }

    $search_parameter = filter_input( INPUT_GET, 's', FILTER_SANITIZE_URL );
    if ( is_search() && ! is_admin() && ! empty( $search_parameter ) ) {
        wp_safe_redirect( generate_search_url( $search_parameter ) );
        exit();
    }
}

add_filter( 'query_vars', 'add_query_vars_filter' );
function add_query_vars_filter( $vars ){
    $vars[] = "guid";
    $vars[] = "lang";
    return $vars;
}
