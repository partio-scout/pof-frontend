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

class POF_Importer {

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
            \WP_CLI::add_command( 'pof import', [ $this, 'importer_cron' ] );
        }
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

    public function init_plugin() {

        // get API url from options
        include_once ABSPATH . 'wp-admin/includes/plugin.php'; // included for is_plugin_active()
        if ( is_plugin_active( 'advanced-custom-fields-pro/acf.php' ) ) {
            $this->tree_url       = get_field( 'ohjelma-json', 'option' ) . '?rand=' . mt_rand();
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
        $this->tree_url = get_field( 'ohjelma-json', 'option' );
        $this->tips_url = get_field( 'tips-json', 'option' );
        $this->import();
        update_field( 'field_57c6b36acafd4', date( 'Y-m-d H:i:s' ), 'option' );
        $this->import_tips();
        update_field( 'field_57c6b3e52325e', date( 'Y-m-d H:i:s' ), 'option' );
        $this->wp_cli_msg( 'Import finished in: ' . round( microtime( true ) - $start, 4 ) . 's' );
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
            $this->update_metas( $tree );
        }
    }

    /*
    * import tips data
    *
    * This function starts the tips importing
    *
    */
    public function import_tips() {
        $this->wp_cli_msg( 'Importing tips' );

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
     * Flatten api program tree into a single array
     *
     * @param  array $tree Api program tree.
     * @return array       Flattened tree.
     */
    protected function flatten_tree( $tree ) {
        $flattened = [];
        $start     = microtime( true );

        /**
         * Recursively add api item to flattened array
         *
         * @param array  $item      Item to add.
         * @param array  $flattened Array to gather items to.
         * @param string $parent    Parent guid.
         */
        function add_to_flattened( $item, &$flattened, $parent = null ) {
            $item['parent']  = $parent;
            $items_to_search = [
                'taskgroups',
                'tasks',
                'agegroups',
            ];

            foreach ( $items_to_search as $key ) {
                if ( array_key_exists( $key, $item ) ) {
                    foreach ( $item[ $key ] as $new_item ) {
                        add_to_flattened( $new_item, $flattened, $item['guid'] );
                    }

                    // Remove unnecessary data from item
                    unset( $item[ $key ] );
                }
            }

            $flattened[ $item['guid'] ] = $item;

        }
        add_to_flattened( $tree, $flattened );

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

            // Collect only matching posts
            $pages = array_filter( $posts, function( $page ) use ( $item ) {
                return ( ! empty( $page->fields ) && $page->fields['api_guid'] === $item['guid'] );
            });

            // Update languages with necessary data
            $item['languages'] = array_map(function( $lang ) use ( $pages, $importer ) {
                // Mark posts to update
                foreach ( $pages as $page ) {
                    if ( $lang['lang'] === strtolower( $page->fields['api_lang'] ) ) {

                        // Store the page to possibly update it or translations later
                        $lang['page'] = $page;

                        // Mark post meta for update
                        if (
                            strtotime( $page->fields['api_lastmodified'] ) <
                            strtotime( $lang['lastModified'] )
                        ) {
                            // Mark the language for updating
                            $lang['update_meta'] = true;
                        }

                        // Mark post data for update
                        if (
                            strtotime( $page->post_modified ) <
                            strtotime( $lang['lastModified'] )
                        ) {
                            // Mark the language for updating
                            $lang['update_page'] = true;
                        }
                    }
                }

                return $lang;
            }, $item['languages'] );

            // Update progressbar
            $progress->tick();

            return $item;
        }, $tree);
        // Finish progressbar
        $progress->finish();

        // Reduce tree to only those that need to be updated
        $tree = array_filter( $tree, function( $item ) {
            foreach ( $item['languages'] as $lang ) {
                if ( $lang['update_meta'] || $lang['update_page'] ) {
                    return true;
                }
            }

            return false;
        });
        $this->wp_cli_msg( 'Items to update: ' . count( $tree ) );

        return $tree;
    }

    /**
     * Get new data for languages
     *
     * @param  array $tree Api data tree.
     * @return array       Modified $tree.
     */
    private function retrieve_new_data( $tree ) {

        $pool_size = 20; // Max concurrent requests
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
                if ( $lang['update_meta'] || $lang['update_page'] ) {
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

                $progress->tick();
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
                if ( $lang['update_meta'] || $lang['update_page'] ) {
                    $lang['data'] = json_decode( $done[ $lang['detail'] ]->body, true ) ?? null;
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
    private function update_pages( &$tree ) {

        // Create wp cli progress bar
        $progress = $this->wp_cli_progress( 'Importing page content', count( $tree ) );
        $start    = microtime( true );

        // Update/Create invidual pages
        foreach ( $tree as &$item ) {

            // Collect connected posts
            $translations = [];
            foreach ( $item['languages'] as $lang ) {
                if (
                    (
                        ! $lang['update_meta'] &&
                        ! $lang['update_page']
                    ) &&
                    $lang['page']
                ) {
                    $translations[ $lang['lang'] ] = $lang['page']->ID;
                    break;
                }
            }
            $new_translations = false;

            // Update translations
            foreach ( $item['languages'] as &$lang ) {
                if ( $lang['update_page'] && $lang['data'] ) {

                    // Post object params
                    $args = [
                        'post_title'    => sanitize_title( $lang['title'] ),
                        'menu_order'    => $lang['order'],
                        'post_status'   => 'publish',
                        'post_type'     => 'page',
                        'post_title'    => $item['title'],
                        'post_content'  => $lang['data']['content'],
                        'page_template' => 'models/page-' . $lang['data']['type'] . '.php',
                        'post_modified' => $lang['lastModified'],
                    ];

                    // If item has parent link to it
                    if ( $item['parent'] ) {
                        $parent_id = $this->queried[ $item['guid'] ][ $lang['lang'] ]->ID;
                        if ( ! empty( $parent_id ) ) {
                            $args['post_parent'] = $parent_id;
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

                        // If no changes were made collect page id just in case anyways
                        if ( ! $post_id ) {
                            $post_id = $lang['page']->ID;
                        }
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

                        // Create dummy page object for post meta update
                        $lang['page'] = (object) [
                            'ID' => $post_id,
                        ];
                    }
                }
            }

            // Link connected translations
            if ( $new_translations ) {
                if ( function_exists( 'pll_save_post_translations' ) ) {
                    pll_save_post_translations( $translations );
                }
            }

            $progress->tick();
        }
        $progress->finish();

        $this->wp_cli_success( 'Updated pages in: ' . round( microtime( true ) - $start, 4 ) . 's' );
    }

    /**
     * Update page meta data
     *
     * @param array $tree Api data.
     */
    private function update_metas( $tree ) {

        // Create wp cli progress bar
        $progress = $this->wp_cli_progress( 'Importing postmeta', count( $tree ) );
        $start    = microtime( true );

        // Update meta data
        foreach ( $tree as $item ) {

            // Update translations
            foreach ( $item['languages'] as $lang ) {
                if ( $lang['update_meta'] && $lang['data'] ) {
                    // Update meta fields
                    $this->update_page_meta( $lang['page']->ID, $item, $lang );
                }
            }
            $progress->tick();
        }
        $progress->finish();

        $this->wp_cli_success( 'Updated postmeta in: ' . round( microtime( true ) - $start, 4 ) . 's' );
    }

    private function update_tips( $data ) {

        $tips      = array();
        $tips_data = $this->fetch_data( $this->tips_url );

        if ( ! $tips_data ) {
            $this->error = __( 'An error occured while fetching tips from backend.', 'pof_importer' );
        } else {
            $progress = $this->wp_cli_progress( 'Importing tips', count( $tips_data ) );

            foreach ( $tips_data as $id => $tip ) {
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

                $progress->tick();
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
        // update acf fields
        update_field( 'field_559a2aa1bbff7', $lang['data']['type'], $post_id ); // api_type
        update_field( 'field_559a2abfbbff8', $lang['data']['ingress'], $post_id ); // api_ingress
        update_field( 'field_559a2aebbbff9', $lang['data']['lastModified'], $post_id ); // last_modified
        update_field( 'field_559a2d91a18a1', $lang['data']['lang'], $post_id ); // api_lang
        update_field( 'field_559a2db2a18a2', $lang['data']['guid'], $post_id ); // api_guid
        update_field( 'field_559e4a50b1f42', $lang['details'], $post_id ); // api_url
        update_field( 'field_5abca09542839', wp_json_encode( $lang['data']['parents'] ), $post_id ); // api_path

        // update acf repeater field to create/clear the array
        update_field( 'field_55a369e3d3b3a', null, $post_id );
        if ( is_array( $lang['images'] ) ) {
            foreach ( $lang['images'] as $key => $obj ) {
                if ( is_array( $obj ) && count( $obj ) > 0 ) {
                    update_sub_field( array( 'api_images', $i, 'key' ), $key, $post_id );
                    update_sub_field( array( 'api_images', $i, 'object' ), wp_json_encode( $obj ), $post_id );
                    $saved++;
                }
                $i++;
            }
        }

        // set array count, 'cause acf is lazy
        if ( $saved > 0 ) {
            $field = array(
                'name' => 'api_images',
                'key'  => 'field_55a369e3d3b3a',
            );
            acf_update_value( $saved, $post_id, $field );
        }

        // attachments (acf repeater)
        $j           = 1;
        $attachments = 0;
        // update acf repeater field to create/clear the array
        update_field( 'field_57bffb08a4191', null, $post_id );
        if ( ! empty( $lang['additional_content'] ) ) {
            foreach ( $lang['additional_content'] as $type => $content ) {
                foreach ( $content as $obj ) {
                    update_sub_field( array( 'api_attachments', $j, 'type' ), $type, $post_id );
                    update_sub_field( array( 'api_attachments', $j, 'object' ), wp_json_encode( $obj ), $post_id );
                    $attachments++;
                    $j++;
                }
            }
        }

        // set array count, 'cause acf is lazy
        if ( $attachments > 0 ) {
            $field = array(
                'name' => 'api_attachments',
                'key'  => 'field_57bffb08a4191',
            );
            acf_update_value( $attachments, $post_id, $field );
        }

        // update page specific fields
        switch ( $lang['type'] ) {
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
                $this->update_task_data( $post_id, $item['guid'], $lang, true );
                break;
            case 'task':
                $this->update_task_data( $post_id, $item['guid'], $lang );
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
