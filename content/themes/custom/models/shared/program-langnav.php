<?php
/**
 * Get language navigation
 */

/**
 * ProgramLangnav class
 */
class ProgramLangnav extends \DustPress\Model {

    /**
     * Contains the current lang
     *
     * @var string
     */
    public $current_lang;

    /**
     * Get the language navigation
     *
     * @return array An array of languages.
     */
    public function Lang() {
        $post = \DustPress\Query::get_acf_post( get_the_ID() );

        // If the post is not created by the api, we have to get the translations otherway.
       if ( is_search() || empty( $post->fields['api_guid'] ) ) {
            $lang = [
                'langs' => array_map(function( $lang ) {
                        // Modify pll_the_languages result
                        $lang['lang'] = strtoupper( $lang['slug'] );

                        if ( is_search() ) {
                            $current = rawurlencode( search_base( pll_current_language() ) );
                            $default = rawurlencode( search_base( 'fi' ) );

                            $lang['url'] = str_replace( $current, rawurlencode( search_base( $lang['slug'] ) ), $lang['url'] );
                            $lang['url'] = str_replace( $default, rawurlencode( search_base( $lang['slug'] ) ), $lang['url'] );
                        }

                        $lang['permalink'] = $lang['url'];

                        if ( $lang['current_lang'] ) {
                            $lang['class'] = 'active ';
                        }

                        return $lang;
                    }, pll_the_languages([
                        'echo'                   => false,
                        'raw'                    => true,
                        'hide_if_no_translation' => true,
                    ])
                ),
            ];

            return $lang;
        }

        $args  = [
            'posts_per_page' => -1,
            'post_type'      => 'page',
            'post_status'    => 'publish',
            'meta_key'       => 'api_guid',
            'meta_value'     => $post->fields['api_guid'],
            'lang'           => '',
        ];
        $pages = \DustPress\Query::get_acf_posts( $args );
        $lang  = [];

        // Get main languages
        $languages = function_exists( 'pll_languages_list' ) ? pll_languages_list() : array();

        // Get the parent model name.
        $model_args   = $this->get_args();
        $parent_model = $model_args['model'];
        // Iterator for non-top-level-languages
        $exist_extra_langs = 0;

        foreach ( $pages as $page ) {
            // Force api created pages to right lang order, ksort below before return languages
            $key = array_search( strtolower( $page->fields['api_lang'] ), $languages, true );

            // If page is not top level language, generate key after bae languages
            if ( $key === false ) {
                $key = count( $languages ) + $exist_extra_langs;
                $exist_extra_langs++;
            }
            $lang['langs'][ $key ]['id']        = $page->ID;
            $lang['langs'][ $key ]['lang']      = $page->fields['api_lang'];
            $lang['langs'][ $key ]['permalink'] = $page->permalink;
            $lang['langs'][ $key ]['model']     = $parent_model;

            // Build class string and store current language
            if ( $post->fields['api_lang'] === $page->fields['api_lang'] ) {
                $lang['langs'][ $key ]['class'] = 'active ';
                $this->current_lang             = strtolower( $post->fields['api_lang'] );
                add_filter( 'body_class', [ $this, 'add_lang_to_body_class' ] );
            }

            // This page does not belong to a top level language
            if ( ! in_array( strtolower( $page->fields['api_lang'] ), $languages, true ) ) {
                $lang['langs'][ $key ]['class'] .= 'dynamic';
            }

            trim( $lang['langs'][ $key ]['class'] );
        }
        ksort( $lang['langs'] );
        return $lang;
    }

    /**
     * Add current language to the body classes
     *
     * @param  array $classes Current classes.
     * @return array          Modified $classes.
     */
    public function add_lang_to_body_class( $classes ) {
        $classes['lang'] = $this->current_lang;
        return $classes;
    }
}
