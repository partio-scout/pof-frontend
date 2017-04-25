<?php

// add tag support to pages
function enable_tags_for_pages() {
	register_taxonomy_for_object_type('post_tag', 'page');
}

// tag hooks
add_action('init', 'enable_tags_for_pages');
