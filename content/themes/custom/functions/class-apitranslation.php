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
     * Outputs the api translation or optional fallback
     *
     * @return mixed
     */
    public function output() {
        // Get translations from the api and transform them into an easily searchable format
        $kaannos_json = get_field( 'kaannos-json', 'option' );
        $translations = \POF\Api::get( $kaannos_json, true );
        $locale       = substr( get_locale(), 0, 2 );
        foreach ( $translations as &$group ) {
            foreach ( $group as $lang ) {
                if ( $lang['lang'] === $locale ) {
                    $group = array_column( $lang['items'], 'value', 'key' );
                    continue 2;
                }
            }
        }

        $path     = $this->params->path;
        $fallback = $this->params->fallback ?? null;
        $path     = explode( '.', $path );
        $item     = $translations;
        foreach ( $path as $key ) {
            if ( array_key_exists( $key, $item ) ) {
                $item = $item[ $key ];
            }
            else {
                return $fallback;
            }
        }

        return $item;
    }
}

// Add the helper to dustpress
dustpress()->add_helper( 'apitranslation', new ApiTranslation() );
