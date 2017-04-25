<?php


function enable_duplicate_comments_preprocess_comment($comment_data)
{
    //add some random content to comment to keep dupe checker from finding it
    $random = md5(time());  
    $comment_data['comment_content'] .= "disabledupes{" . $random . "}disabledupes";    
    
    return $comment_data;
}
add_filter('preprocess_comment', 'enable_duplicate_comments_preprocess_comment');

function enable_duplicate_comments_comment_post($comment_id)
{
    global $wpdb;
    
    //remove the random content
    $comment_content = $wpdb->get_var("SELECT comment_content FROM $wpdb->comments WHERE comment_ID = '$comment_id' LIMIT 1");  
    $comment_content = preg_replace("/disabledupes{.*}disabledupes/", "", $comment_content);
    $wpdb->query("UPDATE $wpdb->comments SET comment_content = '" . $wpdb->escape($comment_content) . "' WHERE comment_ID = '$comment_id' LIMIT 1");
        
}

add_action('comment_post', 'enable_duplicate_comments_comment_post');

// hide generator
remove_action('wp_head', 'wp_generator');

// fix ADMIN urls if any to PUB site
function change_admin_urls( $content ) {
  // Process content here
  return str_replace(WP_HOME, WP_PUB_SITEURL, $content);
}
add_filter( 'content_save_pre', 'change_admin_urls', 10, 1 );

/** Add options page **/

if( function_exists("acf_add_options_page") ) {
	acf_add_options_page(array(
		"page_title" => __("CDN-asetukset"),
		"menu_title" => __('CDN-asetukset'),
		"menu_slug" => "cdnasetukset",
	));
	acf_add_options_page(array(
		"page_title" => __("Sivuston asetukset"),
		"menu_title" => __('Sivuston asetukset'),
		"menu_slug" => "sivuston-asetukset",
	));
}

/** Used by Geniem CDN to get the urls from site options **/

function gen_cdn_fill_url_array() {
	if( !is_admin() ) {
		if(function_exists("get_field") && true == get_field("cdn_on","option")) {
			if(get_field("cdn_urls","option")) {
				while(has_sub_field("cdn_urls","option")) {
					$GLOBALS["gen_cdn_urls"][] = get_sub_field("url");
				}
			}
		}
	}
}

add_action("after_setup_theme", "gen_cdn_fill_url_array");

/** Fix the url for uploaded media to be the default url for cdn,
 *	which is defined in the wp-config.php.
 */

function gen_correct_upload_url() {
  return WP_CONTENT_BASE_URL . '/content/uploads';
}

add_filter( 'pre_option_upload_url_path', 'gen_correct_upload_url' );

function gen_add_upload_url_init() {
	if ( !get_option('upload_url_path') ) {
		register_setting( 'media',	'upload_url_path',	'esc_url'  );
		update_option('upload_url_path', WP_CONTENT_BASE_URL . '/content/uploads');
	}
}

add_action( 'load-options-media.php',	'gen_add_upload_url_init' );
add_action( 'load-options.php',			'gen_add_upload_url_init' );

/** Automatically save json file for deploying the custom fields.
 *  Only performed on local side. Otherwise hide the ACF menu
 *  from admin side.
 */
 
if(true === WP_LOCAL_DEV) {
	function acf_json_save_point( $path ) {
	    $path = get_stylesheet_directory() . "/acf-json";
	    return $path;
	}

	add_filter('acf/settings/save_json', 'acf_json_save_point');
}
else {
	add_filter('acf/settings/show_admin', '__return_false');
}

// correct all public urls to point to public side
function fix_home_url($url, $path, $orig_scheme, $blog_id) {
    $new_url = str_replace(WP_HOME, WP_PUB_SITEURL, $url);
    return $new_url;
}
add_filter( 'home_url', 'fix_home_url', 1, 4 );

