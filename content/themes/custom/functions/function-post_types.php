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