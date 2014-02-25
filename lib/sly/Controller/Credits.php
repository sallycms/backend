<?php
/*
 * Copyright (c) 2013, webvariants GbR, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_Credits extends sly_Controller_Backend implements sly_Controller_Interface {
	protected function init() {
		$menu = null;

		// add link to help page for bugreports

		if ($this->getCurrentUser()->isAdmin()) {
			$menu = new sly_Layout_Navigation_Page('');
			$menu->addSubpage('credits', t('credits'));
			$menu->addSubpage('credits_bugreport', 'Fehler gefunden?');
		}

		$layout = $this->getContainer()->getLayout();
		$layout->pageHeader(t('credits'), $menu);
	}

	public function indexAction() {
		$this->init();
		$this->render('credits/index.phtml', array(), false);
	}

	public function checkPermission($action) {
		return sly_Util_User::getCurrentUser() !== null;
	}
}
