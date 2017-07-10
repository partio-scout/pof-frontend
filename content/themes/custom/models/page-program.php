<?php
/*
 Template name: Ohjelma
*/

class PageProgram extends \DustPress\Model {

    private $id;
    private $dp;
    private $post;

    public $api = [
        'translate'
    ];

    public function Submodules() {

        $this->bind_sub("Header");
        $this->bind_sub("Footer");
        $this->bind_sub("Breadcrumbs");
        $this->bind_sub( 'ProgramLangnav', [ 'model' => 'PageProgram' ] );
        $this->bind_sub("Sidenav");
    }

    public function Content() {

        if ( have_posts() ) : while ( have_posts() ) : the_post();
            $post_id = $this->post_id ? $this->post_id : get_the_ID();
            $post       = \DustPress\Query::get_acf_post( $post_id );
            $this->id   = $post_id;
            $this->post = $post;

            return $post;

        endwhile; endif;

    }

    // Binds all child pages.
    public function Children() {
        $args = [
            'posts_per_page'    => -1,
            'post_type'         => 'page',
            'post_parent'       => $this->post->ID,
            'post_status'       => 'publish',
            'orderby'           => 'menu_order',
            'order'             => 'ASC'
        ];
        $children = \DustPress\Query::get_acf_posts( $args );

        // bind images into a more dust-friendly array
        foreach ( $children as &$page ) {
            map_api_images( $page->fields['api_images'] );
        }

        return $children;
    }

    public function Hero() {
        $hero = [
            'image'  => \DustPress\Query::get_acf_posts( get_hero_args() ),
            'slogan' => $this->post->fields['slogan'] ? $this->post->fields['slogan'] : $this->post->fields['api_ingress']
        ];
        return $hero;
    }

    protected function translate() {
        $args = $this->get_args();

        $this->post_id  = $args['id'];
        $lang           = $args['lang'];

        $content = (object) [];
        $content->S         = $this->S();
        $content->Content   = $this->Content();
        $content->Children  = $this->Children();

        $this->set_template('content-program');

        return $content;
    }

}