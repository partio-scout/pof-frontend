<?php
/*
Plugin Name: POF Importer
Plugin URI: http://www.geniem.com
Description: Imports data from POF-API
Version: 0.0.2
Author: Ville Siltala, Kalle Haavisto
*/

register_activation_hook( __FILE__, array( 'POF_Importer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'POF_Importer', 'deactivate' ) );

/**
 * Class POF_Importer
 */
class POF_Importer {
    // Class constants
    const API_IMAGES_FIELD = 'field_55a369e3d3b3a';
    const API_ATTACHMENTS_FIELD = 'field_57bffb08a4191';

    // Class variables
    private $queried = [];      // pages queried from db
    private $data;              // data from json api
    private $tree_url;          // url to main tree json
    private $tips_url;          // url to tips json
    private $success;           // stores success values for error handling
    private $error;             // error string/array
    private $updated;           // count the number of updated pages
    private $created;           // count the number of created pages
    private $debug;             // print out data after import
    private $api_images_key;    // field key for api images repeater
    private $start_time;        // microtime for script execution start
    private $normal_importer;   // tell that we run normal importer
    private $tips_importer;     // tell that we run tips importer
    private $site_languages;    // get site general languages
    private static $instance;

    public static function init() {
        if ( ! static::$instance ) {
            static::$instance = new POF_Importer();
        }

        return static::$instance;
    }

    public function __construct() {
        add_action( 'init', array( $this, 'init_plugin' ) );
        add_action( 'daily_cron',  array( $this, 'importer_cron' ) );
        if ( defined( '\\WP_CLI' ) && \WP_CLI ) {

            // Does the import
            \WP_CLI::add_command( 'pof import', [ $this, 'importer_cron' ] );

            // Deletes imported api items that don't exist anymore
            \WP_CLI::add_command( 'pof cleanup', [ $this, 'importer_cleanup' ] );
        }
    }

    /**
     * Delete imported posts that no longer exist in the backend
     *
     * @return bool Whether posts were deleted or not.
     */
    public function importer_cleanup() : bool {

        $this->wp_cli_msg( 'Deleting old imported data.' );

        // Collect all of the currently existing guid's
        $tree  = $this->fetch_data( $this->tree_url );
        $guids = array_keys( $this->flatten_tree( $tree['program'][0] ) );

        // Get all current posts
        $ids = ( new WP_Query([
            'fields'         => 'ids',
            'post_type'      => 'page',
            'post_status'    => 'any',
            'posts_per_page' => -1, //phpcs:ignore
        ]) )->posts;

        /**
         * Filter the id's to those that have a guid that is not in the guids list
         *
         * @param  int $id Post id to check.
         * @return bool    Whether this post should be deleted or not.
         */
        $delete_ids = array_filter( $ids, function( int $post_id ) use ( $guids ) : bool {
            $guid = get_field( 'api_guid', $post_id );
            return (
                ! empty( $guid ) &&
                ! in_array( $guid, $guids, true )
            );
        });

        if ( empty( $delete_ids ) ) {
            $this->wp_cli_msg( 'No posts to delete.' );
            return false;
        }

        // Check that we will still have a suitable amount of posts after the deletion
        if ( count( $ids ) - count( $delete_ids ) < 70 ) {
            $this->wp_cli_error( 'There would be less than a 1000 posts after cleanup so aborting just in case.' );
            return false;
        }

        global $wpdb;
        $this->wp_cli_msg( 'Deleting old posts.' );
        $posts_table = $wpdb->prefix . 'posts';
        $wpdb->query( 'DELETE FROM ' . $posts_table . ' WHERE ID IN(' . implode( ',', $delete_ids ) . ')' );
        $this->wp_cli_msg( 'Deleting old postmeta.' );
        $postmeta_table = $wpdb->prefix . 'postmeta';
        $wpdb->query( 'DELETE FROM ' . $postmeta_table . ' WHERE post_id IN(' . implode( ',', $delete_ids ) . ')' );

        return true;
    }

    /**
     * Print message to wp cli if it is enabled
     *
     * @param string $msg Message to print.
     */
    protected function wp_cli_msg( $msg ) {
        if ( defined( '\\WP_CLI' ) && \WP_CLI ) {
            \WP_CLI::log( $msg );
        }
    }

    /**
     * Print success message to wp cli if it is enabled
     *
     * @param string $msg Message to print.
     */
    protected function wp_cli_success( $msg ) {
        if ( defined( '\\WP_CLI' ) && \WP_CLI ) {
            \WP_CLI::success( $msg );
        }
    }

    /**
     * Print error message to wp cli if it is enabled
     *
     * @param string $msg Message to print.
     */
    protected function wp_cli_error( $msg ) {
        if ( defined( '\\WP_CLI' ) && \WP_CLI ) {
            \WP_CLI::error( $msg );
        }
    }

    /**
     * Print warning message to wp cli if it is enabled
     *
     * @param string $msg Message to print.
     */
    protected function wp_cli_warning( $msg ) {
        if ( defined( '\\WP_CLI' ) && \WP_CLI ) {
            \WP_CLI::warning( $msg );
        }
    }

    /**
     * Create wp cli progress bar if it is enabled.
     *
     * @param  string $msg   Message to show alongside progressbar.
     * @param  int    $count Amount of items to process.
     * @return mixed         Progressbar class or dummy object.
     */
    protected function wp_cli_progress( $msg, $count ) {
        if ( defined( '\\WP_CLI' ) && \WP_CLI ) {
            $progress = \WP_CLI\Utils\make_progress_bar( $msg, $count );
        }
        else {
            // Create dummy functions if wp cli is not enabled
            $progress = (object) [
                'tick'   => function() {},
                'finish' => function() {},
            ];
        }

        return $progress;
    }

    /**
     * Get api tree url
     *
     * @param  array $extra_params Optional extra params to add to url.
     * @return string              Url.
     */
    public function get_tree_url( array $extra_params = [] ) : string {
        if ( ! empty( $this->tree_url ) ) {
            return $this->tree_url;
        }

        $tree_url = get_field( 'ohjelma-json', 'option' );
        if ( ! empty( $tree_url ) ) {
            $url_parts = wp_parse_url( $tree_url );
            $params    = [];
            parse_str( $url_parts['query'], $params );
            $params             = $params += $extra_params;
            $url_parts['query'] = http_build_query( $params );
            $tree_url           = $url_parts['scheme'] . '://' . $url_parts['host'] . $url_parts['path'] . '?' . $url_parts['query'];
        }

        $this->tree_url = $tree_url;
        return $tree_url;
    }

    public function init_plugin() {

        // get API url from options
        include_once ABSPATH . 'wp-admin/includes/plugin.php'; // included for is_plugin_active()
        if ( is_plugin_active( 'advanced-custom-fields-pro/acf.php' ) ) {

            $this->tree_url       = $this->get_tree_url( [ 'rand' => mt_rand() ] );
            $this->tips_url       = get_field( 'tips-json', 'option' ) . '?rand=' . mt_rand();
            $this->site_languages = array();
            if ( function_exists( 'pll_languages_list' ) ) {
                $this->site_languages = pll_languages_list();
            }
        }

        // setup attributes
        $this->debug = 1;

        // Add admin menu
        if ( is_admin() ) {
            add_action( 'admin_menu', array( $this, 'pof_menu_init' ) );
        }
    }

    // Setup cronjob for importer daily automatic execution
    public static function activate() {
        wp_schedule_event( time(), 'hourly', 'daily_cron' );
    }
    public static function deactivate() {
        wp_clear_scheduled_hook( 'daily_cron' );
    }

    // Setup cronjob for importer daily automatic execution
    public function importer_cron() {
        $start = microtime( true );
        $this->wp_cli_msg( 'Beginning import' );
        ini_set( 'memory_limit', '-1' );
        $this->import();
        update_field( 'field_57c6b36acafd4', date( 'Y-m-d H:i:s' ), 'option' );
        $this->import_tips();
        update_field( 'field_57c6b3e52325e', date( 'Y-m-d H:i:s' ), 'option' );
        $this->wp_cli_msg( 'Import finished in: ' . round( microtime( true ) - $start, 4 ) . 's' );
        $this->importer_cleanup();
    }

    /*
    * Helper function to calc script execution time
    */
    public function execution_time( $halt = false ) {
        $execution_time = microtime( true ) - $this->start_time;
        echo '<b>Total Execution Time:</b> ' . $execution_time . ' Seconds';
        if ( $halt ) {
            die();
        }
    }

    /*
    *  import
    *
    *  This function starts the import functions and handles the responses
    *
    */
    public function import() {

        // init counters
        $this->updated    = 0;
        $this->created    = 0;
        $this->start_time = microtime( true );

        // fetch the POF API tree
        $tree = $this->fetch_data( $this->tree_url );

        // tree imported
        if ( $tree ) {
            $tree = $this->import_data( $tree['program'][0] );
            $tree = $this->retrieve_new_data( $tree );

            $this->update_pages( $tree );
        }
    }

    /*
    * import tips data
    *
    * This function starts the tips importing
    *
    */
    public function import_tips() {
        // init counters
        $this->updated    = 0;
        $this->created    = 0;
        $this->start_time = microtime( true );

        // fetch tasks from database
        $args  = [
            'posts_per_page' => -1,
            'post_type'      => 'page',
            'post_parent'    => $post_id,
            'post_status'    => 'publish',
            'meta_key'       => 'api_type',
            'meta_value'     => 'task',
        ];
        $tasks = \DustPress\Query::get_acf_posts( $args );

        $taskData = array();
        foreach ( $tasks as $key => $task ) {
            $taskData[ $task->fields['api_guid'] ][ strtolower( $task->fields['api_lang'] ) ] = $task;
        }

        $this->update_tips( $taskData );
    }

    /**
     * Recursively add api item to flattened array
     *
     * @param array  $item      Item to add.
     * @param array  $flattened Array to gather items to.
     * @param string $parent    Parent guid.
     */
    protected function add_to_flattened( $item, &$flattened, $parent = null ) {
        $item['parent']  = $parent;
        $items_to_search = [
            'taskgroups',
            'tasks',
            'agegroups',
        ];

        // Add oldest parent to flattened first so that we can create the posts in the right order
        $flattened[ $item['guid'] ] = array_filter( $item, function( string $key ) use ( $items_to_search ) : bool {

            // Remove unnecessary data from item
            return ! in_array( $key, $items_to_search, true );
        }, ARRAY_FILTER_USE_KEY );

        // Recursively go through items
        foreach ( $items_to_search as $key ) {
            if ( array_key_exists( $key, $item ) ) {
                foreach ( $item[ $key ] as $new_item ) {
                    $this->add_to_flattened( $new_item, $flattened, $item['guid'] );
                }
            }
        }
    }


    /**
     * Flatten api program tree into a single array
     *
     * @param  array $tree Api program tree.
     * @return array       Flattened tree.
     */
    protected function flatten_tree( $tree ) {
        $flattened = [];
        $start     = microtime( true );

        $this->add_to_flattened( $tree, $flattened );

        $this->wp_cli_msg( 'Flattened tree in: ' . round( microtime( true ) - $start, 4 ) . 's' );

        return $flattened;
    }

    /*
    *  import_data
    *
    *  This function loops recursively through the API tree and imports items from the POF API.
    *
    */
    public function import_data( $tree ) {
        $tree = $this->flatten_tree( $tree );
        $tree = $this->get_import_data( $tree );

        return $tree;
    }

    /**
     * Get data to import
     * This function loads items from api and compares them to wp data.
     * Modified pages/json data are returned for later use.
     *
     * @param  array $tree Api data.
     * @return array       Modified & filtered $tree.
     */
    private function get_import_data( $tree ) {

        $start = microtime( true );
        $posts = \DustPress\Query::get_acf_posts([
            'post_type'      => 'page',
            'post_status'    => 'any',
            'posts_per_page' => -1, //phpcs:ignore
        ]);
        // Store each post and its language
        foreach ( $posts as $post ) {
            $guid = $post->fields['api_guid'];
            $lang = strtolower( $post->fields['api_lang'] );

            if ( ! array_key_exists( $guid, $this->queried ) ) {
                $this->queried[ $guid ] = [];
            }
            $this->queried[ $guid ][ $lang ] = $post;
        }

        // Add all pages to queried list
        $this->wp_cli_msg( 'Got all pages in: ' . round( microtime( true ) - $start, 4 ) . 's' );

        // Create wp cli progress bar
        $progress = $this->wp_cli_progress( 'Filtering posts to update', count( $tree ) );

        // Update tree data for languages
        $start    = microtime( true );
        $importer = $this; // Store this in variable to pass it to the functions
        $tree     = array_map(function( $item ) use ( $posts, $progress, $importer ) {

            // Update progressbar
            $progress->tick();

            // Collect only matching posts
            $pages = array_filter( $posts, function( $page ) use ( $item ) {
                return ( ! empty( $page->fields ) && $page->fields['api_guid'] === $item['guid'] );
            });

            // Update languages with necessary data
            $item['languages'] = array_map(function( $lang ) use ( $pages, $importer ) {

                if ( empty( $pages ) ) {

                    // No matching pages so we need to create new ones
                    $lang['update'] = true;
                }
                else {

                    // Mark posts to update
                    foreach ( $pages as $page ) {
                        if ( $lang['lang'] === strtolower( $page->fields['api_lang'] ) ) {

                            // Store the page to possibly update it or translations later
                            $lang['page'] = $page;

                            // Mark post for update
                            if (
                                (
                                    strtotime( $page->fields['api_lastmodified'] ) <
                                    strtotime( $lang['lastModified'] )
                                ) ||
                                (
                                    strtotime( $page->post_modified ) <
                                    strtotime( $lang['lastModified'] )
                                )
                            ) {
                                $lang['update'] = true;
                            }
                        }
                    }
                }

                return $lang;
            }, $item['languages'] );

            return $item;
        }, $tree);

        // Finish progressbar
        $progress->finish();

        // Reduce tree to only those that need to be updated
        $tree = array_filter( $tree, function( $item ) {
            foreach ( $item['languages'] as $lang ) {
                if ( $lang['update'] ) {
                    return true;
                }
            }

            return false;
        });
        $this->wp_cli_msg( 'Posts to update: ' . count( $tree ) );

        return $tree;
    }

    /**
     * Get new data for languages
     *
     * @param  array $tree Api data tree.
     * @return array       Modified $tree.
     */
    private function retrieve_new_data( $tree ) {

        $pool_size = ( defined( '\\WP_CLI' ) && \WP_CLI ) ? 20 : 5; // Max concurrent requests
        $pool      = []; // Current requests
        $done      = []; // Done requests

        // Get full limit of calls
        $size = 0;
        foreach ( $tree as $item ) {
            foreach ( $item['languages'] as $lang ) {
                $size++;
            }
        }

        // Create wp cli progress bar
        $progress = $this->wp_cli_progress( 'Fetching new data', $size );
        foreach ( $tree as $item ) {
            foreach ( $item['languages'] as $lang ) {
                $progress->tick();

                if ( $lang['update'] ) {
                    if ( count( $pool ) >= $pool_size ) {
                        $responses = \Requests::request_multiple( $pool );
                        foreach ( $responses as $resp ) {
                            if ( $resp->status_code !== 200 ) {
                                $this->wp_cli_error( 'Request failed: (' . $resp->url . ')' );
                            }
                        }
                        $done = array_merge( $done, $responses );
                        $pool = [];
                    }

                    $pool[ $lang['details'] ] = [
                        'url'  => $lang['details'],
                        'type' => 'GET',
                    ];
                }
            }
        }
        // Finish any dangling requests
        if ( ! empty( $pool ) ) {
            $responses = \Requests::request_multiple( $pool );
            $done      = array_merge( $done, $responses );
            $pool      = [];
        }
        $progress->finish();

        // Assign results to languages
        foreach ( $tree as &$item ) {
            foreach ( $item['languages'] as &$lang ) {
                if ( $lang['update'] ) {
                    $lang['data'] = ! empty( $done[ $lang['details'] ]->body ) ? json_decode( $done[ $lang['details'] ]->body, true ) : null;
                }
            }
        }

        return $tree;
    }

    /**
     * Begin the actual import process
     *
     * @param array $tree Api data.
     */
    private function update_pages( $tree ) {
        $queried = $this->queried;

        // Create wp cli progress bar
        $progress = $this->wp_cli_progress( 'Importing page content', count( $tree ) );
        $start    = microtime( true );
        $class    = $this;

        $tree = array_map(function( $item ) use ( $progress, &$queried, $class ) {
            $progress->tick();

            // Collect connected posts
            $translations = array_reduce( $item['languages'], function( $carry, $lang ) use ( $class ) {
                if ( ! empty( $lang['page'] ) ) {
                    $carry[ $lang['lang'] ] = $lang['page']->ID;
                }

                return $carry;
            }, []);

            $new_translations  = false;
            $item['languages'] = array_map( function( $lang ) use ( &$new_translations, &$translations, &$queried, $item, $class ) {

                if ( $lang['update'] && $lang['data'] ) {

                    // Post object params
                    $args = [
                        'post_title'    => $lang['title'] ?? $item['title'],
                        'menu_order'    => $item['order'],
                        'post_status'   => 'publish',
                        'post_type'     => 'page',
                        'post_content'  => $lang['data']['content'],
                        'page_template' => 'models/page-' . $lang['data']['type'] . '.php',
                        'post_modified' => $lang['lastModified'],
                    ];

                    // If item has parent link to it
                    if ( ! empty( $item['parent'] ) ) {
                        $parent_id = $queried[ $item['parent'] ][ $lang['lang'] ]->ID;
                        if ( ! empty( $parent_id ) ) {

                            // Parent post already exists
                            $args['post_parent'] = $parent_id;
                        }
                        else {

                            // Try to get post parent via wp query if it wasnt part of the import
                            $post_parent_query = new \WP_Query([
                                'post_type'      => 'page',
                                'posts_per_page' => 1,
                                'fields'         => 'ids',
                                'meta_key'       => 'api_guid',
                                'meta_value'     => $item['parent'],
                            ]);
                            if ( ! empty( $post_parent_query->posts ) ) {
                                $args['post_parent'] = $post_parent_query->posts[0];
                            }
                            else {

                                // Parent post doesnt exist
                                $class->wp_cli_warning( 'Failed to get post parent for guid (' . $item['guid'] . '), parent should have guid (' . $item['parent'] . ')' );
                            }
                        }
                    }

                    // If page already exists update it
                    if ( $lang['page'] ) {
                        // Assign id to update page
                        $args['ID'] = $lang['page']->ID;

                        // Check that there are actually updated values
                        foreach ( $args as $key => $value ) {
                            if ( $lang['page']->{ $key } !== $value ) {
                                // Update the post
                                $post_id = wp_insert_post( $args );
                                break;
                            }
                        }
                        $post_id = $post_id ?? $lang['page']->ID;
                    }
                    else {

                        // Create the post
                        $post_id = wp_insert_post( $args );

                        // Set post language
                        if ( in_array( $lang['lang'], $this->site_languages, true ) ) {
                            if ( function_exists( 'pll_set_post_language' ) ) {
                                pll_set_post_language( $post_id, $lang['lang'] );
                                $new_translations = true;
                            }
                            // Store set language
                            $translations[ $lang['lang'] ] = $post_id;
                        }

                    }
                    if ( ! empty( $post_id ) ) {
                        $pseudo_page = (object) [
                            'ID' => $post_id,
                        ];

                        // Create dummy page object for post meta update
                        $lang['page'] = $pseudo_page;

                        // Create a dummy page object for page parent update
                        $queried[ $item['guid'] ]                  = $queried[ $item['guid'] ] ?? [];
                        $queried[ $item['guid'] ][ $lang['lang'] ] = $pseudo_page;

                        // Update meta
                        $this->update_page_meta( $post_id, $item, $lang );
                    }
                }

                return $lang;
            }, $item['languages']);

            // Link connected translations
            if ( $new_translations ) {
                if ( function_exists( 'pll_save_post_translations' ) ) {
                    pll_save_post_translations( $translations );
                }
            }

            return $item;
        }, $tree);
        $progress->finish();

        return $tree;
    }

    /**
     * Update tips.
     *
     * @param array $data Tip data.
     */
    private function update_tips( $data ) {

        $tips      = array();
        $tips_data = $this->fetch_data( $this->tips_url );

        if ( ! $tips_data ) {
            $this->error = __( 'An error occured while fetching tips from backend.', 'pof_importer' );
        } else {
            $progress = $this->wp_cli_progress( 'Importing tips', count( $tips_data ) );

            foreach ( $tips_data as $id => $tip ) {
                $progress->tick();

                $parent     = $data[ $tip['post']['guid'] ][ $tip['lang'] ];
                $comment_id = null;
                if ( isset( $parent ) ) {
                    // Data for new import
                    $comment_data = array(
                        'comment_post_ID'  => $parent->ID,
                        'comment_author'   => $tip['publisher']['nickname'],
                        'comment_content'  => $tip['content'],
                        'user_id'          => $parent->post_author,
                        'comment_date'     => $tip['modified'],
                        'comment_approved' => 1,
                    );

                    // Check if comment exists
                    $args     = array(
                        'meta_key'   => 'ag_' . $tip['guid'] . '_' . $tip['lang'],
                        'meta_value' => 'true',
                    );
                    $comments = get_comments( $args );

                    if ( count( $comments ) > 0 ) {
                        if ( $tip['modified'] > $comments[0]->comment_date ) {
                            $comment_id                 = $comments[0]->comment_ID;
                            $comment_data['comment_ID'] = $comment_id;
                            wp_update_comment( $comment_data );
                            $this->data['updated'][] = $tip;
                            $this->updated++;
                        }
                    } else {
                        $comment_id = wp_new_comment( $comment_data );
                        // Force update after insert because looks like inserting won't auto approve tip
                        $comment_data['comment_ID'] = $comment_id;
                        wp_update_comment( $comment_data );
                        $this->data['created'][] = $tip;
                        $this->created++;
                    }
                    if ( $comment_id ) {
                        update_comment_meta( $comment_id, 'ag_' . $tip['guid'] . '_' . $tip['lang'], 'true' );
                        update_comment_meta( $comment_id, 'guid', $tip['guid'] );
                        update_comment_meta( $comment_id, 'title', $tip['title'] );
                        if ( ! empty( $tip['additional_content'] ) ) {
                            update_comment_meta( $comment_id, 'attachments', json_encode( $tip['additional_content'] ) );
                        }
                    }
                } else {

                    // TODO: Errorlog, if some tips not importet
                }
            }
            $progress->finish();
        }
    }

    /**
     * Update page meta from api data
     *
     * @param  int   $post_id Post id to update.
     * @param  array $item    Api item.
     * @param  array $lang    Post language data.
     */
    public function update_page_meta( $post_id, $item, $lang ) {
        $this->wp_cli_msg( 'Updating postmeta for ID: (' . $post_id . ') lang: (' . $lang['lang'] . ')' );

        $fields = [
            'field_559a2aa1bbff7' => $lang['data']['type'],                      // api_type
            'field_559a2abfbbff8' => $lang['data']['ingress'],                   // api_ingress
            'field_559a2aebbbff9' => $lang['data']['lastModified'],              // api_lastmodified
            'field_559a2d91a18a1' => $lang['data']['lang'],                      // api_lang
            'field_559a2db2a18a2' => $lang['data']['guid'],                      // api_guid
            'field_559e4a50b1f42' => $lang['details'],                           // api_url
            'field_5abca09542839' => wp_json_encode( $lang['data']['parents'] ), // api_path
            'field_55a369e3d3b3a' => [],                                         // api_images
            'field_57bffb08a4191' => [],                                         // api_attachments
            'last_modified'       => $lang['data']['lastModified'],
        ];
        foreach ( $fields as $key => $value ) {
            update_field( $key, $value, $post_id );
        }

        // images (acf repeater)
        $i = 0;
        if ( is_array( $lang['data']['images'] ) ) {
            foreach ( $lang['data']['images'] as $key => $obj ) {
                if ( is_array( $obj ) && count( $obj ) > 0 ) {
                    $i++;
                    update_sub_field( [ 'api_images', $i, 'key' ], $key, $post_id );
                    update_sub_field( [ 'api_images', $i, 'object' ], wp_json_encode( $obj ), $post_id );
                }
            }
        }
        if ( $i > 0 ) {
            // set array count, 'cause acf is lazy
            $field = [
                'name' => 'api_images',
                'key'  => static::API_IMAGES_FIELD,
            ];
            acf_update_value( $i, $post_id, $field );
        }

        // attachments (acf repeater)
        $j = 0;
        if ( ! empty( $lang['data']['additional_content'] ) ) {
            foreach ( $lang['data']['additional_content'] as $type => $content ) {
                foreach ( $content as $obj ) {
                    $j++;
                    update_sub_field( [ 'api_attachments', $j, 'type' ], $type, $post_id );
                    update_sub_field( [ 'api_attachments', $j, 'object' ], wp_json_encode( $obj ), $post_id );
                }
            }
        }
        if ( $j > 0 ) {
            // set array count, 'cause acf is lazy
            $field = [
                'name' => 'api_attachments',
                'key'  => static::API_ATTACHMENTS_FIELD,
            ];
            acf_update_value( $j, $post_id, $field );
        }

        // update page specific fields
        switch ( $lang['data']['type'] ) {
            case 'agegroup':
                if ( isset( $lang['subtaskgroup_term'] ) ) {
                    update_field( 'field_57c067cfff3cf', wp_json_encode( $lang['subtaskgroup_term'] ), $post_id ); // task_term
                }
                break;
            case 'taskgroup':
                if ( isset( $lang['subtaskgroup_term'] ) ) {
                    update_field( 'field_57c067cfff3cf', wp_json_encode( $lang['subtaskgroup_term'] ), $post_id ); // task_term
                }
                if ( isset( $lang['taskgroup_term'] ) ) {
                    update_field( 'field_57c06808e6bcf', wp_json_encode( $lang['taskgroup_term'] ), $post_id ); // task_term
                }
                if ( isset( $lang['subtask_term'] ) ) {
                    update_field( 'field_57c0680ae6bd0', wp_json_encode( $lang['subtask_term'] ), $post_id ); // task_term
                }
                $this->update_task_data( $post_id, $item['guid'], $lang['data'], true );
                break;
            case 'task':
                $this->update_task_data( $post_id, $item['guid'], $lang['data'] );
                break;
            default:
                break;
        }
    }

    /*
    *  update_task_data
    *
    *  This function updates api related meta for tasks
    *  and creates custom taxonomies based on tags.
    *
    */
    public function update_task_data( $post_id, $guid, $item, $taskgroup = false ) {

        // init counters
        $saved        = 0;
        $saved_groups = 0;

        if ( ! $taskgroup ) {
            // update acf fields
            if ( isset( $item['level'] ) ) {
                update_field( 'field_57c05f189b016', $item['level'], $post_id ); // level
            }
            if ( isset( $item['leader_tasks'] ) ) {
                update_field( 'field_57c05f519b017', $item['leader_tasks'], $post_id ); // leader_tasks
            }
            if ( isset( $item['task_term'] ) ) {
                update_field( 'field_57c0642aca6ae', wp_json_encode( $item['task_term'] ), $post_id ); // task_term
            }
        }

        // update acf repeaters to create/clear the array
        update_field( 'field_55a3b3e796fe9', null, $post_id );

        foreach ( $item['tags'] as $group_key => $group ) {
            // group has multiple value sets
            if ( ! isset( $group['name'] ) ) {
                foreach ( $group as $key => $values ) {
                    update_sub_field( array( 'tags', $saved_groups + 1, 'group', $saved + 1, 'group_key' ), $group_key, $post_id );
                    update_sub_field( array( 'tags', $saved_groups + 1, 'group', $saved + 1, 'slug' ), $values['slug'], $post_id );
                    update_sub_field( array( 'tags', $saved_groups + 1, 'group', $saved + 1, 'name' ), $values['name'], $post_id );
                    if ( isset( $values['icon'] ) ) {
                        update_sub_field( array( 'tags', $saved_groups + 1, 'group', $saved + 1, 'icon' ), $values['icon'], $post_id );
                    }
                    $saved++;
                }
            } // only a single value set
            else {
                update_sub_field( array( 'tags', $saved_groups + 1, 'group', $saved + 1, 'group_key' ), $group_key, $post_id );
                update_sub_field( array( 'tags', $saved_groups + 1, 'group', $saved + 1, 'slug' ), $group['slug'], $post_id );
                update_sub_field( array( 'tags', $saved_groups + 1, 'group', $saved + 1, 'name' ), $group['name'], $post_id );
                if ( isset( $group['icon'] ) ) {
                    update_sub_field( array( 'tags', $saved_groups + 1, 'group', $saved + 1, 'icon' ), $group['icon'], $post_id );
                }
                $saved++;
            }

            $field = array(
                'name' => 'tags_' . $saved_groups . '_group',
                'key'  => 'field_55a3b40596fea',
            );
            acf_update_value( $saved, $post_id, $field );
            $saved = 0; // init counter
            $saved_groups++;
        }

        if ( $saved_groups > 0 ) {
            $field = array(
                'name' => 'tags',
                'key'  => 'field_55a3b3e796fe9',
            );
            acf_update_value( $saved_groups, $post_id, $field );
        }
    }

    /*
    *  fetch_data
    *
    *  This function data from the POF API
    *
    */
    public function fetch_data( $url ) {
        $ctx = null;
        if ( $url === $this->tree_url ) {
            $ctx = stream_context_create(
                array(
                    'http' => array( 'timeout' => 120 ), // raise timeout value for full three fetching
                )
            );
        }
        $json = file_get_contents( $url, false, $ctx );
        if ( $json ) {
            return json_decode( $json, true ); // decode the JSON into an associative array (true)
        } else {
            return false;
        }

    }

    /*
    *  plugin_menu
    *
    *  This function creates the menu item for plugin options in admin side.
    *
    */
    public function pof_menu_init() {
        add_options_page( 'POF Importer Options', 'POF Importer', 'manage_options', 'pof_importer', array( $this, 'pof_menu' ) );
    }

    /*
    *  pof_menu
    *
    *  This function creates the options page functionality in admin side.
    *
    */
    public function pof_menu() {
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( __( 'You do not have sufficient permissions to access this page.', 'pof_importer' ) );
        }

        if ( isset( $_POST['pof_import'] ) ) {
            ini_set( 'memory_limit', '-1' );
            $this->normal_importer = true;
            $this->import();

        }

        if ( isset( $_POST['pof_import_tips'] ) ) {
            $this->tips_importer = true;
            $this->import_tips();
        }
        ?>

        <div class="pof-wrap wrap">

            <h2>POF API Importer</h2>

            <div class="card">
                <form name="pof-importer-form" method="post">
                    <input type="hidden" name="pof_import" value="1"/>
                    <h3>Import</h3>
                    <p class="pof-info"><?php _e( 'Click here to import data from the POF API. The import might take a while, please be patient.', 'pof_importer' ); ?></p>
                    <p class="pof-submit">
                        <input type="submit" name="import" class="button-primary" value="<?php _e( 'Import', 'pof_importer' ); ?>"/>
                    </p>
                </form>
            <?php if ( $this->normal_importer ) : ?>
                <?php if ( $this->error ) : ?>
                    <div class="pof-error">
                        <h4>Error!</h4>
                        <p><?php echo $this->error; ?></p>
                    </div>
                <?php elseif ( $this->success || $this->updated > 0 || $this->created > 0 ) : ?>
                    <div class="pof-imported">
                        <h4><?php _e( 'Data imported!', 'pof_importer' ); ?></h4>
                        <ul style="color:#00A0D2">
                            <li>
                            <?php
                            echo $this->created;
_e( ' pages created.' );
?>
</li>
                            <li>
                            <?php
                            echo $this->updated;
_e( ' pages updated.' );
?>
</li>
                        </ul>
                    </div>
                <?php elseif ( $this->updated === 0 && $this->created === 0 ) : ?>
                    <div class="pof-imported">
                        <h4><?php _e( 'Everything up to date', 'pof_importer' ); ?></h4>
                        <p><?php _e( 'The WordPress database already matches the POF API.', 'pof_importer' ); ?></p>
                    </div>
                <?php endif; ?>
                <?php if ( $this->debug ) : ?>
                <?php $this->execution_time(); ?>
                <?php endif; ?>
                <?php if ( $this->debug && $this->data ) : ?>
                    <h2>Data</h2>
                    <pre><?php var_dump( $this->data ); ?></pre>
                    <hr />
                    <h2>Queried</h2>
                    <pre><?php var_dump( $this->queried ); ?></pre>
                <?php endif; ?>
            <?php endif; ?>
            </div>

            <div class="card">
                <form name="pof-importer-tips-form" method="post">
                    <input type="hidden" name="pof_import_tips" value="1" />
                    <h3>Import tips</h3>
                    <p class="pof-info"><?php _e( 'Click here to import tips data from the POF API. The import might take a while, please be patient.', 'pof_importer' ); ?></p>
                    <p class="pof-submit">
                        <input type="submit" name="import-tips" class="button-primary" value="<?php _e( 'Import', 'pof_importer' ); ?>"/>
                    </p>
                </form>
            <?php if ( $this->tips_importer ) : ?>
                <?php if ( $this->error ) : ?>
                    <div class="pof-error">
                        <h4>Error!</h4>
                        <p><?php echo $this->error; ?></p>
                    </div>
                <?php elseif ( $this->success || $this->updated > 0 || $this->created > 0 ) : ?>
                    <div class="pof-imported">
                        <h4><?php _e( 'Data imported!', 'pof_importer' ); ?></h4>
                        <ul style="color:#00A0D2">
                            <li>
                            <?php
                            echo $this->created;
_e( ' tips created.' );
?>
</li>
                            <li>
                            <?php
                            echo $this->updated;
_e( ' tips updated.' );
?>
</li>
                        </ul>
                    </div>
                <?php elseif ( $this->updated === 0 && $this->created === 0 ) : ?>
                    <div class="pof-imported">
                        <h4><?php _e( 'Everything up to date', 'pof_importer' ); ?></h4>
                        <p><?php _e( 'The WordPress database already matches the POF TIPS API.', 'pof_importer' ); ?></p>
                    </div>
                <?php endif; ?>
                <?php if ( $this->debug ) : ?>
                <?php $this->execution_time(); ?>
                <?php endif; ?>
                <?php if ( $this->debug && $this->data ) : ?>
                    <?php if ( $this->data['created'] ) : ?>
                    <hr/>
                    <h2>Tips created</h2>
                    <pre><?php var_dump( $this->data['created'] ); ?></pre>
                    <?php endif; ?>
                    <?php if ( $this->data['updated'] ) : ?>
                    <hr />
                    <h2>Tips updated</h2>
                    <pre><?php var_dump( $this->data['updated'] ); ?></pre>
                    <?php endif; ?>
                <?php endif; ?>
            <?php endif; ?>
            </div>
        <?php
    }

}

POF_Importer::init();

// Require redirect handler
require 'pof-redirect-handler.php';
// Initialize redirect handler
POF_Redirect::initialize();
