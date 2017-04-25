<?php

// execute if in admin
if ( is_admin() ) {

	$post_id = $_GET['post'] ? $_GET['post'] : $_POST['post_ID'] ;
	$template_file = get_post_meta($post_id,'_wp_page_template',TRUE);

	$program_templates = [
		'models/page-program.php',
		'models/page-agegroup.php',
		'models/page-taskgroup.php',
		'models/page-task.php'
	];

	// if we are editing a program page
	if ( in_array( $template_file, $program_templates ) ) {		
		// disable post content and title with a class and some js
		add_filter('wp_editor_settings', 'disable_editor');		
		add_action( 'post_edit_form_tag', 'disable_title' );		
	}

	function disable_editor( $settings ) {
		$inline_css = '
			<style>
				textarea.api-disabled {
					max-height: 350px;
					overflow: auto;
				}
				.api_edit_block_wrap .acf-repeater-add-row,
				.api_edit_block_wrap .acf-repeater-remove-row {
					display: none;
				}
				span#edit-slug-buttons {
					display: none;
				}
			</style>
		';
		$settings = array(
			'wpautop'             => true,
			'media_buttons'       => false,
			'default_editor'      => '',
			'drag_drop_upload'    => false,			
			'editor_css'          => $inline_css,
			'editor_class'        => 'api-disabled',
			'teeny'               => false,						
			'tinymce'             => false,
			'quicktags'           => false
		);
		return $settings;
	}

	function disable_title( $post ) {
		// give custom class to notify of a api related page
		echo 'class="api-related"';
	}

}