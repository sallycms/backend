<?php
/*
 * Copyright (c) 2014, webvariants GmbH & Co. KG, http://www.webvariants.de
 *
 * This file is released under the terms of the MIT license. You can find the
 * complete text in the attached LICENSE file or online at:
 *
 * http://www.opensource.org/licenses/mit-license.php
 */

class sly_Controller_System_Setup extends sly_Controller_System {
	public function indexAction() {
		$this->init();

		$this->render('system/setup.phtml', array(), false);
	}

	public function setupAction() {
		$this->init();

		// @edge: is sly_local.yml setup deprecated?

		$this->getContainer()->getConfig()->set('setup', true)->store();
		$this->redirect(array(), '');
	}
}
