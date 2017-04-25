<?php
/*
 Template name: Yleinen

 If you modify this, check that same changes will find also from default template (Page) files
*/

class PageGeneral extends \DustPress\Model {
	private $post;

    public function Submodules() {

        $this->bind_sub("Header");
        $this->bind_sub("Footer");
        $this->bind_sub("Breadcrumbs");
        $this->bind_sub("Sidenav");        
    }

    public function Content() {
    	$this->post = \DustPress\Query::get_acf_post( get_the_ID() );
        return $this->post;

    }

    public function Hero() {
        $hero = [
            'image'  => \DustPress\Query::get_acf_posts( get_hero_args() ), 
            'slogan' => $this->post->fields['slogan']
        ];
        return $hero;
    }

}