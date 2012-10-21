<?php
/*
 * Copyright (c) 2012, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

abstract class sly_Controller_Backend extends sly_Controller_Base {
	public function __construct() {
		$this->setContentType('text/html');
		$this->setCharset('UTF-8');
	}

	protected function getViewFolder() {
		return SLY_SALLYFOLDER.'/backend/views/';
	}

	protected function redirect($params = array(), $page = null, $code = 302) {
		sly_Core::getCurrentApp()->redirect($page, $params, $code);
	}

	protected function redirectResponse($params = array(), $page = null, $code = 302) {
		return sly_Core::getCurrentApp()->redirectResponse($page, $params, $code);
	}
}
