<?php

class Sidenavsearch extends \DustPress\Model {

    public function Content() {

        wp_enqueue_script( 'jquery-ui-slider', array('jquery'), null, true );
        wp_enqueue_script( 'search', get_template_directory_uri().'/assets/js/min/search-min.js', array('jquery'), null, true );

    }

}