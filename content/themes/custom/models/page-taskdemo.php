<?php

class PageTaskDemo extends \DustPress\Model {

    public function Submodules() {

        $this->bind_sub("Header");
        $this->bind_sub("Footer");

    }

    public function Content() {

        $args = [ 'recursive' => true ];
        return \DustPress\Query::get_acf_post( get_the_ID(), $args );

    }

}