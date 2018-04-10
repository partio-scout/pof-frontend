<?php
/*
 Template name: GUID-ohjaus
*/

class PageGuid extends \DustPress\Model {

    public function init() {
        $guid = get_query_var( 'guid' );
        if (!$guid || trim($guid) === '') {
            wp_redirect('/');
            exit;
        }
        $lang = get_query_var( 'lang' );
        if (!$lang || trim($guid) === '') $lang = 'FI';

        $args = [
            'posts_per_page' => -1,
            'lang'           => strtolower($lang),
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

        if (count($pages) === 1) {
            wp_redirect($pages[0]->permalink);
            exit;
        }

        wp_redirect('/');
        exit;
    }
}