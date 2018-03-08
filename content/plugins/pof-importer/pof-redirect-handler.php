<?php
/**
 * Keeps track of importer redirect changes and helps if page slug has been changed.
 */

class POF_Redirect {

    /**
     * Add template redirect hook.
     */
    public static function initialize() {
        add_action( 'template_redirect', array( 'POF_Redirect', 'redirect_broken_url' ) );
    }

    /**
     * Added into template_redirect hook. Tries to redirect if 404 is happening.
     */
    public static function redirect_broken_url() {
        // Check if page is not found.
        if ( is_404() ) {
            $cache      = get_option( 'pof_redirect_cache' );
            $broken_url = $_SERVER['REQUEST_URI'];

            if ( is_array( $cache ) && array_key_exists( $broken_url, $cache ) ) {
				$slug = $cache[ $broken_url ];
                // Prevent loops.
				if ( $broken_url !== $slug ) {
                    wp_safe_redirect( $slug );
                    die();
                }
            }
        }
    }

    /**
     * Stores new redirects with other data.
     *
     * @param array $redirect_cache Cache to store.
     */
    public static function update_cache( $redirect_cache = array() ) {
        $current_redirect_cache            = get_option( 'pof_redirect_cache' );
        $redirect_cache['updated'] = time();

        if ( $current_redirect_cache ) {
            $new_redirect_cache = array_merge( $current_redirect_cache, $redirect_cache );
            update_option( 'pof_redirect_cache', $new_redirect_cache );
        }
    }
}
