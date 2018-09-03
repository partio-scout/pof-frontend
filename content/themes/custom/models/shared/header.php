<?php
/**
 * Header class file
 */

/**
 * Header class
 */
class Header extends \DustPress\Model {

    /**
     * Get google analytics key
     */
    public function Analytics() {
        return get_field( 'google_analytics', 'option' );
    }

    /**
     * Get current language slug
     */
    public function LangSlug() {
        return pll_current_language();
    }

    /**
     * Get current language home url
     */
    public function HomeUrl() {
        return pll_home_url();
    }

    /**
     * Add current language slug to body classes
     *
     * @param  array $classes Class list.
     * @return array          Modified $classes.
     */
    public function add_lang_to_body_class( $classes ) {
        $classes['lang'] = pll_current_language();
        return $classes;
    }

    /**
     * Get translations
     *
     * @return array
     */
    public function S() {
        $s = [
            'valikko' => __( 'Main Menu', 'pof' ),
        ];
        return $s;
    }
}
