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
            // Gets current page ID and the current page parent
            $curpage = get_the_ID();
            $curpageparent = wp_get_post_parent_id();

            $args['post_parent'] = $item->object_id;
            $children = \DustPress\Query::get_posts( $args );

            // If page has children
            if ( is_array($children) && count( $children ) > 0 ) {
                $menu[ $key ]->has_children = true;
                $menu[ $key ]->children = $children;

                // Keeps sidemenu open if on a page that is listed in the menu
                if ( $curpageparent === (int) $menu[ $key ]->object_id ) {
                    $menu[ $key ]->current_first = 'opened';
                    $menu[ $key ]->current_id = $curpage;
                }

                // Loop through childrens
                foreach ( $menu[ $key ]->children as $child_key => $children ) {
                    $args['post_parent'] = $children->ID;
                    $sub_children = \DustPress\Query::get_posts( $args );

                    // If page has children
                    if ( is_array($sub_children) && count( $sub_children ) > 0 ) {
                        $menu[ $key ]->children[ $child_key ]->has_sub_children = true;
                        $menu[ $key ]->children[ $child_key ]->children = $sub_children;

                        // Keeps sidemenu open if on a page that is listed in the menu
                        if ( $curpageparent ===  $menu[ $key ]->children[ $child_key ]->ID ) {
                            $menu[ $key ]->current_first = 'opened';
                            $menu[ $key ]->children[ $child_key ]->current_second = 'opened';
                            $menu[ $key ]->children[ $child_key ]->current_id = $curpage;
                        }
                    }
                }
            }
        }
        return $menu;
    }

    public function LangSwitcher() {
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

        foreach ($pages as $key => $page) {
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
        return $lang;
    }

    // Bind translated strings.
    public function S() {

        $s = [
            'valikko' => __('Main Menu', 'pof')
        ];

        return $s;

    }
}
