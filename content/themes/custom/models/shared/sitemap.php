<?php

class Sitemap extends \DustPress\Model {

    public function Submodules() {

        $this->bind_sub("Sitemap");

    }

    public function Content() {

        $post = \DustPress\Query::get_acf_post( get_the_ID() );

        return $post;
    }

}