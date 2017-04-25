<?php

class ProgramLangnav extends \DustPress\Model {

    public $current_lang;

    public function Lang() {
        $post = \DustPress\Query::get_acf_post( get_the_ID() );
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

    public function add_lang_to_body_class( $classes ) {
            $classes['lang'] = $this->current_lang;
            return $classes;
    }
}