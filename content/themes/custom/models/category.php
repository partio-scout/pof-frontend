<?php

class Category extends \DustPress\Model {

    public function Submodules() {

        $this->bind_sub("Header");
        $this->bind_sub("Footer");
    }

    public function Content() {

        $post = array();
        $post['uutiset'] = array(1,2,3,4,5);

        return $post;
    }

}