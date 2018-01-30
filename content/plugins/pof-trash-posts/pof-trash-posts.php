<?php
/*
Plugin Name: POF Trash Posts
Plugin URI: http://www.geniem.com
Description: Delete posts deleted from backend with cron.
Version: 1.0.0
Author: Anttoni Lahtinen
*/

register_activation_hook( __FILE__, array( 'POF_Trash_Posts', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'POF_Trash_Posts', 'deactivate' ) );

/**
 * Class POF_Trash_Posts
 *
 * This plugin reads feed like https://pof-backend.partio.fi/json-trash/
 * and removes posts based on guids in their postmeta.
 */
class POF_Trash_Posts {

    /**
     * Setup cronjob for importer daily automatic execution
     */
    public static function activate() {
        wp_schedule_event( time(), 'hourly', 'pof-trash-posts' );
    }

    /**
     * Remove cron hook after deactivating.
     */
    public static function deactivate() {
        wp_clear_scheduled_hook( 'pof-trash-posts' );
    }

    /**
     * Add action for cron to execute.
     */
    public static function init() {
        add_action( 'pof-trash-posts',  array( 'POF_Trash_Posts', 'execute' ) );
    }

    /**
     * Get data from Partio backend and trashs some posts.
     *
     * @return bool
     */
    public static function execute() {
        $url = get_field( 'trash-json', 'option' );
        if ( empty( $url ) ) {
            $log['error'] = 'API url not found!';
            self::keep_log( $log );
            return false;
        }
        $data  = self::fetch_data( $url );
        $guids = self::extract_guids( $data );
        // No need to continue this further if we have no posts to trash.
        if ( empty( $guids ) ) {
            $log['error'] = 'No guids given';
            self::keep_log( $log );
            return false;
        }
        $ids           = self::get_post_ids_by_guid( $guids );
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
            $log['error'] = 'Data fetching contains error';
            self::keep_log( $log );
            return false;
        }

        $body = wp_remote_retrieve_body( $request );
        $json = json_decode( $body, true );
        // Validate json and return null
        if ( json_last_error() !== JSON_ERROR_NONE ) {
            $log['error'] = 'JSON error: ' . json_last_error_msg();
            self::keep_log( $log );
            return false;
        }
        return $json;

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
     * Retrieves post id's based on list of guids.
     *
     * @param array $guids Array of guids.
     * @return array|bool $ids Array of WordPress post ids or false
     */
    public static function get_post_ids_by_guid( $guids ) {

        $ids = array();

        global $wpdb;
        // Prefix postmeta
        $tablename = $wpdb->prefix . 'postmeta';
        $key       = 'api_guid';
        //  Keep transient cache for already used guids. No point deleting same post again.
        $cache_key      = 'pof_trash_posts_cache/' . date( 'm-Y' );
        $monthly_cache  = get_transient( $cache_key ) ?: array();
        $guids_to_cache = array();

        // Loop through all guids.
        foreach ( $guids as $guid ) {
            if ( ! in_array( $guid, $monthly_cache, true ) ) {
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
                } else {
                    $log['error'] = 'No posts found.';
                    self::keep_log( $log );
                    return false;
                }
            } else {
                $log['error'] = 'Guid: ' . $guid . ' in cache.';
                self::keep_log( $log );
                return false;
            }
        }

        $to_be_cached = array_merge( $monthly_cache, $guids_to_cache );

        set_transient( $cache_key, $to_be_cached, MONTH_IN_SECONDS );

        return $ids;
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
                // We do not actually trash any posts yet. Just for testing.
                // wp_trash_post( $id );
                $log['trashed_posts'][] = $id;
            }
        } else {
            $log['error'] = 'No ids given';
            self::keep_log( $log );
            return false;
        }
        self::keep_log( $log );
    }

    /**
     * Read cache from log add stuff and store it back.
     *
     * Contains timestamped array containing arrays:
     *  - trashed_posts     | Array of trashed posts
     *  - already_in_trash  | Post already in trash
     *  - not_found         | Post not found
     *  - error             | Error message
     *
     * @param array $log Log.
     */
    public static function keep_log( $log ) {
        // Get current cache that stores all operations for debugging purposes.
        $pof_log = get_transient( 'pof_trash_posts_log' );
        // Add current log to cache.
        $pof_log[ time() ] = $log;
        // Save data back.
        set_transient( 'pof_trash_posts_log', $pof_log,  YEAR_IN_SECONDS );
    }
}

POF_Trash_Posts::init();