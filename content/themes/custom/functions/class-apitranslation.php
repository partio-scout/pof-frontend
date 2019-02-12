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
            $kaannos_json = get_field( 'kaannos-json', 'option' );
            $translations = \POF\Api::get( $kaannos_json, true );
            $locale       = get_short_locale();
            foreach ( $translations as &$group ) {
                foreach ( $group as $lang ) {
                    if ( $lang['lang'] === $locale ) {
                        $group = array_column( $lang['items'], 'value', 'key' );
                        continue 2;
                    }
                }
            }
            static::$translations = $translations;
        }

        return static::$translations;
    }
}

// Add the helper to dustpress
dustpress()->add_helper( 'apitranslation', new ApiTranslation() );
