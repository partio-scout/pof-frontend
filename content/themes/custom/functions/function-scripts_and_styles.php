<?php

function enqueue_styles_and_scripts(){

	// replace jquery for front end with a newer version
	if ( ! is_admin() ) {
		wp_deregister_script( 'jquery' );
		wp_register_script( 'jquery', ("https://ajax.googleapis.com/ajax/libs/jquery/2.1.4/jquery.min.js"), false, '2.1.5' );
	}

	// jquery
	wp_enqueue_script( 'jquery' );
	// foundation
	wp_enqueue_script( 'foundation', get_template_directory_uri().'/bower_components/foundation/js/foundation/foundation.js', array('jquery'), null, true);
	wp_enqueue_script( 'foundation-equalizer', get_template_directory_uri().'/bower_components/foundation/js/foundation/foundation.equalizer.js', array('foundation'), null, true);
	wp_enqueue_script( 'foundation-reveal', get_template_directory_uri().'/bower_components/foundation/js/foundation/foundation.reveal.js', array('foundation'), null, true);
	// modernizr
	wp_enqueue_script( 'modernizr', get_template_directory_uri().'/bower_components/modernizr/modernizr.js', array(), null, true);
	// enquire
	wp_enqueue_script( 'enquire-js', get_template_directory_uri().'/bower_components/enquire/dist/enquire.min.js', array(), null, true);
	// slick slider js
	wp_enqueue_script( 'slick-js', get_template_directory_uri().'/bower_components/slick.js/slick/slick.min.js', array('jquery'), null, true);
	// list js
	wp_enqueue_script( 'list-js', get_template_directory_uri().'/bower_components/list.js/dist/list.min.js', array('jquery'), null, true);
	// fastclick
	wp_enqueue_script( 'fastclick', get_template_directory_uri().'/bower_components/fastclick/lib/fastclick.js', array('jquery'), null, true);
	// dotdotdot
	wp_enqueue_script( 'dotdotdot', get_template_directory_uri().'/bower_components/jquery.dotdotdot/src/js/jquery.dotdotdot.min.js', array('jquery'), null, true);
	// tips
	wp_enqueue_script( 'tips', get_template_directory_uri().'/assets/js/min/tips-min.js', array('jquery', 'list-js'), null, true);
	// app scripts
	wp_enqueue_script( 'app', get_template_directory_uri().'/assets/js/min/app-min.js', array('jquery'), null, true);

	// fontawesome text fonts
	wp_enqueue_style( 'fontawesome', get_template_directory_uri().'/bower_components/fontawesome/css/font-awesome.min.css', false, 1, all );
	// animate custom
	wp_enqueue_style( 'animate', get_template_directory_uri().'/bower_components/animate-css/animate.min.css', false, 1, all );
	// slick slider css
	wp_enqueue_style( 'slick', get_template_directory_uri().'/bower_components/slick.js/slick/slick.css', false, 1, all );
	wp_enqueue_style( 'slick', get_template_directory_uri().'/bower_components/slick.js/slick/slick-theme.css', false, 1, all );
	// main styles
	wp_enqueue_style( 'app', get_template_directory_uri().'/assets/css/app.css', false, "1.0.2", all );

}

add_action( 'wp_enqueue_scripts', 'enqueue_styles_and_scripts' );


function enqueue_admin_styles_and_scripts( $hook ) {
	
	wp_enqueue_script('api_edit_block', get_template_directory_uri().'/assets/js/min/api-edit-block-min.js', array('jquery'), null, true);

}

add_action( 'admin_enqueue_scripts', 'enqueue_admin_styles_and_scripts' );