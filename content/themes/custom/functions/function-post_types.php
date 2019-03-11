<?php
function cpt_header_images() {
  $labels = array(
    'name'               => __( 'Header images', 'pof' ),
    'singular_name'      => __( 'Header image', 'pof' ),
    'add_new'            => __( 'Add new', 'pof' ),
    'add_new_item'       => __( 'Add new header image', 'pof' ),
    'edit_item'          => __( 'Edit header image', 'pof' ),
    'new_item'           => __( 'New header image', 'pof' ),
    'all_items'          => __( 'All header images', 'pof' ),
    'view_item'          => __( 'View header image', 'pof' ),
    'search_items'       => __( 'Find header images', 'pof' ),
    'not_found'          => __( 'No header images found', 'pof' ),
    'not_found_in_trash' => __( 'No header images in trash', 'pof' ),
    'parent_item_colon'  => '',
    'menu_name'          => 'Header images'
  );
  $args = array(
    'labels'        => $labels,
    'description'   => 'Yläkuvat',
    'public'        => true,
    'has_archive'   => false,
  );
  register_post_type( 'partio-ylakuvat', $args );
}
add_action( 'init', 'cpt_header_images' );

/**
 * Register tips post type.
 */
function register_pof_tips() {

    $labels = array(
        'name'                  => _x( 'Vinkit', 'Post Type General Name', 'pof' ),
        'singular_name'         => _x( 'Vinkki', 'Post Type Singular Name', 'pof' ),
        'menu_name'             => __( 'Tips', 'pof' ),
        'name_admin_bar'        => __( 'Tips', 'pof' ),
        'archives'              => __( 'Tip Archives', 'pof' ),
        'attributes'            => __( 'Tip Attributes', 'pof' ),
        'parent_item_colon'     => __( 'Parent Tip:', 'pof' ),
        'all_items'             => __( 'All Tips', 'pof' ),
        'add_new_item'          => __( 'Add New Tip', 'pof' ),
        'add_new'               => __( 'Add New', 'pof' ),
        'new_item'              => __( 'New Tip', 'pof' ),
        'edit_item'             => __( 'Edit Tip', 'pof' ),
        'update_item'           => __( 'Update Tip', 'pof' ),
        'view_item'             => __( 'View Tip', 'pof' ),
        'view_items'            => __( 'View Tips', 'pof' ),
        'search_items'          => __( 'Search Tip', 'pof' ),
        'not_found'             => __( 'Not found', 'pof' ),
        'not_found_in_trash'    => __( 'Not found in Trash', 'pof' ),
        'featured_image'        => __( 'Featured Image', 'pof' ),
        'set_featured_image'    => __( 'Set featured image', 'pof' ),
        'remove_featured_image' => __( 'Remove featured image', 'pof' ),
        'use_featured_image'    => __( 'Use as featured image', 'pof' ),
        'insert_into_item'      => __( 'Insert into item', 'pof' ),
        'uploaded_to_this_item' => __( 'Uploaded to this item', 'pof' ),
        'items_list'            => __( 'Tips list', 'pof' ),
        'items_list_navigation' => __( 'Tips list navigation', 'pof' ),
        'filter_items_list'     => __( 'Filter items list', 'pof' ),
    );
    $args = array(
        'label'                 => __( 'Vinkki', 'pof' ),
        'description'           => __( 'Post Type Description', 'pof' ),
        'labels'                => $labels,
        'supports'              => array( 'title', 'editor' ),
        'hierarchical'          => false,
        'public'                => true,
        'show_ui'               => true,
        'show_in_menu'          => true,
        'menu_position'         => 5,
        'menu_icon'             => 'dashicons-pressthis',
        'show_in_admin_bar'     => false,
        'show_in_nav_menus'     => true,
        'can_export'            => false,
        'has_archive'           => false,
        'exclude_from_search'   => false,
        'publicly_queryable'    => true,
        'rewrite'               => false,
        'capability_type'       => 'page',
        'show_in_rest'          => false,
    );
    register_post_type( 'pof_tip', $args );

}
add_action( 'init', 'register_pof_tips', 0 );