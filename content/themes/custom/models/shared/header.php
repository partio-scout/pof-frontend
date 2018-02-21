<?php

class Header extends \DustPress\Model {
    public function Content() {
        return true;
    }

    public function Analytics() {
    	return get_field('google_analytics', 'option');
    }

    public function LangSwitcher() {
        $navigation = new Sidenav();
        $lang = $navigation->LangSwitcher();
    	return $lang;
    }

    public function MainMenu() {
        $navigation = new Sidenav();
        $menu = $navigation->Content();
    	return $menu;
    }

    public function S() {
        $navigation = new Sidenav();
        $s = $navigation->S();
    	return $s;
    }
}