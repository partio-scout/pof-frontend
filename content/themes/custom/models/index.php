<?php

class Index extends \DustPress\Model {
    public function submodules() {
        if ( defined('DUSTPRESS_AJAX') && DUSTPRESS_AJAX ) {
            exit();
        }
        // Include header in the page
        $this->bind_sub("Header");

        // Include footer in the page
        return "Footer";
    }
}