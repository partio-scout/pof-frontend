<?php

class PageAgegroupDemo extends \DustPress\Model {

    private $post_id;

    public function Submodules() {

        $this->bind_sub("Header");
        $this->bind_sub("Footer");

    }

    public function Content() {

        $post = \DustPress\Query::get_acf_post( get_the_ID() );
        $this->post_id = $post['ID'];

        // bind images into a more dust-friendly array
        map_api_images( $post['fields']['api_images'] );

        return $post;
    }

    // loads all child pages into a tree
    public function Children() {
        $dp         =
        $post_id    = $this->post_id;
        $child_tree = get_child_page_tree( $post_id, $dp );

        return $child_tree;
    }

}