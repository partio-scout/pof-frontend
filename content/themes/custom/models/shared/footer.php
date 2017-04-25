<?php

class Footer extends \DustPress\Model {

    public function S() {

        $s = [
            'takaisinylos'  => __( 'Back up', 'pof' ),
            'sivukartta'    => __( 'Sitemap', 'pof' ),
        ];

        return $s;
    }

}