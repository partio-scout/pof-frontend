<?php

class Sidenav extends \DustPress\Model {

    // public $ttl = [
    //     'Content' => 3600
    // ];

    public function Content() {
        $menuLocation = get_nav_menu_locations();
        $menu = wp_get_nav_menu_items( $menuLocation['main-menu'] );

        $args = [
            'posts_per_page'    => -1,
            'post_type'         => 'page',
            'post_status'       => 'publish',
            'orderby'           => 'menu_order title',
            'order'             => 'ASC',
        ];
        foreach ( $menu as $key => $item ) {
            $args['post_parent'] = $item->object_id;
            $children = \DustPress\Query::get_posts( $args );
            if ( is_array($children) && count( $children ) > 0 ) {
                $menu[ $key ]->has_children = true;
                $menu[ $key ]->children = $children;
                foreach ( $menu[ $key ]->children as $child_key => $children ) {
                    $args['post_parent'] = $children->ID;
                    $sub_children = \DustPress\Query::get_posts( $args );
                    if ( count( $sub_children ) > 0 ) {
                        $menu[ $key ]->children[ $child_key ]->has_sub_children = true;
                        $menu[ $key ]->children[ $child_key ]->children = $sub_children;
                    }
                }
            }
        }
        return $menu;
    }

    public function LangSwitcher() {
        return get_languages();
    }

    // Bind translated strings.
    public function S() {

        $s = [
            'valikko' => __('Main Menu', 'pof')
        ];

        return $s;

    }    
}