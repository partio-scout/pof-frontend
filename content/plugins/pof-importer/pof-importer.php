<?php
/*
Plugin Name: POF Importer
Plugin URI: http://www.geniem.com
Description: Imports data from POF-API
Version: 0.0.2
Author: Ville Siltala, Kalle Haavisto
*/

register_activation_hook(   __FILE__, array( 'POF_Importer', 'activate' ) );
register_deactivation_hook( __FILE__, array( 'POF_Importer', 'deactivate' ) );

class POF_Importer {

    private $queried;           // pages queried from db
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

    public function __construct() {
        add_action( 'init', array( $this, 'init_plugin' ) );
        add_action( 'daily_cron',  array( $this, 'importer_cron' ) );
    }

    public function init_plugin() {

        // get API url from options
        include_once( ABSPATH . 'wp-admin/includes/plugin.php' ); // included for is_plugin_active()
        if ( is_plugin_active( 'advanced-custom-fields-pro/acf.php' ) ) {
            $this->tree_url = get_field('ohjelma-json', 'option')  . '?rand=' . mt_rand();
            $this->tips_url = get_field('tips-json', 'option') . '?rand=' . mt_rand();
            $this->site_languages = array();
            if (function_exists('pll_languages_list')) {
                $this->site_languages = pll_languages_list();
            }
        }

        // setup attributes
        $this->debug = 1;

        // Add admin menu
        if ( is_admin() ) {
            add_action( 'admin_menu', array($this, 'pof_menu_init') );
        }
    }

    // Setup cronjob for importer daily automatic execution
    public static function activate() {
        wp_schedule_event( time(), 'hourly', 'daily_cron' );
    }
    public static function deactivate() {
        wp_clear_scheduled_hook('daily_cron');
    }

    // Setup cronjob for importer daily automatic execution
    public function importer_cron () {
        ini_set('memory_limit', '-1');
        $this->tree_url = get_field('ohjelma-json', 'option');
        $this->tips_url = get_field('tips-json', 'option');
        $this->import();
        update_field( 'field_57c6b36acafd4', date("Y-m-d H:i:s"), 'option' );
        $this->import_tips();
        update_field( 'field_57c6b3e52325e', date("Y-m-d H:i:s"), 'option' );
    }

