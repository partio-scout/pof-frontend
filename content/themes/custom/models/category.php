<?php

class Category extends \DustPress\Model {

    public function Submodules() {

        $this->bind_sub("Header");
        $this->bind_sub("Footer");
        $this->bind_sub("Sidenav");
    }

    public function Content() {

        $post = array();
        $post['uutiset'] = array(1,2,3,4,5);

        return $post;
    }

}