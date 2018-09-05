<?php
/*
 Template name: GUID-ohjaus
*/

/**
 * PageGuid Class
 */
class PageGuid extends \DustPress\Model {

    /**
     * Method to get page/tips with guid and redirect to correct place in site
     */
    public function init() {

        // Get necessary args
        $guid = get_query_var( 'guid' );
        $lang = strtolower( get_query_var( 'lang', 'fi' ) );
        if ( empty( $guid ) ) {
            wp_safe_redirect( '/' );
            exit;
        }

        $args = [
            'posts_per_page' => 1,
            'lang'           => $lang,
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => 'api_guid',
                    'value'   => $guid,
                    'compare' => '=',
                ],
            ],
        ];

        $pages = ( new \WP_Query( $args ) )->posts;
        if ( ! empty( $pages ) ) {
            wp_safe_redirect( get_permalink( $pages[0] ) );
            exit;
        }

        // If page not found, check also if guid is tip-spesific
        $tips_args = [
            'status'     => 'approve',
            'meta_query' => [
                [
                    'key'     => 'guid',
                    'value'   => $guid,
                    'compare' => '=',
                ],
            ],
        ];
        $tips      = get_comments( $tips_args );
        if ( $tips && count( $tips ) === 1 ) {
            $parent = get_permalink( $tips[0]->comment_post_ID );
            wp_safe_redirect( $parent . '#' . $guid );
            exit;
        }

        // No content found, redirect to frontpage
        wp_safe_redirect( '/' );
        exit;
    }
}
