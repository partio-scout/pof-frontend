<?php
/*
 Template name: GUID-ohjaus
*/

class PageGuid extends \DustPress\Model {

    /**
     * Method to get page/tips with guid and redirect to correct place in site
     */
    public function init() {
        $guid = get_query_var( 'guid' );
        $lang = get_query_var( 'lang' ) ?: pll_current_language();

        if ( empty( $guid ) ) {
            wp_safe_redirect( '/' );
            exit;
        }

        $id  = ( new \WP_Query([
            'fields'         => 'ids',
            'posts_per_page' => 1,
            'lang'           => $lang,
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'meta_query'     => [
                [
                    'key'     => 'api_guid',
                    'value'   => $guid,
                    'compare' => '=',
                ],
            ],
        ]) )->posts[0];
        $url = get_permalink( $id );

        if ( $url ) {
            wp_safe_redirect( $url );
            exit;
        }

        // If page not found, check also if guid is tip-spesific
        $tips = get_comments([
            'status'     => 'approve',
            'meta_query' => [
                [
                    'key'     => 'guid',
                    'value'   => $guid,
                    'compare' => '=',
                ],
            ],
        ]);

        if ( $tips ) {
            $parent = get_permalink( $tips[0]->comment_post_ID );
            wp_safe_redirect( $parent . '#' . $guid );
            exit;
        }

        // No content found, redirect to frontpage
        wp_safe_redirect( '/' );
        exit;
    }
}
