<?php
/**
 * This file handles automatic generation of a meta description for posts that are missing one
 */

add_filter( 'wpseo_metadesc', function( $description ) {
    global $post;
    if ( empty( $description ) ) {
        // This will get the excerpt if possible but if that doesn't exist it will attempt to cut part of the text in the post content
        $excerpt = get_the_excerpt( $post );
        if ( ! empty( $excerpt ) ) {
            $description = $excerpt;
        }
        else {
            // If all else fails try to get the site description
            $blog_description = get_bloginfo( 'description' );
            if ( ! empty( $blog_description ) ) {
                $description = $blog_description;
            }
        }
    }

    return $description;
}, 100, 1 );
