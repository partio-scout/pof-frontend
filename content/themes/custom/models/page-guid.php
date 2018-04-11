<?php
/*
 Template name: GUID-ohjaus
*/

class PageGuid extends \DustPress\Model {

    // Method to get page/tips with guid and redirect to correct place in site
    public function init() {
        $guid = get_query_var( 'guid' );
        if ( !$guid || trim( $guid ) === '' ) {
            wp_redirect('/');
            exit;
        }
        $lang = get_query_var( 'lang' );
        if ( !$lang || trim( $guid ) === '' ) $lang = 'FI';

        $args = [
            'posts_per_page' => -1,
            'lang'           => strtolower( $lang ),
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'meta_query'     => array(
                array(
                    'key'     => 'api_guid',
                    'value'   => $guid,
                    'compare' => '=',
                ),
            )
        ];

        $pages  = \DustPress\Query::get_posts( $args );
        if ( $pages && count( $pages ) === 1 ) {
            wp_redirect( $pages[0]->permalink );
            exit;
        }

        // If page not found, check also if guid is tip-spesific
        $tips_args = [
            'status' => 'approve',
            'meta_query'     => array(
                array(
                    'key'     => 'guid',
                    'value'   => $guid,
                    'compare' => '=',
                ),
            )
        ];
        $tips = get_comments( $tips_args );
        if ( $tips && count( $tips ) === 1 ) {
            $parent = get_permalink( $tips[0]->comment_post_ID );
            wp_redirect( $parent . '#' . $guid );
            exit;
        }

        // No content found, redirect to frontpage
        wp_redirect('/');
        exit;
    }
}