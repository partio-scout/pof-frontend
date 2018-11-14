<?php

use chillerlan\QRCode\{QRCode};

/**
 * Class Tips
 */
class Tips extends \DustPress\Model {

    // Load tips based on params
    public function Tips() {
        $args = $this->get_args();

        if ( isset( $args['post_id'] ) ) {
            $this->do_not_render();
            $this->validate_tip( $args );
            return;
        }
        $tips_args = [
            'post_id' => get_the_ID(),    // id of the curr page
            'status'  => 'approve',
            'orderby' => 'comment_date',
            'order'   => 'ASC',
        ];
        $tips      = get_comments( $tips_args );
        foreach ( $tips as &$tip ) {
            $tip->comment_content = nl2br( $tip->comment_content );
            $tip->fields          = get_comment_meta( $tip->comment_ID );

            // Parse tip guid from meta fields
            foreach ( $tip->fields as $key => $value ) {
                $key_arr = explode( '_', $key ); // guid is formatted as ag_{guid}_{lang}
                if ( count( $key_arr ) === 3 && $key_arr[0] === 'ag' ) {
                    $tip->guid = $key_arr[1];
                    break;
                }
            }

            if ( isset( $tip->fields['attachments'] ) ) {
                $tip->fields['attachments'] = json_decode_pof( $tip->fields['attachments'][0] );

                foreach ( $tip->fields['attachments'] as $type => $attachment ) {
                    if ( $type === 'files' ) {
                        foreach ( $attachment as $attachment_key => $file ) {
                            $tip->fields['attachments']->{$type}[ $attachment_key ]->icon = get_template_directory_uri() . '/assets/img/file_' . substr( $file->url, -3 ) . '.png';
                        }
                    }
                }
            }

            if ( ! empty( $tip->guid ) ) {
                $qr_data = get_permalink() . '/#/' . $tip->guid;
                $tip->qr_code = ( new QRCode() )->render( $qr_data );
            }
        }

        return $tips;
    }

    public function SendUrl() {
        return get_field( 'tips-send-url', 'option' );
    }

    // Init strings for UI
    public function S() {
        $args = $this->get_args();

        if ( isset( $args['post_id'] ) ) {
            return;
        }

        $s = [
            'vinkit'         => __( 'Tips', 'pof' ),
            'jarjestys'      => __( 'Order', 'pof' ),
            'uusimmat'       => __( 'Latest', 'pof' ),
            'suosituimmat'   => __( 'Most popular', 'pof' ),
            'nayta_lisaa'    => __( 'Show more', 'pof' ),
            'lisaa_vinkki'   => __( 'Add a tip', 'pof' ),
            'kuvat'          => __( 'Pictures', 'pof' ),
            'laheta'         => __( 'Send', 'pof' ),
            'nimi'           => __( 'Name', 'pof' ),
            'otsikko'        => __( 'Title', 'pof' ),
            'kirjoita_tahan' => __( 'Write your tips here', 'pof' ),
            'liite'          => __( 'Add an attachment', 'pof' ),
        ];

        return $s;
    }

}
