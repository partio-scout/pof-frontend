<?php
/**
 * Contains the apiicon helper
 */

namespace DustPress;

/**
 * ApiIcon helper
 */
class ApiIcon extends Helper {

    /**
     * Contains the api icons
     *
     * @var array
     */
    private static $icons = [];

    /**
     * Outputs the api icon or optional fallback
     *
     * @return mixed
     */
    public function output() {
        $base         = $this->params->base;
        $guid         = $this->params->guid ?? 'default';
        $icon         = $this->params->icon;
        $no_default   = $this->params->no_default ? true : false; // Fallback to default by default if no icon is found in guid
        $icon_path    = $base . '.' . $guid . '.' . $icon;
        $default_path = $base . '.default.' . $icon;
        $icon         = parse_path( $icon_path, static::get_icons() ) ?: (
            ! $no_default ?
                parse_path( $default_path, static::get_icons() ) :
                null
        );

        return $icon;
    }

    /**
     * Get or generate icons
     *
     * @return array
     */
    public static function get_icons() {
        if ( empty( static::$icons ) ) {
            // Get translations from the api and transform them into an easily searchable format
            $icon_json = get_field( 'icon-json', 'option' );
            $icons     = \POF\Api::get( $icon_json, true );
            foreach ( $icons as &$group ) {
                $group = array_column( $group, 'items', 'post_guid' );
                foreach ( $group as $slug => &$items ) {
                    // Replace empty slug with "default"
                    if ( empty( $slug ) ) {
                        $group['default'] = $items;
                        unset( $group[ $slug ] );
                        continue;
                    }

                    $items = array_column( $items, 'icon', 'key' );
                }
            }

            static::$icons = $icons;
        }

        return static::$icons;
    }
}

// Add the helper to dustpress
dustpress()->add_helper( 'apiicon', new ApiIcon() );
