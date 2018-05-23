<?php
/**
 * Contains the apiimage
 */

namespace DustPress;

/**
 * ApiImage helper
 */
class ApiImage extends Helper {

    /**
     * Outputs the image markup or nothing
     *
     * @return mixed
     */
    public function output() {
        return get_api_media( $this->params->id );
    }
}

// Add the helper to dustpress
dustpress()->add_helper( 'apiimage', new ApiImage() );

/**
 * Modify content to detect any api images in it and rerender them
 */
add_filter( 'the_content', function( $content ) {

    // All api images seem to have a wp-image-(id) class so use that to parse the id
    $regex = '/<p><a.*wp-image-([0-9]+).*<\/a><\/p>/';

    $content = preg_replace_callback( $regex, function( $matches ) {
        $new_image = get_api_media( $matches[1] );

        if ( ! empty( $new_image ) ) {
            return $new_image;
        }

        return $matches[0];

    }, $content );

    return $content;
});
