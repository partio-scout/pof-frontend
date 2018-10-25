<?php

class Breadcrumbs extends \DustPress\Model {

    public function Content() {
        $ancestors = get_post_ancestors( get_the_ID() );

        if ( count( $ancestors ) > 0 ) {
            foreach ( array_reverse($ancestors) as $ancestor ) {
                $breadcrumb[] = [
                    "title" => get_the_title( $ancestor ),
                    "url" => get_permalink( $ancestor ),
                ];
            }
        }

        $breadcrumb[] = [
            "title" => get_the_title(),
            "url" => get_permalink(),
            "current" => true
        ];

        return $breadcrumb;
    }

    /*
    * Current language front page link
    */
    public function CurrentLangFrontPLink() {

        // Checks if Polylang is in use
        if ( function_exists( 'pll_current_language' ) ) {
            $currentLangCode = pll_current_language( "slug" );
            $currentLink     = pll_home_url( $currentLangCode );
        } else {
            $currentLink     = home_url();
        }

        return $currentLink;
    }
}