    /*
    * Helper function to calc script execution time
    */
    public function execution_time($halt = false) {
        $execution_time = microtime(true) - $this->start_time;
        echo '<b>Total Execution Time:</b> '.$execution_time.' Seconds';
        if ($halt) {
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
        $this->updated = 0;
        $this->created = 0;
        $this->start_time = microtime(true);

        // fetch the POF API tree
        $tree = $this->fetch_data( $this->tree_url );

        // tree imported
        if ( $tree ) {
            $this->success = $this->import_data( $tree['program'][0] );
        } // something went wrong
        else  {
            $this->error = __('An error occured while importing the data tree.', 'pof_importer');
        }

        // Something went wrong
        if ( isset( $this->success['error'] ) ) {
            $this->error = $this->success['error'];
            $this->error .= '<li>' . $this->success['guid'] . '</li>';
        } else {
            // Update pages
            $this->update_pages();
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
        $this->updated = 0;
        $this->created = 0;
        $this->start_time = microtime(true);

        // fetch tasks from database
        $args = [
            'posts_per_page'    => -1,
            'post_type'         => 'page',
            'post_parent'       => $post_id,
            'post_status'       => 'publish',
            'meta_key'          => 'api_type',
            'meta_value'        => 'task'
        ];
        $tasks = \DustPress\Query::get_acf_posts( $args );

        $taskData = array();
        foreach ($tasks as $key => $task) {
            $taskData[$task->fields['api_guid']][strtolower($task->fields['api_lang'])] = $task;
        }

        $this->update_tips($taskData);
    }

    /*
    *  import_data
    *
    *  This function loops recursively through the API tree and imports items from the POF API.
    *
    */
    public function import_data( $branch, $parent = 0 ) {

        // create or update current page of branch
        $success = $this->get_items( $branch, $parent );
        // an error occured
        if ( isset( $success['error'] ) ) {
            return $success;
        } elseif ( is_array( $success ) ) {
            // store items for later
            $this->data[$branch['guid']] = $success;
        }

        // loop through age groups
        if ( isset( $branch['agegroups'] ) ) {
            foreach ( $branch['agegroups'] as $ag ) {
                $success = $this->import_data( $ag, $branch['guid'] );
            }
            // an error occured
            if ( isset( $success['error'] ) ) {
                return $success;
            }
        }

        // loop through task groups
        if ( isset( $branch['taskgroups'] ) ) {
            foreach ( $branch['taskgroups'] as $ag ) {
                $success = $this->import_data( $ag, $branch['guid'] );
            }
            // an error occured
            if ( isset( $success['error'] ) ) {
                return $success;
            }
        }

        // loop through tasks
        if ( isset( $branch['tasks'] ) ) {
            foreach ( $branch['tasks'] as $ag ) {
                $success = $this->import_data( $ag, $branch['guid'] );
            }
            // an error occured
            if ( isset( $success['error'] ) ) {
                return $success;
            }
        }

        // all good
        return $success;

    }

    /*
    *  get_items
    *
    *  This function loads items from api and compares them to wp data.
    *  Modified pages/json data are returned for later use.
    *
    */
    private function get_items( $branch, $parent ) {
        $guid               = $branch['guid'];
        $languages          = [];
        $details            = [];
        $pages              = [];
        $api_data           = [];
        $count              = 0;

        // loop languages and get the item data
        foreach ( $branch['languages'] as $item ) {

            $languages[strtoupper($item['lang'])] = [ 'url' => $item['details'], 'modified' => $item['lastModified'] ];
        }

        // query page by api guid
        $args = array(
            'meta_key' => 'ag_'.$guid,
            'meta_value' => 'true',
            'post_type' => 'page',
            'post_status' => 'any',
            'posts_per_page' => -1
        );
        $posts = get_posts($args);
        // index array by language
        foreach ($posts as $p) {
            $lang = strtoupper( get_post_meta( $p->ID, 'api_lang', true ) );
            $pages[$lang] = $p;
        }
        // page found
        if ( count( $pages) > 0 ) {
            // store pages for later
            $this->queried[$guid] = $pages;
            // loop through pages and check if modified
            foreach ( $languages as $lang => $leaf ) {
                if ( isset( $pages[$lang] ) ) {
                    $modified = get_field( 'api_lastmodified', $pages[$lang]->ID );

                    if ( $languages[$lang]['modified'] > $modified ) {
                        $languages[$lang]['update'] = true;
                    }
                    else if ( $this->queried[$parent][$lang]->ID !== $pages[$lang]->post_parent && ! is_null($this->queried[$parent][$lang]->ID) ) {
                        $languages[$lang]['update'] = true;
                    }

                } else {
                    $languages[$lang]['new_lang'] = true;
                }
            }
        } else {
            $create = true;
        }

        // fetch details if create/update
        foreach ( $languages as $lang => $leaf ) {
            if ( $create || isset( $leaf['update'] ) || isset( $leaf['new_lang'] ) ) {
                $d = $this->fetch_data( $leaf['url'] );

                // check if an error occurred
                if ( ! is_array( $d ) ) {
                    return [
                        'error' => __('Data could not be loaded from:<br/>', 'pof_importer'),
                        'guid'  => $leaf['url']
                    ];
                }

                $d['url'] = $leaf['url']; // store url

                $details[$lang] = $d;
                $count++;

            }
        }

        // nothing loaded -> return
        if ( $count === 0 ) return;

        // details ok
        if ( count( $details ) > 0 && ! array_search( false, $details ) ) {
            foreach ( $details as $lang => $data ) {

                // has a $parent
                if ( $parent !== 0 ) {
                    $data['parent'] = $parent;
                }

                // store by language index
                $api_data[$lang] = $data;
            }
        } else {
            return [
                'error' => __('An error occured while importing data for guid:<br/>', 'pof_importer'),
                'guid'  => $guid
            ]; // something went wrong
        }
        return $api_data;
    }

    /*
    *  update_pages
    *
    *  This function updates/creates pages
    *
    */
    private function update_pages() {
        // loop through loaded data
        if ( is_array( $this->data ) ) {
            foreach ( $this->data as $guid => $items ) {
                $polyLangs = array();
                $redirect_cache = array();
                foreach ( $items as $item ) {
                    $args = array();

                    // Langversion found, add existing page specific args
                    if (isset($this->queried[$guid][$item['lang']])) {
                        $page = $this->queried[$guid][$item['lang']];

                        $args['ID']         = $page->ID;
                        $args['menu_order'] = $page->menu_order;
                        $args['post_name']  = wp_unique_post_slug( sanitize_title( $item['title'] ), $page->ID, 'publish', 'page', $parent_id );
                        // Get current page relative url
                        $old_slug = wp_make_link_relative( get_permalink( $page->ID ));

                    } else {
                        $args['post_name'] = wp_unique_post_slug( sanitize_title( $item['title'] ), $post_id, 'publish', 'page', $parent_id );
                    }

                    // has a parent
                    if ( isset( $item['parent'] ) ) {
                        $parent_guid = $item['parent'];
                        $parent_id = $this->queried[$parent_guid][$item['lang']]->ID;
                    } else {
                        $parent_id = 0;
                    }

                    // Default values for page creation/updation
                    $args['post_status']   = 'publish';
                    $args['post_type']     = 'page';
                    $args['post_title']    = $item['title'];
                    $args['post_parent']   = $parent_id;
                    $args['post_content']  = isset($item['content']) ? $item['content'] : '';
                    $args['page_template'] = 'models/page-' . $item['type'] . '.php';

                    
                    // Create the post
                    $post_id = wp_insert_post( $args );

                    if ( ! is_wp_error( $post_id ) && $post_id !== 0 && isset( $old_slug ) ) {
                        $new_slug = wp_make_link_relative( get_permalink( $post_id ));
                        if ( $new_slug !== $old_slug ) {
                            $redirect_cache[ $old_slug ] = $new_slug;
                        }
                    }



                    $this->update_page_meta( $post_id, $guid, $item );
                    if (in_array(strtolower($item['lang']), $this->site_languages)) {
                        if (function_exists('pll_set_post_language')) {
                            pll_set_post_language($post_id, strtolower($item['lang']));
                        }
                        $polyLangs[strtolower($item['lang'])] = $post_id;
                    }

                    isset($args['ID']) ? $this->updated++ : $this->created++;
                }

                // Redirect changes have been made, update cache.
                if( ! empty ( $redirect_cache ) ) {
                    POF_Redirect::update_cache( $redirect_cache );
                }

            }
            // Link program page different languages for polylang
            if (!empty($polyLangs)) {
                if (function_exists('pll_save_post_translations')) {
                    pll_save_post_translations($polyLangs);
                }
            }
        }
    }

    private function update_tips($data) {

        $tips = array();
        $tips_data = $this->fetch_data( $this->tips_url );

        if ( !$tips_data ) {
            $this->error = __('An error occured while fetching tips from backend.', 'pof_importer');
        } else {
            foreach ($tips_data as $id => $tip) {
                $parent = $data[$tip['post']['guid']][$tip['lang']];
                $comment_id = null;
                if (isset($parent)) {
                    // Data for new import
                    $comment_data = array(
                        'comment_post_ID'   => $parent->ID,
                        'comment_author'    => $tip['publisher']['nickname'],
                        'comment_content'   => $tip['content'],
                        'user_id'           => $parent->post_author,
                        'comment_date'      => $tip['modified'],
                        'comment_approved'  => 1,
                    );

                    // Check if comment exists
                    $args = array(
                        'meta_key' => 'ag_'.$tip['guid'].'_'.$tip['lang'],
                        'meta_value' => 'true',
                    );
                    $comments = get_comments( $args );

                    if (count($comments) > 0) {
                        if ( $tip['modified'] > $comments[0]->comment_date ) {
                            $comment_id = $comments[0]->comment_ID;
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
                    if ($comment_id) {
                        update_comment_meta( $comment_id, 'ag_'.$tip['guid'].'_'.$tip['lang'], 'true' );
                        update_comment_meta( $comment_id, 'title', $tip['title'] );
                        if (!empty( $tip['additional_content'] )) {
                            update_comment_meta( $comment_id, 'attachments', json_encode($tip['additional_content']) );
                        }
                    }
                }
                else {

                    // TODO: Errorlog, if some tips not importet
                }
            }
        }
    }

    /*
    *  update_page_meta
    *
    *  This function updates api related meta for pages.
    *
    */
    public function update_page_meta( $post_id, $guid, $item ) {
        // update acf fields
        update_field( 'field_559a2aa1bbff7', $item['type'], $post_id ); // api_type
        update_field( 'field_559a2abfbbff8', $item['ingress'], $post_id ); // api_ingress
        update_field( 'field_559a2aebbbff9', $item['lastModified'], $post_id ); // last_modified
        update_field( 'field_559a2d91a18a1', $item['lang'], $post_id ); // api_lang
        update_field( 'field_559a2db2a18a2', $guid, $post_id ); // api_guid
        update_field( 'field_559e4a50b1f42', $item['url'], $post_id ); // api_url
        update_post_meta($post_id, 'ag_'.$guid, 'true');

        // images (acf repeater)
        $i = 1;
        $saved = 0;
        // update acf repeater field to create/clear the array
        update_field( 'field_55a369e3d3b3a', null, $post_id );
        if ( is_array( $item['images'] ) ) {
            foreach ( $item['images'] as $key => $obj ) {
                if ( is_array( $obj ) && count( $obj ) > 0 ) {
                    update_sub_field( array( 'api_images', $i, 'key' ), $key, $post_id );
                    update_sub_field( array( 'api_images', $i, 'object' ), json_encode( $obj ), $post_id );
                    $saved++;
                }
                $i++;
            }
        }

        // set array count, 'cause acf is lazy
        if ( $saved > 0 ) {
            $field = array(
                'name'  => 'api_images',
                'key'   => 'field_55a369e3d3b3a',
            );
            acf_update_value( $saved, $post_id, $field );
        }

        // attachments (acf repeater)
        $j = 1;
        $attachments = 0;
        // update acf repeater field to create/clear the array
        update_field( 'field_57bffb08a4191', null, $post_id );
        if ( !empty( $item['additional_content'] )) {
            foreach ( $item['additional_content'] as $type => $content ) {
                foreach ($content as $obj) {
                    update_sub_field( array( 'api_attachments', $j, 'type' ), $type, $post_id );
                    update_sub_field( array( 'api_attachments', $j, 'object'), json_encode( $obj ), $post_id );
                    $attachments++;
                    $j++;
                }
            }
        }

        // set array count, 'cause acf is lazy
        if ( $attachments > 0 ) {
            $field = array(
                'name'  => 'api_attachments',
                'key'   => 'field_57bffb08a4191',
            );
            acf_update_value( $attachments, $post_id, $field );
        }

        // update page specific fields
        switch ( $item['type'] ) {
            case 'agegroup':
                if (isset($item['subtaskgroup_term'])) {
                    update_field( 'field_57c067cfff3cf', json_encode($item['subtaskgroup_term']), $post_id ); // task_term
                }
                break;
            case 'taskgroup':
                if (isset($item['subtaskgroup_term'])) {
                    update_field( 'field_57c067cfff3cf', json_encode($item['subtaskgroup_term']), $post_id ); // task_term
                }
                if (isset($item['taskgroup_term'])) {
                    update_field( 'field_57c06808e6bcf', json_encode($item['taskgroup_term']), $post_id ); // task_term
                }
                if (isset($item['subtask_term'])) {
                    update_field( 'field_57c0680ae6bd0', json_encode($item['subtask_term']), $post_id ); // task_term
                }
                $this->update_task_data( $post_id, $guid, $item, true );
                break;
            case 'task':
                $this->update_task_data( $post_id, $guid, $item );
                break;
            default:
                # code...
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
        $saved = 0;
        $saved_groups = 0;

        if (!$taskgroup) {
            // update acf fields
            if (isset($item['level'])) {
                update_field( 'field_57c05f189b016', $item['level'], $post_id ); // level
            }
            if (isset($item['leader_tasks'])) {
                update_field( 'field_57c05f519b017', $item['leader_tasks'], $post_id ); // leader_tasks
            }
            if (isset($item['task_term'])) {
                update_field( 'field_57c0642aca6ae', json_encode($item['task_term']), $post_id ); // task_term
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
                'name'  => 'tags_' . $saved_groups . '_group',
                'key'   => 'field_55a3b40596fea',
            );
            acf_update_value( $saved, $post_id, $field );
            $saved = 0; // init counter
            $saved_groups++;
        }

        if ( $saved_groups > 0 ) {
            $field = array(
                'name'  => 'tags',
                'key'   => 'field_55a3b3e796fe9',
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
        if ($url === $this->tree_url) {
            $ctx = stream_context_create(
                array(
                    'http' => array('timeout' => 120) //raise timeout value for full three fetching
                )
            );
        }
        $json = file_get_contents($url, false, $ctx);
        if ( $json ) {
            return json_decode( $json, true ); // decode the JSON into an associative array (true)
        }
        else {
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
        add_options_page( 'POF Importer Options', 'POF Importer', 'manage_options', 'pof_importer', array( $this, 'pof_menu') );
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
            ini_set('memory_limit', '-1');
            $this->normal_importer = true;
            $this->import();

        }

        if ( isset ( $_POST['pof_import_tips'] ) ) {
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
                    <p class="pof-info"><?php _e('Click here to import data from the POF API. The import might take a while, please be patient.', 'pof_importer'); ?></p>
                    <p class="pof-submit">
                        <input type="submit" name="import" class="button-primary" value="<?php _e('Import', 'pof_importer'); ?>"/>
                    </p>
                </form>
            <?php if ( $this->normal_importer ): ?>
                <?php if ( $this->error ) : ?>
                    <div class="pof-error">
                        <h4>Error!</h4>
                        <p><?php echo $this->error; ?></p>
                    </div>
                <?php elseif ( $this->success || $this->updated > 0 || $this->created > 0 ) : ?>
                    <div class="pof-imported">
                        <h4><?php _e('Data imported!', 'pof_importer'); ?></h4>
                        <ul style="color:#00A0D2">
                            <li><?php echo $this->created; _e(' pages created.'); ?></li>
                            <li><?php echo $this->updated; _e(' pages updated.'); ?></li>
                        </ul>
                    </div>
                <?php elseif ( $this->updated === 0  && $this->created === 0): ?>
                    <div class="pof-imported">
                        <h4><?php _e('Everything up to date', 'pof_importer'); ?></h4>
                        <p><?php _e('The WordPress database already matches the POF API.', 'pof_importer'); ?></p>
                    </div>
                <?php endif; ?>
                <?php if ($this->debug): ?>
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
                    <p class="pof-info"><?php _e('Click here to import tips data from the POF API. The import might take a while, please be patient.', 'pof_importer'); ?></p>
                    <p class="pof-submit">
                        <input type="submit" name="import-tips" class="button-primary" value="<?php _e('Import', 'pof_importer'); ?>"/>
                    </p>
                </form>
            <?php if ( $this->tips_importer ): ?>
                <?php if ( $this->error ) : ?>
                    <div class="pof-error">
                        <h4>Error!</h4>
                        <p><?php echo $this->error; ?></p>
                    </div>
                <?php elseif ( $this->success || $this->updated > 0 || $this->created > 0 ) : ?>
                    <div class="pof-imported">
                        <h4><?php _e('Data imported!', 'pof_importer'); ?></h4>
                        <ul style="color:#00A0D2">
                            <li><?php echo $this->created; _e(' tips created.'); ?></li>
                            <li><?php echo $this->updated; _e(' tips updated.'); ?></li>
                        </ul>
                    </div>
                <?php elseif ( $this->updated === 0  && $this->created === 0): ?>
                    <div class="pof-imported">
                        <h4><?php _e('Everything up to date', 'pof_importer'); ?></h4>
                        <p><?php _e('The WordPress database already matches the POF TIPS API.', 'pof_importer'); ?></p>
                    </div>
                <?php endif; ?>
                <?php if ($this->debug): ?>
                <?php $this->execution_time(); ?>
                <?php endif; ?>
                <?php if ( $this->debug && $this->data ) : ?>
                    <?php if ($this->data['created']): ?>
                    <hr/>
                    <h2>Tips created</h2>
                    <pre><?php var_dump( $this->data['created'] ); ?></pre>
                    <?php endif; ?>
                    <?php if ($this->data['updated']): ?>
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

$importer = new POF_Importer();

// Require redirect handler
require 'pof-redirect-handler.php';
// Initialize redirect handler
POF_Redirect::initialize();
