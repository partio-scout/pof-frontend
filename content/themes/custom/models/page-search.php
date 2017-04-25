<?php
/*
 Template name: Haku
*/

class PageSearch extends \DustPress\Model {

	public function Submodules() {

		$this->bind_sub("Header");
		$this->bind_sub("Sidenavsearch");
		$this->bind_sub("Footer");

	}

}