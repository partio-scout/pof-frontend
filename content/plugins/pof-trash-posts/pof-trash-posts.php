<?php
/*
Plugin Name: POF Trash Posts
Plugin URI: http://www.geniem.com
Description: Imports data from POF-API
Version: 1.0.0
Author: Anttoni Lahtinen
*/

register_activation_hook(   __FILE__, array( 'POF_Trash_Posts', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'POF_Trash_Posts', 'deactivate' ) );

class POF_Trash_Posts {


    // Setup cronjob for importer daily automatic execution
    public static function activate() {
        //wp_schedule_event( time(), 'hourly', 'daily_cron' );
    }
    public static function deactivate() {
       //wp_clear_scheduled_hook('daily_cron');
    }

    public static function init() {
        //add_action( 'daily_cron',  array( 'POF_Trash_Posts', 'importer_cron' ) );
        //add_action( 'admin_menu', array( 'POF_Trash_Posts', 'pof_menu_init') );
    }

    /**
     * Get data from Partio backend and trashs some posts.
     *
     * @return bool
     */
    public static function execute() {
        $url   = 'https://pof-backend-staging.partio.fi/json-trash/';
        $data  = self::fetch_data( $url );
        $guids = self::extract_guids( $data );
        // No need to continue this further if we have no posts to trash.
        if ( empty( $guids ) ) {
            return false;
        }

        $guids = array(
            '1bcaa35ef3a97c5c18c6417bbe26384e',
            'bbcdbe44135b53597166e2ea255eb7f9',
            '1262148fe42ab7662adeebe458037404',
        );

        $ids = self::get_post_ids_by_guid( $guids );

        $trashed_posts = self::trash_posts( $ids );
    }

    /**
     * Fetches JSON from remote endpoint.
     * Parses and validates the JSON.
     *
     * @param string $url Remote endpoint.
     *
     * @return array|bool|mixed|object
     */
    public static function fetch_data( $url ) {
        $request = wp_remote_get( $url );

        if ( is_wp_error( $request ) ) {
            return false;
        }

        $body = wp_remote_retrieve_body( $request );
        $json = json_decode( $body, true );
        // Validate json and return null
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            return false;
        }
        return $json;

    }

    /**
     * Retrieves post id's based on list of guids.
     *
     * @param array $guids Array of guids.
     * @return array $ids Array of WordPress post ids.
     */
    public static function get_post_ids_by_guid( $guids ) {

        $ids = array();

        global $wpdb;
        // Prefix postmeta
        $tablename     = $wpdb->prefix . 'postmeta';
        $key           = 'api_guid';
        $cache_key     = 'pof_trash_posts_cache/' . date( 'm-Y' );
        $monthly_cache = get_transient( $cache_key ) ?: array();

        // Loop through all guids.
        foreach ( $guids as $guid ) {
            if ( in_array( $guid, $monthly_cache, true ) ) {
                // Prepare SQL Query
                $sql = $wpdb->prepare( "SELECT post_id FROM $tablename WHERE meta_key=%s AND meta_value=%s;", $key, $guid );
                // Find post_ids where api_guid matches the given guid.
                $posts = $wpdb->get_results( $sql , ARRAY_A );
                if ( ! empty( $posts ) ) {
                    // Get posts from array.
                    foreach ( $posts as $post ) {
                        $ids[] = $post['post_id'];
                    }
                    $guids_to_cache[] = $guid;
                }
            }
        }

        $to_be_cached = array_merge( $monthly_cache, $guids_to_cache );
        set_transient( $cache_key, $to_be_cached, MONTH_IN_SECONDS );

        return $ids;
    }


    /**
     * Extracts guids from data.
     *
     * @param array $data Contains arrays that have guids to extract.
     *
     * @return array $guids Array containing extracted guids.
     */
    public static function extract_guids( $data ) {
        $guids = array();

        // Check that data is valid.
        if ( ! empty( $data ) && is_array( $data ) ) {

            foreach ( $data as $post ) {
                // We don't want empty guids.
                if ( array_key_exists( 'guid', $post ) && ! empty( $post['guid'] ) ) {
                    $guids[] = $post['guid'];
                }
            }
        }
        return $guids;
    }

    /**
     * Trashes posts with id and logs the process.
     *
     * @param array $ids Post ids to trash.
     * @return array $log Results from trashing operation.
     */
    public static function trash_posts( $ids ) {

        $log                     = array();
        $log['trashed_posts']    = array();
        $log['already_in_trash'] = array();
        $log['not_found']        = array();

        if ( is_array( $ids ) && ! empty( $ids ) ) {
            foreach ( $ids as $id ) {

                $post = get_post( $id );
                if ( empty( $post ) ) {
                    // Post does not exist.
                    $log['not_found'][] = $id;
                    continue;
                }
                if ( $post['post_status'] === 'trash' ) {
                    // Post already trashed, but not in cache.
                    $log['already_in_trash'][] = $id;
                    continue;
                }
                // Post exists and not in trash. Trash it.
                // This function does the above checks but
                // This way the code is clearer.
                wp_trash_post( $id );
                $log['trashed_posts'][] = $id;
            }
        }
        // Get current cache that stores all operations for debugging purposes.
        $pof_log = get_transient( 'pof_trash_posts_log' );
        // Add current log to cache.
        $pof_log[ time() ] = $log;
        // Save data back.
        set_transient( $pof_log, 'pof_trash_posts_log', YEAR_IN_SECONDS );
        return $log;
    }

}

POF_Trash_Posts::init();
