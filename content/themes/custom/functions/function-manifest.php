<?php
/**
 * Handle all manifest.json related calls
 */

/**
 * Match manifest.json query for all or no language
 */
add_action( 'init', function() {
    add_rewrite_rule( '^([a-z]*)(?:/|)manifest.json?', 'index.php?manifest=true&lang=$matches[1]', 'top' );
});

/**
 * Add custom query vars
 */
add_filter( 'query_vars', function( $vars ) {
    $vars[] = 'manifest';
    return $vars;
});

/**
 * Handle displaying the manifest file
 */
add_action( 'template_redirect', function() {
    if ( get_query_var( 'manifest' ) === 'true' ) {
        $lang = pll_current_language( 'locale' );

        // Attempt to get manifest from cache
        $cache_key = 'manifest/' . $lang;
        $data = wp_cache_get( $cache_key );
        if ( empty( $data ) ) {
            // If no cache start gathering data
            $start_url   = pll_home_url();
            $display     = 'standalone';
            $name        = get_bloginfo( 'name' );
            $short_name  = $name;
            $description = get_bloginfo( 'description' );

            // Collect data to show in the manifest file
            $data = [
                'lang'             => $lang,
                'start_url'        => $start_url,
                'display'          => $display,
                'name'             => $name,
                'short_name'       => $short_name,
                'description'      => $description,

                // Both of these colors are defined in _colors.scss
                'theme_color'      => '#253764', // partio_main_color
                'background_color' => '#28a9e1', // partio_main_color_lighter
            ];

            $icon_id = get_option( 'site_icon' );
            if ( ! empty( $icon_id ) ) {
                $img = wp_get_attachment_metadata( $icon_id );

                // Add full size mime type
                $img['mime-type'] = reset( $img['sizes'] )['mime-type'];
                // Get file upload path
                $folder = 'uploads/' . substr( $img['file'], 0, strrpos( $img['file'], '/' ) );

                // Add the full size image to the sizes table
                $img['sizes']['full'] = [
                    'file'      => substr( $img['file'], strrpos( $img['file'], '/' ) + 1 ),
                    'width'     => $img['width'],
                    'height'    => $img['height'],
                    'mime-type' => $img['mime-type'],
                ];

                // Format all the icons to manifest.json compatible format
                $icons = array_map( function( $data ) use ( $folder ) {
                    return [
                        'src'   => content_url( $folder . '/' . $data['file'] ),
                        'sizes' => $data['width'] . 'x' . $data['height'],
                        'type'  => $data['mime-type'],
                    ];
                }, $img['sizes'] );
                // Remove associative array keys
                $icons = array_values( $icons );

                // Add the icons to the manifest file
                $data['icons'] = $icons;
            }

            // 5 min cache for manifest file
            wp_cache_set( $cache_key, $data, null, 5 * 60 );
        }

        wp_send_json( $data );
    }
});
