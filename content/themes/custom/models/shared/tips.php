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
        $tips_args = [
            'post_id'   => get_the_ID(),    // id of the curr page
            'status' => 'approve',
            'orderby'   => 'comment_date',
            'order'     => 'ASC'
        ];
        $tips = get_comments( $tips_args );
        foreach ($tips as $key => $tip) {
            $i++;
            $tips[$key]->comment_content = nl2br($tips[$key]->comment_content);
            $tips[$key]->fields = get_comment_meta($tip->comment_ID);

            if (isset($tips[$key]->fields['attachments'])) {
                $j++;
                $tips[$key]->fields['attachments'] = json_decode_pof($tips[$key]->fields['attachments'][0]);

                foreach ($tips[$key]->fields['attachments'] as $type => $attachment) {
                    if ($type == 'files') {
                        foreach ($attachment as $attachment_key => $file) {
                            $tips[$key]->fields['attachments']->{$type}[$attachment_key]->icon = get_template_directory_uri() . '/assets/img/file_'.substr($file->url, -3).'.png';
                        }
                    }
                }
            }
        }
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
