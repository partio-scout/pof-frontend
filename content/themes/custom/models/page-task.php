<?php
/*
 Template name: Aktiviteetti
*/

class PageTask extends \DustPress\Model {

    private $post_id;
    private $helper;
    private $page;
    private $par; // parent

    public $api = [
        'translate'
    ];

    /**
     * Initialize this mobel by loading submodels.
     */
    public function init() {
        $this->post_id = get_the_ID();
        $this->bind_sub( 'ProgramLangnav', [ 'model' => 'PageTask' ] );
        $this->bind_sub("Attachments");
        $this->bind_sub("Tips");
        $this->bind_sub("Header");
        $this->bind_sub("Footer");
        $this->bind_sub("Breadcrumbs");
    }

    // Bind basic page data with acf fields.
    // Loop must be used for get_the_x-type functions.
    public function Content() {

        $page = \DustPress\Query::get_acf_post( $this->post_id, [ 'output' => 'ARRAY_A' ] );

        // bind images into a more dust-friendly array
        map_api_images( $page['fields']['api_images'] );

        // bind attachments into a more dust-friendly array
        map_api_attachments( $page['fields']['api_attachments'] );

        // bind tags into a dust-friendly array
        map_api_tags( $page['fields']['tags'] );

        // get wp tags
        $page['wp_tags'] = get_the_tags();

        // get search page permalink for current language
        $page['search_page'] = get_search_page( $page['fields']['api_lang'] );

        if ($page['fields']['level'] <= 0) {
            unset($page['fields']['level']);
        }
        $this->page = $page;
        return (object) $page;
    }

    // Bind the parent page.
    public function Parent() {

        $p          = $this->page;
        $this->par  = \DustPress\Query::get_post( $p['post_parent'], [ 'output' => 'ARRAY_A' ] );

        return $this->par;
    }

    // Bind the sibling pages.
    public function Siblings() {

        $args = [
            'post_type'         => 'page',
            'post_status'       => 'publish',
            'post_parent'       => $this->par['ID'],
            'exclude'           => $this->page['ID'],
            'posts_per_page'    => -1
        ];
        return \DustPress\Query::get_posts( $args );
    }

    public function Hero() {
        $hero = [
            'image'  => \DustPress\Query::get_acf_posts( get_hero_args() ),
            'slogan' => $this->page['fields']['slogan'] ? $this->page['fields']['slogan'] : $this->page['fields']['api_ingress']
        ];
        return $hero;
    }

    // Bind translated strings.
    public function S() {

        $s = [
            'suoritustiedot'                => __( 'Execution and goals', 'pof' ),
            'lisatiedot'                    => __( 'Additional information', 'pof' ),
            'tarvikkeet'                    => __( 'Equipment', 'pof' ),
            'aktiviteettiryhma'             => __( 'Task group', 'pof' ),
            'ryhman_muut_aktiviteetit'      => __( 'Paraller', 'pof' ),
            'valitse'                       => __( 'Choose', 'pof' ),
            'taitoalueet'                   => __( 'Skill areas', 'pof' ),
            'vinkit'                        => __( 'Tips', 'pof' ),
            'kommentit'                     => __( 'Comments', 'pof' ),
            'avainsanat'                    => __( 'Keywords', 'pof' ),
            'suoritus_valmistelu_kesto'     => __( 'Preparation', 'pof' ),
            'suoritus_kesto'                => __( 'Length', 'pof' ),
            'taso'                          => __( 'Level', 'pof' ),
            'johtajan_tehtava'              => __( 'Leaders task', 'pof' ),
            'johtamistaito'                 => __( 'Leadership skills', 'pof' ),
            'pakollisuus'                   => __( 'Obligatory', 'pof' ),
            'ryhmakoko'                     => __( 'Group size', 'pof' ),
            'paikka'                        => __( 'Place', 'pof' ),
            'kasvatustavoitteet'            => __( 'Educational goals', 'pof' ),
            'tavoite'                       => __( 'Goal', 'pof' ),
            'kuvaus'                        => __( 'Description', 'pof' ),
        ];
        return $s;
    }

    protected function translate() {
        $args = $this->get_args();

        $this->post_id  = $args['id'];
        $lang           = $args['lang'];

        $content = (object) [];
        $content->S         = $this->S();
        $content->Content   = $this->Content();

        $this->set_template('content-task');

        return $content;
    }

}
