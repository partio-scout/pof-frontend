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
     * Get the slug for current language
     *
     * @return string
     */
    public function LangSlug() : string {
        $slug = pll_current_language();

        if ( $slug !== pll_default_language() ) {
            return $slug;
        }

        return '';
    }

    /**
     * Get current language home url
     */
    public function HomeUrl() {
        return pll_home_url();
    }

    /**
     * Get search page url for current language
     *
     * @return string
     */
    public function SearchUrl() {
        return generate_search_url();
    }

    /**
     * Get base language home url
     *
     * @return string
     */
    public function RawHomeUrl() {
        return pll_home_url( 'fi' );
    }

    // Bind translated strings.
    public function S() {
        $s = [
            'valikko' => __('Main Menu', 'pof')
        ];
        return $s;
    }
}
