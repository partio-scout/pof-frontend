<?php

class Attachments extends \DustPress\Model {

    // Bind translated strings.
    public function S() {

        $s = [
		    'liitteet'                      => __('Attachments', 'pof'),
		    'kuvat'                         => __('Pictures', 'pof'),
		    'tiedostot'                     => __('Documents', 'pof'),
		    'linkit'                        => __('Links', 'pof'),
        ];
        return $s;
    }
}