// correct all admin urls to point to admin side
function fix_admin_url($url, $path, $blog_id) {
    return str_replace(WP_HOME, WP_ADMIN_URL, $url);
}
add_filter( 'admin_url', 'fix_admin_url', 1, 3 );

// Filter by Language
add_action( 'restrict_manage_posts', 'admin_posts_filter_manage_by_lang' );
function admin_posts_filter_manage_by_lang(){
    $type = 'page';
    if (isset($_GET['post_type'])) { $type = $_GET['post_type']; }
 
    //only add filter to post type you want
    if ('page' == $type){
        //change this to the list of values you want to show
        //in 'label' => 'value' format
        $langs = array(
            'FI' => 'FI',
            'SV' => 'SV',
            'EN' => 'EN'
        );
        echo "<select name=\"page_lang\"><option value=\"\">Language</option>";
                $current_lang = isset($_GET['page_lang'])? $_GET['page_lang']:'';
                foreach ($langs as $label => $value) {
                        printf (
                                '<option value="%s"%s>%s</option>',
                                $value,
                                $value == $current_lang? ' selected="selected"':'',
                                $label
                        );
                }
                echo "</select>";
    }
}
 
add_filter( 'parse_query', 'admin_posts_filter_by_lang' );
function admin_posts_filter_by_lang( $query ){
    global $pagenow;
    $type = 'page';
    if (isset($_GET['post_type'])) { $type = $_GET['post_type']; }
 
    if ( 'page' == $type && is_admin() && $pagenow=='edit.php' && isset($_GET['page_lang']) && $_GET['page_lang'] != '') {
        $query->query_vars['meta_key'] = 'api_lang';
        $query->query_vars['meta_value'] = $_GET['page_lang'];
    }
}

// Filter by api type
add_action( 'restrict_manage_posts', 'admin_posts_filter_manage_by_api_type' );
function admin_posts_filter_manage_by_api_type(){
    $type = 'page';
    if (isset($_GET['post_type'])) { $type = $_GET['post_type']; }
 
    //only add filter to post type you want
    if ('page' == $type) {
        //change this to the list of values you want to show
        //in 'label' => 'value' format
        $api_types = array(
            'Frontpage' => 'models/page-frontpage.php',
            'General'   => 'models/page-general.php',
            'Program'   => 'models/page-program.php',
            'Agegroup'  => 'models/page-agegroup.php',
            'Taskgroup' => 'models/page-taskgroup.php',
            'Task'      => 'models/page-task.php'
        );
        echo "<select name=\"_wp_page_template\"><option value=\"\">Sivupohja</option>";
                $current_api_type = isset($_GET['_wp_page_template'])? $_GET['_wp_page_template']:'';
                foreach ($api_types as $label => $value) {
                        printf (
                                '<option value="%s"%s>%s</option>',
                                $value,
                                $value == $current_api_type ? ' selected="selected"':'',
                                $label
                        );
                }
                echo "</select>";
    }
}
 
add_filter( 'parse_query', 'admin_posts_filter_by_api_type' );
function admin_posts_filter_by_api_type( $query ){
    global $pagenow;
    $type = 'page';
    if (isset($_GET['post_type'])) { $type = $_GET['post_type']; }
 
    if ( 'page' == $type && is_admin() && $pagenow=='edit.php' && isset($_GET['_wp_page_template']) && $_GET['_wp_page_template'] != '') {
        $query->query_vars['meta_key'] = '_wp_page_template';
        $query->query_vars['meta_value'] = $_GET['_wp_page_template'];
    }
}

/*
 *
 * GLOBAL HELPER FUNCTIONS
 *
 */

function loki( $var ) {
	ob_start();
	var_dump( $var );
	error_log( ob_get_clean() );
}

function ajax_status($status, $message, $data = NULL) {
	$response = array (
		'status' => $status, // 'success' || 'error'
		'message' => $message, // message for js
		'data' => $data // data for js
	);
	$output = json_encode($response);

	exit($output);	 
}
