<?php

// Remove WordPress discover links
remove_action( 'wp_head', 'wp_oembed_add_discovery_links', 10, 0 );
