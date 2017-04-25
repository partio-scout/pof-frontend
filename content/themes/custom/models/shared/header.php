<?php

class Header extends \DustPress\Model {
    public function Content() {
        return true;
    }

    public function Analytics() {
    	return get_field('google_analytics', 'option');
    }
}