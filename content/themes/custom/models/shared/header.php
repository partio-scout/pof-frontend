<?php

class Header extends \DustPress\Model {

    public function Analytics() {
    	return get_field('google_analytics', 'option');
    }

    public function LangSlug() {
        return pll_current_language();
    }

    public function HomeUrl() {
        return pll_home_url();
    }


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
        //TODO: Make this dynamically and open as many parents element have, not manually defined amount
        foreach ( $menu as $key => $item ) {
            // Gets current page ID and the current page parent (3 levels)
            $curpage = get_the_ID();
            $curpageparent = wp_get_post_parent_id($curpage);
            $curpageparentsparent = wp_get_post_parent_id($curpageparent);
            $curpagethirdparent = wp_get_post_parent_id($curpageparentsparent);

            $args['post_parent'] = $item->object_id;
            $children = \DustPress\Query::get_posts( $args );

            // If page has children
            if ( is_array($children) && count( $children ) > 0 ) {
                $menu[ $key ]->has_children = true;
                $menu[ $key ]->children = $children;

                // Keeps sidemenu open if on a page that is listed in the menu
                if ( $curpageparent === (int) $menu[ $key ]->object_id ) {
                    $firstparent = true;
                    $menu[ $key ]->current_first = 'opened';
                    $menu[ $key ]->current_id = $curpage;
                }

                // Loop through childrens
                foreach ( $menu[ $key ]->children as $child_key => $children ) {
                    $firstparent = false;
                    $args['post_parent'] = $children->ID;
                    $sub_children = \DustPress\Query::get_posts( $args );

                    // If page has children
                    if ( is_array($sub_children) && count( $sub_children ) > 0 ) {
                        $menu[ $key ]->children[ $child_key ]->has_sub_children = true;
                        $menu[ $key ]->children[ $child_key ]->children = $sub_children;

                        // Keeps sidemenu open if on a page that is listed in the menu
                        if ( $curpageparent ===  $menu[ $key ]->children[ $child_key ]->ID ) {
                            $firstparent = true;
                            $menu[ $key ]->current_first = 'opened';
                            $menu[ $key ]->children[ $child_key ]->current_second = 'opened';
                            $menu[ $key ]->children[ $child_key ]->current_id = $curpage;
                        }

                        // Keeps menu open even if we are level that won't occur in menu
                        if ( $firstparent === false &&
                            ( $curpageparentsparent === $menu[ $key ]->children[ $child_key ]->ID ||
                              $curpagethirdparent === $menu[ $key ]->children[ $child_key ]->ID )
                        ) {
                            $menu[ $key ]->current_first = 'opened';
                            $menu[ $key ]->children[ $child_key ]->current_second = 'opened';
                            $menu[ $key ]->current_id = $curpageparent;
                        }                         
                    }
                }
            }
        }
        return $menu;
    }

    // Bind translated strings.
    public function S() {
        $s = [
            'valikko' => __('Main Menu', 'pof')
        ];
        return $s;
    }
}