<?php
namespace partio\Helpers;
/**
 * Create excerpt
 */
class Excerpt extends \DustPress\Helper {
    /**
     * Returns the helper html.
     *
     * @return mixed|string
     */
    public function output() {
        if ( ! isset( $this->params->string ) ) {
            return 'DustPress Excerpt helper error: no string defined.';
        }
        if ( ! is_string( $this->params->string ) ) {
            return 'DustPress Excerpt helper error: string is not a string.';
        }
        else {
            $string = preg_replace( '/\r|\n/', '', $this->params->string );
            // Remove html tags from the string.
            $string = strip_tags( $string );
        }
        if ( isset( $this->params->length ) ) {
            $length = $this->params->length;
        }
        else {
            $length = 140;
        }
        if ( strlen( $string ) <= $length ) {
            return $string;
        }
        else {
            // Wrap string with '\n' from the last whitespace before $length.
            $string = wordwrap( $string, $length, '\n', false );
            // Cut out from that added '\n' and add '...' after the string.
            $string = substr( $string, 0, strpos( $string, '\n' ) ) . '...';
            return $string;
        }
    }
}

dustpress()->add_helper( 'excerpt', new Excerpt() );