<?php
/*
 Template name: Aktiviteettiryhmä
*/

class PageTaskgroup extends \DustPress\Model {

    private $post_id;
    private $post;
    private $dp; // helper

    public $api = [
        'translate'
    ];

    public function Submodules() {
        $this->bind_sub( 'ProgramLangnav', [ 'model' => 'PageTaskgroup' ] );
        $this->bind_sub("Attachments");
        $this->bind_sub("Header");
        $this->bind_sub("Footer");
        $this->bind_sub("Breadcrumbs");
    }

    // Bind basic page data with acf fields.
    // Loop must be used for get_the_x-type functions.
    public function Content() {
        $post_id = $this->post_id ? $this->post_id : get_the_ID();
        $post = \DustPress\Query::get_acf_post( $post_id );

        // bind images into a more dust-friendly array
        map_api_images( $post->fields['api_images'] );
        $post->subtaskgroup_term = json_decode_pof($post->fields['subtaskgroup_term']);
        $post->subtask_term = json_decode_pof($post->fields['subtask_term']);

        // bind attachments into a more dust-friendly array
        map_api_attachments( $post->fields['api_attachments'] );

        // store post id
        $this->post_id = $post_id;
        $this->post = $post;

        return $post;
    }

    // Taskgroup might have subtaskgroups
    // binds those to the task
    public function Subgroups() {
        $post_id = $this->post_id;

        $args = [
            'posts_per_page'    => -1,
            'post_type'         => 'page',
            'post_parent'       => $post_id,
            'post_status'       => 'publish',
            'meta_key'          => 'api_type',
            'meta_value'        => 'taskgroup'
        ];
        $groups  = \DustPress\Query::get_acf_posts( $args );

        return $groups;
    }

    // Binds all child pages in a tree.
    public function Children() {
        $post_id    = $this->post->ID;

        $child_tree = get_child_page_tree( $post_id, $dp );

        return sort_by_mandatory( $child_tree );
    }

    // Bind translated strings.
    public function S() {

        $s = [
            'valinnaiset'           => __('Selectable', 'pof'),
            'pakolliset'            => __('Obligatory', 'pof'),
        ];

        return $s;

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
        $content->Subgroups = $this->Subgroups();
        $this->set_template('content-taskgroup');

        return $content;
    }
}