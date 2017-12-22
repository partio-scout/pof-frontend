<?php
/*
 Template name: Etusivu
*/

class PageFrontpage extends \DustPress\Model {

    // public $ttl = [
    //     'Program' => 3600
    // ];

    private $dp; // helper

    public function Content() {

        $this->bind_sub("Header");
        $this->bind_sub("Footer");
        return \DustPress\Query::get_acf_post( get_the_ID() );
    }

    // Get program and agegroups to frontpage
    public function Program() {
        $args = [
            'posts_per_page'    => -1,
            'post_type'         => 'page',
            'post_status'       => 'publish',
            'meta_key'          => 'api_type',
            'meta_value'        => 'program',
            'orderby'           => 'menu_order',
            'order'             => 'ASC'
        ];
        $program  = \DustPress\Query::get_acf_posts( $args );
        $args['post_parent'] = $program[0]->ID;
        unset($args['meta_key'], $args['meta_value']);

        $children = \DustPress\Query::get_acf_posts( $args );
        foreach ( $children as &$page ) {
            map_api_images( $page->fields['api_images'] );
        }
        if ( is_object( $program[0] ) ) {
            $program[0]->Children = $children;
        }
        return $program[0];
    }

    public function SliderTime() {
        return get_field('slider_time', 'option') * 1000;
    }

    public function Languages() {
        $langs = pll_the_languages([
            'echo' => 0,
            'raw' => 1,
            'hide_if_no_translation' => 1,
        ]);
        return $langs;
    }
}
