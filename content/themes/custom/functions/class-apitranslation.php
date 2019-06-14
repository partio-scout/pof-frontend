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
        $translation = parse_path( $path, static::get_translations() ) ?: $fallback;

        return $translation;
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
        $locale = reset( str_word_count( get_locale(), 1 ) ); // Get the start of the locale string

        foreach ( $group as $lang ) {

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
