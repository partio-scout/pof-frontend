<?php
/**
 * Contains the apititle
 */

namespace DustPress;

/**
 * ApiTitle helper
 */
class ApiTitle extends Helper {

    /**
     * Outputs the image markup or nothing
     *
     * @return string
     */
    public function output() {
        $locale    = $this->params->locale ?? $this->context->get( 'locale' ) ?? get_short_locale();
        $title     = $this->params->title ?? $this->context->get( 'title' );
        $languages = $this->params->languages ?? $this->context->get( 'languages' );

        if ( is_array( $languages ) ) {
            foreach ( $languages as $language ) {
                $language = (array) $language;
                if ( $language['lang'] === $locale && ! empty( $language['title'] ) ) {
                    return $language['title'];
                }
            }
        }

        return $title;
    }
}

// Add the helper to dustpress
dustpress()->add_helper( 'apititle', new ApiTitle() );
