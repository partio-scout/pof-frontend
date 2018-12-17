<?php

class ProgramLangnav extends \DustPress\Model {

    /**
     * Currently selected lang slug
     *
     * @var string
     */
    public static $current_lang = '';

    /**
     * Get language switcher data
     *
     * @return array Language list.
     */
    public function Lang() : array {
        $langs = pll_the_languages([
            'echo'                   => false,
            'raw'                    => true,
            'hide_if_no_translation' => true,
        ]);

        $lang = [
            'langs' => array_map( [ __CLASS__, 'map_languages' ], $langs ),
        ];

        return $lang;
    }

    /**
     * Map default language list
     *
     * @param  array $lang Language to modify.
     * @return array       Modified $lang.
     */
    public static function map_languages( array $lang ) : array {
        $lang['lang'] = strtoupper( $lang['slug'] );

        if ( $lang['current_lang'] ) {
            $lang['class']        = 'active ';
            static::$current_lang = $lang['slug'];
            add_filter( 'body_class', [ __CLASS__, 'add_lang_to_body_class' ] );
        }

        if ( is_search() ) {
            $current = rawurlencode( search_base( pll_current_language() ) );
            $default = rawurlencode( search_base( 'fi' ) );

            $lang['url'] = str_replace( $current, rawurlencode( search_base( $lang['slug'] ) ), $lang['url'] );
        }

        $lang['permalink'] = $lang['url'];

        return $lang;
    }

    /**
     * Add current language to body classes
     *
     * @param  array $classes Current classes.
     * @return array          Modified $classes.
     */
    public static function add_lang_to_body_class( array $classes ) : array {
        $classes['lang'] = static::$current_lang;
        return $classes;
    }
}
