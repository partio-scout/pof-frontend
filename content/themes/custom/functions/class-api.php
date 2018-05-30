<?php
/**
 * Theme translation helpers
 */

namespace POF;

/**
 * Translation helper class
 */
class Api {

    /**
     * Get json data from url
     *
     * @param  string $url Api url.
     * @return mixed       Api data or null.
     */
    public static function get( $url ) {
        $cache_key = 'api_call/' . $url;

        // Try to get the translations first from cache
        $data = wp_cache_get( $cache_key );
        if ( empty( $data ) && static::validate_external_url( $url ) ) {
            // Retrieve the json with a larger than normal timeout just in case
            $request = wp_remote_get( $url, [
                'timeout' => 120,
            ]);

            // Get data from response
            $data = static::parse_request_json( $request );

            // Store the translations to cache
            wp_cache_set( $cache_key, $data, null, HOUR_IN_SECONDS );
        }

        return $data;
    }

    /**
     * Check that an url is valid and external
     *
     * @param  string $url Url to check.
     * @return bool
     */
    public static function validate_external_url( $url ) {
        if ( ! empty( $url ) ) {
            $scheme = wp_parse_url( $url, PHP_URL_SCHEME );
            if ( in_array( $scheme, [ 'http', 'https' ], true ) ) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse json from request
     *
     * @param  array   $request     Wp request data.
     * @param  boolean $assoc_array Should the json be cast into an associative array.
     * @return mixed
     */
    public static function parse_request_json( $request, $assoc_array = false ) {
        // Parse the json if the request passed
        if ( ! is_wp_error( $request ) ) {
            $body = wp_remote_retrieve_body( $request );
            $data = json_decode( $body, $assoc_array );

            return $data;
        }

        return false;
    }
}
