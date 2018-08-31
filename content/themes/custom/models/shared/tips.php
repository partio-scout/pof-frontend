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
                $tip->guid = get_post_meta(  $tip->ID, 'pof_tip_guid' );
            }
        }
        unset( $tip );

        return $tips;
    }

    public function SendUrl() {
        return get_field('tips-send-url', 'option');
    }
}
