<?php
/**
 * Change seo image, if it exists.
 *
 * @param string $new_image Image url.
 * @return void
 */
function change_seo_image( $new_image ) {
    add_filter( 'wpseo_opengraph_image', function( $image ) use ( $new_image ) {
        if ( ! empty( $new_image ) ) {
            $image = $new_image;
        }

        return $image;
    } );
}