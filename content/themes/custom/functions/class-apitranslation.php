<?php
/**
 * Contains the apitranslation
 */

namespace DustPress;

/**
 * ApiTranslation helper
 */
class ApiTranslation extends Helper {

    /**
     * Contains the api translations
     *
     * @var array
     */
    private static $translations = [];

    /**
     * Outputs the api translation or optional fallback
     *
     * @return mixed
     */
    public function output() {
        $path        = $this->params->path;
        $fallback    = $this->params->fallback ?? null;
        $translation = static::get_translation( $path ) ?: $fallback;

        return $translation;
    }

    /**
     * Get translation with path
     *
     * @param  string $path Path to translation.
     * @return mixed        Translation or null.
     */
    public static function get_translation( string $path ) {
        $path = explode( '.', $path );
        $item = static::get_translations();
        foreach ( $path as $key ) {
            if ( array_key_exists( $key, $item ) ) {
                $item = $item[ $key ];
            }
            else {
                return null;
            }
        }

        return $item;
    }

    /**
     * Get or generate translations
     *
     * @return array
     */
    public static function get_translations() {
        if ( empty( static::$translation ) ) {
            // Get translations from the api and transform them into an easily searchable format
            $kaannos_json         = get_field( 'kaannos-json', 'option' );
            $translations         = \POF\Api::get( $kaannos_json, true );
            $translations         = array_map( [ __CLASS__, 'map_get_translations' ], $translations );
            static::$translations = $translations;
        }

        return static::$translations;
    }

    /**
     * Map api translations into an easy to search format
     * Should only be used via array_map
     *
     * @param  array $group Group to modify.
     * @return array        Modified $group.
     */
    public static function map_get_translations( array $group ) : array {
        $locale = substr( get_locale(), 0, 2 );

        foreach ( $group as $lang ) {

            // Handle non-standard lang results
            if ( is_array( $lang['lang'] ) ) {
                $lang['lang'] = reset( $lang['lang'] );
            }

            if ( $lang['lang'] === $locale ) {
                $group = array_column( $lang['items'], 'value', 'key' );
                break;
            }
        }

        return $group;
    }
}

// Add the helper to dustpress
dustpress()->add_helper( 'apitranslation', new ApiTranslation() );
