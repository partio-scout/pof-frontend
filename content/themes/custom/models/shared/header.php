<?php

class Header extends \DustPress\Model {

    public function Analytics() {
        return get_field('google_analytics', 'option');
    }

    /**
     * Get the slug for current language
     */
    public function LangSlug() {
        $slug = pll_current_language();
        return $slug;
    }

    public function HomeUrl() {
        return pll_home_url();
    }

    /**
     * Get search page url for current language
     */
    public function SearchUrl() {
        return generate_search_url();
    }

    /**
     * Get base language home url
     *
     * @return string
     */
    public function RawHomeUrl() {
        return pll_home_url( 'fi' );
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

        // Class to add to menus that are currently open
        $opened_class = 'opened';

        // Do not open the menu anywhere when in search
        if ( is_search() ) {
            $opened_class = '';
        }

        //TODO: Make this dynamically and open as many parents element have, not manually defined amount
        if ( !empty( $menu )) {
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
                        $menu[ $key ]->current_first = $opened_class;
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
                                $menu[ $key ]->current_first = $opened_class;
                                $menu[ $key ]->children[ $child_key ]->current_second = $opened_class;
                                $menu[ $key ]->children[ $child_key ]->current_id = $curpage;
                            }

                            // Keeps menu open even if we are level that won't occur in menu
                            if ( $firstparent === false &&
                                ( $curpageparentsparent === $menu[ $key ]->children[ $child_key ]->ID ||
                                  $curpagethirdparent === $menu[ $key ]->children[ $child_key ]->ID )
                            ) {
                                $menu[ $key ]->current_first = $opened_class;
                                $menu[ $key ]->children[ $child_key ]->current_second = $opened_class;
                                $menu[ $key ]->current_id = $curpageparent;
                            }
                        }
                    }
                }
            }
        }
        return $menu;
    }


    public function LangSwitcher() {

        // If this is a search page we should return translation urls for this search
        if ( is_search() ) {
            $langs = pll_the_languages([
                'echo'                   => false,
                'raw'                    => true,
                'hide_if_no_translation' => false,
            ]);
            foreach ( $langs as $key => $value ) {
                $lang['langs'][ $key ]['lang'] = strtoupper( $value['slug'] );

                // Construct a correctly formatted search url link and remove language slug from the url if it is the main language
                $lang['langs'][ $key ]['permalink'] = generate_search_url( get_query_var( 's' ), $value['slug'] );
                if ( $value['current_lang'] ) {
                    $lang['langs'][ $key ]['class'] = 'active ';
                }
            }
            return $lang;
        }

        $post = \DustPress\Query::get_acf_post( get_the_ID() );

        // If the post is not created by the api, we have to get the translations otherway.
        if (empty($post->fields['api_guid'])) {
            $langs = pll_the_languages([
                'echo' => 0,
                'raw' => 1,
                'hide_if_no_translation' => 1,
            ]);
            foreach ($langs as $key => $value) {
                $lang['langs'][$key]['lang']        = strtoupper($value['slug']);
                $lang['langs'][$key]['permalink']   = $value['url'];
                if($value['current_lang']) $lang['langs'][$key]['class']   = 'active ';

            }
            return $lang;
        }

        $args = [
            'posts_per_page'    => -1,
            'post_type'         => 'page',
            'post_status'       => 'publish',
            'meta_key'          => 'api_guid',
            'meta_value'        => $post->fields['api_guid'],
            'lang'              => '',
        ];
        $pages  = \DustPress\Query::get_acf_posts( $args );
        $lang = [];

        // Get main languages
        $languages = function_exists('pll_languages_list') ? pll_languages_list() : array();
        // Get the parent model name.
        $model_args     = $this->get_args();
        $parent_model   = $model_args['model'];
        // Iterator for non-top-level-languages
        $exist_extra_langs = 0;

        foreach ($pages as $page) {
            // Force api created pages to right lang order, ksort below before return languages
            $key = array_search( strtolower( $page->fields['api_lang'] ), $languages );

            // If page is not top level language, generate key after bae languages
            if ($key === false) {
                $key = count($languages) + $exist_extra_langs;
                $exist_extra_langs++;
            }

            $lang['langs'][$key]['id']          = $page->ID;
            $lang['langs'][$key]['lang']        = $page->fields['api_lang'];
            $lang['langs'][$key]['permalink']   = $page->permalink;
            $lang['langs'][$key]['model']       = $parent_model;

            // Build class string and store current language
            if ( $post->fields['api_lang'] == $page->fields['api_lang'] ) {
                $lang['langs'][$key]['class']   = 'active ';
                $this->current_lang         = strtolower( $post->fields['api_lang'] );
                add_filter( 'body_class', [ $this, 'add_lang_to_body_class' ] );
            }

            // This page does not belong to a top level language
            if ( ! in_array( strtolower( $page->fields['api_lang'] ), $languages, true ) ) {
                $lang['langs'][$key]['class'] .= 'dynamic';
            }

            trim( $lang['langs'][$key]['class'] );
        }
        ksort($lang['langs']);
        return $lang;
    }


    public function add_lang_to_body_class( $classes ) {
        $classes['lang'] = $this->current_lang;
        return $classes;
    }


    /**
     * Bind translated strings.
     *
     * @return array An associative array of translations.
     */
    public function S() {
        $s = [
            'valikko'            => __( 'Main Menu', 'pof' ),
            'haku'               => __( 'Search', 'pof' ),
            'use_adv_search'     => __( 'Use the advanced search', 'pof' ),
            'filter_search'      => __( 'Filter the results', 'pof' ),
            'filter_by_age'      => __( 'Filter by agegroup', 'pof' ),
            'by_agegroup'        => __( 'By agegroup', 'pof' ),
            'more_filter'        => __( 'More filters', 'pof' ),
            'other_filters'      => __( 'Other filters', 'pof' ),
            'filter_type'        => __( 'Filter type', 'pof' ),
            'and'                => __( 'And', 'pof' ),
            'or'                 => __( 'Or', 'pof' ),
            'loadmore'           => __( 'Load more', 'pof' ),
            'results_found'      => __( 'results found', 'pof' ),
            'frontpage'          => __( 'Frontpage', 'pof' ),
            'search_results'     => __( 'Search results', 'pof' ),
            'search_placeholder' => __( 'Tell us what you are looking for..', 'pof' ),
            'search_title'       => __( 'Search on site', 'pof' ),
        ];
        return $s;
    }
}
