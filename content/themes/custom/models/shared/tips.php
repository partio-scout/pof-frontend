<?php

class Tips extends \DustPress\Model {

    // Load tips based on params
    public function Tips() {
        $args = $this->get_args();

        if ( isset( $args['post_id'] ) ) {
            $this->do_not_render();
            $this->validate_tip( $args );
            return;
        }

        $args = array(
            'post_per_page' => 100,
            'post_type' => 'pof_tip',
            'post_status' => 'publish',
            'meta_key' => 'pof_tip_parent',
            'meta_compare' => '=',
            'meta_value' => get_the_ID(),
        );

        $query = new WP_Query( $args );
        $tips = $query->posts;

        if( $query->found_posts > 0 ) {
            foreach ( $tips as &$tip ) {
                $tip->meta = get_post_meta( 'pof_tip_guid', $tip->ID );
            }
        }
        unset( $tip );

        return $tips;
    }

    public function SendUrl() {
        return get_field('tips-send-url', 'option');
    }

    // Init strings for UI
    public function S() {
        $args = $this->get_args();

        if ( isset( $args['post_id'] ) ) {
            return;
        }

        $s = [
            'vinkit'            => __( 'Tips', 'pof' ),
            'jarjestys'         => __( 'Order', 'pof' ),
            'uusimmat'          => __( 'Latest', 'pof' ),
            'suosituimmat'      => __( 'Most popular', 'pof' ),
            'nayta_lisaa'       => __( 'Show more', 'pof' ),
            'lisaa_vinkki'      => __( 'Add a tip', 'pof' ),
            'kuvat'             => __( 'Pictures', 'pof' ),
            'laheta'            => __( 'Send', 'pof' ),
            'nimi'              => __( 'Name', 'pof'),
            'otsikko'           => __( 'Title', 'pof'),
            'kirjoita_tahan'    => __( 'Write your tips here', 'pof'),
            'liite'             => __( 'Add an attachment', 'pof')
        ];

        return $s;
    }

}
